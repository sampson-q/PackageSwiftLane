<?php
// *************************************************************************
// * PackageSwiftLane — Departments (Access Control) root controller       *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() !== true) {
    header("location: login.php");
    exit;
}

$user->cdp_getUserPermissions();

if (!$user->cdp_hasPermission('view_role_assignment')) {
    header("location: error403.php");
    exit;
}

include('views/tools/permissions/departments.php');
