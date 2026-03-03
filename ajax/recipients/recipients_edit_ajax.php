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
require_permission('view_recipients');
$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['fname']))
    $errors['fname'] = $lang['validate_field_ajax122'];

if (empty($_POST['lname']))
    $errors['lname'] = $lang['validate_field_ajax123'];

if (empty($_POST['email']))
    $errors['email'] = $lang['validate_field_ajax125'];


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
            'lname' => cdp_sanitize($_POST['lname']),
            'fname' => cdp_sanitize($_POST['fname']),
            'phone' => cdp_sanitize($_POST['phone']),
            'email' => cdp_sanitize($_POST['email']),
            'id_recipient' => $_POST['recipient_id'],
        );

        $update = cdp_updateRecipient($data);

        if ($update  && isset($_POST["total_address"])) {

            for ($count = 0; $count < $_POST["total_address"]; $count++) {

                if (isset($_POST["address_id"][$count]) && !empty($_POST["address_id"][$count])) {

                    $dataAddresses = array(
                        'address_id' =>  cdp_sanitize($_POST["address_id"][$count]),
                        'address' =>  cdp_sanitize($_POST["address"][$count]),
                        'country' =>  cdp_sanitize($_POST["country"][$count]),
                        'city' =>  cdp_sanitize($_POST["city"][$count]),
                        'state' =>  cdp_sanitize($_POST["state"][$count]),
                        'postal' =>  cdp_sanitize($_POST["postal"][$count])
                    );

                    cdp_updateRecipientAddress($dataAddresses);
                } else {

                    $dataAddresses = array(
                        'recipient_id' =>   cdp_sanitize($_POST['recipient_id']),
                        'address' =>  cdp_sanitize($_POST["address"][$count]),
                        'country' =>  cdp_sanitize($_POST["country"][$count]),
                        'city' =>  cdp_sanitize($_POST["city"][$count]),
                        'state' =>  cdp_sanitize($_POST["state"][$count]),
                        'postal' =>  cdp_sanitize($_POST["postal"][$count])
                    );

                    cdp_insertAddressRecipient($dataAddresses);
                }
            }
        }


        if ($update) {
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