<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

/**
 * Hubtel SMS — the system's SMS provider.
 *
 * Contract (Hubtel Notification APIs, SMS):
 *   POST https://sms.hubtel.com/v1/messages/send
 *   Authorization: Basic base64(ClientID:ClientSecret)
 *   Body: {"From": sender ID (max 11 chars), "To": msisdn, "Content": text}
 *   Success: HTTP 2xx AND "status": 0 (a 2xx with another status is a refusal:
 *   unregistered sender ID, bad number, no balance).
 *
 * The SMS API keys are their own credentials on the Hubtel portal ("SMS API
 * Keys"), separate from any payment keys. They live in cdb_sms_settings
 * (key/value), edited by super admins only on Tools > SMS. The table is
 * created on demand so an environment that has not run sql/hubtel_sms.sql
 * keeps working: with no table there are simply no credentials.
 *
 * When SMS goes out:
 *   - the per-shipment "notify by SMS" toggles (sendNotificationSMS);
 *   - a copy of every WhatsApp notification while the SMS switch
 *     (cdb_settings.active_sms) is on and the customer has not opted out;
 *   - one-time sign-in and reset codes always, as soon as the credentials
 *     are complete — the switch does not hold them back.
 *
 * Every SMS is plain text: WhatsApp markup, HTML and emoji are removed before
 * sending (cdp_smsPlainText), whichever path the text came from.
 */

if (!function_exists('cdp_smsSettingsEnsureTable')) {

    /** Creates cdb_sms_settings when missing. True when it exists afterwards. */
    function cdp_smsSettingsEnsureTable()
    {
        static $ready = null;
        if ($ready === true) {
            return true;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_sms_settings (
                setting_key   VARCHAR(64)  NOT NULL,
                setting_value TEXT         NULL,
                updated_by    INT          NULL,
                updated_at    DATETIME     NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $ready = ($db->cdp_execute() !== false);
        } catch (Throwable $e) {
            $ready = false;
        }
        if ($ready) {
            cdp_smsSettingsTableExists(true); // the cached "absent" answer is stale now
        }
        return $ready;
    }

    /**
     * True when cdb_sms_settings exists (read-only check, no DDL). The answer
     * is cached per request; $known overrides it once the table is created.
     */
    function cdp_smsSettingsTableExists($known = null)
    {
        static $exists = null;
        if ($known !== null) {
            return $exists = (bool) $known;
        }
        if ($exists !== null) {
            return $exists;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS c FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_sms_settings'");
            $row = $db->cdp_registro();
            $exists = ($row && (int) $row->c > 0);
        } catch (Throwable $e) {
            $exists = false;
        }
        return $exists;
    }

    /** All stored SMS settings as key => value (empty when the table is absent). */
    function cdp_smsSettingsAll($fresh = false)
    {
        static $cache = null;
        if ($cache !== null && !$fresh) {
            return $cache;
        }
        $cache = [];
        if (!cdp_smsSettingsTableExists()) {
            return $cache;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT setting_key, setting_value FROM cdb_sms_settings");
            $rows = $db->cdp_registros();
            foreach ((array) $rows as $r) {
                $cache[(string) $r->setting_key] = (string) $r->setting_value;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    function cdp_smsSetting($key, $default = '')
    {
        $all = cdp_smsSettingsAll();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    function cdp_smsSettingSet($key, $value, $userId = null)
    {
        if (!cdp_smsSettingsEnsureTable()) {
            return false;
        }
        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_sms_settings (setting_key, setting_value, updated_by, updated_at)
            VALUES (:k, :v, :u, :t)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
        $db->bind(':k', (string) $key);
        $db->bind(':v', (string) $value);
        $db->bind(':u', $userId !== null ? (int) $userId : null);
        $db->bind(':t', date('Y-m-d H:i:s'));
        $ok = ($db->cdp_execute() !== false);
        cdp_smsSettingsAll(true);
        return $ok;
    }
}

if (!function_exists('cdp_smsCanManage')) {

    /** Only a super admin sees or changes the SMS settings (they hold API keys). */
    function cdp_smsCanManage($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        require_once __DIR__ . '/rbac.php';
        return cdp_roleHasFlag((int) $user->userlevel, 'is_superadmin');
    }
}

if (!function_exists('cdp_hubtelSmsConfig')) {

    /** @return array{client_id:string,client_secret:string,sender:string} */
    function cdp_hubtelSmsConfig()
    {
        return [
            'client_id'     => trim(cdp_smsSetting('hubtel_client_id')),
            'client_secret' => trim(cdp_smsSetting('hubtel_client_secret')),
            // Hubtel caps sender IDs at 11 characters and only accepts IDs
            // registered on the account.
            'sender'        => mb_substr(trim(cdp_smsSetting('hubtel_sender_id')), 0, 11),
        ];
    }

    /** Every credential present: the engine may talk to Hubtel. */
    function cdp_hubtelSmsReady()
    {
        $c = cdp_hubtelSmsConfig();
        return $c['client_id'] !== '' && $c['client_secret'] !== '' && $c['sender'] !== '';
    }
}

if (!function_exists('cdp_smsPlainText')) {

    /**
     * The one cleaner every SMS passes through.
     *
     *  - HTML entities decoded and tags removed (an SMS shows "&amp;" verbatim);
     *  - WhatsApp markup (*bold*, _italic_, ~strike~, ```) reduced to the words;
     *  - emoji and pictographs removed, with their joiners, skin tones,
     *    variation selectors, keycaps and flags;
     *  - typographic quotes, dashes, ellipsis and bullets turned into their
     *    plain keyboard forms, so the text stays in the cheap GSM alphabet
     *    instead of forcing 70-character Unicode segments;
     *  - runs of spaces and blank lines collapsed.
     */
    function cdp_smsPlainText($text)
    {
        $text = (string) $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = preg_replace('/\*([^*\n]+)\*/u', '$1', $text);
        $text = preg_replace('/(?<![A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/u', '$1', $text);
        $text = preg_replace('/~([^~\n]+)~/u', '$1', $text);
        $text = str_replace('```', '', $text);

        $text = strtr($text, [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{2032}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{2033}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-',
            "\u{2026}" => '...',
            "\u{2022}" => '-', "\u{25CF}" => '-', "\u{25AA}" => '-', "\u{25B8}" => '-', "\u{25BA}" => '-',
            "\u{2192}" => '->', "\u{2190}" => '<-',
            "\u{00A0}" => ' ', "\u{2009}" => ' ', "\u{202F}" => ' ',
        ]);

        $emoji = '/[' .
            '\x{1F000}-\x{1FAFF}' .  // pictographs, emoticons, transport, symbols, flags letters
            '\x{2600}-\x{27BF}' .    // misc symbols + dingbats (sun, check marks, stars...)
            '\x{2300}-\x{23FF}' .    // technical (watch, hourglass, play buttons)
            '\x{2B00}-\x{2BFF}' .    // arrows and stars used as emoji
            '\x{2190}-\x{21FF}' .    // remaining arrows
            '\x{25A0}-\x{25FF}' .    // geometric shapes
            '\x{2900}-\x{297F}' .    // supplemental arrows
            '\x{3030}\x{303D}\x{3297}\x{3299}' .
            '\x{2122}\x{2139}\x{24C2}' .
            '\x{200D}\x{20E3}' .     // zero-width joiner, keycap
            '\x{FE00}-\x{FE0F}' .    // variation selectors
            '\x{E0020}-\x{E007F}' .  // tag characters (subdivision flags)
            ']/u';
        $text = preg_replace($emoji, '', $text);

        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/ +([,.!?;:])/u', '$1', $text); // "Ama 👋," leaves "Ama ," once the emoji is gone
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
        return trim($text);
    }
}

if (!function_exists('cdp_sendHubtelSms')) {

    /**
     * Send one SMS. $to is digits-only international form (cdp_normalizePhone).
     * Writes one cdb_message_log row whatever happens.
     *
     * @param string $to
     * @param string $content any text; cleaned by cdp_smsPlainText here
     * @param array  $who     recipient_user_id / recipient_name for the log
     * @return array{success:bool,message:string}
     */
    function cdp_sendHubtelSms($to, $content, array $who = [])
    {
        $to      = preg_replace('/\D+/', '', (string) $to);
        $content = cdp_smsPlainText($content);

        $log = function ($status, $detail, $response = null) use ($to, $content, $who) {
            if (!function_exists('cdp_msgLog')) {
                return;
            }
            cdp_msgLog([
                'channel'           => 'sms',
                'status'            => $status,
                'status_detail'     => $detail,
                'body'              => $content,
                'recipient_user_id' => (int) ($who['id'] ?? 0),
                'recipient_name'    => (string) ($who['name'] ?? ''),
                'recipient_to'      => $to,
                'provider_response' => $response,
            ]);
        };

        if (!cdp_hubtelSmsReady()) {
            $log('skipped', 'Hubtel SMS is not configured.');
            return ['success' => false, 'message' => 'Hubtel SMS is not configured.'];
        }
        if ($to === '' || $content === '') {
            $log('skipped', $to === '' ? 'No usable phone number.' : 'Nothing to send.');
            return ['success' => false, 'message' => 'Nothing to send.'];
        }

        $c  = cdp_hubtelSmsConfig();
        $ch = curl_init('https://sms.hubtel.com/v1/messages/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS     => json_encode([
                'From'    => $c['sender'],
                'To'      => $to,
                'Content' => $content,
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($c['client_id'] . ':' . $c['client_secret']),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $resp === false) {
            $log('failed', 'Could not reach Hubtel SMS: ' . $err);
            return ['success' => false, 'message' => 'Could not reach Hubtel SMS: ' . $err];
        }

        $raw = 'HTTP ' . $code . ': ' . mb_substr((string) $resp, 0, 2000);
        $j   = json_decode((string) $resp, true);
        if ($code >= 200 && $code < 300 && is_array($j) && (int) ($j['status'] ?? -1) === 0) {
            $ref = (string) ($j['messageId'] ?? '');
            $log('sent', 'Accepted by Hubtel' . ($ref !== '' ? ' (' . $ref . ')' : ''), $raw);
            return ['success' => true, 'message' => 'SMS accepted' . ($ref !== '' ? ' (' . $ref . ')' : '')];
        }

        $why = (is_array($j) && !empty($j['statusDescription'])) ? (string) $j['statusDescription']
             : ((is_array($j) && !empty($j['message'])) ? (string) $j['message'] : 'HTTP ' . $code);
        $log('failed', 'Hubtel refused the message: ' . $why, $raw);
        return ['success' => false, 'message' => 'Hubtel SMS refused the message: ' . $why];
    }
}

if (!function_exists('cdp_smsToRecipient')) {

    /**
     * Send an SMS to a user/recipient record (anything with ->phone, and
     * optionally ->country / ->id / ->fname). Normalises the number with the
     * same rules WhatsApp uses, but never asks whether the number is on
     * WhatsApp: a number can take SMS without being on WhatsApp.
     */
    function cdp_smsToRecipient($recipient, $body, $countryHint = null)
    {
        if (is_array($recipient)) {
            $recipient = (object) $recipient;
        }
        $who = function_exists('cdp_msgRecipientFromEntity')
            ? cdp_msgRecipientFromEntity($recipient)
            : ['id' => (int) ($recipient->id ?? 0), 'name' => '', 'phone' => (string) ($recipient->phone ?? '')];

        $raw  = trim((string) ($recipient->phone ?? ''));
        $hint = $recipient->country ?? ($recipient->c_country ?? $countryHint);
        $to   = ($raw !== '' && function_exists('cdp_normalizePhone'))
            ? (string) cdp_normalizePhone($raw, $hint)
            : preg_replace('/\D+/', '', $raw);

        return cdp_sendHubtelSms($to, $body, $who);
    }
}

if (!function_exists('cdp_smsSendNotification')) {

    /**
     * The per-shipment SMS the "notify by SMS" toggles ask for — the body of
     * both sendNotificationSMS() copies (ajax/notify_sms/). Hubtel sends it;
     * the older ClickSend client is reached only while Hubtel holds no
     * credentials, so an install still configured that way keeps texting.
     * Every outcome except "toggle not ticked" leaves a message-log row.
     */
    function cdp_smsSendNotification($user, $sms_body, $notify)
    {
        if (!$notify) {
            return ['success' => false, 'message' => 'Notification not enabled'];
        }
        if (is_array($user)) {
            $user = (object) $user;
        }
        $settings = cdp_getSettingsCourier();
        $who = function_exists('cdp_msgRecipientFromEntity')
            ? cdp_msgRecipientFromEntity($user)
            : ['id' => (int) ($user->id ?? 0), 'name' => '', 'phone' => (string) ($user->phone ?? '')];
        $log = function ($status, $detail) use ($who, $sms_body) {
            if (!function_exists('cdp_msgLog')) {
                return;
            }
            cdp_msgLog(['channel' => 'sms', 'status' => $status, 'status_detail' => $detail,
                'body' => cdp_smsPlainText((string) $sms_body),
                'recipient_user_id' => $who['id'], 'recipient_name' => $who['name'], 'recipient_to' => $who['phone']]);
        };

        if (intval($settings->active_sms ?? 0) !== 1) {
            $log('skipped', 'SMS is switched off (Tools > SMS).');
            return ['success' => false, 'message' => 'SMS is switched off in settings.'];
        }
        if (isset($user->notify_sms) && intval($user->notify_sms) === 0) {
            $log('skipped', 'The customer opted out of SMS.');
            return ['success' => false, 'message' => 'The customer opted out of SMS.'];
        }
        if ($sms_body === null || cdp_smsPlainText($sms_body) === '') {
            $log('failed', 'No body defined for the SMS.');
            return ['success' => false, 'message' => 'Error: No body defined for the SMS'];
        }
        if (trim((string) ($user->phone ?? '')) === '') {
            $log('skipped', 'No phone number on file.');
            return ['success' => false, 'message' => 'No phone number on file.'];
        }

        if (cdp_hubtelSmsReady()) {
            return cdp_smsToRecipient($user, $sms_body); // writes its own log row
        }

        // Legacy ClickSend credentials (cdb_settings.twilio_sms_sid/_token).
        if (trim((string) ($settings->twilio_sms_sid ?? '')) === '' || trim((string) ($settings->twilio_sms_token ?? '')) === '') {
            $log('skipped', 'No SMS provider is configured.');
            return ['success' => false, 'message' => 'No SMS provider is configured.'];
        }
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $config = \ClickSend\Configuration::getDefaultConfiguration()
                ->setUsername($settings->twilio_sms_sid)
                ->setPassword($settings->twilio_sms_token);
            $api = new \ClickSend\Api\SMSApi(new \GuzzleHttp\Client(), $config);
            $msg = new \ClickSend\Model\SmsMessage();
            $msg->setBody(cdp_smsPlainText($sms_body));
            $msg->setTo((string) $user->phone);
            $msg->setSource('sdk');
            $batch = new \ClickSend\Model\SmsMessageCollection();
            $batch->setMessages([$msg]);
            $api->smsSendPost($batch);
            $log('sent', 'Accepted by ClickSend');
            return ['success' => true, 'message' => 'Notification sent successfully'];
        } catch (Throwable $e) {
            $log('failed', 'ClickSend: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Exception when calling SMSApi->smsSendPost: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('cdp_smsCopyOwed')) {

    /**
     * Does a WhatsApp notification owe an SMS copy?
     *   force_sms => true   one-time codes: always, once credentials exist;
     *   allow_sms => false  the caller texts this event itself (its own SMS
     *                       toggle) — no copy, or the customer gets two;
     *   otherwise           only while the SMS switch is on and the
     *                       recipient has not opted out (notify_sms = 0).
     */
    function cdp_smsCopyOwed($recipient, array $opts, $settings = null)
    {
        if (!cdp_hubtelSmsReady()) {
            return false;
        }
        if (!empty($opts['force_sms'])) {
            return true;
        }
        if (array_key_exists('allow_sms', $opts) && !$opts['allow_sms']) {
            return false;
        }
        $settings = $settings ?: cdp_getSettingsCourier();
        if (intval($settings->active_sms ?? 0) !== 1) {
            return false;
        }
        return !(is_object($recipient) && isset($recipient->notify_sms) && intval($recipient->notify_sms) === 0);
    }
}
