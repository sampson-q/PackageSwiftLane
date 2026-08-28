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



require_once('helpers/querys.php');
require_once('helpers/fs_reports.php');

$db = new Conexion;

$range = $_REQUEST['range'];
$agency_courier = intval($_REQUEST['agency_courier']);
$pay_mode = intval($_REQUEST['pay_mode']);
$customer_id = intval($_REQUEST['customer_id']);

$sWhere = "";


if ($agency_courier > 0) {

    $sWhere .= " and agency = '" . $agency_courier . "'";
}


if ($customer_id > 0) {

    $sWhere .= " and sender_id = '" . $customer_id . "'";
}

if ($pay_mode > 0) {

    $sWhere .= " and order_payment_method = '" . $pay_mode . "'";
}


if (!empty($range)) {

    $fecha =  explode(" - ", $range);
    $fecha = str_replace('/', '-', $fecha);

    $fecha_inicio = date('Y-m-d', strtotime($fecha[0]));
    $fecha_fin = date('Y-m-d', strtotime($fecha[1]));


    $sWhere .= " and  order_date between '" . $fecha_inicio . "'  and '" . $fecha_fin . "'";
}


// Throttled (was a full-scan UPDATE on every page load):


// Financial Sheet ledger — same source as the on-screen report.
$data = cdp_fsBillingSummary([
    'customer_id' => $customer_id,
    'range'       => $range,
]);
$numrows = count($data);

$fecha = str_replace('-', '/', $fecha);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $direction_layout; ?>">

<head>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">
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
    <link rel="icon" type="image/png" sizes="16x16" href="assets/uploads/favicon.png">

    <title><?php echo $lang['report-text85'] ?></title>

    <link href="assets/custom_dependencies/print_report.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Tajawal&subset=arabic" rel="stylesheet">
    <style>
        * {
            font-family: 'Tajawal';
        }
    </style>
</head>

<body>
    <div id="page-wrap">

        <h2><?php echo $core->site_name; ?><br>
            <?php echo $lang['report-text85'] ?> <br>

            [<?php echo $fecha[0] . ' - ' . $fecha[1]; ?>] <br>


        </h2>


        <table>
            <tr>

                <th class="text-center"></th>
                <th><b><?php echo $lang['ltracking'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['report-text37'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['payment_text2'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['lstatusinvoice'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['modal-text20'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['leftorder110'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['modal-text16'] ?></b></th>



            </tr>

            <?php

            if ($numrows > 0) {

                $count = 1;
                $sumador_pendiente = 0;
                $sumador_total = 0;
                $sumador_pagado = 0;

                foreach ($data as $row) {

                                                                                                                        list($text_status, $label_class) = cdp_fsPayStatusLabel($row->pay_status);
                    $sumador_pendiente += $row->balance_ghs;
                    $sumador_total += $row->amount_ghs;
                    $sumador_pagado += $row->paid_ghs;


            ?>
                    <tr class="card-hovera">
                        <td><?php echo $count; ?></td>

                        <td><b><a data-toggle="modal" data-target="#charges_list" data-id="<?php echo $row->order_id; ?>"><?php echo $row->consol_no; ?></a></b></td>

                        <td class="text-center">
                            <?php echo htmlspecialchars($row->customer); ?>
                        </td>

                        <td class="text-center">
                            <?php echo date('Y-m-d', strtotime($row->billed_at)); ?>
                        </td>


                        <td class="text-center">
                            <?php echo ($row->discount_ghs > 0 ? "₵" . number_format($row->discount_ghs, 2) : "—"); ?>
                        </td>

                        <td class="text-center">
                            <span class="label label-large <?php echo $label_class; ?>"><?php echo $text_status; ?></span>

                        </td>

                        <td class="text-center">
                            <b><?php echo $core->currency; ?></b> <?php echo '₵' . number_format($row->amount_ghs, 2); ?>
                        </td>

                        <td class="text-center">
                            <b><?php echo $core->currency; ?></b> <?php echo '₵' . number_format($row->paid_ghs, 2); ?>
                        </td>

                        <td class="text-center">
                            <b><?php echo $core->currency; ?></b> <?php echo '₵' . number_format($row->balance_ghs, 2); ?>
                        </td>



                    </tr>
                <?php

                    $count++;
                }
                ?>

                <tr>
                    <td class="text-left"><b><?php echo $lang['report-text53'] ?></b></td>

                    <td colspan="5"></td>
                    <td class="text-center  ">
                        <b><?php echo '₵' . number_format($sumador_total, 2); ?> </b>
                    </td>

                    <td class="text-center  ">
                        <b><?php echo '₵' . number_format($sumador_pagado, 2); ?> </b>
                    </td>

                    <td class="text-center  ">
                        <b><?php echo '₵' . number_format($sumador_pendiente, 2); ?> </b>
                    </td>
                </tr>
            <?php
            }
            ?>


        </table>

        <button class='button -dark center no-print' onClick="window.print();" style="font-size:16px; margin-top: 20px;"><?php echo $lang['report-text5'] ?> &nbsp;&nbsp; <i class="fa fa-print"></i></button>
    </div>

</body>

</html>