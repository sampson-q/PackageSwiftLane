<?php
// *************************************************************************
// * Bulk customer-package box labels — print_label_ship.php design.       *
// * Normal (4x6") or Small (2x1") via ?size=.                             *
// *************************************************************************

$userData = $user->cdp_getUserData();

require_once('helpers/querys.php');
require_once('views/print/partials/label_data_helpers.php');

if (!isset($_GET['data'])) {
    cdp_redirect_to('customer_packages_list.php');
}
$keys = json_decode($_GET['data']);
if (!is_array($keys) || count($keys) === 0) {
    cdp_redirect_to('customer_packages_list.php');
}

$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';
$page_title = 'Package Labels';
include 'views/print/partials/label_ship_multiple_head.php';

foreach ($keys as $key) {
    $data_key = cdp_getPackagePrintMultiple($key);
    if (!$data_key || $data_key['rowCount'] != 1) {
        continue;
    }
    $row = $data_key['data'];
    $L = cdp_labelModelFromCustomerPackage($db, $row);
    include 'views/print/partials/label_ship_body.php';
}

include 'views/print/partials/label_ship_multiple_foot.php';
