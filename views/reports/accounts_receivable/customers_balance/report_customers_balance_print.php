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



require_once('helpers/querys.php');

$db = new Conexion;

$customer_id = intval($_REQUEST['customer_id']);
$range = $_REQUEST['range'];


$sWhere = "";


if ($customer_id > 0) {

    $sWhere .= " and b.sender_id = '" . $customer_id . "'";
}

if (!empty($range)) {

    $fecha =  explode(" - ", $range);
    $fecha = str_replace('/', '-', $fecha);

    $fecha_inicio = date('Y-m-d', strtotime($fecha[0]));
    $fecha_fin = date('Y-m-d', strtotime($fecha[1]));


    $sWhere .= " and  b.order_date between '" . $fecha_inicio . "'  and '" . $fecha_fin . "'";
}

$sql = "SELECT a.id, b.order_id, a.lname,a.fname, b.order_prefix, b.order_no FROM cdb_users as a
    INNER JOIN cdb_add_order as b on a.id =b.sender_id
    where b.order_payment_method!=1
    $sWhere
    group by a.id

     ";



$query_count = $db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql);
$data = $db->cdp_registros();

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

    <title><?php echo $lang['report-text81'] ?></title>

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
            <?php echo $lang['report-text81'] ?> <br>

            [<?php echo $fecha[0] . ' - ' . $fecha[1]; ?>] <br>


        </h2>


        <table>
            <tr>
                <th></th>
                <th class="text-left"><b><?php echo $lang['report-text82'] ?></b></th>
                <th class="text-left"><b><?php echo $lang['modal-text16'] ?></b></th>
            </tr>

            <?php

            if ($numrows > 0) {

                $count = 0;
                $order_pagado = 0;
                $order_total = 0;
                $sumador_balance = 0;

                foreach ($data as $row) {

                    $db->cdp_query('SELECT  total_order, order_id FROM cdb_add_order WHERE sender_id=:id and  order_payment_method!=1 ');

                    $db->bind(':id', $row->id);

                    $db->cdp_execute();

                    $a = $db->cdp_registros();

                    foreach ($a as $key) {

                        $db->cdp_query('SELECT  IFNULL(sum(total), 0)  as total  FROM cdb_charges_order WHERE order_id=:order_id');

                        $db->bind(':order_id', $key->order_id);

                        $db->cdp_execute();

                        $sum_payment = $db->cdp_registro();

                        $order_pagado += $sum_payment->total;

                        $order_total += $key->total_order;


                        $total_balance = $order_total - $order_pagado;
                    }
                    $sumador_balance += $total_balance;



                    $count++;

                    $order_pagado = 0;
                    $order_total = 0;

            ?>

                    <tr>
                        <td><b><?php echo $count; ?> </b></td>
                        <td class="text-left">
                            <?php echo $row->fname . ' ' . $row->lname; ?>
                        </td>

                        <td class="text-left">
                            <?php echo cdb_money_format($total_balance); ?>
                        </td>
                    </tr>
                <?php
                }
                ?>

                <tr>
                    <td class="text-left"><b>TOTAL</b></td>


                    <td></td>
                    <td class="text-left">
                        <b><?php echo cdb_money_format($sumador_balance); ?> </b>
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