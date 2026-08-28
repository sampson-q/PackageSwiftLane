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

