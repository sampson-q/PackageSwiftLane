<?php
require_once('helpers/querys.php');
require_once('helpers/fs_payments.php');

$data = ['rowCount' => 0, 'data' => null];
$total_qty = 0;
$total_weight = 0;

if (isset($_GET['id'])) {
    $shipmentId = (int) $_GET['id'];
    $data = cdp_getCourierPrint($shipmentId);
}

if (!isset($_GET['id']) || !isset($data['rowCount']) || (int)$data['rowCount'] !== 1) {
    cdp_redirect_to("courier_list.php");
}

$row = $data['data'];

// PAID stamp — the confirmed payment (if any) that cleared THIS package.
// Sourced from the Financial Sheet ledger rather than the order's own
// status_invoice flag, so the stamp can carry the method + reference + date
// that make it auditable against the gateway.
$fs_payment = cdp_fsPaymentForOrder((int) $_GET['id']);
$fs_paid_mode = '';
if ($fs_payment) {
    $modes = ['cash' => 'Cash', 'paystack' => 'Mobile Money (Paystack)',
              'hubtel' => 'Mobile Money (Hubtel)', 'paypal' => 'PayPal'];
    $fs_paid_mode = $modes[strtolower((string) $fs_payment->mode)] ?? ucfirst((string) $fs_payment->mode);
}

// Get order items
$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . (int)$_GET['id'] . "'");
$order_items = $db->cdp_registros();

// Get shipping mode
$db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id='" . (int)$row->order_service_options . "'");
$shipping_mode = $db->cdp_registro();

// Get category
$db->cdp_query("SELECT * FROM cdb_category WHERE id='" . (int)$row->order_item_category . "'");
$category = $db->cdp_registro();

// Get courier
$db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . (int)$row->order_courier . "'");
$courier_com = $db->cdp_registro();

// Get sender and receiver
$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int)$row->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int)$row->receiver_id . "'");
$receiver_data = $db->cdp_registro();

// Get address
$db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();

// Get tracking and ETA
$package_tracking = cdp_getPackageTrackingLegacyAware((int)$_GET['id']);

// Financial-sheet S/N (position of this package in its consolidation's
// sender-sorted financial sheet). Null when the order isn't consolidated.
// An optional consolidate_id pins it to a specific sheet (default: newest).
$financial_serial = cdp_getOrderFinancialSerial(
    $row->order_prefix,
    $row->order_no,
    (int) ($_REQUEST['consolidate_id'] ?? 0)
);

$items = [];
$total_qty = 0;
if ($order_items) {
    foreach ($order_items as $item) {
        $total_weight += (float) $item->order_item_weight;
        $total_qty    += (int) $item->order_item_quantity;
        $items[] = ['qty' => (int) $item->order_item_quantity, 'desc' => $item->order_item_description];
    }
}

$INV = [
    'sys_tracking'     => $row->order_prefix . $row->order_no,
    'sender_name'      => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
    'sender_address'   => $address_order ? $address_order->sender_address : 'N/A',
    'sender_location'  => $address_order ? ($address_order->sender_city . ', ' . $address_order->sender_country) : 'N/A',
    'sender_phone'     => $sender_data->phone ?? '',
    'carrier_tracking' => $package_tracking->tracking_number ?? '',
    'courier_name'     => $courier_com ? $courier_com->name_com : 'N/A',
    'category_name'    => $category ? $category->name_item : 'N/A',
    'financial_serial' => $financial_serial,
    'items'            => $items,
    'total_weight'     => (string) ($row->total_weight ?? $total_weight ?? '—'),
    'total_qty'        => $total_qty,
    'fs_payment'       => $fs_payment,
    'fs_paid_mode'     => $fs_paid_mode,
];

$page_title = ($lang['inv-shipping19'] ?? 'Invoice') . ' - ' . $INV['sys_tracking'];
include 'views/print/partials/inv_ship_page.php';
return;
