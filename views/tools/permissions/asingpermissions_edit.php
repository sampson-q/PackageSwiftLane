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


if (!$user->cdp_is_Admin()) {
    cdp_redirect_to("login.php");
    exit;
}

$db = new Conexion;
$userData = $user->cdp_getUserData();

require_once('helpers/querys.php');

if (isset($_GET['id'])) {
    // Obtener datos del rol
    $data = cdp_getRolesEdit($_GET['id']);
    
    if ($data['rowCount'] != 1) {
        cdp_redirect_to("asingrole_list.php");
        exit;
    }

    $row_off = $data['data']; // Datos del rol
    $id = $_GET['id']; // ID del rol

    // Obtener los permisos asociados al rol
    $db->cdp_query('
        SELECT 
            m.id AS module_id,
            m.module_name,
            m.description AS module_description
        FROM cdb_user_module_permissions m
        ORDER BY m.id DESC
    ');
    $modules = $db->cdp_registros();

        // Si no hay módulos, inicializamos como array vacío para evitar errores
        if (!$modules) {
            $modules = [];
        }
    } else {
        cdp_redirect_to("asingrole_list.php");
        exit;
    }
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
    <title><?php echo $lang['asingmodule4'] ?> | <?php echo $core->site_name ?></title>
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
                                        <h3 class="card-title"><span><?php echo $lang['asingmodule4'] ?> <i class="icon-double-angle-right"></i><strong><?php echo isset($lang['role_'.$row_off->role_id]) ? $lang['role_'.$row_off->role_id] : $row_off->role_name; ?></strong></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>
                              <form class="form-horizontal form-material" id="update_data" name="update_data" method="post">

                               <input type="hidden" id="role_id" name="role_id" value="<?php echo $id; ?>">
                                    <!-- Información general del rol -->
                                    <section>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="role_name"><?php echo $lang['rolesp3']; ?></label>
                                                    <input type="text" class="form-control required" name="role_name" id="role_name" 
                                                           value="<?php echo htmlspecialchars($row_off->role_name); ?>" 
                                                           placeholder="<?php echo $lang['rolesp3']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="description"><?php echo $lang['rolesp4']; ?></label>
                                                    <input type="text" class="form-control required" name="description" id="description" 
                                                           value="<?php echo htmlspecialchars($row_off->description); ?>" 
                                                           placeholder="<?php echo $lang['rolesp13']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                    <br>

                                    <!-- Configuración de módulos -->

                                    <?php
                                    // Reordenar los módulos según la cantidad de acciones (los que tienen menos de 8 primero)
                                    $modules_with_count = [];

                                    foreach ($modules as $module) {
                                        $db->cdp_query("SELECT COUNT(*) as total FROM cdb_user_module_actions WHERE module_id = :module_id");
                                        $db->bind(':module_id', $module->module_id);
                                        $db->cdp_execute();
                                        $count = $db->cdp_registro()->total;

                                        $modules_with_count[] = [
                                            'module' => $module,
                                            'action_count' => $count
                                        ];
                                    }

                                    // Ordena: primero los de menos de 8 acciones
                                    usort($modules_with_count, function ($a, $b) {
                                        return $a['action_count'] <=> $b['action_count'];
                                    });
                                    ?>

                                    <section>
                                        <h2 class="role-title"><?php echo $lang['asingmodule19']; ?></h2>
                                        <div class="row">
                                            <?php
                                            $count = 0;
                                            foreach ($modules_with_count as $entry):
                                                $module = $entry['module'];
                                                if ($count > 0 && $count % 2 === 0): ?>
                                                    </div><div class="row">
                                                <?php endif; ?>
                                                <div class="col-md-6">
                                                    <div class="module-container">
                                                        <div class="role-group">
                                                            <h5>
                                                                <span><?php echo htmlspecialchars($module->module_name ?? 'Unnamed', ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="select-all">
                                                                    <input type="checkbox" id="selectAll-<?php echo $module->module_id; ?>"
                                                                           onclick="toggleAll(this, 'module-<?php echo $module->module_id; ?>')">
                                                                    <?php echo $lang['rolesp43']; ?>
                                                                </span>
                                                            </h5>
                                                            <div class="actions-container" style="max-height: 450px; overflow-y: auto;">
                                                                <?php
                                                                $db->cdp_query('
                                                                    SELECT 
                                                                        a.id AS action_id,
                                                                        a.module_id,
                                                                        a.action_name,
                                                                        a.description_module,
                                                                        IFNULL(p.permitted, 0) AS permitted
                                                                    FROM cdb_user_module_actions a
                                                                    LEFT JOIN cdb_user_role_permissions p 
                                                                        ON a.id = p.module_action_id AND p.role_id = :role_id
                                                                    WHERE a.module_id = :module_id
                                                                    ORDER BY a.id
                                                                ');
                                                                $db->bind(':role_id', $id);
                                                                $db->bind(':module_id', $module->module_id);
                                                                $db->cdp_execute();
                                                                $actions = $db->cdp_registros();

                                                                foreach ($actions as $action): ?>
                                                                    <div class="form-check module-<?php echo $module->module_id; ?>">
                                                                        <input 
                                                                            class="form-check-input module-action module-<?php echo $module->module_id; ?>" 
                                                                            type="checkbox" 
                                                                            name="permissions[]" 
                                                                            id="action-<?php echo $action->action_id; ?>" 
                                                                            value="<?php echo $action->action_id . ':' . $module->module_id; ?>" 
                                                                            <?php echo $action->permitted ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="action-<?php echo $action->action_id; ?>">
                                                                            <?php echo htmlspecialchars($action->description_module ?? 'No description', ENT_QUOTES, 'UTF-8'); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php $count++; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>


                                    <!-- Botones de acción -->
                                    <div class="form-group mt-3">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit">
                                                <?php echo $lang['asingmodule20']; ?> <span><i class="icon-ok"></i></span>
                                            </button>
                                            <a href="permissions_list.php" class="btn btn-outline-secondary btn-confirmation">
                                                <span><i class="ti-share-alt"></i></span> <?php echo $lang['rolesp45']; ?>
                                            </a>
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

</body>

</html>