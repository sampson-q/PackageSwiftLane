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

$errors = array();

if (empty($_POST['meta_description'])) {
    $errors['meta_description'] = $lang['metaseo20'];
}

if (empty($_POST['og_title'])) {
    $errors['og_title'] = $lang['metaseo21'];
}

if (empty($_POST['og_description'])) {
    $errors['og_description'] = $lang['metaseo22'];
}

if (empty($_POST['og_type'])) {
    $errors['og_type'] = $lang['metaseo23'];
}

if (empty($_POST['og_url'])) {
    $errors['og_url'] = $lang['metaseo24'];
}

if (empty($_POST['og_image'])) {
    $errors['og_image'] = $lang['metaseo25'];
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
            'meta_description' => cdp_sanitize($_POST['meta_description']),
            'meta_keywords' => cdp_sanitize($_POST['meta_keywords']),
            'og_title' => cdp_sanitize($_POST['og_title']),
            'og_description' => cdp_sanitize($_POST['og_description']),
            'og_type' => cdp_sanitize($_POST['og_type']),
            'og_url' => cdp_sanitize($_POST['og_url']),
            'og_image' => cdp_sanitize($_POST['og_image'])
        );

        $insert = cdp_updateSeoConfig($data); // Asegúrate de tener una función que actualice esta configuración.


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