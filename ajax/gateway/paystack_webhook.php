<?php
// ============================================================================
// Paystack webhook receiver (PUBLIC — Paystack calls this; no login).
// Verifies the x-paystack-signature (HMAC-SHA512 of the raw body with the
// secret key), then on charge.success:
//
//   * customer self-service payment (an intent exists for the reference) —
//     COMPLETES it: verifies with Paystack, books the money, clears the
//     packages. This is the authoritative path whenever the customer's browser
//     never made it back (closed the tab, lost signal, killed the app), which
//     on mobile money is common rather than exceptional.
//   * staff-recorded payment (no intent) — just marks the existing
//     cdb_fs_payments row confirmed, as before.
//
// The signature check is what makes this safe: the body is otherwise entirely
// attacker-controllable. Even so, completion re-verifies against Paystack
// rather than believing the payload.
//
// Inert until the Paystack secret key is set (cdb_met_payment id 4, via the
// Paystack payment-method settings screen).
//   Configure this URL as your Paystack webhook: .../ajax/gateway/paystack_webhook.php
// ============================================================================

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/fs_gateways.php');
require_once(__DIR__ . '/../../helpers/fs_payments.php');

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
    $reference = (string) $evt->data->reference;
    try {
        if (cdp_fsIntentByRef($reference)) {
            // Customer self-service: book the money and release the packages.
            // Idempotent — if the browser already completed it, this is a no-op.
            cdp_fsCompleteIntent($reference);
        } else {
            // Staff-recorded payment: the row already exists and was verified
            // when it was saved; this only reconciles its status.
            $db = new Conexion;
            $db->cdp_query("UPDATE cdb_fs_payments SET gateway_status = 'success', gateway_payload = :p
                            WHERE reference = :r");
            $db->bind(':p', mb_substr($raw, 0, 4000));
            $db->bind(':r', $reference);
            $db->cdp_execute();
        }
    } catch (Throwable $e) {
        // swallow — always 200 so Paystack doesn't retry-storm
    }
}

http_response_code(200);
echo 'ok';
