<?php
// *************************************************************************
// * Pickup Aging — entry point (admin / super-admin only)                *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('view_pickup_aging')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/pickup_aging/pickup_aging.php');

} else {
    header("location: login.php");
    exit;
}
