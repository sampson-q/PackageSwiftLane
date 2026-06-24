<?php
// ini_set('display_errors', 1);
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/helpers/querys.php';
require_once $projectRoot . '/helpers/whatsapp.php';
require_once $projectRoot . '/helpers/vendor/autoload.php';

/**
 * Send a WhatsApp message via the UltraMsg API.
 *
 * Respects the global `active_whatsapp` switch, normalises the recipient phone,
 * and skips numbers the API confirms are NOT on WhatsApp (fail-open: unknown
 * numbers are still sent). See helpers/whatsapp.php.
 *
 * @param object     $sender                 Record with at least a `phone` property.
 * @param string     $template_whatsapp_body The fully-rendered message body to send.
 * @param mixed|null $countryHint            Optional recipient country (id/iso/name/code).
 * @return array ['success' => bool, 'skipped' => bool, 'message' => string]
 */
function sendNotificationWhatsApp_v2($sender, $template_whatsapp_body, $countryHint = null) {
    // Fetch your API credentials & endpoint
    $settings = cdp_getSettingsCourier();

    // Global kill-switch: if WhatsApp is disabled, send nothing.
    if (intval($settings->active_whatsapp) != 1) {
        return [
            'success' => false,
            'skipped' => true,
            'message' => 'WhatsApp is not active in settings.',
        ];
    }

    // Normalise + verify the destination number (anti-ban gate).
    $target = cdp_wa_resolveSendTarget($sender, $countryHint);
    if ($target['skip']) {
        return [
            'success' => false,
            'skipped' => true,
            'message' => $target['reason'],
        ];
    }

    $apiToken = $settings->api_ws_token;
    $apiUrl   = rtrim($settings->api_ws_url, '/') . '/messages/chat';

    // Build the POST payload
    $params = [
        'token' => $apiToken,
        'to'    => $target['phone'],
        'body'  => $template_whatsapp_body,
    ];

    // Fire off the cURL request
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => http_build_query($params),
        CURLOPT_TIMEOUT           => 30,
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
        cdp_wa_log("send transport error to {$target['phone']}: {$err}");
        return [
            'success' => false,
            'message' => "cURL error: {$err}",
        ];
    }

    // The request completed, but a 200 from cURL does NOT mean UltraMsg accepted
    // the message. Inspect the JSON body so genuine rejects (expired instance,
    // bad token, quota, malformed body) are reported as failures instead of
    // being silently masked as a successful send.
    //   success:  {"sent":"true","message":"ok","id":...}
    //   failure:  {"error":"..."} | {"sent":"false","message":"..."}
    $data = json_decode((string) $response, true);

    if (is_array($data)) {
        if (!empty($data['error'])) {
            $reason = is_scalar($data['error']) ? (string) $data['error'] : json_encode($data['error']);
            cdp_wa_log("send rejected for {$target['phone']} (HTTP {$http_code}): {$reason}");
            return ['success' => false, 'message' => "WhatsApp API error: {$reason}"];
        }
        if (isset($data['sent']) && filter_var($data['sent'], FILTER_VALIDATE_BOOLEAN) === false) {
            $reason = isset($data['message']) ? (string) $data['message'] : 'message not sent';
            cdp_wa_log("send not sent for {$target['phone']} (HTTP {$http_code}): {$reason}");
            return ['success' => false, 'message' => "WhatsApp API: {$reason}"];
        }
    } elseif ($http_code < 200 || $http_code >= 300) {
        // Non-JSON body on a non-2xx response is a clear failure.
        cdp_wa_log("send failed for {$target['phone']} (HTTP {$http_code}): " . substr((string) $response, 0, 200));
        return ['success' => false, 'message' => "WhatsApp API HTTP {$http_code}"];
    }

    // Explicit acceptance, or an ambiguous/unrecognised 2xx body (fail-open).
    return [
        'success' => true,
        'message' => "WhatsApp notification sent successfully",
    ];
}
