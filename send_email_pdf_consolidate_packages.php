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



require_once("loader.php");
require_once("helpers/querys.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'helpers/phpmailer/PHPMailer/src/Exception.php';
require 'helpers/phpmailer/PHPMailer/src/PHPMailer.php';
require 'helpers/phpmailer/PHPMailer/src/SMTP.php';



session_start();
// get the HTML
ob_start();
if (!isset($_SESSION['userid'])) {

    header("location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePackage($_GET['id']);
}




$row = $data['data'];

$core = new Core;
$db = new Conexion;

$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $row->status_courier . "'");
$status_courier = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row->receiver_id . "'");
$receiver_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->order_courier . "'");
$courier_com = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $row->order_pay_mode . "'");
$met_payment = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row->order_service_options . "'");
$order_service_options = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $row->order_package . "'");
$packaging = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $row->order_deli_time . "'");
$delivery_time = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_branchoffices where id= '" . $row->agency . "'");
$branchoffices = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_offices where id= '" . $row->origin_off . "'");
$offices = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row->c_prefix . $row->c_no . "'");
$address_order = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_consolidate_detail WHERE consolidate_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$dias_ = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
$meses_ = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec');


$fecha = date("Y-m-d :h:i A", strtotime($row->order_datetime));

//SENDMAIL PHP

if ($core->mailer == 'PHP') {


    require_once(dirname(__FILE__) . '/helpers/pdf.php');

    include(dirname(__FILE__) . '/pdf/documentos/html/consolidate_print.php');
    $content = ob_get_clean();

    try {
        $filename = $row->c_prefix . $row->c_no . '.pdf';
        $pdfdoc = deprixapro_render_html_to_pdf($content, $filename, ['format' => 'LETTER', 'margins' => [0, 0, 0, 0]]);

        $to = strip_tags($_REQUEST['sendto']);
        $from = $core->site_email;
        $subject = strip_tags($_REQUEST['subject']);
        $message = strip_tags($_REQUEST['message']);
        $separator = md5(time());
        $eol = PHP_EOL;
        $attachment = chunk_split(base64_encode($pdfdoc));

        $headers = "From: " . $from . $eol;
        $headers .= "MIME-Version: 1.0" . $eol;
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol . $eol;


        $body = "";
        $body .= "Content-Transfer-Encoding: 7bit" . $eol;
        $body .= "This is a MIME encoded message." . $eol; //had one more .$eol


        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
        $body .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body .= $message . $eol;


        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
        $body .= "Content-Transfer-Encoding: base64" . $eol;
        $body .= "Content-Disposition: attachment" . $eol . $eol;
        $body .= $attachment . $eol;
        $body .= "--" . $separator . "--";


        if (mail($to, $subject, $body, $headers)) {
            echo "<div class='alert alert-success'>Message has been sent successfully!</div>";
        } else {
            echo "<div class='alert alert-warning'>Mensaje no enviado!!!</div>";
        }
    } catch (Throwable $e) {
        echo $e->getMessage();
        exit;
    }
} elseif ($core->mailer == 'SMTP') {


    //PHPMAILER PHP

    require_once(dirname(__FILE__) . '/helpers/pdf.php');

    include(dirname(__FILE__) . '/pdf/documentos/html/consolidate_print.php');
    $content = ob_get_clean();

    try {
        $filename = $row->c_prefix . $row->c_no . '.pdf';
        $emailAttachment = deprixapro_render_html_to_pdf($content, $filename, ['format' => 'LETTER', 'margins' => [0, 0, 0, 0]]);
        // send the PDF

        $destinatario = strip_tags($_REQUEST['sendto']);


        $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
        try {
            //Server settings

            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $core->smtp_host;                       // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $core->smtp_user;                   // SMTP username
            $mail->Password = $core->smtp_password;               // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($core->email_address, $core->site_name);
            $mail->addAddress($destinatario);     // Add a recipient
            $menssage = strip_tags($_REQUEST['message']);

            //Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';                                 // Set email format to HTML
            $mail->Subject = strip_tags($_REQUEST['subject']);   // Este es el titulo del email.
            $mail->Body = $menssage;
            $mail->AltBody = $menssage;
            $mail->AddStringAttachment($emailAttachment, '' . $filename . '', 'base64', 'application/pdf'); // attachment

            $mail->send();

            echo "<div class='alert alert-success'>Message has been sent successfully!</div>";
        } catch (Exception $e) {

            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
        // send the PDF


    } catch (Throwable $e) {
        echo $e->getMessage();
        exit;
    }
}
