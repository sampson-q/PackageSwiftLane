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
// ... ask if we are logged in here:
if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();
    $isAgency = isset($user->userlevel) && (int)$user->userlevel === 6;

    // Agencia (userlevel 6) siempre puede ver la lista de paquetes consolidados
    if (!$isAgency && !$user->cdp_hasPermission('view_consolidate_package_list')) {
        header("location: error403.php");
        exit;
    }


    include('views/consolidate_packages/consolidate_list.php');
} else {

    header("location: login.php");
    exit;
}
