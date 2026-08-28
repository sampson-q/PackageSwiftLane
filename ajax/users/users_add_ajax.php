<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('add_user');
require_once(__DIR__ . '/../../helpers/rbac.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../helpers/phpmailer/PHPMailer/src/Exception.php';
require '../../helpers/phpmailer/PHPMailer/src/PHPMailer.php';
require '../../helpers/phpmailer/PHPMailer/src/SMTP.php';

$user = new User;
$core = new Core;
$errors = array();

if (empty($_POST['username'])) {

    $errors['username'] = $lang['validate_field_ajax117'];

} elseif (strlen(trim($_POST['username'])) < 4 || !ctype_alnum(trim($_POST['username']))) {

    // Same rule as sign-up / add-client: min 4 chars, alphanumeric only.
    $errors['username'] = $lang['messagesform80'];

} elseif ($user->cdp_usernameExists($_POST['username'])) {

    // Was missing — allowed creating a user with a duplicate username.
    $errors['username'] = $lang['validate_field_ajax118'];
}


if (empty($_POST['branch_office']))

    $errors['branch_office'] = $lang['validate_field_ajax121'];

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

// Validate selected role
if (!empty($_POST['role'])) {
    $db = new Conexion();
    $db->cdp_query("SELECT * FROM cdb_user_roles WHERE role_id = :id AND rol_active = 1");
    $db->bind(':id', intval($_POST['role']));
    $role_row = $db->cdp_registro();
    if (!$role_row) {
        $errors['role'] = 'Invalid role selected';
    }
} else {
    $errors['role'] = 'Role is required';
}





// Validation failed → return the exact reason(s) as JSON so the client shows
// them (was emitting an HTML alert that the JS couldn't read → generic error).
if (!empty($errors)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'error',
        'message' => implode(' ', array_values($errors)),
        'errors'  => array_values($errors),
    ]);
    exit;
}

if (empty($errors)) {


    header('Content-type: application/json; charset=UTF-8');
    
        
        $response = array();

        // New accounts only with a role below the creator's own rank (superadmin: any).
        if (!cdp_canAssignRole($user, intval($_POST['role'] ?? 0))) {
            http_response_code(403);
            header('Content-type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'error', 'message' => 'No permission to create a user with that role']);
            exit;
        }

        $data = array(
            'username' => cdp_sanitize($_POST['username']),
            'branch_office' => cdp_sanitize($_POST['branch_office']),
            'email' => cdp_sanitize($_POST['email']),
            'lname' => cdp_sanitize($_POST['lname']),
            'fname' => cdp_sanitize($_POST['fname']),
            'newsletter' => intval($_POST['newsletter']),
            'notes' => cdp_sanitize($_POST['notes']),
            'phone' => cdp_sanitize($_POST['phone']),
            'gender' => cdp_sanitize($_POST['gender']),
            // Save selected role as userlevel
            'userlevel' => intval($_POST['role']),
            'active' => cdp_sanitize($_POST['active'])
        );


        if ($_POST['password'] != "") {

            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }



        $data['created'] = date("Y-m-d H:i:s");

 
         // Verifica si el rol seleccionado es 3 (Conductor)
        if ($_POST['role'] == 3) {
            $data['enrollment'] = cdp_sanitize($_POST['enrollment']);
            $data['vehiclecode'] = cdp_sanitize($_POST['vehiclecode']);
            $insertResult = cdp_insertDrivers1fcoe($data);
        } else {
            // Inserta en la otra tabla si userlevel no es 3
            $insertResult = cdp_insertUserfp40f($data);
        }
        



        if (!empty($_POST['notify']) && $_POST['notify'] == 1) {

            $email_template = cdp_getEmailTemplatesdg1i4(3);

            $body = str_replace(
                array(
                    '[USERNAME]',
                    '[PASSWORD]',
                    '[BRANCHOFFICES]',
                    '[NAME]',
                    '[SITE_NAME]',
                    '[URL]'
                ),
                array(
                    $_POST['username'],
                    $_POST['password'],
                    $_POST['branch_office'],
                    $_POST['fname'] . ' ' . $_POST['lname'],
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


                $destinatario = "" . $_POST['email'] . "";


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

                    //$messages[] = "All Email(s) have been sent successfully!";
                } catch (Exception $e) {

                    //$errors['critical_error'] = "Some of the emails alls could not be reached!";
                }
            }
        }

        // Verifica el resultado y responde
        if ($insertResult) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_add'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
            $response['error_sql'] = $GLOBALS['cdp_error'];
        }


        echo json_encode($response);
    }