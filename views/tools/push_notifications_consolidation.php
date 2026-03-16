<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// *************************************************************************

if ((!$user->cdp_is_Admin()))
    cdp_redirect_to("login.php");

$userData = $user->cdp_getUserData();

if (!isset($_GET['id'])) {
    cdp_redirect_to("index.php");
    exit;
}

$data = cdp_getConsolidatePrint($_GET['id']);

if (empty($data) || !isset($data['data'])) {
    cdp_redirect_to("index.php");
    exit;
}

$row_order = $data['data'];


?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['left-menu-sidebar-66'] . ' for ' . $row_order->c_prefix . $row_order->c_no;?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
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
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['left-menu-sidebar-66'] . ' for ' ?><span class="text-danger"><?php echo $row_order->c_prefix . $row_order->c_no; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>
                                <div id="resultados_ajax"></div>

                                <form class="form-horizontal form-material" id="push_notification_form" name="push_notification_form" method="post">
                                    <section>
                                        <div class="row form-group">
                                            <label class="col-1 text-center"><?php echo $lang['push_notifications_type'] . ':' ?></label>

                                            <div class="col-11 row">
                                                <!-- Broadcast (users in consolidation) -->
                                                <div class="col-6 custom-control custom-radio">
                                                    <input type="radio" id="broadcast" name="notification_type" class="custom-control-input" value="broadcast" checked>
                                                    <label class="custom-control-label" for="broadcast"><?php echo $lang['push_notifications_type_broadcast']; ?>
                                                    <div class="small text-muted mt-1">
                                                        <?php echo $lang['push_notifications_hint_broadcast']; ?><span class="text-danger"><?php echo $row_order->c_prefix . $row_order->c_no; ?></span>
                                                    </div>
                                                </div>

                                                <!-- Select users (multi-select, restricted to consolidation) -->
                                                <div class="col-6 custom-control custom-radio">
                                                    <input type="radio" id="selected_users" name="notification_type" class="custom-control-input" value="selected_users">
                                                    <label class="custom-control-label" for="selected_users"><?php echo $lang['push_notifications_type_users']; ?></label>
                                                    <div class="small text-muted mt-1">
                                                        <?php echo $lang['push_notifications_hint_users']; ?><span class="text-danger"><?php echo $row_order->c_prefix . $row_order->c_no; ?></span>
                                                    </div>
                                                </div>

                                                <input type="hidden" id="consolidation_id" name="consolidation_id" value="<?php echo $_GET['id']; ?>" />
                                                <!-- user multi-select (hidden until 'selected_users' chosen) -->
                                                <div class="col-11 custom-control mt-3" id="user-container">
                                                    <label class="small mb-1"><?php echo $lang['select_users_label']; ?></label>
                                                    <select class="select2 form-control custom-select" style="width: 100%;" id="user_id" name="sender_ids[]" multiple="multiple"></select>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- subject & message -->
                                        <div class="row">
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
                                        <hr>
                                    </section>
                                    <br>
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

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <!-- include custom JS -->
    <script src="dataJs/push_notifications_consolidation.js"></script>

</body>

</html>
