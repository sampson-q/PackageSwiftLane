<?php
// ============================================================================
// Warehouse â€” new delivery confirmation flow (single or multi-select).
// Deliberately separate from the existing courier_deliver_shipment.php page,
// which is left untouched and unlinked from the warehouse list. This is a
// lightweight flow: preview the selected package(s) (customer, locker,
// system + carrier tracking), confirm via SweetAlert, then flip
// status_courier straight to Delivered â€” no photo/signature capture, no
// notifications (none were asked for here).
//   action=preview : POST order_nos (JSON array) -> package details for the confirm dialog.
//   action=deliver  : POST order_nos (JSON array) -> marks them Delivered.
// ============================================================================

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once("../../helpers/querys.php");
require_login();
require_permission('deliver_shipment');

header('Content-Type: application/json; charset=UTF-8');

if (!defined('CDP_WH_DELIVERED_STATUS')) {
    define('CDP_WH_DELIVERED_STATUS', 8); // Delivered, confirmed in cdb_styles
}

$action   = $_REQUEST['action'] ?? '';
$rawNos   = $_REQUEST['order_nos'] ?? '';
$decoded  = is_array($rawNos) ? $rawNos : json_decode((string) $rawNos, true);
$orderNos = is_array($decoded)
    ? array_values(array_unique(array_filter(array_map('strval', $decoded), function ($v) { return $v !== ''; })))
    : [];

if (!$orderNos) {
    echo json_encode(['ok' => false, 'message' => 'No packages selected.']);
    exit;
}

if ($action === 'preview') {
    $db = new Conexion;
    $packages = [];

    foreach ($orderNos as $no) {
        $row = cdp_getCourierMultiple($no);
        if (!$row) {
            continue;
        }

        $db->cdp_query("SELECT fname, lname, locker FROM cdb_users WHERE id = :sid LIMIT 1");
        $db->bind(':sid', (int) $row->sender_id);
        $db->cdp_execute();
        $sender = $db->cdp_registro();

        $carrier = cdp_getPackageTrackingLegacyAware((int) $row->order_id);

        $packages[] = [
            'order_no'          => (string) $row->order_no,
            'tracking'          => ($row->order_prefix ?? '') . $row->order_no,
            'carrier'           => $carrier->tracking_number ?: 'N/A',
            'name'              => $sender && function_exists('cdp_nameWithLocker') ? cdp_nameWithLocker($sender) : 'N/A',
            'already_delivered' => ((int) $row->status_courier === CDP_WH_DELIVERED_STATUS),
        ];
    }

    if (!$packages) {
        echo json_encode(['ok' => false, 'message' => 'Could not find the selected package(s).']);
        exit;
    }

    echo json_encode(['ok' => true, 'packages' => $packages]);
    exit;
}

if ($action === 'deliver') {
    $user     = new User;
    $userData = $user->cdp_getUserData();
    $uid      = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));

    $delivered = 0;
    foreach ($orderNos as $no) {
        $row = cdp_getCourierMultiple($no);
        if (!$row || (int) $row->status_courier === CDP_WH_DELIVERED_STATUS) {
            continue;
        }

        cdp_updateStatusCourierMultiple($no, CDP_WH_DELIVERED_STATUS);
        cdp_freeConsolidatedItem((int) $row->order_id);

        $tracking = ($row->order_prefix ?? '') . $no;
        if (function_exists('cdp_updateShipTrackingMultiple')) {
            cdp_updateShipTrackingMultiple($tracking, CDP_WH_DELIVERED_STATUS, 'Delivered via Warehouse', $row->origin_off ?? 0, $uid);
        }
        $delivered++;
    }

    echo json_encode(['ok' => true, 'delivered' => $delivered]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
