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

$userData = $user->cdp_getUserData();

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("consolidate_list.php");
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
$statusrow = $core->cdp_getStatusByType(1);


$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();


if (isset($_POST['address'])) {
    $db = new Conexion;


    $id = $_GET['id'];

    $errors = array();

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


        $db->cdp_query('UPDATE cdb_consolidate SET    
                         
                status_courier =:status_courier               
                where  consolidate_id=:id      
            ');


        $db->bind(':status_courier', $_POST['status_courier']);
        $db->bind(':id', $id);


        $db->cdp_execute();


        $order_track = $row->c_prefix . $row->c_no;
        $date = date('Y-m-d', strtotime(trim($_POST["t_date"])));
        $time = date("H:i:s");
        $date = $date . ' ' . $time;


        $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,
                    t_dest,
                    t_city,
                    comments,
                    t_date,
                    status_courier,
                    office_id,
                    user_id
                    )
                VALUES
                    (
                    :order_track,
                    :country,
                    :address,
                    :comments,
                    :t_date,
                    :status_courier,
                    :office,                   
                    :user_id
                    )
            ");



        $db->bind(':order_track',  $order_track);
        $db->bind(':country', cdp_sanitize($_POST['country']));
        $db->bind(':address', cdp_sanitize($_POST['address']));
        $db->bind(':comments', cdp_sanitize($_POST['comments']));
        $db->bind(':t_date',  trim($date));
        $db->bind(':status_courier', cdp_sanitize($_POST['status_courier']));
        $db->bind(':office', cdp_sanitize($_POST['office']));
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



        $db->bind(':order_id',  $id);
        $db->bind(':order_track',  $order_track);
        $db->bind(':is_consolidate', '1');
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':action',  $lang['notification_shipment11']);
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
        $db->bind(':notification_description', $lang['notification_shipment10']);
        $db->bind(':shipping_type', '2');
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



        $fullshipment = $row->c_prefix . $row->c_no;
        $date_ship   = date("Y-m-d H:i:s a");

        $app_url = $settings->site_url . 'track.php?order_track=' . $fullshipment;
        $subject = $lang['notification_shipment9'] . ' ' . $lang['notification_shipment6'] .  $fullshipment;
        $status_courier_deliver = "" . $_POST['status_courier'] . "";



        $email_template = cdp_getEmailTemplatesdg1i4(19);

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
                $status_courier_deliver,
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

        header("location:consolidate_view.php?id=$id");
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
    <title><?php echo $lang['status-ship1011'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <link href="assets/template/assets/libs/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.min.css" rel="stylesheet">
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">
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

        <?php $code_countries = $core->cdp_getCodeCountries(); ?>


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
                                <form class="xform" id="form" name="form" method="post">
                                    <header>
                                        <h4 class="modal-title"> <b class="text-danger"><?php echo $lang['status-ship1011'] ?> </b> <b>| #<?php echo $row->c_prefix . $row->c_no; ?></b>
                                        </h4><!--  <?php echo $lang['status-ship3'] ?> <?php echo $receiver_data->country; ?> | <?php echo $receiver_data->city; ?> -->
                                        <hr>
                                    </header>


                                    <div class="row">
                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['status-ship4'] ?> </label>
                                            <div class="input-group mb-3">

                                                <select class="custom-select input-sm " name="country" id="country" required="">
                                                    <option value="0"><?php echo $lang['leftorder14track'] ?> </option>
                                                    <?php foreach ($code_countries as $row) : ?>
                                                        <option value="<?php echo $row->name; ?>"><?php echo $row->name; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['status-ship5'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" id="basic-addon1"><i class="ti-direction"></i></span>
                                                </div>
                                                <input type="text" class="form-control" id="address" name="address" placeholder="<?php echo $lang['status-ship5'] ?>" required>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="row">

                                        <div class="col-sm-12 col-md-6">
                                            <div class="form-group">
                                                <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['tools-office1'] ?></label>
                                                <select class="custom-select" name="office" id="office" list="browsee" autocomplete="off" placeholder="--Select Office--">
                                                    <option value="0"><?php echo $lang['left343'] ?></option>
                                                    <?php foreach ($office as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_off; ?></option>
                                                    <?php endforeach; ?>
                                                    <!-- </datalist> -->
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['status-ship9'] ?></label>
                                            <div class="input-group mb-3">
                                                <select class="custom-select" name="status_courier" placeholder="<?php echo $lang['langs_040'] ?>" required="required">
                                                    <!-- <datalist id="browserst"> -->
                                                    <option value="0"><?php echo $lang['langs_040'] ?></option>
                                                    <?php foreach ($statusrow as $row) : ?>
                                                        <?php if ($row->mod_style == 'Delivered') { ?>
                                                        <?php } elseif ($row->mod_style == 'Pending') { ?>
                                                        <?php } elseif ($row->mod_style == 'Rejected') { ?>
                                                        <?php } elseif ($row->mod_style == 'Pick up') { ?>
                                                        <?php } elseif ($row->mod_style == 'Picked up') { ?>
                                                        <?php } elseif ($row->mod_style == 'No Picked up') { ?>
                                                        <?php } elseif ($row->mod_style == 'Consolidate') { ?>
                                                        <?php } else { ?>
                                                            <option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>
                                                        <?php } ?>
                                                    <?php endforeach; ?>
                                                    <!-- </datalist> -->
                                                </select>
                                            </div>
                                        </div>




                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12 col-md-6">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['status-ship6'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <span class="fa fa-calendar"></span>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" name="t_date" id="t_date" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title16'] ?>" readonly value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>


                                        <div class="col-sm-12 col-md-6">
                                            <label for="message-text" class="control-label"><?php echo $lang['status-ship8'] ?></label>
                                            <textarea rows="3" class="form-control" id="message-text" name="comments"></textarea>
                                        </div>

                                    </div>

                                    </br>
                                    </br>
                                    <footer>
                                        <div class="pull-right">
                                            <a href="consolidate_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['status-ship11'] ?></a>
                                            <button class="btn btn-danger" name="dosubmit" type="submit"><?php echo $lang['status-ship10'] ?></button>
                                        </div>
                                    </footer>
                                </form>
                            </div>
                        </div>

                    </div>
                    <!-- Column -->
                </div>
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
    <?php include 'views/inc/footer.php'; ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <script src="assets/template/dist/js/app-style-switcher.js"></script>
    <script src="assets/template/assets/libs/bootstrap-switch/dist/js/bootstrap-switch.min.js"></script>
    <script src="dataJs/consolidate_tracking.js"></script>

</body>

</html>