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

$range = isset($_GET['range']) ? $_GET['range'] : '';
$page  = isset($_GET['page'])  ? $_GET['page']  : 'topshipper';

$report_type = $page === "topshipper" ? $lang["report-text89"] : $lang["report-text92"];

header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=$report_type Report - (Sea Shipping) " . date('Y-m-d') . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);


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
                FROM cdb_add_order AS a
                INNER JOIN cdb_users AS b ON b.id = a.sender_id
                WHERE a.status_courier IN (27,15) $sWhere
                GROUP BY b.id
                ORDER BY COUNT(*) DESC
                LIMIT 1";
} else {
    // default to topshipper
    $sql = "SELECT
                b.id, b.lname, b.fname, b.avatar, b.username, b.email, b.locker, b.phone, b.created, b.lastlogin, a.sender_id
                FROM cdb_add_order AS a
                INNER JOIN cdb_users AS b ON b.id = a.sender_id
                WHERE a.status_invoice = 1 $sWhere
                ORDER BY a.total_order DESC
                LIMIT 1";
}
$db->cdp_query($sql);
$db->cdp_execute();
$top = $db->cdp_registro();

// 2) Fetch all shipments for that user
$innerSQL = "SELECT * FROM cdb_add_order AS cp
             WHERE cp.sender_id = {$top->sender_id} AND cp.status_courier IN (27,15)" 
             . $sWhere . "
             ORDER BY cp.order_date DESC";
$db->cdp_query($innerSQL);
$db->cdp_execute();
$shipments = $db->cdp_registros();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $lang["report-text88"] ?> – Sea Shipping</title>
    <style>body, table { font-family: Arial, sans-serif; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 4px; }</style>
</head>
<body>
    <h2>
        <img src="assets/<?= htmlspecialchars($core->favicon ?: 'uploads/blank.png', ENT_QUOTES) ?>"
             width="40" height="40" style="vertical-align:middle; border-radius:50%;">
        <?= htmlspecialchars($core->site_name, ENT_QUOTES) ?><br>
        <?= $lang["report-text88"] ?> – Sea Shipping<br>
        <?= $lang["shipping-list"] ?> for <?= htmlspecialchars($top->fname . " " . $top->lname, ENT_QUOTES) ?>
        (<?= $page === "topshipper" ? $lang["report-text89"] : $lang["report-text92"] ?>)
    </h2>

    <table>
        <thead>
            <tr><th colspan="6" style="text-align:left;">
                <?= $lang["report-text93"] ?>: <?= $displayRange ?: 'All time' ?>
            </th></tr>
            <tr>
                <th><?= $lang["ltracking"] ?></th>
                <th><?= $lang["left46"] ?></th>
                <th><?= $lang["ddate"] ?></th>
                <th><?= $lang["ldestination"] ?></th>
                <th><?= $lang["ship-all5"] ?></th>
                <th><?= $lang["global-3"] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shipments)): ?>
                <tr><td colspan="6" style="text-align:center;"><?= $lang["report-text91"] ?></td></tr>
            <?php else: ?>
                <?php foreach ($shipments as $row): 
                    // fetch address and status label as you already do…
                    $order_track_full = $row->order_prefix . $row->order_no;
                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track = '$order_track_full'");
                    $db->cdp_execute();
                    $addr = $db->cdp_registro();

                    if ($row->status_invoice == 1)            $text_status = $lang["invoice_paid"];
                    elseif (in_array($row->status_invoice, [0,2])) $text_status = $lang["invoice_pending"];
                    elseif ($row->status_invoice == 3)        $text_status = $lang["verify_payment"];
                    else                                       $text_status = $lang["invoice_unknown"];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($order_track_full, ENT_QUOTES) ?></strong></td>
                    <td><?= htmlspecialchars($row->tracking_purchase, ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($row->order_date, ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($addr->sender_country . ' – ' . $addr->sender_city, ENT_QUOTES) ?></td>
                    <td><?= $core->currency . ' ' . cdb_money_format($row->total_order) ?></td>
                    <td><?= $text_status ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>