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
require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');
require_csrf();

$user = new User;
$core = new Core;
$errors = array();
$userData = $user->cdp_getUserData();

if (empty($_POST['username']))

    $errors['username'] = $lang['validate_field_ajax117'];

if ($value = $user->cdp_usernameExists($_POST['username']))

    if ($value == 1)

        $errors['username'] = $lang['validate_field_ajax118'];

if ($value == 2)

    $errors['username'] = $lang['validate_field_ajax119'];

if ($value == 3)
    $errors['username'] = $lang['validate_field_ajax120'];


if (empty($_POST['fname']))

    $errors['fname'] = $lang['validate_field_ajax122'];
if (empty($_POST['lname']))

    $errors['lname'] = $lang['validate_field_ajax123'];

if (empty($_POST['password']))

    $errors['password'] = $lang['validate_field_ajax124'];

if (empty($_POST['email']))

    $errors['email'] = $lang['validate_field_ajax125'];

if ($user->cdp_emailExists($_POST['email']))

    $errors[] = $lang['validate_field_ajax126'];

if (!$user->cdp_isValidEmail($_POST['email']))

    $errors[] = $lang['validate_field_ajax127'];

if (empty($_POST['phone']))

    $errors['phone'] = $lang['validate_field_ajax128'];




if (empty($errors)) {


    $settings = cdp_getSettingsCourier();
    $prefixlk = $settings->prefix_locker;



     header('Content-type: application/json; charset=UTF-8');
    
    $response = array();

    $datos = array(
        'username' => cdp_sanitize($_POST['username']),
        'locker' => cdp_sanitize($prefixlk. ' ' .$_POST['locker']),
        'userlevel' => 1,
        'email' => cdp_sanitize($_POST['email']),
        'fname' => cdp_sanitize($_POST['fname']),
        'lname' => cdp_sanitize($_POST['lname']),
        'notes' => cdp_sanitize($_POST['notes']),
        'phone' => cdp_sanitize($_POST['phone']),
        'gender' => cdp_sanitize($_POST['gender']),
        'newsletter' => intval($_POST['newsletter']),
        'active' => cdp_sanitize($_POST['active']),
        'document_type' => cdp_sanitize($_POST['document_type']),
        'document_number' => cdp_sanitize($_POST['document_number'])
    );

    if ($_POST['password'] != "") {
        $datos['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }


    $datos['created'] = date("Y-m-d H:i:s");

    $ctx = cdp_getAgencyContext();
    if ($ctx['is_restricted']) {
        if ($ctx['agency_id'] === null) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'error', 'message' => 'Su usuario de agencia no tiene una agencia asociada.']);
            exit;
        }
        $datos['agency_id'] = (int)$ctx['agency_id'];
    }

    $customer_id = cdp_insertCustomer($datos);

    if ($customer_id !== null && isset($_POST["total_address"])) {

        for ($count = 0; $count < $_POST["total_address"]; $count++) {

            $dataAddresses = array(
                'user_id' =>  $customer_id,
                'address' =>  cdp_sanitize($_POST["address"][$count]),
                'country' =>  cdp_sanitize($_POST["country"][$count]),
                'city' =>  cdp_sanitize($_POST["city"][$count]),
                'state' =>  cdp_sanitize($_POST["state"][$count]),
                'postal' =>  cdp_sanitize($_POST["postal"][$count])
            );

            cdp_insertAddressCustomer($dataAddresses); 
        }


        if ($customer_id) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_add'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
        }


        echo json_encode($response);
    }

    if (isset($_POST['notify']) && $_POST['notify'] == 1) {
        $email_template = cdp_getEmailTemplatesdg1i4(3);

        $body = str_replace(
            array(
                '[USERNAME]',
                '[PASSWORD]',
                '[LOCKER]',
                '[VIRTUAL_LOCKER]',
                '[NAME]',
                '[SITE_NAME]',
                '[URL]'
            ),
            array(
                cdp_sanitize($_POST['username']),
                cdp_sanitize($_POST['password']),
                cdp_sanitize($_POST['locker']),
                $core->locker_address,
                cdp_sanitize($_POST['fname']) . ' ' . cdp_sanitize($_POST['lname']),
                $core->site_name,
                $core->site_url
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
            mail($_POST['email'], $subject, $message, $header);
            /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/
        } elseif ($core->mailer == 'SMTP') {


            //PHPMAILER PHP


            $destinatario = "" . cdp_sanitize($_POST['email']) . "";


            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = true;
            $mail->Port = $core->smtp_port;
            $mail->IsHTML(true);
            $mail->CharSet = 'UTF-8';

            // Datos de la cuenta de correo utilizada para enviar vía SMTP
            $mail->Host = $core->smtp_host;       // Dominio alternativo brindado en el email de alta
            $mail->Username = $core->smtp_user;    // Mi cuenta de correo
            $mail->Password = $core->smtp_password;    //Mi contraseña


            $mail->From = $core->site_email; // Email desde donde envío el correo.
            $mail->FromName = $core->smtp_names;
            $mail->AddAddress($destinatario); // Esta es la dirección a donde enviamos los datos del formulario

            $mail->Subject = $email_template->subject; // Este es el titulo del email.
            $mail->Body = "<html> 
                  
                  <body> 
                  
                  <p>{$newbody}</p>
                  
                  </body> 
                  
                  </html>
                  
                  <br />"; // Texto del email en formato HTML
            // FIN - VALORES A MODIFICAR //

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $estadoEnvio = $mail->Send();
        }
    }
}


if (!empty($errors)) {
?>
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
}

if (isset($messages)) {

?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <p><span class="icon-info-sign"></span>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

<?php
}
?>