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

$errors = array();

if (empty($_POST['timezone']))

    $errors['timezone'] = $lang['validate_field_ajax52'];

if (empty($_POST['language']))

    $errors['language'] = $lang['validate_field_ajax53'];

if (empty($_POST['currency']))

    $errors['currency'] = $lang['validate_field_ajax54'];

if (empty($_POST['for_currency']))

    $errors['for_currency'] = $lang['validate_field_ajax55'];

if (empty($_POST['for_symbol']))

    $errors['for_symbol'] = $lang['validate_field_ajax56'];

if (empty($_POST['for_decimal']))

    $errors['for_decimal'] = $lang['validate_field_ajax57'];

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

        if ($_POST['cformat'] == 1) {

            $cform = 1;
            $decpoint = ".";
            $thousandssep = "";
        } else if ($_POST['cformat'] == 2) {

            $cform = 2;
            $decpoint = ".";
            $thousandssep = ",";
        } else if ($_POST['cformat'] == 3) {

            $cform = 3;
            $decpoint = ",";
            $thousandssep = "";
        } else if ($_POST['cformat'] == 4) {

            $cform = 4;
            $decpoint = ",";
            $thousandssep = ".";
        }

        $data = array(
            'timezone' => cdp_sanitize($_POST['timezone']),
            'language' => cdp_sanitize($_POST['language']),
            'currency' => cdp_sanitize($_POST['currency']),
            'for_currency' => cdp_sanitize($_POST['for_currency']),
            'for_symbol' => cdp_sanitize($_POST['for_symbol']),
            'for_decimal' => cdp_sanitize($_POST['for_decimal']),
            'cformat' => $cform,
            'dec_point' => $decpoint,
            'thousands_sep' => $thousandssep,
        );


        $insert = cdp_updateConfigGeneral0gqr5($data);

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