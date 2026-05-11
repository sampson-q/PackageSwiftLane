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
if ($userData->userlevel == 1) {
    cdp_redirect_to("login.php");
}

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

$row_order = $data['data'];

$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

if ($row_order->recipient_type == 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->receiver_id . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
}

$receiver_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->order_prefix . $row_order->order_no . "'");
$address_order = $db->cdp_registro();

$office        = $core->cdp_getOffices();
$agencyrow     = $core->cdp_getBranchoffices();
$agency_default_id = (isset($userData->userlevel) && (int)$userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '') : 0;
$courierrow    = $core->cdp_getCouriercom();
$statusrow     = $core->cdp_getStatus();
$packrow       = $core->cdp_getPack();
$payrow        = $core->cdp_getPayment();
$paymethodrow  = $core->cdp_getPaymentMethod();
$itemrow       = $core->cdp_getItem();
$moderow       = $core->cdp_getShipmode();
$driverrow     = $user->cdp_userAllDriver();
$delitimerow   = $core->cdp_getDelitime();
$track         = $core->cdp_order_track();
$categories    = $core->cdp_getCategories();

// Archivos ya adjuntados al envío
$db->cdp_query("SELECT * FROM cdb_order_files where order_id='" . $_GET['id'] . "' ORDER BY date_file");
$files_order = $db->cdp_registros();
$numrows     = $db->cdp_rowCount();
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
    <title><?php echo $lang['edit-courier1'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
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
    <?php include 'views/inc/preloader.php'; ?>

    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title">
                            <i class="ti-package" aria-hidden="true"></i>
                            <?php echo $lang['leftorder73'] ?>
                        </h4>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="container-fluid">

                    <!-- 1) Datos del envío -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <span style="color:#ff0000"><b><?php echo $lang['inv-shipping9'] ?></b></span>
                                                    </div>
                                                </div>
                                                <input type="text" class="form-control" name="order_no" id="order_no"
                                                       value="<?php echo $row_order->order_prefix . $row_order->order_no; ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label"><?php echo $lang['left201'] ?></label>
                                            <div class="input-group mb-3">
                                                <?php if ($agency_default_id > 0) { ?><input type="hidden" name="agency" id="agency" value="<?php echo $agency_default_id; ?>"><?php } ?>
                                                <select class="custom-select col-12" id="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" name="<?php echo ($agency_default_id > 0) ? '' : 'agency'; ?>" required <?php echo ($agency_default_id > 0) ? 'disabled style="pointer-events:none;background:#e9ecef;"' : ''; ?>>
                                                    <option value="0">--<?php echo $lang['left202'] ?>--</option>
                                                    <?php foreach ($agencyrow as $row): ?>
                                                        <option value="<?php echo (int)$row->id; ?>"
                                                            <?php echo (($agency_default_id > 0 && (int)$row->id === $agency_default_id) || ($agency_default_id === 0 && $row_order->agency == $row->id)) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($row->name_branch ?? ''); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <?php if ($userData->userlevel == 9): ?>
                                            <div class="form-group col-md-4">
                                                <label class="control-label col-form-label"><?php echo $lang['add-title14'] ?></label>
                                                <div class="input-group mb-3">
                                                    <select class="custom-select col-12" id="origin_off" name="origin_off" required>
                                                        <option value="0">--<?php echo $lang['left343'] ?>--</option>
                                                        <?php foreach ($office as $row): ?>
                                                            <option value="<?php echo $row->id; ?>"
                                                                <?php if ($row_order->origin_off == $row->id) echo 'selected'; ?>>
                                                                <?php echo $row->name_off; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1b) Remitente / Destinatario -->
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

                                    <div class="resultados_ajax_add_user_modal_sender"></div>
                                    <br>

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

                    <!-- 2) Información del paquete -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">

                                    <div class="row align-items-center mb-3">
                                        <div class="col-md-8">
                                            <h4 class="card-title mb-0">
                                                <i class="fas fas fa-boxes" style="color:#20c997"></i>
                                                <?php echo $lang['left212'] ?>
                                            </h4>
                                        </div>
                                        <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                            <label class="custom-control custom-checkbox mb-0">
                                                <?php echo $lang['messagesform112'] ?>
                                                <input type="checkbox" class="custom-control-input"
                                                       name="tariff_mode" id="tariff_mode" value="1"
                                                       <?php if ((int) $row_order->manual_tariff === 1) echo 'checked'; ?>>
                                                <span class="custom-control-indicator"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label class="control-label col-form-label"><?php echo isset($lang['add-title22']) ? $lang['add-title22'] : 'Modo de envío'; ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_service_options" name="order_service_options" required>
                                                    <?php foreach ($categories as $row): ?>
                                                        <option value="<?php echo (int)$row->id; ?>"
                                                            <?php if (isset($row_order->order_service_options) && (int)$row_order->order_service_options === (int)$row->id) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars($row->name_item, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" id="rate_provider" name="rate_provider" value="internal">
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="control-label col-form-label">
                                                Distancia (millas) (opcional)
                                            </label>
                                            <div class="input-group mb-3">
                                                <input type="text" class="form-control"
                                                       id="distance_miles" name="distance_miles"
                                                       value="<?php echo isset($row_order->distance_miles) ? htmlspecialchars($row_order->distance_miles, ENT_QUOTES, 'UTF-8') : '0'; ?>"
                                                       onkeypress="return isNumberKey(event,this)">
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['add-title20'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_deli_time"
                                                        name="order_deli_time" required>
                                                    <option value="0">--<?php echo $lang['left207'] ?>--</option>
                                                    <?php foreach ($delitimerow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->order_deli_time == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->delitime; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['payment_methods'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_payment_method"
                                                        name="order_payment_method" required>
                                                    <?php foreach ($paymethodrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->order_payment_method == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->label; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de paquetes (igual estructura que add_courier) -->
                                    <div id="data_items" class="mb-3">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-bordered" id="packages_table">
                                                <thead class="bg-inverse text-white">
                                                    <tr>
                                                        <th class="text-center"><b><?php echo $lang['left214']; /* Cantidad */ ?></b></th>
                                                        <th class="text-center"><b><?php echo $lang['left215']; /* Peso */ ?></b></th>
                                                        <th class="text-center"><b><?php echo $lang['left216']; /* Largo */ ?></b></th>
                                                        <th class="text-center"><b><?php echo $lang['left217']; /* Ancho */ ?></b></th>
                                                        <th class="text-center"><b><?php echo $lang['left218']; /* Alto */ ?></b></th>
                                                        <th class="text-center" style="min-width:140px;"><b><?php echo $lang['left213']; /* Descripción */ ?></b></th>
                                                        <th class="text-center"><b><?php echo $lang['left239']; /* Acciones */ ?></b></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Filas generadas por JS (packagesItems + loadPackages()) -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Totales de cantidades/pesos -->
                                    <div class="row mb-3">
                                        <div class="col-sm-12 col-md-4">
                                            <span class="text-secondary">
                                                <?php echo $lang['leftorder17713'] ?>
                                            </span>
                                        </div>
                                        <div class="col-sm-12 col-md-2 text-center">
                                            <span class="text-secondary" id="total_weight">0.00</span>
                                        </div>
                                        <div class="col-sm-12 col-md-2 text-center">
                                            <span class="text-secondary" id="total_vol_weight">0.00</span>
                                        </div>
                                        <div class="col-sm-12 col-md-2 text-center">
                                            <span class="text-secondary" id="total_fixed">0.00</span>
                                        </div>
                                        <div class="col-sm-12 col-md-2 text-center">
                                            <span class="text-secondary" id="total_declared">0.00</span>
                                        </div>
                                    </div>

                                    <!-- Botón añadir caja -->
                                    <div class="row mb-4">
                                        <div class="col-md-3 text-left">
                                            <button type="button" onclick="addPackage()" name="add_rows"
                                                    id="add_rows" class="btn btn-outline-dark">
                                                <span class="fa fa-plus"></span>
                                                <?php echo $lang['left231'] ?>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Adjuntar archivos -->
                                    <hr>
                                    <div class="row mt-4">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="control-label d-block" id="selectItem"><?php echo $lang['leftorder15']; ?></label>
    
                                                <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple type="file" style="display:none" onchange="cdp_validateZiseFiles(); cdp_preview_images();" />
    
                                                <button type="button" id="openMultiFile" class="btn btn-default mb-3">
                                                    <i class="fa fa-paperclip" style="font-size:18px; cursor:pointer;"></i>
                                                    <?php echo $lang['leftorder16']; ?>
                                                </button>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="control-label" id="captureItem"><?php echo $lang['leftorder90']; ?></label>
    
                                                <input class="custom-file-input" id="filesCapture" name="filesCapture[]" multiple="multiple" type="file" accept="image/*" capture="environment" style="display:none;" />
    
                                                <!-- camera open/capture button — follows visual style of your upload button -->
                                                <button type="button" id="openCameraButton" class="btn btn-dark mb-3">
                                                    <i class="fa fa-camera" style="font-size:18px; cursor:pointer;"></i>
                                                    <?php echo $lang['leftorder90']; ?>
                                                </button>

                                                <!-- small inline camera UI (hidden until camera opened) -->
                                                <div class="mt-2 d-flex align-items:flex-start" style="gap:.5rem;">
                                                    <video id="cameraPreview" autoplay playsinline style="width:220px; height:165px; background:#000; display:none; border-radius:6px; object-fit:cover;"></video>

                                                    <div style="flex:1;">
                                                        <div style="margin-bottom:.5rem;">
                                                            <button type="button" id="takeCameraPhoto" class="btn btn-success btn-sm" style="display:none;"><?php echo $lang['left1105']; ?></button>
                                                            <button type="button" id="stopCamera" class="btn btn-secondary btn-sm" style="display:none;"><?php echo $lang['left1111']; ?></button>
                                                        </div>
                                                    
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row" id="image_preview"></div>

                                            <div class="mt-2">
                                                <div id="clean_files" class="hide">
                                                    <button type="button" id="clean_file_button" class="ml-5 btn btn-danger">
                                                        <i class="fa fa-trash" style="font-size:12px; cursor:pointer;"></i>
                                                        <?php echo 'Clear Files'; ?>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="resultados_file col-md-12 mt-3"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Archivos ya adjuntos -->
                    <?php if ($numrows > 0): ?>
                        <div class="row">
                            <div class="col-lg-12">
                                <div id="resultados_ajax_delete_file"></div>
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fa fa-paperclip"></i>
                                            <?php echo $lang['leftorder16']; ?>
                                        </h5>
                                        <hr>

                                        <div class="col-md-12 row">
                                            <?php foreach ($files_order as $file):
                                                $date_add = date("Y-m-d h:i A", strtotime($file->date_file));
                                                $src = 'assets/images/no-preview.jpeg';

                                                if (
                                                    $file->file_type == 'jpg' ||
                                                    $file->file_type == 'jpeg' ||
                                                    $file->file_type == 'png' ||
                                                    $file->file_type == 'ico'
                                                ) {
                                                    $src = $file->url;
                                                }
                                                ?>
                                                <div class="col-md-3" id="file_delete_item_<?php echo $file->id; ?>">
                                                    <img style="width: 180px; height: 180px;"
                                                         class="img-thumbnail" src="<?php echo $src; ?>">
                                                    <div class="row">
                                                        <div class="col-md-12 mb-3 mt-2">
                                                            <p class="text-justify">
                                                                <a style="color:#7460ee;" target="_blank"
                                                                   href="<?php echo $file->url; ?>">
                                                                    <?php echo $file->name; ?>
                                                                </a>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <button type="button" class="btn btn-danger btn-sm"
                                                                    onclick="cdp_deleteImgAttached('<?php echo $file->id; ?>');">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3) Servicio & Cotización -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">

                                    <h4 class="card-title">
                                        <i class="mdi mdi-book-multiple" style="color:#20c997"></i>
                                        <?php echo $lang['add-title13'] ?>
                                    </h4>
                                    <br>

                                    <div class="row">
                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['itemcategory'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_item_category"
                                                        name="order_item_category" required>
                                                    <?php foreach ($categories as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->order_item_category == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->name_item; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['add-title17'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_package"
                                                        name="order_package" required>
                                                    <option value="0">--<?php echo $lang['left203'] ?>--</option>
                                                    <?php foreach ($packrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->order_package == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->name_pack; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['add-title18'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="order_courier"
                                                        name="order_courier" required>
                                                    <option value="0">--<?php echo $lang['left204'] ?>--</option>
                                                    <?php foreach ($courierrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->order_courier == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->name_com; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Fecha (oculta) -->
                                        <div class="col-md-4" style="display:none;">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['add-title15'] ?>
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-append" data-target="#datetimepicker1"
                                                     data-toggle="datetimepicker">
                                                    <div class="input-group-text">
                                                        <i style="color:#ff0000" class="fa fa-calendar"></i>
                                                    </div>
                                                </div>
                                                <input type='text' class="form-control" name="order_date"
                                                       id="order_date"
                                                       placeholder="--<?php echo $lang['left206'] ?>--"
                                                       data-toggle="tooltip" data-placement="bottom"
                                                       title="<?php echo $lang['add-title16'] ?>"
                                                       value="<?php echo date("Y/m/d", strtotime($row_order->order_datetime)); ?>"
                                                       readonly />
                                            </div>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['add-title19'] ?>
                                                <i style="color:#ff0000" class="fas fa-shipping-fast"></i>
                                            </label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="status_courier"
                                                        name="status_courier" required>
                                                    <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                    <?php foreach ($statusrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"
                                                            <?php if ($row_order->status_courier == $row->id) echo 'selected'; ?>>
                                                            <?php echo $row->mod_style; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Conductor -->
                                        <div class="form-group col-md-4">
                                            <label class="control-label col-form-label">
                                                <?php echo $lang['left208'] ?>
                                            </label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="color:#ff0000">
                                                        <i class="fas fa-car"></i>
                                                    </span>
                                                </div>

                                                <?php if ($userData->userlevel == 3): ?>

                                                    <input type="hidden" name="driver_id" id="driver_id"
                                                           value="<?php echo $_SESSION['userid']; ?>">
                                                    <select class="custom-select col-12" id="driver_name"
                                                            name="driver_name">
                                                        <option value="<?php echo $_SESSION['userid']; ?>">
                                                            <?php echo $_SESSION['name']; ?>
                                                        </option>
                                                    </select>

                                                <?php else: ?>

                                                    <select class="custom-select col-12" id="driver_id"
                                                            name="driver_id">
                                                        <option value="0">--<?php echo $lang['left209'] ?>--</option>
                                                        <?php foreach ($driverrow as $row): ?>
                                                            <option value="<?php echo $row->id; ?>"
                                                                <?php if ($row_order->driver_id == $row->id) echo 'selected'; ?>>
                                                                <?php echo $row->fname . ' ' . $row->lname; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Información de tarifa e impuestos -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="table-responsive" id="table-totals">

                                                <div class="row row-shadow input-container">

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['left905'] ?>
                                                                &nbsp;<?php echo $core->weight_p; ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->value_weight; ?>"
                                                                       name="price_lb" id="price_lb"
                                                                       style="border:1px solid red;">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder21'] ?>
                                                                <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       value="<?php echo $row_order->tax_discount; ?>"
                                                                       name="discount_value" id="discount_value"
                                                                       class="form-control form-control-sm">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="discount">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder22'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->total_insured_value; ?>"
                                                                       name="insured_value" id="insured_value"
                                                                       style="border: 1px solid darkorange;">
                                                            </div>
                                                            <span id="insured_label"></span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder24'] ?>
                                                                <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->tax_insurance_value; ?>"
                                                                       name="insurance_value" id="insurance_value"
                                                                       style="border: 1px solid darkorange;">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="insurance">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder25'] ?>
                                                                <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->tax_custom_tariffis_value; ?>"
                                                                       name="tariffs_value" id="tariffs_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="total_impuesto_aduanero">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder67'] ?>
                                                                <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->tax_value; ?>"
                                                                       name="tax_value" id="tax_value">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="impuesto">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder19'] ?>
                                                                <?php echo $lang['leftorder222221'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->declared_value; ?>"
                                                                       name="declared_value_tax" id="declared_value_tax">
                                                            </div>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="declared_value_label">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['langs_048'] ?></label>
                                                            <div class="input-group">
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event,this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $row_order->total_reexp; ?>"
                                                                       name="reexpedicion_value" id="reexpedicion_value">
                                                            </div>
                                                            <span id="reexpedicion_label"></span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['leftorder1878'] ?></label>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="fixed_value_label">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label style="font-weight:bold;">
                                                                <?php echo $lang['leftorder2021'] ?>
                                                            </label>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="subtotal" class="green-bold">0.00</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-12 col-md-6 col-lg-2">
                                                        <div class="form-group">
                                                            <label style="font-weight:bold;">
                                                                <?php echo $lang['leftorder2020'] ?>
                                                            </label>
                                                            <?php if ($core->for_symbol !== null): ?>
                                                                <b><?php echo $core->for_symbol; ?></b>
                                                            <?php endif; ?>
                                                            <span id="total_envio" class="green-bold">0.00</span>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="text-right">
                                                <input type="hidden" name="total_item_files" id="total_item_files" value="0">
                                                <input type="hidden" name="deleted_file_ids" id="deleted_file_ids">
                                                
                                                <button type="submit" name="create_invoice" id="create_invoice"
                                                        class="btn btn-danger">
                                                    <i class="fas fa-save"></i>
                                                    <span class="ml-1">
                                                        <?php echo $lang['left1103'] ?>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.container-fluid -->

                <input type="hidden" name="order_id" id="order_id"
                       value="<?php echo $row_order->order_id; ?>" />
                <input type="hidden" name="core_meter" id="core_meter"
                       value="<?php echo $row_order->volumetric_percentage; ?>" />
                <input type="hidden" name="core_min_cost_tax" id="core_min_cost_tax"
                       value="<?php echo $core->min_cost_tax; ?>" />
                <input type="hidden" name="core_min_cost_declared_tax" id="core_min_cost_declared_tax"
                       value="<?php echo $core->min_cost_declared_tax; ?>" />

            </form>

            <?php include('views/modals/modal_add_user_shipment.php'); ?>
            <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
            <?php include('views/modals/modal_add_addresses_user.php'); ?>
            <?php include('views/modals/modal_add_addresses_recipient.php'); ?>

        </div>

        <?php include 'views/inc/footer.php'; ?>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <script src="dataJs/courier_edit.js"></script>
</body>
</html>
