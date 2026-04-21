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
        $userData = $user->cdp_getUserData();

// Verifica si estamos autenticados
if ($user->cdp_loginCheck() == true) {
    // Agencia (6) siempre va al dashboard por roles; no debe ver el de administración
    if (isset($_SESSION['userlevel']) && (int)$_SESSION['userlevel'] === 6) {
        include('dashboard_roles.php');
        exit;
    }
    // Determina la vista a cargar según el nivel de usuario
    switch ($_SESSION['userlevel']) {
        case 9:
        case 2:
        case 4:
            // Super Admin, Administrator, Employee: dashboard de administración
            include('views/dashboard/index.php');
            break;
        case 1:
            include('views/dashboard/dashboard_client.php');
            break;
        case 3:
            include('views/dashboard/dashboard_driver.php');
            break;
        case 6:
            include('dashboard_roles.php');
            break;
        default:
            include('dashboard_roles.php');
            break;
    }
} else {
    // Si no estamos autenticados, redirige al inicio de sesión
    header("location: login.php");
    exit;
}
