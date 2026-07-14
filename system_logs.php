<?php
// *************************************************************************
// * System Logs — entry point.                                           *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    if (!$user->cdp_hasPermission('view_system_logs')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/system_logs/system_logs.php');

} else {
    header("location: login.php");
    exit;
}
?>
