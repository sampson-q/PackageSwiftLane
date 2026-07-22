<?php
// *************************************************************************
// * Payment voucher / charge receipt — shares the print_inv_ship.php look *
// * (110mm thermal, brand header, panels, credits) but keeps the amount,  *
// * since this is an accounts-receivable billing document.                *
// *************************************************************************

require_once('helpers/querys.php');

if (isset($_GET['id'])) {
    $data = cdp_getChargePrint($_GET['id']);
}
if (!isset($_GET['id']) || !isset($data['data']) || !$data['data']) {
    cdp_redirect_to('charges_list.php');
}

$row = $data['data'];

$db->cdp_query("SELECT * FROM cdb_add_order WHERE order_id='" . intval($row->order_id) . "'");
$row_order = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row_order->sender_id ?? 0) . "'");
$sender_data = $db->cdp_registro();

$order_track = $row_order ? ($row_order->order_prefix . $row_order->order_no) : '';
$amount = cdb_money_format($row->total);
$page_title = 'Charge - #' . $row->id_charge;
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title><?php echo h($page_title); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type="text/css" href="assets/custom_dependencies/print.css" rel="stylesheet" />
    <?php include 'views/print/partials/inv_ship_styles.php'; ?>
    <style>.signatures3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 3mm; align-items: end; }</style>
</head>
<body>
    <div class="label-page">
        <div class="topbar">
            <div class="brand-wrap">
                <div class="logo">
                    <?php echo ($core->logo) ? '<img src="assets/uploads/SWIFT LOGO PNG-04.png" alt="' . h($core->site_name) . '"/>' : '<h3>' . h($core->site_name) . '</h3>'; ?>
                </div>
                <div class="brand-text" style="color:#000 !important;">
                    <p class="brand-name"><?php echo h($core->site_name); ?></p>
                    <p class="brand-lines">
                        <strong><?php echo $lang['inv-shipping2']; ?>:</strong> <?php echo h($core->c_phone); ?><br>
                        <strong><?php echo $lang['inv-shipping3']; ?>:</strong> <?php echo h($core->site_email); ?><br>
                        <strong><?php echo $lang['inv-shipping4']; ?>:</strong> <?php echo h($core->c_address . ' - ' . $core->c_country . '-' . $core->c_city); ?>
                    </p>
                </div>
            </div>
            <div class="barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($row->id_charge); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Receipt Of Payment</div>
            <div class="kv"><div class="k">Charge #:</div><div class="v"><strong><?php echo h($row->id_charge); ?></strong></div></div>
            <div class="kv"><div class="k">Date:</div><div class="v"><?php echo h($row->charge_date); ?></div></div>
            <div class="kv"><div class="k">Received From:</div><div class="v"><strong><?php echo h(trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''))); ?></strong></div></div>
            <div class="kv"><div class="k">In Concept Of:</div><div class="v">Payment to shipping <strong><?php echo h($order_track); ?></strong></div></div>
            <?php if (!empty($row->note)) : ?>
                <div class="kv"><div class="k">Note:</div><div class="v"><?php echo h($row->note); ?></div></div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <div class="total-box">
                <label>The Sum Of</label>
                <div class="value"><?php echo h($amount); ?></div>
            </div>

            <div class="items-panel" style="border:none;padding:1mm 0 0;">
                <p align="justify" style="font-size:13px;margin:0 0 2mm;">This voucher is valid without amendments or erasures and must have the signature and stamp of the authorized person.</p>
            </div>

            <div class="signatures3">
                <div class="signature"><div class="line">Made By</div></div>
                <div class="signature"><div class="line">Received</div></div>
                <div class="signature"><div class="line">Authorized</div></div>
            </div>

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
            <i class="fa fa-print"></i> Print Receipt Of Payment
        </button>
        <div class="print-info">Press Ctrl+P or click above to print</div>
    </div>
</body>
</html>
