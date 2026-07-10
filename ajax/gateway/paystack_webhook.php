<?php
// ============================================================================
// Paystack webhook receiver (PUBLIC — Paystack calls this; no login).
// Verifies the x-paystack-signature (HMAC-SHA512 of the raw body with the
// secret key) and, on charge.success, marks the matching cdb_fs_payments row
// confirmed. This is a reconciliation backstop — the counter flow already
// verifies synchronously on save. Inert until the Paystack secret key is set
// in cdb_settings.
//   Configure this URL as your Paystack webhook: .../ajax/gateway/paystack_webhook.php
// ============================================================================

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/fs_gateways.php');

$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

$r = function_exists('cdp_fsGatewayRow') ? cdp_fsGatewayRow(4) : null;
$secret = $r ? trim((string) $r->secret_key) : '';
if (!function_exists('cdp_fsKeyOk') || !cdp_fsKeyOk($secret)) {
    http_response_code(200); // not configured yet — acknowledge and ignore
    exit;
}
if ($sig === '' || !hash_equals(hash_hmac('sha512', $raw, $secret), $sig)) {
    http_response_code(401);
    exit;
}

$evt = json_decode($raw);
if (isset($evt->event) && $evt->event === 'charge.success' && isset($evt->data->reference)) {
    try {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_fs_payments SET gateway_status = 'success', gateway_payload = :p
                        WHERE reference = :r");
        $db->bind(':p', mb_substr($raw, 0, 4000));
        $db->bind(':r', (string) $evt->data->reference);
        $db->cdp_execute();
    } catch (Throwable $e) {
        // swallow — always 200 so Paystack doesn't retry-storm
    }
}

http_response_code(200);
echo 'ok';
