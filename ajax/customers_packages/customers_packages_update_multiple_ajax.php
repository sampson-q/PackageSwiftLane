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
require_permission('view_client_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

session_start();

$settings   = cdp_getSettingsCourier();
$site_email = $settings->email_address;
$check_mail = $settings->mailer;
$names_info = $settings->smtp_names;
$mlogo      = $settings->logo;
$msite_url  = $settings->site_url;
$msnames    = $settings->site_name;
$smtphoste  = $settings->smtp_host;
$smtpuser   = $settings->smtp_user;
$smtppass   = $settings->smtp_password;
$smtpport   = $settings->smtp_port;
$smtpsecure = $settings->smtp_secure;

$status = intval($_GET['status']);
$data   = json_decode($_GET['checked_data']);

// Resolve new status label once (same for all shipments in this batch)
$new_status_obj   = cdp_getCourierstatusApi($status);
$new_status_label = $new_status_obj ? $new_status_obj->mod_style : 'Updated';

foreach ($data as $key) {

    // Get shipment info
    $courier  = cdp_getPackageMultiple($key);
    $prefix   = $courier->order_prefix;
    $office   = $courier->origin_off;
    $tracking = $prefix . $key;

    // Check for duplicate track entry
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {

        // Snapshot old status before update
        $old_status_obj   = cdp_getCourierstatusApi((int)$courier->status_courier);
        $old_status_label = $old_status_obj ? $old_status_obj->mod_style : 'Previous Status';

        // Update shipment status
        cdp_updateStatusCustomerPackageMultiple($key, $status);

        // Build comment and insert track entry
        $comment = $lang['multiple_updated2'] . ' ' . $tracking;
        $user    = $_SESSION['userid'];
        cdp_updateShipTrackingMultiple($tracking, $status, $comment, $office, $user);

        // Get sender details
        $sender_data = cdp_getSenderCourier((int)$courier->sender_id);
        $app_url     = $msite_url . 'track_online_shopping.php?order_track=' . $tracking;

        // =====================
        // EMAIL NOTIFICATION
        // =====================
        $email_template = cdp_getEmailTemplatesdg1i4(35);

        if ($email_template && $sender_data && !empty($sender_data->email)) {

            $body = str_replace(
                [
                    '[NAME]',
                    '[TRACKING]',
                    '[OLD_STATUS]',
                    '[NEW_STATUS]',
                    '[URL]',
                    '[URL_LINK]',
                    '[SITE_NAME]',
                    '[URL_SHIP]'
                ],
                [
                    $sender_data->fname . ' ' . $sender_data->lname,
                    $tracking,
                    $old_status_label,
                    $new_status_label,
                    $msite_url,
                    $mlogo,
                    $msnames,
                    $app_url
                ],
                $email_template->body
            );

            $newbody = cdp_cleanOutx($body);
            $subject = 'Shipment Status Update - ' . $tracking;

            if ($check_mail == 'PHP') {

                $header  = "MIME-Version: 1.0\r\n";
                $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                $header .= "From: " . $site_email . " \r\n";
                try {
                    mail($sender_data->email, $subject, $newbody, $header);
                } catch (Exception $e) {}

            } elseif ($check_mail == 'SMTP') {

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtphoste;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpuser;
                $mail->Password   = $smtppass;
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom($site_email, $names_info);
                $mail->addAddress($sender_data->email);
                $mail->addCC($site_email, $msnames);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = "<html><body><p>{$newbody}</p></body></html><br />";

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    )
                );

                try {
                    $mail->Send();
                } catch (Exception $e) {}
            }
        }

        // =====================
        // WHATSAPP NOTIFICATION
        // =====================
        if ($sender_data && !empty($sender_data->phone)) {
            try {
                $whatsapp_body =
                    "Hello {$sender_data->fname} {$sender_data->lname},\n\n" .
                    "There is an update on your shipment *{$tracking}*.\n\n" .
                    "*Shipment Status:*\n" .
                    "_{$old_status_label}_ -> *{$new_status_label}*\n\n" .
                    "Track your shipment at any time:\n" .
                    $app_url . "\n\n" .
                    "Thank you, *{$msnames}* Team";

                sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);

            } catch (Exception $e) {
                error_log('Error sending WhatsApp status notification for ' . $tracking . ': ' . $e->getMessage());
            }
        }

        $message[$key] = $key . ' ' . $lang['modal-text30'];

    } else {
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}


if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_success_updated']; ?>
        <ul class="error">
            <?php foreach ($message as $msj) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php echo $msj; ?>
                </li>
            <?php } ?>
        </ul>
        </p>
    </div>
<?php
}