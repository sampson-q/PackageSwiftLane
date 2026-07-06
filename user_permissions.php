<?php
// *************************************************************************
// * PackageSwiftLane — per-user permission overrides (root controller)   *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() !== true) {
    header("location: login.php");
    exit;
}

$user->cdp_getUserPermissions();

// Managing another user's permissions is an admin-grade action.
if (!$user->cdp_hasPermission('edit_user')) {
    header("location: error403.php");
    exit;
}

include('views/tools/users/user_permissions.php');
