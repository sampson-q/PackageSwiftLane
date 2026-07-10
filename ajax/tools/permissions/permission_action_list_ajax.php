<?php
// List all permission definitions grouped by module, with how many roles /
// departments currently grant each (usage), for the Manage Permissions screen.
ini_set('display_errors', 0);

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;

$db->cdp_query("
    SELECT m.id AS module_id, m.module_name, a.id AS action_id, a.action_name, a.description_module,
           (SELECT COUNT(*) FROM cdb_user_role_permissions rp WHERE rp.module_action_id = a.id AND rp.permitted = 1) AS role_uses,
           (SELECT COUNT(*) FROM cdb_department_permissions dp WHERE dp.module_action_id = a.id AND dp.permitted = 1) AS dept_uses
    FROM cdb_user_module_actions a
    JOIN cdb_user_module_permissions m ON m.id = a.module_id
    ORDER BY m.module_name, a.action_name
");
$db->cdp_execute();
$rows = $db->cdp_registros() ?: [];

$byModule = [];
foreach ($rows as $r) { $byModule[$r->module_name][] = $r; }

if (!$rows) { echo '<div class="text-muted p-3">No permissions defined.</div>'; exit; }

foreach ($byModule as $moduleName => $actions) { ?>
    <div class="pa-mod mb-2">
        <h6 style="border-bottom:2px solid #f0f0f0;padding-bottom:4px;margin-top:12px;">
            <?php echo htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?>
            <span class="text-muted" style="font-weight:400;font-size:12px;">(<?php echo count($actions); ?>)</span>
        </h6>
        <?php foreach ($actions as $a):
            $label = $a->description_module ?: $a->action_name;
            $uses  = (int) $a->role_uses + (int) $a->dept_uses;
            $hay   = strtolower($label . ' ' . $a->action_name . ' ' . $moduleName);
        ?>
        <div class="pa-row d-flex justify-content-between align-items-center py-1"
             data-search="<?php echo htmlspecialchars($hay, ENT_QUOTES, 'UTF-8'); ?>"
             data-id="<?php echo (int) $a->action_id; ?>"
             data-name="<?php echo htmlspecialchars($a->action_name, ENT_QUOTES, 'UTF-8'); ?>"
             data-module="<?php echo (int) $a->module_id; ?>"
             data-desc="<?php echo htmlspecialchars((string) $a->description_module, ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <div><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                <small class="text-muted"><code><?php echo htmlspecialchars($a->action_name, ENT_QUOTES, 'UTF-8'); ?></code>
                    &middot; assigned in <?php echo $uses; ?> role(s)/dept(s)</small>
            </div>
            <div class="text-right" style="white-space:nowrap;">
                <button class="btn btn-outline-secondary btn-sm pa-edit">Edit</button>
                <button class="btn btn-outline-danger btn-sm pa-delete">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php }
