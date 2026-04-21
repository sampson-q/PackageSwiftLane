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
                        'view_module_package_reports',    
                        'view_general_package_records',
                        'view_package_by_employees',
                        'view_package_by_agencies',
                        'view_package_by_drivers',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="mdi mdi-cart-outline" style="color:#9B9B8C"></i></span> <?php echo $lang['report-general02'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general07'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>

                                        <?php if ($user->cdp_hasPermission('view_general_package_records')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_packages_registered.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general03'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_employees')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_packages_registered_employee.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general04'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_agencies')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_packages_registered_agency.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general05'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_package_by_drivers')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_packages_registered_driver.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general06'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <tr>
                                            <td class="title"></td>
                                        </tr>
                                        <br>

                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- ONLINE SHOPPING-->


                    <!-- SHIPMENT -->
                    <?php 

                        $perModule = [
                        'view_module_shipping_reports',    
                        'view_general_shipments',
                        'view_shipment_by_clients',
                        'view_shipment_by_employees',
                        'view_shipment_by_agencies',
                        'view_shipment_by_drivers',
                        ];
                        if ($user->cdp_hasPermission($perModule)) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="mdi mdi-package-variant" style="color:#9B9B8C"></i></span> <?php echo $lang['report-general08'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general09'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>
                                        <?php if ($user->cdp_hasPermission('view_general_shipments')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_general.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general010'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_clients')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_customer.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general011'] ?></a></td>
                                        </tr>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_employees')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_employees.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general012'] ?></a></td>
                                        </tr>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_agencies')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_agency.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general013'] ?></a></td>
                                        </tr>
                                         <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_shipment_by_drivers')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_driver_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general014'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>

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
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="mdi mdi-cube-send" style="color:#9B9B8C"></i></span> <?php echo $lang['report-general015'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general016'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>
                                        <?php if ($user->cdp_hasPermission('view_general_pickups')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_pickup_general_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general017'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_clients')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_pickup_customers_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general018'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_employees')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_pickup_employees_list.php"><i class="ti ti-arrow-rightmdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general019'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_agencies')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_pickup_agency_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general020'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_pickups_by_drivers')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_pickup_driver_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general021'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>

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
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card  ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="fas fas fa-boxes" style="color:#9B9B8C"></i></span> <?php echo $lang['left-menu-sidebar-87800334'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general023'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>
                                        <?php if ($user->cdp_hasPermission('view_general_consolidated_shipments')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_general_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general024'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_by_clients')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_customers_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general025'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_employees')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_employees_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general026'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_agencies')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_agency_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general027'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                         <?php if ($user->cdp_hasPermission('view_consolidated_by_drivers')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_driver_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general028'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <tr>
                                            <td class="title"></td>
                                        </tr>

                                    </tbody>
                                </table>

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
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card  ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="fas fas fa-boxes" style="color:#9B9B8C"></i></span> <?php echo $lang['left-menu-sidebar-87800333'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general023'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>
                                        <?php if ($user->cdp_hasPermission('view_general_consolidated_locker_packages')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_packages_general_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general024'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_clients')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_packages_customers_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general025'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_employees')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_packages_employees_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general026'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_agencies')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_packages_agency_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general027'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_consolidated_locker_by_drivers')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_consolidate_packages_driver_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general028'] ?></a></td>
                                        </tr>
                                        <?php } ?>

                                        <tr>
                                            <td class="title"></td>
                                        </tr>

                                    </tbody>
                                </table>

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
                    <div class="col-sm-12 col-md-6 col-lg-4">

                        <div class="card ">
                            <div class="card-body">
                                <!-- title -->
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h4 class="card-title"><span class="display-7"><i class="mdi mdi-chart-line" style="color:#9B9B8C"></i></span> <?php echo $lang['report-general029'] ?></h4>
                                        <h5 class="card-subtitle"><span class=""><i class="mdi mdi-chevron-double-right"></i></span> <?php echo $lang['report-general030'] ?></h5>
                                    </div>

                                </div>
                                <!-- title -->
                                <table class="tablesaw table-hover table no-border">
                                    <tbody>
                                        <?php if ($user->cdp_hasPermission('view_client_balance')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_customers_balance_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general031'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_accounts_summary')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_summary_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general032'] ?></a></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if ($user->cdp_hasPermission('view_received_payments')) { ?>
                                        <tr>
                                            <td class="title"><a class="link" href="report_payments_received_list.php"><i class="mdi mdi-chevron-right" style="color:#00D900"></i> <?php echo $lang['report-general033'] ?></a></td>
                                        </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>

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