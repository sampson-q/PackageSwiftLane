<?php

    require_once("loader.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {       
        include('views/locker/locker_search.php');
    } else {
        header("location: login.php");
        exit;       
    }
?>