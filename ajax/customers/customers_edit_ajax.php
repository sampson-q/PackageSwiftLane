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

// Partial saves: a field left blank keeps the customer's stored value, so an
// empty field the admin did not touch (a missing ID number, an old phone that
// was never filled in) can no longer block saving e.g. a name change.
$currentRow = null;
if ($targetId > 0) {
    $cur = cdp_getUserEdit4bozo($targetId);
    $currentRow = ($cur && $cur['rowCount'] == 1) ? $cur['data'] : null;
}
if (!$currentRow) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
    exit;
}
foreach (['fname', 'lname', 'email', 'phone', 'document_type', 'document_number', 'gender', 'company'] as $k) {
    $v = isset($_POST[$k]) ? trim((string) $_POST[$k]) : '';
    if ($v === '' || $v === 'undefined') {
        $_POST[$k] = (string) ($currentRow->$k ?? '');
    }
}

if ($_POST['fname'] !== (string) $currentRow->fname && mb_strlen(trim($_POST['fname'])) < 2) {
    $errors['fname'] = 'First name must be at least 2 characters.';
}
if ($_POST['lname'] !== (string) $currentRow->lname && mb_strlen(trim($_POST['lname'])) < 2) {
    $errors['lname'] = 'Last name must be at least 2 characters.';
}
if (strcasecmp(trim($_POST['email']), (string) $currentRow->email) !== 0 && $_POST['email'] !== '') {
    if (!$user->cdp_isValidEmail($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = $lang['validate_field_ajax127'];
    } elseif ($user->cdp_emailExists($_POST['email'], $targetId)) {
        $errors['email'] = $lang['validate_field_ajax126'];
    }
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
            'newsletter' => isset($_POST['newsletter']) ? intval($_POST['newsletter']) : (int) $currentRow->newsletter,
            'notes' => array_key_exists('notes', $_POST) ? cdp_sanitize($_POST['notes']) : (string) $currentRow->notes,
            'phone' => cdp_sanitize($_POST['phone']),
            'gender' => cdp_sanitize($_POST['gender']),
            'active' => isset($_POST['active']) ? cdp_sanitize($_POST['active']) : (int) $currentRow->active,
            'id' => cdp_sanitize($_POST['id']),
            'company' => cdp_sanitize($_POST['company']) ?? ''
        );

        if ((int) $datos['active'] === 1 && $approve == 0) {
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
            // Addresses of this customer, for ownership and the keep-blank merge.
            $adb = new Conexion;
            $adb->cdp_query("SELECT * FROM cdb_senders_addresses WHERE user_id = :uid");
            $adb->bind(':uid', $targetId);
            $ownAddr = [];
            foreach ((array) $adb->cdp_registros() as $oa) {
                $ownAddr[(int) $oa->id_addresses] = $oa;
            }

            for ($count = 0; $count < (int) $_POST['total_address']; $count++) {
                $row = [
                    'address' => trim((string) ($_POST['address'][$count] ?? '')),
                    'country' => (int) ($_POST['country'][$count] ?? 0),
                    'city'    => (int) ($_POST['city'][$count] ?? 0),
                    'state'   => (int) ($_POST['state'][$count] ?? 0),
                    'postal'  => trim((string) ($_POST['postal'][$count] ?? '')),
                ];
                $aid = (int) ($_POST['address_id'][$count] ?? 0);

                if ($aid > 0) {
                    if (!isset($ownAddr[$aid])) {
                        continue; // never touch another account's address
                    }
                    $oa = $ownAddr[$aid];
                    cdp_updateCustomerAddress([
                        'address_id' => $aid,
                        'address' => cdp_sanitize($row['address'] !== '' ? $row['address'] : (string) $oa->address),
                        'country' => $row['country'] > 0 ? $row['country'] : (int) $oa->country,
                        'city'    => $row['city'] > 0 ? $row['city'] : (int) $oa->city,
                        'state'   => $row['state'] > 0 ? $row['state'] : (int) $oa->state,
                        'postal'  => cdp_sanitize($row['postal'] !== '' ? $row['postal'] : (string) $oa->zip_code),
                    ]);
                    continue;
                }

                // New address: saved only when complete; an empty block is skipped.
                if ($row['address'] === '' || $row['country'] <= 0 || $row['state'] <= 0 || $row['city'] <= 0 || $row['postal'] === '') {
                    continue;
                }
                cdp_insertAddressCustomer([
                    'user_id' => $targetId,
                    'address' => cdp_sanitize($row['address']),
                    'country' => $row['country'],
                    'city'    => $row['city'],
                    'state'   => $row['state'],
                    'postal'  => cdp_sanitize($row['postal']),
                ]);
            }
        }

        if ($update) {
            cdp_activityLog([
                'module'       => 'customers',
                'verb'         => 'update',
                'entity_type'  => 'user',
                'entity_id'    => (int) $datos['id'],
                'entity_label' => trim($datos['fname'] . ' ' . $datos['lname']),
                'summary'      => 'Updated customer profile - ' . trim($datos['fname'] . ' ' . $datos['lname']),
                'changes'      => cdp_activityDiff(
                    $userDataEdit['data'] ?? null,
                    $datos,
                    ['fname', 'lname', 'email', 'phone', 'company', 'gender',
                     'document_number', 'document_type', 'newsletter', 'notes', 'active']
                ),
            ]);

            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_updated'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
        }

        echo json_encode($response);
    }
}
