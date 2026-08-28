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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['site_name']))

    $errors['site_name'] = $lang['validate_field_ajax58'];

if (empty($_POST['site_url']))

    $errors['site_url'] = $lang['validate_field_ajax59'];


if (empty($_POST['code_number_locker']))

  $errors['code_number_locker'] = $lang['validate_field_ajax26'];


if (intval($_POST['digit_random_locker']) > 10 || intval($_POST['digit_random_locker']) < 1)

  $errors['track_digit_length'] = $lang['validate_field_ajax27'];

if (empty($_POST['prefix_locker']))

  $errors['prefix_locker'] = $lang['validate_field_ajax23'];




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
        'site_name' => cdp_sanitize($_POST['site_name']),
        'site_url' => cdp_sanitize($_POST['site_url']),
        'c_nit' => cdp_sanitize($_POST['c_nit']),
        'c_phone' => cdp_sanitize($_POST['c_phone']),
        'cell_phone' => cdp_sanitize($_POST['cell_phone']),
        'c_address' => cdp_sanitize($_POST['c_address']),
        'locker_address' => cdp_sanitize($_POST['locker_address']),
        'c_country' => cdp_sanitize($_POST['c_country']),
        'c_city' => cdp_sanitize($_POST['c_city']),
        'c_postal' => cdp_sanitize($_POST['c_postal']),
        'site_email' => cdp_sanitize($_POST['site_email']),
        'reg_allowed' => intval($_POST['reg_allowed']),
        'reg_verify' => intval($_POST['reg_verify']),
        'notify_admin' => intval($_POST['notify_admin']),
        'auto_verify' => intval($_POST['auto_verify']),
        'code_number_locker' => intval($_POST['code_number_locker']),
        'digit_random_locker' => cdp_sanitize($_POST['digit_random_locker']),
        'prefix_locker' => cdp_sanitize($_POST['prefix_locker'])
    );

        $insert = cdp_updateConfigSystemytdb1($data);

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