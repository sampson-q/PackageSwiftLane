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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['api_ws_url']))
    $errors['api_ws_url'] = 'Ingrese URL de la API WhatsApp';

if (empty($_POST['api_ws_token']))
    $errors['api_ws_token'] = 'Ingrese TOKEN de la API WhatsApp';

if (!isset($_POST['active_whatsapp'])) {
    $active_whatsapp = 0;
} else {
    $active_whatsapp = $_POST['active_whatsapp'];
}

if (CDP_APP_MODE_DEMO === true) {
?>

    <div class="alert alert-warning" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Error! </span> There was an error processing the request
        <ul class="error">

            <li>
                <i class="icon-double-angle-right"></i>
                This is a demo version, this action is not allowed. Contact iSolveAfrica Ltd. to enable the full version of Swiftlane.

            </li>


        </ul>
        </p> 
    </div>
    <?php
} else {
     
    if (empty($errors)) {

    header('Content-type: application/json; charset=UTF-8');
    
    $response = array();

    $data = array(
        'api_ws_url' => trim($_POST['api_ws_url']),
        'api_ws_token' => trim($_POST['api_ws_token']),
        'active_whatsapp' => $active_whatsapp

    );

    $insert = updateApiWhatsConfig($data);

    if ($insert) {
        $response['status'] = 'success';
        $response['message'] = $lang['message_ajax_success_updated'];
    } else {
        $response['status'] = 'error';
        $response['message'] = $lang['message_ajax_error1'];
    }


    echo json_encode($response);
}


if (!empty($errors)) {
 
?>
    <div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Error! </span> There was an error processing the request
        <ul class="error">
            <?php
            foreach ($errors as $error) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $error;

                    ?>

                </li>
            <?php

            }
            ?>


        </ul>
        </p>
    </div>



<?php
}

if (isset($messages)) {

?>
    <div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>
    </div>

<?php
    }
}
?>