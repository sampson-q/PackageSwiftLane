<?php
// ============================================================================
// Effective permissions for a role — everything the role is ALLOWED to do,
// resolved through its parent-role chain (nearest-wins), grouped by module.
// This is the "what can they actually do" view, in addition to the per-module
// toggle cards (which only show the role's OWN explicit grants, not inherited).
// ============================================================================
require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../../helpers/rbac.php');
require_login();
require_permission('view_role_assignment');

header('Content-Type: text/html; charset=UTF-8');

$roleId = (int) ($_REQUEST['role_id'] ?? 0);
if ($roleId <= 0) {
    echo '<div class="text-muted">No role specified.</div>';
    exit;
}

$db = new Conexion;
$db->cdp_query("SELECT role_name FROM cdb_user_roles WHERE role_id = :r LIMIT 1");
$db->bind(':r', $roleId);
$db->cdp_execute();
$roleRow = $db->cdp_registro();
$roleName = $roleRow ? (string) $roleRow->role_name : ('Role ' . $roleId);

$eff = cdp_resolveRolePermissions($roleId);

if (!empty($eff['is_wildcard'])) {
    echo '<div class="alert alert-success mb-0"><b>' . htmlspecialchars($roleName) . '</b> is a super-admin role — it is allowed to do <b>everything</b> (wildcard).</div>';
    exit;
}

$allowed = $eff['allowed'];
if (!$allowed) {
    echo '<div class="alert alert-warning mb-0"><b>' . htmlspecialchars($roleName) . '</b> has no permissions granted — it cannot do anything yet.</div>';
    exit;
}

// Group the allowed actions by module (label = module_name/description).
$in = implode(',', array_map(function ($a) { return "'" . addslashes($a) . "'"; }, $allowed));
$db->cdp_query("SELECT a.action_name, a.description_module,
                       COALESCE(NULLIF(m.description,''), m.module_name, CONCAT('Module ', a.module_id)) AS module_label
                FROM cdb_user_module_actions a
                LEFT JOIN cdb_user_module_permissions m ON m.id = a.module_id
                WHERE a.action_name IN ($in)
                ORDER BY module_label, a.action_name");
$db->cdp_execute();
$rows = $db->cdp_registros() ?: [];

$groups = [];
foreach ($rows as $r) {
    $groups[(string) $r->module_label][] = $r;
}
ksort($groups);
?>
<div class="mb-2">
    <span class="badge badge-success p-2"><?php echo count($allowed); ?> permission(s) allowed</span>
    <span class="text-muted small ml-2">Includes permissions inherited from parent roles.</span>
</div>
<div class="row">
    <?php foreach ($groups as $label => $acts): ?>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body p-2">
                <h6 class="mb-2"><i class="mdi mdi-folder-outline text-primary"></i> <?php echo htmlspecialchars((string) $label); ?>
                    <span class="badge badge-light float-right"><?php echo count($acts); ?></span></h6>
                <ul class="list-unstyled mb-0" style="font-size:12.5px;">
                    <?php foreach ($acts as $a): ?>
                    <li class="py-1 border-bottom">
                        <i class="mdi mdi-check text-success"></i>
                        <?php echo htmlspecialchars(($a->description_module !== '' && $a->description_module !== null) ? $a->description_module : $a->action_name); ?>
                        <br><small class="text-muted" style="font-family:monospace;"><?php echo htmlspecialchars($a->action_name); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
