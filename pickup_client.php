<?php

    require_once("loader.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {
        include('views/pickup_client.php');
    } else {
        header("location: login.php");
        exit;       
    }
?>