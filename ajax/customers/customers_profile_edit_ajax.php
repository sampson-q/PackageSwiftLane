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
require_permission('view_client_list');

$user = new User;
$core = new Core;
$errors = array();



if (empty($_POST['fname']))

    $errors['fname'] = $lang['validate_field_ajax122'];
if (empty($_POST['lname']))

    $errors['lname'] = $lang['validate_field_ajax123'];

if (empty($_POST['email']))

    $errors['email'] = $lang['validate_field_ajax125'];

if ($user->cdp_emailExists($_POST['email'], $_POST['id']))

    $errors[] = $lang['validate_field_ajax126'];

if (!$user->cdp_isValidEmail($_POST['email']))

    $errors[] = $lang['validate_field_ajax127'];

if (empty($_POST['phone']))

    $errors['phone'] = $lang['validate_field_ajax128'];

if (empty($_POST['address']))

    $errors['phone'] = $lang['validate_field_ajax134'];




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



        if (isset($_POST['document_type'])) {

            $document_type = $_POST['document_type'];
        } else {

            $document_type = '';
        }

        if (isset($_POST['document_number'])) {

            $document_number = $_POST['document_number'];
        } else {

            $document_number = '';
        }

        $datos = array(
            'email' => cdp_sanitize($_POST['email']),
            'lname' => cdp_sanitize($_POST['lname']),
            'fname' => cdp_sanitize($_POST['fname']),
            'document_number' => cdp_sanitize($document_number),
            'document_type' => cdp_sanitize($document_type),
            'notes' => cdp_sanitize($_POST['notes']),
            'phone' => cdp_sanitize($_POST['phone']),
            'gender' => cdp_sanitize($_POST['gender']),
            'id' => cdp_sanitize($_POST['id'])
        );

        $userDataEdit = cdp_getUserEdit4bozo($_POST['id']);

        if ($_POST['password'] != "") {

            $datos['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } else {

            $datos['password'] = $userDataEdit['data']->password;
        }

        $update = cdp_updateCustomersprofile($datos);


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

                    cdp_updateCustomerAddress($dataAddresses);
                } else {

                    $dataAddresses = array(
                        'user_id' =>   cdp_sanitize($_POST['id']),
                        'address' =>  cdp_sanitize($_POST["address"][$count]),
                        'country' =>  cdp_sanitize($_POST["country"][$count]),
                        'city' =>  cdp_sanitize($_POST["city"][$count]),
                        'state' =>  cdp_sanitize($_POST["state"][$count]),
                        'postal' =>  cdp_sanitize($_POST["postal"][$count])
                    );

                    cdp_insertAddressCustomer($dataAddresses);
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