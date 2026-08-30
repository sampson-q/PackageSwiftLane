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

        // Audit: record who took this document out of the system.
        require_once(__DIR__ . "/helpers/activity_log.php");
        cdp_activityLogDocument();

        include('views/reports/shipments/report_users/top_users_air_print.php');
           
    } else {
        header("location: login.php");
        exit;       
    }
?>