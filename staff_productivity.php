<?php
// *************************************************************************
// * Staff Productivity — entry point.                                     *
// *                                                                       *
// * How long each staff member was actually working in the system, how    *
// * many packages they registered and when. Admin and Super Admin only.   *
// *************************************************************************

require_once("loader.php");
require_once("helpers/staff_activity.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    // cdp_spCanView() requires BOTH the permission and the role's is_admin /
    // is_superadmin flag — a page that reports on Employees must not be
    // openable by an Employee, even if the permission is granted by mistake.
    if (!cdp_spCanView($user)) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/staff_productivity/staff_productivity.php');

} else {
    header("location: login.php");
    exit;
}
?>
