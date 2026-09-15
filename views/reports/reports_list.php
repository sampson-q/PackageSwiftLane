<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



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
    <title><?php echo $lang['report-general01'] ?> | <?php echo $core->site_name ?></title>
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

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-5 align-self-center">
                        <h4 class="page-title"> <?php echo $lang['report-general01'] ?></h4>

                    </div>
                </div>
            </div>


            <div class="container-fluid">
                <!-- ============================================================== -->
                <!-- REPORTS GENERALS 1 -->
                <!-- ============================================================== -->
                <div class="row mb-4 d-flex">
                    <!-- ONLINE SHIPPING -->
                    
                    <?php

                        $perModule = [
                        'view_module_shipping_reports',    
                        'view_general_shipments',
                        'view_shipment_by_clients',
                        'view_shipment_by_employees',
                        'view_shipment_by_agencies',
                        'view_shipment_by_drivers',
                        'view_top_users_sea'
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><iconify-icon icon="glyphs:plane-departure-bold"></iconify-icon></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo 'Air Shipments' ?></span><span class="swl-panel__note"><?php echo 'Advanced Air Shipping Reports' ?></span></div></div>
                                <!-- title -->
                                 <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_general_shipments')) { ?>
                                        <a class="swl-kv__row" href="report_general.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general010'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_clients')) { ?>
                                        <a class="swl-kv__row" href="report_customer.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general011'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_employees')) { ?>
                                        <a class="swl-kv__row" href="report_employees.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general012'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="report_agency.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general013'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_drivers')) { ?>
                                        <a class="swl-kv__row" href="report_driver_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general014'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_top_users_sea')) { ?>
                                        <a class="swl-kv__row" href="report_top_users_sea.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo 'Top Users' ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                    </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- ONLINE SHOPPING-->

                    <!-- SHIPMENT -->
                    <?php 

                        $perModule = [
                        'view_module_package_reports',    
                        'view_general_package_records',
                        'view_package_by_employees',
                        'view_package_by_agencies',
                        'view_package_by_drivers',
                        'view_top_users_air',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><iconify-icon icon="mingcute:ship-fill"></iconify-icon></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo 'Sea Shipments' ?></span><span class="swl-panel__note"><?php echo 'Advanced Sea Shipping Reports' ?></span></div></div>
                                <!-- title -->
                                <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_general_package_records')) { ?>
                                        <a class="swl-kv__row" href="report_packages_registered.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general03'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_employees')) { ?>
                                        <a class="swl-kv__row" href="report_packages_registered_employee.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general04'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="report_packages_registered_agency.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general05'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_drivers')) { ?>
                                        <a class="swl-kv__row" href="report_packages_registered_driver.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general06'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_top_users_air')) { ?>
                                        <a class="swl-kv__row" href="report_top_users_air.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo 'Top Users' ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="financial_sheet.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo 'Financial Sheet' ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                    </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- SHIPMENT -->

                    <!-- PICK UP SHIPMENT -->
                    <?php 

                        $perModule = [
                        'view_module_pickup_reports',    
                        'view_general_pickups',
                        'view_pickups_by_clients',
                        'view_pickups_by_employees',
                        'view_pickups_by_agencies',
                        'view_pickups_by_drivers',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><i class="mdi mdi-cube-send"></i></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo $lang['report-general015'] ?></span><span class="swl-panel__note"><?php echo $lang['report-general016'] ?></span></div></div>
                                <!-- title -->
                                <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_general_pickups')) { ?>
                                        <a class="swl-kv__row" href="report_pickup_general_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general017'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_clients')) { ?>
                                        <a class="swl-kv__row" href="report_pickup_customers_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general018'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_employees')) { ?>
                                        <a class="swl-kv__row" href="report_pickup_employees_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general019'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="report_pickup_agency_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general020'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_drivers')) { ?>
                                        <a class="swl-kv__row" href="report_pickup_driver_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general021'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                    </div>

                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- PICK UP SHIPMENT-->


                    <!-- CONSOLIDATE -->
                     <?php 

                        $perModule = [
                        'view_module_consolidated_shipping_reports',    
                        'view_general_consolidated_shipments',
                        'view_consolidated_by_clients',
                        'view_consolidated_by_employees',
                        'view_consolidated_by_agencies',
                        'view_consolidated_by_drivers',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><i class="fas fas fa-boxes"></i></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo $lang['left-menu-sidebar-87800334'] ?></span><span class="swl-panel__note"><?php echo $lang['report-general023'] ?></span></div></div>
                                <!-- title -->
                                <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_general_consolidated_shipments')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_general_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general024'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_by_clients')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_customers_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general025'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_employees')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_employees_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general026'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_agency_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general027'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_drivers')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_driver_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general028'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <tr>
                                            <td class="title"></td>
                                        </tr>

                                    </div>

                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- CONSOLIDATE-->


                    <!-- CONSOLIDATE PACKAGES -->
                    <?php 

                        $perModule = [
                        'view_module_locker_package_conso_reports',    
                        'view_general_consolidated_locker_packages',
                        'view_consolidated_locker_by_clients',
                        'view_consolidated_locker_by_employees',
                        'view_consolidated_locker_by_agencies',
                        'view_consolidated_locker_by_drivers',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><i class="fas fas fa-boxes"></i></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo $lang['left-menu-sidebar-87800333'] ?></span><span class="swl-panel__note"><?php echo $lang['report-general023'] ?></span></div></div>
                                <!-- title -->
                                <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_general_consolidated_locker_packages')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_packages_general_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general024'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_clients')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_packages_customers_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general025'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_employees')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_packages_employees_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general026'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_agencies')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_packages_agency_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general027'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_drivers')) { ?>
                                        <a class="swl-kv__row" href="report_consolidate_packages_driver_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general028'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>

                                        <tr>
                                            <td class="title"></td>
                                        </tr>

                                    </div>

                            </div>
                        </div>
                    </div>
                     <?php } ?>
                     <!-- CONSOLIDATE PACKAGES-->

                    <!-- ACCOUNTS RECEIVABLE -->
                    <?php 

                        $perModule = [
                        'view_module_accounts_receivable_reports',    
                        'view_client_balance',
                        'view_accounts_summary',
                        'view_received_payments',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">

                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <!-- title -->
                                <div class="swl-panel__head"><span class="swl-metric__icon"><i class="mdi mdi-chart-line"></i></span><div class="swl-panel__text"><span class="ds-title-md"><?php echo $lang['report-general029'] ?></span><span class="swl-panel__note"><?php echo $lang['report-general030'] ?></span></div></div>
                                <!-- title -->
                                <div class="swl-kv">
                                        <?php if ($user->cdp_hasPermission('view_client_balance')) { ?>
                                        <a class="swl-kv__row" href="report_customers_balance_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general031'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_accounts_summary')) { ?>
                                        <a class="swl-kv__row" href="report_summary_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general032'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_received_payments')) { ?>
                                        <a class="swl-kv__row" href="report_payments_received_list.php"><div class="swl-kv__text"><span class="swl-kv__label"><?php echo $lang['report-general033'] ?></span></div><span class="swl-kv__value"><span class="btn btn-xs btn-outline-dark">Open</span></span></a>
                                        <?php } ?>

                                    </div>

                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- ACCOUNTS RECEIVABLE-->
                </div>
                <!-- ============================================================== -->
                <!-- REPORT GENERALS 1 -->
                <!-- ============================================================== -->



                <!-- ============================================================== -->
                <!-- REPORTS GENERALS 2 -->
                <!-- ============================================================== -->
                <div class="row">




                </div>
                <!-- ============================================================== -->
                <!-- REPORT GENERAL 2 -->
                <!-- ============================================================== -->


                <!-- ============================================================== -->
                <!-- REPORTS GENERALS 3 -->
                <!-- ============================================================== -->
                <!-- ============================================================== -->
                <!-- REPORT GENERAL 3 -->
                <!-- ============================================================== -->

                <?php include 'views/inc/footer.php'; ?>
            </div>

        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->

</body>

</html>