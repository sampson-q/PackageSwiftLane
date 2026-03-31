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



$sql = "SELECT * FROM cdb_add_order where order_payment_method !=1  
            and order_date between '$fecha_inicio'  and '$fecha_fin'
            $sWhere
            
             order by order_id desc 
             ";


$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql);
$data = $db->cdp_registros();


$db->cdp_query("SELECT * FROM cdb_users where id= '" . $customer_id . "'");
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
                                            <th><b><?php echo $lang['ltracking'] ?></b></th>
                                            <th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
                                            <th class="text-center"><b><?php echo $lang['payment_text2'] ?></b></th>
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

                                                $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
                                                $sender_data = $db->cdp_registro();



                                                $db->cdp_query('SELECT  IFNULL(sum(total), 0)  as total  FROM cdb_charges_order WHERE order_id=:order_id');

                                                $db->bind(':order_id', $row->order_id);

                                                $db->cdp_execute();

                                                $sum_payment = $db->cdp_registro();

                                                $pendiente = $row->total_order - $sum_payment->total;

                                                if ($row->status_invoice == 1) {
                                                    $text_status = $lang['invoice_paid'];
                                                    $label_class = "label-success";
                                                } else if ($row->status_invoice == 2) {
                                                    $text_status = $lang['invoice_pending'];
                                                    $label_class = "label-warning";
                                                } else if ($row->status_invoice == 3) {
                                                    $text_status = $lang['invoice_due'];
                                                    $label_class = "label-danger";
                                                }



                                                $sumador_pendiente += $pendiente;
                                                $sumador_total += $row->total_order;
                                                $sumador_pagado += $sum_payment->total;




                                        ?>
                                        <tr class="card-hover">

                                            <td><b><a data-toggle="modal" data-target="#charges_list" data-id="<?php echo $row->order_id; ?>"><?php echo $row->order_prefix . $row->order_no; ?></a></b></td>

                                            <td class="text-center">
                                                <?php echo $row->order_date; ?>
                                            </td>


                                            <td class="text-center">
                                                <?php echo $row->due_date; ?>
                                            </td>

                                            <td class="text-center">
                                                <span class="label label-large <?php echo $label_class; ?>"><?php echo $text_status; ?></span>

                                            </td>

                                            <td class="text-center">
                                                <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
                                            </td>

                                            <td class="text-center">
                                                <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($sum_payment->total); ?>
                                            </td>

                                            <td class="text-center">
                                                <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($pendiente); ?>
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