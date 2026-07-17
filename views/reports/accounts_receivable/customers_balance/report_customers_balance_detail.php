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
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$customer_id = intval($_REQUEST['customer']);
$fecha_inicio = cdp_sanitize($_REQUEST['fecha_inicio']);
$fecha_fin = cdp_sanitize($_REQUEST['fecha_fin']);

$sWhere = "";


if ($customer_id > 0) {

    $sWhere .= " and sender_id = '" . $customer_id . "'";
}



// Financial Sheet ledger. The payable unit is the BILL (one consolidation for
// one customer), not the individual order — so this now itemises the customer's
// bills. It previously listed orders priced from cdb_add_order.total_order less
// cdb_charges_order, a ledger retired in 2022.
$range = ($fecha_inicio !== '' && $fecha_fin !== '')
    ? (str_replace('-', '/', $fecha_inicio) . ' - ' . str_replace('-', '/', $fecha_fin))
    : '';

$data = cdp_fsBillingSummary([
    'customer_id' => $customer_id,
    'range'       => $range,
]);
$numrows = count($data);

$db->cdp_query("SELECT * FROM cdb_users WHERE id = :id");
$db->bind(':id', (int) $customer_id);
$sender_data = $db->cdp_registro();


$fecha_inicio = str_replace('-', '/', $fecha_inicio);
$fecha_fin = str_replace('-', '/', $fecha_fin);


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
    <title><?php echo $lang['report-text83'] ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>

    <link rel="stylesheet" href="assets/template/assets/libs/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">


    <style type="text/css">
        .scrollable-menu {
            height: auto;
            max-height: 300px;
            overflow-x: hidden;
        }

        .card-outline {
            border-top: 3px solid #bbb;
        }
    </style>

</head>

<body>
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->
    <?php $agencyrow = $core->cdp_getBranchoffices(); ?>


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

                    </div>
                </div>
            </div>

            <!-- Action part -->
            <!-- Button group part -->
            <div class="bg-light">
                <div class="row justify-content-center">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-12">

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Action part -->


            <div class="container-fluid">

                <div class="row">
                    <!-- Column -->

                    <div class="col-lg-12 col-xl-12 col-md-12">

                        <div class="card card-outline">
                            <h3 class="card-title  ml-4 mt-3"> <?php echo $lang['report-text83'] ?>
                                <br>
                                [<?php echo $fecha_inicio . ' - ' . $fecha_fin; ?>]

                            </h3>

                            <h4 class="card-title  ml-4 mt-3">

                                <?php echo $lang['report-text82'] ?>: <?php echo $sender_data->fname . ' ' . $sender_data->lname; ?>
                            </h4>

                            <div class="card-body">

                                <table id="zero_config" class="table table-condensed table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th><b>Consolidation</b></th>
                                            <th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
                                            <th class="text-center"><b>Discount</b></th>
                                            <th class="text-center"><b><?php echo $lang['lstatusinvoice'] ?></b></th>
                                            <th class="text-center"><b><?php echo $lang['modal-text20'] ?></b></th>
                                            <th class="text-center"><b><?php echo $lang['leftorder110'] ?></b></th>
                                            <th class="text-center"><b><?php echo $lang['modal-text16'] ?></b></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($numrows > 0) {

                                            $count = 0;
                                            $sumador_pendiente = 0;
                                            $sumador_total = 0;
                                            $sumador_pagado = 0;
                                            foreach ($data as $row) {

                                                list($text_status, $label_class) = cdp_fsPayStatusLabel($row->pay_status);

                                                $sumador_pendiente += $row->balance_ghs;
                                                $sumador_total     += $row->amount_ghs;
                                                $sumador_pagado    += $row->paid_ghs;

                                        ?>
                                        <tr class="card-hover">

                                            <td><b><a href="financial_sheet_consolidation.php?id=<?php echo (int) $row->consolidate_id; ?>"><?php echo htmlspecialchars($row->consol_no); ?></a></b></td>

                                            <td class="text-center">
                                                <?php echo htmlspecialchars(date('Y-m-d', strtotime($row->billed_at))); ?>
                                            </td>


                                            <td class="text-center">
                                                <?php echo $row->discount_ghs > 0 ? '₵' . number_format($row->discount_ghs, 2) : '—'; ?>
                                            </td>

                                            <td class="text-center">
                                                <span class="label label-large <?php echo $label_class; ?>"><?php echo htmlspecialchars($text_status); ?></span>

                                            </td>

                                            <td class="text-center">
                                                <?php echo '₵' . number_format($row->amount_ghs, 2); ?>
                                            </td>

                                            <td class="text-center">
                                                <?php echo '₵' . number_format($row->paid_ghs, 2); ?>
                                            </td>

                                            <td class="text-center">
                                                <b><?php echo '₵' . number_format($row->balance_ghs, 2); ?></b>
                                            </td>
                                        </tr>


                                    </tbody>

                                <?php $count++;
                                            } ?>

                            <?php } ?>
                            <tfoot>
                                <tr class="card-hover">
                                    <td class="text-left"><b><?php echo $lang['report-text53'] ?></b></td>

                                    <td colspan="3"></td>
                                    <td class="text-center  ">
                                        <b><?php echo cdb_money_format($sumador_total); ?> </b>
                                    </td>

                                    <td class="text-center  ">
                                        <b><?php echo cdb_money_format($sumador_pagado); ?> </b>
                                    </td>

                                    <td class="text-center  ">
                                        <b><?php echo cdb_money_format($sumador_pendiente); ?> </b>
                                    </td>

                                </tr>

                            </tfoot>
                                </table>

                                <div class="pull-right">

                                    <a class="btn btn-danger" href="report_customers_balance_list.php"><?php echo $lang['report-text84'] ?></a>
                                </div>

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

</body> 

</html>