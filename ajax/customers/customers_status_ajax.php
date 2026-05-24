<?php
    require_once("../../loader.php");
    require_once("../../helpers/querys.php");
    require_once("../../helpers/phpmailer/class.phpmailer.php");
    require_once("../../helpers/phpmailer/class.smtp.php");


    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $response = array();

        if (isset($_POST['id'])) {
            $userId  = cdp_sanitize($_POST['id']);
            $stat    = isset($_POST['stat']) ? cdp_sanitize($_POST['stat']) : null;
            $approve = isset($_POST['approve']) ? cdp_sanitize($_POST['approve']) : null;

            $db = new Conexion;

            // Instantiate core settings and get mailing configuration
            $core = new Core;
            $settings = cdp_getSettingsCourier();
            $site_email = $settings->email_address;
            $names_info = $settings->smtp_names;
            $smtphoste  = $settings->smtp_host;
            $smtpuser   = $settings->smtp_user;
            $smtppass   = $settings->smtp_password;
            $smtpport   = $settings->smtp_port;
            $smtpsecure = $settings->smtp_secure; // e.g., 'tls'
            
            // Handle status update if requested
            if ($stat !== null) {
                // Update the status (active/inactive)
                $statusUpdate = cdp_updateUserStatus4234sf($userId, $stat);
                if ($statusUpdate) {
                    $response['status'] = 'success';
                    $response['message'] = 'User status updated successfully.';
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Failed to update user status.';
                }
            }

            // Handle approval
            if ($approve !== null) {
                // Approve the user and activate them
                $approveUser = approveUser($userId);
                if ($approveUser) {

                    // Retrieve the approved user's information directly from the database
                    $db->cdp_query("SELECT email, fname FROM cdb_users WHERE id = :id");
                    $db->bind(':id', $userId);
                    $userInfo = $db->cdp_registro();

                    // ... [inside the approval block after approving the user and retrieving userInfo]

                    if ($userInfo) {
                        $subject = "Your account has been approved!";
                        $body = "Hello " . $userInfo->fname . ",<br/><br/>" .
                                "Your account has been successfully approved and activated. " .
                                "You can now log in and start using our services.<br/><br/>" .
                                "Thank you,<br/>" . $core->site_name;
                        
                        $mailSent = true; // assume success by default

                        if ($core->mailer == 'PHP') {
                            $websiteName  = $core->site_name;
                            $emailAddress = $core->site_email;
                            $headers  = "MIME-Version: 1.0\r\n";
                            $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
                            $headers .= "From: " . $websiteName . " <" . $emailAddress . ">\r\n";
                            $mailSent = mail($userInfo->email, $subject, $body, $headers);
                        } elseif ($core->mailer == 'SMTP') {
                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host       = $smtphoste;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $smtpuser;
                                $mail->Password   = $smtppass;
                                $mail->SMTPSecure = $smtpsecure;
                                $mail->Port       = $smtpport;
                                
                                $mail->setFrom($site_email, $names_info);
                                $mail->addAddress($userInfo->email);
                                
                                $mail->isHTML(true);
                                $mail->Subject = $subject;
                                $mail->Body    = "<html><body><p>" . $body . "</p></body></html>";
                                $mail->SMTPOptions = array(
                                    'ssl' => array(
                                        'verify_peer'       => false,
                                        'verify_peer_name'  => false,
                                        'allow_self_signed' => true
                                    )
                                );
                                $mailSent = $mail->send();
                            } catch (Exception $e) {
                                error_log("Mail error: " . $e->getMessage());
                                $mailSent = false;
                            }
                        }

                        // Regardless of email status, send success response for approval
                        $response['status'] = 'success';
                        $response['message'] = $mailSent 
                            ? 'User approved, activated, and email sent.' 
                            : 'User approved and activated, but email failed to send.';
                    }

                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Failed to approve the user.';
                }
            }

            // Send the JSON response
            echo json_encode($response);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'User ID is missing.';
            echo json_encode($response);
        }
    }
?>
