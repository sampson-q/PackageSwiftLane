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

    // --- Handle optional image uploads ---
    $uploadDir = '../assets/uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $avatarPath = '';
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $mime = mime_content_type($_FILES['avatar']['tmp_name']);
        if (!in_array($mime, $allowedMimeTypes)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid avatar file type.']);
            exit;
        }
        $avatarExt  = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $avatarFile = 'avatar_' . uniqid() . '.' . $avatarExt;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $avatarFile)) {
            $avatarPath = 'assets/uploads/users/' . $avatarFile;
        }
    }

    $documentPhotoPath = '';
    if (!empty($_FILES['document_photo']['name']) && $_FILES['document_photo']['error'] === UPLOAD_ERR_OK) {
        $mime = mime_content_type($_FILES['document_photo']['tmp_name']);
        if (!in_array($mime, $allowedMimeTypes)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid document photo file type.']);
            exit;
        }
        $docExt   = pathinfo($_FILES['document_photo']['name'], PATHINFO_EXTENSION);
        $docFile  = 'docphoto_' . uniqid() . '.' . $docExt;
        if (move_uploaded_file($_FILES['document_photo']['tmp_name'], $uploadDir . $docFile)) {
            $documentPhotoPath = 'assets/uploads/users/' . $docFile;
        }
    }
    // --- End image uploads ---

    $datos = array(
        'username'       => cdp_sanitize($_POST['username']),
        'email'          => cdp_sanitize($_POST['email']),
        'lname'          => cdp_sanitize($_POST['lname']),
        'fname'          => cdp_sanitize($_POST['fname']),
        'document_number'=> cdp_sanitize($_POST['document_number']),
        'document_type'  => cdp_sanitize($_POST['document_type']),
        'locker'         => cdp_sanitize($prefixlk . ' ' . $_POST['locker']),
        'phone'          => cdp_sanitize($_POST['phone']),
        'userlevel'      => 1,
        'active'         => 0,
        'password'       => password_hash($_POST['pass'], PASSWORD_DEFAULT),
        'terms'          => isset($_POST['terms']) ? $_POST['terms'] : '',
        'created'        => date("Y-m-d H:i:s"),
        'avatar'         => $avatarPath,
        'document_photo' => $documentPhotoPath,
    );

    $db->cdp_query('INSERT INTO cdb_users (username,password,locker,userlevel,email,fname,lname,document_number,document_type,created,phone,active,terms,avatar,document_photo)
        VALUES (:username,:password,:locker,:userlevel,:email,:fname,:lname,:document_number,:document_type,:created,:phone,:active,:terms,:avatar,:document_photo)');

    foreach ($datos as $k => $v) {
        $db->bind(':' . $k, $v);
    }

    $insert = $db->cdp_execute();
    $user_created_id = $db->dbh->lastInsertId();

    if ($user_created_id) {
        cdp_insertAddressCustomer(array(
            'user_id' => $user_created_id,
            'address' => cdp_sanitize($_POST["address"]),
            'country' => cdp_sanitize($_POST["country"]),
            'city'    => cdp_sanitize($_POST["city"]),
            'state'   => cdp_sanitize($_POST["state"]),
            'postal'  => cdp_sanitize($_POST["postal"])
        ));

        $challenge = $otp->createChallenge((int)$user_created_id, 'signup', array('email' => $datos['email']));
        $otp->sendOtpEmail($datos['email'], $datos['fname'] . ' ' . $datos['lname'], $challenge['code'], $challenge['expires_at'], 'signup');
        $_SESSION['otp_signup_challenge'] = $challenge['id'];

        echo json_encode([
            'success'  => true,
            'messages' => 'Success! Verify your email to complete your registration.',
            'redirect' => 'auth-otp.php?flow=signup'
        ]);

        exit;
    }

    $error = "An error occurred during the registration process. Contact the administrator ...";
}

echo json_encode([
    'success' => false,
    'errors'  => $error
]);