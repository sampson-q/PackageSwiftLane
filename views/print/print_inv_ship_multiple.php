<?php
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
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['inv-shipping19'] . ' ' ?> - <?php echo count($order_list); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type='text/css' href='assets/custom_dependencies/print.css' rel='stylesheet' />
    <style>
        /* ── 80mm thermal receipt ─────────────────────────────────────────── */
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 10px; background: #f5f5f5; }
        .container { width: 80mm; margin: 0 auto 16px auto; background: white; padding: 4mm 3mm; }
        .receipt { page-break-after: always; }
        .receipt:last-child { page-break-after: auto; }
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
            .container { width: 80mm; max-width: 80mm; padding: 2mm; margin: 0 auto; }
            .print-button, .print-info { display: none; }
        }
    </style>
</head>
<body onload="window.print();">

    <!-- Print Button (top) -->
    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();" style="padding: 12px 30px; font-size: 16px;">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print all <?php echo count($order_list); ?> receipt(s)</div>
    </div>

<?php
foreach ($order_list as $order_no) {

    $data = cdp_getCourierPrintMultiple($order_no);

    // Skip silently if the shipment can't be resolved (deleted / bad id).
    if ($data['rowCount'] != 1) {
        continue;
    }

    $row = $data['data'];
    $order_id = $row->order_id;

    // Get order items
    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $order_id . "'");
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
    $package_tracking = cdp_getPackageTrackingLegacyAware($order_id);
?>
    <div class="receipt">
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

            <?php if ($package_tracking->tracking_number) : ?>
                <div class="barcode">
                    <img src='https://barcode.tec-it.com/barcode.ashx?data=<?php echo $package_tracking->tracking_number; ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50' alt='' />
                </div>
            <?php endif; ?>

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
                            <table class="table table-hover">
                                <thead class="bg-inverse text-white">
                                    <tr>
                                        <th><b><?php echo $lang['left214'] ?></b></th>      <!-- Cantidad -->
                                        <th><b><?php echo $lang['left213'] ?></b></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_qty = 0;
                                    if ($order_items) {
                                        foreach ($order_items as $row_order_item) {
                                            $total_qty += (int) $row_order_item->order_item_quantity;
                                    ?>
                                            <tr class="card-hover">
                                                <td><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                                                <td><?php echo $row_order_item->order_item_description; ?></td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                    ?>
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
    </div>
<?php
} // end foreach
?>

</body>
</html>
