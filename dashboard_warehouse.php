<?php
// ============================================================================
// Warehouse Control Panel — entry point. The warehouse side had operational
// pages (warehouse.php / warehouse_delivery.php) but no dashboard; this panel
// gives the WAREHOUSE permission module its own control panel.
// ============================================================================

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery'])) {
        header("location: error403.php");
        exit;
    }

    include('views/dashboard/dashboard_warehouse.php');

} else {
    header("location: login.php");
    exit;
}
?>
