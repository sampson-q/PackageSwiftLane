<?php
// Create a department (name + base role + description).
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$name = trim($_POST['name'] ?? '');
$baseRole = intval($_POST['base_role_id'] ?? 0);
$desc = trim($_POST['description'] ?? '');

if ($name === '' || $baseRole < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Name and base role are required.']);
    exit;
}
if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

// Base role must exist and be active.
$db->cdp_query("SELECT role_id FROM cdb_user_roles WHERE role_id = :r AND rol_active = 1");
$db->bind(':r', $baseRole);
$db->cdp_execute();
if (!$db->cdp_registro()) {
    echo json_encode(['status' => 'error', 'message' => 'Base role not found.']);
    exit;
}

// Unique name.
$db->cdp_query("SELECT id FROM cdb_departments WHERE name = :n");
$db->bind(':n', $name);
$db->cdp_execute();
if ($db->cdp_registro()) {
    echo json_encode(['status' => 'error', 'message' => 'A department with that name already exists.']);
    exit;
}

$db->cdp_query("INSERT INTO cdb_departments (name, description, base_role_id) VALUES (:n, :d, :r)");
$db->bind(':n', $name);
$db->bind(':d', $desc !== '' ? $desc : null);
$db->bind(':r', $baseRole);
if ($db->cdp_execute()) {
    $newId = (int)$db->dbh->lastInsertId();
    // Base role display name for the new card.
    $db->cdp_query("SELECT role_name FROM cdb_user_roles WHERE role_id = :r");
    $db->bind(':r', $baseRole);
    $db->cdp_execute();
    $rr = $db->cdp_registro();
    $roleName = $rr ? $rr->role_name : '';
    if (isset($lang['role_' . $baseRole])) { $roleName = $lang['role_' . $baseRole]; }
    echo json_encode([
        'status' => 'success',
        'message' => 'Department created.',
        'department' => [
            'id' => $newId,
            'name' => $name,
            'description' => $desc,
            'base_role_id' => $baseRole,
            'base_role_name' => $roleName,
        ],
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not create department.']);
}
