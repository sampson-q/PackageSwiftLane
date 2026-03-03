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

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

require_once("../../../helpers/querys.php");

$errors = [];
$response = [];

$roleName = $_POST['role_name'] ?? null;
$description = $_POST['description'] ?? null;
$permissions = $_POST['permissions'] ?? [];

// Validar datos
if (empty($roleName)) {
    $errors['role_name'] = $lang['rolesp16'];
}

if (empty($description)) {
    $errors['description'] = $lang['rolesp17'];
}

// Verificar existencia del rol
if (cdp_rolesExistsjmbj12($roleName)) {
    $response['status'] = 'error';
    $response['message'] = $lang['rolesp18'];
}

// Si hay errores, retornar respuesta
if (!empty($errors)) {
    $response['status'] = 'error';
    $response['message'] = $lang['message_ajax_error2'];
    $response['errors'] = $errors;
    header('Content-type: application/json; charset=UTF-8');
    echo json_encode($response);
    exit;
}

// Crear nuevo rol y asignar permisos si no hay errores previos
if (!isset($response['status'])) {
    $db = new Conexion;

    // Crear nuevo rol
    $db->cdp_query('INSERT INTO cdb_user_roles (role_name, description, rol_active, created_at) 
                     VALUES (:role_name, :description, :rol_active, NOW())');
    $db->bind(':role_name', $roleName);
    $db->bind(':description', $description);
    $db->bind(':rol_active', 1);

    if (!$db->cdp_execute()) {
        echo json_encode(['status' => 'error', 'message' => $lang['message_ajax_error1']]);
        exit;
    }

    // Obtener ID del rol recién creado
    $roleId = $db->dbh->lastInsertId();

    // Insertar permisos asociados
    foreach ($permissions as $permissionId) {
        $db->cdp_query('INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at) 
                        VALUES (:role_id, :module_action_id, 1, NOW())');
        $db->bind(':role_id', $roleId);
        $db->bind(':module_action_id', $permissionId);
        if (!$db->cdp_execute()) {
            echo json_encode(['status' => 'error', 'message' => $lang['asingmodule6'] . ' ' . $permissionId]);
            exit;
        }
    }

    // Respuesta exitosa
    $response['status'] = 'success';
    $response['message'] = $lang['message_ajax_success_add'];
    header('Content-type: application/json; charset=UTF-8');
    echo json_encode($response);
    exit;
}

// Mostrar errores
if (!empty($errors)) {
    ?>
    <div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_error2']; ?>
        <ul class="error">
            <?php foreach ($errors as $error) { ?>
                <li>
                    <i class="icon-double-angle-right"></i> <?php echo $error; ?>
                </li>
            <?php } ?>
        </ul>
        </p>
    </div>
    <?php
}

// Mostrar mensajes
if (isset($messages)) {
    ?>
    <div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            <?php foreach ($messages as $message) {
                echo $message;
            } ?>
        </p>
    </div>
    <?php
}
?>
