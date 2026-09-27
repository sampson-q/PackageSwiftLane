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
 *   - Partial updates are allowed: a customer who only wants to change their
 *     name saves just that. A field left blank keeps what is stored (it never
 *     wipes a value); a field that IS filled in must be valid.
 *   - Addresses are optional. An empty new address block is ignored; a new
 *     block that is only partly filled is rejected (so half an address is never
 *     saved); an existing address keeps its stored value for any field left
 *     blank. Password is optional (blank = unchanged).
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
$notes  = array_key_exists('notes', $_POST) ? trim((string) $_POST['notes']) : (string) $row->notes;
$pass   = (string) ($_POST['password'] ?? '');

// Blank = keep the stored value. Only what the customer actually filled in is
// checked, so an untouched field can never block a save.
if ($fname === '') {
    $fname = (string) $row->fname;
} elseif (mb_strlen($fname) < 2) {
    $errors['fname'] = 'First name must be at least 2 characters.';
}
if ($lname === '') {
    $lname = (string) $row->lname;
} elseif (mb_strlen($lname) < 2) {
    $errors['lname'] = 'Last name must be at least 2 characters.';
}
if ($email === '') {
    $email = (string) $row->email;
} elseif (strcasecmp($email, (string) $row->email) !== 0) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$user->cdp_isValidEmail($email)) {
        $errors['email'] = $lang['validate_field_ajax127'] ?? 'Invalid email address.';
    } elseif ($user->cdp_emailExists($email, $targetId)) {
        $errors['email'] = $lang['validate_field_ajax126'] ?? 'This email is already in use by another account.';
    }
}
if ($gender === '') {
    $gender = (string) $row->gender;
} elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $errors['gender'] = 'Please choose a gender from the list.';
}
if ($pass !== '' && strlen($pass) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
}

// Addresses (optional). Stored rows of this account, for the keep-blank merge.
$db->cdp_query("SELECT * FROM cdb_senders_addresses WHERE user_id = :uid");
$db->bind(':uid', $targetId);
$storedAddr = [];
foreach ((array) $db->cdp_registros() as $sa) {
    $storedAddr[(int) $sa->id_addresses] = $sa;
}

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
    $filled = ($a['address'] !== '' || $a['country'] > 0 || $a['state'] > 0 || $a['city'] > 0 || $a['postal'] !== '');

    if ($a['address_id'] > 0 && isset($storedAddr[$a['address_id']])) {
        // Existing address: a blank field keeps what is stored.
        $sa = $storedAddr[$a['address_id']];
        if ($a['address'] === '') $a['address'] = (string) $sa->address;
        if ($a['country'] <= 0)   $a['country'] = (int) $sa->country;
        if ($a['state'] <= 0)     $a['state']   = (int) $sa->state;
        if ($a['city'] <= 0)      $a['city']    = (int) $sa->city;
        if ($a['postal'] === '')  $a['postal']  = (string) $sa->zip_code;
        $addresses[] = $a;
        continue;
    }

    $a['address_id'] = 0;
    if (!$filled) {
        continue; // an empty new block is simply ignored
    }
    $missing = [];
    if ($a['country'] <= 0)   $missing[] = 'country';
    if ($a['state'] <= 0)     $missing[] = 'state';
    if ($a['city'] <= 0)      $missing[] = 'city';
    if ($a['postal'] === '')  $missing[] = 'zip code';
    if ($a['address'] === '') $missing[] = 'address';
    if ($missing) {
        $errors['address_' . $posted] = 'Address ' . $posted . ' is incomplete: add the ' . implode(', ', $missing) . ', or clear the block to skip it.';
        continue;
    }
    $addresses[] = $a;
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
// The onboarding "address" step only counts once a complete address exists.
$db->cdp_query("SELECT 1 FROM cdb_senders_addresses WHERE user_id = :uid AND country > 0 AND state > 0 AND city > 0
                AND TRIM(COALESCE(address, '')) <> '' AND TRIM(COALESCE(zip_code, '')) <> '' LIMIT 1");
$db->bind(':uid', $targetId);
if ($db->cdp_registro()) {
    cdp_profileMarkStep($targetId, 'update_address');
}

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
