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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');
require_csrf();

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
                This is a demo version, this action is not allowed, <a class="btn waves-effect waves-light btn-xs btn-success" href="https://codecanyon.net/item/courier-deprixa-pro-integrated-web-system-v32/15216982" target="_blank">Buy DEPRIXA PRO</a> the full version and enjoy all the functions...

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