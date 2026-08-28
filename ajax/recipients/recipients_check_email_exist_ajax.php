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

 
require_once("../../loader.php");
require_once("../../helpers/querys.php");

$db = new Conexion;

$response = []; // Inicializar la respuesta

// Verificar si se recibió un correo electrónico mediante POST
if (!empty($_POST['email'])) {
    // Sanitizar el correo electrónico recibido
    $email = cdp_sanitize($_POST['email']);

    // Verificar si el correo electrónico ya existe en la base de datos
    if (isset($_POST['original_email']) && $_POST['original_email'] === $email) {
        // Si el correo electrónico no ha cambiado, no es necesario verificar si ya existe en la base de datos
        $response['status'] = 'success';
    } elseif (cdp_recipientEmailExiste($email)) {
        // Si el correo electrónico ha cambiado y ya existe en la base de datos
        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax126']; // Mensaje de correo electrónico duplicado
    } else {
        // Si el correo electrónico ha cambiado y no existe en la base de datos
        $response['status'] = 'success'; // Correo electrónico válido
    }
} else {
    $response['status'] = 'error';
    $response['message'] = $lang['messagesform46']; // Mensaje de error si no se recibió un correo electrónico
}

// Devolver la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);
