<?php
require_once('helpers/querys.php');
require_once('helpers/rbac.php');

$db = new Conexion;

$target_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
if (!$target_id) {
    cdp_redirect_to("users_list.php");
    exit;
}

// Target must exist.
$db->cdp_query("SELECT id, username, fname, lname, userlevel FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $target_id);
$db->cdp_execute();
$target = $db->cdp_registro();
if (!$target) {
    cdp_redirect_to("users_list.php");
    exit;
}

// Rank gate: only accounts of strictly lower rank than the viewer (superadmin: any).
if (!cdp_canManageUser($user, (int)$target->userlevel)) {
    cdp_redirect_to("users_list.php");
    exit;
}

$userData = $user->cdp_getUserData();

// Effective role permissions for the target (parent chain resolved, NO overrides)
// = the "inherited" baseline shown per action.
$targetRoleUser = new User();
$targetRoleUser->userlevel = (int)$target->userlevel;
$targetRoleUser->uid = -1; // no user → override table returns nothing
$rolePerms = array_flip($targetRoleUser->cdp_getUserPermissions());

// Current explicit overrides for this user.
$db->cdp_query("SELECT module_action_id, permitted FROM cdb_user_permission_overrides WHERE user_id = :uid");
$db->bind(':uid', $target_id);
$db->cdp_execute();
$ovRows = $db->cdp_registros() ?: [];
$overrides = [];
foreach ($ovRows as $r) { $overrides[(int)$r->module_action_id] = (int)$r->permitted; }

// Catalog grouped by module.
$db->cdp_query("
    SELECT m.id AS module_id, m.module_name, a.id AS action_id, a.action_name, a.description_module
    FROM cdb_user_module_permissions m
    JOIN cdb_user_module_actions a ON a.module_id = m.id
    ORDER BY m.module_name, a.action_name
");
$db->cdp_execute();
$catalog = $db->cdp_registros() ?: [];
$byModule = [];
foreach ($catalog as $c) { $byModule[$c->module_name][] = $c; }

$target_name = trim(($target->fname ?? '') . ' ' . ($target->lname ?? ''));
if ($target_name === '') { $target_name = $target->username; }
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Permissions | <?php echo $core->site_name ?></title>
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .perm-search-bar { margin-bottom:14px; }
        .perm-search-bar .form-control { height:36px; font-size:13px; }
        .perm-mod-card { margin-bottom: 18px; }
        .perm-card-head { display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #f0f0f0; padding-bottom:6px; margin-bottom:6px; gap:8px; }
        .perm-card-head h5 { margin:0; font-size:15px; }
        .perm-card-selall { font-size:11px; color:#9aa0ac; cursor:pointer; white-space:nowrap; margin:0; }
        .perm-card-selall input { vertical-align:middle; }
        /* Per-card bulk bar — shows only when rows in THIS card are selected. */
        .perm-card-bulk { display:none; align-items:center; gap:6px; background:#eef2fb; border:1px solid #cdd8f3; border-radius:6px; padding:5px 8px; margin-bottom:8px; font-size:12px; }
        .perm-card-bulk.show { display:flex; }
        .perm-card-bulk .btn { padding:2px 8px; font-size:11px; }
        /* Fixed card height: list scrolls internally when actions overflow. */
        .perm-card-actions { max-height:300px; overflow-y:auto; padding-right:4px; }
        .perm-row { display:flex; align-items:center; justify-content:space-between; padding:6px 4px; border-bottom:1px solid #f0f0f0; gap:8px; }
        .perm-row:last-child { border-bottom:none; }
        .perm-row.hidden { display:none; }
        .perm-row .row-check { flex:0 0 auto; margin:0 2px 0 0; }
        .perm-label { font-size:13px; color:#333; flex:1 1 auto; min-width:0; }
        .perm-label small { color:#9aa0ac; display:block; word-break:break-all; }
        .perm-choice { display:flex; gap:4px; flex:0 0 auto; }
        .perm-choice label { font-size:11px; margin:0; padding:3px 8px; border:1px solid #d9d9d9; border-radius:4px; cursor:pointer; background:#fff; }
        .perm-choice input { display:none; }
        .perm-choice input:checked + span { font-weight:600; }
        .perm-choice label.inh input:checked ~ span { color:#5b6b8c; }
        .perm-choice label.allow input:checked ~ span { color:#1a8a3a; }
        .perm-choice label.deny input:checked ~ span { color:#c0392b; }
        .perm-choice label:has(input:checked) { background:#eef2fb; border-color:#336aea; }
        .perm-badge { font-size:10px; padding:1px 6px; border-radius:8px; margin-left:6px; }
        .perm-badge.on { background:#e5f6ea; color:#1a8a3a; }
        .perm-badge.off { background:#fbeaea; color:#c0392b; }
        .perm-row.saving { opacity:.55; }
        /* Kill the SweetAlert backdrop entirely — the toast must never cover or
           block the page. Container is click-through; only the toast catches clicks. */
        .swal2-container { background:transparent !important; pointer-events:none !important; }
        .swal2-container .swal2-toast { pointer-events:auto !important; }
    </style>
</head>
<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>
        <div class="page-wrapper">
            <div class="container-fluid mb-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center justify-content-between">
                                    <div>
                                        <h3 class="card-title">Permissions &mdash; <?php echo htmlspecialchars($target_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="text-muted mb-0" style="font-size:13px;">
                                            Role: <strong><?php echo htmlspecialchars(isset($lang['role_'.$target->userlevel]) ? $lang['role_'.$target->userlevel] : (string)$target->userlevel, ENT_QUOTES, 'UTF-8'); ?></strong>.
                                            <em>Inherit</em> follows the role. <em>Allow</em>/<em>Deny</em> override it for this person only.
                                        </p>
                                    </div>
                                    <a href="users_list.php" class="btn btn-outline-secondary btn-sm"><i class="ti-arrow-left"></i> Back</a>
                                </div>
                                <hr>
                                <div id="user_perms" data-user-id="<?php echo $target_id; ?>">
                                    <div class="perm-search-bar row align-items-center">
                                        <div class="col-md-7">
                                            <input type="text" id="perm_search" class="form-control" placeholder="Filter permissions by name, description or module…" autocomplete="off">
                                        </div>
                                        <div class="col-md-5 text-right" style="font-size:12px;color:#9aa0ac;">
                                            <span id="perm_visible_count">0</span> shown &middot; changes save instantly
                                        </div>
                                    </div>

                                    <div class="row">
                                    <?php foreach ($byModule as $moduleName => $actions): ?>
                                        <div class="col-md-6 perm-mod-group" data-module="<?php echo htmlspecialchars(strtolower($moduleName ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="card perm-mod-card">
                                                <div class="card-body">
                                                    <div class="perm-card-head">
                                                        <h5><?php echo htmlspecialchars($moduleName ?? 'Module', ENT_QUOTES, 'UTF-8'); ?></h5>
                                                        <label class="perm-card-selall"><input type="checkbox" class="card-select-all"> select all</label>
                                                    </div>
                                                    <div class="perm-card-bulk">
                                                        <span><strong class="card-sel-count">0</strong> selected:</span>
                                                        <button type="button" class="btn btn-outline-success" data-bulk="allow">Allow</button>
                                                        <button type="button" class="btn btn-outline-danger" data-bulk="deny">Deny</button>
                                                        <button type="button" class="btn btn-outline-secondary" data-bulk="inherit">Inherit</button>
                                                        <button type="button" class="btn btn-link card-sel-clear" style="padding:2px 4px;">clear</button>
                                                    </div>
                                                    <div class="perm-card-actions">
                                                        <?php foreach ($actions as $a):
                                                            $aid = (int)$a->action_id;
                                                            $inheritedOn = isset($rolePerms[$a->action_name]);
                                                            $state = array_key_exists($aid, $overrides) ? ($overrides[$aid] === 1 ? 'allow' : 'deny') : 'inherit';
                                                            $label = $a->description_module ?: $a->action_name;
                                                            $haystack = strtolower(($label) . ' ' . $a->action_name . ' ' . $moduleName);
                                                            // Badge shows the EFFECTIVE result for this person, and updates live:
                                                            //   allow -> allowed | deny -> denied | inherit -> role baseline.
                                                            if ($state === 'allow')      { $bClass='on';  $bText='allowed'; }
                                                            elseif ($state === 'deny')   { $bClass='off'; $bText='denied'; }
                                                            else                         { $bClass=$inheritedOn?'on':'off'; $bText=$inheritedOn?'role: allowed':'role: none'; }
                                                        ?>
                                                            <div class="perm-row" data-aid="<?php echo $aid; ?>" data-role-on="<?php echo $inheritedOn ? 1 : 0; ?>" data-search="<?php echo htmlspecialchars($haystack, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="checkbox" class="row-check" title="Select for bulk action">
                                                                <span class="perm-label">
                                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                    <small><?php echo htmlspecialchars($a->action_name, ENT_QUOTES, 'UTF-8'); ?>
                                                                        <span class="perm-badge <?php echo $bClass; ?>"><?php echo $bText; ?></span>
                                                                    </small>
                                                                </span>
                                                                <span class="perm-choice">
                                                                    <label class="inh"><input type="radio" name="ov[<?php echo $aid; ?>]" value="inherit" <?php echo $state==='inherit'?'checked':''; ?>><span>Inherit</span></label>
                                                                    <label class="allow"><input type="radio" name="ov[<?php echo $aid; ?>]" value="allow" <?php echo $state==='allow'?'checked':''; ?>><span>Allow</span></label>
                                                                    <label class="deny"><input type="radio" name="ov[<?php echo $aid; ?>]" value="deny" <?php echo $state==='deny'?'checked':''; ?>><span>Deny</span></label>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                    <div id="perm_no_results" style="display:none;padding:20px;text-align:center;color:#9aa0ac;">No permissions match your filter.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>
    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="<?= cdp_asset('dataJs/user_permissions.js') ?>"></script>
</body>
</html>
