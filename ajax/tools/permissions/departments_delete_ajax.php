<?php
// Delete a department (and its members + permission rows).
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$id = intval($_POST['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid department.']);
    exit;
}
if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

try {
    $db->cdp_query("DELETE FROM cdb_department_members WHERE department_id = :id");
    $db->bind(':id', $id); $db->cdp_execute();
    $db->cdp_query("DELETE FROM cdb_department_permissions WHERE department_id = :id");
    $db->bind(':id', $id); $db->cdp_execute();
    $db->cdp_query("DELETE FROM cdb_departments WHERE id = :id");
    $db->bind(':id', $id); $db->cdp_execute();
    echo json_encode(['status' => 'success', 'message' => 'Department deleted.']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed.']);
}
