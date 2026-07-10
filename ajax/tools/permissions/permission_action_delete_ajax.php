<?php
// Delete a permission (action) definition and every assignment of it (role,
// department, per-user override). WARNING surfaced in the UI: if code still
// checks this action_name, that check will then deny everyone (except
// superadmin) — delete only permissions no longer referenced in code.
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
$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid permission.']);
    exit;
}

try {
    // Remove assignments first (no FK cascade in this schema).
    $db->cdp_query("DELETE FROM cdb_user_role_permissions WHERE module_action_id = :id");
    $db->bind(':id', $id); $db->cdp_execute();
    $db->cdp_query("DELETE FROM cdb_department_permissions WHERE module_action_id = :id");
    $db->bind(':id', $id); $db->cdp_execute();
    try { // overrides table may not exist on every env
        $db->cdp_query("DELETE FROM cdb_user_permission_overrides WHERE module_action_id = :id");
        $db->bind(':id', $id); $db->cdp_execute();
    } catch (Throwable $e) { /* ignore */ }

    $db->cdp_query("DELETE FROM cdb_user_module_actions WHERE id = :id");
    $db->bind(':id', $id); $db->cdp_execute();

    echo json_encode(['status' => 'success', 'message' => 'Permission deleted.']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed.']);
}
