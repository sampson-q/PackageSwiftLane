<?php

require_once("loader.php");
require_once("helpers/finance_switch.php");
cdp_financeGuardPage(); // financial module switched off: back to the dashboard

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('financial_sheet')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/financial_sheet/financial_sheet_consolidation.php');

} else {
    header("location: login.php");
    exit;
}
?>
