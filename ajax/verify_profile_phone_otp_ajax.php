<?php
/**
 * Step 2 of changing / confirming the logged-in customer's WhatsApp number:
 * verify the code sent by send_profile_phone_otp_ajax.php and store the
 * number that was carried in the challenge metadata.
 */
ini_set('display_errors', 0);

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../helpers/profile.php");
require_once("../lib/OtpService.php");
require_once("../helpers/ajax_guard.php");
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$otp  = new OtpService();
$db   = new Conexion;

$challengeId = isset($_SESSION['profile_phone_otp_challenge']) ? (int) $_SESSION['profile_phone_otp_challenge'] : 0;
$otpCode     = preg_replace('/\D/', '', (string) ($_POST['otp_code'] ?? ''));

if ($challengeId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'No verification in progress. Please request a new code.']);
    exit;
}
if ($otpCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Enter the 6-digit code.']);
    exit;
}

$verify = $otp->verifyChallenge($challengeId, $otpCode, 'profile_phone');

if (empty($verify['ok'])) {
    echo json_encode(['status' => 'error', 'message' => $verify['error']]);
    exit;
}
if ((int) $verify['user_id'] !== (int) $user->uid) {
    echo json_encode(['status' => 'error', 'message' => 'Verification mismatch.']);
    exit;
}

$phone = isset($verify['metadata']['phone']) ? cdp_profileNormalizePhone($verify['metadata']['phone']) : '';
if ($phone === '') {
    echo json_encode(['status' => 'error', 'message' => 'Phone number missing from the verification. Please start again.']);
    exit;
}

$db->cdp_query("SELECT phone FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', (int) $user->uid);
$before = $db->cdp_registro();

$db->cdp_query("UPDATE cdb_users SET phone = :phone WHERE id = :id");
$db->bind(':phone', $phone);
$db->bind(':id', (int) $user->uid);
$db->cdp_execute();

cdp_profileMarkStep((int) $user->uid, 'update_phone');
cdp_profileMarkPhoneVerified((int) $user->uid, $phone, 'whatsapp_otp');
unset($_SESSION['profile_phone_otp_challenge']);

if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'      => 'profile',
        'verb'        => 'update',
        'action'      => 'profile.phone',
        'label'       => 'Profile · WhatsApp Number Verified',
        'entity_type' => 'user',
        'entity_id'   => (int) $user->uid,
        'summary'     => 'Verified their WhatsApp number by one-time code',
        'changes'     => ['phone' => ['from' => (string) ($before->phone ?? ''), 'to' => $phone]],
    ]);
}

echo json_encode([
    'status'  => 'success',
    'phone'   => $phone,
    'message' => 'WhatsApp number verified and saved.',
]);
