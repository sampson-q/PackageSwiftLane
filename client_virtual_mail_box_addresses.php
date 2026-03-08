<?php
    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) {
        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('client_virtual_mail_box_addresses')) {
            header("location: error403.php");
            exit;
        }

        include('views/tools/offices/client_virtual_mail_box_addresses.php');
           
    } else {
        header("location: login.php");
        exit;       
    }
?>