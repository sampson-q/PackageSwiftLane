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


    // Incluye el archivo loader.php
    require_once("loader.php");

    // Crea instancias de las clases User y Core
    $user = new User();
    $core = new Core();

    $permissions = $user->cdp_getUserPermissions();
        $userData = $user->cdp_getUserData();

    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) 
    {

       
      include('views/tools/all_tools.php');     
           
 
    } else{
        
        header("location: login.php");
        exit;       
    }
?>