<?php
/**
 * Step 1 of changing / confirming the logged-in customer's WhatsApp number.
 *
 * Flow (driven by dataJs/check_user_update.js):
 *   1. The customer is first asked whether the number on file is the one they
 *      use on WhatsApp. "Yes" posts that number here; "No" posts a new one.
 *   2. When the system-wide OTP switch is ON, a 6-digit code is sent to the
 *      number over WhatsApp and the number is only stored after
 *      verify_profile_phone_otp_ajax.php confirms the code.
 *      When the switch is OFF the number is stored immediately
 *      (`otp_required: false` in the response).
 *
 * A number that cannot receive the WhatsApp message is rejected, so a
 * customer can never end up with an unreachable number on file.
 */
ini_set('display_errors', 0);

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../helpers/profile.php");
require_once("../helpers/otp_settings.php");
require_once("../lib/OtpService.php");
require_once(__DIR__ . "/notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../helpers/ajax_guard.php");
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$db   = new Conexion;
$core = new Core();

$phone = cdp_profileNormalizePhone($_POST['phone'] ?? '');
$digits = ltrim($phone, '+');

if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid WhatsApp number including the country code.']);
    exit;
}

$db->cdp_query("SELECT id, fname, lname, phone FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', (int) $user->uid);
$u = $db->cdp_registro();
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

// ── OTP switched off system-wide: store the number right away ────────────────
if (!cdp_otpEnabled()) {
    $db->cdp_query("UPDATE cdb_users SET phone = :phone WHERE id = :id");
    $db->bind(':phone', $phone);
    $db->bind(':id', (int) $user->uid);
    $db->cdp_execute();
    cdp_profileMarkStep((int) $user->uid, 'update_phone');
    cdp_profileMarkPhoneVerified((int) $user->uid, $phone, 'no_otp');

    if (function_exists('cdp_activityLog')) {
        cdp_activityLog([
            'module'      => 'profile',
            'verb'        => 'update',
            'action'      => 'profile.phone',
            'label'       => 'Profile · WhatsApp Number Updated',
            'entity_type' => 'user',
            'entity_id'   => (int) $user->uid,
            'summary'     => 'Updated their WhatsApp number (OTP disabled system-wide)',
            'changes'     => ['phone' => ['from' => (string) $u->phone, 'to' => $phone]],
        ]);
    }

    echo json_encode([
        'status'       => 'success',
        'otp_required' => false,
        'message'      => 'WhatsApp number saved.',
    ]);
    exit;
}

// ── OTP on: create the challenge and deliver it over WhatsApp ────────────────
$otp = new OtpService();
$challenge = $otp->createChallenge((int) $user->uid, 'profile_phone', ['phone' => $phone], 300);

if (!empty($challenge['blocked'])) {
    $msg = $challenge['error'] ?? 'Please wait before requesting a new code.';
    if (!empty($challenge['wait_sec'])) {
        $msg = 'Please wait ' . (int) $challenge['wait_sec'] . ' seconds before requesting another code.';
    }
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$sender = new stdClass();
$sender->phone = $phone;

$name = trim($u->fname . ' ' . $u->lname);
$message = implode("\n", [
    "Dear " . ucfirst($name) . ",",
    "",
    "Your WhatsApp verification code is: *{$challenge['code']}*",
    "",
    "This code expires in *5 minutes*. Do not share it with anyone — {$core->site_name} will never ask for it.",
    "",
    "If you did not request a phone number update, please report this to the administrator immediately.",
    "",
    "Thank you.",
    "{$core->site_name} Team.",
]);

$sendResult = sendNotificationWhatsApp_v2($sender, $message);

if (empty($sendResult['success'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'We could not reach that number on WhatsApp. ' . ($sendResult['message'] ?? 'Please check it and try again.'),
    ]);
    exit;
}

$_SESSION['profile_phone_otp_challenge'] = (int) $challenge['id'];

$masked = substr($phone, 0, 5) . str_repeat('*', max(0, strlen($phone) - 8)) . substr($phone, -3);

echo json_encode([
    'status'       => 'success',
    'otp_required' => true,
    'challenge_id' => (int) $challenge['id'],
    'masked'       => $masked,
    'message'      => 'A verification code has been sent to ' . $masked . ' on WhatsApp.',
]);
