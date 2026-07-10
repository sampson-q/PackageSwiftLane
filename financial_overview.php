<?php
// *************************************************************************
// * Financial Overview — unifying finance hub, entry point               *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('view_financial_overview')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/financial_overview/financial_overview.php');

} else {
    header("location: login.php");
    exit;
}
?>
