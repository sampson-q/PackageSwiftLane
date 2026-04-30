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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");

$user = new User;
$core = new Core;
$errors = array();

// =======================
// VALIDACIONES BÁSICAS
// =======================
if (empty($_POST['sender_id']))            $errors['sender_id']            = $lang['validate_field_ajax150'];
if (empty($_POST['sender_address_id']))    $errors['sender_address_id']    = $lang['validate_field_ajax145'];
if (empty($_POST['recipient_id']))         $errors['recipient_id']         = $lang['validate_field_ajax146'];
if (empty($_POST['recipient_address_id'])) $errors['recipient_address_id'] = $lang['validate_field_ajax147'];
if (empty($_POST['agency']))               $errors['agency']               = $lang['validate_field_ajax148'];
if (empty($_POST['origin_off']))           $errors['origin_off']           = $lang['validate_field_ajax149'];
if (empty($_POST['order_no']))             $errors['order_no']             = $lang['validate_field_ajax150'];
if (empty($_POST['order_item_category']))  $errors['order_item_category']  = $lang['validate_field_ajax151'];
if (empty($_POST['order_package']))        $errors['order_package']        = $lang['validate_field_ajax152'];
if (empty($_POST['order_courier']))        $errors['order_courier']        = $lang['validate_field_ajax153'];
if (empty($_POST['order_service_options']))$errors['order_service_options']= $lang['validate_field_ajax154'];
if (empty($_POST['order_deli_time']))      $errors['order_deli_time']      = $lang['validate_field_ajax155'];
if (empty($_POST['status_courier']))       $errors['status_courier']       = $lang['validate_field_ajax157'];
if (empty($_POST['order_payment_method'])) $errors['order_payment_method'] = $lang['validate_field_ajax158'];

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    // NOTIFY SMS CLICKSEND API
    $templatessender   = 4;
    $templatesreceiver = 3;

    // Umbrales de impuestos desde configuración global
    $min_cost_tax           = (float)$core->min_cost_tax;
    $min_cost_declared_tax  = (float)$core->min_cost_declared_tax;

    // =======================
    // FECHAS / ESTADO FACTURA
    // =======================
        // Fecha de venta
    $sale_date      = date("Y-m-d H:i:s");

    // Método de pago y días de crédito (protegido contra nulos)
    $days = 0;
    $payment_methods = null;

    if (!empty($_POST["order_payment_method"])) {
        $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);

        if ($payment_methods && isset($payment_methods->days)) {
            $days = (int) $payment_methods->days;
        }
    }

    // Fecha de vencimiento y estado de factura
    $due_date       = cdp_sumardias($sale_date, $days);
    $status_invoice = ($days === 0) ? 1 : 2; // 1 = contado, 2 = crédito


    // Modo de tarifa: 1 = manual, 0 = motor tarifas
    $tariff_mode = isset($_POST['tariff_mode']) ? 1 : 0;

    // =======================
    // UPDATE CABECERA ENVÍO
    // =======================
    $dataShipment = array(
        'order_id'              => cdp_sanitize(intval($_POST["order_id"])),
        'driver_id'             => cdp_sanitize(intval($_POST["driver_id"])),
        'sender_id'             => cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id'          => cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id'     => cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id'  => cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency'                => cdp_sanitize(intval($_POST["agency"])),
        'origin_off'            => cdp_sanitize(intval($_POST["origin_off"])),
        'order_package'         => cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category'   => cdp_sanitize(intval($_POST["order_item_category"])),
        'order_courier'         => cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => cdp_sanitize(intval($_POST["order_service_options"])),
        'order_deli_time'       => cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method'  => cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier'        => cdp_sanitize(intval($_POST["status_courier"])),
        'due_date'              => $due_date,
        'status_invoice'        => $status_invoice,
        'manual_tariff'         => $tariff_mode
    );

    $updateShip   = cdp_updateCourierShipment($dataShipment);
    $shipment_id  = cdp_sanitize(intval($_POST["order_id"]));
    $messages     = array();

    if ($updateShip) {

        // =====================================================
        // RE-CALCULO DE PAQUETES Y TOTALES (MODO EDIT)
        // =====================================================

        // Totales inicializados a 0 para evitar "undefined"
        $sum_total_flete        = 0.0;
        $sum_weight_real        = 0.0;
        $sum_weight_vol         = 0.0;
        $sum_declared           = 0.0;
        $sum_fixed              = 0.0;
        $total_impuesto         = 0.0;
        $total_descuento        = 0.0;
        $total_seguro           = 0.0;
        $total_peso             = 0.0;
        $total_impuesto_aduanero= 0.0;
        $total_valor_declarado  = 0.0;
        $total_envio            = 0.0;

        // Valores que vienen del formulario (iguales a courier_add.js)
        $price_lb            = isset($_POST["price_lb"]) ? floatval($_POST["price_lb"]) : 0;
        $insured_value       = isset($_POST["insured_value"]) ? floatval($_POST["insured_value"]) : 0;
        $insurance_value     = isset($_POST["insurance_value"]) ? floatval($_POST["insurance_value"]) : 0;
        $reexpedicion_value  = isset($_POST["reexpedicion_value"]) ? floatval($_POST["reexpedicion_value"]) : 0;
        $discount_value      = isset($_POST["discount_value"]) ? floatval($_POST["discount_value"]) : 0;
        $tax_value           = isset($_POST["tax_value"]) ? floatval($_POST["tax_value"]) : 0;
        $declared_value_tax  = isset($_POST["declared_value_tax"]) ? floatval($_POST["declared_value_tax"]) : 0;
        $tariffs_value       = isset($_POST["tariffs_value"]) ? floatval($_POST["tariffs_value"]) : 0;
        $core_meter          = isset($_POST["meter"]) ? floatval($_POST["meter"]) : 0;

        if (isset($_POST["packages"])) {

            // Borramos detalles anteriores y re-insertamos
            cdp_deleteCourierPackages($shipment_id);

            $packages = json_decode($_POST['packages']);

            if ($packages && is_array($packages)) {

                foreach ($packages as $package) {

                    $qty      = isset($package->qty) ? floatval($package->qty) : 0;
                    if ($qty <= 0) $qty = 1;

                    $length   = isset($package->length) ? floatval($package->length) : 0;
                    $width    = isset($package->width) ? floatval($package->width) : 0;
                    $height   = isset($package->height) ? floatval($package->height) : 0;
                    $weight   = isset($package->weight) ? floatval($package->weight) : 0;
                    $declared = isset($package->declared_value) ? floatval($package->declared_value) : 0;
                    $fixed    = isset($package->fixed_value) ? floatval($package->fixed_value) : 0;
                    $descr    = isset($package->description) ? trim($package->description) : '';

                    // Guardar detalle del paquete
                    $dataPackage = array(
                        'order_id'       => $shipment_id,
                        'qty'            => $qty,
                        'description'    => $descr,
                        'length'         => $length,
                        'width'          => $width,
                        'height'         => $height,
                        'weight'         => $weight,
                        'declared_value' => $declared,
                        'fixed_value'    => $fixed
                    );
                    cdp_insertCourierShipmentPackages($dataPackage);

                    // Peso volumétrico unitario
                    $vol_unit = 0.0;
                    if ($core_meter > 0) {
                        $vol_unit = ($length * $width * $height) / $core_meter;
                    }

                    // Acumulados
                    $sum_weight_real  += $weight   * $qty;
                    $sum_weight_vol   += $vol_unit * $qty;
                    $sum_declared     += $declared * $qty;
                    $sum_fixed        += $fixed    * $qty;
                }
            }

            // Redondeos
            $sum_weight_real = round($sum_weight_real, 2);
            $sum_weight_vol  = round($sum_weight_vol, 2);
            $sum_declared    = round($sum_declared, 2);
            $sum_fixed       = round($sum_fixed, 2);

            // Peso a cobrar y peso total
            $calculate_weight = max($sum_weight_real, $sum_weight_vol);
            $total_peso       = $sum_weight_real + $sum_weight_vol;

            // Flete base: backend fuente de verdad cuando manual_tariff = 0
            $meter_edit = isset($settings->meter) ? (float)$settings->meter : (float)$core_meter;
            if ($tariff_mode == 0 && $meter_edit > 0) {
                $distance_miles_edit = (float)($_POST['distance_miles'] ?? 0);
                $order_svc_edit      = (int)($_POST['order_service_options'] ?? 0);
                $tariffEdit = cdp_calculateTariffServerSide(
                    intval($_POST['sender_id']),
                    intval($_POST['sender_address_id']),
                    intval($_POST['recipient_id']),
                    intval($_POST['recipient_address_id']),
                    $order_svc_edit,
                    $packages,
                    $distance_miles_edit,
                    $meter_edit
                );
                if ($tariffEdit !== null) {
                    $sum_total_flete = $tariffEdit['total_tarifa'];
                    $price_lb        = $tariffEdit['price_lb_derived'];
                } else {
                    $sum_total_flete = $calculate_weight * $price_lb;
                }
            } else {
                $sum_total_flete = $calculate_weight * $price_lb;
            }

            // Impuesto normal (con umbral)
            if ($sum_total_flete > $min_cost_tax) {
                $total_impuesto = $sum_total_flete * $tax_value / 100;
            }

            // Impuesto sobre valor declarado (con umbral)
            if ($sum_declared > $min_cost_declared_tax) {
                $total_valor_declarado = $sum_declared * $declared_value_tax / 100;
            }

            // Descuento
            $total_descuento = $sum_total_flete * $discount_value / 100;
            if ($total_descuento > $sum_total_flete || $discount_value < 0) {
                $total_descuento = 0;
            }

            // Seguro y aranceles
            $total_seguro          = $insured_value * $insurance_value / 100;
            $total_impuesto_aduanero = ($total_peso * $tariffs_value / 100);

            // Total final
            $total_envio = $sum_total_flete
                           - $total_descuento
                           + $total_seguro
                           + $total_impuesto
                           + $total_impuesto_aduanero
                           + $total_valor_declarado
                           + $sum_fixed
                           + $reexpedicion_value;

            $total_envio = round($total_envio, 2);
        }

        // =====================================================
        // Actualizar totales en la tabla principal
        // =====================================================
        $dataShipmentUpdateTotals = array(
            'order_id'                     => $shipment_id,
            'value_weight'                 => floatval($price_lb),
            'sub_total'                    => floatval($sum_total_flete),
            'tax_discount'                 => floatval($discount_value),
            'total_insured_value'          => floatval($insured_value),
            'tax_insurance_value'          => floatval($insurance_value),
            'tax_custom_tariffis_value'    => floatval($tariffs_value),
            'tax_value'                    => floatval($tax_value),
            'declared_value'               => floatval($declared_value_tax),
            'total_reexp'                  => floatval($reexpedicion_value),
            'total_declared_value'         => floatval($total_valor_declarado),
            'total_fixed_value'            => floatval($sum_fixed),
            'total_tax_discount'           => floatval($total_descuento),
            'total_tax_insurance'          => floatval($total_seguro),
            'total_tax_custom_tariffis'    => floatval($total_impuesto_aduanero),
            'total_tax'                    => floatval($total_impuesto),
            'total_weight'                 => floatval($total_peso),
            'total_order'                  => floatval($total_envio),
        );

        $update = cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);

        // =======================
        // TRACKING / HISTÓRICO
        // =======================
        $shipment     = cdp_getCourier($shipment_id);
        $order_track  = $shipment->order_prefix . $shipment->order_no;

        // ARCHIVOS ADJUNTOS
        if (isset($_FILES['filesMultiple']) &&
            count($_FILES['filesMultiple']['name']) > 0 &&
            $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir       = "../../order_files/";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {

                    $image_name    = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                    $target_file   = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                    $imageFileZise = $_FILES["filesMultiple"]["size"][$key];

                    if ($imageFileZise > 0) {
                        move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                    }

                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertOrdersFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), '0', $imageFileType);
                }
            }
        }

        // HISTORIAL DE USUARIO
        $dataHistory = array(
            'user_id'       => $_SESSION['userid'],
            'order_id'      => $shipment_id,
            'order_track'   => $order_track,
            'action'        => $lang['notification_shipment7'],
            'date_history'  => cdp_sanitize(date("Y-m-d H:i:s")),
        );
        cdp_insertCourierShipmentUserHistory($dataHistory);

        // DIRECCIONES
        $shipment    = cdp_getCourier($shipment_id);
        $order_track = $shipment->order_prefix . $shipment->order_no;
        cdp_deleteCourierAddress($order_track);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $sender_country      = $sender_address_data->country;
        $sender_state        = $sender_address_data->state;
        $sender_city         = $sender_address_data->city;
        $sender_zip_code     = $sender_address_data->zip_code;
        $sender_address      = $sender_address_data->address;

        $_sender_country     = cdp_getCountry($sender_country);
        $final_sender_country= $_sender_country['data'];

        $_sender_state       = cdp_getState($sender_state);
        $final_sender_state  = $_sender_state['data'];

        $sender_city         = cdp_getCity($sender_city);
        $final_sender_city   = $sender_city['data'];

        $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));
        $recipient_address      = $recipient_address_data->address;
        $recipient_country      = $recipient_address_data->country;
        $recipient_city         = $recipient_address_data->city;
        $recipient_state        = $recipient_address_data->state;
        $recipient_zip_code     = $recipient_address_data->zip_code;

        $_recipient_country    = cdp_getCountry($recipient_country);
        $final_recipient_country = $_recipient_country['data'];

        $_recipient_state      = cdp_getState($recipient_state);
        $final_recipient_state = $_recipient_state['data'];

        $recipient_city        = cdp_getCity($recipient_city);
        $final_recipient_city  = $recipient_city['data'];

        $dataAddresses = array(
            'order_id'          => $shipment_id,
            'order_track'       => $order_track,
            'sender_country'    => $final_sender_country->name,
            'sender_state'      => $final_sender_state->name,
            'sender_city'       => $final_sender_city->name,
            'sender_zip_code'   => $sender_zip_code,
            'sender_address'    => $sender_address,
            'recipient_country' => $final_recipient_country->name,
            'recipient_state'   => $final_recipient_state->name,
            'recipient_city'    => $final_recipient_city->name,
            'recipient_zip_code'=> $recipient_zip_code,
            'recipient_address' => $recipient_address,
        );
        cdp_insertCourierShipmentAddresses($dataAddresses);

        // SMS
        $sender_data   = cdp_getSenderCourier(intval($_POST["sender_id"]));
        $receiver_data = cdp_getRecipientCourier(intval($_POST["recipient_id"]));

        $fullshipment = $shipment->order_prefix . $shipment->order_no;
        $name_status  = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status   = $name_status->mod_style;
        $app_url      = $settings->site_url . 'track.php?order_track=' . $fullshipment;

        $notify_sms_sender   = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
        }

        try {
            $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);
            sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for receiver: ' . $e->getMessage());
        }

        // =======================
        // WhatsApp v2 Notification (Template 13)
        // =======================
        if (!empty($sender_data->phone)) {
            try {
                // Get template 13 for package status update
                $tpl = getTemplateWhatsApp(13);

                if ($tpl) {
                    // Get current and previous status
                    $current_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
                    $current_status_name = $current_status ? $current_status->mod_style : 'N/A';

                    // Get previous status from tracking history
                    $db_prev_status = new Conexion;
                    $db_prev_status->cdp_query("SELECT status_courier FROM cdb_courier_shipment_track WHERE order_id = :id ORDER BY id DESC LIMIT 2");
                    $db_prev_status->bind(':id', $shipment_id);
                    $db_prev_status->cdp_execute();
                    $prev_records = $db_prev_status->cdp_registros();

                    $prev_status_name = 'N/A';
                    if (count($prev_records) >= 2) {
                        $prev_status_obj = cdp_getCourierstatusApi($prev_records[1]->status_courier);
                        $prev_status_name = $prev_status_obj ? $prev_status_obj->mod_style : 'N/A';
                    }

                    // Get invoice status
                    $invoice_status = $shipment->status_invoice == 1 ? 'Paid' : 'Pending';

                    // Get order date
                    $order_date = date('M d, Y', strtotime($shipment->order_datetime));

                    // Get recipient name
                    $recipient_name = $receiver_data ? ($receiver_data->fname . ' ' . $receiver_data->lname) : 'N/A';

                    // Get origin and destination
                    $origin = $final_sender_city->name . ', ' . $final_sender_state->name;
                    $destination = $final_recipient_city->name . ', ' . $final_recipient_state->name;

                    // Format the message with all placeholders
                    $whatsapp_body = str_replace(
                        [
                            '[CUSTOMER_FULLNAME]',
                            '[TRACKING_NUMBER]',
                            '[PREV_STATUS]',
                            '[CURR_STATUS]',
                            '[INV_STATUS]',
                            '[ORD_DATE]',
                            '[RECIPIENT]',
                            '[ORIGIN]',
                            '[DESTINATION]',
                            '[APP_URL]',
                            '[COMPANY_NAME]'
                        ],
                        [
                            ucfirst("{$sender_data->fname} {$sender_data->lname}"),
                            $fullshipment,
                            $prev_status_name,
                            $current_status_name,
                            $invoice_status,
                            $order_date,
                            $recipient_name,
                            $origin,
                            $destination,
                            $app_url,
                            $settings->site_name
                        ],
                        $tpl->body
                    );

                    // Send via v2 API
                    sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);
                }
            } catch (Exception $e) {
                error_log('Error sending WhatsApp v2 notification to sender on edit: ' . $e->getMessage());
            }
        }

        $messages[] = $lang['message_ajax_success_add_update'];

    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
}

// =======================
// RESPUESTA JSON
// =======================
if (!empty($errors)) {
    echo json_encode(array(
        'success' => false,
        'errors'  => $errors
    ));
} else {
    echo json_encode(array(
        'success'     => true,
        'messages'    => $messages,
        'shipment_id' => $shipment_id,
    ));
}
