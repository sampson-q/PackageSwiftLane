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


$db = new Conexion;

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
    <title><?php echo $lang['left-menu-sidebar-2'] ?> | <?php echo $core->site_name ?></title>

    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>

    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/preloader.php'; ?>

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->

        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-5 align-self-center">
                        <h4 class="page-title"><?php echo $lang['left-menu-sidebar-2'] ?></h4>
                    </div>
                </div>
            </div>
            <!-- ============================================================== -->
            <!-- End Bread crumb and right sidebar toggle -->
            <!-- ============================================================== -->
            <!-- ============================================================== -->
            <!-- Container fluid  -->
            <!-- ============================================================== -->
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12 col-sm-12 col-md-12 col-lg-5 col-xl-5">

                        <div class="card">
                            <div class="card-body border-bottom">
                                <h4 class="card-title"><?php echo $lang['dash-general-35'] ?></h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Primer elemento contador de envios -->
                                    <div class="col-lg-4 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="courier_list.php">
                                                    <span class="text-orange display-7">
                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=0 and driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-1'] ?></small>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Segundo elemento contador de envios -->
                                    <div class="col-lg-4 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="courier_list.php">
                                                    <span class="text-success display-7">
                                                        <iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE status_courier=8 and is_pickup=0 and driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['left20'] ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tercer elemento contador de envios -->
                                    <div class="col-lg-4 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="consolidate_list.php">
                                                    <span class="text-danger display-7">
                                                        <iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_consolidate WHERE driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-3'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-12 col-sm-12 col-md-12 col-lg-7 col-xl-7">

                        <div class="card">
                            <div class="card-body border-bottom">
                                <h4 class="card-title"><?php echo $lang['dash-general-36'] ?></h4>
                            </div>
                            <div class="card-body ">
                                <div class="row">

                                    <!-- Primero elemento contador de envios -->
                                    <div class="col-lg-3 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="pickup_list.php">
                                                    <span class="text-cyan display-7">
                                                        <iconify-icon icon="solar:clock-circle-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and driver_id='" . $_SESSION['userid'] . "' ");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-1222'] ?></small>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Segundo elemento contador de envios -->
                                    <div class="col-lg-3 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="pickup_list.php">
                                                    <span class="text-orange display-7">
                                                        <iconify-icon icon="solar:bell-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and status_courier=12 and driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-221'] ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tercer elemento contador de envios -->
                                    <div class="col-lg-3 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="pickup_list.php">
                                                    <span class="text-danger display-7">
                                                        <iconify-icon icon="solar:close-circle-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and status_courier=21 and driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-222'] ?></small>
                                            </div>
                                        </div>
                                    </div>



                                    <!-- Cuarto elemento contador de envios -->
                                    <div class="col-lg-3 col-md-12 mb-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="pickup_list.php">
                                                    <span class="text-success display-7">
                                                        <iconify-icon icon="solar:clock-circle-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE status_courier=8 and  is_pickup=1 and driver_id='" . $_SESSION['userid'] . "'");
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-220'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class=" col-md-12 col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><?php echo $lang['dash-general-19'] ?></h4>
                                        <input type="hidden" name="userid" id="userid" value="<?php echo $_SESSION['userid']; ?>">
                                    </div>

                                </div>
                                <div class="outer_div">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>

    </div>
    </div>


    <script src="dataJs/dashboard_driver.js"></script>