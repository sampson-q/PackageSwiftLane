<?php
// ============================================================================
// My Bills — customer self-service payments (userlevel 1).
// ============================================================================

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('view_my_bills')) {
        header("location: error403.php");
        exit;
    }

    include('views/customer/my_bills.php');

} else {

    header("location: login.php");
    exit;
}
