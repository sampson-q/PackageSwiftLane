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
require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_pickup_list');

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

// order_item_category falls back to the admin default when not posted.

if (empty($_POST['order_package']))

    $errors['order_package'] = $lang['validate_field_ajax152'];

if (empty($_POST['order_courier']))

    $errors['order_courier'] = $lang['validate_field_ajax153'];

// order_service_options falls back to the admin default when not posted.

if (empty($_POST['order_deli_time']))
    $errors['order_deli_time'] = $lang['validate_field_ajax155'];


if (empty($_POST['status_courier']))
    $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_payment_method']))
    $errors['order_payment_method'] = $lang['validate_field_ajax158'];

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

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

    $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
    $receiver_data = cdp_getRecipientCourier(intval($_POST["recipient_id"]));
    $tariff_mode = isset($_POST['tariff_mode']) ? 1 : 0;

    $dataShipment = array(
        'order_id' =>  cdp_sanitize(intval($_POST["order_id"])),
        'sender_id' =>  cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id' =>  cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id' =>  cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' =>  cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency' =>  cdp_sanitize(intval($_POST["agency"])),
        'origin_off' =>  cdp_sanitize(intval($_POST["origin_off"])),
        'order_package' =>  cdp_sanitize(intval($_POST["order_package"])),
        'driver_id' =>  cdp_sanitize(intval($_POST["driver_id"])),
        'order_item_category' =>  cdp_sanitize(intval($_POST["order_item_category"])),
        'order_courier' =>  cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) (cdp_getInfoShipDefault()->service_default4 ?? 0),
        'order_deli_time' =>  cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
        'due_date' =>  $due_date,
        'status_invoice' =>  $status_invoice,
        'order_incomplete' => '1',
        'manual_tariff' =>  $tariff_mode,

    );

    $updateShip = cdp_updateCourierShipmentFromCustomer($dataShipment);

    $shipment_id =  cdp_sanitize(intval($_POST["order_id"]));

    cdp_insertPackageTracking($shipment_id, $_SESSION['userid'], cdp_sanitize($_POST['tracking_number']), cdp_sanitize($_POST['estimated_eta']));

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


        $fullshipment = $shipment->order_prefix . $shipment->order_no;
        // Obtener el ID del estado del envio desde el POST SMS
        $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status = $name_status->mod_style;
        $app_url = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;


        if (!empty($_POST['notify_whatsapp_sender']) && intval($_POST['notify_whatsapp_sender']) === 1) {
            try {
                require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

                $db_sender = new Conexion;
                $db_sender->cdp_query("SELECT * FROM cdb_users WHERE id = :id");
                $db_sender->bind(':id', intval($_POST['sender_id']));
                $db_sender->cdp_execute();
                $sender_data = $db_sender->cdp_registro();

                if ($sender_data && !empty($sender_data->phone)) {
                    // Shared template-4 path: defaults the service, adds
                    // pieces/weight/dims/carrier-tracking/ETA, valid track URL.
                    $wa_result = cdp_sendShipmentRegisteredWhatsApp($sender_data, $fullshipment, array(
                        'courier'  => intval($dataShipment['order_courier'] ?? 0),
                        'service'  => intval($dataShipment['order_service_options'] ?? 0),
                        'delitime' => intval($dataShipment['order_deli_time'] ?? 0),
                        'office'   => intval($dataShipment['origin_off'] ?? 0),
                    ), cdp_wa_buildShipmentExtraLines());

                    if (empty($wa_result['success'])) {
                        error_log("WhatsApp notification failed for shipment {$fullshipment}: " . ($wa_result['message'] ?? ''));
                    }
                }
            } catch (Exception $e) {
                error_log('WhatsApp notification error for shipment ' . ($fullshipment ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        // NOTIFY SMS CLICKSEND API
        $templatessender = 11;
        $templatesreceiver = 12;

        // Obtener el estado de las casillas de verificación
        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        // Generar cuerpo del SMS para el remitente
        $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);

        // Llamar a la función para enviar la notificación SMS al remitente
        sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);

        // Generar cuerpo del SMS para el receptor
        $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);

        // Llamar a la función para enviar la notificación SMS al receptor
        sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);


        $accept_data = cdp_getacceptCourier(intval($_POST["driver_id"]));

        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'action' =>  $lang['notification_shipment088'] . ' ' . $accept_data->fname . ' ' . $accept_data->lname,
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
            'order_track' => $order_track
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );
        cdp_deleteCourierAddress($shipment_id);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $shipment = cdp_getCourier($shipment_id);
        $apck_recipient_type = cdp_sanitize($_POST['recipient_type'] ?? ($shipment->recipient_type ?? 'recipient'));
        $apck_recip_addr = ($apck_recipient_type === 'user')
            ? cdp_getSenderAddress(intval($_POST["recipient_address_id"]))
            : cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        // SAVE ADDRESS FOR Shipments
        $dataAddresses = array(
            'order_id'           => $shipment_id,
            'order_track'        => $shipment->order_prefix . $shipment->order_no,
            'sender_country'     => $sender_address_data ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '') : '',
            'sender_state'       => $sender_address_data ? cdp_resolveAddressName($sender_address_data->state,   'cdp_getState',   $sender_address_data->legacy_state   ?? '') : '',
            'sender_city'        => $sender_address_data ? cdp_resolveAddressName($sender_address_data->city,    'cdp_getCity',    $sender_address_data->legacy_city    ?? '') : '',
            'sender_zip_code'    => $sender_address_data ? ($sender_address_data->zip_code ?? '') : '',
            'sender_address'     => $sender_address_data ? ($sender_address_data->address  ?? '') : '',
            'recipient_country'  => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->country ?? '', 'cdp_getCountry', $apck_recip_addr->legacy_country ?? '') : '',
            'recipient_state'    => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->state   ?? '', 'cdp_getState',   $apck_recip_addr->legacy_state   ?? '') : '',
            'recipient_city'     => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->city    ?? '', 'cdp_getCity',    $apck_recip_addr->legacy_city    ?? '') : '',
            'recipient_zip_code' => $apck_recip_addr ? ($apck_recip_addr->zip_code ?? '') : '',
            'recipient_address'  => $apck_recip_addr ? ($apck_recip_addr->address  ?? '') : '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);



        $messages[] = $lang['message_ajax_success_add_pickup'];
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
