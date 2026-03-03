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
    $data = cdp_getModulesEdit($_GET['id']);
}

if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to("asingrole_list.php");
    exit;
}

$row_off = $data['data'];
$id = $_GET['id']; // Suponiendo que tienes el ID del módulo en la URL

// Query to get module actions
$db->cdp_query('SELECT id, module_id, action_name, description_module, created_at 
        FROM cdb_user_module_actions 
        WHERE module_id = :id');

$db->bind(':id', $id);
$db->cdp_execute();
$module_actions = $db->cdp_registros();
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
                                        <h3 class="card-title"><span><?php echo $lang['asingmodule4'] ?> <i class="icon-double-angle-right"></i> <?php echo $row_off->module_name; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>


                                <div id="msgholder"></div>
                                <form class="form-horizontal form-material" id="update_data" method="post">
                                    <section>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="firstName1"><?php echo $lang['rolesp3'] ?></label>
                                                    <input type="text" class="form-control required" name="module_name" id="module_name" value="<?php echo $row_off->module_name; ?>" placeholder="<?php echo $lang['rolesp7'] ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="lastName1"><?php echo $lang['rolesp4'] ?></label>
                                                    <input type="text" class="form-control required" name="description" id="description" value="<?php echo $row_off->description; ?>" placeholder="<?php echo $lang['rolesp4'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                        <h3 class="card-title"><?php echo $lang['rolesp48'] ?></h3>
                                    </div>

                                        <div class="row">
                                            <div class="col-md-12">
                                                <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo $lang['rolesp49'] ?></th>
                                                            <th><?php echo $lang['rolesp50'] ?></th>
                                                            <th><?php echo $lang['rolesp6'] ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 


                                                        foreach ($module_actions as $action) : ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="text" name="action_name[<?php echo $action->id; ?>]" 
                                                                           value="<?php echo htmlspecialchars($action->action_name); ?>" class="form-control" readonly>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="description_module[<?php echo $action->id; ?>]" 
                                                                           value="<?php echo htmlspecialchars($action->description_module); ?>" class="form-control">
                                                                </td>
                                                                <td><?php echo htmlspecialchars($action->created_at); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </section>
                                    <br><br>
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['rolesp51'] ?> <span><i class="icon-ok"></i></span></button>
                                            <a href="asingrole_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['rolesp52'] ?></a>
                                        </div>
                                    </div>
                                    <input name="id" id="id" type="hidden" value="<?php echo $_GET['id']; ?>" />
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

    <script src="dataJs/asingrole.js"></script>
</body>

</html>