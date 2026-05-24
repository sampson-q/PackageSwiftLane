<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_sms/api_sms_service.php");

$user = new User;
$core = new Core;
$errors = array();
$messages = array();

// =====================
// VALIDATION (match add where applicable)
// =====================
if (empty($_POST['tracking_purchase'])) $errors['tracking_purchase'] = $lang['validate_field_ajax170'];
if (empty($_POST['provider_purchase'])) $errors['provider_purchase'] = $lang['validate_field_ajax172'];
if (empty($_POST['price_purchase'])) $errors['price_purchase'] = $lang['validate_field_ajax174'];

if (empty($_POST['sender_id'])) $errors['sender_id'] = $lang['validate_field_ajax150'];
if (empty($_POST['sender_address_id'])) $errors['sender_address_id'] = $lang['validate_field_ajax145'];

if (empty($_POST['agency'])) $errors['agency'] = $lang['validate_field_ajax148'];
if (empty($_POST['origin_off'])) $errors['origin_off'] = $lang['validate_field_ajax149'];

if (empty($_POST['order_item_category'])) $errors['order_item_category'] = $lang['validate_field_ajax151'];
if (empty($_POST['order_package'])) $errors['order_package'] = $lang['validate_field_ajax152'];
if (empty($_POST['order_courier'])) $errors['order_courier'] = $lang['validate_field_ajax153'];
if (empty($_POST['order_service_options'])) $errors['order_service_options'] = $lang['validate_field_ajax154'];
if (empty($_POST['order_deli_time'])) $errors['order_deli_time'] = $lang['validate_field_ajax155'];

if (empty($_POST['status_courier'])) $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_id'])) $errors['order_id'] = $lang['message_ajax_error2'];

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();
    $meter = $settings->meter;

    // NOTIFY SMS CLICKSEND API
    $templatessender = 8;

    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    // =====================
    // UPDATE SHIPMENT (now mirrors add for recipient/payment/whatsapp)
    // =====================
    $dataShipment = array(
        'agency' =>  cdp_sanitize((int)$_POST["agency"]),
        'origin_off' =>  cdp_sanitize((int)$_POST["origin_off"]),
        'sender_id' =>  cdp_sanitize((int)$_POST["sender_id"]),
        'sender_address_id' =>  cdp_sanitize((int)$_POST["sender_address_id"]),
        'tracking_purchase' =>  cdp_sanitize($_POST["tracking_purchase"]),
        'provider_purchase' =>  cdp_sanitize($_POST["provider_purchase"]),
        'price_purchase' =>  cdp_sanitize((float)$_POST["price_purchase"]),
        'order_package' =>  cdp_sanitize((int)$_POST["order_package"]),
        'order_item_category' =>  cdp_sanitize((int)$_POST["order_item_category"]),
        'order_courier' =>  cdp_sanitize((int)$_POST["order_courier"]),
        'order_service_options' =>  cdp_sanitize((int)$_POST["order_service_options"]),
        'order_deli_time' =>  cdp_sanitize((int)$_POST["order_deli_time"]),
        'status_courier' =>  cdp_sanitize((int)$_POST["status_courier"]),
        'order_id' =>  cdp_sanitize((int)$_POST["order_id"]),

        // === add parity fields ===
        'recipient_id' => (int)(cdp_sanitize($_POST['recipient_id'] ?? 0)),
        'recipient_address_id' => (int)(cdp_sanitize($_POST['recipient_address_id'] ?? 0)),
        'order_payment_method' => (int)(cdp_sanitize($_POST['order_payment_method'] ?? 0)),
        'notify_whatsapp_sender' => (int)(cdp_sanitize($_POST['notify_whatsapp_sender'] ?? 0)),
        'driver_id' => (int)(cdp_sanitize($_POST['driver_id'] ?? 0)),
    );

    $updateShip = cdp_updateCustomerPackages($dataShipment);

    $shipment_id = (int)cdp_sanitize($_POST["order_id"]);

    if ($updateShip) {

        // =====================
        // PACKAGES + TOTALS (same pattern you already have)
        // =====================
        if (isset($_POST["packages"])) {
            cdp_deleteCustomersPackagesItems($shipment_id);

            $packages = json_decode($_POST['packages']);

            $sumador_total = 0;
            $sumador_valor_declarado = 0;
            $max_fixed_charge = 0;
            $sumador_libras = 0;
            $sumador_volumetric = 0;

            $total_impuesto = 0;
            $total_descuento = 0;
            $total_seguro = 0;
            $total_peso = 0;
            $total_impuesto_aduanero = 0;
            $total_valor_declarado = 0;

            $tariffs_value = $_POST["tariffs_value"];
            $declared_value_tax = $_POST["declared_value_tax"];
            $insurance_value = $_POST["insurance_value"];
            $tax_value = $_POST["tax_value"];
            $discount_value = $_POST["discount_value"];
            $reexpedicion_value = $_POST["reexpedicion_value"];
            $price_lb = $_POST["price_lb"];
            $insured_value = $_POST["insured_value"];

            foreach ($packages as $package) {

                $dataAddresses = array(
                    'order_id' => $shipment_id,
                    'qty' => $package->qty,
                    'description' => $package->description,
                    'length' => $package->length,
                    'width' => $package->width,
                    'height' => $package->height,
                    'weight' => $package->weight,
                    'declared_value' => $package->declared_value,
                    'fixed_value' => $package->fixed_value,
                );

                cdp_insertCustomerPackagesItems($dataAddresses);

                $total_metric = $package->length * $package->width * $package->height / $meter;
                $weight = $package->weight;

                $sumador_volumetric += $total_metric;
                $sumador_libras += $weight;

                if ($sumador_libras > $sumador_volumetric) {
                    $calculate_weight = $sumador_libras;
                } else {
                    $calculate_weight = $sumador_volumetric;
                }

                $sumador_total = $calculate_weight * $price_lb;
                $sumador_valor_declarado += $package->declared_value;
                $max_fixed_charge += $package->fixed_value;

                if ($sumador_total > $min_cost_tax) {
                    $total_impuesto = $sumador_total * $tax_value / 100;
                }

                if ($sumador_valor_declarado > $min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
                }
            }

            $total_descuento = $sumador_total * $discount_value / 100;
            $total_peso = $sumador_libras + $sumador_volumetric;
            $total_seguro = $insured_value * $insurance_value / 100;
            $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

            $total_envio = ($sumador_total - $total_descuento)
                + $total_seguro
                + $total_impuesto
                + $total_impuesto_aduanero
                + $total_valor_declarado
                + $max_fixed_charge
                + $reexpedicion_value;
        }

        $dataShipmentUpdateTotals = array(
            'order_id' => $shipment_id,
            'value_weight' => floatval($price_lb),
            'sub_total' => floatval($sumador_total),
            'tax_discount' => floatval($discount_value),
            'total_insured_value' => floatval($insured_value),
            'tax_insurance_value' => floatval($insurance_value),
            'tax_custom_tariffis_value' => floatval($tariffs_value),
            'tax_value' => floatval($tax_value),
            'declared_value' => floatval($declared_value_tax),
            'total_reexp' => floatval($reexpedicion_value),
            'total_declared_value' => floatval($total_valor_declarado),
            'total_fixed_value' => floatval($max_fixed_charge),
            'total_tax_discount' => floatval($total_descuento),
            'total_tax_insurance' => floatval($total_seguro),
            'total_tax_custom_tariffis' => floatval($total_impuesto_aduanero),
            'total_tax' => floatval($total_impuesto),
            'total_weight' => floatval($total_peso),
            'total_order' => floatval($total_envio),
        );

        cdp_updateCustomerPackagesTotals($dataShipmentUpdateTotals);

        $shipment = cdp_getCustomerPackage($shipment_id);
        $order_track = $shipment->order_prefix . $shipment->order_no;

        cdp_updatePackageTracking($shipment_id, NULL, cdp_sanitize($_POST['estimated_eta']));

        // =====================
        // DELETE EXISTING FILES (already in your temp)
        // =====================
        if (isset($_POST['deleted_db_file_ids']) && !empty($_POST['deleted_db_file_ids'])) {
            $ids_raw = trim($_POST['deleted_db_file_ids']);
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            foreach ($ids as $fid) {
                if ($fid > 0) cdp_deleteFileCustomerPackages(['id' => $fid]);
            }
        }

        // =====================
        // UPLOAD FILES (already in your temp)
        // =====================
        $target_dir = "../../order_files/";
        $deleted_file_ids = array();

        if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
            $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
        }

        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    $image_name = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                    $target_file = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

                    if ($_FILES["filesMultiple"]["size"][$key] > 0) {
                        move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                    }

                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertCustomerPackagesFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), $imageFileType);
                }
            }
        }

        if (isset($_FILES['filesCapture']) && count($_FILES['filesCapture']['name']) > 0 && $_FILES['filesCapture']['tmp_name'][0] != '') {

            foreach ($_FILES["filesCapture"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    $image_name = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesCapture"]["name"][$key]);
                    $target_file = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

                    if ($_FILES["filesCapture"]["size"][$key] > 0) {
                        move_uploaded_file($_FILES["filesCapture"]["tmp_name"][$key], $target_file);
                    }

                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertCustomerPackagesFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), $imageFileType);
                }
            }
        }

        // =====================
        // TRACK + HISTORY + NOTIFICATION (unchanged)
        // =====================
        $dataTrack = array(
            'user_id' => $_SESSION['userid'],
            'order_id' => $shipment_id,
            'order_track' => $order_track,
            't_date' => date("Y-m-d H:i:s"),
            'status_courier' => cdp_sanitize((int)$_POST["status_courier"]),
            'comments' => $lang['messagesform109'],
            'office' => cdp_sanitize((int)$_POST["origin_off"])
        );

        cdp_insertCourierShipmentTrack($dataTrack);

        $dataHistory = array(
            'user_id' => $_SESSION['userid'],
            'order_id' => $shipment_id,
            'order_track' => $order_track,
            'action' => $lang['messagesform109'],
            'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertCourierShipmentUserHistory($dataHistory);

        $dataNotification = array(
            'user_id' => $_SESSION['userid'],
            'order_id' => $shipment_id,
            'notification_description' => $lang['notification_shipment23'],
            'shipping_type' => '4',
            'notification_date' => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertNotification($dataNotification);

        // =====================
        // ADDRESS SNAPSHOT (unchanged)
        // =====================
        cdp_deleteCourierAddress($order_track);

        $sender_address_data = cdp_getSenderAddress((int)$_POST["sender_address_id"]);
        $sender_country = $sender_address_data->country;
        $sender_state = $sender_address_data->state;
        $sender_city = $sender_address_data->city;
        $sender_zip_code = $sender_address_data->zip_code;
        $sender_address = $sender_address_data->address;

        $_sender_country = cdp_getCountry($sender_country);
        $final_sender_country = $_sender_country['data'];

        $_sender_state = cdp_getState($sender_state);
        $final_sender_state = $_sender_state['data'];

        $sender_city = cdp_getCity($sender_city);
        $final_sender_city = $sender_city['data'];

        $dataAddresses = array(
            'order_id' => $shipment_id,
            'order_track' => $order_track,
            'sender_country' => $final_sender_country->name,
            'sender_state' => $final_sender_state->name,
            'sender_city' => $final_sender_city->name,
            'sender_zip_code' => $sender_zip_code,
            'sender_address' => $sender_address,
            'recipient_country' => '',
            'recipient_state' => '',
            'recipient_city' => '',
            'recipient_zip_code' => '',
            'recipient_address' => '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);

        // =====================
        // SMS notify (unchanged)
        // =====================
        $sender_data = cdp_getSenderCourier((int)$_POST["sender_id"]);
        $fullshipment = $shipment->order_prefix . $shipment->order_no;

        $name_status = cdp_getCourierstatusApi((int)$_POST["status_courier"]);
        $add_status = $name_status->mod_style;
        $app_url = $settings->site_url . 'track_online_shopping.php?order_track=' . $fullshipment;

        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;

        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
        }

        $messages[] = $lang['message_ajax_success_add_update'];
    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'shipment_id' => $shipment_id,
    ]);
}