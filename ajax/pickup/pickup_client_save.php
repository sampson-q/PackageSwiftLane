<?php
// *************************************************************************
// * DEPRIXA PRO — Client Pickup Save (no permission gate, login only)     *
// *************************************************************************
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

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

if (empty($_POST['order_item_category']))
    $errors['order_item_category'] = $lang['validate_field_ajax151'];

if (empty($_POST['order_package']))
    $errors['order_package'] = $lang['validate_field_ajax152'];

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $order_prefix = $settings->prefix;
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

    $next_order           = $core->cdp_order_track();
    $min_cost_tax         = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $date   = date('Y-m-d', strtotime(cdp_sanitize($_POST["order_date"])));
    $time   = date("H:i:s");
    $date   = $date . ' ' . $time;
    $status = 14;
    $is_pickup       = true;
    $order_incomplete = 0;
    $days            = 0;
    $sale_date       = date("Y-m-d H:i:s");
    $due_date        = cdp_sumardias($sale_date, $days);
    $status_invoice  = 2;

    $dataShipment = array(
        'user_id'               => $_SESSION['userid'],
        'order_prefix'          => $order_prefix,
        'order_incomplete'      => $order_incomplete,
        'is_pickup'             => $is_pickup,
        'order_no'              => cdp_sanitize($next_order),
        'order_datetime'        => cdp_sanitize($date),
        'sender_id'             => cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id'          => cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id'     => cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id'  => cdp_sanitize(intval($_POST["recipient_address_id"])),
        'order_date'            => date("Y-m-d H:i:s"),
        'order_package'         => cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category'   => cdp_sanitize(intval($_POST["order_item_category"])),
        'order_service_options' => null,
        'status_courier'        => cdp_sanitize(intval($status)),
        'due_date'              => $due_date,
        'status_invoice'        => $status_invoice,
        'volumetric_percentage' => $meter,
    );

    $shipment_id = cdp_insertCourierPickupFromCustomer($dataShipment);

    if ($shipment_id !== null) {

        if (isset($_POST["packages"])) {

            $packages = json_decode($_POST['packages']);

            $sumador_total          = 0;
            $sumador_valor_declarado = 0;
            $max_fixed_charge       = 0;
            $sumador_libras         = 0;
            $sumador_volumetric     = 0;
            $total_impuesto         = 0;
            $total_descuento        = 0;
            $total_seguro           = 0;
            $total_peso             = 0;
            $total_impuesto_aduanero = 0;
            $total_valor_declarado  = 0;

            $tariffs_value      = $_POST["tariffs_value"]      ?? 0;
            $declared_value_tax = $_POST["declared_value_tax"] ?? 0;
            $insurance_value    = $_POST["insurance_value"]    ?? 0;
            $tax_value          = $_POST["tax_value"]          ?? 0;
            $discount_value     = $_POST["discount_value"]     ?? 0;
            $reexpedicion_value = $_POST["reexpedicion_value"] ?? 0;
            $price_lb           = $_POST["price_lb"]           ?? 0;
            $insured_value      = $_POST["insured_value"]      ?? 0;

            foreach ($packages as $package) {
                $dataAddresses = array(
                    'order_id'      => $shipment_id,
                    'qty'           => $package->qty,
                    'description'   => $package->description,
                    'length'        => $package->length,
                    'width'         => $package->width,
                    'height'        => $package->height,
                    'weight'        => $package->weight,
                    'declared_value'=> $package->declared_value,
                    'fixed_value'   => $package->fixed_value,
                );
                cdp_insertCourierShipmentPackages($dataAddresses);

                $total_metric = $package->length * $package->width * $package->height / $meter;
                $sumador_volumetric += $total_metric;
                $sumador_libras     += (float)$package->weight;
                $sumador_valor_declarado += (float)$package->declared_value;
                $max_fixed_charge   += (float)$package->fixed_value;
            }

            // price_lb is the pre-computed flat total from getTariffs() — use directly.
            $sumador_total = floatval($price_lb);

            if ($sumador_total > $min_cost_tax) {
                $total_impuesto = $sumador_total * $tax_value / 100;
            }
            if ($sumador_valor_declarado > $min_cost_declared_tax) {
                $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
            }

            $total_descuento          = $sumador_total * $discount_value / 100;
            $total_peso               = $sumador_libras + $sumador_volumetric;
            $total_seguro             = $insured_value * $insurance_value / 100;
            $total_impuesto_aduanero  = $total_peso * $tariffs_value;
            $total_envio = ($sumador_total - $total_descuento) + $total_seguro + $total_impuesto
                         + $total_impuesto_aduanero + $total_valor_declarado + $max_fixed_charge
                         + $reexpedicion_value;
        }

        $dataShipmentUpdateTotals = array(
            'order_id'                   => $shipment_id,
            'value_weight'               => floatval($price_lb),
            'sub_total'                  => floatval($sumador_total),
            'tax_discount'               => floatval($discount_value),
            'total_insured_value'        => floatval($insured_value),
            'tax_insurance_value'        => floatval($insurance_value),
            'tax_custom_tariffis_value'  => floatval($tariffs_value),
            'tax_value'                  => floatval($tax_value),
            'declared_value'             => floatval($declared_value_tax),
            'total_reexp'                => floatval($reexpedicion_value),
            'total_declared_value'       => floatval($total_valor_declarado),
            'total_fixed_value'          => floatval($max_fixed_charge),
            'total_tax_discount'         => floatval($total_descuento),
            'total_tax_insurance'        => floatval($total_seguro),
            'total_tax_custom_tariffis'  => floatval($total_impuesto_aduanero),
            'total_tax'                  => floatval($total_impuesto),
            'total_weight'               => floatval($total_peso),
            'total_order'                => floatval($total_envio),
        );

        cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);
        $order_track = $order_prefix . $next_order;

        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {
            $target_dir      = "../../order_files/";
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

        $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));

        $dataTrack = array(
            'user_id'       => $_SESSION['userid'],
            'order_id'      => $shipment_id,
            'order_track'   => $order_track,
            't_date'        => date("Y-m-d H:i:s"),
            'status_courier'=> cdp_sanitize(intval($status)),
            'comments'      => $lang['messagesform39'] . ' ' . $sender_data->fname . ' ' . $sender_data->lname,
            'office'        => null,
        );
        cdp_insertCourierShipmentTrack($dataTrack);

        $fullshipment = $order_prefix . $next_order;
        $date_ship    = date("Y-m-d H:i:s a");
        $app_url      = $settings->site_url . 'track.php?order_track=' . $fullshipment;
        $subject      = $lang['notification_shipment2'] . $lang['notification_shipment6'] . $fullshipment;

        $email_template = cdp_getEmailTemplatesdg1i4(16);
        $body = str_replace(
            ['[NAME]','[TRACKING]','[DELIVERY_TIME]','[URL]','[URL_LINK]','[SITE_NAME]','[URL_SHIP]'],
            [$sender_data->fname . ' ' . $sender_data->lname, $fullshipment, $date_ship, $msite_url, $mlogo, $msnames, $app_url],
            $email_template->body
        );
        $newbody = cdp_cleanOut($body);

        if ($check_mail == 'PHP') {
            $header  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8 \r\nFrom: " . $site_email . " \r\n";
            try { mail($sender_data->email, $subject, $newbody, $header); } catch (Exception $e) {}
        } elseif ($check_mail == 'SMTP') {
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = true;
            $mail->Port     = $smtpport;
            $mail->IsHTML(true);
            $mail->CharSet  = 'UTF-8';
            $mail->Host     = $smtphoste;
            $mail->Username = $smtpuser;
            $mail->Password = $smtppass;
            $mail->From     = $site_email;
            $mail->FromName = $names_info;
            $mail->AddAddress($sender_data->email);
            $mail->Subject  = $subject;
            $mail->Body     = "<html><body><p>{$newbody}</p></body></html>";
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true]];
            try { $mail->Send(); } catch (Exception $e) {}
        }

        $dataHistory = array(
            'user_id'      => $_SESSION['userid'],
            'order_id'     => $shipment_id,
            'action'       => $lang['notification_shipment8'],
            'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
            'order_track'  => $order_track,
        );
        cdp_insertCourierShipmentUserHistory($dataHistory);

        $dataNotification = array(
            'user_id'                  => $_SESSION['userid'],
            'order_id'                 => $shipment_id,
            'notification_description' => $lang['notification_shipment'],
            'shipping_type'            => '1',
            'notification_date'        => cdp_sanitize(date("Y-m-d H:i:s")),
        );
        $notification_id = cdp_insertNotification($dataNotification);

        $users_employees = cdp_getUsersAdminEmployees();
        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        cdp_insertNotificationsUsers($notification_id, intval($_POST['sender_id']));

        $sender_address_data  = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        $sc  = cdp_getCountry($sender_address_data->country);
        $ss  = cdp_getState($sender_address_data->state);
        $scy = cdp_getCity($sender_address_data->city);
        $rc  = cdp_getCountry($recipient_address_data->country);
        $rs  = cdp_getState($recipient_address_data->state);
        $rcy = cdp_getCity($recipient_address_data->city);

        $dataAddresses = array(
            'order_id'          => $shipment_id,
            'order_track'       => $order_track,
            'sender_country'    => $sc['data']->name,
            'sender_state'      => $ss['data']->name,
            'sender_city'       => $scy['data']->name,
            'sender_zip_code'   => $sender_address_data->zip_code,
            'sender_address'    => $sender_address_data->address,
            'recipient_country' => $rc['data']->name,
            'recipient_state'   => $rs['data']->name,
            'recipient_city'    => $rcy['data']->name,
            'recipient_zip_code'=> $recipient_address_data->zip_code,
            'recipient_address' => $recipient_address_data->address,
        );
        cdp_insertCourierShipmentAddresses($dataAddresses);

        $messages[] = $lang['message_ajax_success_add_pickup'];
    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
} else {
    echo json_encode(['success' => true, 'messages' => $messages, 'shipment_id' => $shipment_id]);
}
