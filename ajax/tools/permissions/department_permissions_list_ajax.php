<?php
// Render the department's permission grid (Allow / Deny / None per action),
// with the base role's grant shown as a baseline badge.
ini_set('display_errors', 0);

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$deptId = intval($_REQUEST['department_id'] ?? 0);
if ($deptId < 1) { echo '<div class="text-danger">Invalid department.</div>'; exit; }

$db->cdp_query("SELECT base_role_id FROM cdb_departments WHERE id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
$dept = $db->cdp_registro();
if (!$dept) { echo '<div class="text-danger">Department not found.</div>'; exit; }

// Base role's effective permissions (via the role resolver) = the baseline.
$roleUser = new User();
$roleUser->userlevel = (int)$dept->base_role_id;
$roleUser->uid = -1;
$roleGrants = array_flip($roleUser->cdp_getUserPermissions());

// Department's current permission set.
$db->cdp_query("SELECT module_action_id, permitted FROM cdb_department_permissions WHERE department_id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
$deptSet = [];
foreach ($db->cdp_registros() ?: [] as $r) { $deptSet[(int)$r->module_action_id] = (int)$r->permitted; }

// Catalog grouped by module.
$db->cdp_query("
    SELECT m.module_name, a.id AS action_id, a.action_name, a.description_module
    FROM cdb_user_module_permissions m
    JOIN cdb_user_module_actions a ON a.module_id = m.id
    ORDER BY m.module_name, a.action_name
");
$db->cdp_execute();
$rows = $db->cdp_registros() ?: [];
$byModule = [];
foreach ($rows as $r) { $byModule[$r->module_name][] = $r; }

foreach ($byModule as $moduleName => $actions) {
    echo '<div class="dperm-mod"><h6 style="border-bottom:2px solid #f0f0f0;padding-bottom:4px;margin-top:10px;">' . htmlspecialchars($moduleName ?? 'Module', ENT_QUOTES, 'UTF-8') . '</h6>';
    foreach ($actions as $a) {
        $aid = (int)$a->action_id;
        $state = array_key_exists($aid, $deptSet) ? ($deptSet[$aid] === 1 ? 'allow' : 'deny') : 'none';
        $roleOn = isset($roleGrants[$a->action_name]);
        $label = $a->description_module ?: $a->action_name;
        $hay = strtolower($label . ' ' . $a->action_name . ' ' . $moduleName);
        ?>
        <div class="perm-row" data-aid="<?php echo $aid; ?>" data-search="<?php echo htmlspecialchars($hay, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="perm-label">
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                <small><?php echo htmlspecialchars($a->action_name, ENT_QUOTES, 'UTF-8'); ?> ·
                    <?php echo $roleOn ? '<span style="color:#1a8a3a;">role grants</span>' : '<span style="color:#c0392b;">role: none</span>'; ?>
                </small>
            </span>
            <span class="perm-choice">
                <label class="none"><input type="radio" name="dp[<?php echo $aid; ?>]" value="none" <?php echo $state==='none'?'checked':''; ?>><span>None</span></label>
                <label class="allow"><input type="radio" name="dp[<?php echo $aid; ?>]" value="allow" <?php echo $state==='allow'?'checked':''; ?>><span>Allow</span></label>
                <label class="deny"><input type="radio" name="dp[<?php echo $aid; ?>]" value="deny" <?php echo $state==='deny'?'checked':''; ?>><span>Deny</span></label>
            </span>
        </div>
        <?php
    }
    echo '</div>';
}
