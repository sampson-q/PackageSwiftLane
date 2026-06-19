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

// Sender
$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

// Recipient — 'user' ships to the account holder (self); otherwise a saved recipient
$recipient_type = isset($row->recipient_type) ? $row->recipient_type : 'recipient';
if ($recipient_type === 'user') {
    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients WHERE id='" . intval($row->receiver_id) . "'");
}
$receiver_data = $db->cdp_registro();

// Address
$db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();

// Status style
$db->cdp_query("SELECT * FROM cdb_styles WHERE id='" . (int)$row->status_courier . "'");
$status_style = $db->cdp_registro();

// Tracking and ETA
$package_tracking = cdp_getPackageTrackingLegacyAware($_GET['id']);

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// ── Derived values ──────────────────────────────────────────────────────────
$swift_tracking  = $row->order_prefix . $row->order_no;             // system package tracking (banner)
$postal_tracking = $package_tracking->tracking_number ?? '';        // carrier / postal tracking (bottom barcode)
$eta             = $package_tracking->estimated_eta ?? '';

// FROM (sender)
$from_name    = trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''));
$from_phone   = $sender_data->phone ?? '';
$from_address = $address_order->sender_address ?? '';
$from_city    = $address_order->sender_city ?? '';
$from_state   = $address_order->sender_state ?? '';
$from_zip     = $address_order->sender_zip_code ?? '';
$from_country = $address_order->sender_country ?? '';

// SHIP TO (recipient)
if ($recipient_type === 'user') {
    $to_name    = trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''));
    $to_phone   = $sender_data->phone ?? '';
    $to_address = $address_order->sender_address ?? '';
    $to_city    = $address_order->sender_city ?? '';
    $to_state   = $address_order->sender_state ?? '';
    $to_zip     = $address_order->sender_zip_code ?? '';
    $to_country = $address_order->sender_country ?? '';
} else {
    $to_name    = trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? ''));
    $to_phone   = $receiver_data->phone ?? '';
    $to_address = $address_order->recipient_address ?? '';
    $to_city    = $address_order->recipient_city ?? '';
    $to_state   = $address_order->recipient_state ?? '';
    $to_zip     = $address_order->recipient_zip_code ?? '';
    $to_country = $address_order->recipient_country ?? '';
}

// Original package weight (stored on the order; fall back to summed item weights)
$pkg_weight = $row->total_weight ?? '';
if ($pkg_weight === '' || $pkg_weight === null) {
    $sum = 0;
    foreach ($order_items as $it) { $sum += (float)$it->order_item_weight; }
    $pkg_weight = $sum;
}

// QR payload — the sender's details
$qr_data = "FROM: " . $from_name
    . "\nTel: " . $from_phone
    . "\n" . $from_address
    . "\n" . trim($from_city . ', ' . $from_country, ', ');

$qr_src = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($qr_data)
    . '&code=QRCode&translate-esc=true&unit=Fit&dpi=96&imagetype=Gif&rotation=0'
    . '&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=1';

// Bottom barcode — the carrier / postal tracking
$barcode_value = $postal_tracking !== '' ? $postal_tracking : $swift_tracking;
$barcode_src = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($barcode_value)
    . '&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Gif&rotation=0'
    . '&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50';

function label_addr_lines($address, $city, $state, $zip, $country) {
    $line2 = trim(implode(', ', array_filter([$city, $state, $zip])), ', ');
    return array_filter([$address, $line2, $country]);
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo h($core->favicon); ?>">
    <title><?php echo h($lang['inv-shipping19'] . ' - ' . $swift_tracking); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <style>
        @page { size: 102mm 152mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            width: 102mm;
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .label {
            width: 102mm;
            min-height: 152mm;
            padding: 3mm;
            border: 1.5pt solid #000;
        }

        /* Header: logo (left) + sender QR (right) */
        .lab-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3mm;
            padding-bottom: 2mm;
            border-bottom: 2pt solid #000;
        }
        .lab-logo img { max-height: 16mm; max-width: 50mm; object-fit: contain; }
        .lab-logo .brand-name { font-size: 12pt; font-weight: 800; }
        .lab-qr { text-align: center; flex: 0 0 auto; }
        .lab-qr img { width: 22mm; height: 22mm; object-fit: contain; }
        .lab-qr .cap { font-size: 6pt; letter-spacing: .3px; text-transform: uppercase; color: #000; }

        /* Swift tracking banner (the "Priority Mail" slot = our package tracking) */
        .swift-banner {
            margin: 2mm 0;
            border: 1.5pt solid #000;
            text-align: center;
            padding: 1.5mm 2mm;
        }
        .swift-banner .k {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .swift-banner .v {
            font-size: 18pt;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: 1px;
        }
        .swift-banner .meta {
            font-size: 7pt;
            margin-top: 1mm;
            display: flex;
            justify-content: center;
            gap: 3mm;
            flex-wrap: wrap;
        }
        .badge-status {
            display: inline-block;
            color: #fff;
            font-weight: 700;
            padding: 0.3mm 1.6mm;
            border-radius: 1mm;
            font-size: 7pt;
        }

        /* FROM / SHIP TO */
        .addr-grid { display: flex; gap: 2mm; margin-bottom: 2mm; }
        .addr {
            flex: 1;
            border: 1pt solid #000;
            padding: 1.8mm;
            min-width: 0;
        }
        .addr .tag {
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1pt solid #000;
            margin-bottom: 1mm;
            padding-bottom: .6mm;
        }
        .addr .nm { font-size: 8.5pt; font-weight: 800; }
        .addr .ln { font-size: 7.3pt; line-height: 1.3; word-break: break-word; }
        .addr .tel { font-size: 7.3pt; margin-top: .6mm; }

        /* Items table (your spec) */
        .shipment-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2mm;
        }
        .shipment-table th, .shipment-table td {
            border: 1pt solid #000;
            padding: 1.2mm 1.4mm;
            font-size: 7.4pt;
            vertical-align: top;
            word-break: break-word;
        }
        .shipment-table thead th {
            background: #000;
            color: #fff;
            text-align: left;
            font-weight: 700;
        }
        .shipment-table thead th:first-child { width: 16%; text-align: center; }
        .shipment-table tbody td:first-child { text-align: center; font-weight: 700; }
        .shipment-table tfoot td {
            font-weight: 800;
            font-size: 7.6pt;
            background: #f0f0f0;
        }

        /* Bottom carrier tracking barcode */
        .carrier {
            border-top: 2pt solid #000;
            padding-top: 2mm;
            text-align: center;
        }
        .carrier .cap {
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .carrier img { width: 85%; height: 16mm; object-fit: contain; margin: 1mm 0 .5mm; }
        .carrier .num { font-size: 9pt; font-weight: 700; letter-spacing: 1px; }

        .print-button { text-align: center; margin-top: 4mm; }
        .print-button button { padding: 10px 18px; font-size: 14px; cursor: pointer; }
        @media print {
            .print-button { display: none; }
            .label { border: none; }
        }
    </style>
</head>
<body>
    <div class="label">

        <!-- Header: logo + sender QR -->
        <div class="lab-head">
            <div class="lab-logo">
                <?php if (!empty($core->logo)) : ?>
                    <img src="assets/<?php echo h($core->logo); ?>" alt="<?php echo h($core->site_name); ?>">
                <?php else : ?>
                    <span class="brand-name"><?php echo h($core->site_name); ?></span>
                <?php endif; ?>
            </div>
            <div class="lab-qr">
                <img src="<?php echo h($qr_src); ?>" alt="Sender QR">
                <div class="cap"><?php echo h($lang['left498'] ?? 'Sender'); ?></div>
            </div>
        </div>

        <!-- Swift (system) package tracking -->
        <div class="swift-banner">
            <div class="k"><?php echo h($lang['ltracking'] ?? 'Tracking'); ?></div>
            <div class="v"><?php echo h($swift_tracking); ?></div>
            <div class="meta">
                <?php if (!empty($status_style->mod_style)) : ?>
                    <span class="badge-status" style="background: <?php echo h($status_style->color); ?>;"><?php echo h($status_style->mod_style); ?></span>
                <?php endif; ?>
                <?php if (!empty($shipping_mode->ship_mode)) : ?>
                    <span><strong><?php echo h($shipping_mode->ship_mode); ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($eta)) : ?>
                    <span>ETA: <strong><?php echo h($eta); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- FROM / SHIP TO -->
        <div class="addr-grid">
            <div class="addr">
                <div class="tag"><?php echo h($lang['left498'] ?? 'Sender'); ?></div>
                <div class="nm"><?php echo h($from_name); ?></div>
                <?php foreach (label_addr_lines($from_address, $from_city, $from_state, $from_zip, $from_country) as $ln) : ?>
                    <div class="ln"><?php echo h($ln); ?></div>
                <?php endforeach; ?>
                <?php if (!empty($from_phone)) : ?><div class="tel">Tel: <?php echo h($from_phone); ?></div><?php endif; ?>
            </div>
            <div class="addr">
                <div class="tag"><?php echo h($lang['left499'] ?? 'Recipient'); ?></div>
                <div class="nm"><?php echo h($to_name); ?></div>
                <?php foreach (label_addr_lines($to_address, $to_city, $to_state, $to_zip, $to_country) as $ln) : ?>
                    <div class="ln"><?php echo h($ln); ?></div>
                <?php endforeach; ?>
                <?php if (!empty($to_phone)) : ?><div class="tel">Tel: <?php echo h($to_phone); ?></div><?php endif; ?>
            </div>
        </div>

        <!-- Items -->
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
            <tfoot>
                <tr>
                    <td colspan="2">
                        Original Package Weight: <?php echo h($pkg_weight !== '' ? $pkg_weight : '—'); ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Carrier / postal tracking -->
        <div class="carrier">
            <div class="cap"><?php echo h($lang['ltracking'] ?? 'Tracking'); ?> #<?php echo $postal_tracking === '' ? ' (Pending)' : ''; ?></div>
            <?php if ($postal_tracking !== '') : ?>
                <img src="<?php echo h($barcode_src); ?>" alt="Carrier barcode">
                <div class="num"><?php echo h($postal_tracking); ?></div>
            <?php else : ?>
                <div class="num" style="font-weight:700; padding:3mm 0;">Awaiting carrier tracking</div>
            <?php endif; ?>
        </div>

    </div>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> Print Label
        </button>
    </div>
</body>
</html>
