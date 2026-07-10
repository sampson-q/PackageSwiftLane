<?php
// Accumulative outstanding balance for a customer (sender), from the Financial
// Sheet ledger: Σ max(0, bill − discount − paid) across ALL their billed
// consolidations. Used to warn staff at package creation that a customer owes.
// GET (no CSRF needed); any logged-in staff may read it.
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

$sid = (int) ($_REQUEST['sender_id'] ?? 0);
if ($sid < 1) {
    echo json_encode(['ok' => false]);
    exit;
}

$db = new Conexion;
try {
    $db->cdp_query("SELECT
            COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))),0) AS ghs,
            COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0) AS usd,
            COUNT(DISTINCT CASE WHEN GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0)) > 0
                                THEN consolidate_id END) AS consols
        FROM cdb_consolidate_customer_billing WHERE sender_id = :sid");
    $db->bind(':sid', $sid);
    $db->cdp_execute();
    $r = $db->cdp_registro();
} catch (Throwable $e) {
    // FS billing table absent — no debt info.
    echo json_encode(['ok' => true, 'sender_id' => $sid, 'ghs' => 0, 'usd' => 0, 'consols' => 0]);
    exit;
}

echo json_encode([
    'ok'        => true,
    'sender_id' => $sid,
    'ghs'       => $r ? round((float) $r->ghs, 2) : 0.0,
    'usd'       => $r ? round((float) $r->usd, 2) : 0.0,
    'consols'   => $r ? (int) $r->consols : 0,
]);
