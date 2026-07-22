<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// * Bulk box labels — renders the print_label_ship.php design once per    *
// * selected shipment. Normal (4x6") or Small (2x1") via ?size=.          *
// *************************************************************************

require_once('helpers/querys.php');

$db = new Conexion;

if (!isset($_GET['data'])) {
    cdp_redirect_to('courier_list.php');
}

$order_list = json_decode($_GET['data']);
if (!is_array($order_list) || count($order_list) === 0) {
    cdp_redirect_to('courier_list.php');
}

$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';
$page_title = 'Package Labels';
include 'views/print/partials/label_ship_multiple_head.php';

foreach ($order_list as $order_no) {

    $data = cdp_getCourierPrintMultiple($order_no);
    if (!$data || $data['rowCount'] != 1) {
        continue;
    }
    $row = $data['data'];

    // Order items
    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . intval($row->order_id) . "'");
    $order_items = $db->cdp_registros();

    // Courier
    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
    $courier_com = $db->cdp_registro();

    // Sender
    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
    $sender_data = $db->cdp_registro();

    // Recipient — resolve from the right table (recipient_type='user' => cdb_users)
    if (($row->recipient_type ?? 'recipient') === 'user') {
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

    // Tracking
    $package_tracking = cdp_getPackageTrackingLegacyAware($row->order_id);

    $count = is_array($order_items) ? count($order_items) : 0;

    $sender_name = trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''));
    $recip_name  = trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? ''));

    $L = [
        'sys_tracking'  => $row->order_prefix . $row->order_no,
        'courier_track' => !empty($package_tracking->tracking_number) ? $package_tracking->tracking_number : null,
        'courier_name'  => $courier_com->name_com ?? 'N/A',
        'item_count'    => $count,
        'weight'        => number_format((float) $row->total_weight, 2),
        'is_dangerous'  => !empty($row->is_dangerous_good),
        'sender_name'   => $sender_name,
        'sender_line'   => trim(h($core->c_country ?? '') . ', ' . h($core->c_city ?? '') . ', ' . h($core->c_postal ?? ''), ', '),
        'sender_locker' => $sender_data->locker ?? '',
        'recip_name'    => $recip_name,
        'recip_line'    => $address_order->recipient_address ?? '',
        'recip_phone'   => $receiver_data->phone ?? '',
    ];

    include 'views/print/partials/label_ship_body.php';
}

include 'views/print/partials/label_ship_multiple_foot.php';
