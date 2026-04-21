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



    require_once("loader.php");

    $user = new User();
    $core = new Core();

    // Fetch active roles from database
    $db = new Conexion();
    $db->cdp_query("SELECT role_id, role_name FROM cdb_user_roles WHERE rol_active = 1");
    $roles = $db->cdp_registros();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) 
    {

        $permissions = $user->cdp_getUserPermissions();
        $userData = $user->cdp_getUserData();

        if (!$user->cdp_hasPermission('add_user')) {
            header("location: error403.php");
            exit;
        }

       
      include('views/tools/users/users_add.php');     
           

    } else{
        
        header("location: login.php");
        exit;       
    }
?>