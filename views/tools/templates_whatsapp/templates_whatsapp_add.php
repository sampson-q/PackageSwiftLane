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



require_once('helpers/querys.php');
$userData = $user->cdp_getUserData();


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
    <title><?php echo $lang['tools-config61'] ?> | <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
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

            <!-- ============================================================== -->
            <!-- Start Page Content -->
            <!-- ============================================================== -->
            <div class="email-app">
                <!-- ============================================================== -->
                <!-- Left Part menu -->
                <!-- ============================================================== -->

                <?php include 'views/inc/left_part_menu.php'; ?>

                <div class="right-part mail-list bg-white">
                    <div class="p-15 b-b">
                        <div class="d-flex align-items-center">
                            <div>
                                <span><?php echo $lang['tools-config61'] ?> | <?php echo $lang['ws-add-text9'] ?></span>
                            </div>

                        </div>
                    </div>
                    <!-- Action part -->
                    <!-- Button group part -->
                    <div class="bg-light p-15">
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
                                        <header>
                                            <?php echo $lang['ws-add-text10'] ?>
                                        </header> <br><br>

                                        <form class="form-horizontal form-material" id="save_data" name="save_data" method="post">

                                            <section>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label for="firstName1"><?php echo $lang['tools-template3'] ?></label>
                                                            <input type="text" class="form-control" name="title" id="title">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label for="lastName1">
                                                                <?php echo $lang['ws-add-text11'] ?>
                                                            </label>
                                                            <textarea type="text" class="form-control" name="description" id="description" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">

                                                    <div class="col-md-12">
                                                        <!-- <div class="label2 label-important">
															No reemplace los valores dentro de las llaves cuadradas []
														</div> -->
                                                        <div class="alert alert-warning">
                                                            <span class="icon-info-circle"></span>
                                                            <i class="close icon-remove-circle"></i>
                                                            <br>

                                                            <p style="text-align: justify;">
                                                                <b>
                                                                    <?php echo $lang['ws-add_text1'] ?>
                                                                    <br>
                                                                    <br>
                                                                    <?php echo $lang['ws-add_text2'] ?>
                                                                </b>
                                                                <br>
                                                                <br>
                                                                <?php echo $lang['ws-add_text3'] ?>
                                                            <ul>
                                                                <li class="mb-3">
                                                                    <b>[CUSTOMER_FULLNAME]:</b>
                                                                    <?php echo $lang['ws-add_text4'] ?>
                                                                </li>
                                                                <li class="mb-3">
                                                                    <b>[TRACKING_NUMBER]:</b>
                                                                    <?php echo $lang['ws-add_text5'] ?>
                                                                </li>

                                                                <li class="mb-3">
                                                                    <b>[COMPANY_SITE_URL]:</b>
                                                                    <?php echo $lang['ws-add_text6'] ?>
                                                                </li>
                                                                <li class="mb-3">
                                                                    <b>[COMPANY_NAME]:</b>
                                                                    <?php echo $lang['ws-add_text7'] ?>
                                                                </li>
                                                            </ul>
                                                            <?php echo $lang['ws-add-text8'] ?>

                                                            </p>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="lastName1"><?php echo $lang['ws-add-text12'] ?></label>
                                                            <textarea type="text" class="form-control" name="body" id="body" rows="8"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                            </section>
                                            <br><br>
                                            <div class="form-group">
                                                <div class="col-sm-12">
                                                    <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['ws-add-text13'] ?> <span><i class="icon-ok"></i></span></button>
                                                    <a href="templates_whatsapp.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['tools-template8'] ?></a>
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


        <script src="dataJs/templates_whatsapp.js"></script>


</body>

</html>