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

require_once("../loader.php");
require_once("../helpers/querys.php");
require_once("../helpers/phpmailer/class.phpmailer.php");
require_once("../helpers/phpmailer/class.smtp.php");

$user = new User;
$core = new Core;
$db = new Conexion;

$errors = array();

if (empty($_POST['email']))

  $errors['email'] = 'Enter a valid email address';

if (!$user->cdp_emailExists($_POST['email']))

  $errors['email'] = 'The email address you entered does not exist.';

if (!$user->cdp_isValidEmail($_POST['email']))

  $errors['email'] = 'The email address you entered is invalid.';



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


  $user_email = cdp_sanitize($_POST["email"]);

  $verify = cdp_verifyEmailt1xle($user_email);

  if ($verify) {
    //Generar pass aleatorio
    $possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";

    $maxlength = strlen($possible);

    $length = 8;

    $i = 0;

    $password = "";

    while ($i < $length) {
      // elige un caracter al azar de los posibles
      $char = substr($possible, mt_rand(0, $maxlength - 1), 1);

      //¿Ya hemos usado este carácter en $ contraseña?
      if (!strstr($password, $char)) {
        // no, así que está bien agregarlo al final de lo que ya tenemos ...
        $password .= $char;
        // . y aumentar el contador en uno
        $i++;
      }
    }

    $user_password = $password;

    $datos = [
      'password' => password_hash($user_password, PASSWORD_DEFAULT),
      'email' => $user_email
    ];

    $update = cdp_updatePassword5glmh($datos);

    $email_template = cdp_getEmailTemplatesdg1i4(2);

    $user_emailData = cdp_getUserForEmail($user_email);


    $body = str_replace(
      array(
        '[USERNAME]',
        '[PASSWORD]',
        '[URL]',
        '[LINK]',
        '[URL_LINK]',
        '[IP]',
        '[SITE_NAME]'
      ),
      array(
        $user_emailData->username,
        $user_password,
        $core->site_url,
        $core->site_url,
        $core->logo,
        $_SERVER['REMOTE_ADDR'],
        $core->site_name
      ),
      $email_template->body
    );

    $newbody = cdp_cleanOut($body);

    //SENDMAIL PHP

    if ($core->mailer == 'PHP') {

      /*SIGUE RECOLECTANDO DATOS PARA FUNCION MAIL*/
      $message = $newbody;
      $websiteName = $core->site_name;
      $emailAddress = $core->site_email;
      $header = "MIME-Version: 1.0\r\n";
      $header .= "Content-type: text/html; charset=iso-8859-1\r\n";
      $header .= "From: " . $websiteName . " <" . $emailAddress . ">\r\n";
      $subject = $email_template->subject;
      mail($user_email, $subject, $message, $header);
      /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/

      if ($update) {

        $messages[] = "You have successfully changed your password. Please check your email for more information!";
      } else {

        $errors['critical_error'] = "An error occurred during the registration process. Contact the administrator ...";
      }
    } elseif ($core->mailer == 'SMTP') {


      //PHPMAILER PHP

      $destinatario = $user_email;

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
      $mail->addCC($site_email, 'Password change was successful');

      //Content
      $mail->isHTML(true);
      $mail->CharSet = 'UTF-8';                                  // Set email format to HTML
      $mail->Subject = utf8_decode($email_template->subject);
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
        $messages[] = "You have successfully changed your password. Please check your email for more information!";
      } catch (Exception $e) {
        $errors['critical_error'] = "An error occurred during the registration process. Contact the administrator ...";
      }
    }
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
      <span>Success!</span>
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