<?php

ini_set('display_errors', 0);

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../lib/OtpService.php");

$user = new User;
$core = new Core;
$db = new Conexion;
$otp = new OtpService;

$error = "";

// Max file size: 5MB (adjust to taste)
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

$requiredFields = array('terms', 'country', 'state', 'city', 'address', 'postal', 'username', 'email', 'phone', 'fname', 'lname', 'document_number', 'document_type');
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        $error = 'Please enter ' . str_replace('_', ' ', $field);
    }
}

if ($user->cdp_usernameExists($_POST['username'])) $error = $lang['messagesform81'];
if (strlen($_POST['username']) < 4 || !ctype_alnum($_POST['username'])) $error = $lang['messagesform80'];
if ($user->cdp_ccnumberExists($_POST['document_number'])) $error = $lang['messagesform82'];
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $error = $lang['messagesform79'];
if ($user->cdp_emailExists($_POST['email'])) $error = $lang['messagesform78'];
if (!$user->cdp_isValidEmail($_POST['email'])) $error = $lang['messagesform77'];
if (empty($_POST['pass'])) $error = $lang['messagesform76'];
if (strlen($_POST['pass']) < 8) $error = $lang['messagesform75'];
if ($_POST['pass'] != $_POST['pass2']) $error = $lang['messagesform74'];

if (empty($error)) {
    $settings = cdp_getSettingsCourier();
    $prefixlk = $settings->prefix_locker;

    $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Temp folder — files live here until OTP is verified, then get moved to the real folder.
    // A cron job can periodically purge sub-folders older than e.g. 1 hour.
    $tempToken = bin2hex(random_bytes(16)); // unique folder name per registration attempt
    $tempDir   = '../assets/uploads/tmp/' . $tempToken . '/';
    mkdir($tempDir, 0755, true);

    $avatarTempName = '';
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['avatar']['size'] > MAX_UPLOAD_BYTES) {
            echo json_encode(['success' => false, 'errors' => 'Avatar image must be under 5MB.']);
            exit;
        }
        $mime = mime_content_type($_FILES['avatar']['tmp_name']);
        $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($mime, $allowedMimeTypes) || !in_array($ext, $allowedExtensions)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid avatar file type.']);
            exit;
        }
        $avatarTempName = 'avatar.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], $tempDir . $avatarTempName);
    }

    $docPhotoTempName = '';
    if (!empty($_FILES['document_photo']['name']) && $_FILES['document_photo']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['document_photo']['size'] > MAX_UPLOAD_BYTES) {
            echo json_encode(['success' => false, 'errors' => 'Document photo must be under 5MB.']);
            exit;
        }
        $mime = mime_content_type($_FILES['document_photo']['tmp_name']);
        $ext  = strtolower(pathinfo($_FILES['document_photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($mime, $allowedMimeTypes) || !in_array($ext, $allowedExtensions)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid document photo file type.']);
            exit;
        }
        $docPhotoTempName = 'document_photo.' . $ext;
        move_uploaded_file($_FILES['document_photo']['tmp_name'], $tempDir . $docPhotoTempName);
    }

    // Only the token goes into the session — no file data, no base64 bloat.
    $_SESSION['pending_signup'] = [
        'username'        => cdp_sanitize($_POST['username']),
        'email'           => cdp_sanitize($_POST['email']),
        'lname'           => cdp_sanitize($_POST['lname']),
        'fname'           => cdp_sanitize($_POST['fname']),
        'document_number' => cdp_sanitize($_POST['document_number']),
        'document_type'   => cdp_sanitize($_POST['document_type']),
        'locker'          => cdp_sanitize($prefixlk . ' ' . $_POST['locker']),
        'phone'           => cdp_sanitize($_POST['phone']),
        'password'        => password_hash($_POST['pass'], PASSWORD_DEFAULT),
        'terms'           => isset($_POST['terms']) ? $_POST['terms'] : '',
        'created'         => date("Y-m-d H:i:s"),
        'temp_token'      => $tempToken,       // used to locate temp files
        'avatar_tmp'      => $avatarTempName,  // filename inside temp folder, empty if not uploaded
        'document_photo_tmp' => $docPhotoTempName,
        // address fields
        'address'         => cdp_sanitize($_POST['address']),
        'country'         => cdp_sanitize($_POST['country']),
        'city'            => cdp_sanitize($_POST['city']),
        'state'           => cdp_sanitize($_POST['state']),
        'postal'          => cdp_sanitize($_POST['postal']),
    ];

    // user_id=0 placeholder — real row doesn't exist yet.
    $challenge = $otp->createChallenge(0, 'signup', ['email' => $_SESSION['pending_signup']['email']]);
    $otp->sendOtpEmail(
        $_SESSION['pending_signup']['email'],
        $_SESSION['pending_signup']['fname'] . ' ' . $_SESSION['pending_signup']['lname'],
        $challenge['code'],
        'signup'
    );
    $_SESSION['otp_signup_challenge'] = $challenge['id'];

    echo json_encode([
        'success'  => true,
        'messages' => 'Success! Verify your email to complete your registration.',
        'redirect' => 'auth-otp.php?flow=signup'
    ]);

    exit;
}

echo json_encode([
    'success' => false,
    'errors'  => $error
]);