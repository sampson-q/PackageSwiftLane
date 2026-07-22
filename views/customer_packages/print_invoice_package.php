<?php
// *************************************************************************
// * Customer-package receipt — print_inv_ship.php design.                 *
// *************************************************************************

require_once('helpers/querys.php');
require_once('views/print/partials/inv_data_helpers.php');

if (isset($_GET['id'])) {
    $data = cdp_getCustomerPackagePrint($_GET['id']);
}
if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to('customer_packages_list.php');
}

$row = $data['data'];
$INV = cdp_invModelFromCustomerPackage($db, $row);
$page_title = ($lang['inv-shipping19'] ?? 'Invoice') . ' - ' . $INV['sys_tracking'];
include 'views/print/partials/inv_ship_page.php';
