<?php
// Verify the email OTP for the pending client created in client_add. On success
// the account is marked email-verified (registration_complete = 1) and activated
// (active = 1) — the admin already approved it. Mirrors the sign-up flow's
// "registration received" email (template 26).

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../lib/OtpService.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

header('Content-Type: application/json; charset=UTF-8');

$db  = new Conexion;
$otp = new OtpService();

$uid  = isset($_SESSION['client_add_pending_user_id']) ? (int) $_SESSION['client_add_pending_user_id'] : 0;
$chid = isset($_SESSION['client_add_otp_challenge'])   ? (int) $_SESSION['client_add_otp_challenge']   : 0;
$code = isset($_POST['otp_code']) ? preg_replace('/\D/', '', (string) $_POST['otp_code']) : '';

if ($uid <= 0 || $chid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No pending verification. Please resend the code.']);
    exit;
}
if ($code === '') {
    echo json_encode(['ok' => false, 'error' => 'Enter the 6-digit code.']);
    exit;
}

$verify = $otp->verifyChallenge($chid, $code, 'signup');

if (empty($verify['ok'])) {
    echo json_encode(['ok' => false, 'error' => $verify['error']]);
    exit;
}

// Defence in depth: the verified challenge must belong to our pending client.
if ((int) $verify['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => 'Verification mismatch.']);
    exit;
}

$db->cdp_query("SELECT email, fname, lname, username, locker FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $uid);
$su = $db->cdp_registro();

$db->cdp_query("UPDATE cdb_users SET registration_complete = 1, active = 1 WHERE id = :id");
$db->bind(':id', $uid);
$db->cdp_execute();

if ($su && function_exists('cdp_sendTemplateEmail')) {
    cdp_sendTemplateEmail(26, $su->email, [
        '[NAME]'     => trim($su->fname . ' ' . $su->lname),
        '[USERNAME]' => $su->username,
        '[LOCKER]'   => $su->locker,
        '[EMAIL]'    => $su->email,
    ]);
}

unset($_SESSION['client_add_pending_user_id'], $_SESSION['client_add_otp_challenge']);

echo json_encode(['ok' => true]);
exit;
