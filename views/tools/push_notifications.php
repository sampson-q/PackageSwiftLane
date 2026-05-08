<?php

if ((!$user->cdp_is_Admin()))
    cdp_redirect_to("login.php");

$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['left-menu-sidebar-66'] ?> | <?php echo $core->site_name ?></title>

    <!-- existing CSS -->
    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/daterangepicker/daterangepicker.css">

    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid mb-4">
                <div class="row">
                    <div class="col-lg-12 col-xlg-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center"><div><h3 class="card-title"><span><?php echo $lang['left-menu-sidebar-66']; ?></span></h3></div></div>
                                <div><hr><br></div>
                                <div id="resultados_ajax"></div>

                                <form class="form-horizontal form-material" id="push_notification_form" name="push_notification_form" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <section>
                                        <!-- radio to select notification type (broadcast/single/consolidation/invoice) -->
                                        <div class="row form-group">
                                            <label class="col-1 text-center"><?php echo $lang['push_notifications_type'] . ':' ?></label>
                                            <div class="col-11 row">
                                                <div class="col-3 custom-control custom-radio">
                                                    <input type="radio" id="broadcast" name="notification_type" class="custom-control-input" value="broadcast" checked>
                                                    <label class="custom-control-label" for="broadcast"><?php echo $lang['push_notifications_type0'] ?></label>
                                                </div>

                                                <div class="col-3 custom-control custom-radio">
                                                    <input type="radio" id="single_user" name="notification_type" class="custom-control-input" value="single_user">
                                                    <label class="custom-control-label" for="single_user"><?php echo $lang['push_notifications_type1'] ?></label>
                                                </div>

                                                <div class="col-3 custom-control custom-radio">
                                                    <input type="radio" id="consolidation" name="notification_type" class="custom-control-input" value="consolidation">
                                                    <label class="custom-control-label" for="consolidation"><?php echo isset($lang['push_notifications_type2']) ? $lang['push_notifications_type2'] : 'Consolidation' ?></label>
                                                </div>

                                                <div class="col-3 custom-control custom-radio">
                                                    <input type="radio" id="invoice_radio_top" name="notification_type" class="custom-control-input" value="invoice">
                                                    <label class="custom-control-label" for="invoice_radio_top">Invoice</label>
                                                </div>

                                                <div class="col-11 custom-control" id="single-user-container">
                                                    <select class="select2 form-control custom-select" id="user_id" name="user_id"></select>
                                                </div>

                                                <div class="col-11 custom-control" id="consolidation-container">
                                                    <select class="select2 form-control custom-select" id="consolidation_id" name="consolidation_id"></select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- default subject/message form -->
                                        <div class="row default-message-row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="subject"><?php echo $lang['leftorder199'] ?></label>
                                                    <input type="text" class="form-control required" name="subject" id="subject" placeholder="<?php echo $lang['subject_matter'] ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="message"><?php echo $lang['message_matter'] ?></label>
                                                    <textarea class="form-control required" name="message" id="message" placeholder="<?php echo $lang['content_matter'] ?>"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- hidden fields for selected ids -->
                                        <input type="hidden" id="uid" value="" />
                                        <input type="hidden" id="cid" value="" />

                                        <!-- Invoice container (hidden by default) -->
                                        <div id="invoice-container" style="display:none;">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-sm-12 col-md-8">
                                                                    <h4 class="card-title">
                                                                        <i class="fas fa fa-comments" style="color:#36bea6"></i>
                                                                        <?php echo $lang['invoice_header'] ?>
                                                                    </h4>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <div class="row justify-content-center">
                                                                <div class="col-sm-12 col-md-6 col-lg-4">
                                                                    <div class="form-group">
                                                                    <label for="shipping_date_from" class="control-label col-form-label"><?php echo $lang['shipment_date_from'];?></label>
                                                                        <input type="date"  class="form-control form-control-md" id='shipping_date_from' name='shipping_date_from'>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="col-sm-12 col-md-6 col-lg-4">
                                                                    <div class="form-group">
                                                                    <label for="shipping_date_to" class="control-label col-form-label"><?php echo $lang['shipment_date_to'];?></label>
                                                                        <input type="date"  class="form-control form-control-md" id='shipping_date_to' name='shipping_date_to'>
                                                                    </div>
                                                                </div>                                                                
                                                                <div class="col-sm-12 col-md-6 col-lg-4">
                                                                    <div class="form-group">
                                                                        <label for="shipment_pickup_date" class="control-label col-form-label"><?php echo $lang['shipment_pickup_date'];?></label>
                                                                        <input type="date"  class="form-control form-control-md" id='shipment_pickup_date' name='shipment_pickup_date'>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row justify-content-center">
                                                            </div>

                                                            <div id="data_items"></div>

                                                            <div class="row mt-3">
                                                                <div class="col-md-3 text-left">
                                                                    <button type="button" onclick="addPackage()" name="add_rows" id="add_rows" class="btn btn-outline-dark"><span class="fa fa-plus"></span> <?php echo $lang['add_invoice_row'] ?></button>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" name="total_item_files" id="total_item_files" value="0" />
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="translate_quantity" id="translate_quantity" value="<?php echo $lang['left1103'] ?>" />
                                                </div>
                                            </div>
                                            <hr>

                                            <div class="row" style="margin-top:8px;">
                                                <div class="col-md-6">
                                                    <button type="button" id="send_invoice_notifications" class="btn btn-outline-primary"><?php echo $lang['send_invoice_notification']; ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                    
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-primary btn-confirmation" id="send_notification" name="send_notification" type="submit"><?php echo $lang['send_notification'] ?><span><i class="icon-ok"></i></span></button>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <!-- required scripts -->
    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <!-- daterange libs -->
    <script src="assets/template/assets/libs/moment/moment.min.js"></script>
    <script src="assets/template/assets/libs/daterangepicker/daterangepicker.js"></script>

    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <!-- our JS -->
    <script src="dataJs/push_notifications.js"></script>
</body>
</html>