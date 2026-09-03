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

/**
 * Customer profile save ("My Profile").
 *
 * Always answers JSON. The previous version printed HTML on validation
 * errors, relied on a staff-only permission and let the browser decide which
 * fields were mandatory — customers saw a generic error for every failure.
 *
 * Rules (per product decision):
 *   - Name, last name, email, gender and at least one complete address are
 *     mandatory. Password is optional (blank = unchanged).
 *   - The ID document is optional and is handled by its own endpoint.
 *   - The WhatsApp phone number is never changed here: it goes through the
 *     confirm-then-OTP flow (send/verify_profile_phone_otp_ajax.php).
 */
ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/rbac.php");
require_once("../../helpers/profile.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User;
$core = new Core;
$db   = new Conexion;

function cdp_profileRespond($status, $message, array $extra = [])
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (CDP_APP_MODE_DEMO === true) {
    cdp_profileRespond('error', 'This is a demo version, this action is not allowed.');
}

$targetId = (int) ($_POST['id'] ?? 0);
if (!cdp_profileCanEdit($user, $targetId, 'edit_client')) {
    cdp_profileRespond('error', 'You can only edit your own profile.');
}

$current = cdp_getUserEdit4bozo($targetId);
if (!$current || $current['rowCount'] != 1) {
    cdp_profileRespond('error', 'Account not found.');
}
$row = $current['data'];
if (!cdp_roleIsClient((int) $row->userlevel)) {
    cdp_profileRespond('error', 'This page can only edit customer accounts.');
}

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

$fname  = trim((string) ($_POST['fname'] ?? ''));
$lname  = trim((string) ($_POST['lname'] ?? ''));
$email  = trim((string) ($_POST['email'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$notes  = trim((string) ($_POST['notes'] ?? ''));
$pass   = (string) ($_POST['password'] ?? '');

if ($fname === '' || mb_strlen($fname) < 2) {
    $errors['fname'] = $lang['validate_field_ajax122'] ?? 'First name is required.';
}
if ($lname === '' || mb_strlen($lname) < 2) {
    $errors['lname'] = $lang['validate_field_ajax123'] ?? 'Last name is required.';
}
if ($email === '') {
    $errors['email'] = $lang['validate_field_ajax125'] ?? 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$user->cdp_isValidEmail($email)) {
    $errors['email'] = $lang['validate_field_ajax127'] ?? 'Invalid email address.';
} elseif ($user->cdp_emailExists($email, $targetId)) {
    $errors['email'] = $lang['validate_field_ajax126'] ?? 'This email is already in use by another account.';
}
if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $errors['gender'] = 'Please select your gender.';
}
if ($pass !== '' && strlen($pass) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
}

// Addresses: at least one, every field filled, ids must belong to this account.
$total     = (int) ($_POST['total_address'] ?? 0);
$addresses = [];
$posted    = 0;
for ($i = 0; $i < $total; $i++) {
    if (!isset($_POST['address'][$i]) && !isset($_POST['country'][$i])) {
        continue; // a removed row leaves a gap in the index
    }
    $posted++;
    $a = [
        'address_id' => (int) ($_POST['address_id'][$i] ?? 0),
        'address'    => trim((string) ($_POST['address'][$i] ?? '')),
        'country'    => (int) ($_POST['country'][$i] ?? 0),
        'state'      => (int) ($_POST['state'][$i] ?? 0),
        'city'       => (int) ($_POST['city'][$i] ?? 0),
        'postal'     => trim((string) ($_POST['postal'][$i] ?? '')),
    ];
    if ($a['address'] === '' || $a['country'] <= 0 || $a['state'] <= 0 || $a['city'] <= 0 || $a['postal'] === '') {
        $errors['address_' . ($posted)] = 'Address ' . $posted . ': country, state, city, zip code and address are all required.';
    }
    $addresses[] = $a;
}
if ($posted === 0) {
    $errors['address'] = $lang['validate_field_ajax134'] ?? 'At least one address is required.';
}

if (!empty($errors)) {
    cdp_profileRespond('error', implode(' ', array_values($errors)), ['errors' => $errors]);
}

// ── Save ─────────────────────────────────────────────────────────────────────
$sql = 'UPDATE cdb_users SET fname = :fname, lname = :lname, email = :email, gender = :gender, notes = :notes'
     . ($pass !== '' ? ', password = :password' : '')
     . ' WHERE id = :id';
$db->cdp_query($sql);
$db->bind(':fname', cdp_sanitize($fname));
$db->bind(':lname', cdp_sanitize($lname));
$db->bind(':email', cdp_sanitize($email));
$db->bind(':gender', cdp_sanitize($gender));
$db->bind(':notes', cdp_sanitize($notes));
if ($pass !== '') {
    $db->bind(':password', password_hash($pass, PASSWORD_DEFAULT));
}
$db->bind(':id', $targetId);

if (!$db->cdp_execute()) {
    cdp_profileRespond('error', $lang['message_ajax_error1'] ?? 'Could not save your profile.');
}

foreach ($addresses as $a) {
    if ($a['address_id'] > 0) {
        // Only rows that belong to this account may be updated.
        $db->cdp_query("SELECT id_addresses FROM cdb_senders_addresses WHERE id_addresses = :aid AND user_id = :uid LIMIT 1");
        $db->bind(':aid', $a['address_id']);
        $db->bind(':uid', $targetId);
        if ($db->cdp_registro()) {
            cdp_updateCustomerAddress([
                'address_id' => $a['address_id'],
                'address'    => cdp_sanitize($a['address']),
                'country'    => $a['country'],
                'city'       => $a['city'],
                'state'      => $a['state'],
                'postal'     => cdp_sanitize($a['postal']),
            ]);
            continue;
        }
    }
    cdp_insertAddressCustomer([
        'user_id' => $targetId,
        'address' => cdp_sanitize($a['address']),
        'country' => $a['country'],
        'city'    => $a['city'],
        'state'   => $a['state'],
        'postal'  => cdp_sanitize($a['postal']),
    ]);
}
cdp_profileMarkStep($targetId, 'update_address');

$changed = [];
foreach (['fname' => $fname, 'lname' => $lname, 'email' => $email, 'gender' => $gender, 'notes' => $notes] as $k => $v) {
    if ((string) $row->$k !== (string) $v) {
        $changed[$k] = ['from' => (string) $row->$k, 'to' => (string) $v];
    }
}
if ($pass !== '') {
    $changed['password'] = ['from' => '••••', 'to' => '•••• (changed)'];
}
if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'       => 'profile',
        'verb'         => 'update',
        'action'       => 'profile.details',
        'label'        => 'Profile · Details Updated',
        'entity_type'  => 'user',
        'entity_id'    => $targetId,
        'entity_label' => trim($fname . ' ' . $lname),
        'summary'      => ($targetId === (int) $user->uid)
            ? 'Updated their own profile details'
            : 'Updated the profile of customer #' . $targetId,
        'changes'      => $changed,
        'meta'         => ['addresses' => count($addresses)],
    ]);
}

cdp_profileRespond('success', $lang['message_ajax_success_updated'] ?? 'Profile updated.');
