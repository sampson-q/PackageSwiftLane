<?php
    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) {
        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('view_top_users_air')) {
            header("location: error403.php");
            exit;
        }

        include('views/reports/shipments/report_users/top_users_sea.php');
           
    } else {
        header("location: login.php");
        exit;       
    }
?>