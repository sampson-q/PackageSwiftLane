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

$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['fname'])) {
    $errors['fname'] = $lang['validate_field_ajax122'];
}
if (empty($_POST['lname'])) {
    $errors['lname'] = $lang['validate_field_ajax123'];
}
if (empty($_POST['email'])) {
    $errors['email'] = $lang['validate_field_ajax125'];
}
if ($user->cdp_emailExists($_POST['email'], $_POST['id'])) {
    $errors[] = $lang['validate_field_ajax126'];
}
if (!$user->cdp_isValidEmail($_POST['email'])) {
    $errors[] = $lang['validate_field_ajax127'];
}
if (empty($_POST['phone'])) {
    $errors['phone'] = $lang['validate_field_ajax128'];
}

if (!empty($_POST['approve'])) {
    $approve = cdp_sanitize($_POST['approve']);
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
        header('Content-type: application/json; charset=UTF-8');
        $response = array();

        $datos = array(
            'email' => cdp_sanitize($_POST['email']),
            'lname' => cdp_sanitize($_POST['lname']),
            'fname' => cdp_sanitize($_POST['fname']),
            'document_number' => cdp_sanitize($_POST['document_number'] ?? ''),
            'document_type' => cdp_sanitize($_POST['document_type'] ?? ''),
            'newsletter' => intval($_POST['newsletter']),
            'notes' => cdp_sanitize($_POST['notes']),
            'phone' => cdp_sanitize($_POST['phone']),
            'gender' => cdp_sanitize($_POST['gender']),
            'active' => cdp_sanitize($_POST['active']),
            'id' => cdp_sanitize($_POST['id'])
        );

        if (cdp_sanitize($_POST['active']) == 1 && $approve == 0) {
            $datos['approve'] = 1;
        }

        $userDataEdit = cdp_getUserEdit4bozo($_POST['id']);

        if (!empty($_POST['password'])) {
            $datos['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } else {
            $datos['password'] = $userDataEdit['data']->password;
        }

        // Handle status update logic
        if (isset($_POST['stat'])) {
            $stat = cdp_sanitize($_POST['stat']);
            $statusUpdate = cdp_updateUserStatus4234sf($datos['id'], $stat);

            if (!$statusUpdate) {
                $errors[] = 'Failed to update user status';
            }
        }

        $update = cdp_updateCustomers($datos, $approve=true);

        if ($update && isset($_POST['total_address'])) {
            for ($count = 0; $count < $_POST['total_address']; $count++) {
                if (!empty($_POST['address_id'][$count])) {
                    $dataAddresses = array(
                        'address_id' => cdp_sanitize($_POST['address_id'][$count]),
                        'address' => cdp_sanitize($_POST['address'][$count]),
                        'country' => cdp_sanitize($_POST['country'][$count]),
                        'city' => cdp_sanitize($_POST['city'][$count]),
                        'state' => cdp_sanitize($_POST['state'][$count]),
                        'postal' => cdp_sanitize($_POST['postal'][$count])
                    );

                    cdp_updateCustomerAddress($dataAddresses);
                } else {
                    $dataAddresses = array(
                        'user_id' => cdp_sanitize($datos['id']),
                        'address' => cdp_sanitize($_POST['address'][$count]),
                        'country' => cdp_sanitize($_POST['country'][$count]),
                        'city' => cdp_sanitize($_POST['city'][$count]),
                        'state' => cdp_sanitize($_POST['state'][$count]),
                        'postal' => cdp_sanitize($_POST['postal'][$count])
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
                        <?php echo $error; ?>
                    </li>
                <?php } ?>
            </ul>
            </p>
        </div>
        <?php
    }
}
