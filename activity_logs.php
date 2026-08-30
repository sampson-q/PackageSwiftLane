<?php
// *************************************************************************
// * Activity Logs — entry point.                                          *
// *                                                                       *
// * The audit trail written by helpers/activity_log.php: who did what, to  *
// * which record, when, from where, and what changed.                      *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('view_activity_logs')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/activity_logs/activity_logs.php');

} else {
    header("location: login.php");
    exit;
}
?>
