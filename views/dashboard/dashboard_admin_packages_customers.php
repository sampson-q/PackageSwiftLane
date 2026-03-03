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
$statusrow = $core->cdp_getStatus();

// Obtener el mes y el año actual
$month = date('m');
$year = date('Y');

// Obtener el número del mes actual
$currentMonth = date('n');

// Obtener el nombre del mes actual
$monthName = obtenerNombreMes($currentMonth);

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
    <title><?php echo $lang['left-menu-sidebar-6'] ?> | <?php echo $core->site_name ?></title>

    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">

    <link rel="stylesheet" type="text/css" href="assets/template/assets/extra-libs/c3/c3.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link href="assets/template/assets/libs/morris.js/morris.css" rel="stylesheet">
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
                        <h4 class="page-title"><?php echo $lang['left-menu-sidebar-6'] ?></h4>
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

                <!-- ============================================================== -->
                <!-- Sales chart -->
                <!-- ============================================================== -->
                <?php if ($user->cdp_hasPermission('main_dashboard_package_locker')) { ?>
                <div class="row">
                    <div class="col-sm-4 col-md-4 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="card-header-title d-flex justify-content-between">
                                    <div class="card-title mb-6">
                                        <h5 class="m-0 me-2"><?php echo $lang['dash-general-18'] ?></h5>
                                        <small class="text-muted"><?php echo $lang['dash-general-24'] ?></small>
                                    </div>
                                </div>
                                <div><br></div>

                                <div class="col-md-12 col-lg-12">
                                    <!-- Primero elemento contador de envios -->
                                    <div class="col-lg-12 col-md-12 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="customer_packages_list.php">
                                                    <span class="text-secondary display-7">
                                                        <iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                                <?php
                                                $db->cdp_query('SELECT COUNT(*) as total FROM cdb_customers_packages where is_prealert=1');
                                                $db->cdp_execute();
                                                $count = $db->cdp_registro();
                                                echo $count->total;
                                                ?>                      
                                              </h5>
                                              <small><?php echo $lang['dash-general-664'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-12 col-md-12">
                                    <!-- Segundo elemento contador de envios -->
                                    <div class="col-lg-12 col-md-12 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="customer_packages_list.php">
                                                    <span class="text-secondary display-7">
                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                     $db->cdp_query('SELECT COUNT(*) as total FROM cdb_customers_packages');
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-661'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 col-lg-12">

                                    <!-- Tercero elemento contador de envios -->
                                    <div class="col-lg-12 col-md-12 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="customer_packages_list.php">
                                                    <span class="text-secondary display-7">
                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                $db->cdp_query('SELECT COUNT(*) as total FROM cdb_customers_packages where status_invoice = 2');
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-662'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 col-lg-12">
                                    <!-- Cuarto elemento contador de envios -->
                                    <div class="col-lg-12 col-md-12 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="customer_packages_list.php">
                                                    <span class="text-secondary display-7">
                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                              <h5 class="mb-0">
                                                <?php
                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_customers_packages where status_invoice = 3');
                                                    $db->cdp_execute();
                                                    $count = $db->cdp_registro();
                                                    echo $count->total;
                                                    ?>            
                                              </h5>
                                              <small><?php echo $lang['dash-general-665'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 col-lg-12">
                                    <!-- Quinto elemento contador de envios -->
                                    <div class="col-lg-12 col-md-12 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10">
                                                <a href="customer_packages_list.php">
                                                    <span class="text-secondary display-7">
                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                    </span>
                                                </a>
                                            </div>

                                            <div class="card-info-statics">
                                                <?php
                                                $db->cdp_query('SELECT COUNT(*) as total FROM cdb_customers_packages where status_invoice = 1');
                                                $db->cdp_execute();
                                                $count = $db->cdp_registro();
                                                echo $count->total;
                                                ?>                      
                                              </h5>
                                              <small><?php echo $lang['dash-general-663'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $lang['messagesform100'] ?></h4>
                                <div class="table-responsive">
                                    <div id="sales-packages-chart-container" class="morris-chart-container">
                                        <div id="morris-sales-chart-packages"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <?php } ?>
                <!-- ============================================================== -->
                <!-- Sales chart -->
                <!-- ============================================================== -->


                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div id="resultados_ajax"></div>

                                <div class="row mb-5">

                                    <?php if ($user->cdp_hasPermission('add_package')) { ?>

                                        <div class="col-sm-12 col-md-4 mb-2">
                                            <div class=" form-group">
                                                <a href="customer_packages_add.php">
                                                    <button type="button" class="btn btn-outline-dark">
                                                        <iconify-icon icon="solar:add-circle-linear" aria-hidden="true"></iconify-icon>
                                                        <?php echo $lang['global-buttons-2'] ?>
                                                    </button>
                                                </a>
                                            </div>
                                        </div>
                                    <?php } ?>


                                    <div class="col-sm-12 col-md-4 mb-2">
                                        <div class="input-group">
                                            <input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                            <div class="input-group-append input-sm">
                                                <button type="submit" class="btn btn-outline-dark"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                            </div>

                                        </div>
                                    </div><!-- /.col -->

                                    <div class="col-sm-12 col-md-4 mb-2">
                                        <div class="input-group">
                                            <select onchange="cdp_load(1);" class="form-control custom-select" id="status_courier" name="status_courier">
                                                <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                <?php foreach ($statusrow as $row) : ?>
                                                    <option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>

                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="btn-group mt-2 hide" id="div-actions-checked">
                                            <span class="mt-2 mr-4"><strong> <?php echo $lang['global-2'] ?></strong> <strong id="countChecked"> 0</strong></span>
                                            <button class="btn btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <?php echo $lang['global-1'] ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($user->cdp_hasPermission('select_change_status')) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalCheckboxStatus"><i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['left21550'] ?></a>
                                                <?php } ?>

                                                <?php if ($user->cdp_hasPermission('assign_drivers')) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalDriverCheckbox"><iconify-icon icon="solar:delivery-linear" style="color:#ff0000"></iconify-icon>&nbsp;<?php echo $lang['left208'] ?></a>
                                                <?php } ?>

                                                <?php if ($user->cdp_hasPermission('print_label')) { ?>
                                                <a class="dropdown-item" onclick="cdp_printMultipleLabel();" target="_blank"> <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?> </a>
                                                <?php } ?>

                                            </div>
                                        </div>

                                    </div>

                                </div>
                                <div><br></div>

                                <div class="outer_divz"></div>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                include('views/modals/modal_update_status_checked.php');
                ?>
                <?php include('views/modals/modal_send_email.php'); ?>

                <?php include('views/modals/modal_update_driver.php'); ?>
                <?php include('views/modals/modal_update_driver_checked.php'); ?>
                <?php include('views/modals/modal_delete_package.php'); ?>

                <?php include('views/modals/modal_add_payment_package.php'); ?>
                <?php include('views/modals/modal_verify_payment_packages.php'); ?>

                <?php include 'views/inc/footer.php'; ?>
            </div>
        </div>

        <?php include('helpers/languages/translate_to_js.php'); ?>
        <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
        <!--Morris JavaScript -->
        <script src="assets/template/assets/libs/raphael/raphael.min.js"></script>
        <script src="assets/template/assets/libs/morris.js/morris.min.js"></script>
        <script src="dataJs/dashboard_packages.js"></script>

        <script src="dataJs/customers_packages.js"></script> 
