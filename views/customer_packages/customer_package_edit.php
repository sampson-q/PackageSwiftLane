<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************

require_once('helpers/querys.php');

$userData = $user->cdp_getUserData();
if ($userData->userlevel == 1) cdp_redirect_to("login.php");

if (isset($_GET['id'])) {
    $data = cdp_getCustomerPackagePrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("customer_packages_list.php");
}

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($data['data']->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$row_order = $data['data'];

$db->cdp_query("SELECT * FROM cdb_customers_packages_detail WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_category where id = 26");
$category = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_delivery_time where id = 14");
$delivery_times = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_shipping_mode where id = 8");
$ship_modes = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->order_prefix . $row_order->order_no . "'");
$address_order = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_package_tracking_number WHERE order_id='" . (int) cdp_sanitize($_GET['id']) . "'");
$tracking_row = $db->cdp_registro();

// recipient (may be null on older records)
$receiver_data = null;
$recipient_address_text = '';
if (!empty($row_order->receiver_id)) {
    if ($row_order->recipient_type == 'user') {
        $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->receiver_id . "'");
    } else {
        $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
    }

    $receiver_data = $db->cdp_registro();
}

if (!empty($row_order->receiver_address_id)) {
    // we don't know if this is recipient/customer address table; address text is optional
    // leave prefill to JS with hidden ids; select2 will resolve.
    $recipient_address_text = '';
}

// existing files for unified preload
$db->cdp_query("SELECT * FROM cdb_customer_package_files where order_id='" . $_GET['id'] . "' ORDER BY date_file");
$files_order = $db->cdp_registros();
$existing_files_payload = [];
if (!empty($files_order)) {
    foreach ($files_order as $file) {
        $ft = strtolower((string)$file->file_type);
        $is_img = in_array($ft, ['jpg', 'jpeg', 'png', 'ico', 'gif', 'webp'], true);
        $existing_files_payload[] = [
            'id' => (int)$file->id,
            'name' => (string)$file->name,
            'url' => (string)$file->url,
            'file_type' => (string)$file->file_type,
            'is_image' => $is_img ? 1 : 0,
        ];
    }
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['leftorder73'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
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

    <?php include 'views/inc/preloader.php'; ?>

    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <?php
        $office = $core->cdp_getOffices();
        $agencyrow = $core->cdp_getBranchoffices();
        if (!function_exists('cdp_getAgencyBranchIdForUser')) {
            require_once(__DIR__ . '/../../helpers/querys.php');
        }
        $agency_default_id = (isset($userData->userlevel) && (int)$userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '') : 0;

        $courierrow = $core->cdp_getCouriercom();
        $statusrow = $core->cdp_getStatusByType(1);
        $packrow = $core->cdp_getPack();
        $moderow = $core->cdp_getShipmode();
        $driverrow = $user->cdp_userAllDriver();
        $delitimerow = $core->cdp_getDelitime();
        $categories = $core->cdp_getCategoriesById(26);
        ?>

        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><i class="ti-package" aria-hidden="true"></i> <?php echo $lang['messagesform35'] ?></h4>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <!-- Prefill plumbing for edit JS (recipient/sender preselect without disabling/invisibility) -->
                <input type="hidden" id="prefill_sender_id" value="<?php echo (int)$row_order->sender_id; ?>">
                <input type="hidden" id="prefill_sender_address_id" value="<?php echo (int)$row_order->sender_address_id; ?>">
                <input type="hidden" id="prefill_recipient_id" value="<?php echo (int)($row_order->receiver_id ?? 0); ?>">
                <input type="hidden" id="prefill_recipient_address_id" value="<?php echo (int)($row_order->receiver_address_id ?? 0); ?>">

                <!-- Existing DB files deletion list -->
                <input type="hidden" name="deleted_db_file_ids" id="deleted_db_file_ids" value="">

                <div class="container-fluid">

                    <!-- Header -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">

                                        <div class="form-group col-md-4">
                                            <label for="inputcom" class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><span style="color:#ff0000"><b><?php echo $lang['inv-shipping9'] ?></b></span></div>
                                                </div>
                                                <input type="text" class="form-control" name="order_no" id="order_no" value="<?php echo $row_order->order_prefix . $row_order->order_no; ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['left201'] ?></label>
                                            <div class="input-group mb-3">
                                                <?php if ($agency_default_id > 0) { ?>
                                                    <input type="hidden" name="agency" id="agency" value="<?php echo $agency_default_id; ?>">
                                                <?php } ?>
                                                <select class="custom-select col-12" id="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" name="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" <?php echo ($agency_default_id > 0) ? 'disabled' : ''; ?>>
                                                    <option value="0">--<?php echo $lang['left202'] ?>--</option>
                                                    <?php foreach ($agencyrow as $row) : ?>
                                                        <option value="<?php echo (int)$row->id; ?>" <?php echo (($agency_default_id > 0 && (int)$row->id === $agency_default_id) || ((int)$row_order->agency === (int)$row->id)) ? 'selected' : ''; ?>>
                                                            <?php echo $row->name_branch; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <?php if ($user->access_level = 'Admin') { ?>
                                            <div class="form-group col-md-4">
                                                <label for="inputname" class="control-label col-form-label"><?php echo $lang['add-title14'] ?></label>
                                                <div class="input-group mb-3">
                                                    <select class="custom-select col-12" id="origin_off" name="origin_off">
                                                        <option value="0">--<?php echo $lang['left343'] ?>--</option>
                                                        <?php foreach ($office as $row) : ?>
                                                            <option value="<?php echo $row->id; ?>" <?php echo ((int)$row_order->origin_off === (int)$row->id) ? 'selected' : ''; ?>>
                                                                <?php echo $row->name_off; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php } ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sender/Recipient -->
                    <div class="row">
                        <!-- Remitente -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">

                                    <h4 class="card-title">
                                        <i class="mdi mdi-information-outline" style="color:#20c997"></i>
                                        <?php echo $lang['langs_010']; ?>
                                    </h4>
                                    <hr>

                                    <?php
                                    if ($core->active_whatsapp == 1) {
                                    ?>
                                        <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input" name="notify_whatsapp_sender" id="notify_whatsapp_sender" value="1">
                                            <?php echo $lang['leftorder14443']; ?> <i class="mdi mdi-whatsapp" style="font-size: 22px; color:#07bc4c;"></i>
                                            <span class="custom-control-indicator"></span>
                                        </label>

                                    <?php } ?>

                                    <div class="resultados_ajax_add_user_modal_sender"></div>
                                    

                                    <?php if ($core->active_sms == 1): ?>
                                        <label class="custom-control custom-checkbox"
                                               style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input" name="notify_sms_sender"
                                                   id="notify_sms_sender" value="1">
                                            <b>
                                                <?php echo $lang['leftorder14444']; ?>
                                                <i class="fa fa-envelope"
                                                   style="font-size: 22px; color:#07bc4c;"></i>
                                            </b>
                                            <span class="custom-control-indicator"></span>
                                        </label>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['sender_search_title'] ?>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select" id="sender_id"
                                                                name="sender_id">
                                                            <option value="<?php echo $sender_data->id; ?>">
                                                                <?php echo $sender_data->fname . " " . $sender_data->lname; ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button type="button" class="btn btn-default"
                                                                data-type_user="user_customer"
                                                                data-toggle="modal"
                                                                data-target="#myModalAddUser">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['sender_search_address_title'] ?>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="sender_address_id"
                                                                name="sender_address_id">
                                                            <option value="<?php echo $row_order->sender_address_id; ?>">
                                                                <?php echo $address_order->sender_address; ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button id="add_address_sender" data-type_user="user_customer"
                                                                data-toggle="modal"
                                                                data-target="#myModalAddUserAddresses" type="button"
                                                                class="btn btn-default">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Destinatario -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">

                                    <h4 class="card-title">
                                        <i class="mdi mdi-information-outline" style="color:#20c997"></i>
                                        <?php echo $lang['left334']; ?>
                                    </h4>
                                    <hr>

                                    <div class="resultados_ajax_add_user_modal_recipient"></div>
                                    <br>
                                    <br>

                                    <?php if ($core->active_sms == 1): ?>
                                        <label class="custom-control custom-checkbox"
                                               style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input"
                                                   name="notify_sms_receiver"
                                                   id="notify_sms_receiver" value="1">
                                            <b>
                                                <?php echo $lang['leftorder14444']; ?>
                                                <i class="fa fa-envelope"
                                                   style="font-size: 22px; color:#07bc4c;"></i>
                                            </b>
                                            <span class="custom-control-indicator"></span>
                                        </label>
                                    <?php endif; ?>

                                    <div class="row">

                                        <div class="col-md-12">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['recipient_search_title'] ?>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select"
                                                                id="recipient_id" name="recipient_id">
                                                            <option value="<?php echo $receiver_data->id; ?>">
                                                                <?php echo $receiver_data->fname . " " . $receiver_data->lname; ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button id="add_recipient" type="button"
                                                                data-type_user="user_recipient"
                                                                data-toggle="modal"
                                                                data-target="#myModalAddRecipient"
                                                                class="btn btn-default">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['recipient_search_address_title'] ?>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="recipient_address_id"
                                                                name="recipient_address_id">
                                                            <option value="<?php echo $row_order->receiver_address_id; ?>">
                                                                <?php echo $address_order->recipient_address; ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button id="add_address_recipient" type="button"
                                                                data-type_user="user_recipient"
                                                                data-toggle="modal"
                                                                data-target="#myModalAddRecipientAddresses"
                                                                class="btn btn-default">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Shipment details -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-book-multiple" style="color:#20c997"></i> <?php echo $lang['add-title13'] ?></h4>
                                    <br>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sum2"><i class="fa fa-cube mr-1"></i><strong> <?php echo $lang['left63'] ?></strong></label>
                                                <input type="text" class="form-control" name="tracking_purchase" id="tracking_purchase" placeholder="Example: 009785454545554" value="<?php echo htmlspecialchars($row_order->tracking_purchase, ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="ReceiptKind"><strong><?php echo $lang['left64'] ?></strong></label>
                                                <input type="text" class="form-control" name="provider_purchase" id="provider_purchase" placeholder="<?php echo $lang['left65'] ?>" value="<?php echo htmlspecialchars($row_order->provider_purchase, ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sum2"><strong> <?php echo $lang['left66'] ?></strong></label>
                                                <input onkeypress="return isNumberKey(event)" type="text" class="form-control" name="price_purchase" id="price_purchase" placeholder="<?php echo $lang['left66'] ?>" value="<?php echo htmlspecialchars($row_order->price_purchase, ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="form-group col-md-4">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['itemcategory'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_item_category" name="order_item_category" disabled>
                                                    <option value="<?php echo $categories->id; ?>">
                                                        <?php echo $categories->name_item; ?>
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['add-title17'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_package" name="order_package">
                                                    <?php foreach ($packrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php echo ((int)$row_order->order_package === (int)$row->id) ? 'selected' : ''; ?>>
                                                            <?php echo $row->name_pack; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title18'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_courier" name="order_courier">
                                                    <option value="0">--<?php echo $lang['left204'] ?>--</option>
                                                    <?php foreach ($courierrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php echo ((int)$row_order->order_courier === (int)$row->id) ? 'selected' : ''; ?>>
                                                            <?php echo $row->name_com; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title22'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_service_options" name="order_service_options" disabled>
                                                    <option value="<?php echo $ship_modes->id; ?>"><?php echo $ship_modes->ship_mode; ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title20'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_deli_time" name="order_deli_time" disabled>
                                                    <option value="<?php echo $delivery_times->id; ?>"><?php echo $delivery_times->delitime; ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title19'] ?> <i style="color:#ff0000" class="fas fa-shipping-fast"></i></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="status_courier" name="status_courier">
                                                    <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                    <?php foreach ($statusrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php echo ((int)$row_order->status_courier === (int)$row->id) ? 'selected' : ''; ?>>
                                                            <?php echo $row->mod_style; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label"><?php echo 'Estimated Time of Arrival' ?></label>
                                            <input type='date' class="form-control" id="estimated_eta" name="estimated_eta" value="<?php echo $tracking_row->estimated_eta; ?>" />
                                        </div>

                                    </div>

                                    <!-- Attachments + Camera (mirror add style: inputs must exist) -->
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div>
                                                <label class="control-label" id="selectItem"><?php echo $lang['leftorder15']; ?></label>
                                            </div>

                                            <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple="multiple" type="file" style="display:none;" onchange="cdp_preview_images();">

                                            <button type="button" id="openMultiFile" class="btn btn-default pull-left mb-4">
                                                <i class="fa fa-paperclip" style="font-size:18px;"></i> <?php echo $lang['leftorder15']; ?>
                                            </button>
                                        </div>

                                        <div class="col-md-4">
                                            <div>
                                                <label class="control-label" id="captureItem">Camera Captures</label>
                                            </div>
                                            <input type="file" id="filesCapture" name="filesCapture[]" multiple="multiple" accept="image/*" style="display:none;">
                                            <button type="button" id="openCameraButton" class="btn btn-default pull-left mb-4">
                                                <i class="fa fa-camera" style="font-size:18px;"></i> Open Camera
                                            </button>
                                            <button type="button" id="takeCameraPhoto" class="btn btn-default pull-left mb-4" style="display:none;">
                                                <i class="fa fa-circle" style="font-size:18px;"></i> Capture
                                            </button>
                                            <button type="button" id="stopCamera" class="btn btn-default pull-left mb-4" style="display:none;">
                                                <i class="fa fa-stop" style="font-size:18px;"></i> Stop
                                            </button>
                                        </div>

                                        <div class="col-md-4">
                                            <video id="cameraPreview" playsinline autoplay style="display:none;width:100%;max-width:320px;border:1px solid #ddd;border-radius:8px;"></video>
                                        </div>
                                    </div>

                                    <div class="col-md-12 row" id="image_preview"></div>

                                    <div class="col-md-4 mt-4">
                                        <div id="clean_files" class="hide">
                                            <button type="button" id="clean_file_button" class="btn btn-danger ml-3">
                                                <i class="fa fa-trash" style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder17']; ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="resultados_file col-md-4 pull-right mt-4"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Packages -->
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

                                    <div id="data_items"></div>

                                    <div class="col-md-3 text-left">
                                        <button type="button" onclick="addPackage()" name="add_rows" id="add_row" class="btn btn-outline-dark">
                                            <span class="fa fa-plus"></span> <?php echo $lang['left213'] ?>
                                        </button>
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
                                        <div class="table-responsive" id="table-totals">
                                            <table id="insvoice-item-table" class="table">
                                                <tfoot>
                                                    <tr class="card-hover">
                                                        <td colspan="4" class="text-right"><b><?php echo $lang['leftorder2021'] ?></b></td>
                                                        <td colspan="1"></td>
                                                        <td class="text-right">
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="subtotal">0.00</span>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            <div id="row">
                                                <div class="col-md-6">
                                                    <h4 class="card-title">
                                                        <i class="ti-briefcase" style="color:#20c997"></i>
                                                        <?php echo $lang['messageerrorform30'] ?>
                                                    </h4>
                                                </div>
                                                <hr>

                                                <div class="row row-shadow input-container">

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['left905'] ?> &nbsp; <?php echo $core->weight_p; ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->value_weight ?? $row_order->price_lb ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="price_lb" id="price_lb">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder21'] ?> <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" value="<?php echo htmlspecialchars($row_order->tax_discount ?? '0', ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm" name="discount_value" id="discount_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="discount">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder22'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->total_insured_value ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="insured_value" id="insured_value">
                                                            </div>
                                                            <span id="insured_label"></span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder24'] ?> <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->tax_insurance_value ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="insurance_value" id="insurance_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="insurance">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder25'] ?> <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->tax_custom_tariffis_value ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="tariffs_value" id="tariffs_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="total_impuesto_aduanero">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder67'] ?> <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->tax_value ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="tax_value" id="tax_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="impuesto">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder19'] ?> <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" value="<?php echo htmlspecialchars($row_order->declared_value ?? '0', ENT_QUOTES, 'UTF-8'); ?>" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" name="declared_value_tax" id="declared_value_tax">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="declared_value_label">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['langs_048'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text" onchange="calculateFinalTotal(this);" onkeypress="return isNumberKey(event)" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row_order->total_reexp ?? '0', ENT_QUOTES, 'UTF-8'); ?>" name="reexpedicion_value" id="reexpedicion_value">
                                                            </div>
                                                            <span id="reexpedicion_label"></span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder1878'] ?></label>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="fixed_value_label">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder2020'] ?></label>
                                                            <?php if ($core->for_symbol !== null) { ?><b><?php echo $core->for_symbol; ?></b><?php } ?>
                                                            <span id="total_envio" class="green-bold">0.00</span>
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

                                                        <button type="submit" name="create_invoice" id="create_invoice" class="btn btn-danger">
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

                        </div>
                    </div>

                    <input type="hidden" name="order_id" id="order_id" value="<?php echo (int)$row_order->order_id; ?>" />
                    <input type="hidden" name="core_meter" id="core_meter" value="<?php echo htmlspecialchars($row_order->volumetric_percentage, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="core_min_cost_tax" id="core_min_cost_tax" value="<?php echo htmlspecialchars($core->min_cost_tax, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="core_min_cost_declared_tax" id="core_min_cost_declared_tax" value="<?php echo htmlspecialchars($core->min_cost_declared_tax, ENT_QUOTES, 'UTF-8'); ?>" />

                </div>
            </form>

            <?php include('views/modals/modal_add_user_shipment.php'); ?>
            <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
            <?php include('views/modals/modal_add_addresses_user.php'); ?>
            <?php include('views/modals/modal_add_addresses_recipient.php'); ?>

        </div>

        <?php include 'views/inc/footer.php'; ?>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script>
        // Unified existing-files preload payload (used by customers_packages_edit.js)
        window.__existing_customer_package_files = <?php echo json_encode($existing_files_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <script src="dataJs/customers_packages_edit.js"></script>

</body>

</html>