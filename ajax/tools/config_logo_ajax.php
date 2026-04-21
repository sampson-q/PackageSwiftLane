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
require_permission('view_tools');
require_csrf();

require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();



if (empty($_POST['thumb_w']))

    $errors['thumb_w'] = $lang['validate_field_ajax60'];

if (empty($_POST['thumb_h']))

    $errors['thumb_h'] = $lang['validate_field_ajax61'];

if (empty($_POST['thumb_web']))

    $errors['thumb_web'] = $lang['validate_field_ajax60'];

if (empty($_POST['thumb_hweb']))

    $errors['thumb_hweb'] = $lang['validate_field_ajax61'];





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
    


// Verifica si hay errores en el formulario
if (empty($errors)) {
    header('Content-type: application/json; charset=UTF-8');
    $response = array();

    $data = array(
        'thumb_w' => intval($_POST['thumb_w']),
        'thumb_h' => intval($_POST['thumb_h']),
        'thumb_web' => intval($_POST['thumb_web']),
        'thumb_hweb' => intval($_POST['thumb_hweb'])
    );

    // Ruta donde se guardarán las imágenes (ajusta según tu estructura)
    $upload_dir = '../../assets/uploads/';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Función para manejar la carga de archivos
    function handleFileUpload($fileInputName, $uploadDir)
    {
        global $response;

        // Verifica si se envió un archivo
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
            // Obtiene la información del archivo
            $file_name = $_FILES[$fileInputName]['name'];
            $file_tmp = $_FILES[$fileInputName]['tmp_name'];
            $file_type = $_FILES[$fileInputName]['type'];

            // Verifica el tipo de archivo (ajusta esto según tus necesidades)
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file_type, $allowed_types)) {
                $response['message'] = 'File type not allowed. Upload a JPEG, PNG, or GIF image.';
                return false;
            }

            // Genera un nombre único para el archivo
            $user_id = intval($_POST['id']);
            $file_name = $user_id . '_' . time() . '_' . $file_name;

            // Ruta completa donde se guardará el archivo
            $upload_path = $uploadDir . $file_name;

            // Mueve el archivo al directorio de carga
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Devuelve la ruta relativa del archivo
                return 'uploads/' . $file_name;
            } else {
                // Error al mover el archivo
                $error = error_get_last();
                if ($error && $error['type'] === E_WARNING) {
                    $response['message'] = 'Error uploading file. ' . $error['message'];
                } else {
                    $response['message'] = 'Error uploading file.';
                }
                return false;
            }
        }

        return null;
    }

    // Verifica y maneja la carga de logo
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['size'] > 0) {
        $logo_path = handleFileUpload('logo', $upload_dir);
        if ($logo_path !== false) {
            $data['logo'] = $logo_path;
        }
    }

    // Verifica y maneja la carga de logo_web
    if (!empty($_FILES['logo_web']['name']) && $_FILES['logo_web']['size'] > 0) {
        $logo_web_path = handleFileUpload('logo_web', $upload_dir);
        if ($logo_web_path !== false) {
            $data['logo_web'] = $logo_web_path;
        }
    }

    // Verifica y maneja la carga de favicon
    if (!empty($_FILES['favicon']['name']) && $_FILES['favicon']['size'] > 0) {
        $favicon_path = handleFileUpload('favicon', $upload_dir);
        if ($favicon_path !== false) {
            $data['favicon'] = $favicon_path;
        }
    }

    // Actualiza directamente la base de datos con la nueva ruta y los valores numéricos
    $db->cdp_query('UPDATE cdb_settings SET thumb_w = :thumb_w, thumb_h = :thumb_h, thumb_web = :thumb_web, thumb_hweb = :thumb_hweb'
        . (isset($data['logo']) ? ', logo = :logo' : '')
        . (isset($data['logo_web']) ? ', logo_web = :logo_web' : '')
        . (isset($data['favicon']) ? ', favicon = :favicon' : '')
        . ' WHERE id = :id');

    $db->bind(':id', intval($_POST['id']));
    $db->bind(':thumb_w', $data['thumb_w']);
    $db->bind(':thumb_h', $data['thumb_h']);
    $db->bind(':thumb_web', $data['thumb_web']);
    $db->bind(':thumb_hweb', $data['thumb_hweb']);

    // Solo bindea los valores de archivo si están definidos
    if (isset($data['logo'])) {
        $db->bind(':logo', $data['logo']);
    }
    if (isset($data['logo_web'])) {
        $db->bind(':logo_web', $data['logo_web']);
    }
    if (isset($data['favicon'])) {
        $db->bind(':favicon', $data['favicon']);
    }


    if ($db->cdp_execute()) {
        $response['success'] = true;
        $response['message'] = 'Settings successfully updated.';
    } else {
        $response['message'] = 'Error updating settings.';
    }

    echo json_encode($response);

} else {
    $response['message'] = 'There were errors on the form.';
    echo json_encode($response);
}

    if (!empty($errors)) {
    ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <p><span class="icon-info-sign"></span>
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
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