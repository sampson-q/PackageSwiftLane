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



// Verifica si el archivo de configuración existe
if (!file_exists('config/config.php')) {
    header("location: install");
    exit;
}

// Incluye el archivo loader.php
require_once("loader.php");

// Crea instancias de las clases User y Core
$user = new User();
$core = new Core();

$permissions = $user->cdp_getUserPermissions();

// Verifica si estamos autenticados
if ($user->cdp_loginCheck() == true) {
    // Route to a dashboard by the role's dashboard_type flag (not a hardcoded
    // userlevel switch) so new department roles land somewhere sensible:
    //   'admin'  -> full admin dashboard   'client' -> customer dashboard
    //   'driver' -> driver dashboard       'roles'  -> generic permission-aware
    // A brand-new staff role defaults to 'roles' until configured otherwise.
    require_once("helpers/rbac.php");
    switch (cdp_dashboardType($_SESSION['userlevel'] ?? 0)) {
        case 'admin':
            include('views/dashboard/index.php');
            break;
        case 'client':
            include('views/dashboard/dashboard_client.php');
            break;
        case 'driver':
            include('views/dashboard/dashboard_driver.php');
            break;
        case 'roles':
        default:
            include('dashboard_roles.php');
            break;
    }
} else {
    // Si no estamos autenticados, redirige al inicio de sesión
    header("location: login.php");
    exit;
}
