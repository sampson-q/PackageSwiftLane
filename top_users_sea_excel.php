<?php
    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) {
        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('top_users_air_print')) {
            header("location: error403.php");
            exit;
        }

        include('views/reports/shipments/report_users/top_users_air_excel.php');
           
    } else {
        header("location: login.php");
        exit;       
    }
?>