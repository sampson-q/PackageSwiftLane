<?php
// Delta-save a department's permission set. Payload: changes[aid]=allow|deny|none.
// none = remove the row (department has no opinion); allow=1; deny=0.
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$deptId = intval($_POST['department_id'] ?? 0);
$changes = $_POST['changes'] ?? [];
if (!is_array($changes)) { $changes = []; }

if ($deptId < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid department.']);
    exit;
}
if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

$db->cdp_query("SELECT id FROM cdb_departments WHERE id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
if (!$db->cdp_registro()) {
    echo json_encode(['status' => 'error', 'message' => 'Department not found.']);
    exit;
}

// Valid action ids.
$db->cdp_query("SELECT id FROM cdb_user_module_actions");
$db->cdp_execute();
$valid = [];
foreach ($db->cdp_registros() ?: [] as $r) { $valid[(int)$r->id] = true; }

try {
    $applied = [];
    foreach ($changes as $aid => $state) {
        $aid = (int)$aid;
        if (!isset($valid[$aid])) { continue; }
        $state = strtolower((string)$state);
        if ($state === 'none') {
            $db->cdp_query("DELETE FROM cdb_department_permissions WHERE department_id = :d AND module_action_id = :a");
            $db->bind(':d', $deptId); $db->bind(':a', $aid); $db->cdp_execute();
            $applied[$aid] = 'none';
        } elseif ($state === 'allow' || $state === 'deny') {
            $p = ($state === 'allow') ? 1 : 0;
            $db->cdp_query("INSERT INTO cdb_department_permissions (department_id, module_action_id, permitted)
                            VALUES (:d, :a, :p) ON DUPLICATE KEY UPDATE permitted = VALUES(permitted)");
            $db->bind(':d', $deptId); $db->bind(':a', $aid); $db->bind(':p', $p); $db->cdp_execute();
            $applied[$aid] = $state;
        }
    }
    echo json_encode(['status' => 'success', 'applied' => $applied, 'count' => count($applied)]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Save failed.']);
}
