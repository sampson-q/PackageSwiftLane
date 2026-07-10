<?php
// *************************************************************************
// * Hubtel payment-method edit — entry point                            *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    include('views/tools/payment_gateways/setting_methods_hubtel_edit.php');

} else {
    header("location: login.php");
    exit;
}
?>
