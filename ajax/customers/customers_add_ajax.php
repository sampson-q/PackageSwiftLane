<?php
// Admin "add client" — mirrors public sign-up, only from the admin side:
// the client is created as an INCOMPLETE, pre-approved account and must pass
// email-OTP verification (shown in a SweetAlert) before it is activated.
// WhatsApp-number verification is then forced on the client's first login
// (update_phone = 0), exactly like sign-up.

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

header('Content-Type: application/json; charset=UTF-8');

$user = new User;
$core = new Core;
$db   = new Conexion;

$errors = array();

$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$fname           = trim($_POST['fname'] ?? '');
$lname           = trim($_POST['lname'] ?? '');
$password        = $_POST['password'] ?? '';
$phone           = trim($_POST['phone'] ?? '');
$document_type   = trim($_POST['document_type'] ?? '');
$document_number = trim($_POST['document_number'] ?? '');

// ── Validation (mirrors sign-up) ────────────────────────────────────────────
if ($username === '') {
    $errors[] = $lang['validate_field_ajax117'];
} elseif (strlen($username) < 4 || !ctype_alnum($username)) {
    $errors[] = $lang['messagesform80'];
} elseif ($user->cdp_usernameExists($username)) {
    $errors[] = $lang['validate_field_ajax118'];
}

if ($fname === '')           $errors[] = $lang['validate_field_ajax122'];
if ($lname === '')           $errors[] = $lang['validate_field_ajax123'];

if ($password === '') {
    $errors[] = $lang['validate_field_ajax124'];
} elseif (strlen($password) < 8) {
    $errors[] = $lang['messagesform75'];
}

if ($email === '') {
    $errors[] = $lang['validate_field_ajax125'];
} elseif (!$user->cdp_isValidEmail($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = $lang['validate_field_ajax127'];
} elseif ($user->cdp_emailExists($email)) {
    $errors[] = $lang['validate_field_ajax126'];
}

if ($document_number !== '') {
    // Block only when a *usable* (active/approved) account already uses it.
    $db->cdp_query("SELECT id FROM cdb_users WHERE document_number = :d AND (active = 1 OR approve = 1) LIMIT 1");
    $db->bind(':d', cdp_sanitize($document_number));
    $db->cdp_execute();
    if ($db->cdp_registro()) {
        $errors[] = $lang['messagesform82'] ?? 'Document number already in use.';
    }
}

if ($phone === '') $errors[] = $lang['validate_field_ajax128'];

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $errors), 'errors' => $errors]);
    exit;
}

// ── Create the incomplete, pre-approved account ─────────────────────────────
$settings = cdp_getSettingsCourier();
$prefixlk = $settings->prefix_locker;

// Uniqueness: claim the locker digits server-side (see helpers/unique_ids.php).
require_once __DIR__ . '/../../helpers/unique_ids.php';
$_POST['locker'] = cdp_claimLockerDigits($_POST['locker'] ?? '');

$datos = array(
    'username'        => cdp_sanitize($username),
    'locker'          => cdp_sanitize($prefixlk . ' ' . ($_POST['locker'] ?? '')),
    'userlevel'       => 1,
    'email'           => cdp_sanitize($email),
    'fname'           => cdp_sanitize($fname),
    'lname'           => cdp_sanitize($lname),
    'notes'           => cdp_sanitize($_POST['notes'] ?? ''),
    'phone'           => cdp_sanitize($phone),
    'gender'          => cdp_sanitize($_POST['gender'] ?? ''),
    'newsletter'      => intval($_POST['newsletter'] ?? 0),
    'active'          => 0, // inactive until the email OTP is verified
    'document_type'   => cdp_sanitize($document_type),
    'document_number' => cdp_sanitize($document_number),
    'password'        => password_hash($password, PASSWORD_DEFAULT),
    'created'         => date('Y-m-d H:i:s'),
);

$ctx = cdp_getAgencyContext();
if ($ctx['is_restricted']) {
    if ($ctx['agency_id'] === null) {
        echo json_encode(['status' => 'error', 'message' => 'Your agency user has no associated agency.']);
        exit;
    }
    $datos['agency_id'] = (int) $ctx['agency_id'];
}

$customer_id = cdp_insertCustomer($datos);

if (!$customer_id) {
    echo json_encode(['status' => 'error', 'message' => $lang['message_ajax_error1']]);
    exit;
}

// Admin authorised this client → approve = 1; email not yet verified → registration_complete = 0
$db->cdp_query("UPDATE cdb_users SET approve = 1, registration_complete = 0 WHERE id = :id");
$db->bind(':id', $customer_id);
$db->cdp_execute();

// Onboarding checklist — address + document captured now (1); WhatsApp number
// left at 0 so the post-login account-setup forces phone verification.
$db->cdp_query("INSERT INTO cdb_user_details_update_check
    (user_id, update_address, update_phone, update_document) VALUES (:id, 1, 0, 1)");
$db->bind(':id', $customer_id);
$db->cdp_execute();

// Addresses (the form supports multiple)
if (isset($_POST['total_address'])) {
    for ($count = 0; $count < (int) $_POST['total_address']; $count++) {
        if (!isset($_POST['address'][$count])) {
            continue;
        }
        cdp_insertAddressCustomer(array(
            'user_id' => $customer_id,
            'address' => cdp_sanitize($_POST['address'][$count]),
            'country' => cdp_sanitize($_POST['country'][$count]),
            'city'    => cdp_sanitize($_POST['city'][$count]),
            'state'   => cdp_sanitize($_POST['state'][$count]),
            'postal'  => cdp_sanitize($_POST['postal'][$count]),
        ));
    }
}

// Bind the pending verification to THIS admin session so the OTP endpoints can
// only ever act on the account just created here (prevents OTP abuse on
// arbitrary user ids).
// System-wide OTP switch OFF: the client is usable straight away (same end
// state the email code would have produced).
require_once __DIR__ . '/../../helpers/otp_settings.php';
if (!cdp_otpEnabled()) {
    $db->cdp_query("UPDATE cdb_users SET registration_complete = 1, active = 1 WHERE id = :id");
    $db->bind(':id', $customer_id);
    $db->cdp_execute();
    if (function_exists('cdp_sendTemplateEmail')) {
        $db->cdp_query("SELECT email, fname, lname, username, locker FROM cdb_users WHERE id = :id LIMIT 1");
        $db->bind(':id', $customer_id);
        $su = $db->cdp_registro();
        if ($su) {
            cdp_sendTemplateEmail(26, $su->email, [
                '[NAME]'     => trim($su->fname . ' ' . $su->lname),
                '[USERNAME]' => $su->username,
                '[LOCKER]'   => $su->locker,
                '[EMAIL]'    => $su->email,
            ]);
        }
    }
    cdp_activityLog([
        'module'       => 'customers',
        'verb'         => 'create',
        'entity_type'  => 'user',
        'entity_id'    => (int) $customer_id,
        'entity_label' => trim(($datos['fname'] ?? '') . ' ' . ($datos['lname'] ?? '')),
        'summary'      => 'Created customer ' . trim(($datos['fname'] ?? '') . ' ' . ($datos['lname'] ?? '')) . ' (' . $email . ') — OTP disabled system-wide',
        'meta'         => ['email' => $email],
    ]);
    echo json_encode(['status' => 'success', 'message' => 'Client created.']);
    exit;
}

$_SESSION['client_add_pending_user_id'] = (int) $customer_id;
unset($_SESSION['client_add_otp_challenge']);

cdp_activityLog([
    'module'       => 'customers',
    'verb'         => 'create',
    'entity_type'  => 'user',
    'entity_id'    => (int) $customer_id,
    'entity_label' => trim(($datos['fname'] ?? '') . ' ' . ($datos['lname'] ?? '')),
    'summary'      => 'Created customer ' . trim(($datos['fname'] ?? '') . ' ' . ($datos['lname'] ?? '')) . ' (' . $email . ')',
    'meta'         => ['email' => $email],
]);

echo json_encode([
    'status'  => 'verify',
    'message' => 'Client created. Verify their email to finish.',
    'email'   => $email,
]);
exit;
