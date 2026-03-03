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

$userData = $user->cdp_getUserData();

$db = new Conexion;

if (isset($_GET['id_order'])) {
  $data = cdp_getCustomerPackagePrint($_GET['id_order']);
}


if (!isset($_GET['id_order']) or $data['rowCount'] != 1) {
  cdp_redirect_to("customer_packages_list.php");
}

$row_order = $data['data'];

$userData = $user->cdp_getUserData();

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($row_order->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$track_order = $row_order->order_prefix . $row_order->order_no;

$db->cdp_query("SELECT * FROM cdb_met_payment WHERE id=2 ");
$active_paypal = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment WHERE id=3 ");
$active_stripe = $db->cdp_registro();

if ($active_stripe->is_active == 1) {

  $public_key_stripe = $active_stripe->public_key;
}

$db->cdp_query("SELECT * FROM cdb_met_payment WHERE id=4 ");
$active_paystack = $db->cdp_registro();

if ($active_paystack->is_active == 1) {

  $public_key_paystack = $active_paystack->public_key;
}


$db->cdp_query("SELECT * FROM cdb_met_payment WHERE id=5 ");
$active_wire = $db->cdp_registro();

$payrow = $core->cdp_getPayment();

?>



<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="keywords" content="Courier DEPRIXA-Integral Web System" />
  <meta name="author" content="Jaomweb">
  <title><?php echo $lang['payment-gateway-text3']; ?> | <?php echo $core->site_name ?></title>
  <!-- Favicon icon -->
  <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
  <?php include 'views/inc/head_scripts.php'; ?>
  <link href="assets/template/dist/css/stripe_styles.css" rel="stylesheet">

</head>

<body>

  <script src="https://js.stripe.com/v3/"></script>
  <script src="https://polyfill.io/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>

  <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $active_paypal->paypal_client_id; ?>&currency=USD&disable-funding=credit,card"></script>

  <script src="https://js.paystack.co/v1/inline.js"></script>


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

      <!-- ============================================================== -->
      <!-- Container fluid  -->
      <!-- ============================================================== -->
      <div class="container-fluid mb-4">

        <div class="row">
          <div class="col-lg-12">
            <div class="card">
              <div class="card-body">

                <!-- For demo purpose -->
                <div class="row mb-4">
                  <div class="col-lg-12 mx-auto text-center">
                    <h3 class="display-7"><b class="text-danger"><?php echo $lang['payment-gateway-text2']; ?></b> <span><?php echo $row_order->order_prefix . $row_order->order_no; ?></span></h3>
                  </div>

                  <div class="col-lg-12 mx-auto text-center mt-4">

                    <h3><span><?php echo $lang['leftorder132']; ?> : <b><?php echo  cdb_money_format($row_order->total_order); ?> <?php echo $core->currency; ?> </b></span></h3>

                  </div>
                </div> <!-- End -->

                <div class="row">
                  <div class="col-lg-12 mx-auto">
                    <div id="resultados_ajax"></div>
                    <div class="card ">
                      <div class="card-header">
                        <div class="shadow-sm pt-3 pl-3 pr-3 pb-1" style="background-color: #3e5569 !important;">

                          <ul role="tablist" class="nav bg-light nav-pills rounded nav-fill mb-3">

                            <?php

                            if ($active_stripe->is_active == 1) {

                            ?>
                              <li class="nav-item">
                                <a data-toggle="pill" href="#stripe" class="nav-link active">
                                  <i class="fa fa-cc-stripe mr-2"></i>
                                  <?php echo $lang['left533020034']; ?>
                                </a>
                              </li>

                            <?php
                            }
                            ?>

                            <?php
                            if ($active_paypal->is_active == 1) {
                            ?>
                              <li class="nav-item">
                                <a data-toggle="pill" href="#paypal" class="nav-link">
                                  <i class="fab fa-paypal mr-2"></i>
                                  <?php echo $lang['left533020033']; ?>
                                </a>
                              </li>

                            <?php
                            }
                            ?>



                            <?php
                            if ($active_paystack->is_active == 1) {
                            ?>

                              <li class="nav-item">
                                <a data-toggle="pill" href="#paystack" class="nav-link">
                                  <i class="fas fa-credit-card mr-2"></i>
                                  <?php echo $lang['left533020035']; ?>
                                </a>
                              </li>

                            <?php
                            }
                            ?>


                            <?php
                            if ($active_wire->is_active == 1) {
                            ?>

                              <li class="nav-item">
                                <a data-toggle="pill" href="#attach" class="nav-link">
                                  <i class="fa fa-paperclip mr-2"></i>
                                  <?php echo $lang['leftorder78']; ?>
                                </a>
                              </li>

                            <?php
                            }
                            ?>
                          </ul>
                        </div> <!-- End -->
                        <!-- Credit card form content -->
                        <div class="tab-content bg-white">

                          <!-- STRIPE TAB-PANE -->
                          <?php
                          if ($active_stripe->is_active == 1) {
                          ?>
                            <div id="stripe" class="tab-pane fade show active  pt-3">

                              <form id="payment-form" style="padding: 40px">
                                <div>
                                  <label><?php echo $lang['payment-gateway-text1']; ?></label>
                                  <input class="form-control" type="text" name="name_property_card_stripe" id="name_property_card_stripe" required>
                                </div>

                                <input type="hidden" name="order_id" id="order_id" value="<?php echo $_GET['id_order']; ?>">

                                <input type="hidden" name="track_order" id="track_order" value="<?php echo $track_order; ?>">

                                <div>
                                  <label><?php echo $lang['user_manage5']; ?></label>
                                  <input class="form-control" type="email" name="email_property_card_stripe" id="email_property_card_stripe" required>
                                </div>
                                <div id="card-element" style="margin-top: 20px; margin-bottom: 30px">
                                  <!--Stripe.js injects the Card Element-->
                                </div>

                                <button id="submit">
                                  <div class="spinner hidden" id="spinner"></div>
                                  <span id="button-text"><?php echo $lang['leftorder136']; ?></span>
                                </button>
                                <p id="card-error-custom" class="text-danger" role="alert"></p>

                              </form>


                            </div>
                            <!-- END STRIPE TAB-PANE -->
                          <?php
                          }
                          ?>

                          <?php
                          if ($active_paypal->is_active == 1) {
                          ?>

                            <!-- PAYPAL TAB-PANE -->
                            <div id="paypal" class="tab-pane fade  pt-3">

                              <p class="text-center text-info"> <b><?php echo $lang['notes-payment-gateway2']; ?></b></p>
                              <div id="paypal-button-container" class=" text-center col-md-12"></div>

                            </div> <!-- END  PAYPAL TAB-PANE -->

                          <?php
                          }
                          ?>

                          <?php
                          if ($active_paystack->is_active == 1) {
                          ?>

                            <!-- PAYSTACK TAB-PANE -->
                            <div id="paystack" class="tab-pane fade pt-3">

                              <form id="paymentForm" style="padding: 40px">
                                <div class="form-group">
                                  <label for="email"><?php echo $lang['user_manage5']; ?></label>
                                  <input type="email" id="email-address" required />
                                </div>

                                <div class="form-group">
                                  <label for="first-name"><?php echo $lang['user_manage6']; ?></label>
                                  <input type="text" id="first-name" required />
                                </div>
                                <div class="form-group">
                                  <label for="last-name"><?php echo $lang['user_manage7']; ?></label>
                                  <input type="text" id="last-name" required />
                                </div>
                                <div class="form-submit">
                                  <button type="submit"><?php echo $lang['leftorder136']; ?></button>
                                </div>
                              </form>


                            </div>
                            <!-- END PAYSTACK TAB-PANE -->

                          <?php
                          }
                          ?>


                          <?php
                          if ($active_wire->is_active == 1) {
                          ?>

                            <!-- ATTACH TAB-PANE -->
                            <div id="attach" class="tab-pane fade  pt-3">

                              <form style="padding: 40px" class="form-horizontal" method="post" id="add_charges" name="add_charges">

                                <p class=""> <b><?php echo $lang['notes-payment-gateway']; ?></b></p>
                                <div class="row">

                                  <div class="form-group col-md-12">
                                    <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['left243'] ?></label>
                                    <div class="input-group mb-3">
                                      <select class="custom-select col-12" id="mode_pay" name="mode_pay" required="">
                                        <option value=""><?php echo $lang['left243'] ?></option>
                                        <?php foreach ($payrow as $row) : ?>
                                          <option value="<?php echo $row->id; ?>"><?php echo $row->name_pay; ?></option>
                                        <?php endforeach; ?>
                                      </select>
                                    </div>
                                  </div>

                                </div>

                                <div class="row mb-3">

                                  <div class="col-md-3">

                                    <div>
                                      <label class="control-label" id="selectItem"><?php echo $lang['leftorder15']; ?></label>
                                    </div>

                                    <input class="custom-file-input" id="filesMultiple" name="filesMultiple" type="file" style="display: none;" onchange="cdp_validateZiseFiles();" />


                                    <button type="button" id="openMultiFile" class="btn btn-info  pull-left ">
                                      <i class='fa fa-paperclip' id="openMultiFile" style="font-size:18px; cursor:pointer;"></i> <?php echo $lang['leftorder78']; ?></button>

                                    <div id="clean_files" class="row hide">
                                      <button type="button" id="clean_file_button" class="  mt-3 btn btn-danger ml-3">
                                        <i class='fa fa-trash' style="font-size:18px; cursor:pointer;"></i> <?php echo $lang['leftorder17']; ?> </button>
                                    </div>
                                  </div>
                                </div>

                                <div class="row">
                                  <div class="form-group col-sm-12">
                                    <label for="notes" class="control-label"><?php echo $lang['modal-text18']; ?></label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                  </div>
                                </div>

                                <div class="modal-footer">
                                  <button type="submit" class="btn btn-danger" id="save_form2"><?php echo $lang['left1103']; ?></button>
                                </div>
                              </form>
                            </div> <!-- END  ATTACH TAB-PANE -->
                          <?php
                          }
                          ?>

                        </div> <!-- End -->
                      </div>
                    </div>
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


  <input id="active_paypal" name="active_paypal" type="hidden" value="<?php echo $active_paypal->is_active == 1; ?>" />
  <input id="active_stripe" name="active_stripe" type="hidden" value="<?php echo $active_stripe->is_active == 1; ?>" />
  <input id="active_paystack" name="active_paystack" type="hidden" value="<?php echo $active_paystack->is_active == 1; ?>" />

  <input id="order_total_order_paystack" name="order_total_order_paystack" type="hidden" value="<?php echo number_format($row_order->total_order, 2, '.', ''); ?>" />
  <input id="order_total_order" name="order_total_order" type="hidden" value="<?php echo  cdb_money_format_bar($row_order->total_order); ?>" />
  <input id="track_order" name="track_order" type="hidden" value="<?php echo $track_order; ?>" />
  <input id="order_id" name="order_id" type="hidden" value="<?php echo $row_order->order_id; ?>" />

  <input id="order_sender_id" name="order_sender_id" type="hidden" value="<?php echo  $row_order->sender_id; ?>" />
  <input id="public_key_stripe" name="public_key_stripe" type="hidden" value="<?php echo $public_key_stripe; ?>" />
  <input id="public_key_paystack" name="public_key_paystack" type="hidden" value="<?php echo $public_key_paystack; ?>" />


  <script src="dataJs/customers_packages_add_payment_gateways.js"></script>