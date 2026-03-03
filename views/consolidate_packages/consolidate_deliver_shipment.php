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
require_once("helpers/phpmailer/class.phpmailer.php");
require_once("helpers/phpmailer/class.smtp.php");
require_once("ajax/notify_sms/api_sms_consolidate_service.php");

$userData = $user->cdp_getUserData();

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePackage($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("consolidate_package_list.php");
}

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($data['data']->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$row = $data['data'];

$db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row->receiver_id . "'");
$receiver_data = $db->cdp_registro();

$office = $core->cdp_getOffices();
$statusrow = $core->cdp_getStatus();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();


$driverrow = $user->cdp_userAllDriver();


if (isset($_POST['person_receives'])) {
    $db = new Conexion;
    $core = new Core;


    $id = $_GET['id'];

    $errors = array();

    if (empty($_POST['deliver_date']))

        $errors['deliver_date'] = $lang['validate_field_ajax159'];

    if (empty($_POST['person_receives']))

        $errors['person_receives'] = $lang['validate_field_ajax162'];

    if (intval($_POST['driver_id']) <= 0)

        $errors['driver_id'] =  $lang['validate_field_ajax163'];

    $status_courier = 8;
    //signature update  
    if (isset($_POST['sig-dataUrl'])) {

        $img = $_POST['sig-dataUrl']; // Your data 'data:image/png;base64,AAAFBfj42Pj4';
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        file_put_contents('doc_signs/consolidate_packages/' . $id . '.png', base64_decode($img));
    }

    if (!empty($_FILES['miarchivo']['name'])) {

        $target_dir = "files/";
        $image_name = time() . "_" . basename($_FILES["miarchivo"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
        $imageFileZise = $_FILES["miarchivo"]["size"];

        if (($imageFileType != "jpg" && $imageFileType != "png")) {
            $errors['miarchivo'] = $lang['validate_field_ajax62'];
        } else if (empty(getimagesize($_FILES['miarchivo']['tmp_name']))) { //1048576 byte=1MB
            $errors['miarchivo'] = $lang['validate_field_ajax62'];
        } else {

            move_uploaded_file($_FILES["miarchivo"]["tmp_name"], $target_file);
            $photo_delivered = 'files/' . $image_name;
        }
    }


    if (empty($errors)) {


        $db->cdp_query('UPDATE cdb_consolidate_packages SET    
                         
                status_courier =:status_courier,
                person_receives=:person_receives,
                photo_delivered=:photo_delivered
                where  consolidate_id=:id      
            ');


        $db->bind(':status_courier', $status_courier);
        $db->bind(':person_receives', $_POST['person_receives']);
        $db->bind(':id', $id);
        $db->bind(':photo_delivered', $photo_delivered);
        $db->cdp_execute();


        $order_track = $row->c_prefix . $row->c_no;
        $date = date('Y-m-d', strtotime(trim($_POST["deliver_date"])));
        $time = date("H:i:s");
        $date = $date . ' ' . $time;


        $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,
                    t_date,
                    status_courier,
                    user_id
                    )
                VALUES
                    (
                    :order_track,
                    :t_date,
                    :status_courier,                   
                    :user_id
                    )
            ");



        $db->bind(':order_track',  $order_track);
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':t_date',  trim($date));
        $db->bind(':status_courier', $status_courier);

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



        $db->bind(':order_id',  $id);
        $db->bind(':order_track',  $order_track);
        $db->bind(':is_consolidate', '1');
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':action',  $lang['notification_shipment12']);
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
        $db->bind(':order_id',  $_GET['id']);
        $db->bind(':notification_description',  $lang['notification_shipment13']);
        $db->bind(':shipping_type', '5');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO DRIVER

        cdp_insertNotificationsUsers($notification_id, $row->driver_id);


        //NOTIFICATION TO ADMIN AND EMPLOYEES

        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }

        //NOTIFICATION TO CUSTOMER

        cdp_insertNotificationsUsers($notification_id, $row->sender_id);

        $sql = "SELECT * FROM cdb_settings";

        $db->cdp_query($sql);

        $db->cdp_execute();

        $settings = $db->cdp_registro();

        $site_email = $settings->site_email;
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

        // NOTIFY SMS CLICKSEND API
        $templatessender = 17;
        $templatesreceiver = 18;


        $fullshipment = $row->c_prefix . $row->c_no;
        $date_ship   = date("Y-m-d H:i:s a");

        $app_url = $settings->site_url . 'track.php?order_track=' . $fullshipment;
        $subject = $lang['notification_shipment14'] . $lang['notification_shipment3'] . $fullshipment;
        $status_courier_deliver =  $lang['filter73'];

        $email_template = cdp_getEmailTemplatesdg1i4(14);

        $body = str_replace(
            array(
                '[NAME]',
                '[TRACKING]',
                '[DELIVERY_TIME]',
                '[COURIER]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]',
                '[URL_SHIP]'
            ),
            array(
                $sender_data->fname . ' ' . $sender_data->lname,
                $fullshipment,
                $date_ship,
                $status_courier_deliver,
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
            $mail->addCC($site_email,  $lang['notification_shipment14']);
            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
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

        

        // Obtener el estado de las casillas de verificación
        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        // Generar cuerpo del SMS para el remitente
        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $status_courier_deliver, $app_url, $templatessender);
            // Llamar a la función para enviar la notificación SMS al remitente
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
            // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
        }

        // Generar cuerpo del SMS para el receptor
        try {
            $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $status_courier_deliver, $app_url, $templatesreceiver);
            // Llamar a la función para enviar la notificación SMS al receptor
            sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for receiver: ' . $e->getMessage());
            // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
        }

        header("location:consolidate_package_view.php?id=$id");
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
    <title><?php echo $lang['deliver-ship1'] ?> | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">

    <style type="text/css">
        .canvas-bordered {
          border: 1px solid #e9ecef;
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


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">


            <div class="container-fluid">


                <div class="row justify-content-center">
                    <!-- Column -->
                    <div class="col-lg-12 col-xlg-12 col-md-12">
                        <div class="card">

                            <div class="card-body">
                                <!-- <div id="loader" style="display:none"></div> -->
                                <div id="resultados_ajax">
                                    <?php if (!empty($errors)) { ?>
                                        <div class="alert alert-danger" id="success-alert">
                                            <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
                                                <?php echo $lang['message_ajax_error2']; ?>
                                            <ul class="error">
                                                <?php
                                                foreach ($errors as $error) { ?>
                                                    <li>
                                                        <i class="icon-double-angle-right"></i>
                                                        <?php
                                                        echo $error;

                                                        ?>

                                                    </li>
                                                <?php
                                                }
                                                ?>


                                            </ul>
                                            </p>
                                        </div>
                                    <?php
                                    } ?>

                                </div>
                                <form name="myForm" class="xform" enctype="multipart/form-data" id="deliver_form" method="POST">
                                    <header>
                                        <h4 class="modal-title"> <b class="text-danger"><?php echo $lang['deliver-ship1'] ?> </b> <b>| <?php echo $row->c_prefix . $row->c_no; ?></b>
                                        </h4><!--  <?php echo $lang['status-ship3'] ?> <?php echo $receiver_data->country; ?> | <?php echo $receiver_data->city; ?> -->
                                        <hr>
                                    </header>


                                    <div class="row">
                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['deliver-ship4'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <span class="fa fa-calendar"></span>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" name="deliver_date" id="deliver_date" value="<?php echo date('Y-m-d'); ?>" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title16'] ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="inputname" class="control-label col-form-label"><?php echo $lang['deliver-ship12'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select col-12" id="driver_id" name="driver_id" required>
                                                    <option value=""><?php echo $lang['deliver-ship13'] ?></option>
                                                    <?php foreach ($driverrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php if ($row->id == $_SESSION['userid']) {
                                                                                                    echo 'selected';
                                                                                                } ?>><?php echo $row->fname . ' ' . $row->lname; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-12 col-md-12">
                                            <label for="message-text" class="control-label"><?php echo $lang['deliver-ship6'] ?></label>
                                            <input type="text" name="person_receives" id="person_receives" class="form-control" placeholder="<?php echo $lang['deliver-ship6'] ?>" required="">
                                        </div>
                                    </div>

                                    <br>

                                    <div class="row">
                                      <div class="col-sm-12 col-md-6">
                                        <label for="inputcontact" class="control-label col-form-label">
                                          <i align='left'><img src='assets/images/alert/sign_icon.png' width='32' /></i>
                                          &nbsp;&nbsp;&nbsp;<?php echo $lang['deliver-ship14'] ?>
                                        </label>
                                        <div class="row">
                                          <button type="button" class="btn btn-info ml-4" data-toggle="modal" data-target=".bs-example-modal-lg"><?php echo $lang['deliver-ship15'] ?></button>
                                          <div class="modal fade bs-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true" style="display: none;">
                                            <div class="modal-dialog modal-lg">
                                              <div class="modal-content">
                                                <div class="modal-header">
                                                  <h4 class="modal-title" id="myLargeModalLabel"><?php echo $lang['deliver-ship14'] ?></h4>
                                                  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                </div>
                                                <div class="modal-body">
                                                  <p>
                                                    <div class="table-responsive">
                                                        <canvas id="sig-canvas" class="canvas-bordered" width="760" height="360">
                                                          Get a better browser.
                                                        </canvas>
                                                    </div>
                                                  </p>
                                                  <span class="btn btn-danger" id="sig-clearBtn"><?php echo $lang['deliver-ship16'] ?></span>
                                                  <button type="button" id="sig-submitBtn" class="btn btn-default waves-effect text-left" data-dismiss="modal"><?php echo $lang['deliver-ship15'] ?></button>
                                                </div>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="row" style="display:none">
                                          <div class="col-md-8">
                                            <textarea id="sig-dataUrl" name="sig-dataUrl" class="form-control" rows="5">Data URL for your signature will go here!</textarea>
                                          </div>
                                        </div>
                                      </div>

                                      <div class="col-sm-12 col-md-6">
                                          <div class="form-group">
                                            <label class="subtitle"><?php echo $lang['left1064'] ?></label>
                                            <div class="custom-file">
                                              <input type="file" class="custom-file-input" name="miarchivo" id="miarchivo" accept="image/*" capture="camera" onchange="previewImage(event)">
                                              <label class="custom-file-label" for="miarchivo">Choose file</label>
                                            </div>
                                            <div id="image-preview" style="margin-top: 15px;">
                                              <img id="image-preview-img" src="" alt="Image Preview" style="display: none; max-width: 100%; height: auto;">
                                            </div>
                                          </div>
                                        </div>
                                    </div>

                                    <div class="row">

                                        <?php
                                        if ($core->active_sms == 1) {
                                        ?>
                                            <div class="col-sm-12 col-md-5 offset-1 mt-4">
                                                <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                                    <input type="checkbox" class="custom-control-input" name="notify_sms_sender" id="notify_sms_sender" value="1">
                                                    <b><?php echo $lang['leftorder144431'] ?> &nbsp; <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i></b>
                                                    <span class="custom-control-indicator"></span>
                                                </label>

                                                <label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
                                                    <input type="checkbox" class="custom-control-input" name="notify_sms_receiver" id="notify_sms_receiver" value="1">
                                                    <b><?php echo $lang['leftorder144443'] ?> <i class="fa fa-envelope" style="font-size: 22px; color:#07bc4c;"></i></b>
                                                    <span class="custom-control-indicator"></span>
                                                </label>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    </br>
                                    </br>
                                    </br>
                                    <footer>
                                        <div class="pull-right">

                                            <a href="index.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['status-ship11'] ?></a>

                                            <button class="btn btn-danger" type="submit" id="sig-submitBtn"><?php echo $lang['deliver-ship10'] ?></button>
                                        </div>
                                    </footer>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
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
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>
    <script src="dataJs/consolidate_deliver_shipment.js"></script>


</body>

</html>