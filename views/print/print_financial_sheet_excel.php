<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Financial Sheet — Excel export (one consolidation)        *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Mirrors the PDF columns. Long tracking numbers are forced to TEXT so  *
// * Excel does not turn them into scientific notation / lose precision.   *
// *                                                                       *
// *************************************************************************

require_once("../../loader.php");
require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;

if ($user->cdp_loginCheck() != true) {
    header("location: ../../login.php");
    exit;
}
if (!$user->cdp_hasPermission('view_shipment_by_agencies')) {
    header("location: ../../error403.php");
    exit;
}

$db  = new Conexion;
$cid = (int) ($_REQUEST['consolidate_id'] ?? 0);

$db->cdp_query("SELECT * FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $cid);
$db->cdp_execute();
$consol = $db->cdp_registro();

if (!$consol) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Consolidation not found.';
    exit;
}

$cNo  = ($consol->c_prefix ?? '') . ($consol->c_no ?? '');
$fname = preg_replace('/[^A-Za-z0-9_\-]/', '', $cNo);

// Same sender-sorted, S/N-numbered rows the PDF and the labels use.
$packages = cdp_getConsolidationFinancialRows($cid);

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=financial_sheet_" . $fname . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);

// mso-number-format:'\@' = Excel "Text" format -> the cell value is kept verbatim,
// so a 15+ digit tracking number is NOT rendered as 1.23457E+14 or rounded.
$txt = "mso-number-format:'\\@';";
$isDg = ((int) ($consol->is_dangerous_good ?? 0) === 1);
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<table border="1" cellspacing="0">
    <thead>
        <tr>
            <th colspan="6" style="font-size:14px;font-weight:bold;text-align:center;">
                Financial Sheet &mdash; Consolidation <?php echo htmlspecialchars($cNo); ?><?php echo $isDg ? ' (DANGEROUS GOODS)' : ''; ?>
            </th>
        </tr>
        <tr style="background:#3e5569;color:#ffffff;font-weight:bold;">
            <th>S/N</th>
            <th>Sender</th>
            <th>Swift Tracking</th>
            <th>Tracking</th>
            <th>Qty</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$packages): ?>
        <tr><td colspan="6">No packages in this consolidation.</td></tr>
    <?php else: foreach ($packages as $p):
        $senderName = 'N/A';
        if (!empty($p->fname) || !empty($p->lname) || !empty($p->locker)) {
            $fullName   = trim(($p->fname ?? '') . ' ' . ($p->lname ?? ''));
            $locker     = trim((string) ($p->locker ?? ''));
            $senderName = trim($fullName . ($locker !== '' ? ' (' . $locker . ')' : ''));
        }

        $db->cdp_query("SELECT order_item_quantity, order_item_description
                        FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
        $db->bind(':oid', (int) $p->oid);
        $db->cdp_execute();
        $items = $db->cdp_registros();

        $qtyCol = ''; $descCol = '';
        if ($items) {
            foreach ($items as $it) {
                $qtyCol  .= (int) $it->order_item_quantity . '<br>';
                $descCol .= htmlspecialchars((string) ($it->order_item_description ?? '')) . '<br>';
            }
        } else {
            $qtyCol = '-'; $descCol = '-';
        }

        $shipTrack = ($p->order_prefix ?? '') . ($p->order_no ?? '');
        $carrier   = $p->carrier_tracking ?: 'N/A';
    ?>
        <tr>
            <td><?php echo (int) $p->sn; ?></td>
            <td><?php echo htmlspecialchars($senderName); ?></td>
            <td style="<?php echo $txt; ?>"><?php echo htmlspecialchars($shipTrack); ?></td>
            <td style="<?php echo $txt; ?>"><?php echo htmlspecialchars($carrier); ?></td>
            <td><?php echo $qtyCol; ?></td>
            <td><?php echo $descCol; ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
