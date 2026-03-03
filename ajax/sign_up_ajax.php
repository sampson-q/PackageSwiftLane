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

$error = "";

// Verificar campos obligatorios
$requiredFields = array('terms', 'country', 'state', 'city', 'address', 'postal', 'username', 'email', 'phone', 'fname', 'lname', 'document_number', 'document_type');
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        $error = 'Please enter ' . str_replace('_', ' ', $field);
    }
}


// Verificar disponibilidad de nombre de usuario
$usernameExists = $user->cdp_usernameExists($_POST['username']);
if ($usernameExists) {
    $error = $lang['messagesform81'];

} elseif (strlen($_POST['username']) < 4 || !ctype_alnum($_POST['username'])) {
    $error = $lang['messagesform80'];
}

// Verificar disponibilidad de nuemuro de cedula
$cedulaExists = $user->cdp_ccnumberExists($_POST['document_number']);
if ($cedulaExists) {
    $error = $lang['messagesform82'];
}

// Validar formato de correo electrónico
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $error = $lang['messagesform79'];
}


// Verificar disponibilidad de correo electrónico
$emailExists = $user->cdp_emailExists($_POST['email']);
if ($emailExists) {
    $error = $lang['messagesform78'];
}

if (!$user->cdp_isValidEmail($_POST['email']))

    $error = $lang['messagesform77'];


if (empty($_POST['pass']))

    $error = $lang['messagesform76'];

// Validar longitud de contraseña
if (strlen($_POST['pass']) < 6) {
    $error = $lang['messagesform75'];
}

// Verificar si las contraseñas coinciden
if ($_POST['pass'] != $_POST['pass2']) {
    $error = $lang['messagesform74'];
}


if (empty($error)) {

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
    $prefixlk = $settings->prefix_locker;

    // Preparar datos para inserción
    $datos = array(
        'username' => cdp_sanitize($_POST['username']),
        'email' => cdp_sanitize($_POST['email']),
        'lname' => cdp_sanitize($_POST['lname']),
        'fname' => cdp_sanitize($_POST['fname']),
        'document_number' => cdp_sanitize($_POST['document_number']),
        'document_type' => cdp_sanitize($_POST['document_type']),
        'locker' => cdp_sanitize($prefixlk . ' ' . $_POST['locker']),
        'phone' => cdp_sanitize($_POST['phone']),
        'userlevel' => 1,
        'active' => 1,

    );


    // Hash de la contraseña
    if (!empty($_POST['pass'])) {
        $datos['password'] = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    }

    // Añadir términos si está establecido
    $datos['terms'] = isset($_POST['terms']) ? $_POST['terms'] : '';


    // Fecha de creación
    $datos['created'] = date("Y-m-d H:i:s");

    // Insertar datos en la base de datos
    $db->cdp_query('INSERT INTO cdb_users
        (
            username,
            password,
            locker,
            userlevel,
            email,
            fname,
            lname,
            document_number,
            document_type,
            created,
            phone,
            active,
            terms
            
        )

        VALUES (
            :username,
            :password,
            :locker,
            :userlevel,
            :email,
            :fname,
            :lname,
            :document_number,
            :document_type,
            :created,
            :phone,
            :active,
            :terms
        )');


    $db->bind(':username', $datos['username']);
    $db->bind(':password', $datos['password']);
    $db->bind(':locker', $datos['locker']);
    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':document_number', $datos['document_number']);
    $db->bind(':document_type', $datos['document_type']);
    $db->bind(':created', $datos['created']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':active', $datos['active']);
    $db->bind(':terms', $datos['terms']);
    
    

    $insert = $db->cdp_execute();

    $user_created_id = $db->dbh->lastInsertId();

    if ($user_created_id !== null) {
        $dataAddresses = array(
            'user_id' =>  $user_created_id,
            'address' =>  cdp_sanitize($_POST["address"]),
            'country' =>  cdp_sanitize($_POST["country"]),
            'city' =>  cdp_sanitize($_POST["city"]),
            'state' =>  cdp_sanitize($_POST["state"]),
            'postal' =>  cdp_sanitize($_POST["postal"])
        );

        cdp_insertAddressCustomer($dataAddresses);
    }

    if ($insert) {


        $_SESSION['userid'] = $user_created_id;
        $_SESSION['username'] =  $datos['username'];
        $_SESSION['email'] = $datos['email'];
        $_SESSION['name'] = $datos['fname'] . ' ' . $datos['lname'];
        $_SESSION['userlevel'] = $datos['userlevel'];
        $_SESSION['last'] = '';

        $db->cdp_query('UPDATE cdb_users SET  lastlogin=:lastlogin, lastip=:lastip where username=:user');

        $db->bind(':lastlogin', date("Y-m-d H:i:s"));
        $db->bind(':lastip', trim($_SERVER['REMOTE_ADDR']));
        $db->bind(':user', $datos['username']);

        $db->cdp_execute();

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

        $db->bind(':order_id',  $user_created_id);
        $db->bind(':user_id',  $user_created_id);
        $db->bind(':notification_description', $lang['messagesform73']);
        $db->bind(':shipping_type', '0');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();

        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO ADMIN AND EMPLOYEES

        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }



        $email_template = cdp_getEmailTemplatesdg1i4(7);

        $body = str_replace(
            array(
                '[NAME]',
                '[USERNAME]',
                '[PASSWORD]',
                '[LOCKER]',
                '[VIRTUAL_LOCKER]',
                '[CCOUNTRY]',
                '[CCITY]',
                '[CPOSTAL]',
                '[CPHONE]',
                '[EMAIL]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]'
            ),
            array(
                $_POST['fname'] . ' ' . $_POST['lname'],
                $_POST['username'],
                $_POST['pass'],
                $_POST['locker'],
                $core->locker_address,
                $core->c_country,
                $core->c_city,
                $core->c_postal,
                $core->c_phone,
                $_POST['email'],
                $core->site_url,
                $core->logo,
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
            $header .= "Bcc: " . $core->email_address . "\r\n";
            $subject = $email_template->subject;
            mail($_POST['email'], $subject, $message, $header);


            /*FINALIZA RECOLECTANDO DATOS PARA FUNCION MAIL*/
        } elseif ($core->mailer == 'SMTP') {

            //PHPMAILER PHP
            $destinatario = "" . $_POST['email'] . "";

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
            $mail->addCC($site_email, 'New customer registration');

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $email_template->subject;
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


        $messages[] = $lang['messagesform71']. " " . $prefixlk . ' ' . $_POST['locker'] . " ".$lang['messagesform72'];
    } else {
        $error['critical_error'] = "An error occurred during the registration process. Contact the administrator ...";
    }
}





if (!empty($error)) {
    echo json_encode([
        'success' => false,
        'errors' => $error
    ]);
} else {
    echo json_encode([
        'success' => true,
        'messages' => $messages,
    ]);
}
