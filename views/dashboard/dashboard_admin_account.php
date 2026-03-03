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


require_once(__DIR__ . '/../../helpers/querys.php');
$db = new Conexion;

$userData = $user->cdp_getUserData();

$ctx = cdp_getAgencyContext();
$agency_where = '';
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $agency_where = ' AND agency = ' . (int)$ctx['agency_id'];
} elseif ($ctx['is_restricted']) {
    $agency_where = ' AND 1=0';
} elseif ((int)$userData->userlevel === 3) {
    $agency_where = ' AND driver_id = ' . (int)$_SESSION['userid'];
} elseif ((int)$userData->userlevel === 1) {
    $agency_where = ' AND sender_id = ' . (int)$_SESSION['userid'];
}

// Obtener el mes y el año actual
$month = date('m');
$year = date('Y');

// Obtener el número del mes actual
$currentMonth = date('n');

// Obtener el nombre del mes actual
$monthName = obtenerNombreMes($currentMonth);

$sql = "SELECT order_id, total_order FROM cdb_add_order WHERE status_courier != 21 AND order_payment_method > 1 AND MONTH(order_date) = :month AND YEAR(order_date) = :year" . $agency_where . " ORDER BY order_id DESC";
$db->cdp_query($sql);
$db->bind(':month', $month);
$db->bind(':year', $year);
$db->cdp_execute();
$data = $db->cdp_registros();
$count = 0;
$sumador_pendiente = 0;
$sumador_total = 0;
$sumador_pagado = 0;

foreach ($data as $row) {
    $db->cdp_query('SELECT IFNULL(SUM(total), 0) AS total FROM cdb_charges_order WHERE order_id = :order_id');
    $db->bind(':order_id', $row->order_id);
    $db->cdp_execute();
    $sum_payment = $db->cdp_registro();
    $pendiente = $row->total_order - $sum_payment->total;
    $sumador_pendiente += $pendiente;
    $sumador_total += $row->total_order;
    $sumador_pagado += $sum_payment->total;
    $count++;
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
    <title><?php echo $lang['left-menu-sidebar-28'] ?>| <?php echo $core->site_name ?></title>

    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
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
                        <h4 class="page-title"><?php echo $lang['left-menu-sidebar-28'] ?></h4>
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
                <!-- Earnings -->
                <!-- ============================================================== -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $lang['dash-general-31'] ?></h4>
                                <div class="table-responsive">
                                    <div id="sales-packages-chart-container" class="morris-chart-container">
                                        <div id="basic-bar" style="height:400px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Earning Reports -->
                    <div class="col-xl-4 col-md-4 mb-2">
                      <div class="card">
                        <div class="card-body pb-4">
                            <div class="card-header-title d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="m-0 me-2"><?php echo $lang['messagesform107'] ?></h5>
                                    <small class="text-muted"><?php echo $lang['messagesform108'] ?> <?php echo $monthName; ?></small>
                                </div>
                            </div>
                            <div><br><br><br><br><br></div>
                            <ul class="list-style-none">
                                <li class="mb-2">
                                    <div class="row">
                                        <div class="col-xl-6 col-md-6 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['dash-general-3102'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                        // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        echo cdb_money_format($sumador_total);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-md-6 mb-0">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                        <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $progressPercentage = ($sumador_total / $totalDays) * 100; // Utiliza el total de órdenes en lugar del día actual para calcular el progreso
                                                        ?>
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $sumador_total; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>


                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <li class="mb-2">
                                    <div class="row">
                                        <div class="col-xl-6 col-md-6 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['dash-general-32'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                        // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        echo cdb_money_format($sumador_pagado);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-md-6 mb-0">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                       <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $progressPercentage = ($sumador_pagado / $totalDays) * 100; // Utiliza el total de órdenes en lugar del día actual para calcular el progreso
                                                        ?>
                                                        <div class="progress-bar bg-label-blue" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $sumador_pagado; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <li class="mb-2">
                                    <div class="row">
                                        <div class="col-xl-6 col-md-6 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['dash-general-33'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                            // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        echo cdb_money_format($sumador_pendiente);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-md-6 mb-6">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                        <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $progressPercentage = ($sumador_pendiente / $totalDays) * 100; // Utiliza el total de órdenes en lugar del día actual para calcular el progreso
                                                        ?>
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $sumador_pendiente; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                      </div>
                    </div>
                    <!--/ Earning Reports -->
                </div>
                <!-- ============================================================== -->
                <!-- Projects of the month -->
                <!-- ============================================================== -->
                <!-- ============================================================== -->
                <!-- Table -->
                <!-- ============================================================== -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="card-header-title d-flex justify-content-between">
                                    <div class="card-title mb-0">
                                        <h5 class="m-0 me-2"><?php echo $lang['dash-general-30'] ?></h5>
                                    </div>
                                </div>
                                <div class="outer_div">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ============================================================== -->
                <!-- Table -->
                <!-- ============================================================== -->

            </div>
            <?php include 'views/inc/footer.php'; ?>

        </div>
    </div>
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <!-- This Page JS -->
    <script src="assets/template/assets/libs/echarts/dist/echarts-en.min.js"></script>

    <script src="dataJs/dashboard_account.js"> </script>
    <script src="assets/template/assets/extra-libs/chart.js-2.8/Chart.min.js"></script>