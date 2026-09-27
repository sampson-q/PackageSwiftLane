<?php
// *************************************************************************
// * Transactions dashboard — entry point                                 *
// *************************************************************************

require_once("loader.php");
require_once("helpers/finance_switch.php");
cdp_financeGuardPage(); // financial module switched off: back to the dashboard

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('view_transactions')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/transactions/transactions.php');

} else {
    header("location: login.php");
    exit;
}
?>
