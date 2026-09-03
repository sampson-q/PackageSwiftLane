<?php
/**
 * ID document (type / number / photo) for a customer profile.
 *
 * The document is OPTIONAL. Any subset of the three fields may be sent; only
 * the fields that were sent are updated. `skip=1` records that the customer
 * chose not to provide one so the onboarding modal stops asking.
 *
 * Used by: the "My Profile" page and the forced account-setup modal. Staff
 * with the edit_client_document permission may update a client's document by
 * passing `id`.
 */
ini_set('display_errors', 0);

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../helpers/profile.php");
require_once("../helpers/ajax_guard.php");
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$db   = new Conexion;

$targetId = (int) ($_POST['id'] ?? $user->uid);
if (!cdp_profileCanEdit($user, $targetId, 'edit_client_document')) {
    echo json_encode(['status' => 'error', 'message' => 'You can only update your own document.']);
    exit;
}

$db->cdp_query("SELECT id, document_type, document_number, document_photo, fname, lname FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $targetId);
$row = $db->cdp_registro();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
    exit;
}

// "Skip for now": nothing to store, just stop the onboarding prompt.
if (!empty($_POST['skip'])) {
    cdp_profileMarkStep($targetId, 'update_document');
    echo json_encode(['status' => 'success', 'message' => 'You can add your ID document later from your profile.', 'skipped' => true]);
    exit;
}

$allowedTypes = ['PSP', 'ECW', 'DNI'];
$type   = isset($_POST['document_type']) ? strtoupper(trim(cdp_sanitize($_POST['document_type']))) : null;
$number = isset($_POST['document_number']) ? trim(cdp_sanitize($_POST['document_number'])) : null;
$hasPhoto = !empty($_FILES['document_photo']['name']) || !empty($_FILES['document']['name']);

if ($type !== null && $type !== '' && !in_array($type, $allowedTypes, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown document type.']);
    exit;
}
if ($number !== null && $number !== '' && mb_strlen($number) < 3) {
    echo json_encode(['status' => 'error', 'message' => 'The document number looks too short.']);
    exit;
}
// A number without a type (or vice-versa) is not a usable document.
$finalType   = ($type === null) ? (string) $row->document_type : $type;
$finalNumber = ($number === null) ? (string) $row->document_number : $number;
if (($finalType === '') !== ($finalNumber === '')) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide both the document type and the document number.']);
    exit;
}

if ($type === null && $number === null && !$hasPhoto) {
    echo json_encode(['status' => 'error', 'message' => 'Nothing to update.']);
    exit;
}

$photoPath = null;
if ($hasPhoto) {
    $file = !empty($_FILES['document_photo']['name']) ? $_FILES['document_photo'] : $_FILES['document'];
    $stored = cdp_profileStoreImage($file, 'users', 'document_' . $targetId);
    if (empty($stored['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $stored['error']]);
        exit;
    }
    $photoPath = $stored['path'];
}

$sql = 'UPDATE cdb_users SET document_type = :t, document_number = :n' . ($photoPath !== null ? ', document_photo = :p' : '') . ' WHERE id = :id';
$db->cdp_query($sql);
$db->bind(':t', $finalType);
$db->bind(':n', $finalNumber);
if ($photoPath !== null) {
    $db->bind(':p', $photoPath);
}
$db->bind(':id', $targetId);

if (!$db->cdp_execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save the document.']);
    exit;
}

cdp_profileMarkStep($targetId, 'update_document');
if ($photoPath !== null) {
    cdp_profileHistoryLog($targetId, (int) $user->uid, (string) $row->document_photo, 'Document updated');
}

if (function_exists('cdp_activityLog')) {
    $changes = [];
    if ((string) $row->document_type !== $finalType)     { $changes['document_type']   = ['from' => (string) $row->document_type,   'to' => $finalType]; }
    if ((string) $row->document_number !== $finalNumber) { $changes['document_number'] = ['from' => (string) $row->document_number, 'to' => $finalNumber]; }
    if ($photoPath !== null)                             { $changes['document_photo']  = ['from' => (string) $row->document_photo,  'to' => $photoPath]; }
    cdp_activityLog([
        'module'       => 'profile',
        'verb'         => 'update',
        'action'       => 'profile.document',
        'label'        => 'Profile · ID Document Updated',
        'entity_type'  => 'user',
        'entity_id'    => $targetId,
        'entity_label' => trim($row->fname . ' ' . $row->lname),
        'summary'      => ($targetId === (int) $user->uid)
            ? 'Updated their own ID document'
            : 'Updated the ID document of customer #' . $targetId,
        'changes'      => $changes,
    ]);
}

echo json_encode([
    'status'       => 'success',
    'message'      => 'Document saved.',
    'document_url' => $photoPath !== null ? cdp_avatarUrl($photoPath, 'uploads/blankID.jpg') : null,
]);
