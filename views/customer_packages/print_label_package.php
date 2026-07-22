<?php
// *************************************************************************
// * Customer-package box label — print_label_ship.php design.            *
// * Normal (4x6") or Small (2x1") via ?size=. Small label is ideal for   *
// * gadgets (phones etc.) so the sticker does not cover the retail box.   *
// *************************************************************************

$userData = $user->cdp_getUserData();

require_once('helpers/querys.php');
require_once('views/print/partials/label_data_helpers.php');

if (isset($_GET['id'])) {
    $data = cdp_getCustomerPackagePrint($_GET['id']);
}
if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to('customer_packages_list.php');
}

$row = $data['data'];
$label_size = (isset($_GET['size']) && $_GET['size'] === 'small') ? 'small' : 'normal';
$L = cdp_labelModelFromCustomerPackage($db, $row);
$page_title = 'Package Label - ' . $L['sys_tracking'];
include 'views/print/partials/label_ship_page.php';
