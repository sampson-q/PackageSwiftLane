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

// Keep stray warnings/notices out of the response body so the JSON stays clean
// (a warning printed before the JSON was making the client fall back to the
// generic "Error making request / unexpected error" instead of the real cause).
ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/ajax_guard.php");
require_once("../../helpers/rbac.php");
require_login();
require_permission('edit_client'); // was login-only; now honors the RBAC grant

header('Content-Type: application/json; charset=UTF-8');

$user = new User;
$core = new Core;
$errors = array();

// The client editor must never touch a STAFF account (driver/admin/agency/
// employee). Verify the target is a client before doing anything.
$targetId = (int) ($_POST['id'] ?? 0);
if ($targetId > 0) {
    $tdb = new Conexion;
    $tdb->cdp_query("SELECT userlevel FROM cdb_users WHERE id = :id LIMIT 1");
    $tdb->bind(':id', $targetId);
    $tdb->cdp_execute();
    $trow = $tdb->cdp_registro();
    if (!$trow || !cdp_roleIsClient((int) $trow->userlevel)) {
        echo json_encode(['status' => 'error', 'message' => 'This screen can only edit client accounts.']);
        exit;
    }
}

if (empty($_POST['fname'])) {
    $errors['fname'] = $lang['validate_field_ajax122'];
}
if (empty($_POST['lname'])) {
    $errors['lname'] = $lang['validate_field_ajax123'];
}
if (empty($_POST['email'])) {
    $errors['email'] = $lang['validate_field_ajax125'];
} elseif (!$user->cdp_isValidEmail($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = $lang['validate_field_ajax127'];
} elseif ($user->cdp_emailExists($_POST['email'], $_POST['id'])) {
    $errors['email'] = $lang['validate_field_ajax126'];
}
if (empty($_POST['phone'])) {
    $errors['phone'] = $lang['validate_field_ajax128'];
}

$approve = 0;
if (!empty($_POST['approve'])) {
    $approve = cdp_sanitize($_POST['approve']);
}

// Return every failure as JSON with the exact reason(s) — the old HTML alert
// couldn't be read by the JS handler, which then showed a generic error.
if (!empty($errors)) {
    echo json_encode([
        'status'  => 'error',
        'message' => implode(' ', array_values($errors)),
        'errors'  => array_values($errors),
    ]);
    exit;
}

if (CDP_APP_MODE_DEMO === true) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'This is a demo version — this action is not allowed.',
    ]);
    exit;
}

{
    {
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
            'id' => cdp_sanitize($_POST['id']),
            'company' => cdp_sanitize($_POST['company']) ?? ''
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
}
