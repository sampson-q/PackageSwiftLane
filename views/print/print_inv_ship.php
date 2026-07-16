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

if ($order_items) {
    foreach ($order_items as $item) {
        $total_weight += (float) $item->order_item_weight;
    }
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title><?php echo $lang['inv-shipping19'] . ' - ' . $row->order_prefix . $row->order_no; ?></title>
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

        .label-page {
            width: 100%;
            max-width: 110mm;
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

        /* PAID stamp. Black-only by design: this prints on a monochrome
           thermal printer, so the mark has to read from the border and the
           weight rather than from colour. */
        .paid-stamp {
            border: 0.6mm solid #000;
            padding: 1.5mm;
            text-align: center;
            margin-bottom: 2mm;
        }

        .paid-mark {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 2mm;
            line-height: 1;
            text-transform: uppercase;
        }

        .paid-detail {
            font-size: 12px;
            line-height: 1.35;
            margin-top: 1mm;
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
        .meta-chip, .kv, .kv .k, .kv .v, table, thead th, th, td, tfoot td,
        .paid-stamp, .paid-mark, .paid-detail {
            color: #000 !important;
        }

        .panel, .items-panel, .total-box, .paid-stamp {
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

        .financial-sn{
            display:inline-flex;
            align-items:center;
            justify-content:center;

            min-width:28px;
            height:28px;

            padding:0 8px;

            border:2px solid #000;
            border-radius:999px;

            background:#fff;
            color:#000 !important;

            font-weight:800;
            font-size:15px;
            line-height:1;
            white-space:nowrap;

            -webkit-print-color-adjust:exact;
            print-color-adjust:exact;
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
<body>
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

            <!-- <div class="kv">
                <div class="k">Mode:</div>
                <div class="v"><strong><?php echo htmlspecialchars($shipping_mode ? $shipping_mode->ship_mode : 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong></div>
            </div> -->

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
                    <?php
                    if ($order_items) {

                        $sumador_total           = 0.0;
                        $sumador_valor_declarado = 0.0;
                        $sumador_fixed_charge    = 0.0;
                        $max_fixed_charge        = 0.0;
                        $sumador_libras          = 0.0;
                        $sumador_volumetric      = 0.0;
                        $base_packages           = 0.0;
                        $total_impuesto          = 0.0;
                        $total_seguro            = 0.0;
                        $total_peso              = 0.0;
                        $total_descuento         = 0.0;
                        $total_impuesto_aduanero = 0.0;
                        $total_valor_declarado   = 0.0;

                        $meter              = (float) $row->volumetric_percentage;
                        $price_lb           = (float) $row->value_weight;
                        $tax_value          = (float) $row->tax_value;
                        $tax_discount       = (float) $row->tax_discount;
                        $insurance_value    = (float) $row->tax_insurance_value;
                        $tariffs_value      = (float) $row->tax_custom_tariffis_value;
                        $declared_value_tax = (float) $row->declared_value;
                        $insured_value      = (float) $row->total_insured_value;
                        $reexpedicion_value = (float) $row->total_reexp;

                        if ($meter <= 0) {
                            $meter = 1;
                        }

                        $total_qty = 0;

                        foreach ($order_items as $row_order_item) {

                            $qty = (float) $row_order_item->order_item_quantity;
                            if ($qty <= 0) {
                                $qty = 1;
                            }

                            $total_qty += (int) $row_order_item->order_item_quantity;

                            $description_item  = $row_order_item->order_item_description;
                            $weight_item       = (float) $row_order_item->order_item_weight;
                            $custom_price_item = isset($row_order_item->custom_price) ? (float) $row_order_item->custom_price : 0.0;
                            $use_custom_item   = $custom_price_item > 0 ? 1 : 0;

                            if ($use_custom_item) {
                                $line_total_item = $custom_price_item * $qty;
                            } else {
                                $line_total_item = $weight_item * $qty * $price_lb;
                            }

                            $sumador_libras          += $weight_item * $qty;
                            $base_packages           += $line_total_item;
                            $sumador_valor_declarado += (float)$row_order_item->order_item_declared_value * $qty;
                            $sumador_fixed_charge    += (float)$row_order_item->order_item_fixed_value * $qty;
                            $max_fixed_charge        += (float)$row_order_item->order_item_fixed_value * $qty;
                            ?>
                            <tr>
                                <td class="qty"><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                                <td class="desc"><?php echo htmlspecialchars($description_item, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php
                        }

                        $sumador_libras     = round($sumador_libras, 2);
                        $sumador_volumetric = 0.0;
                        $calculate_weight   = $sumador_libras;
                        $sumador_total      = round($base_packages, 2);

                        if ($sumador_total > $core->min_cost_tax) {
                            $total_impuesto = $sumador_total * ($tax_value / 100);
                        }

                        if ($sumador_valor_declarado > $core->min_cost_declared_tax) {
                            $total_valor_declarado = $sumador_valor_declarado * ($declared_value_tax / 100);
                        }

                        $discount_type = (isset($row->discount_type) && $row->discount_type === 'amount') ? 'amount' : 'percent';
                        $total_descuento = ($discount_type === 'amount')
                            ? $tax_discount
                            : $sumador_total * ($tax_discount / 100);

                        if ($tax_discount < 0 || $total_descuento > $sumador_total) {
                            $total_descuento = 0;
                        }

                        $total_peso = $sumador_libras + $sumador_volumetric;
                        $total_seguro = $insured_value * ($insurance_value / 100);
                        $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

                        $total_envio = ($sumador_total - $total_descuento)
                            + $total_seguro
                            + $total_impuesto
                            + $total_impuesto_aduanero
                            + $total_valor_declarado
                            + $max_fixed_charge
                            + $reexpedicion_value;

                        if ($total_envio < 0) {
                            $total_envio = 0;
                        }

                        $sumador_total           = cdb_money_format($sumador_total);
                        $total_envio             = cdb_money_format($total_envio);
                        $total_seguro            = cdb_money_format($total_seguro);
                        $total_impuesto_aduanero = cdb_money_format($total_impuesto_aduanero);
                        $total_impuesto          = cdb_money_format($total_impuesto);
                        $total_descuento         = cdb_money_format($total_descuento);
                        $sumador_valor_declarado = cdb_money_format($sumador_valor_declarado);
                        $sumador_fixed_charge    = cdb_money_format($sumador_fixed_charge);
                        $total_valor_declarado   = cdb_money_format($total_valor_declarado);
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">
                            <strong>Original Package Weight</strong> :
                            <?php echo htmlspecialchars((string)($row->total_weight ?? $total_weight ?? '—'), ENT_QUOTES, 'UTF-8') . '<br><b>Total Items:</b> ' . $total_qty; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <!-- <div class="total-box">
                <label><?php echo 'Total Number of Items'; ?></label>
                <div class="value"><?php echo (int) $total_qty; ?></div>
            </div> -->

            <?php if ($fs_payment) { ?>
                <div class="paid-stamp">
                    <div class="paid-mark">PAID</div>
                    <div class="paid-detail">
                        <?php echo htmlspecialchars($fs_paid_mode, ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php if (!empty($fs_payment->reference)) { ?>
                            Ref: <b><?php echo htmlspecialchars((string) $fs_payment->reference, ENT_QUOTES, 'UTF-8'); ?></b><br>
                        <?php } ?>
                        <?php echo htmlspecialchars(date('d M Y', strtotime((string) $fs_payment->recorded_at)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            <?php } ?>

            <!-- <div class="signatures col-12"> -->
                <div class="signature">
                    <div class="line"><?php echo 'Signature / Stamp' ?></div>
                </div>
                <!-- <div class="signature">
                    <div class="line"><?php echo htmlspecialchars($core->signing_customer, ENT_QUOTES, 'UTF-8'); ?></div>
                </div> -->
            <!-- </div> -->

            <div class="credits text-center">
                Designed by <b>iSolveAfrica</b><br>
                +233 (0) 591 447 845 / +233 (0) 50 550 5009<br>
                Email: hello@isolveafrica.com<br>
                https://www.isolveafrica.com
            </div>
        </div>
    </div>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print</div>
    </div>
</body>
</html>