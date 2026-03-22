<?php
require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../lib/OtpService.php");

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$userData = $user->cdp_getUserData();
$otp = new OtpService();
$db = new Conexion;

$challengeId = isset($_POST['challenge_id']) ? (int)$_POST['challenge_id'] : 0;
$otpCode = trim($_POST['otp_code'] ?? '');

if ($challengeId <= 0 || $otpCode === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request."
    ]);
    exit;
}

$sessionChallengeId = isset($_SESSION['profile_phone_otp_challenge']) ? (int)$_SESSION['profile_phone_otp_challenge'] : 0;

if ($sessionChallengeId !== $challengeId) {
    echo json_encode([
        "status" => "error",
        "message" => "OTP session mismatch."
    ]);
    exit;
}

$verify = $otp->verifyChallenge($challengeId, $otpCode, 'profile_phone');

if (!$verify['ok']) {
    echo json_encode([
        "status" => "error",
        "message" => $verify['error']
    ]);
    exit;
}

$phone = isset($verify['metadata']['phone']) ? cdp_sanitize($verify['metadata']['phone']) : '';

if ($phone === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Phone number missing from verification metadata."
    ]);
    exit;
}

$db->cdp_query("UPDATE cdb_users SET phone = :phone WHERE id = :id");
$db->bind(':phone', $phone);
$db->bind(':id', (int)$userData->id);
$db->cdp_execute();

$db->cdp_query("SELECT id FROM cdb_user_details_update_check WHERE user_id = :user_id LIMIT 1");
$db->bind(':user_id', (int)$userData->id);
$existing = $db->cdp_registro();

if ($existing) {
    $db->cdp_query("UPDATE cdb_user_details_update_check SET update_phone = 1 WHERE user_id = :user_id");
    $db->bind(':user_id', (int)$userData->id);
    $db->cdp_execute();
} else {
    $db->cdp_query("
        INSERT INTO cdb_user_details_update_check (user_id, update_address, update_phone, update_document)
        VALUES (:user_id, 0, 1, 0)
    ");
    $db->bind(':user_id', (int)$userData->id);
    $db->cdp_execute();
}

unset($_SESSION['profile_phone_otp_challenge']);

echo json_encode([
    "status" => "success",
    "message" => "Phone verified and saved successfully."
]);