<?php
// ini_set('display_errors', 1);
$projectRoot = dirname(__DIR__, 2); 
require_once $projectRoot . '/helpers/querys.php';
require_once $projectRoot . '/helpers/vendor/autoload.php';

/**
 * Send a WhatsApp message via your API.
 *
 * @param object $sender                 An object with at least a `phone` property.
 * @param string $template_whatsapp_body The fully‑rendered message body to send.
 * @return array ['success' => bool, 'message' => string]
 */
function sendNotificationWhatsApp_v2($sender, $template_whatsapp_body) {
    // Fetch your API credentials & endpoint
    $settings = cdp_getSettingsCourier();
    $apiToken = $settings->api_ws_token;
    $apiUrl   = rtrim($settings->api_ws_url, '/') . '/messages/chat';

    // Build the POST payload
    $params = [
        'token' => $apiToken,
        'to'    => $sender->phone,
        'body'  => $template_whatsapp_body,
    ];

    // if (intval($settings->active_whatsapp) != 1) {
    //     return [
    //         'success' => false,
    //         'message' => 'WhatsApp is not active in settings.',
    //     ];
    // }

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

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    // Return a uniform result
    if ($err) {
        return [
            'success' => false,
            'message' => "cURL error: {$err}",
        ];
    }

    return [
        'success' => true,
        'message' => "WhatsApp notification sent successfully",
    ];
}
