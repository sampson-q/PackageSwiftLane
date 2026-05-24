<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB.                                                *
// *                                                                       *
// *************************************************************************

require_once("helpers/querys.php");
require_once("helpers/function_exist.php");

// Datos de usuario para topbar/sidebar y lógica por rol
$userData = $user->cdp_getUserData();

// Contexto multi-tenant de agencia
$ctx = cdp_getAgencyContext();
$agency_default_id = ($ctx['is_restricted'] && $ctx['agency_id'] !== null) ? (int)$ctx['agency_id'] : 0;

$db = new Conexion;

// Defaults del sistema
$db->cdp_query("SELECT * FROM cdb_info_ship_default where id= '1'");
$infoship = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_category where id= '" . $infoship->logistics_default1 . "'");
$s_logistics = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_delivery_time where id = 12");
$delivery_times = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $infoship->pay_default6 . "'");
$metod_payment = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_payment_methods where id= '" . $infoship->payment_default7 . "'");
$payment_methods = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $infoship->status_default8 . "'");
$styles_status = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $infoship->packaging_default2 . "'");
$packaging_box = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $infoship->courier_default3 . "'");
$courier_comp = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $infoship->service_default4 . "'");
$ship_modes = $db->cdp_registro();

//Prefix tracking
$sql = "SELECT * FROM cdb_settings";
$db->cdp_query($sql);
$db->cdp_execute();
$settings = $db->cdp_registro();
$order_prefix = $settings->prefix;

// Catálogos
$verifylocker = $core->cdp_verifylockers();
$lockerauto   = $core->cdp_virtual_locker();

$office       = $core->cdp_getOffices();
$agencyrow    = $core->cdp_getBranchoffices();
$statusrow    = $core->cdp_getStatusByType(2);
$payrow       = $core->cdp_getPayment();
$paymethodrow = $core->cdp_getPaymentMethod();
$itemrow      = $core->cdp_getItem();
$driverrow    = $user->cdp_userAllDriver();
$delitimerow  = $core->cdp_getDelitime();
$track        = $core->cdp_order_track();
$code_countries = $core->cdp_getCodeCountries();
$trackDigitsx = $core->cdp_trackDigits();
$packrow      = $core->cdp_getPack();
$moderow      = $core->cdp_getShipmode();
$modeafterrow = $core->cdp_getShipmodeafter();
$courierrow   = $core->cdp_getCouriercom();
$categories   = $core->cdp_getCategoriesById(27);

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['add-courier'] ?> | <?php echo $core->site_name ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css">
    <link rel="stylesheet" href="assets/template/dist/css/custom_swicth.css">
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .row-shadow {background:#fff;border-radius:.5rem;box-shadow:0 .35rem 1rem rgba(15,23,42,.08);padding:1rem;margin:.5rem 0}
        .green-bold {font-weight:700}
        .d-none {display:none!important}
    </style>

    <style>
    /* Bloque proveedor/millas */
    .rate-box {
        background: #f8f9fa;
        border-radius: .5rem;
        padding: 0.75rem 1rem;
        border: 1px solid #e3e6ea;
    }

    /* Tabla de paquetes */
    .table-packages thead th {
        background: #212529;
        color: #fff;
        border-color: #343a40;
        font-weight: 500;
        font-size: 0.85rem;
        text-transform: uppercase;
    }

    .table-packages tbody td {
        vertical-align: middle;
    }

    /* Totales de peso */
    .weight-summary {
        background: #f8f9fa;
        border-radius: .5rem;
        padding: .75rem 1rem;
        border: 1px dashed #d1d5db;
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
                        <div id="resultados_ajax"></div>
                        <h4 class="page-title ">
                            <i class="ti-package" aria-hidden="true"></i>
                            <?php echo $lang['leftorder11'] ?>
                        </h4>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="container-fluid">
                    <div class="resultados_ajax"></div>

                    <!-- ====================== WIZARD ENVÍO ====================== -->
                    <!-- PASO 0: Prefijo y número -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card"><div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <?php if (isset($_GET['prefix_error']) && $_GET['prefix_error'] == 1) { ?>
                                            <div class="alert alert-danger" id="success-alert">
                                                <p><?php echo $lang['message_ajax_error2']; ?><br> <?php echo $lang['courier_select_country_code']; ?></p>
                                            </div>
                                        <?php } ?>
                                        <label class="control-label col-form-label"><?php echo $lang['leftorder12'] ?></label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="prefix_check" id="prefix_check">
                                                        <?php echo $lang['leftorder13'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control" name="code_prefix" id="code_prefix" value="<?php echo $order_prefix; ?>" readonly>
                                            <select class="custom-select input-sm hide" id="code_prefix2" name="code_prefix2">
                                                <option value=""><?php echo $lang['leftorder14'] ?></option>
                                                <?php foreach ($code_countries as $row): ?>
                                                    <option value="<?php echo $row->iso3; ?>"><?php echo $row->iso3 . ' - ' . $row->name; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <?php if ($core->code_number == 1): ?>
                                        <div class="form-group col-md-6">
                                            <label class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
                                            <div class="input-group mb-3">
                                                <input type="number" class="form-control" name="order_no" id="order_no" value="<?php echo $track; ?>" onchange="cdp_validateTrackNumber(this.value, '<?php echo $track; ?>');">
                                                <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $track; ?>">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-group col-md-6">
                                            <label class="control-label col-form-label"><?php echo $lang['leftorder14442'] ?></label>
                                            <div class="input-group mb-3">
                                                <input type="number" class="form-control" name="order_no" id="order_no" value="<?php print_r(cdp_generarCodigo('' . $core->digit_random . '')); ?>" onchange="cdp_validateTrackNumber(this.value, <?php echo $track; ?>);">
                                                <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $track; ?>">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div></div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card"><div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label class="control-label col-form-label"><?php echo $lang['left201'] ?></label>
                                        <div class="input-group mb-3">
                                            <?php if ($agency_default_id > 0) { ?><input type="hidden" name="agency" id="agency" value="<?php echo $agency_default_id; ?>"><?php } ?>
                                            <select class="custom-select col-12" id="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" name="<?php echo ($agency_default_id > 0) ? '' : 'agency'; ?>" required <?php echo ($agency_default_id > 0) ? 'disabled style="pointer-events:none;background:#e9ecef;"' : ''; ?>>
                                                <?php foreach ($agencyrow as $row): ?>
                                                    <option value="<?php echo (int)$row->id; ?>" <?php echo ($agency_default_id > 0 && (int)$row->id === $agency_default_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row->name_branch ?? ''); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label class="control-label col-form-label"><?php echo $lang['add-title14'] ?></label>
                                        <div class="input-group mb-3">
                                            <select class="custom-select col-12" name="origin_off" id="origin_off" required>
                                                <?php foreach ($office as $row): ?>
                                                    <option value="<?php echo $row->id; ?>"><?php echo $row->name_off; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div></div>
                        </div>
                    </div>

                    <!-- PASO 1: DATOS DEL ENVÍO -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card"><div class="card-body">
                                <h4 class="card-title"><i class="mdi mdi-truck-fast" style="color:#20c997"></i> 1) <?php echo 'Sender / Recipient Information'; ?></h4>
                                <hr>

                                <?php
                                if ($core->active_whatsapp == 1) {
                                ?>
                                    <br>

                                    <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                        <input type="checkbox" class="custom-control-input" name="notify_whatsapp_sender" id="notify_whatsapp_sender" value="1">
                                        <b> <?php echo $lang['leftorder14443']; ?> <i class="mdi mdi-whatsapp" style="font-size: 22px; color:#07bc4c;"></i></b>
                                        <span class="custom-control-indicator"></span>
                                    </label>

                                <?php } ?>

                                <div class="row">
                                    <!-- Remitente -->
                                    <div class="col-md-6">
                                        <label class="control-label col-form-label"><?php echo $lang['sender_search_title'] ?></label>
                                        <div class="row">
                                            <div class="col-md-10">
                                                <select class="select2 form-control custom-select" id="sender_id" name="sender_id" style="width:100%"></select>
                                            </div>
                                            <div class="col-md-2">
                                              <button type="button" class="btn btn-default" data-type_user="user_customer" data-toggle="modal" data-target="#myModalAddUser"><i class="fa fa-plus"></i></button>
                                            </div>
                                        </div>

                                        <label class="control-label col-form-label mt-3"><?php echo $lang['sender_search_address_title'] ?></label>
                                        <div class="row">
                                            <div class="col-md-10">
                                                <select class="select2 form-control" id="sender_address_id" name="sender_address_id" disabled style="width:100%"></select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" id="add_address_sender" data-type_user="user_customer" data-toggle="modal" data-target="#myModalAddUserAddresses" class="btn btn-default" disabled><i class="fa fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Destinatario -->
                                    <div class="col-md-6">
                                        <label class="control-label col-form-label"><?php echo $lang['recipient_search_title'] ?></label>
                                        <div class="row">
                                            <div class="col-md-10">
                                                <select class="select2 form-control custom-select" id="recipient_id" name="recipient_id" disabled style="width:100%"></select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" id="add_recipient" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipient" class="btn btn-default" disabled><i class="fa fa-plus"></i></button>
                                            </div>
                                        </div>

                                        <label class="control-label col-form-label mt-3"><?php echo $lang['recipient_search_address_title'] ?></label>
                                        <div class="row">
                                            <div class="col-md-10">
                                                <select class="select2 form-control" id="recipient_address_id" name="recipient_address_id" disabled style="width:100%"></select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" id="add_address_recipient" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipientAddresses" class="btn btn-default" disabled><i class="fa fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div></div>
                        </div>
                    </div>

                    <!-- PASO 2: PAQUETES -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">

                                    <h4 class="card-title mb-3">
                                        <i class="mdi mdi-cube-scan" style="color:#20c997"></i>
                                        2) <?php echo $lang['left212'] ?>
                                    </h4>

                                    <!-- Línea superior: tarifa manual + botón añadir paquete -->
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
                                        <div class="mb-2 mb-md-0">
                                            <label class="custom-control custom-checkbox m-0">
                                                <?php echo $lang['messagesform112'] ?>
                                                <input type="checkbox" checked class="custom-control-input" name="tariff_mode" id="tariff_mode" value="1">
                                                <span class="custom-control-indicator"></span>
                                            </label>
                                            <a href="shipping_tariffs_add.php" class="btn btn-default btn-sm mt-2">
                                                <span class="ti-shortcode"></span> <?php echo $lang['leftorder17712'] ?>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Bloque Modo de envío / millas / tiempo / pago -->
                                    <div class="rate-box mb-3">
                                        <div class="form-row">
                                            <!-- <div class="form-group col-md-3">
                                                <label class="control-label col-form-label mb-1"><?php echo isset($lang['add-title22']) ? $lang['add-title22'] : 'Modo de envío'; ?></label>
                                                <select class="select2 form-control custom-select" id="order_service_options" name="order_service_options" required style="width:100%" disabled>
                                                    <option value="<?php echo $s_logistics->id; ?>"><?php echo htmlspecialchars($s_logistics->name_item); ?></option>
                                                    <?php foreach ($categories as $row): ?>
                                                        <?php if ($row->id != $s_logistics->id): ?>
                                                        <option value="<?php echo (int)$row->id; ?>"><?php echo htmlspecialchars($row->name_item); ?></option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" id="rate_provider" name="rate_provider" value="internal">
                                            </div>

                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label mb-1">Distancia (millas) (opcional)</label>
                                                <input type="text" class="form-control" id="distance_miles" name="distance_miles"
                                                       value="0" onkeypress="return isNumberKey(event, this)">
                                            </div> -->

                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label mb-1"><?php echo $lang['add-title20'] ?></label>
                                                <select class="select2 form-control custom-select" id="order_deli_time" name="order_deli_time" required disabled style="width:100%">
                                                    <option value="<?php echo $delivery_times->id; ?>"><?php echo $delivery_times->delitime; ?></option>
                                                </select>
                                            </div>

                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label mb-1"><?php echo $lang['payment_methods'] ?></label>
                                                <select class="select2 form-control custom-select" id="order_payment_method" name="order_payment_method" required style="width:100%">
                                                    <option value="<?php echo $payment_methods->id; ?>"><?php echo $payment_methods->label; ?></option>
                                                    <?php foreach ($paymethodrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->label; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Tipo de embalaje -->
                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label"><?php echo $lang['add-title17'] ?></label>
                                                <select class="select2 form-control custom-select" id="order_package" name="order_package" required style="width:100%">
                                                    <option value="<?php echo $packaging_box->id; ?>"><?php echo $packaging_box->name_pack; ?></option>
                                                    <?php foreach ($packrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_pack; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Empresa de mensajería -->
                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label"><?php echo $lang['add-title18'] ?></label>
                                                <select class="select2 form-control custom-select" id="order_courier" name="order_courier" required style="width:100%">
                                                    <option value="<?php echo $courier_comp->id; ?>"><?php echo $courier_comp->name_com; ?></option>
                                                    <?php foreach ($courierrow as $row): ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_com; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <?php if ($userData->userlevel == 3): ?>
                                                <div class="col-md-3">
                                                    <label class="control-label col-form-label"><?php echo $lang['left208'] ?></label>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text" style="color:#ff0000"><i class="fas fa-car"></i></span>
                                                        </div>
                                                        <input type="hidden" name="driver_id" id="driver_id" value="<?php echo $_SESSION['userid']; ?>">
                                                        <select class="custom-select col-12" id="driver_name" name="driver_name">
                                                            <option value="<?php echo $_SESSION['userid']; ?>"><?php echo $_SESSION['name']; ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="form-group col-md-3">
                                                    <label class="control-label col-form-label"><?php echo $lang['left208'] ?></label>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text" style="color:#ff0000"><i class="fas fa-car"></i></span>
                                                        </div>
                                                        <select class="custom-select col-12" id="driver_id" name="driver_id">
                                                            <option value="0">--<?php echo $lang['left209'] ?>--</option>
                                                            <?php foreach ($driverrow as $row): ?>
                                                                <option value="<?php echo $row->id; ?>"><?php echo $row->fname . ' ' . $row->lname; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="form-group col-md-3">
                                                <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title19'] ?> <i style="color:#ff0000" class="fas fa-shipping-fast"></i></label>
                                                <div class="input-group mb-3">
                                                    <select class="custom-select col-12" id="status_courier" name="status_courier" required>
                                                        <option value="<?php echo $styles_status->id; ?>"><?php echo $styles_status->mod_style; ?></option>
                                                        <?php foreach ($statusrow as $row) : ?>
                                                            <?php if ($row->id == 8) { ?>
                                                            <?php } elseif ($row->id == 11) { ?>
                                                            <?php } elseif ($row->id == 12) { ?>
                                                            <?php } elseif ($row->id == 14) { ?>
                                                            <?php } elseif ($row->id == 15) { ?>
                                                            <?php } elseif ($row->id == 16) { ?>
                                                            <?php } elseif ($row->id == 13) { ?>
                                                            <?php } else { ?>
                                                                <option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>
                                                            <?php } ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
    
                                            <div class="col-md-3" style="display:none">
                                                <label class="control-label col-form-label"><?php echo $lang['add-title15'] ?></label>
                                                <div class="input-group">
                                                    <div class="input-group-append" data-target="#datetimepicker1" data-toggle="datetimepicker">
                                                        <div class="input-group-text"><i style="color:#ff0000" class="fa fa-calendar"></i></div>
                                                    </div>
                                                    <input type='text' class="form-control" name="order_date" id="order_date" placeholder="--<?php echo $lang['left206'] ?>--" readonly value="<?php echo date('Y-m-d'); ?>" />
                                                </div>
                                            </div>
    
                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label"><?php echo '# Tracking' ?></label>
                                                <input type='text' class="form-control" id="tracking_number" name="tracking_number" style="border: 1px solid red;" placeholder="# Tracking" />
                                            </div>
                                            
                                            <div class="form-group col-md-3">
                                                <label class="control-label col-form-label"><?php echo 'Estimated Time of Arrival' ?></label>
                                                <input type='date' class="form-control" id="estimated_eta" name="estimated_eta" style="border: 1px solid red;" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de paquetes -->
                                     <div class="text-md-right">
                                            <button type="button" onclick="addPackage()" name="add_rows" id="add_rows" class="btn btn-outline-dark">
                                                <span class="fa fa-plus"></span> <?php echo $lang['left231'] ?>
                                            </button>
                                        </div>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-bordered table-hover table-packages mb-0" id="packages_table">
                                            <thead>
                                                <tr>
                                                    <th style="width:70px;"><?php echo $lang['courier_table_qty']; ?></th>
                                                    <th class="text-center" style="min-width:140px;"><?php echo $lang['left213']; ?></th>
                                                    <th><?php echo 'Weight (TRW)'; ?></th>
                                                    <th class="text-center"><?php echo $lang['left216'] . ' (TVW)'; ?></th>
                                                    <th class="text-center"><?php echo $lang['left217'] . ' (TVW)'; ?></th>
                                                    <th class="text-center"><?php echo $lang['left218'] . ' (TVW)'; ?></th>
                                                    <th style="width:60px;"><?php echo $lang['courier_table_remove']; ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Filas dinámicas vía addPackage() -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Totales parciales de paquetes -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="weight-summary mb-2 mb-md-0">
                                                <small class="text-muted d-block"><?php echo $lang['courier_weight_total']; ?></small>
                                                <span class="h5 mb-0" id="total_weight">0.00</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="weight-summary">
                                                <small class="text-muted d-block"><?php echo $lang['courier_vol_weight_total']; ?></small>
                                                <span class="h5 mb-0" id="total_vol_weight">0.00</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- FILE ATTACHMENT AND CAMERA CAPTURE SECTION (FROM OLD VERSION) -->
                                    <div class="row mt-4">
                                        <div class="col-md-2">
                                            <div>
                                                <label class="control-label" id="selectItem"><?php echo $lang['leftorder15']; ?></label>
                                            </div>

                                            <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple="multiple" type="file" style="display: none;" onchange="cdp_validateZiseFiles(); cdp_preview_images();" />
                                            <button type="button" id="openMultiFile" class="btn btn-default pull-left mb-4"> <i class='fa fa-paperclip' style="font-size:18px; cursor:pointer;"></i> <?php echo $lang['leftorder16']; ?> </button>
                                        </div>

                                        <div class="col-md-2">
                                            <div>
                                                <label class="control-label" id="captureItem"><?php echo $lang['leftorder90']; ?></label>
                                            </div>

                                            <button type="button" id="openCameraButton" class="btn btn-dark pull-left mb-4">
                                                <i class="fa fa-camera" style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder90']; ?>
                                            </button>

                                            <div class="mt-2 d-flex align-items-start" style="gap:.5rem;">
                                                <video id="cameraPreview" autoplay playsinline style="width:220px; height:165px; background:#000; display:none; border-radius:6px; object-fit:cover;"></video>

                                                <div style="flex:1;">
                                                    <div style="margin-bottom:.5rem;">
                                                        <button type="button" id="takeCameraPhoto" class="btn btn-success btn-sm" style="display:none;"><?php echo $lang['left1105']; ?></button>
                                                        <button type="button" id="stopCamera" class="btn btn-secondary btn-sm" style="display:none;"><?php echo $lang['left1111']; ?></button>
                                                    </div>
                                                </div>
                                            </div>

                                            <input class="custom-file-input" id="filesCapture" name="filesCapture[]" multiple="multiple" type="file" accept="image/*" capture="environment" style="display:none;" />
                                        </div>
                                    </div>

                                    <div class="col-md-12 row" id="image_preview"></div>

                                    <div class="col-md-4 mt-4">
                                        <div id="clean_files" class="hide">
                                            <button type="button" id="clean_file_button" class="btn btn-danger ml-3"> <i class='fa fa-trash' style="font-size:18px; cursor:pointer;"></i> <?php echo $lang['leftorder17']; ?> </button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="resultados_file col-md-4 pull-right mt-4"></div>
                                    </div>

                                </div><!-- card-body -->
                            </div><!-- card -->
                        </div>
                    </div>

                        <!-- PASO 3: SERVICIO & COTIZACIÓN -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-body">

                                        <h4 class="card-title mb-3">
                                            <i class="mdi mdi-clipboard-check-outline" style="color:#20c997"></i>
                                            3) <?php echo 'Rate and Taxes'; ?>
                                        </h4>

                                        <!-- INFORMACIÓN DE TARIFA E IMPUESTOS -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <h5 class="mb-2">
                                                    <i class="mdi mdi-cash-multiple" style="color:#20c997"></i>
                                                    <?php echo $lang['messageerrorform30']; ?>
                                                </h5>

                                                <!-- contorno igual al de Proveedor de tarifa -->
                                                <div id="table-totals" class="rate-box">

                                                    <!-- Fila 1 -->
                                                    <div class="row">
                                                        <!-- Precio por lb/kg -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm">
                                                                    <?php echo $lang['left905']; ?>&nbsp;<?php echo $core->weight_p; ?>
                                                                </label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $core->value_weight; ?>"
                                                                       name="price_lb" id="price_lb"
                                                                       style="border: 1px solid red;">
                                                            </div>
                                                        </div>

                                                        <!-- Descuento % -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder21']; ?> <?php echo $lang['leftorder222221']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       value="0"
                                                                       name="discount_value" id="discount_value"
                                                                       class="form-control form-control-sm">
                                                                <small>
                                                                    <?php if ($core->for_symbol !== null): ?>
                                                                        <b><?php echo $core->for_symbol; ?></b>
                                                                    <?php endif; ?>
                                                                    <span id="discount"> 0.00</span>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Valor asegurado -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder22']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="100"
                                                                       name="insured_value" id="insured_value">
                                                                <small id="insured_label"></small>
                                                            </div>
                                                        </div>

                                                        <!-- Seguro de envío % -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder24']; ?> <?php echo $lang['leftorder222221']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $core->insurance; ?>"
                                                                       name="insurance_value" id="insurance_value">
                                                                <small>
                                                                    <?php if ($core->for_symbol !== null): ?>
                                                                        <b><?php echo $core->for_symbol; ?></b>
                                                                    <?php endif; ?>
                                                                    <span id="total_impuesto_aduanero"> 0.00</span>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Aranceles aduaneros % -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder25']; ?> <?php echo $lang['leftorder222221']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $core->c_tariffs; ?>"
                                                                       name="tariffs_value" id="tariffs_value">
                                                                <small>
                                                                    <?php if ($core->for_symbol !== null): ?>
                                                                        <b><?php echo $core->for_symbol; ?></b>
                                                                    <?php endif; ?>
                                                                    <span id="impuesto"> 0.00</span>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Impuesto % -->
                                                        <div class="col-sm-6 col-md-2">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder67']; ?> <?php echo $lang['leftorder222221']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $core->tax; ?>"
                                                                       name="tax_value" id="tax_value">
                                                                <small>
                                                                    <?php if ($core->for_symbol !== null): ?>
                                                                        <b><?php echo $core->for_symbol; ?></b>
                                                                    <?php endif; ?>
                                                                    <span id="declared_value_label"> 0.00</span>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Fila 2: Valor declarado / Re expedición -->
                                                    <div class="row mt-2">
                                                        <!-- Valor declarado % -->
                                                        <div class="col-sm-6 col-md-3">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['leftorder66']; ?> <?php echo $lang['leftorder222221']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $core->declared_tax; ?>"
                                                                       name="declared_value_tax" id="declared_value_tax">
                                                                <small>
                                                                    <?php if ($core->for_symbol !== null): ?>
                                                                        <b><?php echo $core->for_symbol; ?></b>
                                                                    <?php endif; ?>
                                                                    <span id="insurance"> 0.00</span>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Re expedición -->
                                                        <div class="col-sm-6 col-md-3">
                                                            <div class="form-group mb-2">
                                                                <label class="control-label col-form-label-sm"><?php echo $lang['langs_048']; ?></label>
                                                                <input type="text"
                                                                       onchange="calculateFinalTotal(this);"
                                                                       onkeypress="return isNumberKey(event, this)"
                                                                       class="form-control form-control-sm"
                                                                       value="0"
                                                                       name="reexpedicion_value" id="reexpedicion_value">
                                                                <small id="reexpedicion_label" class="d-block text-right"></small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Fila 3: tarjetas tipo Peso real / volumétrico -->
                                                    <div class="row mt-3">
                                                        <!-- Cargo fijo -->
                                                        <div class="col-md-4">
                                                            <div class="weight-summary mb-2 mb-md-0">
                                                                <small class="text-muted d-block"><?php echo $lang['leftorder1878'] ?></small>
                                                                <?php if ($core->for_symbol !== null): ?>
                                                                    <b><?php echo $core->for_symbol; ?></b>
                                                                <?php endif; ?>
                                                                <span id="fixed_value_label"> 0.00</span>
                                                            </div>
                                                        </div>

                                                        <!-- Sub total -->
                                                        <div class="col-md-4">
                                                            <div class="weight-summary mb-2 mb-md-0">
                                                                <small class="text-muted d-block"><?php echo $lang['leftorder2021'] ?></small>
                                                                <?php if ($core->for_symbol !== null): ?>
                                                                    <b><?php echo $core->for_symbol; ?></b>
                                                                <?php endif; ?>
                                                                <span id="subtotal" class="green-bold"> 0.00</span>
                                                            </div>
                                                        </div>

                                                        <!-- TOTAL -->
                                                        <div class="col-md-4">
                                                            <div class="weight-summary">
                                                                <small class="text-muted d-block"><?php echo $lang['leftorder2020'] ?></small>
                                                                <?php if ($core->for_symbol !== null): ?>
                                                                    <b><?php echo $core->for_symbol; ?></b>
                                                                <?php endif; ?>
                                                                <span id="total_envio" class="green-bold"> 0.00</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Botones -->
                                                    <div class="row mt-3">
                                                        <div class="col-12 text-right">
                                                            <!-- <button type="button" name="calculate_invoice" id="calculate_invoice" class="btn btn-info">
                                                                <i class="fas fa-calculator"></i>
                                                                <span class="ml-1"><?php echo $lang['leftorder17714']; ?></span>
                                                            </button> -->
                                                            <button type="submit" name="create_invoice" id="create_invoice" class="btn btn-danger">
                                                                <i class="fas fa-save"></i>
                                                                <span class="ml-1"><?php echo $lang['left1103'] ?></span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                </div><!-- /.rate-box -->
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                    <!-- ==================== /WIZARD ENVÍO ==================== -->

                    <!-- Hiddens necesarios -->
                    <input type="hidden" name="total_item_files" id="total_item_files" value="0" />
                    <input type="hidden" name="deleted_file_ids" id="deleted_file_ids" />
                    <input type="hidden" name="core_meter" id="core_meter" value="<?php echo $core->meter; ?>" />
                    <input type="hidden" name="core_min_cost_tax" id="core_min_cost_tax" value="<?php echo $core->min_cost_tax; ?>" />
                    <input type="hidden" name="core_min_cost_declared_tax" id="core_min_cost_declared_tax" value="<?php echo $core->min_cost_declared_tax; ?>" />
                    <input type="hidden" name="translate_quantity" id="translate_quantity" value="<?php echo $lang['left1103'] ?>" />
                    <!-- Peso cobrable que rellena el JS -->
                    <input type="hidden" id="chargeable_weight" name="chargeable_weight" value="0">
                </div>

                <?php include('views/modals/modal_add_user_shipment.php'); ?>
                <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
                <?php include('views/modals/modal_add_addresses_user.php'); ?>
                <?php include('views/modals/modal_add_addresses_recipient.php'); ?>
            </form>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- JS -->
    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <!-- Tu automatización -->
    <script src="dataJs/courier_add.js"></script>
</body>
</html>
