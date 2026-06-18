<?php
require_once('helpers/querys.php');

if (isset($_GET['id'])) {
    $data = cdp_getCourierPrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("courier_list.php");
}

$row = $data['data'];

// Get order items
$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

// Get shipping mode
$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row->order_service_options . "'");
$shipping_mode = $db->cdp_registro();

// Get category
$db->cdp_query("SELECT * FROM cdb_category where id= '" . $row->order_item_category . "'");
$category = $db->cdp_registro();

// Get courier
$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->order_courier . "'");
$courier_com = $db->cdp_registro();

// Get sender and receiver
$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->receiver_id . "'");
$receiver_data = $db->cdp_registro();

// Get address
$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();

// Get tracking and ETA
$package_tracking = cdp_getPackageTrackingLegacyAware($_GET['id']);

// Calculate totals
$total_weight = 0;
foreach ($order_items as $item) {
    $total_weight += (float)$item->order_item_weight;
}

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['inv-shipping19'] . ' ' ?> - <?php echo $row->order_prefix . $row->order_no; ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type='text/css' href='assets/custom_dependencies/print.css' rel='stylesheet' />
    <style>
        /* ── 80mm thermal receipt ─────────────────────────────────────────── */
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 10px; background: #f5f5f5; }
        .container { width: 80mm; margin: 0 auto; background: white; padding: 4mm 3mm; }
        .header { text-align: center; border-bottom: 1px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .logo img { max-width: 45mm; height: auto; }
        .company-info { padding: 0; }
        .company-info h2 { margin: 6px 0 4px 0; font-size: 15px; }
        .company-info p { margin: 1px 0; font-size: 9px; color: #555; }
        .row-section { display: block; margin-bottom: 10px; }
        .card { border: 1px solid #ddd; padding: 8px; background: #fafafa; margin-bottom: 8px; }
        .card-title { font-weight: bold; font-size: 11px; margin-bottom: 8px; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; }
        .card-row { display: flex; margin-bottom: 5px; font-size: 10px; }
        .card-label { flex: 0 0 38%; font-weight: 600; color: #333; }
        .card-value { flex: 1; color: #555; word-break: break-word; }
        .card-body { padding: 0; }
        h3.card-title span { font-size: 12px; }
        .barcode { text-align: center; margin: 10px 0; }
        .barcode img { max-width: 74mm; height: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        thead { background: #333; color: white; }
        th { padding: 5px; text-align: left; font-weight: 600; font-size: 10px; border: 1px solid #ddd; }
        td { padding: 5px; border: 1px solid #ddd; font-size: 10px; word-break: break-word; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .totals { display: flex; gap: 8px; margin: 12px 0; }
        .total-box { flex: 1; text-align: center; }
        .total-box label { font-size: 9px; color: #666; font-weight: 600; display: block; margin-bottom: 3px; }
        .total-box .value { font-size: 15px; font-weight: bold; color: #333; }
        .footer { margin-top: 14px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9px; }
        .footer table { margin: 0; }
        .footer td { border: none; font-size: 9px; }
        .print-button { text-align: center; margin: 16px 0; }
        .print-button button { padding: 10px 20px; font-size: 14px; cursor: pointer; }
        .print-info { margin-top: 8px; font-size: 10px; color: #999; }
        @media print {
            body { background: white; padding: 0; }
            .container { width: 80mm; max-width: 80mm; padding: 2mm; }
            .print-button, .print-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Logo and Company Info -->
        <div class="header">
            <div class="logo">
                <?php echo ($core->logo) ? '<img src="assets/' . $core->logo . '" alt="' . $core->site_name . '"/>' : '<h3>' . $core->site_name . '</h3>'; ?>
            </div>
            <div class="company-info">
                <h2><?php echo $core->site_name; ?></h2>
                <p><strong><?php echo $lang['inv-shipping2'] ?>:</strong> <?php echo "+233(0)243438799 || +233(0)342292798" ?></p>
                <p><strong><?php echo $lang['inv-shipping3'] ?>:</strong> <?php echo $core->site_email; ?></p>
                <p><strong><?php echo 'Address:' ?></strong> #01, Adaman Crescent, Behind The Allied Filling Station, Tesano Abeka Junction
            </div>
        </div>

        <!-- Barcode -->
        <div class="barcode">
            <img src='https://barcode.tec-it.com/barcode.ashx?data=<?php echo $row->order_prefix . $row->order_no; ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50' alt='' />
        </div>

        <!-- Sender and Recipient Cards -->
        <div class="row-section">
            <div class="card">
                <div class="card-title"><?php echo $lang['inv-shipping5']; ?></div>
                <div class="card-row">
                    <span class="card-label">Name:</span>
                    <span class="card-value"><strong><?php echo $sender_data->fname . " " . $sender_data->lname; ?></strong></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Address:</span>
                    <span class="card-value"><?php echo $address_order ? $address_order->sender_address : 'N/A'; ?></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Location:</span>
                    <span class="card-value"><?php echo $address_order ? $address_order->sender_city . ', ' . $address_order->sender_country : 'N/A'; ?></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Phone:</span>
                    <span class="card-value"><?php echo $sender_data->phone; ?></span>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Shipment Details</div>
                <div class="card-row">
                    <span class="card-label">Tracking #:</span>
                    <span class="card-value"><strong><?php echo $package_tracking->tracking_number ?? 'N/A'; ?></strong></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Courier:</span>
                    <span class="card-value"><?php echo $courier_com ? $courier_com->name_com : 'N/A'; ?></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Shipping Mode:</span>
                    <span class="card-value"><strong><?php echo $shipping_mode ? $shipping_mode->ship_mode : 'N/A'; ?></strong></span>
                </div>
                <div class="card-row">
                    <span class="card-label">Category:</span>
                    <span class="card-value"><?php echo $category ? $category->name_item : 'N/A'; ?></span>
                </div>
            </div>
        </div>

        <!-- Items Table (matching courier_add structure) -->
        <div class="row">
            <div class="col-lg-12 col-xl-12 col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-md-flex align-items-center">
                            <div>
                                <h3 class="card-title"><span><?php echo "Items Details" ?></span></h3>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabla">
                                <thead class="bg-inverse text-white">
                                    <tr>
                                        <th><b><?php echo $lang['left214'] ?></b></th>      <!-- Cantidad -->
                                        <th><b><?php echo $lang['left213'] ?></b></th>
                                    </tr>
                                </thead>
                                <tbody id="projects-tbl">
                                    <?php
                                    if ($order_items) {

                                        // ====== INICIALIZAR SUMAS (alineado con JS/PHP de creación) ======
                                        $sumador_total           = 0.0;
                                        $sumador_valor_declarado = 0.0; // suma valores declarados
                                        $sumador_fixed_charge    = 0.0; // suma cargos fijos
                                        $max_fixed_charge        = 0.0; // mismo que arriba, para total
                                        $sumador_libras          = 0.0; // peso real total
                                        $sumador_volumetric      = 0.0; // volumétrico retirado (siempre 0)
                                        $base_packages           = 0.0; // base de paquetes en USD (peso*tarifa + precio personalizado)
                                        $total_impuesto          = 0.0;
                                        $total_seguro            = 0.0;
                                        $total_peso              = 0.0;
                                        $total_descuento         = 0.0;
                                        $total_impuesto_aduanero = 0.0;
                                        $total_valor_declarado   = 0.0;

                                        // Parámetros de la orden
                                        $meter                = (float) $row->volumetric_percentage;
                                        $price_lb             = (float) $row->value_weight;
                                        $tax_value            = (float) $row->tax_value;                  // %
                                        $tax_discount         = (float) $row->tax_discount;              // %
                                        $insurance_value      = (float) $row->tax_insurance_value;       // %
                                        $tariffs_value        = (float) $row->tax_custom_tariffis_value; // %
                                        $declared_value_tax   = (float) $row->declared_value;            // %
                                        $insured_value        = (float) $row->total_insured_value;       // base asegurada
                                        $reexpedicion_value   = (float) $row->total_reexp;

                                        if ($meter <= 0) {
                                            // Por seguridad, evitar división por cero
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

                                            // Per-item line total in USD (mirrors computeLineTotal in the JS):
                                            //   weight item -> weight * qty * rate ; custom item -> custom_price * qty
                                            if ($use_custom_item) {
                                                $line_total_item = $custom_price_item * $qty;
                                            } else {
                                                $line_total_item = $weight_item * $qty * $price_lb;
                                            }

                                            // Acumulados
                                            $sumador_libras          += $weight_item * $qty;
                                            $base_packages           += $line_total_item;
                                            $sumador_valor_declarado += (float)$row_order_item->order_item_declared_value * $qty;
                                            $sumador_fixed_charge    += (float)$row_order_item->order_item_fixed_value * $qty;
                                            $max_fixed_charge        += (float)$row_order_item->order_item_fixed_value * $qty;
                                    ?>

                                            <tr class="card-hover">
                                                <td><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                                                <td><?php echo $description_item; ?></td>
                                            </tr>
                                        <?php
                                        }

                                        // ====== POST-PROCESO DE SUMAS (igual que en calculateFinalTotal) ======

                                        $sumador_libras     = round($sumador_libras, 2);
                                        $sumador_volumetric = 0.0; // volumétrico retirado

                                        // Peso cobrable = peso real (volumétrico retirado)
                                        $calculate_weight = $sumador_libras;

                                        // Flete base (USD): suma de líneas por ítem
                                        // (peso*tarifa para ítems por peso + precio personalizado*qty)
                                        $sumador_total = round($base_packages, 2);

                                        // Impuesto (IVA o similar)
                                        if ($sumador_total > $core->min_cost_tax) {
                                            $total_impuesto = $sumador_total * ($tax_value / 100);
                                        }

                                        // Impuesto por valor declarado
                                        if ($sumador_valor_declarado > $core->min_cost_declared_tax) {
                                            $total_valor_declarado = $sumador_valor_declarado * ($declared_value_tax / 100);
                                        }

                                        // Descuento (porcentaje del base o monto fijo en USD)
                                        $discount_type = (isset($row->discount_type) && $row->discount_type === 'amount') ? 'amount' : 'percent';
                                        $total_descuento = ($discount_type === 'amount')
                                            ? $tax_discount
                                            : $sumador_total * ($tax_discount / 100);
                                        if ($tax_discount < 0 || $total_descuento > $sumador_total) {
                                            $total_descuento = 0;
                                        }

                                        // Peso total para arancel (real + volumétrico)
                                        $total_peso = $sumador_libras + $sumador_volumetric;

                                        // Seguro (costo)
                                        $total_seguro = $insured_value * ($insurance_value / 100);

                                        // Impuesto aduanero (% sobre peso total)
                                        $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

                                        // Total envío
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

                                        // Formateo para mostrar
                                        $sumador_total           = cdb_money_format($sumador_total);
                                        $total_envio             = cdb_money_format($total_envio);
                                        $total_seguro            = cdb_money_format($total_seguro);
                                        $total_impuesto_aduanero = cdb_money_format($total_impuesto_aduanero);
                                        $total_impuesto          = cdb_money_format($total_impuesto);
                                        $total_descuento         = cdb_money_format($total_descuento);
                                        $sumador_valor_declarado = cdb_money_format($sumador_valor_declarado);
                                        $sumador_fixed_charge    = cdb_money_format($sumador_fixed_charge);
                                        $total_valor_declarado   = cdb_money_format($total_valor_declarado);
                                        ?>
                                    <?php }  ?>
                                </tbody>
                                <tfoot>
                                    <tr class="card-hover">
                                        <td colspan="2">
                                            <b>Original Package Weight</b> : <?php echo htmlspecialchars((string) ($row->total_weight ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Totals Section -->
                <div class="totals">
                    <div class="total-box">
                        <label><?php echo 'Total Number of Items'; ?></label>
                        <div class="value"><?php echo $total_qty; ?></div>
                    </div>
                </div>

                <!-- Footer with Terms -->
                <div class="footer">
                    <table style="margin-top: 40px; border: none;">
                        <tr style="border: none;">
                            <td style="border: none; text-align: center; width: 50%; padding-bottom: 45px;">
                                <hr style="border: none; border-top: 1px solid #000;">
                                <small><?php echo $core->signing_company; ?></small>
                            </td>
                            <td style="border: none; text-align: center; width: 50%; padding-bottom: 45px;">
                                <hr style="border: none; border-top: 1px solid #000;">
                                <small><?php echo $core->signing_customer; ?></small>
                            </td>
                        </tr>
                    </table>

                    <div class="mt-5 text-center text-muted">
                        Developed by <b>iSolveAfrica</b><br>+233 (0) 59 144 7845<br>https://www.isolveafrica.com/
                    </div>
                </div>
            </div>

            <!-- Print Button -->
            <div class="print-button">
                <button class="btn btn-primary" onclick="window.print();" style="padding: 12px 30px; font-size: 16px;">
                    <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
                </button>
                <div class="print-info">Press Ctrl+P or click above to print</div>
            </div>
</body>
</html>
