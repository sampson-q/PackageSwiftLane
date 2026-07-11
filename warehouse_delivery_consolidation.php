<?php
// *************************************************************************
// * Warehouse Delivery — one consolidation (own page)                     *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('view_warehouse_delivery')) {
        header("location: error403.php");
        exit;
    }

    include('views/courier/warehouse_delivery_consolidation.php');

} else {
    header("location: login.php");
    exit;
}
?>
