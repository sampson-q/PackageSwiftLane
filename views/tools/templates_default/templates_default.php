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


require_once('helpers/querys.php');

$db = new Conexion;



$db->cdp_query("SELECT * FROM default_notification_templates");
$default_notification_templates = $db->cdp_registros();

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
    <title><?php echo $lang['ws-add-text16'] ?>| <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <?php include 'views/inc/head_scripts.php'; ?>
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

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

            <!-- ============================================================== -->
            <!-- Start Page Content -->
            <!-- ============================================================== -->
            <div class="email-app">
                <!-- ============================================================== -->
                <!-- Left Part menu -->
                <!-- ============================================================== -->

                <?php include 'views/inc/left_part_menu.php'; ?>

                <!-- ============================================================== -->
                <!-- Right Part contents-->
                <!-- ============================================================== -->
                <div class="right-part mail-list bg-white mt-3">

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


                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <div class="row">
                                <!-- Column -->
                                <div class="col-12">
                                    <div class="card-body">

                                    <div class="d-md-flex align-items-center">
                                            <div>
                                            <h3 class="card-title"><span><?php echo $lang['ws-add-text16'] ?></span></h3>
                                                </div>
                                                </div>
                                                <div><hr><br></div>

                                                <form class="form-horizontal form-material" id="save_data" name="save_data" method="post">

                                                <section>
                                                <div class="row">
                                                <div class="table-responsive">
                                                    <table id="file_export" class="table border table-striped table-bordered display dataTable" aria-describedby="file_export_info">
                                                        <thead>
                                                            <th>
                                                                <h5><?php echo $lang['ws-add-text17'] ?></h5>
                                                            </th>
                                                            <th>
                                                                <h5><?php echo $lang['ws-add-text18'] ?></h5>
                                                            </th>
                                                            <th></th>
                                                            <th>
                                                                <h5><?php echo $lang['ws-add-text19'] ?></h5>
                                                            </th>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            foreach ($default_notification_templates as $template) {


                                                                $db->cdp_query("SELECT * FROM whatsapp_templates WHERE id='" . $template->id_template . "'");
                                                                $whatsapp_template = $db->cdp_registro();
                                                            ?>
                                                                <tr class="card-hover">
                                                                    <td style="width: 10%;">
                                                                        <div class="form-group">
                                                                            <label class="custom-control custom-checkbox" style="font-size: 18px;">
                                                                                <input name="active_<?php echo $template->id; ?>" data-id="<?php echo $template->id; ?>" 
                                                                                type="checkbox" class="custom-control-input checkbox-active" value="1" 
                                                                                <?php if ($template->active == 1) {
                                                                                    echo 'checked';
                                                                                        } ?>>

                                                                                <span class="custom-control-indicator"></span>
                                                                            </label>
                                                                        </div>
                                                                    </td>
                                                                    <td style="width: 50%;">
                                                                        <?php echo $template->notification_description; ?>
                                                                    </td>

                                                                    <td style="width: 10%;"></td>



                                                                    <td style="width: 30%;">
                                                                        <div class="form-group">
                                                                            <select required class="form-control custom-select select2" id="template_<?php echo $template->id; ?>" name="id_template_<?php echo $template->id; ?>">
                                                                                <?php
                                                                                if ($whatsapp_template != null) {
                                                                                ?>
                                                                                    <option value="<?php echo $whatsapp_template->id; ?>"> <?php echo $whatsapp_template->title; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>

                                                                        </div>
                                                                    </td>
                                                                </tr>

                                                            <?php
                                                            }
                                                            ?>

                                                        </tbody>
                                                    </table>
                                                </div>
                                                </div>


                                                </section>

                                                <div class="form-group">
                                                <div class="col-sm-12">
                                                <button class="btn btn-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['ws-add-text20'] ?><span><i class="icon-ok"></i></span></button>

                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <!-- Column -->
                            </div>
                        </div>
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

        <script src="assets/jquery.validate.js"></script>
        <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
        <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
        <script src="dataJs/templates_default.js"></script>
</body>

</html>