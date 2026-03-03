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



require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_user_list');

require_once("../../helpers/querys.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../helpers/phpmailer/PHPMailer/src/Exception.php';
require '../../helpers/phpmailer/PHPMailer/src/PHPMailer.php';
require '../../helpers/phpmailer/PHPMailer/src/SMTP.php';

$user = new User;
$core = new Core;
$db = new Conexion;

$errors = array();


if (empty($_POST['subject']))
  $errors['subject'] = $lang['validate_field_ajax130'];

if (empty($_POST['body']))
  $errors['body'] = $lang['validate_field_ajax131'];


if (empty($errors)) {

  $to = cdp_sanitize($_POST['recipient']);
  $subject = cdp_sanitize($_POST['subject']);
  $body = cdp_cleanOut($_POST['body']);

  switch ($to) {
    case "1":

      $db->cdp_query("SELECT email, CONCAT(fname, '', lname) as name FROM cdb_users  WHERE id != 1");

      $db->cdp_execute();

      $userrow = $db->cdp_registros();

      $replacements = array();


      $array = array();

      foreach ($userrow as $cols) {
        $replacements[$cols->email] = array(
          '[NAME]' => $cols->name,
          '[SITE_NAME]' => $core->site_name,
          '[URL_LINK]' => $core->logo,
          '[URL]' => $core->site_url
        );
      }

      $body = str_replace(
        array(
          '[NAME]',
          '[URL]',
          '[URL_LINK]',
          '[SITE_NAME]'
        ),
        array(
          $userrow->name,
          $core->site_url,
          $core->logo,
          $core->site_name
        ),
        $body
      );

      $newbody = cdp_cleanOut($body);



      foreach ($userrow as $user) {

        array_push($array, $user->email);
      }


      //SENDMAIL PHP

      if ($core->mailer == 'PHP') {

        /*SIGUE RECOLECTANDO DATOS PARA FUNCION MAIL*/
        $message = $newbody;
        $websiteName = $core->site_name;
        $emailAddress = $core->email_address;
        $header = "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $header .= "From: " . $websiteName . " <" . $emailAddress . ">\r\n";
        $subject = $subject;
        $mail = mail(cdp_email_users_notifications($array), $subject, $message, $header);
        /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/
      } elseif ($core->mailer == 'SMTP') {


        //PHPMAILER PHP

        $destinatario = cdp_email_users_notifications($array);


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

          //Content
          $mail->isHTML(true);                                  // Set email format to HTML
          $mail->CharSet = 'UTF-8';
          $mail->Subject = $subject;
          $mail->Body = "<html> 
                    
                    <body> 
                    
                    <p>{$newbody}</p>
                    
                    </body> 
                    
                    </html>
                    
                    <br />"; // Texto del email en formato HTML
          // FIN - VALORES A MODIFICAR //

          $mail->send();

          $messages[] = $lang['message_ajax_success_send_email3'];
        } catch (Exception $e) {

          $errors['critical_error'] =  $lang['message_ajax_error_send_email3'];
        }
      }

      break;

    case "2":

      $db->cdp_query("SELECT email, CONCAT(fname, '', lname) as name FROM cdb_users WHERE newsletter= '1' AND id != 1");

      $db->cdp_execute();

      $userrow = $db->cdp_registros();

      $replacements = array();
      $array = array();


      foreach ($userrow as $cols) {

        $replacements[$cols->email] = array(
          '[NAME]' => $cols->name,
          '[SITE_NAME]' => $core->site_name,
          '[URL_LINK]' => $core->logo,
          '[URL]' => $core->site_url
        );
      }

      $body = str_replace(
        array(
          '[NAME]',
          '[URL]',
          '[URL_LINK]',
          '[SITE_NAME]'
        ),
        array(
          $userrow->name,
          $core->site_url,
          $core->logo,
          $core->site_name
        ),
        $body
      );

      $newbody = cdp_cleanOut($body);

      foreach ($userrow as $user) {

        array_push($array, $user->email);
      }

      //SENDMAIL PHP

      if ($core->mailer == 'PHP') {


        /*SIGUE RECOLECTANDO DATOS PARA FUNCION MAIL*/
        $message = $newbody;
        $websiteName = $core->site_name;
        $emailAddress = $core->email_address;
        $header = "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $header .= "From: " . $websiteName . " <" . $emailAddress . ">\r\n";
        $subject = $subject;
        $mail = mail(cdp_email_users_notifications($array), $subject, $message, $header);
        /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/
      } elseif ($core->mailer == 'SMTP') {


        //PHPMAILER PHP

        $destinatario = cdp_email_users_notifications($array);


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

          //Content
          $mail->isHTML(true);                                  // Set email format to HTML
          $mail->Subject = $subject;
          $mail->Body = "<html> 
                    
                    <body> 
                    
                    <p>{$newbody}</p>
                    
                    </body> 
                    
                    </html>
                    
                    <br />"; // Texto del email en formato HTML
          // FIN - VALORES A MODIFICAR //

          $mail->send();

          $messages[] = $lang['message_ajax_success_send_email'];
        } catch (Exception $e) {

          $errors['critical_error'] =  $lang['message_ajax_error_send_email2'];
        }
      }

      break;

    default:

      $db->cdp_query("SELECT email, CONCAT(fname, '', lname) as name FROM cdb_users WHERE email 
          LIKE '%" . trim($to) . "%'");

      $db->cdp_execute();

      $userrow = $db->cdp_registro();


      $body = str_replace(
        array(
          '[NAME]',
          '[URL]',
          '[URL_LINK]',
          '[SITE_NAME]'
        ),
        array(
          $userrow->name,
          $core->site_url,
          $core->logo,
          $core->site_name
        ),
        $body
      );

      $newbody = cdp_cleanOut($body);

      //SENDMAIL PHP

      if ($core->mailer == 'PHP') {

        /*SIGUE RECOLECTANDO DATOS PARA FUNCION MAIL*/
        $message = $newbody;
        $websiteName = $core->site_name;
        $emailAddress = $core->email_address;
        $header = "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $header .= "From: " . $websiteName . " <" . $emailAddress . ">\r\n";
        $subject = $subject;
        $mail = mail($userrow->email, $subject, $message, $header);
        /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/
      } elseif ($core->mailer == 'SMTP') {


        //PHPMAILER PHP
        $destinatario = $userrow->email;

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

          //Content
          $mail->isHTML(true);                                  // Set email format to HTML
          $mail->Subject = $subject;
          $mail->Body = "<html> 
                    
                    <body> 
                    
                    <p>{$newbody}</p>
                    
                    </body> 
                    
                    </html>
                    
                    <br />"; // Texto del email en formato HTML
          // FIN - VALORES A MODIFICAR //

          $mail->send();

          $messages[] =  $lang['message_ajax_success_send_email'];
        } catch (Exception $e) {

          $errors['critical_error'] =  $lang['message_ajax_error_send_email'];
        }
      }

      break;
  }
}



if (!empty($errors)) {
?>
  <div class="alert alert-danger" id="success-alert">
    <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
      <?php echo $lang['message_ajax_error2']; ?> <br>
      <?php
      foreach ($errors as $error) { ?>

        <?php
        echo $error . "<br>";

        ?>

      <?php

      }
      ?>


    </p>
  </div>



<?php
}

if (isset($messages)) {

?>

  <div class="alert alert-success" id="success-alert">
    <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
      <?php
      foreach ($messages as $message) {
        echo $message;
      }
      ?>
    </p>
  </div>
<?php
}
?>