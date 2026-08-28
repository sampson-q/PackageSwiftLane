<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



    require_once("loader.php");

    $user = new User();
    $core = new Core();

    // Fetch active roles for role selector
    $db = new Conexion();
    $db->cdp_query("SELECT role_id, role_name FROM cdb_user_roles WHERE rol_active = 1");
    $roles = $db->cdp_registros();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) 
    {

        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('edit_user')) {
            header("location: error403.php");
            exit;
        }

       
      include('views/tools/users/users_edit.php');     
           

    } else{
        
        header("location: login.php");
        exit;       
    }
?>