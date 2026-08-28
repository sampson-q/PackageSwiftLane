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

$customer_id = intval($_REQUEST['customer_id']);
$pay_mode = intval($_REQUEST['pay_mode']);
$range = $_REQUEST['range'];


// Financial Sheet ledger — same source as the on-screen report, so print and
// screen can never disagree. (Was cdb_charges_order, retired since 2022.)
$data = cdp_fsPaymentsReceived([
    'customer_id' => $customer_id,
    'mode'        => cdp_fsModeFromMetPayment($pay_mode),
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

    <title><?php echo $lang['report-text86'] ?></title>
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
            <?php echo $lang['report-text86'] ?> <br>

            [<?php echo $fecha[0] . ' - ' . $fecha[1]; ?>] <br>


        </h2>


        <table>
            <tr>
                <th></th>
                <th class="text-center"><b><?php echo $lang['leftorder98'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['report-text37'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['leftorder287'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['ltracking'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['payment5'] ?></b></th>
            </tr>

            <?php

            if ($numrows > 0) {

                $count = 1;
                $sumador_total = 0;

                foreach ($data as $row) {
                    $sumador_total += $row->amount_ghs;

            ?>
                    <tr>
                        <td class="text-center">
                            <?php echo $count; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $row->id; ?>
                        </td>

                        <td class="text-center">
                            <?php echo date('Y-m-d H:i', strtotime($row->paid_at)); ?>
                        </td>


                        <td class="text-center">
                            <?php echo $row->customer; ?>
                        </td>
                        <td class="text-center">
                            <?php echo cdp_fsModeLabel($row->mode); ?>
                        </td>

                        <td class="text-center">
                            <?php echo ($row->tracking !== "" ? $row->tracking : "—"); ?>
                        </td>

                        <td class="text-center">
                            <?php echo '₵' . number_format($row->amount_ghs, 2); ?>
                        </td>
                    </tr>
                <?php

                    $count++;
                }
                ?>

                <tr>
                    <td class="text-left"><b><?php echo $lang['report-text53'] ?></b></td>


                    <td colspan="5"></td>
                    <td class="text-left">
                        <b><?php echo '₵' . number_format($sumador_total, 2); ?> </b>
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