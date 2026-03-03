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
require_once("../notify_whatsapp/api_whatsapp_service.php");
require_once("../notify_sms/api_sms_service.php");

$user   = new User;
$core   = new Core;
$errors = array();
$ctx = cdp_getAgencyContext();

if (empty($_POST['sender_id']))
    $errors['sender_id'] = $lang['validate_field_ajax144'];

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

    $settings = cdp_getSettingsCourier();

    $site_email   = $settings->email_address;
    $check_mail   = $settings->mailer;
    $names_info   = $settings->smtp_names;
    $mlogo        = $settings->logo;
    $msite_url    = $settings->site_url;
    $msnames      = $settings->site_name;
    $smtphoste    = $settings->smtp_host;
    $smtpuser     = $settings->smtp_user;
    $smtppass     = $settings->smtp_password;
    $smtpport     = $settings->smtp_port;
    $smtpsecure   = $settings->smtp_secure;
    $value_weight = $settings->value_weight;
    $meter        = $settings->meter;

    // NOTIFY SMS CLICKSEND API
    $templatessender   = 2;
    $templatesreceiver = 1;

    $next_order             = $core->cdp_order_track();
    $min_cost_tax           = $core->min_cost_tax;
    $min_cost_declared_tax  = $core->min_cost_declared_tax;

    $date      = date('Y-m-d', strtotime(cdp_sanitize($_POST["order_date"])));
    $time      = date("H:i:s");
    $date      = $date . ' ' . $time;
    $sale_date = date("Y-m-d H:i:s");

    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days           = intval($payment_methods->days);
    $due_date       = cdp_sumardias($sale_date, $days);

    if ($days == 0) {
        $status_invoice = 1;
    } else {
        $status_invoice = 2;
    }

    if (isset($_POST["prefix_check"]) && intval($_POST["prefix_check"]) == 1) {
        $code_prefix = cdp_sanitize($_POST["code_prefix2"]);
    } else {
        $code_prefix = cdp_sanitize($_POST["code_prefix"]);
    }

    $is_pickup       = false;
    $order_incomplete = 1;
    $tariff_mode      = isset($_POST['tariff_mode']) ? 1 : 0; // 1 = manual, 0 = motor tarifas

    if ($tariff_mode == 0 && isset($_POST['packages'])) {
        $packages_pre = json_decode($_POST['packages']);
        if (is_array($packages_pre) && count($packages_pre) > 0) {
            $distance_miles_pre = (float)($_POST['distance_miles'] ?? 0);
            $order_svc_pre      = (int)($_POST['order_service_options'] ?? 0);
            $tariff_pre = cdp_calculateTariffServerSide(
                intval($_POST['sender_id']),
                intval($_POST['sender_address_id']),
                intval($_POST['recipient_id']),
                intval($_POST['recipient_address_id']),
                $order_svc_pre,
                $packages_pre,
                $distance_miles_pre,
                (float)$meter
            );
            if ($tariff_pre === null) {
                $errors['tariff_not_found'] = isset($lang['tariff_no_configured']) ? $lang['tariff_no_configured'] : 'No hay tarifa configurada para la ruta/modo/peso indicados.';
            }
        }
    }

    if (empty($errors)) {

    $agencyId = (int)$_POST["agency"];
    if ($ctx['is_restricted']) {
        if ($ctx['agency_id'] === null) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'agency_not_configured', 'message' => 'Su usuario de agencia no tiene una agencia asociada.']);
            exit;
        }

        $agencyId = (int)$ctx['agency_id'];
        $sender_id = (int)$_POST['sender_id'];
        $db_check = new Conexion;
        $db_check->cdp_query('SELECT agency_id FROM cdb_users WHERE id = :id AND userlevel = 1 LIMIT 1');
        $db_check->bind(':id', $sender_id);
        $db_check->cdp_execute();
        $sender_row = $db_check->cdp_registro();
        if (!$sender_row || (int)$sender_row->agency_id !== $agencyId) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'sender_forbidden', 'message' => 'El cliente no pertenece a su agencia.']);
            exit;
        }
    }

    $dataShipment = array(
        'user_id'              => $_SESSION['userid'],
        'order_prefix'         => $code_prefix,
        'is_pickup'            => $is_pickup,
        'order_incomplete'     => $order_incomplete,
        'order_no'             => cdp_sanitize($_POST["order_no"]),
        'order_datetime'       => cdp_sanitize($date),
        'sender_id'            => cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id'         => cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id'    => cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' => cdp_sanitize(intval($_POST["recipient_address_id"])),
        'order_date'           => date("Y-m-d H:i:s"),
        'agency'               => $agencyId,
        'origin_off'           => cdp_sanitize(intval($_POST["origin_off"])),
        'order_package'        => cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category'  => cdp_sanitize(intval($_POST["order_item_category"])),
        'order_courier'        => cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options'=> cdp_sanitize(intval($_POST["order_service_options"])),
        'order_deli_time'      => cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' => cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier'       => cdp_sanitize(intval($_POST["status_courier"])),
        'driver_id'            => cdp_sanitize(intval($_POST["driver_id"])),
        'due_date'             => $due_date,
        'status_invoice'       => $status_invoice,
        'volumetric_percentage'=> $meter,
        'manual_tariff'        => $tariff_mode,
    );

    $shipment_id = cdp_insertCourierShipment($dataShipment);

    if ($shipment_id !== null) {

        if (isset($_POST["packages"])) {

            $packages = json_decode($_POST['packages']);

            // Sumas principales (alineadas con courier_add.js)
            $sumador_total           = 0.0;
            $sumador_valor_declarado = 0.0;
            $max_fixed_charge        = 0.0;
            $sumador_libras          = 0.0; // peso real acumulado
            $sumador_volumetric      = 0.0; // peso volumétrico acumulado

            $precio_total            = 0.0;
            $total_impuesto          = 0.0;
            $total_descuento         = 0.0;
            $total_seguro            = 0.0;
            $total_peso              = 0.0;
            $total_impuesto_aduanero = 0.0;
            $total_valor_declarado   = 0.0;

            // Valores provenientes del formulario
            $tariffs_value      = floatval($_POST["tariffs_value"]);
            $declared_value_tax = floatval($_POST["declared_value_tax"]);
            $insurance_value    = floatval($_POST["insurance_value"]);
            $tax_value          = floatval($_POST["tax_value"]);
            $discount_value     = floatval($_POST["discount_value"]);
            $reexpedicion_value = floatval($_POST["reexpedicion_value"]);
            $price_lb           = floatval($_POST["price_lb"]);
            $insured_value      = floatval($_POST["insured_value"]);

            foreach ($packages as $package) {

                // Cantidad (muy importante para que coincida con JS)
                $qty = isset($package->qty) ? floatval($package->qty) : 1;
                if ($qty <= 0) {
                    $qty = 1;
                }

                $length         = floatval($package->length);
                $width          = floatval($package->width);
                $height         = floatval($package->height);
                $weight         = floatval($package->weight);
                $declared_val   = floatval($package->declared_value);
                $fixed_val      = floatval($package->fixed_value);

                // Guardar detalle de paquete
                $dataAddresses = array(
                    'order_id'       => $shipment_id,
                    'qty'            => $qty,
                    'description'    => $package->description,
                    'length'         => $length,
                    'width'          => $width,
                    'height'         => $height,
                    'weight'         => $weight,
                    'declared_value' => $declared_val,
                    'fixed_value'    => $fixed_val,
                );

                cdp_insertCourierShipmentPackages($dataAddresses);

                // Peso volumétrico por pieza (igual que JS: (L*W*H)/meter)
                $total_metric = 0.0;
                if ($meter > 0) {
                    $total_metric = ($length * $width * $height) / $meter;
                }

                // Acumulados multiplicando por qty (igual JS)
                $sumador_volumetric      += $total_metric * $qty;
                $sumador_libras          += $weight * $qty;
                $sumador_valor_declarado += $declared_val * $qty;
                $max_fixed_charge        += $fixed_val * $qty;
            }

            // Redondeos coherentes con JS
            $sumador_libras     = round($sumador_libras, 2);
            $sumador_volumetric = round($sumador_volumetric, 2);

            // Peso cobrable (chargeable): el mayor entre real y volumétrico
            $calculate_weight = max($sumador_libras, $sumador_volumetric);

            // == BASE FLETE == (backend fuente de verdad cuando manual_tariff = 0)
            if ($tariff_mode == 0) {
                $distance_miles = (float)($_POST['distance_miles'] ?? 0);
                $order_svc      = (int)($_POST['order_service_options'] ?? 0);
                $tariffResult   = cdp_calculateTariffServerSide(
                    intval($_POST['sender_id']),
                    intval($_POST['sender_address_id']),
                    intval($_POST['recipient_id']),
                    intval($_POST['recipient_address_id']),
                    $order_svc,
                    $packages,
                    $distance_miles,
                    (float)$meter
                );
                if ($tariffResult !== null) {
                    $sumador_total = $tariffResult['total_tarifa'];
                    $price_lb      = $tariffResult['price_lb_derived'];
                } else {
                    $sumador_total = $calculate_weight * $price_lb;
                }
            } else {
                $sumador_total = $calculate_weight * $price_lb;
            }

            // == IMPUESTO ==
            if ($sumador_total > $min_cost_tax) {
                $total_impuesto = $sumador_total * ($tax_value / 100);
            }

            // == VALOR DECLARADO ==
            if ($sumador_valor_declarado > $min_cost_declared_tax) {
                $total_valor_declarado = $sumador_valor_declarado * ($declared_value_tax / 100);
            }

            // == DESCUENTO ==
            $total_descuento = $sumador_total * ($discount_value / 100);
            // Evitar incoherencias (igual que JS: no negativo y no mayor que el total)
            if ($discount_value < 0 || $total_descuento > $sumador_total) {
                $discount_value  = 0;
                $total_descuento = 0;
            }

            // == SEGURO ==
            $total_seguro = $insured_value * ($insurance_value / 100);

            // == PESO TOTAL (para arancel) ==
            $total_peso = $sumador_libras + $sumador_volumetric;

            // == IMPUESTO ADUANERO ==
            $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

            // == TOTAL ENVÍO ==
            $total_envio = ($sumador_total - $total_descuento)
                + $total_seguro
                + $total_impuesto
                + $total_impuesto_aduanero
                + $total_valor_declarado
                + $max_fixed_charge
                + $reexpedicion_value;

            $total_envio = round($total_envio, 2);
        }

        // Totales para guardar en la tabla principal (coherentes con lo que ves en el front)
        $dataShipmentUpdateTotals = array(
            'order_id'                    => $shipment_id,
            'value_weight'                => floatval($price_lb),           // precio por lb/kilo
            'sub_total'                   => floatval($sumador_total),      // base_flete
            'tax_discount'                => floatval($discount_value),     // %
            'total_insured_value'         => floatval($insured_value),
            'tax_insurance_value'         => floatval($insurance_value),    // %
            'tax_custom_tariffis_value'   => floatval($tariffs_value),      // %
            'tax_value'                   => floatval($tax_value),          // %
            'declared_value'              => floatval($declared_value_tax), // %
            'total_reexp'                 => floatval($reexpedicion_value),
            'total_declared_value'        => floatval($total_valor_declarado),
            'total_fixed_value'           => floatval($max_fixed_charge),
            'total_tax_discount'          => floatval($total_descuento),
            'total_tax_insurance'         => floatval($total_seguro),
            'total_tax_custom_tariffis'   => floatval($total_impuesto_aduanero),
            'total_tax'                   => floatval($total_impuesto),
            'total_weight'                => floatval($total_peso),
            'total_order'                 => floatval($total_envio),
        );

        $update      = cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);
        $order_track = $code_prefix . $_POST["order_no"];

        // =======================
        // ARCHIVOS ADJUNTOS
        // =======================
        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

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

        // =======================
        // TRACKING / HISTORIAL / NOTIFICACIONES
        // =======================

        $dataTrack = array(
            'user_id'       => $_SESSION['userid'],
            'order_id'      => $shipment_id,
            'order_track'   => $order_track,
            't_date'        => date("Y-m-d H:i:s"),
            'status_courier'=> cdp_sanitize(intval($_POST["status_courier"])),
            'comments'      => $lang['notification_shipment8'],
            'office'        => cdp_sanitize(intval($_POST["origin_off"]))
        );

        cdp_insertCourierShipmentTrack($dataTrack);

        $sender_data   = cdp_getSenderCourier(intval($_POST["sender_id"]));
        $receiver_data = cdp_getRecipientCourier(intval($_POST["recipient_id"]));

        $fullshipment = $code_prefix . $_POST["order_no"];

        $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status  = $name_status->mod_style;

        $date_ship = date("Y-m-d H:i:s a");

        $app_url = $settings->site_url . 'track.php?order_track=' . $fullshipment;
        $subject = $lang['notification_shipment2'] . $lang['notification_shipment6'] . $fullshipment;

        $email_template = cdp_getEmailTemplatesdg1i4(16);

        $body = str_replace(
            array(
                '[NAME]',
                '[TRACKING]',
                '[DELIVERY_TIME]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]',
                '[URL_SHIP]'
            ),
            array(
                $sender_data->fname . ' ' . $sender_data->lname,
                $fullshipment,
                $date_ship,
                $msite_url,
                $mlogo,
                $msnames,
                $app_url
            ),
            $email_template->body
        );

        $newbody = cdp_cleanOut($body);

        // --- Email (PHP mail o SMTP) ---
        if ($check_mail == 'PHP') {

            $message = $newbody;
            $to      = $sender_data->email;
            $from    = $site_email;

            $header  = "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html; charset=UTF-8 \r\n";
            $header .= "From: " . $from . " \r\n";
            try {
                mail($to, $subject, $message, $header);
            } catch (Exception $e) {
            }
        } elseif ($check_mail == 'SMTP') {

            $destinatario = $sender_data->email;

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $smtphoste;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpuser;
            $mail->Password   = $smtppass;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom($site_email, $names_info);
            $mail->addAddress($destinatario);
            $mail->addCC($site_email, $lang['notification_shipment5']);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = "
                <html> 
                <body> 
                <p>{$newbody}</p>
                </body> 
                </html>
                <br />";

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            try {
                $estadoEnvio = $mail->Send();
            } catch (Exception $e) {
            }
        }

        // Historial usuario
        $dataHistory = array(
            'user_id'      => $_SESSION['userid'],
            'order_id'     => $shipment_id,
            'order_track'  => $order_track,
            'action'       => $lang['notification_shipment8'],
            'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertCourierShipmentUserHistory($dataHistory);

        // Notificación general
        $dataNotification = array(
            'user_id'                  => $_SESSION['userid'],
            'order_id'                 => $shipment_id,
            'order_track'              => $order_track,
            'notification_description' => $lang['notification_shipment'],
            'shipping_type'            => '1',
            'notification_date'        => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertNotification($dataNotification);
        $notification_id = $db->dbh->lastInsertId();

        // NOTIFICATION TO DRIVER
        cdp_insertNotificationsUsers($notification_id, intval($_POST["driver_id"]));
        // NOTIFICATION TO ADMIN AND EMPLOYEES
        $users_employees = cdp_getUsersAdminEmployees();
        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        // NOTIFICATION TO CUSTOMER
        cdp_insertNotificationsUsers($notification_id, intval($_POST['sender_id']));

        // Dirección remitente
        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $sender_country      = $sender_address_data->country;
        $sender_state        = $sender_address_data->state;
        $sender_city         = $sender_address_data->city;
        $sender_zip_code     = $sender_address_data->zip_code;
        $sender_address      = $sender_address_data->address;

        $_sender_country      = cdp_getCountry($sender_country);
        $final_sender_country = $_sender_country['data'];

        $_sender_state      = cdp_getState($sender_state);
        $final_sender_state = $_sender_state['data'];

        $sender_city_obj   = cdp_getCity($sender_city);
        $final_sender_city = $sender_city_obj['data'];

        // Dirección destinatario
        $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        $recipient_address  = $recipient_address_data->address;
        $recipient_country  = $recipient_address_data->country;
        $recipient_city     = $recipient_address_data->city;
        $recipient_state    = $recipient_address_data->state;
        $recipient_zip_code = $recipient_address_data->zip_code;

        $_recipient_country      = cdp_getCountry($recipient_country);
        $final_recipient_country = $_recipient_country['data'];

        $_recipient_state      = cdp_getState($recipient_state);
        $final_recipient_state = $_recipient_state['data'];

        $recipient_city_obj   = cdp_getCity($recipient_city);
        $final_recipient_city = $recipient_city_obj['data'];

        // SAVE ADDRESS FOR Shipments
        $dataAddressesShip = array(
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

        cdp_insertCourierShipmentAddresses($dataAddressesShip);

        // =======================
        // WHATSAPP
        // =======================
        $notify_whatsapp_sender   = isset($_POST['notify_whatsapp_sender']) && $_POST['notify_whatsapp_sender'] == 1;
        $notify_whatsapp_receiver = isset($_POST['notify_whatsapp_receiver']) && $_POST['notify_whatsapp_receiver'] == 1;

        function sendWhatsAppNotification($data, $shipment_id, $type) {
            try {
                sendNotificationWhatsAppWithPDF($data, $shipment_id, $type);
            } catch (Exception $e) {
                error_log('Error sending WhatsApp notification: ' . $e->getMessage());
            }
        }

        if ($notify_whatsapp_sender) {
            sendWhatsAppNotification($sender_data, $shipment_id, 3);
        }

        if ($notify_whatsapp_receiver) {
            sendWhatsAppNotification($receiver_data, $shipment_id, 3);
        }

        // =======================
        // SMS
        // =======================
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

        $messages[] = $lang['message_ajax_success_add_shipment'];
    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors'  => $errors
    ]);
} else {
    echo json_encode([
        'success'     => true,
        'messages'    => $messages,
        'shipment_id' => $shipment_id,
    ]);
}
