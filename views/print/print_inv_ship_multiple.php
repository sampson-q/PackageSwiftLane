<?php
// *************************************************************************
// * Bulk shipment invoice/receipt — one continuous thermal roll output   *
// * Single header, all shipments in one flow, single footer at the end.   *
// *************************************************************************
require_once('helpers/querys.php');

if (!isset($_GET['data'])) {
    cdp_redirect_to("courier_list.php");
}

$order_list = json_decode($_GET['data']);

if (!is_array($order_list) || count($order_list) === 0) {
    cdp_redirect_to("courier_list.php");
}

$shipment_total = count($order_list);
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title><?php echo $lang['inv-shipping19'] . ' - ' . $shipment_total; ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type="text/css" href="assets/custom_dependencies/print.css" rel="stylesheet" />

    <style>
        /*
         * Thermal roll, fixed-page-size driver (Premax):
         * We can't use true "infinite continuous roll" because the driver
         * is configured with a fixed page size, not receipt/continuous mode.
         * Instead we set a generous but FINITE ceiling. The actual rendered
         * height is driven entirely by real content (see .label-page below),
         * so short batches print short and only batches that approach this
         * ceiling would ever risk a second page.
         * 4000mm covers ~30+ shipments with room to spare; raise if your
         * largest realistic batch could ever exceed it.
         */
        @page {
            size: 110mm 4000mm;
            margin: 0;
        }

        html, body {
            width: 110mm;
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

        .label-page {
            width: 110mm;
            height: auto;
            padding: 4mm 3.5mm 5mm;
            overflow: visible;
            display: block;
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

        .shipment-block {
            margin-bottom: 6mm;
            overflow: visible;
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

        .sn-row {
            align-items: baseline;
        }

        .sn-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
        }

        .sn-total {
            font-size: 15px;
            font-weight: 700;
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
            margin-top: 8mm;
            overflow: visible;
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

        body, .label-page, .panel, .items-panel, .signature, .credits,
        .topbar, .brand-text, .brand-lines, .brand-name, .panel-title, .items-header h3,
        .kv, .kv .k, .kv .v, table, thead th, th, td, tfoot td {
            color: #000 !important;
        }

        .panel, .items-panel {
            border-color: #000 !important;
        }

        .topbar, .panel-title, th, td, tfoot td {
            border-color: #000 !important;
        }

        .brand-name,
        .panel-title,
        .items-header h3,
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
        .credits {
            font-weight: 600 !important;
        }

        .logo img,
        .barcode img {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .financial-sn{
            display:inline-flex;
            align-items:center;
            justify-content:center;

            width:28px;
            height:28px;

            border:2px solid #000;
            border-radius:50%;

            background:#fff;
            color:#000 !important;

            font-weight:800;
            font-size:15px;
            line-height:1;

            -webkit-print-color-adjust:exact;
            print-color-adjust:exact;
        }

        @media print {
            html, body {
                width: 110mm !important;
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

            .shipment-block,
            .panel,
            .items-panel,
            tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .label-page {
                width: 110mm !important;
                max-width: 110mm !important;
                height: auto !important;
                padding: 4mm 3.5mm 5mm !important;
                overflow: visible !important;
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
<body>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print all <?php echo $shipment_total; ?> receipt(s)</div>
    </div>

    <div class="label-page">
        <!-- ONE HEADER ONLY -->
        

        <?php
        foreach ($order_list as $order_no) {

            $data = cdp_getCourierPrintMultiple($order_no);

            if (!isset($data['rowCount']) || (int) $data['rowCount'] !== 1) {
                continue;
            }

            $row      = $data['data'];
            $order_id = (int) $row->order_id;

            $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $order_id . "'");
            $order_items = $db->cdp_registros();

            $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id='" . (int) $row->order_service_options . "'");
            $shipping_mode = $db->cdp_registro();

            $db->cdp_query("SELECT * FROM cdb_category WHERE id='" . (int) $row->order_item_category . "'");
            $category = $db->cdp_registro();

            $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . (int) $row->order_courier . "'");
            $courier_com = $db->cdp_registro();

            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int) $row->sender_id . "'");
            $sender_data = $db->cdp_registro();

            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . (int) $row->receiver_id . "'");
            $receiver_data = $db->cdp_registro();

            $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
            $address_order = $db->cdp_registro();

            $package_tracking = cdp_getPackageTrackingLegacyAware($order_id);

            // Financial-sheet S/N for this package (cached per consolidation).
            // Optional consolidate_id pins all labels to one sheet (default: newest).
            $financial_serial = cdp_getOrderFinancialSerial(
                $row->order_prefix,
                $row->order_no,
                (int) ($_REQUEST['consolidate_id'] ?? 0)
            );

            $total_qty    = 0;
            $total_weight = 0;

            if ($order_items) {
                foreach ($order_items as $item) {
                    $total_weight += (float) $item->order_item_weight;
                    $total_qty    += (int) $item->order_item_quantity;
                }
            }
        ?>
            <div class="shipment-block">
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
                </div>

                <div class="barcode">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($row->order_prefix . $row->order_no); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
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

                    <?php if ($financial_serial) : ?>
                        <div style="width:100%; text-align:right;">
                            <span class="financial-sn">
                                <?php echo (int) $financial_serial['sn']; ?>
                            </span>
                        </div>
                    <?php endif; ?>

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
                        <tbody id="projects-tbl">
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
                                    <?php echo htmlspecialchars((string) ($row->total_weight ?? $total_weight ?? '—'), ENT_QUOTES, 'UTF-8') . '<br><b>Total Items:</b> ' . $total_qty; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
        <?php
        } // end foreach
        ?>

        <!-- ONE FOOTER ONLY -->
        
    </div>

    <script>
    (function () {
        var images = document.querySelectorAll('img');
        var total = images.length;
        var loaded = 0;

        function tryPrint() {
            loaded++;
            if (loaded >= total) {
                // Small delay lets the browser finish layout/paint after the
                // last barcode image lands before we snapshot the page for print.
                setTimeout(function () { window.print(); }, 150);
            }
        }

        if (total === 0) {
            window.print();
            return;
        }

        images.forEach(function (img) {
            if (img.complete) {
                tryPrint();
            } else {
                img.addEventListener('load', tryPrint);
                // Don't hang forever if a barcode image fails to fetch (e.g. tec-it.com down)
                img.addEventListener('error', tryPrint);
            }
        });
    })();
    </script>
</body>
</html>