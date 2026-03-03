<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************



if (!$user->cdp_is_Admin())
    cdp_redirect_to("login.php");

$userData = $user->cdp_getUserData();

// Consultar los módulos
$db->cdp_query('SELECT id, module_name, description FROM cdb_user_module_permissions ORDER BY id DESC');
$db->cdp_execute();
$modules = $db->cdp_registros();

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Meta Description (for search results) -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Author (content owner) -->
    <meta name="author" content="CODDINGPRO">
    <!-- Keywords (related keywords) -->
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph Meta (for social media sharing, like Facebook) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['rolesp30'] ?> | <?php echo $core->site_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'views/inc/head_scripts.php'; ?>

</head>

<body>
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->


    <?php include 'views/inc/preloader.php'; ?>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <!-- Action part -->
            <!-- Button group part -->
            <div class="bg-light">
                <div class="row justify-content-center">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-12">
                                <!-- <div id="loader" style="display:none"></div> -->
                                <div id="resultados_ajax"></div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Action part -->

            <div class="container-fluid mb-4">

                <div class="row">
                    <!-- Column -->

                    <div class="col-lg-12 col-xl-12 col-md-12">

                        <div class="card">
                            <div class="card-body">

                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['rolesp30'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>
                                <form class="form-horizontal form-material" id="save_data" name="save_data" method="post">
                                    <section>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="role_name"><?php echo $lang['rolesp3']; ?></label>
                                                    <input type="text" class="form-control required" name="role_name" id="role_name" placeholder="<?php echo $lang['rolesp3']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="description"><?php echo $lang['rolesp4']; ?></label>
                                                    <input type="text" class="form-control required" name="description" id="description" placeholder="<?php echo $lang['rolesp13']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                    <br>
                                    <section>
                                        <h2 class="role-title"><?php echo $lang['rolesp40']; ?></h2>
                                        <!-- Configuración de módulos -->
                                        <div class="row">
                                            <?php
                                            $count = 0;
                                            foreach ($modules as $module):
                                                if ($count > 0 && $count % 6 === 0): ?>
                                                    </div><div class="row">
                                                <?php endif; ?>
                                                <div class="col-md-6">
                                                    <div class="module-container">
                                                        <h3 class="module-title"><?php echo htmlspecialchars($module->description); ?></h3>
                                                        <div class="role-group">
                                                            <h5>
                                                                <span><?php echo htmlspecialchars($module->module_name); ?></span>
                                                                <span class="select-all">
                                                                    <input type="checkbox" id="selectAll-<?php echo $module->id; ?>" onclick="toggleAll(this, 'module-<?php echo $module->id; ?>')">
                                                                    <?php echo $lang['rolesp43']; ?>
                                                                </span>
                                                            </h5>
                                                            <div class="actions-container" style="max-height: 200px; overflow-y: auto;">
                                                                <?php
                                                                $db->cdp_query('SELECT id, action_name, description_module FROM cdb_user_module_actions WHERE module_id = :id');
                                                                $db->bind(':id', $module->id);
                                                                $db->cdp_execute();
                                                                $actions = $db->cdp_registros();
                                                                ?>
                                                                <?php foreach ($actions as $index => $action): ?>
                                                                    <div class="form-check <?php echo $index >= 10 ? 'hidden-action module-' . $module->id : ''; ?>" style="<?php echo $index >= 10 ? 'display: none;' : ''; ?>">
                                                                        <input class="form-check-input module-action module-<?php echo $module->id; ?>" type="checkbox" name="permissions[]" id="action-<?php echo $action->id; ?>" value="<?php echo $action->id; ?>">
                                                                        <label class="form-check-label" for="action-<?php echo $action->id; ?>">
                                                                            <?php echo htmlspecialchars($action->description_module); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <?php if (count($actions) > 10): ?>
                                                                <div class="text-center mt-2">
                                                                    <button type="button" class="btn btn-link btn-sm toggle-actions" data-module-id="<?php echo $module->id; ?>"><?php echo $lang['rolesp39']; ?></button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php $count++; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['rolesp44']; ?> <span><i class="icon-ok"></i></span></button>
                                            <a href="asingrole_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['rolesp45']; ?></a>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                    <!-- Column -->
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>

        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->



     <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>

    <script src="dataJs/asingpermissions.js"></script>
    <script>
        document.querySelectorAll('.toggle-actions').forEach(button => {
            button.addEventListener('click', function () {
                const moduleId = this.dataset.moduleId;
                const hiddenActions = document.querySelectorAll(`.hidden-action.module-${moduleId}`);
                hiddenActions.forEach(action => {
                    action.style.display = action.style.display === 'none' ? '' : 'none';
                });
                this.textContent = this.textContent === '<?php echo $lang['rolesp39'] ?>' ? '<?php echo $lang['rolesp38'] ?>' : '<?php echo $lang['rolesp39'] ?>';
            });
        });

        function toggleAll(selectAllCheckbox, groupClass) {
            const checkboxes = document.querySelectorAll(`.${groupClass}`);
            checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
        }
    </script>
</body>

</html>