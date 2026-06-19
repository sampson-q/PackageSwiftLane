<?php

    require_once("loader.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();
        $isAgency = isset($user->userlevel) && (int)$user->userlevel === 6;

        if (!$isAgency && !$user->cdp_hasPermission('warehouse_view')) {
            header("location: error403.php");
            exit;
        }

        include('views/courier/warehouse_view_print.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
