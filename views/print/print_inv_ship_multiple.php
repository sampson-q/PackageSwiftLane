<?php
// *************************************************************************
// * Bulk shipment invoice/receipt — prints one print_inv_ship layout per  *
// * selected shipment. Mirrors the single views/print/print_inv_ship.php   *
// * exactly (110mm thermal label), one receipt per page-break.             *
// * NOTE: the bulk-select checkboxes carry order_no, so each shipment is    *
// * resolved with cdp_getCourierPrintMultiple($order_no), then $row->order_id*
// * is used for the item/address/tracking lookups.                          *
// *************************************************************************
require_once('helpers/querys.php');

if (!isset($_GET['data'])) {
    cdp_redirect_to("courier_list.php");
}

$order_list = json_decode($_GET['data']);

if (!is_array($order_list) || count($order_list) === 0) {
    cdp_redirect_to("courier_list.php");
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title><?php echo $lang['inv-shipping19'] . ' - ' . count($order_list); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type="text/css" href="assets/custom_dependencies/print.css" rel="stylesheet" />

    <style>
        @page {
            size: 110mm auto;
            margin: 0;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            overflow: visible;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.35;
            color: #000;
        }

        /* One receipt per shipment; each starts on a new page when printed. */
        .receipt { page-break-after: always; }
        .receipt:last-of-type { page-break-after: auto; }

        .label-page {
            width: 100%;
            max-width: 110mm;
            padding: 4mm 3.5mm 5mm;
            overflow: visible;
            display: block;
            margin: 0 auto 6mm auto;
        }

        .topbar {
            border-bottom: 0.35mm solid #000;
            padding-bottom: 2.2mm;
            margin-bottom: 2.2mm;
        }

        .brand-wrap {
            display: block;
        }

        .logo {
            text-align: center;
            margin-bottom: -15.5mm;
            margin-top: -15mm;
        }

        .logo img {
            display: inline-block;
            max-width: 120mm;
            max-height: 54mm;
            object-fit: contain;
        }

        .brand-text {
            min-width: 0;
            text-align: center;
        }

        .brand-name {
            margin: 0 0 1mm 0;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-lines {
            margin: 0;
            font-size: 16px;
            line-height: 1.35;
            word-break: break-word;
        }

        .barcode {
            text-align: center;
            margin-top: 1.5mm;
        }

        .barcode img {
            display: inline-block;
            width: 100%;
            max-width: 80mm;
            height: 20mm;
            object-fit: contain;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2mm;
        }

        .panel {
            border: 0.35mm solid #000;
            padding: 1.5mm 1.8mm;
            overflow: visible;
        }

        .panel-title {
            margin: 0 0 1mm 0;
            padding-bottom: 0.8mm;
            border-bottom: 0.25mm solid #000;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .kv {
            display: grid;
            grid-template-columns: 19mm 1fr;
            gap: 1.2mm;
            margin: 0 0 0.8mm 0;
            font-size: 15px;
            line-height: 1.35;
        }

        .kv .k {
            font-weight: 700;
            white-space: nowrap;
        }

        .kv .v {
            min-width: 0;
            word-break: break-word;
        }

        .items-panel {
            border: 0.35mm solid #000;
            padding: 1.2mm 1.4mm;
            overflow: visible;
            min-height: 0;
        }

        .items-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            margin-bottom: 1mm;
        }

        .items-header h3 {
            margin: 0;
            font-size: 14.5px;
            font-weight: 700;
            line-height: 1.1;
        }

        .meta-chip {
            font-size: 14px;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            background: #f0f0f0;
            font-size: 16px;
            font-weight: 700;
        }

        th, td {
            border: 0.2mm solid #000;
            padding: 0.75mm 1mm;
            vertical-align: top;
            word-break: break-word;
        }

        td.qty {
            width: 16mm;
            text-align: center;
            font-weight: 700;
        }

        td.desc {
            width: auto;
        }

        tfoot td {
            font-size: 16px;
            padding: 0.8mm 1mm;
            background: #fafafa;
        }

        .footer {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2mm;
            align-items: start;
        }

        .total-box {
            border: 0.35mm solid #000;
            padding: 1.5mm;
            text-align: center;
        }

        .total-box label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }

        .total-box .value {
            font-size: 23px;
            font-weight: 700;
            line-height: 1;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            align-items: end;
        }

        .signature {
            text-align: center;
            font-size: 14px;
            line-height: 1.2;
        }

        .signature .line {
            margin-top: 43mm;
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
        }

        .credits {
            text-align: center;
            font-size: 12px;
            margin-top: 5mm;
        }

        .print-button {
            text-align: center;
            margin: 2mm 0 0 0;
        }

        .print-button button {
            padding: 10px 24px;
            font-size: 20px;
            cursor: pointer;
        }

        .print-info {
            margin-top: 1mm;
            font-size: 14.5px;
            color: #000;
        }

        /* Stronger contrast for thermal printing */
        body, .label-page, .panel, .items-panel, .total-box, .signature, .credits,
        .topbar, .brand-text, .brand-lines, .brand-name, .panel-title, .items-header h3,
        .meta-chip, .kv, .kv .k, .kv .v, table, thead th, th, td, tfoot td {
            color: #000 !important;
        }

        .panel, .items-panel, .total-box {
            border-color: #000 !important;
        }

        .topbar, .panel-title, th, td, tfoot td, .total-box {
            border-color: #000 !important;
        }

        .brand-name,
        .panel-title,
        .items-header h3,
        .total-box label,
        .kv .k,
        .signature .line,
        .print-info {
            font-weight: 700 !important;
        }

        .brand-lines,
        .kv .v,
        td,
        th,
        tfoot td,
        .credits,
        .meta-chip {
            font-weight: 600 !important;
        }

        .logo img,
        .barcode img {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        @media print {
            html, body {
                width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                -webkit-text-size-adjust: 100% !important;
                text-size-adjust: 100% !important;
            }

            * {
                color: #000 !important;
                text-shadow: none !important;
                box-shadow: none !important;
                filter: none !important;
            }

            .label-page {
                width: 100% !important;
                max-width: 110mm !important;
                height: auto !important;
                min-height: 0 !important;
                padding: 4mm 3.5mm 5mm !important;
                overflow: visible !important;
                margin: 0 auto !important;
            }

            .topbar,
            .panel,
            .items-panel,
            .total-box,
            .signature .line,
            th,
            td,
            tfoot td {
                border-color: #000 !important;
            }

            .print-button,
            .print-info {
                display: none !important;
            }

            a[href]:after {
                content: "";
            }
        }
    </style>

</head>
<body onload="window.print();">

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print all <?php echo count($order_list); ?> receipt(s)</div>
    </div>

<?php
foreach ($order_list as $order_no) {

    $data = cdp_getCourierPrintMultiple($order_no);

    // Skip silently if the shipment can't be resolved (deleted / bad id).
    if (!isset($data['rowCount']) || (int) $data['rowCount'] !== 1) {
        continue;
    }

    $row      = $data['data'];
    $order_id = (int) $row->order_id;

    // Get order items
    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $order_id . "'");
    $order_items = $db->cdp_registros();

    // Get shipping mode
    $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id='" . (int) $row->order_service_options . "'");
    $shipping_mode = $db->cdp_registro();

    // Get category
    $db->cdp_query("SELECT * FROM cdb_category WHERE id='" . (int) $row->order_item_category . "'");
    $category = $db->cdp_registro();

    // Get courier
    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . (int) $row->order_courier . "'");
    $courier_com = $db->cdp_registro();

    // Get sender and receiver
    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int) $row->sender_id . "'");
    $sender_data = $db->cdp_registro();

    $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int) $row->receiver_id . "'");
    $receiver_data = $db->cdp_registro();

    // Get address
    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
    $address_order = $db->cdp_registro();

    // Get tracking and ETA
    $package_tracking = cdp_getPackageTrackingLegacyAware($order_id);

    $total_qty    = 0;
    $total_weight = 0;
    if ($order_items) {
        foreach ($order_items as $item) {
            $total_weight += (float) $item->order_item_weight;
            $total_qty    += (int) $item->order_item_quantity;
        }
    }
?>
    <div class="receipt">
    <div class="label-page">
        <div class="topbar">
            <div class="brand-wrap">
                <div class="logo">
                    <?php echo ($core->logo) ? '<img src="assets/uploads/SWIFT LOGO PNG-04.png" alt="' . htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') . '"/>' : '<h3>' . htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') . '</h3>'; ?>
                </div>
                <div class="brand-text" style="color:#000 !important;">
                    <p class="brand-name"><?php echo htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="brand-lines">
                        <strong><?php echo $lang['inv-shipping2']; ?>:</strong> <?php echo htmlspecialchars("+233(0)243438799 || +233(0)342292798", ENT_QUOTES, 'UTF-8'); ?><br>
                        <strong><?php echo $lang['inv-shipping3']; ?>:</strong> <?php echo htmlspecialchars($core->site_email, ENT_QUOTES, 'UTF-8'); ?><br>
                        <strong>Address:</strong> <?php echo htmlspecialchars("#01, Adaman Crescent, Behind The Allied Filling Station, Tesano Abeka Junction", ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>

            <div class="barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($row->order_prefix . $row->order_no); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
            </div>
        </div>

        <div class="info-grid">
            <div class="panel">
                <div class="panel-title"><?php echo htmlspecialchars($lang['inv-shipping5'], ENT_QUOTES, 'UTF-8'); ?></div>

                <div class="kv">
                    <div class="k">Name:</div>
                    <div class="v"><strong><?php echo htmlspecialchars($sender_data->fname . " " . $sender_data->lname, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                </div>

                <div class="kv">
                    <div class="k">Address:</div>
                    <div class="v"><?php echo htmlspecialchars($address_order ? $address_order->sender_address : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="kv">
                    <div class="k">Location:</div>
                    <div class="v"><?php echo htmlspecialchars($address_order ? ($address_order->sender_city . ', ' . $address_order->sender_country) : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="kv">
                    <div class="k">Phone:</div>
                    <div class="v"><?php echo htmlspecialchars($sender_data->phone, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Shipment Details</div>

            <div class="kv">
                <div class="k">Tracking #:</div>
                <div class="v">&nbsp;&nbsp;&nbsp;&nbsp;<strong><?php echo htmlspecialchars($package_tracking->tracking_number ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong></div>
            </div>

            <div class="kv">
                <div class="k">Courier:</div>
                <div class="v"><?php echo htmlspecialchars($courier_com ? $courier_com->name_com : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="kv">
                <div class="k">Category:</div>
                <div class="v"><?php echo htmlspecialchars($category ? $category->name_item : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <?php if (!empty($package_tracking->tracking_number)) : ?>
                <div class="barcode">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($package_tracking->tracking_number); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
                </div>
            <?php endif; ?>
        </div>

        <div class="items-panel">
            <div class="items-header">
                <h3><?php echo htmlspecialchars("Items Details", ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 14mm;"><?php echo $lang['left214']; ?></th>
                        <th><?php echo $lang['left213']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($order_items) : foreach ($order_items as $row_order_item) : ?>
                        <tr>
                            <td class="qty"><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                            <td class="desc"><?php echo htmlspecialchars($row_order_item->order_item_description, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">
                            <strong>Original Package Weight</strong> :
                            <?php echo htmlspecialchars((string) ($row->total_weight ?? $total_weight ?? '—'), ENT_QUOTES, 'UTF-8') . ', <b>Total Items:</b> ' . $total_qty; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
    </div>
<?php
} // end foreach
?>

    <!-- Signature + credits appear ONCE, at the very end of the whole batch. -->
    <div class="label-page batch-footer">
        <div class="footer">
            <div class="signature">
                <div class="line"><?php echo 'Signature / Stamp' ?></div>
            </div>

            <div class="credits text-center">
                Designed by <b>iSolveAfrica</b><br>
                +233 (0) 591 447 845<br>
                https://www.isolveafrica.com
            </div>
        </div>
    </div>

</body>
</html>
