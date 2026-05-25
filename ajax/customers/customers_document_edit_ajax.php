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
                This is a demo version, this action is not allowed, <a class="btn waves-effect waves-light btn-xs btn-success" href="https://codecanyon.net/item/courier-deprixa-pro-integrated-web-system-v32/15216982" target="_blank">Buy DEPRIXA PRO</a> the full version and enjoy all the functions...

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

        // Ruta donde se guardarán las imágenes del document (ajusta según tu estructura)
        $upload_dir = realpath('../../assets/uploads/documents') . '/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Verifica si se envió un archivo
        if (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
            // Obtiene la información del archivo
            $file_name = $_FILES['document']['name'];
            $file_tmp = $_FILES['document']['tmp_name'];
            $file_type = $_FILES['document']['type'];

            // Verifica el tipo de archivo (ajusta esto según tus necesidades)
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file_type, $allowed_types)) {
                $response = array('success' => false, 'message' => 'File type not allowed. Upload a JPEG, PNG or GIF image.');
            } else {
                // Genera un nombre único para el archivo
                $user_id = $_POST['id']; // Ajusta según tu lógica de obtener el ID
                $current_document = $_POST['current_document'];
                $file_name = $user_id . '_' . time() . '_' . $file_name;

                // Ruta completa donde se guardará el archivo
                $upload_path = $upload_dir . $file_name;

                // Mueve el archivo al directorio de carga
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Actualiza directamente la base de datos con la nueva ruta del document
                    $db->cdp_query('UPDATE cdb_users SET document_photo = :document_photo WHERE id = :id');

                    $db->bind(':document_photo', 'uploads/documents/' . $file_name);
                    $db->bind(':id', $user_id);
                    $db->cdp_execute();

                    $documentUpdateData = array(
                        'user_id' =>  $user_id,
                        'update_by' => $userData->id,
                        'prev_document' =>  $current_document,
                        'remarks' =>  'Document updated',
                        'datetime' =>  cdp_sanitize(date("Y-m-d H:i:s")),
                    );

                    $record_history = cdp_insertDocumentUpdateHistory($documentUpdateData);

                    // if ($record_history) {
                    //     $response = array('success' => true, 'message' => 'Document successfully updated.');
                    // }
                    
                    $response = array('success' => true, 'message' => 'Document successfully updated.');

                } else {
                    // Error al mover el archivo
                    $response = array('success' => false, 'message' => 'Error uploading file. ' . error_get_last()['message']);
                }
            }
        } else {
            // No se envió ningún archivo
            $response = array('success' => false, 'message' => 'No file was selected. Click on the Image to select your document.');
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