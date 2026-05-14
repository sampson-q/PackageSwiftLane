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
require_once("helpers/phpmailer/class.phpmailer.php");
require_once("helpers/phpmailer/class.smtp.php");

$userData = $user->cdp_getUserData();

if (isset($_GET['id'])) {
    $data = cdp_getCourierPrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("courier_list.php");
}

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($data['data']->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$row = $data['data'];

$office = $core->cdp_getOffices();
$statusrow = $core->cdp_getStatusByType(2);
$driverrow = $user->cdp_userAllDriver();


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
    <title><?php echo $lang['deliver-ship1'] ?> | <?php echo $core->site_name ?></title>

    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">

    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">

    <style type="text/css">
        .canvas-bordered {
          border: 1px solid #e9ecef;
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


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">


            <div class="container-fluid mb-4">


                <div class="row justify-content-center">
                    <!-- Column -->
                    <div class="col-lg-12 col-xlg-12 col-md-12">
                        <div class="card">

                            <div class="card-body">
                                <!-- <div id="loader" style="display:none"></div> -->
                                <div id="resultados_ajax">
                                    <?php if (!empty($errors)) { ?>
                                        <div class="alert alert-danger" id="success-alert">
                                            <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
                                                <?php echo $lang['message_ajax_error2']; ?>
                                            <ul class="error">
                                                <?php
                                                foreach ($errors as $error) { ?>
                                                    <li>
                                                        <i class="icon-double-angle-right"></i>
                                                        <?php
                                                        echo $error;

                                                        ?>

                                                    </li>
                                                <?php
                                                }
                                                ?>


                                            </ul>
                                            </p>
                                        </div>
                                    <?php
                                    } ?>

                                </div>
                                <form name="invoice_form" class="xform" enctype="multipart/form-data" id="invoice_form" method="POST">
                                    <header>
                                        <h4 class="modal-title"> <b class="text-danger"><?php echo $lang['status-ship1'] ?> </b> <b>| <?php echo $row->order_prefix . $row->order_no; ?></b>
                                        </h4>
                                        <hr>
                                    </header>

                                    <input type="hidden" value="<?php echo $_GET['id']; ?>" id="shipment_id" name="shipment_id">

                                    <div class="row">
                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['deliver-ship4'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <span class="fa fa-calendar"></span>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" name="deliver_date" id="deliver_date" value="<?php echo date('Y-m-d'); ?>" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title16'] ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="inputname" class="control-label col-form-label"><?php echo $lang['deliver-ship12'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="driver_id" name="driver_id" required>
                                                    <option value=""><?php echo $lang['deliver-ship13'] ?></option>
                                                    <?php foreach ($driverrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php if ($row->id == $_SESSION['userid']) {
                                                                                                    echo 'selected';
                                                                                                } ?>><?php echo $row->fname . ' ' . $row->lname; ?>

                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-12 col-md-12">
                                            <label for="message-text" class="control-label"><?php echo $lang['deliver-ship6'] ?></label>
                                            <input type="text" name="person_receives" id="person_receives" class="form-control" placeholder="<?php echo $lang['deliver-ship6'] ?>" required="">
                                        </div>
                                    </div>

                                    <br>

                                    <div class="row">
                                      <div class="col-sm-12 col-md-6">
                                        <label for="inputcontact" class="control-label col-form-label">
                                          <i align='left'><img src='assets/images/alert/sign_icon.png' width='32' /></i>
                                          &nbsp;&nbsp;&nbsp;<?php echo $lang['deliver-ship14'] ?>
                                        </label>
                                        <div class="row">
                                          <button type="button" class="btn btn-info ml-4" data-toggle="modal" data-target=".bs-example-modal-lg"><?php echo $lang['deliver-ship15'] ?></button>
                                          <div class="modal fade bs-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true" style="display: none;">
                                            <div class="modal-dialog modal-lg">
                                              <div class="modal-content">
                                                <div class="modal-header">
                                                  <h4 class="modal-title" id="myLargeModalLabel"><?php echo $lang['deliver-ship14'] ?></h4>
                                                  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                </div>
                                                <div class="modal-body">
                                                  <p>
                                                    <div class="table-responsive">
                                                        <canvas id="sig-canvas" class="canvas-bordered" width="760" height="360">
                                                          Get a better browser.
                                                        </canvas>
                                                    </div>
                                                  </p>
                                                  <span class="btn btn-danger" id="sig-clearBtn"><?php echo $lang['deliver-ship16'] ?></span>
                                                  <button type="button" id="sig-submitBtn" class="btn btn-default waves-effect text-left" data-dismiss="modal"><?php echo $lang['deliver-ship15'] ?></button>
                                                </div>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="row" style="display:none">
                                          <div class="col-md-8">
                                            <textarea id="sig-dataUrl" name="sig-dataUrl" class="form-control" rows="5">Data URL for your signature will go here!</textarea>
                                          </div>
                                        </div>
                                      </div>

                                      <div class="col-sm-12 col-md-6">
                                          <div class="form-group">
                                            <label class="subtitle"><?php echo $lang['left1064'] ?></label>
                                            <div class="custom-file">
                                              <input type="file" class="custom-file-input" name="miarchivo" id="miarchivo" accept="image/*" capture="camera" onchange="previewImage(event)">
                                              <label class="custom-file-label" for="miarchivo">Choose file</label>
                                            </div>
                                            <div id="image-preview" style="margin-top: 15px;">
                                              <img id="image-preview-img" src="" alt="Image Preview" style="display: none; max-width: 100%; height: auto;">
                                            </div>
                                          </div>
                                        </div>
                                    </div>

                                     <div class="row">
                                        <?php
                                        if ($core->active_whatsapp == 1) {
                                        ?>
                                            <div class="col-sm-6 col-md-6">
                                                <br>
                                                <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                                    <input type="checkbox" class="custom-control-input" name="notify_whatsapp_sender" id="notify_whatsapp_sender" value="1">
                                                    <b><?php echo $lang['leftorder144430'] ?> &nbsp; <i class="mdi mdi-whatsapp" style="font-size: 22px; color:#07bc4c;"></i></b>
                                                    <span class="custom-control-indicator"></span>
                                                </label>

                                                <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                                    <input type="checkbox" class="custom-control-input" name="notify_whatsapp_receiver" id="notify_whatsapp_receiver" value="1">
                                                    <b><?php echo $lang['leftorder144442'] ?> <i class="mdi mdi-whatsapp" style="font-size: 22px; color:#07bc4c;"></i></b>
                                                    <span class="custom-control-indicator"></span>
                                                </label>

                                            </div>
                                        <?php } ?>

                                        <?php
                                        if ($core->active_sms == 1) {
                                        ?>
                                        <div class="col-sm-6 col-md-6">
                                            <br>
                                            <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                                <input type="checkbox" class="custom-control-input" name="notify_sms_sender" id="notify_sms_sender" value="1">
                                                <b>
                                                    <?php echo $lang['leftorder144431']; ?>

                                                    <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i>
                                                </b>
                                                <span class="custom-control-indicator"></span>
                                            </label>
                                            <br>
                                            <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input" name="notify_sms_receiver" id="notify_sms_receiver" value="1">
                                            <b>
                                                <?php echo $lang['leftorder144443']; ?>

                                                <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i>
                                            </b>
                                            <span class="custom-control-indicator"></span>
                                        </label>
                                        </div>
                                        <?php } ?> 

                                    </div>
                                    </br>
                                    </br>
                                    </br>
                                    <footer>
                                        <div class="pull-right">

                                            <a href="index.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['status-ship11'] ?></a>

                                            <button class="btn btn-danger" type="submit" id="sig-submitBtn"><?php echo $lang['deliver-ship10'] ?></button>
                                        </div>
                                    </footer>
                                </form>
                            </div>
                        </div>
                    </div>
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
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="dataJs/courier_delivered.js"></script>
</body>

</html>