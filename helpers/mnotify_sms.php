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
 * mNotify (BMS) SMS — the second SMS provider, next to Hubtel.
 *
 * Contract (BMS API v2.0, https://developer.bms.africa/):
 *   POST https://api.mnotify.com/api/sms/quick?key=API_KEY
 *   Body: {"recipient": [msisdn], "sender": sender ID (max 11 chars),
 *          "message": text, "is_schedule": false, "schedule_date": ""}
 *   Success: "status": "success" with "code": "2000"; summary._id is the
 *   campaign id, summary.total_rejected counts refused numbers.
 *   GET https://api.mnotify.com/api/balance/sms?key=API_KEY
 *   → {"status":"success","balance":N,"bonus":N} — tests the key without
 *   sending anything.
 *
 * Credentials live in cdb_sms_settings (mnotify_api_key, mnotify_sender_id),
 * edited by super admins on Tools > SMS. Which provider sends is decided by
 * cdp_sendSms() in helpers/hubtel_sms.php.
 */

if (!function_exists('cdp_mnotifySmsConfig')) {

    /** @return array{api_key:string,sender:string} */
    function cdp_mnotifySmsConfig()
    {
        return [
            'api_key' => trim(cdp_smsSetting('mnotify_api_key')),
            // mNotify caps sender IDs at 11 characters and only sends from IDs
            // approved on the account.
            'sender'  => mb_substr(trim(cdp_smsSetting('mnotify_sender_id')), 0, 11),
        ];
    }

    /** Every credential present: the engine may talk to mNotify. */
    function cdp_mnotifySmsConfigured()
    {
        $c = cdp_mnotifySmsConfig();
        return $c['api_key'] !== '' && $c['sender'] !== '';
    }
}

if (!function_exists('cdp_mnotifyRequest')) {

    /**
     * One call to the mNotify API.
     *
     * @return array{ok:bool,code:int,json:?array,raw:string,error:string}
     */
    function cdp_mnotifyRequest($method, $path, array $body = null, $apiKey = null)
    {
        $apiKey = $apiKey !== null ? (string) $apiKey : cdp_mnotifySmsConfig()['api_key'];
        $url    = 'https://api.mnotify.com/api/' . ltrim($path, '/') . '?key=' . rawurlencode($apiKey);
        $ch     = curl_init($url);
        $opts   = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?: new stdClass());
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $resp === false) {
            return ['ok' => false, 'code' => $code, 'json' => null, 'raw' => '', 'error' => $err ?: 'No response'];
        }
        $j = json_decode((string) $resp, true);
        return ['ok' => true, 'code' => $code, 'json' => is_array($j) ? $j : null,
                'raw' => 'HTTP ' . $code . ': ' . mb_substr((string) $resp, 0, 2000), 'error' => ''];
    }
}

if (!function_exists('cdp_sendMnotifySms')) {

    /**
     * Send one SMS through mNotify. $to is digits-only international form
     * (cdp_normalizePhone). Writes one cdb_message_log row whatever happens.
     *
     * @return array{success:bool,message:string}
     */
    function cdp_sendMnotifySms($to, $content, array $who = [])
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

        if (!cdp_mnotifySmsConfigured()) {
            $log('skipped', 'mNotify SMS is not configured.');
            return ['success' => false, 'message' => 'mNotify SMS is not configured.'];
        }
        if ($to === '' || $content === '') {
            $log('skipped', $to === '' ? 'No usable phone number.' : 'Nothing to send.');
            return ['success' => false, 'message' => 'Nothing to send.'];
        }

        $c   = cdp_mnotifySmsConfig();
        $res = cdp_mnotifyRequest('POST', 'sms/quick', [
            'recipient'     => [$to],
            'sender'        => $c['sender'],
            'message'       => $content,
            'is_schedule'   => false,
            'schedule_date' => '',
        ]);

        if (!$res['ok']) {
            $log('failed', 'Could not reach mNotify SMS: ' . $res['error']);
            return ['success' => false, 'message' => 'Could not reach mNotify SMS: ' . $res['error']];
        }

        $j = $res['json'];
        $accepted = is_array($j)
            && strtolower((string) ($j['status'] ?? '')) === 'success'
            && (string) ($j['code'] ?? '') === '2000'
            && (int) ($j['summary']['total_rejected'] ?? 0) === 0;
        if ($res['code'] >= 200 && $res['code'] < 300 && $accepted) {
            $ref = (string) ($j['summary']['_id'] ?? '');
            $log('sent', 'Accepted by mNotify' . ($ref !== '' ? ' (' . $ref . ')' : ''), $res['raw']);
            return ['success' => true, 'message' => 'SMS accepted' . ($ref !== '' ? ' (' . $ref . ')' : '')];
        }

        $why = (is_array($j) && !empty($j['message'])) ? (string) $j['message'] : 'HTTP ' . $res['code'];
        if (is_array($j) && (int) ($j['summary']['total_rejected'] ?? 0) > 0) {
            $why = 'the number was rejected';
        }
        $log('failed', 'mNotify refused the message: ' . $why, $res['raw']);
        return ['success' => false, 'message' => 'mNotify SMS refused the message: ' . $why];
    }
}

if (!function_exists('cdp_mnotifySmsBalance')) {

    /**
     * SMS credit left on the mNotify account — checks the API key without
     * sending anything.
     *
     * @return array{success:bool,message:string,balance?:int,bonus?:int}
     */
    function cdp_mnotifySmsBalance($apiKey = null)
    {
        $apiKey = $apiKey !== null ? trim((string) $apiKey) : cdp_mnotifySmsConfig()['api_key'];
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Enter the mNotify API key first.'];
        }
        $res = cdp_mnotifyRequest('GET', 'balance/sms', null, $apiKey);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Could not reach mNotify: ' . $res['error']];
        }
        $j = $res['json'];
        if (is_array($j) && strtolower((string) ($j['status'] ?? '')) === 'success' && isset($j['balance'])) {
            return ['success' => true, 'message' => 'mNotify key works.',
                    'balance' => (int) $j['balance'], 'bonus' => (int) ($j['bonus'] ?? 0)];
        }
        $why = (is_array($j) && !empty($j['message'])) ? (string) $j['message'] : 'HTTP ' . $res['code'];
        return ['success' => false, 'message' => 'mNotify refused the key: ' . $why];
    }
}
