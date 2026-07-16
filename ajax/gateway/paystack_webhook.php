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

$evt   = json_decode($raw);
$event = isset($evt->event) ? (string) $evt->event : '';

// The reference lives in different places depending on the event: a charge
// carries its own, a refund/dispute points at the transaction it concerns.
$reference = (string) ($evt->data->reference
    ?? $evt->data->transaction->reference
    ?? $evt->data->transaction_reference
    ?? '');

if ($event !== '' && $reference !== '') {
    try {
        // Record that Paystack told us something, whatever it was. Even an
        // event we take no action on belongs in the trail — "Paystack never
        // told us" and "we ignored it" must be distinguishable during an audit.
        $pid = null;
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM cdb_fs_payments WHERE reference = :r LIMIT 1");
        $db->bind(':r', $reference);
        $pRow = $db->cdp_registro();
        $pid = $pRow ? (int) $pRow->id : null;

        cdp_fsLogPaymentEvent([
            'payment_id' => $pid,
            'reference'  => $reference,
            'source'     => 'paystack_webhook',
            'event'      => $event,
            'message'    => 'Webhook received.',
            'payload'    => $raw,
        ]);

        if ($event === 'charge.success') {
            if (cdp_fsIntentByRef($reference)) {
                // Customer self-service: book the money and release the packages.
                // Idempotent — if the browser already completed it, this is a no-op.
                cdp_fsCompleteIntent($reference, ['source' => 'paystack_webhook']);
            } elseif ($pid) {
                // Staff-recorded payment: re-verify rather than trusting this
                // body, then store the gateway's full account of it.
                cdp_fsRecheckPayment($pid, null, 'paystack_webhook');
            }
        } elseif ($pid && in_array($event, [
            // Money going back out, or being contested. Each of these can turn
            // a confirmed payment into one we no longer hold — re-verify and
            // let cdp_fsApplyPaymentStatus() deal with the consequences.
            'refund.processed', 'refund.pending', 'refund.failed',
            'charge.dispute.create', 'charge.dispute.remind', 'charge.dispute.resolve',
        ], true)) {
            cdp_fsRecheckPayment($pid, null, 'paystack_webhook');
        }
    } catch (Throwable $e) {
        // swallow — always 200 so Paystack doesn't retry-storm
    }
}

http_response_code(200);
echo 'ok';
