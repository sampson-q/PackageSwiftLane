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

 
require_once("../../loader.php");
require_once("../../helpers/querys.php");

$db = new Conexion;


$response = []; // Inicializar la respuesta

// Verificar si se recibió un correo electrónico mediante POST
if (!empty($_POST['email'])) {
    // Sanitizar el correo electrónico recibido
    $email = cdp_sanitize($_POST['email']);

    // Verificar si el correo electrónico ya existe en la base de datos
    if (cdp_recipientEmailExiste($email)) {
        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax126']; // Mensaje de correo electrónico duplicado
    } else {
        $response['status'] = 'success'; // Correo electrónico válido
    }
} else {
    $response['status'] = 'error';
    $response['message'] = $lang['messagesform46']; // Mensaje de error si no se recibió un correo electrónico
}

// Devolver la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);

