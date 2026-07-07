<?php
require_once('helpers/querys.php');
$db = new Conexion;

// Active roles usable as a department's base role.
$db->cdp_query("SELECT role_id, role_name FROM cdb_user_roles WHERE rol_active = 1 ORDER BY role_name");
$db->cdp_execute();
$roles = $db->cdp_registros() ?: [];

// Departments with counts.
$db->cdp_query("
    SELECT d.id, d.name, d.description, d.base_role_id, r.role_name AS base_role_name,
           (SELECT COUNT(*) FROM cdb_department_members m WHERE m.department_id = d.id) AS member_count,
           (SELECT COUNT(*) FROM cdb_department_permissions p WHERE p.department_id = d.id AND p.permitted = 1) AS allow_count,
           (SELECT COUNT(*) FROM cdb_department_permissions p WHERE p.department_id = d.id AND p.permitted = 0) AS deny_count
    FROM cdb_departments d
    LEFT JOIN cdb_user_roles r ON r.role_id = d.base_role_id
    ORDER BY d.name
");
$db->cdp_execute();
$departments = $db->cdp_registros() ?: [];
$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Departments | <?php echo $core->site_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .dept-card { border:1px solid #e6e6e6; border-radius:8px; padding:14px 16px; margin-bottom:14px; }
        .dept-card h5 { margin:0 0 2px; }
        .dept-meta { font-size:12px; color:#9aa0ac; }
        .dept-pill { font-size:11px; padding:2px 8px; border-radius:10px; background:#eef2fb; color:#336aea; margin-right:6px; }
        .dept-actions .btn { font-size:12px; }
        /* tri-state grid (reused for dept permission set + member list) */
        .d-modal-scroll { max-height:60vh; overflow-y:auto; }
        .perm-row { display:flex; align-items:center; justify-content:space-between; padding:6px 4px; border-bottom:1px solid #f0f0f0; gap:8px; }
        .perm-row:last-child { border-bottom:none; }
        .perm-row.hidden { display:none; }
        .perm-label { font-size:13px; flex:1 1 auto; min-width:0; }
        .perm-label small { color:#9aa0ac; display:block; word-break:break-all; }
        .perm-choice { display:flex; gap:4px; flex:0 0 auto; }
        .perm-choice label { font-size:11px; margin:0; padding:3px 8px; border:1px solid #d9d9d9; border-radius:4px; cursor:pointer; background:#fff; }
        .perm-choice input { display:none; }
        .perm-choice label:has(input:checked) { background:#eef2fb; border-color:#336aea; font-weight:600; }
        .perm-choice label.allow:has(input:checked) { color:#1a8a3a; }
        .perm-choice label.deny:has(input:checked) { color:#c0392b; }
        .mem-row { display:flex; align-items:center; gap:10px; padding:6px 4px; border-bottom:1px solid #f0f0f0; }
        .mem-row.hidden { display:none; }
        .mem-row small { color:#9aa0ac; }
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
                    <!-- Create department -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">New Department</h4>
                                <p class="text-muted" style="font-size:13px;">Built on a base role. Members get the role's permissions plus whatever you allow/deny for the department, then per-person tweaks.</p>
                                <form id="dept_create_form">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-2">
                                                <label>Department Name</label>
                                                <input type="text" class="form-control" name="name" id="dept_name" placeholder="e.g. Finance" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-2">
                                                <label>Base Role</label>
                                                <select class="form-control" name="base_role_id" id="dept_base_role" required>
                                                    <option value="">— Select a Role —</option>
                                                    <?php foreach ($roles as $r): ?>
                                                        <option value="<?php echo (int)$r->role_id; ?>"><?php echo htmlspecialchars(isset($lang['role_'.$r->role_id]) ? $lang['role_'.$r->role_id] : $r->role_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-2">
                                                <label>Description <small class="text-muted">(Optional)</small></label>
                                                <input type="text" class="form-control" name="description" id="dept_desc">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Create Department</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Department list -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Departments <span class="text-muted" style="font-size:13px;">(<span id="dept_count"><?php echo count($departments); ?></span>)</span></h4>
                                <p id="dept_empty" class="text-muted" style="<?php echo $departments ? 'display:none;' : ''; ?>">No departments yet. Create one above.</p>
                                <div id="dept_list">
                                    <?php foreach ($departments as $d): ?>
                                        <div class="dept-card" data-dept-id="<?php echo (int)$d->id; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h5><?php echo htmlspecialchars($d->name, ENT_QUOTES, 'UTF-8'); ?></h5>
                                                    <div class="dept-meta">
                                                        Base Role: <strong><?php echo htmlspecialchars($d->base_role_name ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php if ($d->description): ?> &middot; <?php echo htmlspecialchars($d->description, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                    </div>
                                                    <div class="mt-2">
                                                        <span class="dept-pill"><span class="dept-member-count"><?php echo (int)$d->member_count; ?></span> Members</span>
                                                        <span class="dept-pill" style="background:#e5f6ea;color:#1a8a3a;"><span class="dept-allow-count"><?php echo (int)$d->allow_count; ?></span> Allow</span>
                                                        <span class="dept-pill dept-deny-pill" style="background:#fbeaea;color:#c0392b;<?php echo (int)$d->deny_count ? '' : 'display:none;'; ?>"><span class="dept-deny-count"><?php echo (int)$d->deny_count; ?></span> Deny</span>
                                                    </div>
                                                </div>
                                                <div class="dept-actions text-right">
                                                    <button class="btn btn-outline-primary btn-sm dept-members" data-id="<?php echo (int)$d->id; ?>" data-name="<?php echo htmlspecialchars($d->name, ENT_QUOTES, 'UTF-8'); ?>" data-role="<?php echo (int)$d->base_role_id; ?>">Members</button>
                                                    <button class="btn btn-outline-secondary btn-sm dept-perms" data-id="<?php echo (int)$d->id; ?>" data-name="<?php echo htmlspecialchars($d->name, ENT_QUOTES, 'UTF-8'); ?>">Permissions</button>
                                                    <button class="btn btn-outline-danger btn-sm dept-delete" data-id="<?php echo (int)$d->id; ?>" data-name="<?php echo htmlspecialchars($d->name, ENT_QUOTES, 'UTF-8'); ?>">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- Members modal -->
    <div class="modal fade" id="deptMembersModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Members — <span id="mem_dept_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-2" id="mem_search" placeholder="Search Users…">
                    <div class="text-muted mb-2" style="font-size:12px;">Users with the base role. Tick to add them to this department (saves instantly).</div>
                    <div id="mem_body" class="d-modal-scroll"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions modal -->
    <div class="modal fade" id="deptPermsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Permissions — <span id="perm_dept_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-2" id="dperm_search" placeholder="Filter Permissions…">
                    <div class="text-muted mb-2" style="font-size:12px;">Allow = grant to all members. Deny = remove from all members (even if the role grants it). None = department has no opinion. Saves instantly.</div>
                    <div id="dperm_body" class="d-modal-scroll"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="<?= cdp_asset('dataJs/departments.js') ?>"></script>
</body>
</html>
