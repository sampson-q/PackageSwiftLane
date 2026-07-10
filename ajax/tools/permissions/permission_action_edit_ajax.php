<?php
// Edit a permission's module + description. The action_name (the code key) is
// intentionally NOT editable — renaming it would silently break every
// cdp_hasPermission('...') / require_permission('...') check in code.
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
$id          = (int) ($_POST['id'] ?? 0);
$module_id   = (int) ($_POST['module_id'] ?? 0);
$description = trim((string) ($_POST['description_module'] ?? ''));

if ($id < 1 || $module_id < 1 || $description === '') {
    echo json_encode(['status' => 'error', 'message' => 'Module and description are required.']);
    exit;
}

$db->cdp_query("SELECT module_name FROM cdb_user_module_permissions WHERE id = :id");
$db->bind(':id', $module_id); $db->cdp_execute();
$mod = $db->cdp_registro();
if (!$mod) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown module.']);
    exit;
}

try {
    $db->cdp_query("UPDATE cdb_user_module_actions SET module_id = :m, description_module = :d WHERE id = :id");
    $db->bind(':m', $module_id);
    $db->bind(':d', $description);
    $db->bind(':id', $id);
    $db->cdp_execute();
    echo json_encode(['status' => 'success', 'message' => 'Saved.', 'module_name' => $mod->module_name]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save.']);
}
