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
$core = new Core;
$db = new Conexion;

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

if (isset($_GET['id_notification'])) {
    # code...

    $user_log = $_SESSION['userid'];
    $id_notification = $_GET['id_notification'];

    cdp_updateNotificationRead($user_log, $id_notification);
}




$row_order = $data['data'];

// STRICT OWNERSHIP: customers (userlevel 1) may only view packages they sent.
// Viewing anyone else's shipment is forbidden.
if (isset($userData->userlevel) && (int)$userData->userlevel === 1
    && (int)($row_order->sender_id ?? 0) !== (int)($_SESSION['userid'] ?? 0)) {
    header('Location: error403.php');
    exit;
}

$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $row_order->status_courier . "'");
$status_courier = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

$recipient_type = isset($row_order->recipient_type) ? $row_order->recipient_type : 'recipient';

if ($recipient_type === 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . intval($row_order->receiver_id) . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . intval($row_order->receiver_id) . "'");
}

$receiver_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->order_prefix . $row_order->order_no . "'");
$address_order = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row_order->order_courier . "'");
$courier_com = $db->cdp_registro();

// Item category from the order itself. Was hardcoded to 27 (Ocean Freight),
// which mislabeled every air shipment. Air courier is never legitimately
// "Ocean Freight" (27) — that was the erroneous legacy default — so coerce
// 27/none to "Air Freight" (26). New shipments store 26 from add_courier_ajax.
$fs_cat_id = (int) ($row_order->order_item_category ?? 0);
if ($fs_cat_id <= 0 || $fs_cat_id === 27) $fs_cat_id = 26;
$db->cdp_query("SELECT * FROM cdb_category where id = " . $fs_cat_id);
$category = $db->cdp_registro();

// $db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row_order->order_service_options . "'");
$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= 8");
$order_service_options = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $row_order->order_package . "'");
$packaging = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $row_order->order_deli_time . "'");
$delivery_time = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_branchoffices where id= '" . $row_order->agency . "'");
$branchoffices = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_offices where id= '" . $row_order->origin_off . "'");
$offices = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $row_order->order_payment_method . "'");
$met_payment = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail where order_no='" . $row_order->order_no . "'");
$consolidate_id = $db->cdp_registro() -> consolidate_id;

$db->cdp_query("SELECT status_courier FROM cdb_consolidate where consolidate_id='" . $consolidate_id . "'");
$consolidate_status_courier = $db->cdp_registro() -> status_courier;

$db->cdp_query("SELECT * FROM cdb_styles where id='" . $consolidate_status_courier . "'");
$consolidate_style = $db->cdp_registro();

// Legacy-aware: old-system orders kept the postal tracking on cdb_add_order.tracking_num.
$postal_tracking = cdp_getPackageTrackingLegacyAware((int) $_GET['id']);


$dias_ = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
$meses_ = array(
    '01' => $lang['translate_graphic_0'],
    '02' => $lang['translate_graphic_1'],
    '03' => $lang['translate_graphic_2'],
    '04' => $lang['translate_graphic_3'],
    '05' => $lang['translate_graphic_4'],
    '06' => $lang['translate_graphic_5'],
    '07' => $lang['translate_graphic_6'],
    '08' => $lang['translate_graphic_7'],
    '09' => $lang['translate_graphic_8'],
    '10' => $lang['translate_graphic_9'],
    '11' => $lang['translate_graphic_10'],
    '12' => $lang['translate_graphic_11']
);


$fecha = strtotime($row_order->order_datetime);
$anio = date("Y", $fecha);
$mes = date("m", $fecha);
$dia = date("d", $fecha);

if ($row_order->status_invoice == 1) {

    $text_status = $lang['invoice_paid'];
    $label_class = "label-success";
} else if ($row_order->status_invoice == 2) {

    $text_status = $lang['invoice_pending'];
    $label_class = "label-warning";
} else if ($row_order->status_invoice == 3) {
    $text_status = $lang['verify_payment'];
    $label_class = "label-info";
}

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
    <title> <?php echo $lang['left492'] ?> <?php echo $row_order->order_prefix . $row_order->order_no; ?> | <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
    <?php include 'views/inc/head_scripts.php'; ?>

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


            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card" style=" padding-bottom: 40px">
                            <div class="card-body">

                                <div class="mb-3" id="resultados_ajax_cancel"></div>
                                <div class="mb-3" id="resultados_ajax"></div>

                                <div class="row"> 
                                    <div class=" col-sm-12 col-md-6 mb-2">
                                        <h3><b class="text-danger"><?php echo $lang['left533020013'] ?></b> <span>#<?php echo $row_order->order_prefix . $row_order->order_no; ?></span></h3>
                                    </div>

                                    <?php if ($row_order->status_courier != 14) { ?>

                                        <div class="col-sm-12 col-md-6 mb-2">
                                            <div class="pull-right">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-block btn-outline-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <?php echo $lang['left533020014'] ?>
                                                    </button>
                                                    <div class="dropdown-menu scrollable-menu" style="overflow-y: auto; max-height: 500px;">
                                                        <!-- VERIFICAR PAGOS DE ENVÍOS PERMISO -->
                                                        <?php if ($row_order->status_invoice == 2 && $user->cdp_hasPermission('verify_payments')) { ?>
                                                            <?php if ($userData->userlevel == 1) { ?>
                                                                <a class="dropdown-item" href="add_payment_gateways_courier.php?id_order=<?php echo $row_order->order_id; ?>">
                                                                    <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder32'] ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- VERIFICAR PAGOS DE ENVÍOS (Status = 3) PERMISO -->
                                                        <?php if ($row_order->status_invoice == 3 && $user->cdp_hasPermission('verify_payments')) { ?>
                                                            <?php if ($userData->userlevel != 1) { ?>
                                                                <a class="dropdown-item" data-toggle="modal" data-target="#detail_payment_packages" data-id="<?php echo $row_order->order_id; ?>" data-customer="<?php echo $row_order->sender_id; ?>">
                                                                    <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder33'] ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- ACEPTAR ENVÍO PERMISO -->
                                                        <?php if ($row_order->order_incomplete == 0 && $row_order->is_pickup == 0 && $user->cdp_hasPermission('complete_client_shipment') && $userData->userlevel != 1) { ?>
                                                            <a class="dropdown-item" href="courier_accept.php?id=<?php echo $row_order->order_id; ?>">
                                                                <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['left533020017'] ?>
                                                            </a>
                                                        <?php } ?>

                                                        <!-- IMPRIMIR ETIQUETA DE ENVÍO PERMISO -->
                                                        <?php if ($row_order->order_incomplete == 0 && $user->cdp_hasPermission('print_label')) { ?>
                                                            <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row_order->order_id; ?>" target="_blank">
                                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?>
                                                            </a>
                                                        <?php } ?>

                                                        <!-- EDITAR ENVÍO PERMISO -->
                                                        <?php if ($row_order->order_incomplete == 1 && $user->cdp_hasPermission('edit_shipment')) { ?>
                                                            <?php if (/*$row_order->is_consolidate == 0 &&*/$userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                                                <?php if ($row_order->status_courier != 8) { ?>
                                                                    <a class="dropdown-item" href="courier_edit.php?id=<?php echo $_GET['id']; ?>">
                                                                        <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['tooledit'] ?>
                                                                    </a>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- ANULAR ENVÍO PERMISO -->
                                                        <?php if ($user->cdp_hasPermission('cancel_shipment')) { ?>
                                                            <?php if ($row_order->status_courier != 21 && $row_order->status_courier != 12) { ?>
                                                                <a class="dropdown-item" data-id="<?php echo $row_order->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalCancel">
                                                                    <i style="color:#f62d51" class="fas fa-times-circle"></i>&nbsp;<?php echo $lang['leftorder34444']; ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- ASIGNAR CONDUCTOR A ENVÍO PERMISO -->
                                                        <?php if ($user->cdp_hasPermission('assign_drivers')) { ?>
                                                            <?php if ($row_order->status_courier != 21 && $row_order->status_courier != 12 && $row_order->status_courier != 8) { ?>
                                                                <a class="dropdown-item" data-toggle="modal" data-target="#modalDriver" data-id_shipment="<?php echo $row_order->order_id; ?>">
                                                                    <i style="color:#ff0000" class="fas fa-car"></i>&nbsp;<?php echo $lang['left208']; ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- SEGUIMIENTO DE ENVÍO PERMISO -->
                                                        <?php if ($user->cdp_hasPermission('track_shipment')) { ?>
                                                            <?php if ($row_order->status_courier != 21 && $row_order->status_courier != 12) { ?>
                                                                <a class="dropdown-item" href="courier_shipment_tracking.php?id=<?php echo $_GET['id']; ?>" title="<?php echo $lang['toolupdate'] ?>">
                                                                    <i style="color:#20c997" class="ti-reload">&nbsp;</i><?php echo $lang['toolupdate'] ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- IMPRIMIR ENVÍO PERMISO -->
                                                        <?php if ($user->cdp_hasPermission('print_shipment')) { ?>
                                                            <a class="dropdown-item" target="blank" href="print_inv_ship.php?id=<?php echo $_GET['id']; ?>">
                                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toolprint']; ?>
                                                            </a>
                                                        <?php } ?>

                                                        <!-- ENVIAR CORREO DE ENVÍO PERMISO -->
                                                        <?php if ($user->cdp_hasPermission('send_email_attachment')) { ?>
                                                            <a class="dropdown-item" href="#" data-toggle="modal" data-id="<?php echo $row_order->order_id; ?>" data-email="<?php echo $sender_data->email; ?>" data-order="<?php echo $row_order->order_prefix . $row_order->order_no; ?>" data-target="#myModal">
                                                                <i class="fas fa-envelope"></i>&nbsp;<?php echo $lang['left533020019'] ?>
                                                            </a>
                                                        <?php } ?>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    <?php } else { ?>

                                        <div class="col-sm-12 col-md-6 mb-2">
                                            <div class="pull-right">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <?php echo $lang['left533020014'] ?>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <!-- ACEPTAR RECOGIDA (si el usuario tiene el permiso para hacerlo) -->
                                                        <?php if ($user->cdp_hasPermission('complete_client_shipment') && ($userData->userlevel == 9 || $userData->userlevel == 3 || $userData->userlevel == 2)) { ?>
                                                            <?php if ($row_order->status_courier == 14) { ?>
                                                                <a class="dropdown-item" href="pickup_accept.php?id=<?php echo $row_order->order_id; ?>">
                                                                    <i style="color:#20c997" class="fas fa-check-circle"></i>&nbsp;<?php echo $lang['left533020020'] ?>
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <!-- ANULAR RECOGIDA PERMISO (solo si tiene el permiso) -->
                                                        <?php if ($user->cdp_hasPermission('cancel_shipment')) { ?>
                                                            <a class="dropdown-item" data-id="<?php echo $row_order->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalCancel">
                                                                <i style="color:#f62d51" class="fas fa-times-circle"></i>&nbsp;<?php echo $lang['left533020021'] ?>
                                                            </a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div class="row">
                                    <div class=" col-sm-12 col-md-6 mb-2">
                                        <b class=""><?php echo $lang['left506']?></b>
                                        <span class="label" style="background-color: <?php echo $row_order->is_consolidate ? $consolidate_style->color : $status_courier->color; ?>"><?php echo $row_order->is_consolidate ? $consolidate_style->mod_style : $status_courier->mod_style; ?></span>
                                        <?php if (isset($row_order->is_dangerous_good) && (int)$row_order->is_dangerous_good === 1) { $dg_style = cdp_getDangerousGoodsStyle(); if ($dg_style) { ?>
                                            <span class="label" style="background-color: <?php echo htmlspecialchars($dg_style->color, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(str_replace('_', ' ', $dg_style->mod_style), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php } } ?>
                                    </div>

                                    <div class=" col-sm-12 col-md-6 mb-2">
                                        <b class=""><?php echo $lang['left533020022'] ?></b>
                                        <span class="label <?php echo $label_class; ?>"><?php echo $text_status; ?></span>
                                    </div>
                                </div>

                                <br>

                                <div class="row">
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['tools-branchOffice4'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php if ($branchoffices != null) { echo $branchoffices->name_branch; } ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['tools-office1'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php if ($offices != null) { echo $offices->name_off; } ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['itemcategory'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $category->name_item; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5>&nbsp;<b><?php echo $lang['track-shipment19'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php echo $row_order->order_datetime; ?></p>

                                            <h5>&nbsp;<b><?php echo $lang['langs_034'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php if ($delivery_time != null) { echo $delivery_time->delitime;} ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['tools-courier1'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php if ($courier_com != null) { echo $courier_com->name_com; } ?>
                                            </p>

                                            <h5> &nbsp;<b><?php echo $lang['tools-shipmode1'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php echo $order_service_options->ship_mode . ' (' . $order_service_options->detail . ')'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['eta'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php echo $postal_tracking->estimated_eta != null ? $postal_tracking->estimated_eta : 'N/A'; ?>
                                            </p>

                                            <h5> &nbsp;<b><?php echo $lang['postal_tracking'] ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php echo $postal_tracking->tracking_number != null ? $postal_tracking->tracking_number : 'N/A'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo 'Notes' ?></b></h5>
                                            <p class="text-muted  m-l-5">
                                                <?php echo $row_order->courier_notes != null ? $row_order->courier_notes : 'N/A'; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php if ($row_order->status_courier == 21) { ?>
                                        <?php if ($row_order->reason_cancel != null) { ?>
                                            <div class="col-md-12 pt-4">
                                                <div class="">
                                                    <h5> &nbsp;<b><?php echo $lang['left533020023'] ?></b></h5>
                                                    <b class="text-danger  m-l-5">
                                                        <?php if ($row_order->reason_cancel != null) {
                                                            echo $row_order->reason_cancel;
                                                        } ?></b>
                                                </div>
                                            </div>
                                    <?php } }?>
                                </div>

                                <?php
                                $track_c = $row_order->order_prefix . $row_order->order_no;

                                $db->cdp_query("SELECT * FROM cdb_payments_gateway  where order_track ='" . $track_c . "'");
                                $order_p = $db->cdp_registro();

                                if ($order_p) {

                                    if ($order_p->status === 'COMPLETED' || $order_p->status === 'succeeded' || $order_p->status === 'success') {
                                        $text_status_payment = $lang['left533020024'];
                                        $label_class_payment = "label-success";
                                    } else {

                                        $text_status_payment = $order_p->status;
                                        $label_class_payment = "label-warning";
                                    }
                                ?>

                                    <div class="row">

                                        <div class=" col-sm-12 col-md-12 mb-2">
                                            <br>
                                            <br>

                                            <h4><span><b><?php echo $lang['tools-config118'] ?></b></span></h4>
                                            <br>
                                            <br>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['leftorder157'] ?></b></h5>
                                                <p class="text-muted  m-l-5"><?php echo date('Y-m-d h:i A', strtotime($order_p->date_payment)); ?></p>
                                            </div>

                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['left533020025'] ?></b></h5>
                                                <p class="text-muted  m-l-5"><?php echo $order_p->gateway; ?></p>
                                            </div>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">

                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['left533020026'] ?></b></h5>

                                                <b class="text-muted  m-l-5"><?php echo $order_p->payment_transaction; ?></b>
                                            </div>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['payment5'] ?></b></h5>
                                                <b class="text-muted  m-l-5"><?php echo $order_p->amount; ?></b>
                                            </div>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['tools-config52'] ?></b></h5>
                                                <b class="text-muted  m-l-5"><?php echo $order_p->currency; ?></b>
                                            </div>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b><?php echo $lang['tools-statuscourier7'] ?></b></h5>
                                                <span class="label <?php echo $label_class_payment; ?>"><?php echo $text_status_payment; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                <?php

                                }

                                ?>

                                <!-- VERIFY PAYMENT PERMISSION  -->
                                <?php if ($user->cdp_hasPermission('verify_payments') && in_array($userData->userlevel, [2, 3, 9])) { ?>

                                    <?php

                                    // Verificación de condiciones para mostrar el bloque
                                    if ($row_order->url_payment_attach != null || $row_order->status_invoice == 3) { ?>
                                        <div class="table-responsive">
                                            <table id="zero_config" class="table table-striped">
                                                <thead class="bg-inverse text-white">
                                                    <tr>
                                                        <!-- Aquí van los encabezados de la tabla -->
                                                        <th><?php echo $lang['leftorder157']; ?></th>
                                                        <th><?php echo $lang['left603']; ?></th>
                                                        <th><?php echo $lang['user_manage31']; ?></th>
                                                        <th><?php echo $lang['left533020027']; ?></th> <!-- Columna adicional para el botón -->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <!-- Columna 1: Detalles del pago -->
                                                        <td>
                                                            <p class="text-muted m-l-5"><?php echo formatDate($row_order->payment_date); ?></p>
                                                        </td>

                                                        <!-- Columna 2: Método de pago -->
                                                        <td>
                                                            <p class="text-muted m-l-5"><?php echo getTextOrDefault($met_payment->name_pay); ?></p>
                                                        </td>

                                                        <!-- Columna 3: Notas del cliente -->
                                                        <td>
                                                            <b class="text-muted m-l-5"><?php echo getTextOrDefault($row_order->notes); ?></b>
                                                        </td>

                                                        <!-- Columna 4: Enlace al archivo de pago -->
                                                        <td>
                                                            <a href="assets/<?php echo getTextOrDefault($row_order->url_payment_attach); ?>" target="blank" class="btn btn-info text- btn-sm">
                                                                <?php echo $lang['left533020028'] ?>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Row -->
                <?php
                if ($row_order->status_courier == 8) {


                    $db->cdp_query("SELECT * FROM cdb_courier_track where order_track='" . $row_order->order_prefix . $row_order->order_no . "'");
                    $courier_track = $db->cdp_registro();

                    $fecha_delivered = strtotime($courier_track->t_date);
                    $anio_delivered = date("Y", $fecha_delivered);
                    $mes_delivered = date("m", $fecha_delivered);
                    $dia_delivered = date("d", $fecha_delivered);
                    $time_delivered = date("h:i A", $fecha_delivered);


                    $db->cdp_query("SELECT * FROM cdb_users where id='" . $courier_track->user_id . "'");
                    $user_delivered = $db->cdp_registro();

                ?>
                    <div class="row">
                        <div class="col-lg-12 col-xl-12 col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-md-flex align-items-center">
                                        <div>
                                            <h3 class="card-title"><span><?php echo $lang['left533020029'] ?></span></h3>
                                        </div>
                                    </div>
                                    <div><hr></div>
                                    <div class="row">
                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b> <?php echo $lang['leftorder51'] ?></b></h5>
                                                <p class="text-muted  m-l-5"><?php echo $meses_[$mes_delivered] . ' ' . $dia_delivered . ', ' . $anio_delivered . ' ' . $time_delivered; ?></p>

                                            </div>
                                        </div>

                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b> <?php echo 'Recipient Name' ?></b></h5>
                                                <p class="text-muted  m-l-5"><?php echo $user_delivered->fname . ' ' . $user_delivered->lname; ?></p>
                                            </div>
                                        </div>
                                        <div class=" col-sm-12 col-md-4 mb-2">
                                            <div class="">
                                                <h5> &nbsp;<b> <?php echo $lang['leftorder53'] ?></b></h5>
                                                <p class="text-muted  m-l-5"><?php echo $row_order->person_receives; ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    $dir = 'doc_signs/shipments_courier/' . $row_order->order_id . '.png';

                                    ?>
                                    <div class="row">
                                        <div class=" col-sm-12 col-md-6 mb-2">
                                            <h5> &nbsp;<b> <?php echo $lang['leftorder54'] ?></b></h5>
                                            <img src="doc_signs/shipments_courier/<?php echo $row_order->order_id; ?>.png" style="max-width:50%;width:auto;height:auto;">
                                        </div>
                                        <?php

                                        if (!empty($row_order->photo_delivered)) { ?>

                                            <div class=" col-sm-12 col-md-6 mb-2">
                                                <h5> &nbsp;<b> <?php echo 'Delivery Image' ?></b></h5>
                                                <img src="<?php echo $row_order->photo_delivered; ?>" width="400" height="250" style="max-width:50%;width:auto;height:auto;">
                                            </div>
                                        <?php
                                        } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>

                <!-- PACKAGES FILES PERMISSION  -->
                <?php if ($user->cdp_hasPermission('view_details_courier_files')) { ?>

                <?php

                $db->cdp_query("SELECT * FROM cdb_order_files where order_id='" . $_GET['id'] . "' ORDER BY date_file");
                $files_order = $db->cdp_registros();
                $numrows = $db->cdp_rowCount();


                if ($numrows > 0) {
                ?>
                    <div class="row">
                        <div class="col-lg-12 col-xl-12 col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-md-flex align-items-center">
                                        <div>
                                            <h3 class="card-title"><span><?php echo $lang['leftorder56'] ?></span></h3>
                                        </div>
                                    </div>
                                    <div><hr></div> 
                                    <div class="table-responsive">
                                        <table id="zero_config" class="table table-striped">
                                            <thead class="bg-inverse text-white">
                                                <tr>
                                                    <th><?php echo $lang['left533020029'] ?></th>
                                                    <th><?php echo $lang['left533020030'] ?></th>
                                                    <th><?php echo $lang['left533020031'] ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="projects-tbl">

                                                <?php
                                                $count = 0;
                                                foreach ($files_order as $file) {
                                                    $date_add = date("Y-m-d h:i A", strtotime($file->date_file));
                                                    $count++;
                                                ?>

                                                    <tr class="card-hover">
                                                        <td><?php echo $count; ?></td>
                                                        <td> <a style="color:#7460ee;" target="_blank" href="<?php echo $file->url; ?>" class=""><?php echo $file->name; ?> </a></td>
                                                        <td><?php echo $date_add; ?></td>

                                                    </tr>
                                                <?php
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                } ?>
                <?php
                } ?>

                <?php

                $db->cdp_query("SELECT * FROM cdb_order_files where order_id='" . $_GET['id'] . "' ORDER BY date_file");
                $files_order = $db->cdp_registros();
                $numrows = $db->cdp_rowCount();

                if ($numrows > 0) {
                ?>
                    <div class="row">
                        <div class="col-lg-12 col-xl-12 col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-md-flex align-items-center">
                                        <div>
                                            <h3 class="card-title"><span><?php echo $lang['leftorder59'] ?></span></h3>
                                        </div>
                                    </div>
                                    <div><hr></div>
                                    <div class="col-md-12 row">

                                        <?php
                                        $count = 0;
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
                                            }

                                            $video_exts = array('webm', 'mp4', 'm4v', 'mov', 'ogg', 'ogv', '3gp', '3gpp', 'mkv', 'avi');
                                            $is_video   = in_array(strtolower((string) $file->file_type), $video_exts, true);

                                            $count++;
                                        ?>

                                            <div class=" col-sm-12 col-md-3 mb-2">

                                                <?php if ($is_video) { ?>
                                                    <video style="width: 180px; height: 180px; background:#000;" class="img-thumbnail" controls preload="metadata" src="<?php echo $file->url; ?>"></video>
                                                <?php } else { ?>
                                                    <img style="width: 180px; height: 180px;" class="img-thumbnail" src="<?php echo $src; ?>">
                                                <?php } ?>

                                                <div class="row ">
                                                    <div class=" col-md-12 mb-2 mt-2">
                                                        <p class="text-justify"><a style="color:#7460ee;" target="_blank" href="<?php echo $file->url; ?>" class=""><?php echo $file->name; ?> </a></p>

                                                    </div>

                                                </div>
                                            </div>
                                        <?php
                                        } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                } ?>


                <!-- COURIER TRACKINGS PERMISSION  -->
                <?php if ($user->cdp_hasPermission('view_tracking_history_courier')) { ?>
                <?php

                $db->cdp_query("SELECT * FROM cdb_courier_track where order_track='" . $row_order->order_prefix . $row_order->order_no . "' ORDER BY t_date");
                $courier_track_items = $db->cdp_registros();
                $numrows = $db->cdp_rowCount();


                if ($numrows > 0) {
                ?>
                    <div class="row">
                        <div class="col-lg-12 col-xl-12 col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-md-flex align-items-center">
                                        <div>
                                            <h3 class="card-title"><span><?php echo $lang['left502'] ?></span></h3>
                                        </div>
                                    </div>
                                    <div><hr></div>

                                    <div class="table-responsive">
                                        <table id="zero_config" class="table table-striped">
                                            <thead class="bg-inverse text-white">
                                                <tr class="text-white">
                                                    <th><?php echo $lang['left503'] ?></th>
                                                    <th><?php echo $lang['left504'] ?></th>
                                                    <th><?php echo $lang['left505'] ?></th>
                                                    <th><?php echo $lang['left506'] ?></th>
                                                    <th><?php echo $lang['left507'] ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="projects-tbl">

                                                <?php
                                                foreach ($courier_track_items as $track_item) {

                                                    $date_update = date("Y-m-d", strtotime($track_item->t_date));
                                                    $time_update = date("h:i A", strtotime($track_item->t_date));

                                                    $db->cdp_query("SELECT * FROM cdb_styles where id= '" . $track_item->status_courier . "'");
                                                    $status_courier_item = $db->cdp_registro();
                                                ?>
                                                    <tr class="card-hover">
                                                        <td><?php echo $date_update; ?></td>
                                                        <td><?php echo $time_update; ?></td>
                                                        <td><?php echo $track_item->t_dest; ?> /<br>
                                                            <?php echo $track_item->t_city; ?></td>
                                                        <td>
                                                            <span class="label" style="background-color: <?php echo $status_courier_item->color; ?>"><?php echo $status_courier_item->mod_style; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $track_item->comments; ?></td>
                                                    </tr>
                                                <?php
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                } ?>
                <?php
                } ?>


                <!-- USERS HISTORY PERMISSION  -->
                <?php if ($user->cdp_hasPermission('view_users_history_courier')) { ?>
                    <?php

                        $db->cdp_query("SELECT * FROM cdb_order_user_history where order_track='" . $row_order->order_prefix . $row_order->order_no . "' ORDER BY history_id");

                        $order_user_history = $db->cdp_registros();
                        $numrows = $db->cdp_rowCount();

                        if ($numrows > 0) {
                    ?>
                            <div class="row">
                                <div class="col-lg-12 col-xl-12 col-md-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-md-flex align-items-center">
                                                <div>
                                                    <h3 class="card-title"><span><?php echo $lang['left533020001'] ?></span></h3>
                                                </div>
                                            </div>
                                            <div><hr></div>

                                            <div class="table-responsive">
                                                <table id="zero_config" class="table table-striped">
                                                    <thead class="bg-inverse text-white">
                                                        <tr>
                                                            <th><?php echo $lang['left503'] ?></th>
                                                            <th><?php echo $lang['left504'] ?></th>
                                                            <th><?php echo $lang['left533020002'] ?></th>
                                                            <th><?php echo $lang['left533020003'] ?></th>
                                                            <th><?php echo $lang['left533020004'] ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="projects-tbl">

                                                        <?php
                                                        foreach ($order_user_history as $track_item) {

                                                            $date_update = date("Y-m-d", strtotime($track_item->date_history));
                                                            $time_update = date("h:i A", strtotime($track_item->date_history));


                                                            $db->cdp_query("SELECT * FROM cdb_users where id= '" . $track_item->user_id . "'");
                                                            $sender_data2 = $db->cdp_registro();


                                                            $role = '';

                                                            switch ($sender_data2->userlevel) {
                                                                case '1':
                                                                    $role =  $lang['left533020005'];
                                                                    break;

                                                                case '2':

                                                                    $role =  $lang['left533020006'];

                                                                    break;

                                                                case '3':

                                                                    $role = $lang['left533020007'];

                                                                    break;

                                                                case '9':

                                                                    $role =  $lang['left533020008'];

                                                                    break;

                                                                default:
                                                                    # code...
                                                                    break;
                                                            }

                                                        ?>
                                                            <tr class="card-hover">
                                                                <td><?php echo $date_update; ?></td>
                                                                <td><?php echo $time_update; ?></td>
                                                                <td><?php echo $sender_data2->fname . ' ' . $sender_data2->lname; ?></td>
                                                                <td><?php echo $role; ?></td>
                                                                <td><?php echo $track_item->action; ?></td>

                                                            </tr>
                                                        <?php
                                                        } ?>

                                                    </tbody>
                                                </table>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php } ?>
                <?php } ?>


                <!-- Row -->
                <!-- DETAILS TAX PERMISSION  -->
                <?php if ($user->cdp_hasPermission('view_details_courier') || (int)$userData->userlevel === 1) {
                    // Customers (userlevel 1) see the item list too, but per-item WEIGHT,
                    // CUSTOM PRICE and LINE TOTAL are redacted SERVER-SIDE — the real
                    // values are never written to the page, so the blur cannot be removed
                    // (via inspector / display:none) to reveal them. Line total is
                    // included because for custom items it equals custom_price x qty.
                    $cv_is_customer = ((int)$userData->userlevel === 1);
                    // Prices stay hidden from a customer UNTIL they have been billed
                    // (a Financial Sheet bill exists for this package's consolidation).
                    // Once billed, the real values are written so the customer can see
                    // what they owe.
                    $cv_billed = false;
                    if ($cv_is_customer) {
                        $cvdb = new Conexion;
                        $cvdb->cdp_query("SELECT 1 FROM cdb_consolidate_detail cd
                                          JOIN cdb_consolidate_customer_billing b
                                            ON b.consolidate_id = cd.consolidate_id AND b.sender_id = :sid
                                          WHERE cd.order_no = :ono LIMIT 1");
                        $cvdb->bind(':sid', (int) $row_order->sender_id);
                        $cvdb->bind(':ono', $row_order->order_no);
                        $cvdb->cdp_execute();
                        $cv_billed = (bool) $cvdb->cdp_registro();
                    }
                    $cv_hide = ($cv_is_customer && !$cv_billed);
                    $cv_redact = '<span class="cv-redacted" title="Shown after you are billed">&bull;&bull;&bull;&bull;</span>';
                ?>
                <style>
                    .cv-redacted{display:inline-block;min-width:46px;text-align:center;filter:blur(5px);-webkit-filter:blur(3px);user-select:none;-webkit-user-select:none;pointer-events:none;color:#555;letter-spacing:2px;}
                </style>
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['left533020009'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr></div>

                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabla">
                                        <thead class="bg-inverse text-white">
                                            <tr>
                                                <th><b><?php echo $lang['left214'] ?></b></th>      <!-- Cantidad -->
                                                <th><b><?php echo $lang['left213'] ?></b></th>      <!-- Descripción -->
                                                <th><b>Pricing mode</b></th>
                                                <th><b><?php echo $lang['left215'] ?></b></th>      <!-- Peso -->
                                                <th><b>Custom Price</b></th>
                                                <th><b>Line Total</b></th>
                                                <th><b><?php echo $lang['left231c9'] ?></b></th>    <!-- Cargo fijo -->
                                                <th><b><?php echo $lang['left239'] ?></b></th>      <!-- Valor declarado -->
                                            </tr>
                                        </thead>
                                        <tbody id="projects-tbl">
                                            <?php
                                            if ($order_items) {

                                                // ====== INICIALIZAR SUMAS (alineado con JS/PHP de creación) ======
                                                $sumador_total           = 0.0;
                                                $sumador_valor_declarado = 0.0; // suma valores declarados
                                                $sumador_fixed_charge    = 0.0; // suma cargos fijos
                                                $max_fixed_charge        = 0.0; // mismo que arriba, para total
                                                $sumador_libras          = 0.0; // peso real total
                                                $sumador_volumetric      = 0.0; // volumétrico retirado (siempre 0)
                                                $base_packages           = 0.0; // base de paquetes en USD (peso*tarifa + precio personalizado)
                                                $total_impuesto          = 0.0;
                                                $total_seguro            = 0.0;
                                                $total_peso              = 0.0;
                                                $total_descuento         = 0.0;
                                                $total_impuesto_aduanero = 0.0;
                                                $total_valor_declarado   = 0.0;

                                                // Parámetros de la orden
                                                $meter                = (float) $row_order->volumetric_percentage;
                                                $price_lb             = (float) $row_order->value_weight;
                                                $tax_value            = (float) $row_order->tax_value;                  // %
                                                $tax_discount         = (float) $row_order->tax_discount;              // %
                                                $insurance_value      = (float) $row_order->tax_insurance_value;       // %
                                                $tariffs_value        = (float) $row_order->tax_custom_tariffis_value; // %
                                                $declared_value_tax   = (float) $row_order->declared_value;            // %
                                                $insured_value        = (float) $row_order->total_insured_value;       // base asegurada
                                                $reexpedicion_value   = (float) $row_order->total_reexp;

                                                if ($meter <= 0) {
                                                    // Por seguridad, evitar división por cero
                                                    $meter = 1;
                                                }

                                                foreach ($order_items as $row_order_item) {

                                                    $qty            = (float) $row_order_item->order_item_quantity;
                                                    if ($qty <= 0) {
                                                        $qty = 1;
                                                    }

                                                    $description_item  = $row_order_item->order_item_description;
                                                    $weight_item       = (float) $row_order_item->order_item_weight;
                                                    $custom_price_item = isset($row_order_item->custom_price) ? (float) $row_order_item->custom_price : 0.0;
                                                    $use_custom_item   = $custom_price_item > 0 ? 1 : 0;

                                                    // Per-item line total in USD (mirrors computeLineTotal in the JS):
                                                    //   weight item -> weight * qty * rate ; custom item -> custom_price * qty
                                                    if ($use_custom_item) {
                                                        $line_total_item = $custom_price_item * $qty;
                                                    } else {
                                                        $line_total_item = $weight_item * $qty * $price_lb;
                                                    }

                                                    // Acumulados
                                                    $sumador_libras          += $weight_item * $qty;
                                                    $base_packages           += $line_total_item;
                                                    $sumador_valor_declarado += (float)$row_order_item->order_item_declared_value * $qty;
                                                    $sumador_fixed_charge    += (float)$row_order_item->order_item_fixed_value * $qty;
                                                    $max_fixed_charge        += (float)$row_order_item->order_item_fixed_value * $qty;
                                            ?>

                                                    <tr class="card-hover">
                                                        <td><?php echo (int) $row_order_item->order_item_quantity; ?></td>
                                                        <td><?php echo $description_item; ?></td>
                                                        <td>
                                                            <?php if ($use_custom_item) { ?>
                                                                <span class="badge badge-success">Custom</span>
                                                            <?php } else { ?>
                                                                <span class="badge badge-dark">Weight</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo $cv_hide ? $cv_redact : ($use_custom_item ? '—' : $weight_item); ?></td>
                                                        <td class="text-center"><?php echo $cv_hide ? $cv_redact : ($use_custom_item ? number_format($custom_price_item, 2) : '—'); ?></td>
                                                        <td class="text-center"><?php echo $cv_hide ? $cv_redact : number_format($line_total_item, 2); ?></td>
                                                        <td class="text-center"><?php echo $row_order_item->order_item_fixed_value; ?></td>
                                                        <td class="text-center"><?php echo $row_order_item->order_item_declared_value; ?></td>
                                                    </tr>
                                                <?php
                                                }

                                                // ====== POST-PROCESO DE SUMAS (igual que en calculateFinalTotal) ======

                                                $sumador_libras     = round($sumador_libras, 2);
                                                $sumador_volumetric = 0.0; // volumétrico retirado

                                                // Peso cobrable = peso real (volumétrico retirado)
                                                $calculate_weight = $sumador_libras;

                                                // Flete base (USD): suma de líneas por ítem
                                                // (peso*tarifa para ítems por peso + precio personalizado*qty)
                                                $sumador_total = round($base_packages, 2);

                                                // Impuesto (IVA o similar)
                                                if ($sumador_total > $core->min_cost_tax) {
                                                    $total_impuesto = $sumador_total * ($tax_value / 100);
                                                }

                                                // Impuesto por valor declarado
                                                if ($sumador_valor_declarado > $core->min_cost_declared_tax) {
                                                    $total_valor_declarado = $sumador_valor_declarado * ($declared_value_tax / 100);
                                                }

                                                // Descuento (porcentaje del base o monto fijo en USD)
                                                $discount_type = (isset($row_order->discount_type) && $row_order->discount_type === 'amount') ? 'amount' : 'percent';
                                                $total_descuento = ($discount_type === 'amount')
                                                    ? $tax_discount
                                                    : $sumador_total * ($tax_discount / 100);
                                                if ($tax_discount < 0 || $total_descuento > $sumador_total) {
                                                    $total_descuento = 0;
                                                }

                                                // Peso total para arancel (real + volumétrico)
                                                $total_peso = $sumador_libras + $sumador_volumetric;

                                                // Seguro (costo)
                                                $total_seguro = $insured_value * ($insurance_value / 100);

                                                // Impuesto aduanero (% sobre peso total)
                                                $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

                                                // Total envío
                                                $total_envio = ($sumador_total - $total_descuento)
                                                    + $total_seguro
                                                    + $total_impuesto
                                                    + $total_impuesto_aduanero
                                                    + $total_valor_declarado
                                                    + $max_fixed_charge
                                                    + $reexpedicion_value;

                                                if ($total_envio < 0) {
                                                    $total_envio = 0;
                                                }

                                                // Formateo para mostrar
                                                $sumador_total           = cdb_money_format($sumador_total);
                                                $total_envio             = cdb_money_format($total_envio);
                                                $total_seguro            = cdb_money_format($total_seguro);
                                                $total_impuesto_aduanero = cdb_money_format($total_impuesto_aduanero);
                                                $total_impuesto          = cdb_money_format($total_impuesto);
                                                $total_descuento         = cdb_money_format($total_descuento);
                                                $sumador_valor_declarado = cdb_money_format($sumador_valor_declarado);
                                                $sumador_fixed_charge    = cdb_money_format($sumador_fixed_charge);
                                                $total_valor_declarado   = cdb_money_format($total_valor_declarado);
                                                ?>
                                            <?php }  ?>
                                        </tbody>
                                        
                                        <?php if ($user->cdp_hasPermission('view_details_courier')) { ?>
                                        <tfoot>
                                            <!-- Tarifa por unidad de peso y subtotal -->
                                            <tr class="card-hover">
                                                <td colspan="4">
                                                    <b><?php echo $lang['left905'] ?> &nbsp; <?php echo $core->weight_p; ?>:</b>
                                                    <?php echo $row_order->value_weight; ?>
                                                </td>
                                                <td colspan="3" class="text-right">
                                                    <b><?php echo $lang['leftorder2021'] ?> (USD)</b>
                                                </td>
                                                <td class="text-center"><?php echo $sumador_total; ?></td>
                                            </tr>

                                            <!-- Peso real y descuento -->
                                            <tr class="card-hover">
                                                <td colspan="4">
                                                    <b><?php echo $lang['left232'] ?></b>
                                                    <span id="total_libras">: <?php echo $sumador_libras; ?></span>
                                                </td>
                                                <td colspan="3" class="text-right">
                                                    <b><?php echo $lang['leftorder21'] ?> <?php echo $row_order->tax_discount; ?> <?php echo (($discount_type ?? 'percent') === 'amount') ? '(' . $core->currency . ')' : $lang['leftorder222221']; ?> </b>
                                                </td>
                                                <td class="text-center"><?php echo $total_descuento; ?></td>
                                            </tr>

                                            <!-- Peso total (items) y peso original del paquete -->
                                            <tr>
                                                <td colspan="7">
                                                    <b><?php echo $lang['left236'] ?> <span class="text-muted">(items)</span></b>
                                                    <span id="total_peso"> : <?php echo $total_peso; ?></span>
                                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                                    <b>Original total weight</b>
                                                    : <?php echo htmlspecialchars((string) ($row_order->total_weight ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                                <td></td>
                                            </tr>

                                        </tfoot>
                                        <?php } ?>
                                    </table>
                                </div>
                                </div>

                                <div><br></div>
                                <?php if ($user->cdp_hasPermission('view_details_courier')) { ?>
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['messageerrorform30'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr></div>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabla">
                                        <thead class="bg-inverse text-white">
                                            <tr>
                                                <th><b><?php echo $lang['leftorder22'] ?></b></th> <!-- Seguro -->
                                                <th><b><?php echo $lang['leftorder25'] ?> <?php echo $row_order->tax_custom_tariffis_value; ?> <?php echo $lang['leftorder222221'] ?></b></th>
                                                <th><b><?php echo $lang['leftorder23'] ?></b></th> <!-- Valor declarado -->
                                                <th><b><?php echo $lang['leftorder67'] ?> <?php echo $row_order->tax_value; ?> <?php echo $lang['leftorder222221'] ?></b></th>
                                                <th><b><?php echo $lang['leftorder19'] ?> <?php echo $row_order->declared_value; ?> <?php echo $lang['leftorder222221'] ?></b></th>
                                                <th><b><?php echo $lang['leftorder1878'] ?></b></th> <!-- Cargos fijos -->
                                                <th><b><?php echo $lang['langs_048'] ?></b></th>    <!-- Reexpedición -->
                                                <th><b><?php echo $lang['leftorder2020'] ?> &nbsp; <?php echo $core->currency; ?></b></th>
                                            </tr>
                                        </thead>
                                        <tbody id="projects-tbl">
                                            <tr class="card-hover">
                                                <!-- Seguro (costo calculado) -->
                                                <td class="text-center" id="insurance"><?php echo $total_seguro; ?></td>
                                                <!-- Impuesto aduanero -->
                                                <td class="text-center" id="total_impuesto_aduanero"><?php echo $total_impuesto_aduanero; ?></td>
                                                <!-- Suma de valores declarados -->
                                                <td class="text-center"><?php echo $sumador_valor_declarado; ?></td>
                                                <!-- Impuesto general -->
                                                <td class="text-center" id="impuesto"><?php echo $total_impuesto; ?></td>
                                                <!-- Impuesto por valor declarado -->
                                                <td class="text-center" id="declared_value_label"><?php echo $total_valor_declarado; ?></td>
                                                <!-- Cargos fijos -->
                                                <td class="text-center" id="fixed_value_label"><?php echo $sumador_fixed_charge; ?></td>
                                                <!-- Reexpedición -->
                                                <td class="text-center" id="reexp"><?php echo cdb_money_format($row_order->total_reexp); ?></td>
                                                <!-- Total envío -->
                                                <td class="text-center" id="total_envio"><b><?php echo $total_envio; ?></b></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php }?>
                        </div>
                    </div>
                </div>
                <?php } ?>


                <!-- Row -->
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['left533020010'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr></div>

                                <div class="row">

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien6'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $sender_data->fname . ' ' . $sender_data->lname . ' <b>(' . $sender_data->locker . ')</b>'; ?></p>

                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien5'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $sender_data->email; ?></p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien9'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $sender_data->phone; ?></p>
                                        </div>
                                    </div>
                                </div>


                                <div class="row">
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien10'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $address_order->sender_address; ?></p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien12'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $address_order->sender_country; ?></p>
                                        </div>
                                    </div>


                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien13'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $address_order->sender_city; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Row -->

                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['left533020011'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr></div>
                                <div class="row">

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien6'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $sender_data->fname . ' ' . $sender_data->lname : $receiver_data->fname . ' ' . $receiver_data->lname; ?></p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien5'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $sender_data->email : $receiver_data->email; ?></p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien9'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $sender_data->phone : $receiver_data->phone; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien10'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $address_order->sender_address : $address_order->recipient_address; ?></p>
                                        </div>
                                    </div>

                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien12'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $address_order->sender_country : $address_order->recipient_country; ?></p>

                                        </div>
                                    </div>


                                    <div class=" col-sm-12 col-md-4 mb-2">
                                        <div class="">
                                            <h5> &nbsp;<b><?php echo $lang['edit-clien13'] ?></b></h5>
                                            <p class="text-muted  m-l-5"><?php echo $recipient_type == 'user' ? $address_order->sender_city : $address_order->recipient_city; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include('views/modals/modal_send_email.php'); ?>
            <?php include('views/modals/modal_update_driver.php'); ?>
            <?php include('views/modals/modal_verify_payment_packages.php'); ?> 


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
    <?php include('views/modals/modal_cancel_pickup.php'); ?>

    <?php include('views/modals/modal_charges_list.php'); ?>
    <?php include('views/modals/modal_charges_add.php'); ?>
    <?php include('views/modals/modal_charges_edit.php'); ?>


    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="<?= cdp_asset('dataJs/courier_view.js') ?>"></script>


</body>

</html>