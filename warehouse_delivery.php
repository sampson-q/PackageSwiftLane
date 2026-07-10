<?php
// *************************************************************************
// * Warehouse Delivery — deliver cleared-for-delivery packages           *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('deliver_shipment')) {
        header("location: error403.php");
        exit;
    }

    include('views/courier/warehouse_delivery.php');

} else {
    header("location: login.php");
    exit;
}
?>
