<?php
// *************************************************************************
// * Consolidation receipt — print_inv_ship.php design.                    *
// *************************************************************************

require_once('helpers/querys.php');
require_once('views/print/partials/inv_data_helpers.php');

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}
if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to('consolidate_list.php');
}

$row = $data['data'];
$INV = cdp_invModelFromConsolidate($db, $row, 'cdb_consolidate_detail');
$page_title = ($lang['inv-shipping19'] ?? 'Invoice') . ' - ' . $INV['sys_tracking'];
include 'views/print/partials/inv_ship_page.php';
