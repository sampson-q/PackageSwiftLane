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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_recipients');
require_csrf();

require_once("../../helpers/querys.php");

$id = $_REQUEST['id'];

$errors = array();
$messages = array();

if (CDP_APP_MODE_DEMO === true) {
    // Si está en modo demo, mostrar un mensaje de error
    $errors['demo_mode'] = "This is a demo version, this action is not allowed.";
} else {
    if (empty($errors)) {
        // Verificar si la dirección está asociada a algún envío, paquete de cliente o consolidado
        $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'receiver_address_id', $id);
        $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'receiver_address_id', $id);
        $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'receiver_address_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {
            // Si la dirección está asociada a algún envío, paquete de cliente o consolidado, mostrar un mensaje de error
            $errors['constrains'] = $lang['validate_field_ajax133'];
        } else {
            // Si no hay restricciones, eliminar la dirección
            $delete = cdp_deleteRecipientAddress($id);
            if ($delete) {
                $messages[] = $lang['message_ajax_success_delete'];
            } else {
                // Si hay un error crítico al eliminar la dirección, mostrar un mensaje de error
                $errors['critical_error'] = $lang['message_ajax_error1'];
            }
        }
    }
}

// Comprobar si hay errores
if (!empty($errors)) {
    // Si hay errores, devolver un JSON con los errores y mostrar SweetAlert
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
} else {
    // Si no hay errores, devolver un JSON con los mensajes de éxito y mostrar SweetAlert
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}
