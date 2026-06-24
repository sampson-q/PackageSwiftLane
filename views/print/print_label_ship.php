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

// QR encodes the sender's details (scannable contact block)
$qr_data = "FROM: " . $sender_name
    . "\n" . ($address_order->sender_address ?? '')
    . "\n" . trim(($address_order->sender_city ?? '') . ', ' . ($address_order->sender_country ?? ''), ', ')
    . "\nTel: " . ($sender_data->phone ?? '')
    . "\nRef: " . $sys_tracking;

$qr_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($qr_data)
    . '&code=QRCode&unit=Fit&dpi=96&imagetype=Gif&eclevel=M&quiet=0';

$sys_barcode_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($sys_tracking)
    . '&code=Code128&unit=Fit&dpi=96&imagetype=Gif&rotation=0&quiet=0&modulewidth=50';

$courier_barcode_url = $courier_track
    ? 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($courier_track)
        . '&code=Code128&unit=Fit&dpi=96&imagetype=Gif&rotation=0&quiet=0&modulewidth=50'
    : null;
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo h($core->favicon); ?>">
    <title><?php echo h('Package Label - ' . $sys_tracking); ?></title>
    <link rel="stylesheet" href="assets/vendor/fonts/fontawesome.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/tabler-icons.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/flag-icons.css" />
    <style>
        @page { size: 102mm 152mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            width: 102mm; height: 152mm; margin: 0; padding: 0;
            background: #fff; color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .label { width: 102mm; height: 152mm; padding: 2.5mm; display: flex; flex-direction: column; }

        /* Everything sits inside this border */
        .panel {
            border: 2.5px solid #000; flex: 1 1 auto;
            display: flex; flex-direction: column; overflow: hidden;
        }

        /* Top: logo + sender contact block (was sender QR) */
        .top { display: flex; align-items: center; justify-content: space-between; max-height: 80px; min-height: 80px; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
        .top .logo { width: 40%; text-align: center; }
        .top .logo img { max-width: 100%; height: auto; max-height: 18mm; }
        .top .logo .fallback { font-size: 13pt; font-weight: 800; letter-spacing: .5px; }
        .top .qr { text-align: center; }
        .top .qr img { width: 15mm; height: 15mm; }
        .top .qr small { display: block; font-size: 6pt; font-weight: 800; color: #000; margin-top: .3mm; letter-spacing: .3px; }
        .top .contact { text-align: center; font-size: 7.5pt; font-weight: 700; color: #000; line-height: 1.3; }
        .top .contact strong { font-size: 9pt; font-weight: 800; }

        /* Title band: system / package tracking */
        .title { background: #000; color: #fff; text-align: center; padding: 1.6mm 2mm; }
        .title .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .title .v { font-size: 16pt; font-weight: 800; letter-spacing: 1px; line-height: 1.05; }

        /* Shipment facts row (no monetary values) */
        .facts { display: flex; gap: 1.5mm; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
        .facts .f { flex: 1; border: 1.5px solid #000; padding: 1.4mm 1mm; text-align: center; }
        .facts .f .k { font-size: 6pt; font-weight: 800; text-transform: uppercase; color: #000; letter-spacing: .3px; }
        .facts .f .v { font-size: 9pt; font-weight: 800; line-height: 1.15; }

        /* Sender / Recipient block */
        .addr-block { padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
        .addr { margin-bottom: 1.5mm; }
        .addr:last-child { margin-bottom: 0; }
        .addr .role { font-size: 7pt; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #000; border-bottom: 1.5px solid #000; padding-bottom: .6mm; margin-bottom: 1mm; }
        .addr.from .name { font-size: 13pt; font-weight: 800; }
        .addr.from .line { font-size: 10pt; font-weight: 700; line-height: 1.3; }
        .addr.to .name { font-size: 13pt; font-weight: 800; line-height: 1.15; }
        .addr.to .line { font-size: 10pt; font-weight: 700; line-height: 1.3; }

        /* Items table */
        .mid { flex: 1 1 auto; padding: 2mm 2.5mm; display: flex; flex-direction: column; overflow: hidden; }
        .shipment-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5pt; font-weight: 700; }
        .shipment-table thead th { background: #000; color: #fff; padding: 1.2mm; text-align: left; font-weight: 800; }
        .shipment-table th:first-child, .shipment-table td:first-child { width: 14mm; text-align: center; }
        .shipment-table tbody td { border-bottom: 1.5px solid #000; padding: 1mm 1.2mm; vertical-align: top; word-wrap: break-word; }
        .shipment-table tbody tr:last-child td { border-bottom: none; }

        /* Bottom: both QR codes, stacked in rows, filling the space */
        .bar { border-top: 1.5px solid #000; padding: 1.8mm 2.5mm 2mm; text-align: center; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.bar .track-row { display: flex; flex-direction: column; gap: 1.5mm; flex: 1 1 auto; min-height: 0; overflow: hidden; }
.bar .track-row.single { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
.bar .track { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
.bar .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; flex: 0 0 auto; }
.bar img { display: block; margin: 0 auto; flex: 1 1 auto; min-height: 0; height: 100%; width: auto; max-width: 100%; object-fit: contain; }
.bar .num { font-size: 8pt; font-weight: 800; letter-spacing: 1px; }
.bar .divider { height: 1.5px; width: 100%; background: #000; flex: 0 0 auto; }

        .print-button { text-align: center; margin-top: 4mm; }

        @media print { .print-button { display: none; } .label { padding: 2mm; } }
    </style>
</head>
<body>
    <div class="label">
        <div class="panel">
            <!-- Top: logo + sender contact block (was sender QR) -->
            <div class="top">
                <div class="logo">
                    <?php if (!empty($core->logo)) : ?>
                        <img src="assets/<?php echo h($core->logo); ?>" alt="<?php echo h($core->site_name); ?>">
                    <?php else : ?>
                        <span class="fallback"><?php echo h($core->site_name); ?></span>
                    <?php endif; ?>
                </div>
                <div class="contact">
                    <strong><?php echo h($core->site_name); ?></strong><br>
                    Ghana, 4 Nii Attram Mensah CL <br>GS-0211-5741<br>
                    <?php echo h($lang['print-text8'] ?? 'Tel'); ?>: +233 538 346 496
                </div>
            </div>

            <!-- Title band: system / package tracking -->
            <div class="title">
                <div class="k">Package Tracking</div>
                <div class="v"><?php echo h($sys_tracking); ?></div>
            </div>

            <!-- Shipment facts (no monetary values) -->
            <div class="facts">
                <?php if (!empty($row->is_dangerous_good)) : ?>
                    <div class="f"><div class="k">Hazmat</div><div class="v"><i class="fas fa-exclamation-triangle fa-lg"></i></div></div>
                <?php endif; ?>
                <div class="f"><div class="k">Courier</div><div class="v"><?php echo h($courier_com->name_com ?? 'N/A'); ?></div></div>
                <div class="f"><div class="k">Items</div><div class="v"><?php echo h($count); ?></div></div>
                <div class="f"><div class="k">Weight</div><div class="v"><?php echo number_format($row->total_weight, 2); ?></div></div>
            </div>

            <!-- Sender / Recipient -->
            <div class="addr-block">
                <div class="addr from">
                    <div class="role">Sender</div>
                    <div class="name"><?php echo h($sender_name) . ' (' . $sender_data->locker . ')'; ?></div>
                    <div class="line"><?php echo h($core->c_country ?? '') . ', ' . h($core->c_city ?? '') . ', ' . h($core->c_postal ?? ''); ?></div>
                </div>
                <div class="addr to">
                    <div class="role">Recipient</div>
                    <div class="name"><?php echo h($recip_name); ?></div>
                    <div class="line"><?php echo h($recip_address_line); ?></div>
                    <?php if (!empty($recip_phone)) : ?>
                        <div class="line"><?php echo h($recip_phone); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items -->
            <!-- <div class="mid">
                <table class="shipment-table">
                    <thead>
                        <tr>
                            <th><?php echo h($lang['left214']); ?></th>
                            <th><?php echo h($lang['left213']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($order_items)) : ?>
                            <?php foreach ($order_items as $row_order_item) : ?>
                                <tr>
                                    <td><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                                    <td><?php echo h($row_order_item->order_item_description); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2" style="text-align:center;">No items</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div> -->

            <!-- Bottom: both QR codes, stacked on top of each other, filling the space -->
            <div class="bar">
                <?php if ($courier_track) : ?>
                    <div class="track-row">
                        <div class="track">
                            <div class="k">System Tracking</div>
                            <img src="<?php echo h($sys_barcode_url); ?>" alt="Courier barcode">
                        </div>
                        <div class="divider"></div>
                        <div class="track">
                            <div class="k">Courier Tracking</div>
                            <img src="<?php echo h($courier_barcode_url); ?>" alt="Courier barcode">
                        </div>
                    </div>
                <?php else : ?>
                    <div class="track">
                        <div class="k">System Tracking</div>
                        <img src="<?php echo h($sys_barcode_url); ?>" alt="Courier barcode">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">🖨️ Print Label</button>
    </div>

    <script>window.onload = function () { setTimeout(function () { window.print(); }, 300); };</script>
</body>
</html>