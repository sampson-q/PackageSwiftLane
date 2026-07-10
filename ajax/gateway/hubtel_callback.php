<?php
// ============================================================================
// Hubtel payment callback receiver (PUBLIC — Hubtel calls this; no login).
// Hubtel POSTs the final status to the PrimaryCallbackUrl. We never trust the
// callback body alone — we re-verify authoritatively via the Status Check API
// before marking the cdb_fs_payments row confirmed. Reconciliation backstop;
// the counter flow already verifies synchronously on save. Inert until Hubtel
// credentials are set in cdb_settings.
//   Configure as your Hubtel PrimaryCallbackUrl: .../ajax/gateway/hubtel_callback.php
// ============================================================================

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/fs_gateways.php');

$raw  = file_get_contents('php://input');
$data = json_decode($raw);

// Hubtel nests under Data; be liberal about shape.
$ref = '';
$status = '';
if (is_object($data)) {
    $d = isset($data->Data) ? $data->Data : $data;
    $ref = (string) ($d->ClientReference ?? ($d->clientReference ?? ''));
    $status = (string) ($d->Status ?? ($d->status ?? ''));
}

if ($ref !== '' && function_exists('cdp_fsVerifyHubtel')) {
    try {
        // Authoritative re-check. The callback body is UNTRUSTED — we NEVER treat
        // its own "Status" as proof of payment. Only a real success from Hubtel's
        // Status API grants 'success'; otherwise we mark pending, never success.
        $v = cdp_fsVerifyHubtel($ref);
        if (!empty($v['ok'])) {
            $gs = 'success';
        } else {
            $vs = strtolower((string) ($v['status'] ?? ''));
            $gs = ($vs !== '' && $vs !== 'unconfigured') ? $vs : 'pending';
        }
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_fs_payments SET gateway_status = :s, gateway_payload = :p
                        WHERE reference = :r");
        $db->bind(':s', $gs);
        $db->bind(':p', mb_substr($raw, 0, 4000));
        $db->bind(':r', $ref);
        $db->cdp_execute();
    } catch (Throwable $e) {
        // swallow — always 200
    }
}

http_response_code(200);
echo 'ok';
