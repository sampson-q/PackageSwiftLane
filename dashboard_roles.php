<?php
require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {
    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('view_dashboard')) {
        header("location: error403.php");
        exit;
    }

    include('views/dashboard/dashboard_roles.php');

} else {
    header("location: login.php");
    exit;
}
?>
