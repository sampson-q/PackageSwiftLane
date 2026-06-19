<?php
require_once('helpers/querys.php');

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

// Totals
$total_weight = 0;
$total_qty    = 0;
foreach ($order_items as $item) {
    $total_weight += (float)$item->order_item_weight;
    $total_qty    += (int)$item->order_item_quantity;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ── Derived values ──────────────────────────────────────────────────────────
$sys_tracking = $row->order_prefix . $row->order_no;                  // system/package tracking (title)
$postal_track = !empty($package_tracking->tracking_number)           // carrier/postal tracking (bottom barcode)
    ? $package_tracking->tracking_number
    : $sys_tracking;

$sender_name = trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''));
$recip_name  = trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? ''));

// QR encodes the sender's details (scannable contact block)
$qr_data = "FROM: " . $sender_name
    . "\n" . ($address_order->sender_address ?? '')
    . "\n" . trim(($address_order->sender_city ?? '') . ', ' . ($address_order->sender_country ?? ''), ', ')
    . "\nTel: " . ($sender_data->phone ?? '')
    . "\nRef: " . $sys_tracking;

$qr_url      = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($qr_data)
    . '&code=QRCode&unit=Fit&dpi=96&imagetype=Gif&eclevel=M&quiet=0';
$barcode_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($postal_track)
    . '&code=Code128&unit=Fit&dpi=96&imagetype=Gif&rotation=0&quiet=0&modulewidth=50';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo h($core->favicon); ?>">
    <title><?php echo h('Label - ' . $sys_tracking); ?></title>
    <style>
        @page { size: 102mm 152mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            width: 102mm; height: 152mm; margin: 0; padding: 0;
            background: #fff; color: #000;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .label { width: 102mm; height: 152mm; padding: 2.5mm; display: flex; flex-direction: column; }
        /* Everything except the iSolveAfrica line sits inside this border */
        .panel {
            border: 2px solid #000; flex: 1 1 auto;
            display: flex; flex-direction: column; overflow: hidden;
        }
        /* Top: logo (was the "P") + sender QR */
        .top { display: flex; align-items: center; justify-content: space-between; padding: 2mm 2.5mm; border-bottom: 2px solid #000; }
        .top .logo img { max-height: 16mm; max-width: 42mm; object-fit: contain; }
        .top .logo .fallback { font-size: 13pt; font-weight: 800; letter-spacing: .5px; }
        .top .qr { text-align: center; }
        .top .qr img { width: 22mm; height: 22mm; }
        .top .qr small { display: block; font-size: 5.5pt; color: #333; margin-top: .3mm; letter-spacing: .3px; }
        /* Title band: system / package tracking */
        .title { background: #000; color: #fff; text-align: center; padding: 1.6mm 2mm; }
        .title .k { font-size: 6pt; letter-spacing: 1px; text-transform: uppercase; opacity: .85; }
        .title .v { font-size: 15pt; font-weight: 800; letter-spacing: 1px; line-height: 1.05; }
        /* Middle: addresses + shipment facts */
        .mid { flex: 1 1 auto; padding: 2.5mm; display: flex; flex-direction: column; gap: 2mm; }
        .addr .role { font-size: 6.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #000; border-bottom: 1px solid #000; padding-bottom: .6mm; margin-bottom: 1mm; }
        .addr.from .name { font-size: 8.5pt; font-weight: 700; }
        .addr.from .line { font-size: 7.5pt; line-height: 1.25; }
        .addr.to   .name { font-size: 12pt; font-weight: 800; line-height: 1.1; }
        .addr.to   .line { font-size: 9.5pt; line-height: 1.3; }
        .facts { display: flex; gap: 2mm; margin-top: auto; }
        .facts .f { flex: 1; border: 1px solid #000; padding: 1.4mm 1mm; text-align: center; }
        .facts .f .k { font-size: 5.8pt; text-transform: uppercase; color: #333; letter-spacing: .3px; }
        .facts .f .v { font-size: 8.5pt; font-weight: 800; line-height: 1.1; }
        /* Bottom: carrier / postal tracking barcode */
        .bar { border-top: 2px solid #000; padding: 1.8mm 2.5mm 2mm; text-align: center; }
        .bar .k { font-size: 6.5pt; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .bar img { display: block; width: 88%; height: 16mm; margin: 1mm auto .6mm; }
        .bar .num { font-size: 9pt; font-weight: 700; letter-spacing: 1px; }
        /* Footer OUTSIDE the border */
        .foot { text-align: center; font-size: 7.5pt; color: #444; padding-top: 1.6mm; line-height: 1.3; }
        .print-button { text-align: center; margin-top: 4mm; }
        .shipment-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7pt;
        }

        .shipment-table thead th {
            background: #000;
            color: #fff;
            padding: 1.2mm;
            text-align: left;
            font-weight: 700;
        }

        .shipment-table th:first-child,
        .shipment-table td:first-child {
            width: 14mm;
            text-align: center;
        }

        .shipment-table tbody td {
            border-bottom: 1px solid #ccc;
            padding: 1mm 1.2mm;
            vertical-align: top;
            word-wrap: break-word;
        }

        .shipment-table tfoot td {
            padding: 1.5mm 1.2mm;
            font-size: 7pt;
            font-weight: 700;
            border-top: 1.5px solid #000;
        }

        .shipment-table tbody tr:last-child td {
            border-bottom: none;
        }
        @media print { .print-button { display: none; } .label { padding: 2mm; } }
    </style>
</head>
<body>
    <div class="label">
        <div class="panel">
            <!-- Top: logo + sender QR -->
            <div class="top">
                <div class="logo">
                    <?php if (!empty($core->logo)) : ?>
                        <img src="assets/<?php echo h($core->logo); ?>" alt="<?php echo h($core->site_name); ?>">
                    <?php else : ?>
                        <span class="fallback"><?php echo h($core->site_name); ?></span>
                    <?php endif; ?>
                </div>
                <div class="qr">
                    <img src="<?php echo h($qr_url); ?>" alt="Sender QR">
                    <small>SENDER DETAILS</small>
                </div>
            </div>

            <!-- Title band: system / package tracking -->
            <div class="title">
                <div class="k">Package Tracking</div>
                <div class="v"><?php echo h($sys_tracking); ?></div>
            </div>

            <!-- Middle: addresses + facts -->
            <div class="mid">
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
                                    <td>
                                        <?php echo (int) $row_order_item->order_item_quantity; ?>
                                    </td>

                                    <td>
                                        <?php echo h($row_order_item->order_item_description); ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <tr>
                                <td colspan="2" style="text-align:center;">
                                    No items
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="facts">
                    <div class="f"><div class="k">Courier</div><div class="v"><?php echo h($courier_com->name_com ?? 'N/A'); ?></div></div>
                    <div class="f"><div class="k">Mode</div><div class="v"><?php echo h($shipping_mode->ship_mode ?? 'N/A'); ?></div></div>
                    <div class="f"><div class="k">Items</div><div class="v"><?php echo h($total_qty); ?></div></div>
                    <div class="f"><div class="k">Weight</div><div class="v"><?php echo h(number_format($row->total_weight, 2)); ?></div></div>
                </div>
            </div>

            <!-- Bottom: carrier / postal tracking -->
            <div class="bar">
                <div class="k">Tracking #</div>
                <img src="<?php echo h($barcode_url); ?>" alt="Carrier barcode">
            </div>
        </div>
    </div>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">🖨️ Print Label</button>
    </div>

    <script>window.onload = function () { setTimeout(function () { window.print(); }, 300); };</script>
</body>
</html>
