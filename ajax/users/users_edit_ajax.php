<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');

header('Content-type: application/json; charset=UTF-8');

try {
    require_login();
    require_permission('view_user_list');

    $user = new User;
    $core = new Core;
    $errors = array();

    // ============================================================
    // STEP 1: SECURITY CHECKS
    // ============================================================

    if (CDP_APP_MODE_DEMO === true) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Demo mode blocked']);
        exit;
    }

    if (empty($_POST['_csrf_token'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'CSRF token missing']);
        exit;
    }

    $user_id = isset($_POST['id']) ? intval(trim($_POST['id'])) : 0;
    if (!$user_id || $user_id < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        exit;
    }

    // ============================================================
    // STEP 2: FETCH CURRENT USER
    // ============================================================

    $userDataEdit = cdp_getUserEdit4bozo($user_id);
    if (!$userDataEdit || $userDataEdit['rowCount'] != 1) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    $currentUser = $userDataEdit['data'];

    // ============================================================
    // STEP 3: PERMISSION CHECK
    // ============================================================

    if ((int)$currentUser->userlevel === 9 && (int)$user->userlevel !== 9) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No permission to edit this user']);
        exit;
    }

    // ============================================================
    // STEP 4: PREPARE NEW DATA & DETECT CHANGES
    // ============================================================

    $newData = array(
        'fname'         => trim($_POST['fname'] ?? ''),
        'lname'         => trim($_POST['lname'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'gender'        => $_POST['gender'] ?? '',
        'active'        => isset($_POST['active']) ? intval($_POST['active']) : null,
        'newsletter'    => isset($_POST['newsletter']) ? intval($_POST['newsletter']) : null,
        'notes'         => $_POST['notes'] ?? '',
        'branch_office' => trim($_POST['branch_office'] ?? ''),
        'password'      => $_POST['password'] ?? '',
        'document_type' => $_POST['document_type'] ?? '',
        'document_number' => $_POST['document_number'] ?? '',
        'userlevel'     => isset($_POST['userlevel']) ? intval($_POST['userlevel']) : (int)$currentUser->userlevel,
    );

    // Detect what changed
    $fieldsChanged = array(
        'fname'         => $newData['fname'] !== $currentUser->fname,
        'lname'         => $newData['lname'] !== $currentUser->lname,
        'email'         => $newData['email'] !== $currentUser->email,
        'phone'         => $newData['phone'] !== $currentUser->phone,
        'gender'        => $newData['gender'] !== $currentUser->gender,
        'active'        => $newData['active'] !== (int)$currentUser->active,
        'newsletter'    => $newData['newsletter'] !== (int)$currentUser->newsletter,
        'notes'         => $newData['notes'] !== $currentUser->notes,
        'branch_office' => $newData['branch_office'] !== $currentUser->name_off,
        'password'      => !empty($newData['password']),
        'document_type' => $newData['document_type'] !== $currentUser->document_type,
        'document_number' => $newData['document_number'] !== $currentUser->document_number,
        'userlevel'     => $newData['userlevel'] !== (int)$currentUser->userlevel,
    );

    // ============================================================
    // STEP 5: VALIDATE ONLY CHANGED FIELDS
    // ============================================================

    if ($fieldsChanged['fname']) {
        if (empty($newData['fname'])) {
            $errors['fname'] = 'First name is required.';
        } elseif (strlen($newData['fname']) < 2) {
            $errors['fname'] = 'First name must be at least 2 characters.';
        }
    }

    if ($fieldsChanged['lname']) {
        if (empty($newData['lname'])) {
            $errors['lname'] = 'Last name is required.';
        } elseif (strlen($newData['lname']) < 2) {
            $errors['lname'] = 'Last name must be at least 2 characters.';
        }
    }

    if ($fieldsChanged['email']) {
        if (empty($newData['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!$user->cdp_isValidEmail($newData['email'])) {
            $errors['email'] = 'Invalid email format.';
        } elseif ($user->cdp_emailExists($newData['email'], $user_id)) {
            $errors['email'] = 'This email is already in use.';
        }
    }

    if ($fieldsChanged['phone']) {
        if (empty($newData['phone'])) {
            $errors['phone'] = 'Phone is required.';
        } elseif (strlen($newData['phone']) < 7) {
            $errors['phone'] = 'Phone is invalid.';
        }
    }

    if ($fieldsChanged['branch_office']) {
        if (empty($newData['branch_office'])) {
            $errors['branch_office'] = 'Branch office is required.';
        }
    }

    if ($fieldsChanged['password']) {
        if (strlen($newData['password']) < 6) {
            $errors['password'] = 'Password must be 6+ characters.';
        }
    }

    // ============================================================
    // STEP 6: CHECK FOR ERRORS
    // ============================================================

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    // ============================================================
    // STEP 7: BUILD UPDATE ARRAY WITH ALL REQUIRED FIELDS
    // ============================================================
    // The cdp_updateUserrx0xr() function requires ALL fields to be present

    $updateData = array(
        'id'             => $user_id,
        'fname'          => $fieldsChanged['fname'] ? $newData['fname'] : $currentUser->fname,
        'lname'          => $fieldsChanged['lname'] ? $newData['lname'] : $currentUser->lname,
        'email'          => $fieldsChanged['email'] ? $newData['email'] : $currentUser->email,
        'phone'          => $fieldsChanged['phone'] ? $newData['phone'] : $currentUser->phone,
        'gender'         => $fieldsChanged['gender'] ? $newData['gender'] : $currentUser->gender,
        'active'         => $fieldsChanged['active'] ? $newData['active'] : (int)$currentUser->active,
        'newsletter'     => $fieldsChanged['newsletter'] ? $newData['newsletter'] : (int)$currentUser->newsletter,
        'notes'          => $fieldsChanged['notes'] ? $newData['notes'] : $currentUser->notes,
        'branch_office'  => $fieldsChanged['branch_office'] ? $newData['branch_office'] : $currentUser->name_off,
        'document_type'  => $fieldsChanged['document_type'] ? $newData['document_type'] : $currentUser->document_type,
        'document_number'=> $fieldsChanged['document_number'] ? $newData['document_number'] : $currentUser->document_number,
        'userlevel'      => $fieldsChanged['userlevel'] ? $newData['userlevel'] : (int)$currentUser->userlevel,
        'password'       => $fieldsChanged['password'] ? password_hash($newData['password'], PASSWORD_DEFAULT) : $currentUser->password,
    );

    // ============================================================
    // STEP 8: PERFORM UPDATE
    // ============================================================

    $result = cdp_updateUserrx0xr($updateData);

    if ($result === true || $result == 1 || !empty($result)) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => [
                'user_id' => $user_id,
                'changed' => array_keys(array_filter($fieldsChanged)),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update user in database'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

exit;
?>