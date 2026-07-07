<?php
// Add/remove one user to/from a department (instant).
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$deptId = intval($_POST['department_id'] ?? 0);
$userId = intval($_POST['user_id'] ?? 0);
$member = intval($_POST['member'] ?? 0); // 1 add, 0 remove

if ($deptId < 1 || $userId < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

// Department must exist.
$db->cdp_query("SELECT id FROM cdb_departments WHERE id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
if (!$db->cdp_registro()) {
    echo json_encode(['status' => 'error', 'message' => 'Department not found.']);
    exit;
}

try {
    if ($member === 1) {
        $db->cdp_query("INSERT IGNORE INTO cdb_department_members (department_id, user_id) VALUES (:d, :u)");
    } else {
        $db->cdp_query("DELETE FROM cdb_department_members WHERE department_id = :d AND user_id = :u");
    }
    $db->bind(':d', $deptId);
    $db->bind(':u', $userId);
    $db->cdp_execute();
    echo json_encode(['status' => 'success', 'member' => $member]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Save failed.']);
}
