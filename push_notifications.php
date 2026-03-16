<?php
    require_once("loader.php");

    $user = new User();
    $core = new Core();
    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('push_notifications')) {
            header("location: error403.php");
            exit;
        }
       
        include('views/tools/push_notifications.php');
           
    } else{
        header("location: login.php");
        exit;       
    }
?>