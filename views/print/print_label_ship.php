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

// Category
$db->cdp_query("SELECT * FROM cdb_category WHERE id='" . $row->order_item_category . "'");
$category = $db->cdp_registro();

// Courier
$db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . $row->order_courier . "'");
$courier_com = $db->cdp_registro();

// Sender and receiver
$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $row->receiver_id . "'");
$receiver_data = $db->cdp_registro();

// Address
$db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();

// Tracking and ETA
$package_tracking = cdp_getPackageTrackingLegacyAware($_GET['id']);

// Totals
$total_weight = 0;
foreach ($order_items as $item) {
    $total_weight += (float)$item->order_item_weight;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo h($core->favicon); ?>">
    <title><?php echo h($lang['inv-shipping19'] . ' - ' . $row->order_prefix . $row->order_no); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <style>
        @page { size: 102mm 152mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            width: 102mm;
            height: 152mm;
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: 102mm;
            height: 152mm;
            padding: 4mm;
            overflow: hidden;
        }
        .panel {
            border: 1px solid #111;
            border-radius: 2mm;
            padding: 3mm;
            margin-bottom: 2.5mm;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #ddd;
            margin-bottom: 2mm;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 2.5mm;
            min-width: 0;
        }
        .brand img {
            max-height: 12mm;
            max-width: 25mm;
            object-fit: contain;
        }
        .brand-name {
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.05;
            margin: 0;
        }
        .brand-meta {
            font-size: 7.5pt;
            line-height: 1.2;
            margin-top: 0.5mm;
            color: #444;
        }
        .track-box {
            text-align: right;
            flex: 0 0 auto;
        }
        .track-label {
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #666;
            margin-bottom: 1mm;
        }
        .track-value {
            font-size: 11pt;
            font-weight: 800;
            line-height: 1;
        }
        .barcode {
            text-align: right;
            margin: 1.5mm 0 1mm;
        }
        .barcode img {
            width: 55%;
            /* max-width: 90mm; */
            height: auto;
        }
        .mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
        }
        .card {
            border: 1px solid #d8d8d8;
            border-radius: 1.5mm;
            padding: 2mm;
            background: #fafafa;
            min-height: 0;
        }
        .card-title {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding-bottom: 1.2mm;
            margin-bottom: 1.5mm;
            border-bottom: 1px solid #e4e4e4;
        }
        .rowline {
            display: flex;
            gap: 1.2mm;
            font-size: 7.2pt;
            line-height: 1.25;
            margin-bottom: 0.9mm;
        }
        .lbl {
            flex: 0 0 24%;
            color: #666;
            font-weight: 700;
        }
        .val {
            flex: 1;
            min-width: 0;
            word-break: break-word;
            color: #111;
        }
        .full {
            grid-column: 1 / -1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2mm;
        }
        .stat {
            text-align: center;
            border: 1px solid #d8d8d8;
            border-radius: 1.5mm;
            padding: 1.8mm 1mm;
            background: #fff;
        }
        .stat .k {
            display: block;
            font-size: 6.5pt;
            color: #666;
            font-weight: 700;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }
        .stat .v {
            display: block;
            font-size: 10pt;
            font-weight: 800;
            line-height: 1;
        }
        .items {
            margin-top: 1mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #d8d8d8;
            padding: 1.4mm 1.2mm;
            font-size: 7pt;
            vertical-align: top;
            word-break: break-word;
        }
        th {
            background: #111;
            color: #fff;
            font-weight: 700;
            text-align: left;
        }
        .footer-note {
            margin-top: 2.5mm;
            font-size: 6.5pt;
            color: #555;
            text-align: center;
        }
        .print-button {
            text-align: center;
            margin-top: 4mm;
        }
        .print-button button {
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print {
            .print-button { display: none; }
            body { background: #fff; }
            .label { padding: 3mm; }
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="panel">
            <div class="header">
                <div class="brand">
                    <?php if (!empty($core->logo)) : ?>
                        <img src="assets/<?php echo h($core->logo); ?>" alt="<?php echo h($core->site_name); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="barcode">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($row->order_prefix . $row->order_no); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="Barcode">
                </div>
            </div>


            <div class="mini-grid">
                <div class="card">
                    <div class="card-title"><?php echo h($lang['inv-shipping5']); ?></div>
                    <div class="rowline"><div class="lbl">Name</div><div class="val"><strong><?php echo h($sender_data->fname . ' ' . $sender_data->lname); ?></strong></div></div>
                    <div class="rowline"><div class="lbl">Phone</div><div class="val"><?php echo h($sender_data->phone); ?></div></div>
                    <div class="rowline"><div class="lbl">Address</div><div class="val"><?php echo h($address_order->sender_address ?? 'N/A'); ?></div></div>
                    <div class="rowline"><div class="lbl">Location</div><div class="val"><?php echo h(($address_order->sender_city ?? 'N/A') . ', ' . ($address_order->sender_country ?? 'N/A')); ?></div></div>
                </div>


                <div class="card">
                    <div class="card-title">Shipment Details</div>
                    <div class="">
                        <div>
                            <div class="rowline"><div class="lbl">Tracking</div><br><div class="val"><strong><?php echo h($package_tracking->tracking_number ?? 'N/A'); ?></strong></div></div>
                            <div class="rowline"><div class="lbl">Courier</div><div class="val"><?php echo h($courier_com->name_com ?? 'N/A'); ?></div></div>
                        </div>
                        <div>
                            <div class="rowline"><div class="lbl">Mode</div><div class="val"><?php echo h($shipping_mode->ship_mode ?? 'N/A'); ?></div></div>
                            <div class="rowline"><div class="lbl">Category</div><div class="val"><?php echo h($category->name_item ?? 'N/A'); ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="card full items">
                    <div class="card-title">Items</div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 18%;">Qty</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($order_items) : ?>
                                <?php foreach ($order_items as $item) : ?>
                                    <tr>
                                        <td><?php echo h((int)$item->order_item_quantity); ?></td>
                                        <td><?php echo h($item->order_item_description); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="2">No items found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="footer-note">
                <?php echo '<br>Developed by <b>iSolveAfrica</b><br>+233 (0) 59 144 7845<br>https://www.isolveafrica.com/'; ?>
            </div>
        </div>
    </div>
    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> Print Label
        </button>
    </div>
</body>
</html>
