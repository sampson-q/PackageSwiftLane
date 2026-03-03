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

require_once("../../helpers/querys.php");

$errors = array();

if (empty($_POST['twilio_sms_sid']))
    $errors['twilio_sms_sid'] = $lang['validate_field_ajax70'];
if (empty($_POST['twilio_sms_token']))
    $errors['twilio_sms_token'] =  $lang['validate_field_ajax71'];

if (empty($_POST['twilio_sms_number']))
    $errors['twilio_sms_number'] =  $lang['validate_field_ajax72'];

if (!isset($_POST['active_sms'])) {
    $active_sms = 0;
} else {
    $active_sms = $_POST['active_sms'];
}


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

        $data = array(
            'twilio_sms_sid' => cdp_sanitize($_POST['twilio_sms_sid']),
            'twilio_sms_token' => cdp_sanitize($_POST['twilio_sms_token']),
            'twilio_sms_number' => cdp_sanitize($_POST['twilio_sms_number']),
            'active_sms' => $active_sms
        );

        $insert = cdp_updateTwiliosmsConfig($data);

        if ($insert) {
            $messages[] = $lang['message_ajax_success_updated'];
        } else {
            $errors['critical_error'] = $lang['message_ajax_error1'];
        }
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