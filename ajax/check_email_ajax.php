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

 
require_once("../loader.php");
require_once("../helpers/querys.php");


$db = new Conexion;

$response = []; // Inicializar la respuesta

// Verificar si se recibió un número de seguimiento (email) mediante POST
if (!empty($_POST['email'])) {
    // Sanitizar el número de seguimiento recibido
    $email = cdp_sanitize($_POST['email']);

    // Realizar la consulta en la base de datos para verificar si el número de seguimiento ya está en uso
    $existingEmail = cdp_userwebEmailExiste($email);

    // Verificar si se encontró un registro con el número de seguimiento
    if ($existingEmail) {
        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax126'];
    } else {
        $response['status'] = 'success';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = $lang['messagesform46'];
}

// Devolver la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);

