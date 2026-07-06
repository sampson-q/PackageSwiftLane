<?php
// *************************************************************************
// * Save per-user permission overrides (allow/deny on top of the role).  *
// *************************************************************************

ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/rbac.php');
require_login();
require_permission('edit_user');

$db = new Conexion;

$target_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
// Delta payload: { action_id => 'allow' | 'deny' | 'inherit' }.
// Applied incrementally (does NOT touch other overrides), so this endpoint
// serves both instant single-change saves and multi-select bulk applies.
$changes = $_POST['changes'] ?? [];
if (!is_array($changes)) { $changes = []; }

if ($target_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user.']);
    exit;
}

// Target must exist and be of strictly lower rank than the editor (superadmin: any).
$db->cdp_query("SELECT id, userlevel FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $target_id);
$db->cdp_execute();
$target = $db->cdp_registro();
if (!$target) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}
if (!cdp_canManageUser($user, (int)$target->userlevel)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No permission to manage this user.']);
    exit;
}

if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

// Valid action ids from the catalog (ignore anything not real).
$db->cdp_query("SELECT id FROM cdb_user_module_actions");
$db->cdp_execute();
$validRows = $db->cdp_registros() ?: [];
$valid = [];
foreach ($validRows as $r) { $valid[(int)$r->id] = true; }

try {
    $applied = [];
    foreach ($changes as $actionId => $state) {
        $actionId = (int)$actionId;
        if (!isset($valid[$actionId])) { continue; }
        $state = strtolower((string)$state);

        if ($state === 'inherit') {
            $db->cdp_query("DELETE FROM cdb_user_permission_overrides WHERE user_id = :uid AND module_action_id = :aid");
            $db->bind(':uid', $target_id);
            $db->bind(':aid', $actionId);
            $db->cdp_execute();
            $applied[$actionId] = 'inherit';
        } elseif ($state === 'allow' || $state === 'deny') {
            $permitted = ($state === 'allow') ? 1 : 0;
            $db->cdp_query("INSERT INTO cdb_user_permission_overrides (user_id, module_action_id, permitted)
                            VALUES (:uid, :aid, :p)
                            ON DUPLICATE KEY UPDATE permitted = VALUES(permitted)");
            $db->bind(':uid', $target_id);
            $db->bind(':aid', $actionId);
            $db->bind(':p', $permitted);
            $db->cdp_execute();
            $applied[$actionId] = $state;
        }
    }

    echo json_encode(['status' => 'success', 'applied' => $applied, 'count' => count($applied)]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Save failed.']);
}
