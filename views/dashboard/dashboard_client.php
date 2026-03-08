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


$userData = $user->cdp_getUserData();
$virtualMailboxes = $core->cdp_getVirtualMailboxes(' WHERE id = 1');

$db = new Conexion;

$sql = "SELECT * FROM cdb_add_order where status_courier!=21 and order_payment_method >1  and sender_id='" . $_SESSION['userid'] . "' ";
$db->cdp_query($sql);
$data = $db->cdp_registros();

$count = 0;
$sumador_pendiente = 0;
$sumador_total = 0;
$sumador_pagado = 0;

foreach ($data as $row) {



    $db->cdp_query('SELECT  IFNULL(sum(total), 0)  as total  FROM cdb_charges_order WHERE order_id=:order_id');

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
                    <div class="col-12 col-sm-12 col-md-8 col-lg-8 col-xl-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-8 col-md-8 col-lg-8 col-xl-8">
                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-0">
                                                <h5 class="m-0 me-2"><?php echo $lang['virtual_mailbox-4'] ?></h5>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="pb-0">
                                            <div class="row">
                                                <!-- Virtual Address -->
                                                <?php foreach ($virtualMailboxes as $virtualMailbox) {
                                                    $db->cdp_query("SELECT * FROM cdb_countries WHERE id='" . $virtualMailbox-> cdb_countries_id . "'");
                                                    $db->cdp_execute();
                                                    $flag = strtolower(substr($db->cdp_registro()->iso3, 0, 2));

                                                    $pattern = '/\(?locker\s*ID\)?/i';
                                                    $updatedAddress = preg_replace($pattern, ' (' . $userData->locker . ') ', $virtualMailbox->address);
                                                    ?>
                                                    
                                                    <div class="col-5">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h4 class="col-12">
                                                                    <?= $virtualMailbox->country; ?>
                                                                    <span class="display-20 flag-circle">
                                                                        <i class="fi fi-<?php echo $flag; ?>"></i>
                                                                        <hr>
                                                                    </span>
                                                                </h4>
                                                                <ul class="list-style-none">
                                                                    <li class="mb-2">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['virtual_mailbox-1']; ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <h6 class="text-muted"><?php echo $updatedAddress; ?></h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-2">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['dash-general-39'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <h6 class="text-muted"><?php echo $userData->locker; ?></h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-2">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['left92'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <h6 class="text-muted"><?php echo $virtualMailbox->city; ?></h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-3">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['left94'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <h6 class="text-muted"><?php echo $virtualMailbox->postcode; ?></h6>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                                <!--/ Virtual Address -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>    

                <div class="row">
                    <div class="col-12 col-sm-12 col-md-8 col-lg-8 col-xl-12 mb-4">

                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-8 col-md-8 col-lg-8 col-xl-8">

                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-6">
                                                <h5 class="m-0 me-2"><?php echo $lang['messagesform91'] ?></h5>
                                                <small class="text-muted"><?php echo $lang['messagesform92'] ?></small>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="pb-0">
                                            <div class="row">
                                                <!-- Primer grupo de 3 elementos -->
                                                <div class="col-sm-6 col-md-6 col-lg-6">
                                                    <!-- Primer elemento contador de envios -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10">
                                                                <a href="dashboard_admin_shipments.php">
                                                                    <span class="text-orange display-7">
                                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                                    </span>
                                                                </a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE order_incomplete=1 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                    ?>            
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-1'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Segundo elemento contador de recogida envio -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="pickup_list.php"><span class="text-cyan display-7"><iconify-icon icon="solar:star-linear" class="display-7"></iconify-icon></span> </a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE order_incomplete != 0 and is_pickup=1 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>            
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-2'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Tercer elemento contador de consolidados de envios-->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="consolidate_list.php"><span class="text-danger display-7"><iconify-icon icon="solar:gift-linear" class="display-7"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_consolidate WHERE sender_id='" . $_SESSION['userid'] . "'");
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
                                          
                                                <!-- Segundo grupo de 3 elementos -->
                                                <div class="col-sm-6 col-md-6 col-lg-6">
                                                    <!-- Cuarto elemento contador de cuentas por cobrar -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="courier_list.php"><span class="text-success display-7"><iconify-icon icon="solar:wallet-money-linear" class="display-7"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE status_courier=8 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>         
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-25'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Quinto elemento contador de pre alertas -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="prealert_list.php"><span class="text-warning display-7"><iconify-icon icon="solar:bell-linear" class="display-7"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                 <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_pre_alert where is_package=0 and customer_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>       
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-5'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Sexto elemento de contador de paquetes -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="customer_packages_list.php"><span class="text-success display-7"><iconify-icon icon="solar:box-minimalistic-linear" class="display-7"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                 <?php
                                                                    $db->cdp_query("SELECT COUNT(*) as total FROM cdb_customers_packages where sender_id='" . $_SESSION['userid'] . "'");
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
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-12 col-sm-4 col-md-4 col-lg-4 col-xl-4">

                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-6">
                                                <h5 class="m-0 me-2"><?php echo $lang['dash-general-36'] ?></h5>
                                                <small class="text-muted"><?php echo $lang['messagesform98'] ?></small>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="pb-0">
                                            <ul class="p-0 m-0">
                                                <li class="d-flex mb-2">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-info d-inline-flex align-items-center justify-content-center"><iconify-icon icon="solar:clock-circle-linear" style="font-size:1.1rem"></iconify-icon></span>
                                                        </div>
                                                        <div class="card-user d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                        <div class="me-2">
                                                            <h6 class="mb-0"><?php echo $lang['dash-general-2'] ?></h6>
                                                        </div>
                                                        <div class="user-progress d-flex align-items-center gap-3">
                                                          
                                                          <div class="d-flex align-items-center gap-1">
                                                            <small class="text-muted">
                                                                <?php
                                                                $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and order_incomplete=1 and sender_id='" . $_SESSION['userid'] . "' ");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>  
                                                            </small>
                                                          </div>
                                                        </div>
                                                    </div>
                                                </li>

                                                <li class="d-flex mb-2">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-warning d-inline-flex align-items-center justify-content-center"><iconify-icon icon="solar:bell-linear" style="font-size:1.1rem"></iconify-icon></span>
                                                        </div>
                                                        <div class="card-user d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                        <div class="me-2">
                                                            <h6 class="mb-0"><?php echo $lang['dash-general-221'] ?></h6>
                                                        </div>
                                                        <div class="user-progress d-flex align-items-center gap-3">
                                                          
                                                          <div class="d-flex align-items-center gap-1">
                                                            <small class="text-muted">
                                                                <?php
                                                                $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and status_courier=12 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>  
                                                            </small>
                                                          </div>
                                                        </div>
                                                    </div>
                                                </li>


                                                <li class="d-flex mb-2">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-danger d-inline-flex align-items-center justify-content-center"><iconify-icon icon="solar:close-circle-linear" style="font-size:1.1rem"></iconify-icon></span>
                                                        </div>
                                                        <div class="card-user d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                        <div class="me-2">
                                                            <h6 class="mb-0"><?php echo $lang['dash-general-222'] ?></h6>
                                                        </div>
                                                        <div class="user-progress d-flex align-items-center gap-3">
                                                          
                                                          <div class="d-flex align-items-center gap-1">
                                                            <small class="text-muted">
                                                                <?php
                                                                $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE is_pickup=1 and status_courier=21 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>  
                                                            </small>
                                                          </div>
                                                        </div>
                                                    </div>
                                                </li>

                                                <li class="d-flex mb-2">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-success d-inline-flex align-items-center justify-content-center"><iconify-icon icon="solar:clock-circle-linear" style="font-size:1.1rem"></iconify-icon></span>
                                                        </div>
                                                        <div class="card-user d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                        <div class="me-2">
                                                            <h6 class="mb-0"><?php echo $lang['dash-general-220'] ?></h6>
                                                        </div>
                                                        <div class="user-progress d-flex align-items-center gap-3">
                                                          
                                                          <div class="d-flex align-items-center gap-1">
                                                            <small class="text-muted">
                                                                <?php
                                                                $db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order WHERE status_courier=8 and  is_pickup=1 and sender_id='" . $_SESSION['userid'] . "'");
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo $count->total;
                                                                ?>  
                                                            </small>
                                                          </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12 col-md-12 col-lg-12">
                        <div class="card">
                            <div class="card-body">

                                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="pills-home-tab" data-toggle="pill" href="#pills-shipment" role="tab" aria-controls="pills-shipment" aria-selected="true"><?php echo $lang['dash-general-19'] ?></a>
                                        <input type="hidden" name="userid" id="userid" value="<?php echo $_SESSION['userid']; ?>">
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab" href="prealert_list.php">
                                            <?php echo $lang['dash-general-22'] ?>
                                        </a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab" href="customer_packages_list.php"><?php echo $lang['dash-general-23'] ?></a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab" href="consolidate_list.php">
                                            <?php echo $lang['dash-general-21'] ?></a>
                                    </li>

                                </ul>

                                <div class="tab-content" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-shipment" role="tabpanel" aria-labelledby="pills-home-tab">

                                        <div class="outer_div"></div>

                                    </div>

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


    <script src="dataJs/dashboard_client.js"></script>