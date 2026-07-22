<?php
// *************************************************************************
// * Bulk consolidation-package box labels — print_label_ship.php design.  *
// * Normal (4x6") or Small (2x1") via ?size=.                             *
// *************************************************************************

require_once('helpers/querys.php');
require_once('views/print/partials/label_data_helpers.php');

if (!isset($_GET['data'])) {
    cdp_redirect_to('consolidate_package_list.php');
}
$keys = json_decode($_GET['data']);
if (!is_array($keys) || count($keys) === 0) {
    cdp_redirect_to('consolidate_package_list.php');
}

$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';
$page_title = 'Consolidation Labels';
include 'views/print/partials/label_ship_multiple_head.php';

foreach ($keys as $key) {
    $data_key = cdp_getConsolidatePrintMultiplePackage($key);
    if (!$data_key || $data_key['rowCount'] != 1) {
        continue;
    }
    $row_order = $data_key['data'];
    $L = cdp_labelModelFromConsolidate($db, $row_order, 'cdb_consolidate_packages_detail');
    include 'views/print/partials/label_ship_body.php';
}

include 'views/print/partials/label_ship_multiple_foot.php';
