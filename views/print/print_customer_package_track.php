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

if (isset($_GET['id'])) {
    $data = cdp_getCustomerPackagePrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("courier_list.php");
}



$row = $data['data'];

$db->cdp_query("SELECT * FROM cdb_customers_packages_detail WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $row->order_pay_mode . "'");
$met_payment = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->order_courier . "'");
$courier_com = $db->cdp_registro();

$fecha = date("Y-m-d :h:i A", strtotime($row->order_datetime));

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_category where id= '" . $row->order_item_category . "'");
$category = $db->cdp_registro();




$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row->order_prefix . $row->order_no . "'");
$address_order = $db->cdp_registro();


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $direction_layout; ?>" lang="en">

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

    <title>Tracking - <?php echo $row->order_prefix . $row->order_no; ?></title>
    <link type='text/css' href='assets/custom_dependencies/print.css' rel='stylesheet' />

    <link rel="stylesheet" href="assets/css/input-css/intlTelInput.css">

    <link rel="stylesheet" type="text/css" href="assets/select2/dist/css/select2.min.css">
    <link href="assets/css/style.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/jquery-ui.css" type="text/css" />
    <link href="assets/css/front.css" rel="stylesheet" type="text/css">

    <link rel="stylesheet" href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">

</head>

<body>
    <div id="page-wrap">
        <table>
            <tr>
                <td style="border: 0;  text-align: left" width="18%">
                    <div id="logo">
                        <?php echo ($core->logo) ? '<img src="assets/' . $core->logo . '" alt="' . $core->site_name . '" width="190" height="39"/>' : $core->site_name; ?>
                </td>
                <td style="border: 0;  text-align: center" width="56%">
                    <?php echo $lang['inv-shipping1'] ?>: <?php echo $core->c_nit; ?> </br>
                    <?php echo $lang['inv-shipping2'] ?>: <?php echo $core->c_phone; ?></br>
                    <?php echo $lang['inv-shipping3'] ?>: <?php echo $core->site_email; ?></br>
                    <?php echo $lang['inv-shipping4'] ?>: <?php echo $core->c_address; ?> - <?php echo $core->c_country; ?>-<?php echo $core->c_city; ?>
                </td>
                <td style="border: 0;  text-align: center" width="48%">
                    </br><img src='https://barcode.tec-it.com/barcode.ashx?data=<?php echo $row->order_prefix . $row->order_no; ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50' alt='' />
                </td>
            </tr>
        </table>
        <hr>
        <div id="customer">

            <table align="left" width="55%">
                <tr>
                    <td style="border: 1px solid white; text-align: left">
                        <strong><?php echo $lang['inv-shipping5'] ?></strong> </br>
                        <table id="items">
                            <b><?php echo $sender_data->fname . " " . $sender_data->lname; ?></b></br> </br>
                            <?php echo $address_order->sender_address; ?> </br>
                            <?php echo $address_order->sender_country . " | " . $address_order->sender_city; ?> </br>
                            <?php echo $sender_data->phone; ?> </br>
                            <?php echo $sender_data->email; ?>
                        </table>
                    </td>
                </tr>
            </table>

            <table align="right" width="45%">

                <tr>
                    <td class="meta-td" style="line-height: 7px">
                        <span style="color:white;"><?php echo $lang['itemcategory'] ?></span>
                    </td>
                    <td><?php if ($category != null) {
                            echo $category->name_item;
                        } ?></td>
                </tr>
                <tr>
                    <td class="meta-td" style="line-height: 7px">
                        <span style="color:white;"><?php echo $lang['inv-shipping7'] ?></span>

                    </td>

                    <td>
                        <?php if ($courier_com != null) {
                            echo $courier_com->name_com;
                        } ?>
                    </td>
                </tr>
                <tr>
                    <td class="meta-td" style="line-height: 7px">
                        <span style="color:white;"><?php echo $lang['add-title22'] ?></span>
                    </td>

                    <td>
                        <?php if ($shipping_mode != null) {
                            echo $shipping_mode->ship_mode;
                        } ?>
                    </td>
                </tr>
                <tr>
                    <td class="meta-td" style="line-height: 7px">
                        <span style="color:white;"><?php echo $lang['inv-shipping8'] ?></span>
                    </td>
                    <td><?php echo $fecha; ?></td>
                </tr>
                <tr>
                    <td class="meta-td" style="line-height: 7px">
                        <span style="color:white;"><?php echo $lang['inv-shipping9'] ?></span>
                    </td>
                    <td><b><?php echo $row->order_prefix . $row->order_no; ?></b></td>
                </tr>
            </table>
        </div>

        <table id="items">
            <tr>
                <th style="color:white;" width="5%"><b><?php echo $lang['left214'] ?></b></th>
                <th style="color:white;" width="31%"><b><?php echo $lang['left213'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left215'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left216'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left217'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left218'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left219'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left231c9'] ?></b></th>
                <th style="color:white;" width="12%"><b><?php echo $lang['left239'] ?></b></th>
            </tr>
            <?php

            $sumador_total = 0;
            $sumador_libras = 0;
            $sumador_volumetric = 0;
            $sumador_valor_declarado = 0;
            $sumador_fixed_charge = 0;
            $max_fixed_charge = 0;
            $precio_total = 0;
            $total_impuesto = 0;
            $total_seguro = 0;
            $total_peso = 0;
            $total_descuento = 0;
            $total_impuesto_aduanero = 0;
            $total_valor_declarado = 0;


            foreach ($order_items as $row_item) {

                $description_item = $row_item->order_item_description;
                $weight_item =  (float) $row_item->order_item_weight;
                $length_item =  (float) $row_item->order_item_length;
                $width_item =  (float) $row_item->order_item_width;
                $height_item =  (float) $row_item->order_item_height;
                $meter = (float) $row->volumetric_percentage;

                $total_metric =  ($length_item *  $width_item *  $height_item) /  $meter;
                $total_metric = round($total_metric, 2);

                $sumador_libras += $weight_item; //Sumador
                $sumador_volumetric += $total_metric; //Sumador
                // calculate weight x price
                if ($sumador_libras > $sumador_volumetric) {
                    $calculate_weight = $sumador_libras;
                } else {
                    $calculate_weight = $sumador_volumetric;
                }

                $sumador_total =  ($calculate_weight *  (float)$row->value_weight);

                (float)$sumador_valor_declarado += $row_item->order_item_declared_value;
                (float) $sumador_fixed_charge += $row_item->order_item_fixed_value;
                (float) $max_fixed_charge += $row_item->order_item_fixed_value;

                if ($sumador_total > $core->min_cost_tax) {
                    $total_impuesto = $sumador_total * $row->tax_value / 100;
                }

                if ($sumador_valor_declarado > $core->min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $row->declared_value / 100;
                }


            ?>

                <tr class="item-row">
                    <td><?php echo $row_item->order_item_quantity; ?></td>
                    <td><?php echo $description_item; ?></td>
                    <td><?php echo $weight_item; ?></td>
                    <td><?php echo $row_item->order_item_length; ?></td>
                    <td><?php echo $row_item->order_item_width; ?></td>
                    <td><?php echo $row_item->order_item_height; ?></td>
                    <td><?php echo $total_metric; ?></td>
                    <td><?php echo $row_item->order_item_fixed_value; ?></td>
                    <td><?php echo $row_item->order_item_declared_value; ?></td>

                </tr>
            <?php

            }

            $total_descuento = $sumador_total * $row->tax_discount / 100;

            $total_peso = $weight_item + $total_metric;

            $total_seguro = $row->tax_insurance_value * $row->total_insured_value / 100;

            $total_impuesto_aduanero = ($total_peso * $row->tax_custom_tariffis_value) / 100;

            $total_envio = ($sumador_total - $total_descuento) + $total_impuesto + $total_seguro + $total_impuesto_aduanero + $max_fixed_charge + $total_valor_declarado + $row->total_reexp;

            $sumador_total = cdb_money_format_bar($sumador_total);
            $sumador_libras = $sumador_libras;
            $sumador_volumetric = $sumador_volumetric;
            $total_envio = cdb_money_format($total_envio);
            $total_seguro = cdb_money_format_bar($total_seguro);
            $total_peso = $total_peso;
            $total_impuesto_aduanero = cdb_money_format_bar($total_impuesto_aduanero);
            $total_impuesto = cdb_money_format_bar($total_impuesto);
            $sumador_valor_declarado = cdb_money_format_bar($sumador_valor_declarado);
            $total_valor_declarado = cdb_money_format_bar($total_valor_declarado);

            ?>

        </table>


        <div><br></div>

        <table align="left" width="45%" class="separador">
            <tr class="card-hover">
                <td colspan="2"><b><?php echo $lang['left905'] ?> &nbsp; <?php echo $core->weight_p; ?>:</b> <?php echo $row->value_weight; ?></td>
                <td colspan="3"><b><?php echo $lang['left232'] ?>:</b> <span id="total_libras"><?php echo $sumador_libras; ?></span></td>

            </tr>

            <tr class="card-hover">
                <td colspan="2"><b><?php echo $lang['left234'] ?>:</b> <span id="total_volumetrico"><?php echo $sumador_volumetric; ?></span></td>
                <td colspan="3"><b><?php echo $lang['left236'] ?></b>: <span id="total_peso"><?php echo $total_peso; ?></span></td>
            </tr>

        </table>

        <table align="right" width="45%" class="separador">

            <tr class="card-hover">
                <td colspan="3" align="center"><b><?php echo $lang['leftorder2021'] ?></b></td>
                <td colspan="3" align="center"><?php echo $sumador_total; ?></td>
            </tr>
        </table>

        <table id="items">
            <tr>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder21'] ?> <?php echo $row->tax_discount; ?> <?php echo $lang['leftorder222221'] ?> </b></th>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder24'] ?> <?php echo $row->tax_insurance_value; ?> <?php echo $lang['leftorder222221'] ?> </b></th>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder25'] ?> <?php echo $row->tax_custom_tariffis_value; ?> <?php echo $lang['leftorder222221'] ?></b></th>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder67'] ?> <?php echo $row->tax_value; ?> <?php echo $lang['leftorder222221'] ?> </b></th>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder23'] ?></b> </th>
                <th colspan="2" style="color:white;" align="center"><b><?php echo $lang['leftorder19'] ?> <?php echo $row->declared_value; ?> <?php echo $lang['leftorder222221'] ?> </b></th>
                <td colspan="2" bgcolor="#6c757d" style="color:white;" align="center"><b>Total envío</b></th>
            </tr>
            <tr class="card-hover">
                <td colspan="2" align="center"><?php echo $total_descuento; ?></td>
                <td colspan="2" align="center" id="insurance"><?php echo $total_seguro; ?></td>
                <td colspan="2" align="center" id="total_impuesto_aduanero"><?php echo $total_impuesto_aduanero; ?></td>
                <td colspan="2" align="center" id="impuesto"><?php echo $total_impuesto; ?></td>
                <td colspan="2" align="center"><?php echo $sumador_valor_declarado; ?></td>
                <td colspan="2" align="center"><?php echo $total_valor_declarado; ?></td>
                <td colspan="2" align="center" class="ancho-td"><?php echo $core->currency; ?> &nbsp; <?php echo $total_envio; ?></td>


            </tr>
        </table>

        <!--    end related transactions -->

        <div id="terms">
            <h5><?php echo $lang['inv-shipping18'] ?></h5>
            <table id="related_transactions" style="width: 100%">
                <p align="justify"><?php echo cdp_cleanOut($core->interms); ?></p>
            </table>

            <?php



            $db->cdp_query("SELECT * FROM cdb_customer_package_files where order_id='" . $_GET['id'] . "' ORDER BY date_file");
            $files_order = $db->cdp_registros();
            $numrows = $db->cdp_rowCount();


            if ($numrows > 0) {
            ?>
                <div class="col-lg-12">
                    <h5 class=""> <?php echo $lang['print-text10'] ?></h5>
                    <div class="col-md-12 row">

                        <?php
                        $count = 0;
                        $count_hr = 0;

                        foreach ($files_order as $file) {

                            $date_add = date("Y-m-d h:i A", strtotime($file->date_file));

                            $src = 'assets/images/no-preview.jpeg';

                            if (
                                $file->file_type == 'jpg' ||
                                $file->file_type == 'jpeg' ||
                                $file->file_type == 'png' ||
                                $file->file_type == 'ico'
                            ) {

                                $src = $file->url;

                                $count++;
                        ?>

                                <div class="col-md-3" id="file_delete_item_<?php echo $file->id; ?>">

                                    <div style="text-align: center; margin-bottom: 5px">
                                        <img style="width: 120px; height: 120px;" class="" src="<?php echo $src; ?>">
                                    </div>
                                </div>
                        <?php
                            }
                        } ?>
                    </div>

                </div>

            <?php
            } ?>
            </br></br></br></br>
            <table id="signing">
                <tr class="noBorder">
                    <td align="center">
                        <h4></h4>
                    </td>
                    <td align="center">
                        <h4></h4>
                    </td>
                </tr>
                <tr class="noBorder">
                    <td align="center"><?php echo $core->signing_company; ?></td>
                    <td align="center"><?php echo $core->signing_customer; ?></td>
                </tr>
            </table>
        </div>
        <button class='button -dark center no-print' onClick="window.print();" style="font-size:16px"><?php echo $lang['inv-shipping19'] ?>&nbsp;&nbsp; <i class="fa fa-print"></i></button>
    </div>
    </div>

</body>

</html>