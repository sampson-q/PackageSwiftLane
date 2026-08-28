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
require_permission('edit_client_avatar'); // was login-only


$db = new Conexion;
$user = new User;
$core = new Core;
$errors = array();

$userData = $user->cdp_getUserData();


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
    
    // Verifica si hay errores en el formulario (si tienes esta lógica implementada)
    if (empty($errors)) {
        header('Content-type: application/json; charset=UTF-8');
        $response = array();

        // Ruta donde se guardarán las imágenes del avatar (ajusta según tu estructura)
        $upload_dir = realpath('../../assets/uploads/') . '/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Verifica si se envió un archivo
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
            // Obtiene la información del archivo
            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_type = $_FILES['avatar']['type'];

            // Verifica el tipo de archivo (ajusta esto según tus necesidades)
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file_type, $allowed_types)) {
                $response = array('success' => false, 'message' => 'File type not allowed. Upload a JPEG, PNG or GIF image.');
            } else {
                // Genera un nombre único para el archivo
                $user_id = $_POST['id']; // Ajusta según tu lógica de obtener el ID
                $current_avatar = $_POST['current_avatar']; // Ajusta según tu lógica de obtener el documento actual
                $file_name = $user_id . '_' . time() . '_' . $file_name;

                // Ruta completa donde se guardará el archivo
                $upload_path = $upload_dir . $file_name;

                // Mueve el archivo al directorio de carga
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Actualiza directamente la base de datos con la nueva ruta del avatar
                    $db->cdp_query('UPDATE cdb_users SET avatar = :avatar WHERE id = :id');

                    $db->bind(':avatar', 'uploads/' . $file_name);
                    $db->bind(':id', $user_id);
                    $db->cdp_execute();

                    $avatarUpdateData = array(
                        'user_id' =>  $user_id,
                        'update_by' => $userData->id,
                        'prev_document' =>  $current_avatar, // Updated to use $current_avatar
                        'remarks' =>  'Avatar updated',
                        'datetime' =>  cdp_sanitize(date("Y-m-d H:i:s")),
                    );

                    $record_history = cdp_insertAvatarUpdateHistory($avatarUpdateData);

                    $response = array('success' => true, 'message' => 'Avatar successfully updated.');
                } else {
                    // Error al mover el archivo
                    $response = array('success' => false, 'message' => 'Error uploading file. ' . error_get_last()['message']);
                }
            }
        } else {
            // No se envió ningún archivo
            $response = array('success' => false, 'message' => 'No file was selected. Click on the Image to select your avatar.');
        }

        echo json_encode($response);
    } else {
        // Lógica para manejar errores en el formulario si es necesario
        $response = array('success' => false, 'message' => 'There were errors on the form.');
        echo json_encode($response);
    }



    if (!empty($errors)) {
    ?>
        <div class="alert alert-danger" id="success-alert">
            <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
                <?php echo $lang['message_ajax_error2']; ?>
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
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <p><span class="icon-info-sign"></span>
                <?php
                foreach ($messages as $message) {
                    echo $message;
                }
                ?>
            </p>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

<?php
    }
}

?>