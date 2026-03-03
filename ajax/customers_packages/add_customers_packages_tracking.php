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

// ini_set('display_errors', 0);


require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/functions.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service.php");
require_once("../notify_sms/api_sms_service.php");



$user = new User;
$core = new Core;
$errors = array();

$db = new Conexion;

if (empty($_POST['t_date']))

    $errors['t_date'] = $lang['validate_field_ajax159'];

if (empty($_POST['address']))

    $errors['address'] = $lang['validate_field_ajax88'];

if (intval($_POST['status_courier']) <= 0)

    $errors['status_courier'] = $lang['validate_field_ajax160'];

if (intval($_POST['office']) <= 0)

    $errors['office'] = $lang['validate_field_ajax84'];

if (empty($_POST['country']))

    $errors['country'] = $lang['validate_field_ajax102'];



if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $site_email = $settings->email_address;
    $check_mail = $settings->mailer;
    $names_info = $settings->smtp_names;
    $mlogo = $settings->logo;
    $msite_url = $settings->site_url;
    $msnames = $settings->site_name;
    //SMTP
    $smtphoste = $settings->smtp_host;
    $smtpuser = $settings->smtp_user;
    $smtppass = $settings->smtp_password;
    $smtpport = $settings->smtp_port;
    $smtpsecure = $settings->smtp_secure;


    // NOTIFY SMS CLICKSEND API
    $templatessender = 8;


    $date = date('Y-m-d', strtotime(cdp_sanitize($_POST["t_date"])));
    $time = date("H:i:s");
    $date = $date . ' ' . $time;

    $status = intval($_POST['status_courier']);
    $shipment_id = intval($_POST['package_id']);


    $shipment = cdp_getCustomerPackage($shipment_id);


    if ($shipment) {

        $update = updateCustomerPackagesStatusFromTracking($status, $shipment_id);
        $order_track = $shipment->order_prefix . $shipment->order_no;

        $dataTrack = array(
            'user_id' =>  $_SESSION['userid'],
            'order_track' =>  $order_track,
            't_date' => $date,
            'status_courier' =>  cdp_sanitize(intval($status)),
            'comments' =>   cdp_sanitize($_POST['comments']),
            'office' => cdp_sanitize(intval($_POST["office"]))
        );

        insertCourierShipmentTrack($dataTrack);

        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            'action' =>  $lang['notification_shipment11'],
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );

        $dataNotification = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            'notification_description' => $lang['notification_shipment'],
            'shipping_type' => '4',
            'notification_date' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );
        // SAVE NOTIFICATION
        cdp_insertNotification(
            $dataNotification
        );

        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO ADMIN AND EMPLOYEES
        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        //NOTIFICATION TO CUSTOMER
        cdp_insertNotificationsUsers($notification_id, intval($shipment->sender_id));




        $sender_data = cdp_getSenderCourier(intval($shipment->sender_id));

        $fullshipment = $shipment->order_prefix . $shipment->order_no;
        $date_ship   = date("Y-m-d H:i:s a");

        $app_url = $settings->site_url . 'track.php?order_track=' . $fullshipment;
        $subject = $lang['notification_shipment9'] . ' ' . $lang['notification_shipment6'] .  $fullshipment;

        $status_courier_deliver = "" . $_POST['status_courier'] . "";

        $db->cdp_query("SELECT * FROM cdb_styles where id= '" . $status_courier_deliver . "'");
        $status_data = $db->cdp_registro();


        // Obtener el ID del estado del envio desde el POST SMS
        $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status = $name_status->mod_style;


        $email_template = cdp_getEmailTemplatesdg1i4(10);

        $body = str_replace(
            array(
                '[NAME]',
                '[TRACKING]',
                '[DELIVERY_TIME]',
                '[COURIER]',
                '[NEW_ADDRESS]',
                '[COMMENT]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]',
                '[URL_SHIP]'
            ),
            array(
                $sender_data->fname . ' ' . $sender_data->lname,
                $fullshipment,
                $date_ship,
                $status_data->mod_style,
                $_POST['country'] . ' | ' . $_POST['address'],
                $_POST['comments'],
                $msite_url,
                $mlogo,
                $msnames,
                $app_url
            ),
            $email_template->body
        );

        $newbody = cdp_cleanOut($body);

        //SENDMAIL PHP

        if ($check_mail == 'PHP') {

            $message = $newbody;
            $to = $sender_data->email;
            $from = $site_email;

            $header = "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html; charset=UTF-8 \r\n";
            $header .= "From: " . $from . " \r\n";

            mail($to, $subject, $message, $header);
        } elseif ($check_mail == 'SMTP') {

            //PHPMAILER PHP   
            $destinatario = $sender_data->email;
            $mail = new PHPMailer(true);                              // Passing `true` enables exceptions

            //Server settings

            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $smtphoste;                       // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $smtpuser;                   // SMTP username
            $mail->Password = $smtppass;               // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($site_email, $names_info);
            $mail->addAddress($destinatario);     // Add a recipient
            $mail->addCC($site_email,  $lang['notification_shipment9']);

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = "
                <html> 
                <body> 
                <p>{$newbody}</p>
                </body> 
                </html>
                <br />"; // Texto del email en formato HTML

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            try {
                $estadoEnvio = $mail->Send();
                //echo "El correo fue enviado correctamente.";
            } catch (Exception $e) {
                //echo "Ocurrió un error inesperado.";
            }
        }

        //NOTIFY WHATSAPP API

        if (isset($_POST['notify_whatsapp']) && intval($_POST['notify_whatsapp']) == 1) {
            $notification_result =  sendNotificationWhatsApp($sender_data, 4, null, $fullshipment);
        }


        // Obtener el estado de las casillas de verificación
        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;

        // Generar cuerpo del SMS para el remitente
        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            // Llamar a la función para enviar la notificación SMS al remitente
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
            // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
        }

        $messages[] = $lang['notification_shipment11'];
    } else {
        $errors['critical_error'] = "Error";
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
