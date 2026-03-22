<?php
require_once("../loader.php");
require_once("../helpers/querys.php");

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$userData = $user->cdp_getUserData();
$db = new Conexion;

$document_type   = cdp_sanitize($_POST['document_type'] ?? '');
$document_number = cdp_sanitize($_POST['document_number'] ?? '');

if ($document_type === '' || $document_number === '') {
    echo json_encode([
        "status" => "error",
        "message" => "All document fields are required."
    ]);
    exit;
}

$documentPhotoPath = null;

/* Optional upload */
if (
    isset($_FILES['document_photo']) &&
    !empty($_FILES['document_photo']['name']) &&
    is_uploaded_file($_FILES['document_photo']['tmp_name'])
) {
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    $tmpPath = $_FILES['document_photo']['tmp_name'];
    $mime    = mime_content_type($tmpPath);

    if (!isset($allowedMimeTypes[$mime])) {
        echo json_encode([
            "status" => "error",
            "message" => "Only image files are allowed for document upload."
        ]);
        exit;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext       = $allowedMimeTypes[$mime];
    $finalName = 'document_' . uniqid() . '.' . $ext;
    $finalPath  = $uploadDir . $finalName;

    if (!move_uploaded_file($tmpPath, $finalPath)) {
        echo json_encode([
            "status" => "error",
            "message" => "Could not upload document image."
        ]);
        exit;
    }

    $documentPhotoPath = '../assets/uploads/users/' . $finalName;
}

if ($documentPhotoPath !== null) {
    $db->cdp_query("UPDATE cdb_users SET document_type = :document_type, document_number = :document_number, document_photo = :document_photo WHERE id = :user_id");
    $db->bind(':document_photo', $documentPhotoPath);
} else {
    $db->cdp_query("UPDATE cdb_users SET document_type = :document_type, document_number = :document_number WHERE id = :user_id");
}

$db->bind(':document_type', $document_type);
$db->bind(':document_number', $document_number);
$db->bind(':user_id', (int) $userData->id);

if ($db->cdp_execute()) {
    $db->cdp_query("SELECT * FROM cdb_user_details_update_check WHERE user_id = :user_id");
    $db->bind(':user_id', (int) $userData->id);
    $update_info = $db->cdp_registro();

    if ($update_info) {
        $db->cdp_query("UPDATE cdb_user_details_update_check SET update_document = 1 WHERE user_id = :user_id");
        $db->bind(':user_id', (int) $userData->id);
        $db->cdp_execute();
    } else {
        $db->cdp_query("INSERT INTO cdb_user_details_update_check (user_id, update_document) VALUES (:user_id, 1)");
        $db->bind(':user_id', (int) $userData->id);
        $db->cdp_execute();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Document saved successfully."
    ]);
    exit;
}

echo json_encode([
    "status" => "error",
    "message" => "Could not save document."
]);