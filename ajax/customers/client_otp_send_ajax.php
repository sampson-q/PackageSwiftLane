<?php
// Send (or resend) the email-verification OTP for the client an admin just
// created in client_add. Operates ONLY on the pending account bound to this
// admin's session, so it can never be aimed at an arbitrary user id.

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

$uid = isset($_SESSION['client_add_pending_user_id']) ? (int) $_SESSION['client_add_pending_user_id'] : 0;
if ($uid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No pending client to verify.']);
    exit;
}

$db->cdp_query("SELECT email, fname, lname FROM cdb_users WHERE id = :id AND registration_complete = 0 LIMIT 1");
$db->bind(':id', $uid);
$u = $db->cdp_registro();

if (!$u) {
    echo json_encode(['ok' => false, 'error' => 'This client is already verified or no longer pending.']);
    exit;
}

$challenge = $otp->createChallenge($uid, 'signup', ['email' => $u->email]);

if (!empty($challenge['blocked'])) {
    // Rate-limited: keep the existing challenge so the entered code still verifies.
    if (!empty($challenge['existing_id'])) {
        $_SESSION['client_add_otp_challenge'] = (int) $challenge['existing_id'];
    }
    echo json_encode(['ok' => false, 'cooldown' => true, 'error' => $challenge['error']]);
    exit;
}

$_SESSION['client_add_otp_challenge'] = (int) $challenge['id'];

$res = $otp->sendOtpEmail($u->email, trim($u->fname . ' ' . $u->lname), $challenge['code'], 'signup');

if (empty($res['ok'])) {
    echo json_encode(['ok' => false, 'error' => 'Email could not be sent: ' . ($res['error'] ?? 'unknown error')]);
    exit;
}

echo json_encode(['ok' => true, 'email' => $u->email]);
exit;
