<?php
    require_once("../../loader.php");
    require_once("../../helpers/querys.php");
    require_once("../../helpers/phpmailer/class.phpmailer.php");
    require_once("../../helpers/phpmailer/class.smtp.php");
    require_once("../../helpers/ajax_guard.php");
    require_login();
    require_permission('approve_client'); // approve/activate/deactivate a client — was login-only


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
                cdp_activityLog([
                    'module'       => 'customers',
                    'verb'         => 'status',
                    'entity_type'  => 'user',
                    'entity_id'    => (int) $userId,
                    'summary'      => 'Set customer #' . (int) $userId . ' to ' . ((int) $stat === 1 ? 'Active' : 'Inactive'),
                    'changes'      => ['active' => ['from' => '', 'to' => (string) $stat]],
                ]);

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
                        // "Your account has been activated" — tells the now-approved
                        // (and auto-activated) customer they can start using the system.
                        $emailResult = cdp_sendTemplateEmail(17, $userInfo->email, [
                            '[NAME]'     => $userInfo->fname,
                            '[USERNAME]' => $userInfo->fname,
                        ]);
                        $mailSent = !empty($emailResult['ok']);

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
