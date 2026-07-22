<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************

require_once('helpers/querys.php');

$db = new Conexion;

if (isset($_GET['id'])) {
    $data = cdp_getCourierPrint($_GET['id']);
}

if (!isset($_GET['id']) || !isset($data) || $data['rowCount'] != 1) {
    cdp_redirect_to('courier_list.php');
}

$row = $data['data'];

// Order items
$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

// Shipping mode
$db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id='" . $row->order_service_options . "'");
$shipping_mode = $db->cdp_registro();

// Courier
$db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . $row->order_courier . "'");
$courier_com = $db->cdp_registro();

// Sender
$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

// Recipient — resolve from the right table (recipient_type='user' => cdb_users)
$recipient_type = isset($row->recipient_type) ? $row->recipient_type : 'recipient';
if ($recipient_type === 'user') {
    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients WHERE id='" . intval($row->receiver_id) . "'");
}
$receiver_data = $db->cdp_registro();
if (!$receiver_data) {
    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
    $receiver_data = $db->cdp_registro();
}

// Address
$db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();

// Tracking and ETA
$package_tracking = cdp_getPackageTrackingLegacyAware($_GET['id']);

// Volumetric / weight calculation (per item, mirrors legacy logic)
$sumador_libras = 0;
$sumador_volumetric = 0;
$count = 0;
$length_total = 0;
$width_total = 0;
$height_total = 0;

foreach ($order_items as $row_item) {
    $weight_item = number_format((float)$row_item->order_item_weight, 2, '.', '');
    $vol_metric = (!empty($row->volumetric_percentage))
        ? ($row_item->order_item_length * $row_item->order_item_width * $row_item->order_item_height / $row->volumetric_percentage)
        : 0;

    $sumador_libras += $weight_item;
    $sumador_volumetric += $vol_metric;
    $length_total += (float)$row_item->order_item_length;
    $width_total  += (float)$row_item->order_item_width;
    $height_total += (float)$row_item->order_item_height;
    $count++;
}

$display_weight = ($sumador_libras > $sumador_volumetric) ? $sumador_libras : $sumador_volumetric;

$total_qty = 0;
foreach ($order_items as $item) {
    $total_qty += (int)$item->order_item_quantity;
}

// h() is provided globally by helpers/functions.php (loaded via loader.php).

// ── Derived values ──────────────────────────────────────────────────────────
$sys_tracking = $row->order_prefix . $row->order_no;                  // system/package tracking
$courier_track = !empty($package_tracking->tracking_number)           // carrier/postal tracking
    ? $package_tracking->tracking_number
    : null;

$sender_name = trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''));
$recip_name  = trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? ''));

if (empty($sender_data)) {
    // legacy fallback parsing for orders with no linked sender record
    $address_sender = explode(" - ", $address_order->sender_address ?? '');
    $sender_name = isset($address_sender[0]) ? $address_sender[0] : '';
}

$recip_address_line = !empty($receiver_data)
    ? ($address_order->recipient_address ?? '')
    : '4 Nii Attram Mensah, CL GS-0211-5741, Weija, Accra';

$recip_phone = $receiver_data->phone ?? '';

// ── Normalized label model + chosen physical size ────────────────────────────
$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';

$L = [
    'sys_tracking'  => $sys_tracking,
    'courier_track' => $courier_track,
    'courier_name'  => $courier_com->name_com ?? 'N/A',
    'item_count'    => $count,
    'weight'        => number_format($row->total_weight, 2),
    'is_dangerous'  => !empty($row->is_dangerous_good),
    'sender_name'   => $sender_name,
    'sender_line'   => trim(h($core->c_country ?? '') . ', ' . h($core->c_city ?? '') . ', ' . h($core->c_postal ?? ''), ', '),
    'sender_locker' => $sender_data->locker ?? '',
    'recip_name'    => $recip_name,
    'recip_line'    => $recip_address_line,
    'recip_phone'   => $recip_phone,
];

$page_title = 'Package Label - ' . $sys_tracking;
include 'views/print/partials/label_ship_page.php';
