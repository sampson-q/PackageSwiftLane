<?php
// ini_set('display_errors', 1);
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/helpers/querys.php';
require_once $projectRoot . '/helpers/whatsapp.php';
require_once $projectRoot . '/helpers/message_log.php';
require_once $projectRoot . '/helpers/hubtel_sms.php';
// helpers/vendor/autoload.php is NOT loaded here: this file talks to UltraMsg
// with cURL and uses nothing from that vendor tree (ClickSend SDK, Guzzle,
// Symfony polyfills). Loading it cost 50-170 ms on every page that sends a
// WhatsApp message, the login page included. The SMS services load it themselves.

/**
 * Send a WhatsApp message via the UltraMsg API.
 *
 * Respects the global `active_whatsapp` switch, normalises the recipient phone,
 * and skips numbers the API confirms are NOT on WhatsApp (fail-open: unknown
 * numbers are still sent). See helpers/whatsapp.php.
 *
 * Every attempt — sent, failed or skipped — is written to cdb_message_log
 * (helpers/message_log.php), so the Message Logs page shows the whole system's
 * WhatsApp traffic without callers doing anything. Callers that know the
 * shipment / template describe it with cdp_msgSetContext() beforehand.
 *
 * @param object     $sender                 Record with at least a `phone` property.
 * @param string     $template_whatsapp_body The fully-rendered message body to send.
 * @param mixed|null $countryHint            Optional recipient country (id/iso/name/code).
 * @param array      $opts                   SMS copy: ['force_sms' => true] for one-time
 *                                           codes, ['allow_sms' => false] when the caller
 *                                           texts the event itself (helpers/hubtel_sms.php).
 *                                           Media: ['media' => ['kind' => image|video|document,
 *                                           'url' => public URL, 'filename' => name]] sends the
 *                                           file with the body as its caption.
 * @return array ['success' => bool, 'skipped' => bool, 'message' => string,
 *                'wa_sent' => bool, 'sms_sent' => bool]
 */
function sendNotificationWhatsApp_v2($sender, $template_whatsapp_body, $countryHint = null, array $opts = []) {
    // Respond first, deliver after (helpers/after_response.php). The number
    // check and the send are two API calls; a request that opted into queued
    // notifications gets an optimistic result and the message log records the
    // real one.
    if (function_exists('cdp_notifyDeferred') && cdp_notifyDeferred()) {
        cdp_afterResponse(function () use ($sender, $template_whatsapp_body, $countryHint, $opts) {
            sendNotificationWhatsApp_v2($sender, $template_whatsapp_body, $countryHint, $opts);
        });
        return ['success' => true, 'queued' => true, 'message' => 'Queued for delivery.'];
    }

    // Fetch your API credentials & endpoint
    $settings = cdp_getSettingsCourier();

    // SMS copy, side by side with WhatsApp (helpers/hubtel_sms.php decides
    // whether one is owed). It goes out once, whatever happened to the
    // WhatsApp send, and the result reports both channels.
    $smsOwed = cdp_smsCopyOwed($sender, $opts, $settings);
    $finish = function (array $res) use ($smsOwed, $sender, $template_whatsapp_body, $countryHint) {
        $waOk = !empty($res['success']);
        $res['wa_sent']  = $waOk;
        $res['sms_sent'] = false;
        if (!$smsOwed) {
            return $res;
        }
        $sms = cdp_smsToRecipient($sender, $template_whatsapp_body, $countryHint);
        $res['sms_sent'] = !empty($sms['success']);
        if ($res['sms_sent']) {
            $res['success'] = true;
            $res['message'] = $waOk ? $res['message'] . ' + SMS copy' : 'Sent by SMS (' . $res['message'] . ')';
        } else {
            $res['message'] .= ' (SMS copy failed: ' . ($sms['message'] ?? '') . ')';
        }
        return $res;
    };

    $who = cdp_msgRecipientFromEntity($sender);
    $log = function ($status, $detail, $to = '', $response = null) use ($who, $template_whatsapp_body) {
        cdp_msgLog([
            'channel'           => 'whatsapp',
            'status'            => $status,
            'status_detail'     => $detail,
            'body'              => $template_whatsapp_body,
            'recipient_user_id' => $who['id'],
            'recipient_name'    => $who['name'],
            'recipient_to'      => $to !== '' ? $to : $who['phone'],
            'provider_response' => $response,
        ]);
    };

    // Global kill-switch: if WhatsApp is disabled, send nothing.
    if (intval($settings->active_whatsapp) != 1) {
        $log('skipped', 'WhatsApp is not active in settings.');
        return $finish([
            'success' => false,
            'skipped' => true,
            'message' => 'WhatsApp is not active in settings.',
        ]);
    }

    // Normalise + verify the destination number (anti-ban gate).
    $target = cdp_wa_resolveSendTarget($sender, $countryHint);
    if ($target['skip']) {
        $log('skipped', $target['reason'], (string) $target['phone']);
        return $finish([
            'success' => false,
            'skipped' => true,
            'message' => $target['reason'],
        ]);
    }

    // Media (helpers/push_compose.php): an image, video or document goes to
    // its own UltraMsg endpoint with the text as the caption. A text longer
    // than WhatsApp's caption limit follows the media as a second message.
    $media = (isset($opts['media']) && is_array($opts['media']) && !empty($opts['media']['url'])
        && in_array($opts['media']['kind'] ?? '', ['image', 'video', 'document'], true)) ? $opts['media'] : null;

    $sends = [];
    if ($media) {
        $kind    = $media['kind'];
        $caption = (string) $template_whatsapp_body;
        $tail    = '';
        if (function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') > 1024 : strlen($caption) > 1024) {
            $tail = $caption;
            $caption = '';
        }
        $p = ['to' => $target['phone'], $kind => (string) $media['url'], 'caption' => $caption];
        if ($kind === 'document') {
            $p['filename'] = (string) ($media['filename'] ?? 'document');
        }
        $sends[] = ['endpoint' => 'messages/' . $kind, 'params' => $p,
            'label' => ucfirst($kind) . ' ' . (string) ($media['filename'] ?? '')];
        if ($tail !== '') {
            $sends[] = ['endpoint' => 'messages/chat', 'params' => ['to' => $target['phone'], 'body' => $tail], 'label' => 'Text'];
        }
    } else {
        $sends[] = ['endpoint' => 'messages/chat', 'params' => ['to' => $target['phone'], 'body' => $template_whatsapp_body], 'label' => ''];
    }

    $ids = [];
    $accepted = 0;
    foreach ($sends as $send) {
        $r = cdp_wa_postUltraMsg($settings, $send['endpoint'], $send['params'], $target['phone']);
        $prefix = $send['label'] !== '' && count($sends) > 1 ? trim($send['label']) . ': ' : '';
        if (!$r['ok']) {
            if ($accepted > 0) {
                // The attachment already reached the recipient: report a partial
                // delivery, so a retry does not send the attachment twice.
                $detail = 'Partly delivered: ' . $media['kind'] . ' accepted'
                    . ($ids ? ' (id ' . implode(', ', $ids) . ')' : '') . ', ' . $prefix . $r['message'];
                $log('sent', $detail, $target['phone'], $r['raw']);
                return $finish([
                    'success' => true,
                    'partial' => true,
                    'message' => 'WhatsApp attachment sent, but the follow-up text failed: ' . $r['message'],
                ]);
            }
            $log('failed', $prefix . $r['message'], $target['phone'], $r['raw']);
            return $finish(['success' => false, 'message' => $prefix . $r['message']]);
        }
        $accepted++;
        if ($r['id'] !== '') {
            $ids[] = $r['id'];
        }
        $lastRaw = $r['raw'];
    }

    // Explicit acceptance, or an ambiguous/unrecognised 2xx body (fail-open).
    $detail = $ids ? 'Accepted by UltraMsg (id ' . implode(', ', $ids) . ')' : 'Accepted by UltraMsg';
    if ($media) {
        $detail .= ' with ' . $media['kind'] . ' ' . (string) ($media['filename'] ?? '');
    }
    $log('sent', $detail, $target['phone'], $lastRaw ?? null);
    return $finish([
        'success' => true,
        'message' => "WhatsApp notification sent successfully",
    ]);
}

/**
 * One UltraMsg API call (messages/chat, messages/image, messages/video,
 * messages/document). A 200 from cURL does NOT mean UltraMsg accepted the
 * message: the JSON body is inspected so genuine rejects (expired instance,
 * bad token, quota, malformed body, media URL it cannot fetch) are failures.
 *   success:  {"sent":"true","message":"ok","id":...}
 *   failure:  {"error":"..."} | {"sent":"false","message":"..."}
 *
 * @return array ['ok' => bool, 'message' => string, 'id' => string, 'raw' => string|null]
 */
function cdp_wa_postUltraMsg($settings, $endpoint, array $params, $phone)
{
    $params = array_merge(['token' => $settings->api_ws_token], $params);
    $apiUrl = rtrim($settings->api_ws_url, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => http_build_query($params),
        CURLOPT_CONNECTTIMEOUT    => 5,
        // Media is fetched by UltraMsg before it answers: allow it longer.
        CURLOPT_TIMEOUT           => $endpoint === 'messages/chat' ? 15 : 30,
        CURLOPT_SSL_VERIFYHOST    => 0,
        CURLOPT_SSL_VERIFYPEER    => 0,
        CURLOPT_HTTPHEADER        => ["Content-Type: application/x-www-form-urlencoded"],
    ]);

    $response  = curl_exec($ch);
    $err       = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Transport failure: the request never completed.
    if ($err || $response === false) {
        cdp_wa_log("send transport error to {$phone}: {$err}");
        return ['ok' => false, 'message' => "cURL error: {$err}", 'id' => '', 'raw' => null];
    }

    $data = json_decode((string) $response, true);
    $raw  = "HTTP {$http_code}: " . (string) $response;

    if (is_array($data)) {
        if (!empty($data['error'])) {
            $reason = is_scalar($data['error']) ? (string) $data['error'] : json_encode($data['error']);
            cdp_wa_log("send rejected for {$phone} (HTTP {$http_code}): {$reason}");
            return ['ok' => false, 'message' => "WhatsApp API error: {$reason}", 'id' => '', 'raw' => $raw];
        }
        if (isset($data['sent']) && filter_var($data['sent'], FILTER_VALIDATE_BOOLEAN) === false) {
            $reason = isset($data['message']) ? (string) $data['message'] : 'message not sent';
            cdp_wa_log("send not sent for {$phone} (HTTP {$http_code}): {$reason}");
            return ['ok' => false, 'message' => "WhatsApp API: {$reason}", 'id' => '', 'raw' => $raw];
        }
    } elseif ($http_code < 200 || $http_code >= 300) {
        // Non-JSON body on a non-2xx response is a clear failure.
        cdp_wa_log("send failed for {$phone} (HTTP {$http_code}): " . substr((string) $response, 0, 200));
        return ['ok' => false, 'message' => "WhatsApp API HTTP {$http_code}", 'id' => '', 'raw' => $raw];
    }

    return ['ok' => true, 'message' => '', 'id' => (is_array($data) && isset($data['id'])) ? (string) $data['id'] : '', 'raw' => $raw];
}
