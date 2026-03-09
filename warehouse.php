<?php

    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();
        $isAgency = isset($user->userlevel) && (int)$user->userlevel === 6;

        // Agencia (userlevel 6) siempre puede ver el listado de envíos
        if (!$isAgency && !$user->cdp_hasPermission('warehouse_view')) {
            header("location: error403.php");
            exit;
        }

        include('views/courier/warehouse_view.php');

    } else {
        header("location: login.php");
        exit;       
    }
?>