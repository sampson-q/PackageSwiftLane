<?php
require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../lib/OtpService.php");
require_once(__DIR__ . "/notify_whatsapp/api_whatsapp_service_v2.php");

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$userData = $user->cdp_getUserData();
$otp = new OtpService();
$db = new Conexion;

$phone = cdp_sanitize($_POST['phone'] ?? '');

if ($phone === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Phone number is required."
    ]);
    exit;
}

$db->cdp_query("SELECT fname, lname, email FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', (int)$userData->id);
$u = $db->cdp_registro();

if (!$u) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found."
    ]);
    exit;
}

$challenge = $otp->createChallenge((int)$userData->id, 'profile_phone', [
    'phone' => $phone
], 300);

$sender = new stdClass();
$sender->phone = $phone;

$name = trim($u->fname . ' ' . $u->lname);

$message = implode("\n", [
    "Dear " . ucfirst($name) . ",",
    "",
    "Your WhatsApp verification OTP is: *{$challenge['code']}*",
    "",
    "⚠️ *Note* that this One-Time Password will expire in *5 minutes*. Do *not* share this code with anyone — {$this->core->site_name} will never ask for it.",
    "",
    "If you did not request a phone number update, please report this to the administrator immediately.",
    "",
    "Thank you.",
    "{$this->core->site_name} Team."
]);

$sendResult = sendNotificationWhatsApp_v2($sender, $message);

if (!$sendResult['success']) {
    echo json_encode([
        "status" => "error",
        "message" => $sendResult['message']
    ]);
    exit;
}

$_SESSION['profile_phone_otp_challenge'] = $challenge['id'];

echo json_encode([
    "status" => "success",
    "challenge_id" => $challenge['id'],
    "message" => "OTP sent successfully."
]);