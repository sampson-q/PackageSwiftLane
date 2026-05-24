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
    <title><?php echo $lang['add-courier'] ?> | <?php echo $core->site_name ?></title>
    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .select2-selection__rendered {
            line-height: 31px !important;
        }

        .select2-container .select2-selection--single {
            height: 35px !important;
        }

        .select2-selection__arrow {
            height: 34px !important;
        }
    </style>
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

        <?php $office = $core->cdp_getOffices(); ?>
        <?php $agencyrow = $core->cdp_getBranchoffices(); ?>
        <?php $courierrow = $core->cdp_getCouriercom(); ?>
        <?php $statusrow = $core->cdp_getStatus(); ?>
        <?php $packrow = $core->cdp_getPack(); ?>
        <?php $payrow = $core->cdp_getPayment(); ?>
        <?php $paymethodrow = $core->cdp_getPaymentMethod(); ?>
        <?php $itemrow = $core->cdp_getItem(); ?>
        <?php $moderow = $core->cdp_getShipmode(); ?>
        <?php $driverrow = $user->cdp_userAllDriver(); ?>
        <?php $delitimerow = $core->cdp_getDelitime(); ?>
        <?php $track = $core->cdp_order_track(); ?>
        <?php $categories = $core->cdp_getCategories(); ?>

        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><i class="ti-package" aria-hidden="true"></i> <?php echo $lang['left82'] ?></h4> <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="container-fluid">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1) { ?>

                        <div class="alert alert-info" id="success-alert">
                            <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
                                Your collection has been created successfully!
                            </p>
                        </div>
                    <?php
                    } else if (isset($_GET['success']) && $_GET['success'] == 0) { ?>

                        <div class="alert alert-danger" id="success-alert">
                            <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
                                <?php echo $lang['message_ajax_error2']; ?>
                            </p>
                        </div>
                    <?php
                    }
                    ?>


                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-information-outline" style="color:#20c997"></i><?php echo $lang['langs_010']; ?></h4>
                                    <hr>

                                    <div class="resultados_ajax_add_user_modal_sender"></div>
                                    <div class="row">
                                        <div class="col-md-12 ">

                                            <label class="control-label col-form-label"><?php echo $lang['sender_search_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select" id="sender_id" name="sender_id" disabled="">
                                                            <option value="<?php echo $userData->id; ?>" selected> <?php echo $userData->fname . ' ' . $userData->lname; ?></option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">

                                                    <input type="hidden" name="sender_id_temp" id="sender_id_temp" value="<?php echo $userData->id; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12 ">

                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['sender_search_address_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="sender_address_id" name="sender_address_id">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button id="add_address_sender" data-type_user="user_customer" data-toggle="modal" data-target="#myModalAddUserAddresses" type="button" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-information-outline" style="color:#20c997"></i><?php echo $lang['left334']; ?></h4>
                                    <hr>
                                    <div class="resultados_ajax_add_user_modal_recipient"></div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['recipient_search_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select" id="recipient_id" name="recipient_id">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button id="add_recipient" type="button" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipient" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12">

                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['recipient_search_address_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="recipient_address_id" name="recipient_address_id" disabled="">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button disabled id="add_address_recipient" type="button" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipientAddresses" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Row: Agencia/Sucursal y Oficina origen (parametrizable) -->
                    <?php
                    $agency_default_id = 0;
                    if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
                        require_once(__DIR__ . '/../../helpers/querys.php');
                        $agency_default_id = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
                    }
                    ?>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="agency" class="control-label col-form-label"><?php echo isset($lang['left201']) ? $lang['left201'] : 'Agencia / Sucursal'; ?></label>
                                            <div class="input-group mb-3">
                                                <?php if ($agency_default_id > 0) { ?><input type="hidden" name="agency" id="agency" value="<?php echo $agency_default_id; ?>"><?php } ?>
                                                <select class="custom-select col-12" id="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" name="<?php echo ($agency_default_id > 0) ? '' : 'agency'; ?>" required <?php echo ($agency_default_id > 0) ? 'disabled style="pointer-events:none;background:#e9ecef;"' : ''; ?>>
                                                    <?php foreach ($agencyrow as $row) : ?>
                                                        <option value="<?php echo (int)$row->id; ?>" <?php echo ($agency_default_id > 0 && (int)$row->id === $agency_default_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row->name_branch ?? ''); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="origin_off" class="control-label col-form-label"><?php echo isset($lang['add-title14']) ? $lang['add-title14'] : 'Oficina origen'; ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="origin_off" name="origin_off">
                                                    <?php foreach ($office as $row) : ?>
                                                        <option value="<?php echo (int)$row->id; ?>"><?php echo htmlspecialchars($row->name_off ?? ''); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Row -->


                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-book-multiple" style="color:#20c997"></i> <?php echo $lang['add-title13'] ?></h4>
                                    <br>
                                    <div class="row">

                                        <div class="form-group col-md-6">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['itemcategory'] ?></label>
                                            <div class="input-group">
                                                <select class="custom-select col-12" id="order_item_category" name="order_item_category" required>
                                                    <?php foreach ($categories as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_item; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['add-title17'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_package" name="order_package">
                                                    <?php foreach ($packrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_pack; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>


                                        <div class="col-md-4" style="display: none;">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title1555'] ?></i></label>
                                            <div class="input-group">
                                                <div class="input-group-append" data-target="#datetimepicker1" data-toggle="datetimepicker">
                                                    <div class="input-group-text"><i style="color:#ff0000" class="fa fa-calendar"></i></div>
                                                </div>
                                                <input type='text' class="form-control" name="order_date" id="order_date" placeholder="--<?php echo $lang['left206'] ?>--" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title1555'] ?>" readonly value="<?php echo date('Y-m-d'); ?>" />
                                            </div>
                                        </div>
                                    </div>


                                    <div class="row">
                                        <div class="col-md-4">
                                            <div>
                                                <label class="control-label" id="selectItem"> <?php echo $lang['leftorder15']; ?></label>
                                            </div>

                                            <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple="multiple" type="file" style="display: none;" onchange="cdp_validateZiseFiles(); cdp_preview_images();" />


                                            <button type="button" id="openMultiFile" class="btn btn-default  pull-left  mb-4"> <i class='fa fa-paperclip' id="openMultiFile" style="font-size:18px; cursor:pointer;"></i> <?php echo $lang['leftorder16']; ?> </button>


                                        </div>
                                    </div>

                                    <div class="col-md-12 row" id="image_preview"></div>

                                    <div class="col-md-4 mt-4">
                                        <div id="clean_files" class="hide">
                                            <button type="button" id="clean_file_button" class="btn btn-danger ml-3">
                                                <i class='fa fa-trash' style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder17']; ?>
                                            </button>

                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="resultados_file col-md-4 pull-right mt-4">


                                        </div>
                                    </div>


                                    <div class="row">

                                        <div class="resultados_file col-md-4 pull-right mt-4">

                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h4 class="card-title">
                                                <i class="fas fas fa-boxes" style="color:#20c997"></i>
                                                <?php echo $lang['left212'] ?>
                                            </h4>
                                        </div>
                                    </div>

                                    <!-- Listas item caja -->
                                    <div id="data_items"></div>

                                    <!-- Boton adicionar caja al listado -->
                                    <div class="col-md-3 text-left">
                                        <button type="button" onclick="addPackage()" name="add_rows" id="add_rows" class="btn btn-outline-dark"><span class="fa fa-plus"></span> <?php echo $lang['left231'] ?></button>
                                    </div>

                                    <div><br></div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="text-secondary text-left"><?php echo $lang['leftorder17713'] ?></span>
                                        </div>
                                        <div class="col-md-1">
                                            <span class="text-secondary text-center" id="total_weight">0.00</span>
                                        </div>
                                        <div class="col-md-1 offset-3">
                                            <span class="text-secondary text-center" id="total_vol_weight">0.00</span>
                                        </div>
                                        <div class="col-md-1">
                                            <span class="text-secondary text-center" id="total_fixed">0.00</span>
                                        </div>
                                        <div class="col-md-1">
                                            <span class="text-secondary text-center" id="total_declared">0.00</span>
                                        </div>
                                    </div>
                                    <hr>

                                    <div class="row" style="margin-top: 20px;">
                                        <div class="table-responsive d-none" id="table-totals">
                                            <table id="insvoice-item-table" class="table">
                                                <tfoot>
                                                    <tr class="card-hover">
                                                        <td colspan="4" class="text-right"><b><?php echo $lang['leftorder2021'] ?></b></td>
                                                        <td colspan="1"></td>
                                                        <td class="text-right">
                                                            <?php
                                                            if ($core->for_symbol !== null) {
                                                            ?>
                                                                <b> <?php echo $core->for_symbol; ?> </b>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span id="subtotal"> 0.00</span>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>


                                            <!-- Listado de impuestos-->

                                            <div  class="card" id="row">
                                                <div class="col-md-6">
                                                    <h4 class="card-title">
                                                        <i class="ti ti-briefcase " style="color:#20c997"></i>
                                                        <?php echo $lang['messageerrorform30'] ?>
                                                    </h4>
                                                </div>
                                                <hr>
                                                <div class="row row-shadow input-container"> 
                                                  <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['left905'] ?> &nbsp; <?php echo $core->weight_p; ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="<?php echo $core->value_weight; ?>" name="price_lb" id="price_lb" style="border: 1px solid red;" readonly>
                                                        </div>
                                                     </div>
                                                  </div>


                                                  <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder21'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" value="0" name="discount_value" id="discount_value" class="form-control form-control-sm" readonly>
                                                        </div>
                                                        
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="discount"> 0.00</span>
                                                        
                                                     </div>
                                                  </div>

                                                  <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder22'] ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="100" name="insured_value" id="insured_value" style="border: 1px solid darkorange;" readonly>
                                                        </div>
                                                        
                                                        <td class="text-center" id="insured_label"></td>
                                                        
                                                     </div>
                                                  </div>


                                                  <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder24'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="<?php echo $core->insurance; ?>" name="insurance_value" id="insurance_value" style="border: 1px solid darkorange;" readonly>
                                                        </div>
                                                        
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="insurance"> 0.00</span>
                                                        
                                                     </div>
                                                  </div>


                                                  <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder25'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="<?php echo $core->c_tariffs; ?>" name="tariffs_value" id="tariffs_value" readonly>
                                                        </div>
                                                        
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="total_impuesto_aduanero"> 0.00</span>
                                                        
                                                     </div>
                                                  </div>


                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['leftorder67'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                            <div class="input-group">
                                                              <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="<?php echo $core->tax; ?>" name="tax_value" id="tax_value" readonly>
                                                            </div>
                                                            
                                                            <?php
                                                            if ($core->for_symbol !== null) {
                                                            ?>
                                                                <b> <?php echo $core->for_symbol; ?> </b>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span id="impuesto"> 0.00</span>
                                                            
                                                         </div>
                                                    </div>
                                                
                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['leftorder19'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                            <div class="input-group">
                                                              <input type="text" onchange="calculateFinalTotal(this);" value="<?php echo $core->declared_tax; ?>" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" name="declared_value_tax" id="declared_value_tax" readonly>
                                                            </div>
                                                            
                                                            <?php
                                                            if ($core->for_symbol !== null) {
                                                            ?>
                                                                <b> <?php echo $core->for_symbol; ?> </b>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span id="declared_value_label"> 0.00</span>
                                                            
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['langs_048'] ?> </label>
                                                            <div class="input-group">
                                                              <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" value="0" name="reexpedicion_value" id="reexpedicion_value" readonly>
                                                            </div>
                                                            
                                                            <td class="text-right" id="reexpedicion_label"></td>
                                                            
                                                        </div>
                                                    </div>



                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['leftorder1878'] ?></label>
                                                            <?php
                                                            if ($core->for_symbol !== null) {
                                                            ?>
                                                                <b> <?php echo $core->for_symbol; ?> </b>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span id="fixed_value_label"> 0.00</span>
                                                            
                                                         </div>
                                                    </div>


                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['leftorder2020'] ?></label>
                                                            <?php
                                                            if ($core->for_symbol !== null) {
                                                            ?>
                                                                <b> <?php echo $core->for_symbol; ?> </b>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span id="total_envio" class="green-bold"> 0.00</span>
                                                            
                                                         </div>
                                                    </div>

                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-actions">
                                                <div class="card-body">
                                                    <div class="text-right">
                                                        <input type="hidden" name="total_item_files" id="total_item_files" value="0" />
                                                        <input type="hidden" name="deleted_file_ids" id="deleted_file_ids" />
                                                        <button type="button" name="calculate_invoice" id="calculate_invoice" class="btn btn-info">
                                                            <i class="fas fa-calculator"></i>
                                                            <span class="ml-1">
                                                                <?php echo $lang['leftorder17714'] ?>
                                                            </span>
                                                        </button>
                                                        <button type="submit" name="create_invoice" id="create_invoice" class="btn btn-danger" disabled>
                                                            <i class="fas fa-save"></i>
                                                            <span class="ml-1"><?php echo $lang['left1103'] ?></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" value="<?php echo $core->value_weight; ?>" name="price_lb" id="price_lb">
                            <input type="hidden" value="0" name="discount_value" id="discount_value">
                            <input type="hidden" value="100" name="insured_value" id="insured_value">
                            <input type="hidden" value="<?php echo $core->insurance; ?>" name="insurance_value" id="insurance_value">
                            <input type="hidden" value="<?php echo $core->c_tariffs; ?>" name="tariffs_value" id="tariffs_value">
                            <input type="hidden" value="<?php echo $core->tax; ?>" name="tax_value" id="tax_value">
                            <input type="hidden" value="<?php echo $core->declared_tax; ?>" name="declared_value_tax" id="declared_value_tax">
                            <input type="hidden" value="0" name="reexpedicion_value" id="reexpedicion_value">

                            <input type="hidden" name="core_meter" id="core_meter" value="<?php echo $core->meter; ?>" />
                            <input type="hidden" name="core_min_cost_tax" id="core_min_cost_tax" value="<?php echo $core->min_cost_tax; ?>" />
                            <input type="hidden" name="core_min_cost_declared_tax" id="core_min_cost_declared_tax" value="<?php echo $core->min_cost_declared_tax; ?>" />
                        </div>
                    </div>
            </form>

            <?php include('views/modals/modal_add_user_shipment.php'); ?>
            <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
            <?php include('views/modals/modal_add_addresses_user.php'); ?>
            <?php include('views/modals/modal_add_addresses_recipient.php'); ?>

        </div>

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

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>
    <script src="dataJs/pickup_add.js"></script>

</body>

</html>