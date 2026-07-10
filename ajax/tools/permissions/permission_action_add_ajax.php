<?php
// Add a new permission (action) definition to cdb_user_module_actions. Once
// added it appears automatically in every Roles/Departments/User assignment
// grid. NOTE: for it to actually restrict anything a developer must still add a
// require_permission()/cdp_hasPermission() check in code.
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

$db = new Conexion;

$action_name = strtolower(trim((string) ($_POST['action_name'] ?? '')));
$module_id   = (int) ($_POST['module_id'] ?? 0);
$description = trim((string) ($_POST['description_module'] ?? ''));

// action_name is a CODE KEY — enforce a strict slug so it can be used safely in
// cdp_hasPermission('...'): lowercase letter first, then letters/digits/_.
if (!preg_match('/^[a-z][a-z0-9_]{2,49}$/', $action_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Name must be 3–50 chars: lowercase letters, digits and underscores, starting with a letter (e.g. fs_export_pdf).']);
    exit;
}
if ($module_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Pick a module.']);
    exit;
}
if ($description === '') {
    echo json_encode(['status' => 'error', 'message' => 'Add a short description.']);
    exit;
}

// Module must exist.
$db->cdp_query("SELECT module_name FROM cdb_user_module_permissions WHERE id = :id");
$db->bind(':id', $module_id); $db->cdp_execute();
$mod = $db->cdp_registro();
if (!$mod) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown module.']);
    exit;
}

// Unique action_name.
$db->cdp_query("SELECT id FROM cdb_user_module_actions WHERE action_name = :a");
$db->bind(':a', $action_name); $db->cdp_execute();
if ($db->cdp_registro()) {
    echo json_encode(['status' => 'error', 'message' => 'A permission with that name already exists.']);
    exit;
}

try {
    $db->cdp_query("INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
                    VALUES (:m, :a, :d)");
    $db->bind(':m', $module_id);
    $db->bind(':a', $action_name);
    $db->bind(':d', $description);
    $db->cdp_execute();
    $id = (int) $db->dbh->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Permission added. Assign it in Departments/Roles; a developer must add the code check to enforce it.',
        'action' => [
            'id' => $id,
            'action_name' => $action_name,
            'module_id' => $module_id,
            'module_name' => $mod->module_name,
            'description_module' => $description,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not add the permission.']);
}
