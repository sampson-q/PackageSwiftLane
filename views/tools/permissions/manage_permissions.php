<?php
// ============================================================================
// Manage Permissions — define the assignable permission actions
// (cdb_user_module_actions). Adding one here makes it appear automatically in
// the Roles / Departments / User permission grids. Enforcement still needs a
// code check (require_permission / cdp_hasPermission).
// ============================================================================
if (!$user->cdp_is_Admin())
    cdp_redirect_to("login.php");
$userData = $user->cdp_getUserData();

$db = new Conexion;
$db->cdp_query("SELECT id, module_name FROM cdb_user_module_permissions ORDER BY module_name");
$db->cdp_execute();
$modules = $db->cdp_registros() ?: [];
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Manage Permissions | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row"><div class="col-12 align-self-center">
                    <h4 class="page-title">Manage Permissions</h4>
                    <small class="text-muted">Define assignable permissions. They appear in Roles &amp; Departments automatically; a developer adds the code check to enforce a new one.</small>
                </div></div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <!-- Add form -->
                    <div class="col-12 mb-3">
                        <div class="card"><div class="card-body">
                            <h5 class="card-title mb-3">Add Permission</h5>
                            <form id="pa_add_form">
                                <div class="form-row align-items-end">
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="mb-1">Name <small class="text-muted">(code key)</small></label>
                                        <input type="text" class="form-control" id="pa_name" placeholder="e.g. fs_export_pdf" maxlength="50">
                                    </div>
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="mb-1">Module</label>
                                        <select class="form-control custom-select" id="pa_module">
                                            <option value="">— choose a module —</option>
                                            <?php foreach ($modules as $m): ?>
                                                <option value="<?php echo (int) $m->id; ?>"><?php echo htmlspecialchars($m->module_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="mb-1">Description</label>
                                        <input type="text" class="form-control" id="pa_desc" placeholder="What this permission protects" maxlength="255">
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <button type="submit" class="btn btn-danger btn-block">Add</button>
                                    </div>
                                </div>
                                <small class="text-muted">Name: lowercase letters, digits and underscores; starts with a letter.</small>
                            </form>
                            <div class="alert alert-info mt-3 mb-0" style="font-size:12.5px;">
                                Adding a permission only makes it <b>assignable</b>. To make it actually restrict something, a developer must add a <code>require_permission()</code> / <code>cdp_hasPermission()</code> check in code.
                            </div>
                        </div></div>
                    </div>

                    <!-- List -->
                    <div class="col-12 mb-3">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Permissions</h5>
                                <input type="text" id="pa_search" class="form-control" style="max-width:260px;" placeholder="Search permissions…">
                            </div>
                            <div id="pa_list" class="mt-2"><div class="text-muted p-3">Loading…</div></div>
                        </div></div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script>
        window.PA_MODULES = <?php echo json_encode(array_map(function ($m) {
            return ['id' => (int) $m->id, 'name' => $m->module_name];
        }, $modules)); ?>;
    </script>
    <script src="<?= cdp_asset('dataJs/manage_permissions.js') ?>"></script>
</body>

</html>
