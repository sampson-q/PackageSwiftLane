<?php
// *************************************************************************
// * Consolidation box label — renders the print_label_ship.php design.    *
// * Normal (4x6") or Small (2x1") via ?size=.                             *
// *************************************************************************

require_once('helpers/querys.php');
require_once('views/print/partials/label_data_helpers.php');

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}
if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to('consolidate_list.php');
}

$row_order = $data['data'];
$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';
$L = cdp_labelModelFromConsolidate($db, $row_order, 'cdb_consolidate_detail');
$page_title = 'Consolidation Label - ' . $L['sys_tracking'];
include 'views/print/partials/label_ship_page.php';
