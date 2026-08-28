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



require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

require_once("../../../helpers/querys.php");

$errors = array();

if (empty($_POST['mod_style']))

    $errors['mod_style'] = $lang['validate_field_ajax106'];


if (empty($_POST['detail']))

    $errors['detail'] =  $lang['validate_field_ajax108'];

if (empty($_POST['color']))

    $errors['color'] =  $lang['validate_field_ajax109'];

if (empty($_POST['status_type']))
    $errors['status_type'] = 'Please provide a status type.';


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


    $response = array();


    if (cdp_colorStatusCourierExists($_POST['mod_style'], $_POST['id'])) {

        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax107'];
    }


    if (cdp_colorStatusCourierExists($_POST['color'], $_POST['id'])) {

        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax110'];
    }

    if (!isset($response['status'])) {
        $data = array(
            'mod_style' => cdp_sanitize($_POST['mod_style']),
            'detail' => cdp_sanitize($_POST['detail']),
            'color' => cdp_sanitize($_POST['color']),
            'id' => cdp_sanitize($_POST['id']),
            'status_type'=> cdp_sanitize($_POST['status_type'])
        );

        $update = cdp_updateStatusCourier($data);

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_updated'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
        }
    }

    header('Content-type: application/json; charset=UTF-8');
    echo json_encode($response);


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