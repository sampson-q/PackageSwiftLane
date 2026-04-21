<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************


    // Clear OPcache so all PHP files (including AJAX endpoints) are always fresh
    if (function_exists('opcache_reset')) { opcache_reset(); }

    // Never cache this page — forces browser to always get fresh HTML/JS references
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) 
    {

        $permissions = $user->cdp_getUserPermissions();
        $userData = $user->cdp_getUserData();

        if (!$user->cdp_hasPermission('pickup_add')) {
            header("location: error403.php");
            exit;
        }

        $is_client = true;
        include('views/pickup/pickup_add_full.php');     
           

    } else{
        
        header("location: login.php");
        exit;       
    }
?>