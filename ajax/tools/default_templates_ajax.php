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
require_once("../../helpers/ajax_guard.php");
require_login();
$db = new Conexion;

$errors = array();



if (empty($_POST['id'])) {
    $errors['id'] = $lang['messageerrorform18'];
}

if (empty($_POST['id_template'])) {
    $errors['id_template'] = $lang['messageerrorform19'];
}

if (empty($_POST['active'])) {
    $errors['active'] = $lang['messageerrorform20'];
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

    $id_array = $_POST['id'];
    $id_template_array = $_POST['id_template'];
    $active_array = $_POST['active'];

    for ($i = 0; $i < count($id_array); $i++) {
        $data = array(
            'id' => trim($id_array[$i]),
            'id_template' => trim($id_template_array[$i]),
            'active' => trim($active_array[$i])
        );

        $insert = updateDefaultTemplateWhatsApp($data);

        if (!$insert) {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
            echo json_encode($response);
            exit; // Importante: salir para evitar que se siga ejecutando el código.
        }
    }

    $response['status'] = 'success';
    $response['message'] = $lang['messageerrorform17'];
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