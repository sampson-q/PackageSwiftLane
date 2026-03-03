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

ini_set('display_errors', 0);


require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service.php");

$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['sender_id']))

    $errors['sender_id'] = $lang['validate_field_ajax150'];

if (empty($_POST['sender_address_id']))

    $errors['sender_address_id'] = $lang['validate_field_ajax145'];

if (empty($_POST['recipient_id']))

    $errors['recipient_id'] = $lang['validate_field_ajax146'];

if (empty($_POST['recipient_address_id']))

    $errors['recipient_address_id'] = $lang['validate_field_ajax147'];

if (empty($_POST['agency']))

    $errors['agency'] = $lang['validate_field_ajax148'];

if (empty($_POST['origin_off']))

    $errors['origin_off'] = $lang['validate_field_ajax149'];

if (empty($_POST['order_no']))

    $errors['order_no'] = $lang['validate_field_ajax150'];

if (empty($_POST['order_item_category']))

    $errors['order_item_category'] = $lang['validate_field_ajax151'];

if (empty($_POST['order_package']))

    $errors['order_package'] = $lang['validate_field_ajax152'];

if (empty($_POST['order_courier']))

    $errors['order_courier'] = $lang['validate_field_ajax153'];

if (empty($_POST['order_service_options']))
    $errors['order_service_options'] = $lang['validate_field_ajax154'];

if (empty($_POST['order_deli_time']))
    $errors['order_deli_time'] = $lang['validate_field_ajax155'];


if (empty($_POST['status_courier']))
    $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_payment_method']))
    $errors['order_payment_method'] = $lang['validate_field_ajax158'];

if (empty($errors)) {

    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $sale_date   = date("Y-m-d H:i:s");
    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days = $payment_methods->days;
    $days = intval($days);
    $due_date = cdp_sumardias($sale_date, $days);

    if ($days == 0) {
        $status_invoice = 1;
    } else {
        $status_invoice = 2;
    }

    $tariff_mode = isset($_POST['tariff_mode']) ? 1 : 0;

    $dataShipment = array(
        'order_id' =>  cdp_sanitize(intval($_POST["order_id"])),
        'driver_id' =>  cdp_sanitize(intval($_POST["driver_id"])),
        'sender_id' =>  cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id' =>  cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id' =>  cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' =>  cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency' =>  cdp_sanitize(intval($_POST["agency"])),
        'origin_off' =>  cdp_sanitize(intval($_POST["origin_off"])),
        'order_package' =>  cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category' =>  cdp_sanitize(intval($_POST["order_item_category"])),
        'order_courier' =>  cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' =>  cdp_sanitize(intval($_POST["order_service_options"])),
        'order_deli_time' =>  cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
        'due_date' =>  $due_date,
        'status_invoice' =>  $status_invoice,
        'order_incomplete' => '1',
        'manual_tariff' =>  $tariff_mode,
    );


    $updateShip = cdp_updateCourierShipmentFromCustomer($dataShipment);
    $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
    $receiver_data = cdp_getRecipientCourier(intval($_POST["recipient_id"]));

    $shipment_id =  cdp_sanitize(intval($_POST["order_id"]));

    if ($updateShip) {

        if (isset($_POST["packages"])) {

            cdp_deleteCourierPackages($shipment_id);
            $packages = json_decode($_POST['packages']);
            $meter = cdp_sanitize($_POST["meter"]);

            $sumador_total = 0;
            $sumador_valor_declarado = 0;
            $max_fixed_charge = 0;
            $sumador_libras = 0;
            $sumador_volumetric = 0;
            $precio_total = 0;
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
                    'order_id' =>  $shipment_id,
                    'qty' =>  $package->qty,
                    'description' =>  $package->description,
                    'length' =>  $package->length,
                    'width' =>  $package->width,
                    'height' =>  $package->height,
                    'weight' =>  $package->weight,
                    'declared_value' =>  $package->declared_value,
                    'fixed_value' =>  $package->fixed_value,
                );

                cdp_insertCourierShipmentPackages($dataAddresses);

                // calculate weight columetric box size
                $total_metric = $package->length * $package->width * $package->height / $meter;
                $weight = $package->weight;

                $sumador_volumetric += $total_metric;
                $sumador_libras += $weight;
                // calculate weight x price
                if ($sumador_libras > $sumador_volumetric) {
                    $calculate_weight = $sumador_libras;
                } else {
                    $calculate_weight = $sumador_volumetric;
                }

                $sumador_libras = floatval($sumador_libras);
                $sumador_volumetric = floatval($sumador_volumetric);
                $calculate_weight = floatval($calculate_weight);

                $sumador_libras = round($sumador_libras, 2);
                $sumador_volumetric = round($sumador_volumetric, 2);
                $calculate_weight = round($calculate_weight, 2);

                $sumador_valor_declarado += $package->declared_value;
                $max_fixed_charge += $package->fixed_value;

                if ($tariff_mode) {
                    $sumador_total = $calculate_weight * $price_lb; // Calculate total based on weight and price per lb
                } else {
                    $sumador_total = $price_lb;
                }

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
            $total_envio = ($sumador_total - $total_descuento) + $total_seguro + $total_impuesto + $total_impuesto_aduanero + $total_valor_declarado + $max_fixed_charge + $reexpedicion_value;
        }

        $dataShipmentUpdateTotals = array(
            'order_id' =>  $shipment_id,
            'value_weight' =>  floatval($price_lb),
            'sub_total' =>  floatval($sumador_total),
            'tax_discount' =>  floatval($discount_value),
            'total_insured_value' => floatval($insured_value),
            'tax_insurance_value' => floatval($insurance_value),
            'tax_custom_tariffis_value' => floatval($tariffs_value),
            'tax_value' => floatval($tax_value),
            'declared_value' =>  floatval($declared_value_tax),
            'total_reexp' =>  floatval($reexpedicion_value),
            'total_declared_value' =>  floatval($total_valor_declarado),
            'total_fixed_value' =>  floatval($max_fixed_charge),
            'total_tax_discount' =>  floatval($total_descuento),
            'total_tax_insurance' =>  floatval($total_seguro),
            'total_tax_custom_tariffis' =>  floatval($total_impuesto_aduanero),
            'total_tax' =>  floatval($total_impuesto),
            'total_weight' =>  floatval($total_peso),
            'total_order' =>  floatval($total_envio),
        );

        $update = cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);
        $shipment = cdp_getCourier($shipment_id);
        $order_track =  $shipment->order_prefix . $shipment->order_no;

        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir = "../../order_files/";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    $image_name = $order_track .  date("Y-m-d") . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                    $target_file = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                    $imageFileZise = $_FILES["filesMultiple"]["size"][$key];

                    if ($imageFileZise > 0) {
                        move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                        $imagen = basename($_FILES["filesMultiple"]["name"][$key]);
                    }

                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertOrdersFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), '0', $imageFileType);
                }
            }
        }



        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            'action' => $lang['notification_shipment7'],
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );
        cdp_deleteCourierAddress($shipment_id);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
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


        $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        $recipient_address = $recipient_address_data->address;
        $recipient_country = $recipient_address_data->country;
        $recipient_city = $recipient_address_data->city;
        $recipient_state = $recipient_address_data->state;
        $recipient_zip_code = $recipient_address_data->zip_code;

        $_recipient_country = cdp_getCountry($recipient_country);
        $final_recipient_country = $_recipient_country['data'];

        $_recipient_state = cdp_getState($recipient_state);
        $final_recipient_state = $_recipient_state['data'];

        $recipient_city = cdp_getCity($recipient_city);
        $final_recipient_city = $recipient_city['data'];


        $shipment = cdp_getCourier($shipment_id);

        // SAVE ADDRESS FOR Shipments
        $dataAddresses = array(
            'order_id' =>   $shipment_id,
            'order_track' =>  $shipment->order_prefix . $shipment->order_no,
            'sender_country' =>   $final_sender_country->name,
            'sender_state' =>   $final_sender_state->name,
            'sender_city' =>   $final_sender_city->name,
            'sender_zip_code' =>   $sender_zip_code,
            'sender_address' =>   $sender_address,
            'recipient_country' =>   $final_recipient_country->name,
            'recipient_state' =>   $final_recipient_state->name,
            'recipient_city' =>   $final_recipient_city->name,
            'recipient_zip_code' =>   $recipient_zip_code,
            'recipient_address' =>   $recipient_address,
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);


        //NOTIFY WHATSAPP API

        if (isset($_POST['notify_whatsapp_sender']) && $_POST['notify_whatsapp_sender'] == 1) {
            sendNotificationWhatsAppWithPDF($sender_data, $shipment_id, 3);
        }

        if (isset($_POST['notify_whatsapp_receiver']) && $_POST['notify_whatsapp_receiver'] == 1) {
            sendNotificationWhatsAppWithPDF($receiver_data, $shipment_id, 3);
        }
        $messages[] = $lang['message_ajax_success_add_shipment'];
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
