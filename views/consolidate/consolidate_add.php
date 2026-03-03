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



if (!$user->cdp_is_Admin())
    cdp_redirect_to("login.php");



require_once("helpers/querys.php");

require_once("helpers/phpmailer/class.phpmailer.php");
require_once("helpers/phpmailer/class.smtp.php");
require_once("ajax/notify_sms/api_sms_consolidate_service.php");

$userData = $user->cdp_getUserData();

$db = new Conexion;
$core = new Core;

$db->cdp_query("SELECT * FROM cdb_info_ship_default where id= '1'");
$infoship = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_category where id= '" . $infoship->logistics_default1 . "'");
$s_logistics = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $infoship->packaging_default2 . "'");
$packaging_box = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $infoship->courier_default3 . "'");
$courier_comp = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $infoship->service_default4 . "'");
$ship_modes = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $infoship->time_default5 . "'");
$delivery_times = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $infoship->pay_default6 . "'");
$metod_payment = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_payment_methods where id= '" . $infoship->payment_default7 . "'");
$payment_methods = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $infoship->status_default8 . "'");
$styles_status = $db->cdp_registro();

//Prefix tracking   
$sql = "SELECT * FROM cdb_settings";

$db->cdp_query($sql);

$db->cdp_execute();

$settings = $db->cdp_registro();

$order_prefix = $settings->prefix_consolidate;
$check_mail = $settings->mailer;
$names_info = $settings->smtp_names;
$mlogo      = $settings->logo;
$msite_url  = $settings->site_url;
$msnames    = $settings->site_name;

//SMTP

$smtphoste = $settings->smtp_host;
$smtpuser = $settings->smtp_user;
$smtppass = $settings->smtp_password;
$smtpport = $settings->smtp_port;
$smtpsecure = $settings->smtp_secure;

$value_weight = $settings->value_weight;
$meter = $settings->meter;
$site_email = $settings->site_email;


// NOTIFY SMS CLICKSEND API 
$templatessender = 13;
$templatesreceiver = 14;


if (isset($_POST["create_invoice"])) {

    $next_order = $core->cdp_consolidate_track();
    $date = date('Y-m-d', strtotime(trim($_POST["order_date"])));
    $time = date("H:i:s");

    $date = $date . ' ' . $time;

    if (isset($_POST["prefix_check"]) == 1) {

        $code_prefix = cdp_sanitize($_POST["code_prefix2"]);
    } else {

        $code_prefix = cdp_sanitize($_POST["code_prefix"]);
    }

    $status_invoice = 2;




    $db->cdp_query("
                INSERT INTO cdb_consolidate 
                (
                    user_id,
                    c_prefix,
                    c_no, 
                    c_date,                    
                    sender_id,
                    sender_address_id,
                    receiver_id,                
                    receiver_address_id,
                    tax_value,
                    tax_insurance_value,
                    total_insured_value,
                    tax_custom_tariffis_value,
                    value_weight,
                    volumetric_percentage,
                    total_weight,                    
                    sub_total,
                    tax_discount,
                    total_tax_insurance,
                    total_tax_custom_tariffis,
                    total_tax,
                    total_tax_discount,
                    total_reexp,                    
                    total_order,                    
                    order_datetime,
                    agency,
                    origin_off,
                    order_package,
                    order_item_category, 
                    order_courier,
                    order_service_options,
                    order_deli_time,                   
                    order_pay_mode,
                    status_courier,
                    driver_id,
                    seals_package,
                    status_invoice
                    )
                VALUES
                    (
                    :user_id,
                    :order_prefix,
                    :order_no,
                    :order_date,
                    :sender_id,
                    :sender_address_id,
                    :receiver_id,       
                    :receiver_address_id,
                    :tax_value,
                    :tax_insurance_value,
                    :total_insured_value,
                    :tax_custom_tariffis_value,
                    :value_weight,
                    :volumetric_percentage,
                    :total_weight,                    
                    :sub_total,
                    :tax_discount,
                    :total_tax_insurance,
                    :total_tax_custom_tariffis,
                    :total_tax,
                    :total_tax_discount,
                    :total_reexp,
                    :total_order,                    
                    :order_datetime,
                    :agency,
                    :origin_off,
                    :order_package,
                    :order_item_category, 
                    :order_courier,
                    :order_service_options,
                    :order_deli_time,                   
                    :order_pay_mode,
                    :status_courier,
                    :driver_id,
                    :seals_package,
                    :status_invoice
                    )
            ");


    $db->bind(':status_invoice',  $status_invoice);
    $db->bind(':user_id',  $_SESSION['userid']);
    $db->bind(':order_prefix',  $code_prefix);
    $db->bind(':order_no',  $_POST["order_no"]);
    $db->bind(':order_datetime',  trim($date));
    $db->bind(':sender_id',  cdp_sanitize($_POST["sender_id"]));
    $db->bind(':receiver_id',  cdp_sanitize($_POST["recipient_id"]));
    $db->bind(':sender_address_id',  cdp_sanitize($_POST["sender_address_id"]));
    $db->bind(':receiver_address_id',  cdp_sanitize($_POST["recipient_address_id"]));
    $db->bind(':tax_value', floatval($_POST["tax_value"]));
    $db->bind(':tax_insurance_value', floatval($_POST["insurance_value"]));
    $db->bind(':total_insured_value', floatval($_POST["insured_value"]));
    $db->bind(':tax_custom_tariffis_value', floatval($_POST["tariffs_value"]));
    $db->bind(':value_weight',  floatval($_POST["price_lb"]));
    $db->bind(':volumetric_percentage',  $meter);
    $db->bind(':sub_total',  floatval($_POST["subtotal_input"]));
    $db->bind(':tax_discount',  floatval($_POST["discount_value"]));
    $db->bind(':total_reexp',  floatval($_POST["reexpedicion_value"]));
    $db->bind(':total_tax_discount',  floatval($_POST["discount_input"]));
    $db->bind(':total_tax_insurance',  floatval($_POST["insurance_input"]));
    $db->bind(':total_tax_custom_tariffis',  floatval($_POST["total_impuesto_aduanero_input"]));
    $db->bind(':total_tax',  floatval($_POST["impuesto_input"]));
    $db->bind(':total_order',  floatval($_POST["total_envio_input"]));
    $db->bind(':total_weight',  floatval($_POST["total_weight_input"]));
    $db->bind(':order_date',  date("Y-m-d H:i:s"));
    $db->bind(':agency',  cdp_sanitize($_POST["agency"]));
    $db->bind(':origin_off',  cdp_sanitize($_POST["origin_off"]));
    $db->bind(':order_package',  cdp_sanitize($_POST["order_package"]));
    $db->bind(':order_item_category',  cdp_sanitize($_POST["order_item_category"]));
    $db->bind(':order_courier',  cdp_sanitize($_POST["order_courier"]));
    $db->bind(':order_service_options',  cdp_sanitize($_POST["order_service_options"]));
    $db->bind(':order_deli_time',  cdp_sanitize($_POST["order_deli_time"]));
    $db->bind(':order_pay_mode',  cdp_sanitize($_POST["order_pay_mode"]));
    $db->bind(':status_courier',  cdp_sanitize($_POST["status_courier"]));
    $db->bind(':driver_id',  cdp_sanitize($_POST["driver_id"]));
    $db->bind(':seals_package',  cdp_sanitize($_POST["seals"]));

    $db->cdp_execute();


    $order_id = $db->dbh->lastInsertId();

    for ($count = 0; $count < $_POST["total_item"]; $count++) {

        $db->cdp_query("
                  INSERT INTO cdb_consolidate_detail 
                  (
                  consolidate_id,
                  order_id,
                  order_prefix,
                  order_no,
                  weight,
                  weight_vol,  
                  length,
                  width,
                  height              
                                  
                  )
                  VALUES 
                  (
                  :consolidate_id,
                  :order_id,
                  :order_prefix,
                  :order_no, 
                  :weight,
                  :weight_vol,
                  :length,
                  :width,
                  :height                               
                  )
                ");


        $db->bind(':consolidate_id',  $order_id);
        $db->bind(':order_prefix',  cdp_sanitize($_POST["prefix"][$count]));
        $db->bind(':order_no',  cdp_sanitize($_POST["order_no_item"][$count]));
        $db->bind(':weight',  cdp_sanitize($_POST["weight"][$count]));
        $db->bind(':length',  cdp_sanitize($_POST["length"][$count]));
        $db->bind(':width',  cdp_sanitize($_POST["width"][$count]));
        $db->bind(':height',  cdp_sanitize($_POST["height"][$count]));
        $db->bind(':weight_vol',  cdp_sanitize($_POST["weight_vol"][$count]));
        $db->bind(':order_id',  cdp_sanitize($_POST["order_id"][$count]));

        $db->cdp_execute();

        $is_consolidate = 1;

        $db->cdp_query("
                UPDATE  cdb_add_order SET           
                    
                    is_consolidate=:is_consolidate

                    WHERE order_id=:order_id                 
                
                ");

        $db->bind(':order_id',  cdp_sanitize($_POST["order_id"][$count]));
        $db->bind(':is_consolidate',  $is_consolidate);

        $db->cdp_execute();
    }


    if (count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

        $target_dir = "order_files/";

        $deleted_file_ids = array();

        if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {

            $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
        }


        foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {


            if (!in_array($key, $deleted_file_ids)) {

                $image_name = time() . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                $target_file = $target_dir . $image_name;
                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                $imageFileZise = $_FILES["filesMultiple"]["size"][$key];

                if ($imageFileZise > 0) {

                    move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                    $imagen = basename($_FILES["filesMultiple"]["name"][$key]);
                    $file = "image_path='img/usuarios/$image_name' ";
                }

                cdp_insertOrdersFiles($order_id, $target_file, $image_name, date("Y-m-d H:i:s"), '1', $imageFileType);
            }
        }
    }




    // Add a sender
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $_POST["sender_id"] . "'");
    $sender_data = $db->cdp_registro();


    // Add a recipient
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $_POST["recipient_id"] . "'");
    $receiver_data = $db->cdp_registro();


    // Add a user
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $_SESSION['userid'] . "'");
    $user_data = $db->cdp_registro();


    $fullshipment = $code_prefix . $_POST["order_no"];
    $status_consolidated = $_POST["status_courier"];
    // Obtener el ID del estado del mensajero desde el POST
    $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
    $add_status = $name_status->mod_style;
    $date_ship   = date("Y-m-d H:i:s a");
    $app_url = $settings->site_url . 'track.php?order_track=' . $fullshipment;

    $subject = $lang['notification_shipment-22'] . $lang['notification_shipment6'] .  $fullshipment;

    $email_template = cdp_getEmailTemplatesdg1i4(1);

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


        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->Port = $smtpport;
        $mail->IsHTML(true);
        $mail->CharSet = "utf-8";

        // Datos de la cuenta de correo utilizada para enviar vía SMTP
        $mail->Host = $smtphoste;       // Dominio alternativo brindado en el email de alta
        $mail->Username = $smtpuser;    // Mi cuenta de correo
        $mail->Password = $smtppass;    //Mi contraseña


        $mail->From = $site_email; // Email desde donde envío el correo.
        $mail->FromName = $names_info;
        $mail->AddAddress($destinatario); // Esta es la dirección a donde enviamos los datos del formulario

        $mail->Subject = $subject; // Este es el titulo del email.
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

        $estadoEnvio = $mail->Send();
        if ($estadoEnvio) {
            // echo "El correo fue enviado correctamente.";
        } else {
            // echo "Ocurrió un error inesperado.";
        }
    }

    // Obtener el estado de las casillas de verificación
    $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
    $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

    // Generar cuerpo del SMS para el remitente
    try {
        $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
        // Llamar a la función para enviar la notificación SMS al remitente
        sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
    } catch (Exception $e) {
        error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
        // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
    }

    // Generar cuerpo del SMS para el receptor
    try {
        $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);
        // Llamar a la función para enviar la notificación SMS al receptor
        sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
    } catch (Exception $e) {
        error_log('Error generating or sending SMS for receiver: ' . $e->getMessage());
        // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
    }


    $order_track = $code_prefix . $_POST["order_no"];

    $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track, 
                    comments,                                  
                    t_date,
                    status_courier,
                    office_id,
                    user_id
                    )
                VALUES
                    (
                    :order_track, 
                    :comments,                                     
                    :t_date,
                    :status_courier,
                    :office,                   
                    :user_id
                    )
            ");



    $db->bind(':order_track',  $order_track);
    $db->bind(':t_date',  date("Y-m-d H:i:s"));
    $db->bind(':status_courier', $_POST['status_courier']);
    $db->bind(':comments', $lang['notification_shipment28'] . ' ' . $user_data->fname . ' ' . $user_data->lname);
    $db->bind(':office', $_POST['origin_off']);
    $db->bind(':user_id',  $_SESSION['userid']);

    $db->cdp_execute();

    //INSERT HISTORY USER
    $date = date("Y-m-d H:i:s");
    $db->cdp_query("
                INSERT INTO cdb_order_user_history 
                (
                    user_id,
                    order_id,
                    order_track,
                    action,
                    date_history,
                    is_consolidate                  
                    )
                VALUES
                    (
                    :user_id,
                    :order_id,
                    :order_track,
                    :action,
                    :date_history,
                    :is_consolidate
                    )
            ");



    $db->bind(':order_id',  $order_id);
    $db->bind(':order_track',  $order_track);
    $db->bind(':is_consolidate', '1');
    $db->bind(':user_id',  $_SESSION['userid']);
    $db->bind(':action', $lang['notification_shipment28']);
    $db->bind(':date_history',  trim($date));
    $db->cdp_execute();

    // SAVE NOTIFICATION
    $db->cdp_query("
                INSERT INTO cdb_notifications 
                (
                    user_id,
                    order_id,
                    notification_description,
                    shipping_type,
                    notification_date

                )
                VALUES
                    (
                    :user_id,                    
                    :order_id,
                    :notification_description,
                    :shipping_type,
                    :notification_date                    
                    )
            ");



    $db->bind(':user_id',  $_SESSION['userid']);
    $db->bind(':order_id',  $order_id);
    $db->bind(':notification_description', $lang['notification_shipment29']);
    $db->bind(':shipping_type', '2');
    $db->bind(':notification_date',  date("Y-m-d H:i:s"));

    $db->cdp_execute();


    $notification_id = $db->dbh->lastInsertId();

    //NOTIFICATION TO DRIVER

    cdp_insertNotificationsUsers($notification_id, $_POST["driver_id"]);


    //NOTIFICATION TO ADMIN AND EMPLOYEES

    $users_employees = cdp_getUsersAdminEmployees();

    foreach ($users_employees as $key) {

        cdp_insertNotificationsUsers($notification_id, $key->id);
    }

    //NOTIFICATION TO CUSTOMER

    cdp_insertNotificationsUsers($notification_id, $_POST['sender_id']);


    $db->cdp_query("SELECT * FROM cdb_senders_addresses where id_addresses= '" . $_POST["sender_address_id"] . "'");

    $sender_address_data = $db->cdp_registro();

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


    $db->cdp_query("SELECT * FROM cdb_recipients_addresses where id_addresses= '" . $_POST["recipient_address_id"] . "'");

    $recipient_address_data = $db->cdp_registro();

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


    // SAVE ADDRESS FOR Shipments



    $db->cdp_query("
                    INSERT INTO cdb_address_shipments
                    (
                        order_id,
                        order_track,
                        sender_country,
                        sender_state,
                        sender_city,
                        sender_zip_code,
                        sender_address,
                        recipient_country,
                        recipient_state,
                        recipient_city,
                        recipient_zip_code,
                        recipient_address
                    )
                    VALUES
                        (
                        :order_id,    
                        :order_track,
                        :sender_country,
                        :sender_state,
                        :sender_city,
                        :sender_zip_code,
                        :sender_address,
                        :recipient_country,
                        :recipient_state,
                        :recipient_city,
                        :recipient_zip_code,                
                        :recipient_address
                        )
                ");

    $db->bind(':order_id', $order_id);
    $db->bind(':order_track',   $order_track);
    $db->bind(':sender_country',   $final_sender_country->name);
    $db->bind(':sender_state',   $final_sender_state->name);
    $db->bind(':sender_city',   $final_sender_city->name);
    $db->bind(':sender_zip_code',   $sender_zip_code);
    $db->bind(':sender_address',   $sender_address);
    $db->bind(':recipient_country',   $final_recipient_country->name);
    $db->bind(':recipient_state',   $final_recipient_state->name);
    $db->bind(':recipient_city',   $final_recipient_city->name);
    $db->bind(':recipient_zip_code',   $recipient_zip_code);
    $db->bind(':recipient_address',   $recipient_address);

    $db->cdp_execute();

     if ($db->cdp_execute()) {
        // Éxito al insertar en la base de datos
        $order_id = $db->dbh->lastInsertId();
        $message = "Data has been saved successfully";
        $success_script = 'swal("¡Success!", "' . $message . '", "success").then(function() { window.location.href = "consolidate_view.php?id=' . $order_id . '"; });';
    } else {
        // Error al insertar en la base de datos
        $message = "There was an error processing the data";
        $error_script = 'swal("Error", "' . $message . '", "error");';
    }

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
    <title><?php echo $lang['langs_01'] ?> | <?php echo $core->site_name ?></title>
    <title><?php echo $lang['edit-courier1'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
    <?php include 'views/inc/head_scripts.php'; ?>


    <style>
        /* Estilo para separar el tfoot del thead */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        tfoot tr {
            background-color: #f2f2f2;
             margin-top: 20px; /* Agregar margen superior */
        }

        /* Estilo para el botón */
        .button-container {
            text-align: right;
            margin-bottom: 10px;
        }

        .selected-row {
          background-color: rgba(255, 0, 0, 0.1); /* Color rojo suave con opacidad */
        }

        .marked-row {
          background-color: rgba(255, 0, 0, 0.1); /* Color rojo suave con opacidad */
        }


    </style>


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

        <?php $office = $core->cdp_getOffices(); ?>
        <?php $agencyrow = $core->cdp_getBranchoffices();
        if (!function_exists('cdp_getAgencyBranchIdForUser')) { require_once(__DIR__ . '/../../helpers/querys.php'); }
        $agency_default_id = (isset($userData->userlevel) && (int)$userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '') : 0; ?>
        <?php $courierrow = $core->cdp_getCouriercom(); ?>
        <?php $statusrow = $core->cdp_getStatus(); ?>
        <?php $packrow = $core->cdp_getPack(); ?>
        <?php $payrow = $core->cdp_getPayment(); ?>
        <?php $itemrow = $core->cdp_getItem(); ?>
        <?php $moderow = $core->cdp_getShipmode(); ?>
        <?php $driverrow = $user->cdp_userAllDriver(); ?>
        <?php $delitimerow = $core->cdp_getDelitime(); ?>
        <?php $track = $core->cdp_consolidate_track(); ?>
        <?php $categories = $core->cdp_getCategories(); ?>
        <?php $code_countries = $core->cdp_getCodeCountries(); ?>
        <?php $trackDigitsx = $core->cdp_trackDigits(); ?>

        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><i class="ti-package" aria-hidden="true"></i> <?php echo $lang['langs_01']; ?></h4>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form" enctype="multipart/form-data">

                <div class="container-fluid">

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">

                                            <label for="inputcom" class="control-label col-form-label">
                                                <?php echo $lang['leftorder12'] ?>
                                            </label>

                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" name="prefix_check" id="prefix_check" value="1">
                                                            <label class="form-check-label" for="prefix_check">
                                                                <?php echo $lang['leftorder13'] ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <input type="text" class="form-control" name="code_prefix" id="code_prefix_input" value="<?php echo $order_prefix; ?>" readonly>

                                                <select class="custom-select input-sm hide" id="code_prefix_select" name="code_prefix2">
                                                    <?php foreach ($code_countries as $row) : ?>
                                                        <option value="<?php echo $row->iso3; ?>"><?php echo $row->iso3 . ' - ' . $row->name; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                        </div>



                                        <?php
                                        if ($core->code_number == 1) {
                                        ?>
                                            <div class="form-group col-md-6">
                                                <label for="inputcom" class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="order_no" id="order_no" value="<?php echo $track; ?>" onchange="cdp_validateTrackNumber(this.value, '<?php echo $trackDigitsx; ?>');">
                                                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $track; ?>">
                                                </div>
                                            </div>
                                        <?php } elseif ($core->code_number == 0) {

                                        ?>
                                            <div class="form-group col-md-6">
                                                <label for="inputcom" class="control-label col-form-label">
                                                    <?php echo $lang['add-title24'] ?>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="order_no" id="order_no" value="<?php print_r(cdp_generarCodigo('' . $core->digit_random . '')); ?>" onchange="cdp_validateTrackNumber(this.value, '<?php echo $trackDigitsx; ?>');">
                                                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $track; ?>">
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div class="form-group col-md-12">
                                            <div class="form-group">
                                                <label for="field-2" class="control-label col-form-label"><?php echo $lang['langs_021'] ?></label>
                                                <input name="seals" id="seals" class="form-control" placeholder="00-00000">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">

                                        <div class="form-group col-md-12">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['left201'] ?> </label>
                                            <div class="input-group">
                                                <?php if ($agency_default_id > 0) { ?><input type="hidden" name="agency" id="agency" value="<?php echo $agency_default_id; ?>"><?php } ?>
                                                <select class="custom-select col-12" id="<?php echo ($agency_default_id > 0) ? 'agency_select' : 'agency'; ?>" name="<?php echo ($agency_default_id > 0) ? '' : 'agency'; ?>" <?php echo ($agency_default_id > 0) ? 'disabled style="pointer-events:none;background:#e9ecef;"' : ''; ?>>
                                                    <?php foreach ($agencyrow as $row) : ?>
                                                        <option value="<?php echo (int)$row->id; ?>" <?php echo ($agency_default_id > 0 && (int)$row->id === $agency_default_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row->name_branch ?? ''); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>


                                        <?php if ($userData->userlevel == 9) { ?>

                                            <div class="form-group col-md-12">
                                                <label for="inputname" class="control-label col-form-label"><?php echo $lang['add-title14'] ?></label>
                                                <div class="input-group">
                                                    <select class="custom-select col-12" id="origin_off" name="origin_off">

                                                        <?php foreach ($office as $row) : ?>
                                                            <option value="<?php echo $row->id; ?>"><?php echo $row->name_off; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php } else if ($userData->userlevel == 2) { ?>

                                            <div class="form-group col-md-12">
                                                <label for="inputname" class="control-label col-form-label"><?php echo $lang['add-title14'] ?></label>
                                                <div class="input-group">
                                                    <input class="form-control" name="origin_off" value="<?php echo $user->name_off; ?>" readonly>
                                                </div>
                                            </div>

                                        <?php } ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Row -->


                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-information-outline" style="color:#20c997"></i><?php echo $lang['langs_010']; ?></h4>
                                    <hr>

                                    <div class="resultados_ajax_add_user_modal_sender"></div>
                                    <br>

                                    <?php
                                    if ($core->active_sms == 1) {
                                    ?>
                                        <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input" name="notify_sms_sender" id="notify_sms_sender" value="1">
                                            <b><?php echo $lang['leftorder14444']; ?> <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i></b>
                                            <span class="custom-control-indicator"></span>
                                        </label>
                                    <?php } ?>

                                    <div class="row">
                                        <div class="col-md-12 ">
                                            <label class="control-label col-form-label"><?php echo $lang['sender_search_title'] ?></label>
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select" id="sender_id" name="sender_id">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button type="button" class="btn btn-default" data-type_user="user_customer" data-toggle="modal" data-target="#myModalAddUser"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="col-md-12 ">

                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['sender_search_address_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="sender_address_id" name="sender_address_id" disabled="">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button disabled id="add_address_sender" data-type_user="user_customer" data-toggle="modal" data-target="#myModalAddUserAddresses" type="button" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-information-outline" style="color:#20c997"></i><?php echo $lang['left334']; ?></h4>
                                    <hr>
                                    <div class="resultados_ajax_add_user_modal_recipient"></div>

                                    <br>

                                    <?php
                                    if ($core->active_sms == 1) {
                                    ?>
                                        <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                            <input type="checkbox" class="custom-control-input" name="notify_sms_receiver" id="notify_sms_receiver" value="1">
                                            <b>Notify by SMS <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i></b>
                                            <span class="custom-control-indicator"></span>
                                        </label>
                                    <?php } ?>
                                    <div class="row">

                                        <div class="col-md-12">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['recipient_search_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control custom-select" id="recipient_id" name="recipient_id" disabled>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button disabled id="add_recipient" type="button" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipient" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12">

                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['recipient_search_address_title'] ?></label>

                                            <div class="row">
                                                <div class="col-md-10">
                                                    <div class="input-group">
                                                        <select class="select2 form-control" id="recipient_address_id" name="recipient_address_id" disabled="">
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="input-group-append input-sm">
                                                        <button disabled id="add_address_recipient" type="button" data-type_user="user_recipient" data-toggle="modal" data-target="#myModalAddRecipientAddresses" class="btn btn-default"><i class="fa fa-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Row -->


                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-book-multiple" style="color:#20c997"></i> <?php echo $lang['add-title13'] ?></h4>
                                    <br>
                                    <div class="row">

                                        <div class="form-group col-md-3">

                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['itemcategory'] ?></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_item_category" name="order_item_category" required style="width: 100%;">
                                                    <option value="<?php echo $s_logistics->id; ?>"><?php echo $s_logistics->name_item; ?></option>
                                                    <?php foreach ($categories as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_item; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label for="inputlname" class="control-label col-form-label"><?php echo $lang['add-title17'] ?></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_package" name="order_package" required style="width: 100%;">
                                                    <option value="<?php echo $packaging_box->id; ?>"><?php echo $packaging_box->name_pack; ?></option>
                                                    <?php foreach ($packrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_pack; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>


                                        <div class="form-group col-md-3">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title18'] ?></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_courier" name="order_courier" required style="width: 100%;">
                                                    <option value="<?php echo $courier_comp->id; ?>"><?php echo $courier_comp->name_com; ?></option>
                                                    <?php foreach ($courierrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_com; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title22'] ?></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_service_options" name="order_service_options" required style="width: 100%;">
                                                    <option value="<?php echo $ship_modes->id; ?>"><?php echo $ship_modes->ship_mode; ?></option>
                                                    <?php foreach ($moderow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->ship_mode; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3" style="display:none">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title15'] ?></i></label>
                                            <div class="input-group">
                                                <div class="input-group-append" data-target="#datetimepicker1" data-toggle="datetimepicker">
                                                    <div class="input-group-text"><i style="color:#ff0000" class="fa fa-calendar"></i></div>
                                                </div>
                                                <input type='text' class="form-control" name="order_date" id="order_date" placeholder="--<?php echo $lang['left206'] ?>--" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title16'] ?>" readonly value="<?php echo date('Y-m-d'); ?>" />
                                            </div>
                                        </div>
                                        <!--/span-->
                                        <div class="form-group col-md-3">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title20'] ?></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_deli_time" name="order_deli_time" required style="width: 100%;">
                                                    <option value="<?php echo $delivery_times->id; ?>"><?php echo $delivery_times->delitime; ?></option>
                                                    <?php foreach ($delitimerow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->delitime; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <!--/span-->

                                        <div class="form-group col-md-3">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title23'] ?> <i style="color:#ff0000" class="fas fa-donate"></i></label>
                                            <div class="input-group">
                                                <select class="select2 form-control custom-select" id="order_pay_mode" name="order_pay_mode" required="" style="width: 100%;">
                                                    <option value="<?php echo $metod_payment->id; ?>"><?php echo $metod_payment->name_pay; ?></option>
                                                    <?php foreach ($payrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_pay; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <!--/span-->

                                        <div class="form-group col-md-3">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title19'] ?> <i style="color:#ff0000" class="fas fa-shipping-fast"></i></label>
                                            <div class="input-group">
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

                                        <div class="form-group col-md-3">
                                            <label for="inputname" class="control-label col-form-label"><?php echo $lang['left208'] ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="color:#ff0000"><i class="fas fa-car"></i></span>
                                                </div>
                                                <select class="custom-select col-12" id="driver_id" name="driver_id">
                                                    <option value="0">--<?php echo $lang['left209'] ?>--</option>
                                                    <?php foreach ($driverrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->fname . ' ' . $row->lname; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <!--/row-->

                                    <!--/row-->
                                    <div class="row">

                                        <div class="col-md-4">

                                            <div>
                                                <label class="control-label" id="selectItem">
                                                    <?php echo $lang['leftorder15']; ?>
                                                </label>
                                            </div>

                                            <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple="multiple" type="file" style="display: none;" onchange="cdp_validateZiseFiles(); cdp_preview_images();" />
                                            <button type="button" id="openMultiFile" class="btn btn-default  pull-left  mb-4"> <i class='fa fa-paperclip' id="openMultiFile" style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder16']; ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-12 row" id="image_preview"></div>

                                    <div class="col-md-4 mt-4">
                                        <div id="clean_files" class="hide">
                                            <button type="button" id="clean_file_button" class="btn btn-danger ml-3"> <i class='fa fa-trash' style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder17']; ?>
                                            </button>
                                        </div>
                                    </div>


                                    <div class="row">
                                        <div class="resultados_file col-md-4 pull-right mt-4">


                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <h4 class="card-title">
                                                <i class="fas fas fa-boxes" style="color:#20c997"></i>
                                                <?php echo $lang['left212'] ?>
                                            </h4>
                                        </div>
                                        <!-- Adicionar envios al consolidado de envios-->
                                        <div class="col-md-6 text-right">
                                            <button type="button" data-toggle="modal" data-target="#myModalConsolidate" class="btn btn-outline-dark">
                                                    <span class="fa fa-search"></span>
                                                    <?php echo $lang['leftorder148']; ?>
                                            </button>
                                        </div>
                                        
                                    </div>

                                    <div><br></div>

                                    <div class="table-responsive">
                                        <table id="invoice-item-table" class="table">
  
                                            <thead class="bg-inverse text-white">
                                                <tr>
                                                    <th colspan="3"><b><?php echo $lang['ltracking'] ?></b></th>
                                                    <th colspan="2" class="text-center"><b><?php echo $lang['left215'] ?></b></th>
                                                    <th colspan="2" class="text-right"><b><?php echo $lang['left219'] ?></b></th>
                                                    <th class="text-center"></th>
                                                </tr>
                                            </thead>

                                            <tbody id="projects-tbl">

                                            </tbody>

                                            <tfoot>
                                                <tr class="card-hover">
                                                    <td colspan="2"></td>
                                                    <td colspan="2" class="text-right"><b><?php echo $lang['leftorder2021'] ?></b></td>
                                                    <td colspan="2"></td>
                                                    <td class="text-center" id="subtotal">0.00</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>



                                        <!-- Listado de impuestos-->

                                        <div  class="" id="row">
                                            <div class="col-md-6">
                                                <h4 class="card-title">
                                                    <i class="ti-briefcase" style="color:#20c997"></i>
                                                    <?php echo $lang['messageerrorform30'] ?>
                                                </h4>
                                            </div>
                                            <hr>
                                            <div class="row row-shadow input-container"> 
                                              <div class="col-sm-12 col-md-6 col-lg-2">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['left905'] ?> &nbsp; <?php echo $core->weight_p; ?> </label>
                                                    <div class="input-group">
                                                      <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm" value="<?php echo $core->value_weight; ?>" name="price_lb" id="price_lb" style="border: 1px solid red;">
                                                    </div>
                                                 </div>
                                              </div>


                                              <div class="col-sm-12 col-md-6 col-lg-2">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['leftorder21'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                    <div class="input-group">
                                                      <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" value="0" name="discount_value" id="discount_value" class="form-control form-control-sm">
                                                    </div>
                                                    
                                                    <?php
                                                    if ($core->for_symbol !== null) {
                                                    ?>
                                                        <b> <?php echo $core->for_symbol; ?> </b>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span id="discount"> 0.00</span>
                                                    
                                                 </div>
                                              </div>

                                              <div class="col-sm-12 col-md-6 col-lg-2">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['leftorder22'] ?> </label>
                                                    <div class="input-group">
                                                       <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm is-invalid" value="100" name="insured_value" id="insured_value" style="border: 1px solid darkorange;">
                                                    </div>
                                                    
                                                    <td class="text-center" id="insured_label"></td>
                                                    
                                                 </div>
                                              </div>


                                              <div class="col-sm-12 col-md-6 col-lg-2">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['leftorder24'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                    <div class="input-group">
                                                      <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm" value="<?php echo $core->insurance; ?>" name="insurance_value" id="insurance_value" style="border: 1px solid darkorange;">
                                                    </div>
                                                    
                                                    <?php
                                                    if ($core->for_symbol !== null) {
                                                    ?>
                                                        <b> <?php echo $core->for_symbol; ?> </b>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span id="insurance"> 0.00</span>
                                                    
                                                 </div>
                                              </div>


                                              <div class="col-sm-12 col-md-6 col-lg-2">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['leftorder25'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                    <div class="input-group">

                                                      <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm" value="<?php echo $core->c_tariffs; ?>" name="tariffs_value" id="tariffs_value">
                                                    </div>
                                                    
                                                    <?php
                                                    if ($core->for_symbol !== null) {
                                                    ?>
                                                        <b> <?php echo $core->for_symbol; ?> </b>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span id="total_impuesto_aduanero"> 0.00</span>
                                                    
                                                 </div>
                                              </div>


                                                <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder67'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                        <div class="input-group">
                                                            <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm" value="<?php echo $core->tax; ?>" name="tax_value" id="tax_value">
                                                        </div>
                                                        
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="impuesto"> 0.00</span>
                                                        
                                                     </div>
                                                </div>
                                            
                                                <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder19'] ?> <?php echo $lang['leftorder222221'] ?> </label>
                                                        <div class="input-group">
                                                          <input type="text" onchange="calculateFinalTotal(this);" value="<?php echo $core->declared_tax; ?>" onkeypress="return isNumberKey(event, this)" class="form-control form-control-sm" name="declared_value_tax" id="declared_value_tax">
                                                        </div>
                                                        
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="declared_value_label"> 0.00</span>
                                                        
                                                    </div>
                                                </div>

                                                <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['langs_048'] ?> </label>
                                                        <div class="input-group">

                                                           <input type="text" onkeypress="return isNumberKey(event, this)" onblur="cdp_cal_final_total();" class="form-control form-control-sm" value="0" name="reexpedicion_value" id="reexpedicion_value">
                                                        </div>
                                                        
                                                        <td class="text-right" id="reexpedicion_label"></td>
                                                        
                                                    </div>
                                                </div>


                                                <div class="col-sm-12 col-md-6 col-lg-2">
                                                    <div class="form-group">
                                                        <label for="emailAddress1"><?php echo $lang['leftorder2020'] ?></label>
                                                        <?php
                                                        if ($core->for_symbol !== null) {
                                                        ?>
                                                            <b> <?php echo $core->for_symbol; ?> </b>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span id="total_envio" class="green-bold"> 0.00</span>
                                                        
                                                     </div>
                                                </div>

                                            </div>
                                        </div>
                                           
                                    </div>

                                    <div class="row">

                                        <div class="col-md-12">
                                            <div class="form-actions">
                                                <div class="card-body">
                                                    <div class="text-right">
                                                        <input type="hidden" name="total_item_files" id="total_item_files" value="0" />
                                                        <input type="hidden" name="deleted_file_ids" id="deleted_file_ids" />

                                                        <input type="hidden" name="total_item" id="total_item" value="0" />
                                                        <input type="hidden" name="discount_input" id="discount_input" />
                                                        <input type="hidden" name="subtotal_input" id="subtotal_input" />
                                                        <input type="hidden" name="impuesto_input" id="impuesto_input" />
                                                        <input type="hidden" name="insurance_input" id="insurance_input" />
                                                        <input type="hidden" name="total_impuesto_aduanero_input" id="total_impuesto_aduanero_input" />
                                                        <input type="hidden" name="total_envio_input" id="total_envio_input" />
                                                        <input type="hidden" name="total_weight_input" id="total_weight_input" />
                                                        <button type="button" name="calculate_invoice" id="calculate_invoice" class="btn btn-info">
                                                            <i class="fas fa-calculator"></i>
                                                            <span class="ml-1"><?php echo $lang['leftorder17714']; ?></span>
                                                        </button>

                                                        <button type="button" name="calculate_invoice" id="calculate_invoice" class="btn btn-info">
                                                            <i class="fas fa-calculator"></i>
                                                            <span class="ml-1"><?php echo $lang['leftorder17714'] ?></span>
                                                        </button>
                                                        <input type="submit" name="create_invoice" id="create_invoice" class="btn btn-danger" value="<?php echo $lang['langs_084']; ?>" disabled />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <input type="hidden" name="core_meter" id="core_meter" value="<?php echo $core->meter; ?>" />
                    <input type="hidden" name="core_min_cost_tax" id="core_min_cost_tax" value="<?php echo $core->min_cost_tax; ?>" />

            </form>

            <?php include('views/modals/modal_add_user_shipment.php'); ?>
            <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
            <?php include('views/modals/modal_add_addresses_user.php'); ?>
            <?php include('views/modals/modal_add_addresses_recipient.php'); ?>
            <?php include('views/modals/modal_add_ship_consolidate.php'); ?>
        </div>


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

    <?php include('helpers/languages/translate_to_js.php'); ?>


    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>

    <?php if (isset($success_script)): ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script><?php echo $success_script; ?></script>
    <?php elseif (isset($error_script)): ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script><?php echo $error_script; ?></script>
    <?php endif; ?>

    <script src="dataJs/consolidate_add.js"></script>



</body> 

</html>