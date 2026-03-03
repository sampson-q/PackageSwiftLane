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
require_permission('view_dashboard');

require_once("../../helpers/querys.php");
require_once("../notify_whatsapp/api_whatsapp_service.php");


$db = new Conexion;
session_start();
$errors = array();

if (empty(trim($_POST['template_whatsapp_description'])))
    $errors['username'] = 'La descripción es requerida';


if (empty($errors)) {
    $template_whatsapp = intval($_POST['template_whatsapp']);
    $template_whatsapp_body = trim($_POST['template_whatsapp_description']);

    $data = json_decode($_POST['checked_data']);


    foreach ($data as $key) {


        $package = getCustomerPackageForOrderNo($key);
        if ($package) {
            $sender = getSenderCourier($package->sender_id);
            $notification_result =  sendNotificationWhatsApp($sender, null, $template_whatsapp_body);

            if ($notification_result['success']) {
                $messages = $notification_result['message'];
            } else {
                $errors['notification_error'] = $notification_result['message'];
            }
        }
    }
}


if (!empty($errors)) {

    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
} else {

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}
