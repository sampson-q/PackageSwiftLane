<?php
// Render the base-role users for a department, with membership state.
ini_set('display_errors', 0);

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$deptId = intval($_REQUEST['department_id'] ?? 0);
if ($deptId < 1) { echo '<div class="text-danger">Invalid department.</div>'; exit; }

// Base role of this department.
$db->cdp_query("SELECT base_role_id FROM cdb_departments WHERE id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
$dept = $db->cdp_registro();
if (!$dept) { echo '<div class="text-danger">Department not found.</div>'; exit; }
$baseRole = (int)$dept->base_role_id;

// Current members (any user, in case a user's base role changed later).
$db->cdp_query("SELECT user_id FROM cdb_department_members WHERE department_id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
$memberIds = [];
foreach ($db->cdp_registros() ?: [] as $m) { $memberIds[(int)$m->user_id] = true; }

// Candidate users = users whose base role matches, PLUS any existing members.
$db->cdp_query("
    SELECT id, username, fname, lname, email, userlevel
    FROM cdb_users
    WHERE userlevel = :r
       OR id IN (SELECT user_id FROM cdb_department_members WHERE department_id = :id2)
    ORDER BY fname, lname, username
");
$db->bind(':r', $baseRole);
$db->bind(':id2', $deptId);
$db->cdp_execute();
$users = $db->cdp_registros() ?: [];

if (!$users) {
    echo '<div class="text-muted">No users have this base role yet.</div>';
    exit;
}
foreach ($users as $u) {
    $name = trim(($u->fname ?? '') . ' ' . ($u->lname ?? ''));
    if ($name === '') { $name = $u->username; }
    $checked = isset($memberIds[(int)$u->id]) ? 'checked' : '';
    $hay = strtolower($name . ' ' . $u->username . ' ' . $u->email);
    ?>
    <div class="mem-row" data-search="<?php echo htmlspecialchars($hay, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="checkbox" class="mem-check" data-uid="<?php echo (int)$u->id; ?>" <?php echo $checked; ?>>
        <div>
            <div style="font-size:13px;"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
            <small><?php echo htmlspecialchars($u->username . ' · ' . $u->email, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="ml-auto" style="margin-left:auto;">
            <a href="user_permissions.php?user=<?php echo (int)$u->id; ?>" target="_blank" class="btn btn-link btn-sm" style="font-size:11px;">Individual Permissions ↗</a>
        </div>
    </div>
    <?php
}
