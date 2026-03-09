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

require_once("loader.php");

// Get parameters
$range = isset($_GET['range']) ? $_GET['range'] : '';
$page  = isset($_GET['page'])  ? $_GET['page']  : 'topshipper';

// Prepare date filter
$sWhere = '';
$displayRange = '';
if (!empty($range)) {
    $parts = explode(' - ', $range);
    if (count($parts) == 2) {
        $start = date('Y-m-d', strtotime(str_replace('/', '-', $parts[0])));
        $end   = date('Y-m-d', strtotime(str_replace('/', '-', $parts[1])));
        $sWhere = " AND DATE(a.order_date) BETWEEN '$start' AND '$end'";
        $displayRange = " [". date('d/m/Y', strtotime($start)) ." - ". date('d/m/Y', strtotime($end)) ."]";
    }
}

$db = new Conexion;
$core = new Core;

// 1) Fetch top user
if ($page === 'topshipper') {
    $sql = "SELECT
                b.id, b.lname, b.fname, b.avatar, b.phone, b.email, a.sender_id
                FROM cdb_customers_packages AS a
                INNER JOIN cdb_users AS b ON b.id = a.sender_id
                WHERE a.status_courier IN (27,15) $sWhere
                GROUP BY b.id
                ORDER BY COUNT(*) DESC
                LIMIT 1";
} else {
    // default to topshipper
    $sql = "SELECT
                b.id, b.lname, b.fname, b.avatar, b.username, b.email, b.locker, b.phone, b.created, b.lastlogin, a.sender_id
                FROM cdb_customers_packages AS a
                INNER JOIN cdb_users AS b ON b.id = a.sender_id
                WHERE a.status_invoice = 1 $sWhere
                ORDER BY a.total_order DESC
                LIMIT 1";
}
$db->cdp_query($sql);
$db->cdp_execute();
$top = $db->cdp_registro();

// 2) Fetch all shipments for that user
$innerSQL = "SELECT * FROM cdb_customers_packages AS cp
             WHERE cp.sender_id = {$top->sender_id} AND cp.status_courier IN (27,15)" 
             . $sWhere . "
             ORDER BY cp.order_date DESC";
$db->cdp_query($innerSQL);
$db->cdp_execute();
$shipments = $db->cdp_registros();

// Prepare HTML output
?><!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['report-text88'] . ' - Air Shipping' ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <link href="assets/custom_dependencies/print_report.css" rel="stylesheet">
    <style>* { font-family: Arial, sans-serif; }</style>
</head>
<body>
<div id="page-wrap" style="align-items: center; text-align: center;">
    <h2>
        <img src="assets/<?php echo htmlspecialchars($core->favicon ?: 'uploads/blank.png', ENT_QUOTES); ?>" width="40" height="40" style="vertical-align:middle; border-radius:50%;">
        <?php echo $core->site_name; ?><br>
        <?php echo $lang['report-text88'] . ' - Air Shipping' ?><br>
        <?php echo $lang['shipping-list'] . ' for ' . htmlspecialchars($top->fname . ' ' . $top->lname, ENT_QUOTES) . ' (' . ($page === 'topshipper' ? $lang['report-text89'] : $lang['report-text92']) . ')'; ?><br>
    </h2>

    <table>
        <thead>
            <!-- User info row (spans 7 columns) -->
            <tr>
                <th colspan="7" style="text-align:left;">
                    <?php echo $lang['report-text93'] . ': ' . ($displayRange ? $displayRange : 'All time'); ?>
                </th>
            </tr>

            <!-- Shipment columns header -->
            <tr>
                <th><?php echo $lang['ltracking']; ?></th>
                <th><?php echo $lang['left46']; ?></th>
                <th><?php echo $lang['ddate']; ?></th>
                <th><?php echo $lang['ldestination']; ?></th>
                <th><?php echo $lang['ship-all5']; ?></th>
                <th><?php echo $lang['global-3']; ?></th>
                
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shipments)): ?>
                <tr><td colspan="6" style="text-align:center;"><?php echo $lang['report-text91']; ?></td></tr>
            <?php else:
                foreach ($shipments as $row):
                    // get destination address
                    $order_track_full = $row->order_prefix . $row->order_no;
                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track = '$order_track_full'");
                    $db->cdp_execute();
                    $addr = $db->cdp_registro();
                    // invoice status label
                    if ($row->status_invoice == 1) { $text_status = $lang['invoice_paid']; }
                    elseif (in_array($row->status_invoice, [0,2])) { $text_status = $lang['invoice_pending']; }
                    elseif ($row->status_invoice == 3) { $text_status = $lang['verify_payment']; }
                    else { $text_status = $lang['invoice_unknown']; }
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($row->order_prefix.$row->order_no, ENT_QUOTES); ?></strong></td>
                <td><?php echo htmlspecialchars($row->tracking_purchase, ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars($row->order_date, ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars($addr->sender_country . ' - ' . $addr->sender_city, ENT_QUOTES); ?></td>
                <td><?php echo $core->currency . ' ' . cdb_money_format($row->total_order); ?></td>
                <td><?php echo $text_status; ?></td>
                
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <button class="button -dark center no-print" onClick="window.print();" style="margin-top:20px;">
        <?php echo $lang['report-text5']; ?> &nbsp;<i class="fa fa-print"></i>
    </button>
</div>
</body>
</html>
