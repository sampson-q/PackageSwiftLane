<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * auto_notify.php                                                        *
// * Automatic notification helper: sends email/WhatsApp/SMS when a        *
// * shipment status changes. Safe to call from any context — all errors   *
// * are caught and logged silently so the main operation is never broken. *
// *                                                                       *
// *************************************************************************

// Guard: only define functions once (this file may be included multiple times)
if (!function_exists('cdp_autoNotifyShipmentStatus')) {

    // ------------------------------------------------------------------
    // Ensure PHPMailer classes are available
    // ------------------------------------------------------------------
    if (!class_exists('PHPMailer')) {
        $phpmailer_base = __DIR__ . '/phpmailer/';
        if (file_exists($phpmailer_base . 'class.phpmailer.php')) {
            require_once($phpmailer_base . 'class.phpmailer.php');
            require_once($phpmailer_base . 'class.smtp.php');
        }
    }

    // ------------------------------------------------------------------
    // Ensure WhatsApp / SMS service functions are available
    // ------------------------------------------------------------------
    if (!function_exists('sendNotificationWhatsApp')) {
        $ws_service = __DIR__ . '/../ajax/notify_whatsapp/api_whatsapp_service.php';
        if (file_exists($ws_service)) {
            require_once($ws_service);
        }
    }

    if (!function_exists('sendNotificationSMS')) {
        $sms_service = __DIR__ . '/../ajax/notify_sms/api_sms_service.php';
        if (file_exists($sms_service)) {
            require_once($sms_service);
        }
    }

    // ------------------------------------------------------------------
    // Ensure ClickSend autoload is available for SMS
    // ------------------------------------------------------------------
    $clicksend_autoload = __DIR__ . '/../helpers/vendor/autoload.php';
    if (file_exists($clicksend_autoload) && !class_exists('ClickSend\Api\SMSApi')) {
        require_once($clicksend_autoload);
    }

    /**
     * cdp_getNotificationTemplate
     *
     * Returns the template object for the given channel, or null if none configured.
     *
     * For 'email'    : returns template with id=10 (the standard shipment-tracking email template).
     * For 'sms'      : returns the sender SMS template (id=4) as the canonical status-update template.
     * For 'whatsapp' : returns the default_notification_templates row with id=4 (tracking update slot).
     *
     * $status_id is reserved for future per-status template mapping.
     *
     * @param int    $status_id  The new status ID (reserved for future per-status templates).
     * @param string $channel    'email' | 'sms' | 'whatsapp'
     * @return object|null
     */
    function cdp_getNotificationTemplate(int $status_id, string $channel = 'email')
    {
        try {
            switch ($channel) {
                case 'email':
                    // Template 10 is the shipment tracking update email template used by
                    // add_courier_tracking.php. It uses [NAME], [TRACKING], [DELIVERY_TIME],
                    // [COURIER], [NEW_ADDRESS], [COMMENT], [URL], [URL_LINK], [SITE_NAME], [URL_SHIP].
                    $tpl = cdp_getEmailTemplatesdg1i4(10);
                    return ($tpl && !empty($tpl->body)) ? $tpl : null;

                case 'sms':
                    // Template 4 is the sender SMS template for tracking status updates.
                    $tpl = cdp_getsmsTemplates(4);
                    return ($tpl && !empty($tpl->body)) ? $tpl : null;

                case 'whatsapp':
                    // default_notification_templates row 4 maps to the tracking-update WhatsApp template.
                    $tpl = getDefaultTemplateActiveWhatsApp(4);
                    return ($tpl && intval($tpl->active) == 1 && !empty($tpl->id_template)) ? $tpl : null;

                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log('[auto_notify] cdp_getNotificationTemplate error (' . $channel . '): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * cdp_autoNotifyShipmentStatus
     *
     * Automatically sends email, WhatsApp, and SMS notifications when a shipment
     * status changes. This function NEVER throws — all failures are caught and
     * logged to the PHP error log so the calling operation is never broken.
     *
     * @param int $shipment_id   The cdb_add_order.order_id of the shipment.
     * @param int $new_status_id The new cdb_styles.id status that was just applied.
     * @return bool              true if at least the email channel succeeded or was not needed,
     *                           false if a critical data-load error occurred.
     */
    function cdp_autoNotifyShipmentStatus(int $shipment_id, int $new_status_id): bool
    {
        try {

            // ----------------------------------------------------------------
            // 1. Load shipment, sender, receiver, status, and settings
            // ----------------------------------------------------------------
            $shipment = cdp_getCourier($shipment_id);
            if (!$shipment) {
                error_log('[auto_notify] Shipment not found: id=' . $shipment_id);
                return false;
            }

            $settings     = cdp_getSettingsCourier();
            $sender_data  = cdp_getSenderCourier(intval($shipment->sender_id));
            $receiver_data = cdp_getRecipientCourier(intval($shipment->receiver_id));
            $status_info  = cdp_getCourierstatusApi($new_status_id);

            if (!$sender_data) {
                error_log('[auto_notify] Sender not found for shipment id=' . $shipment_id);
                return false;
            }

            // ----------------------------------------------------------------
            // 2. Build common variables
            // ----------------------------------------------------------------
            $fullshipment  = $shipment->order_prefix . $shipment->order_no;
            $date_ship     = date("Y-m-d H:i:s");
            $status_label  = $status_info ? $status_info->mod_style : '';
            $app_url       = rtrim($settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;

            $site_email    = $settings->email_address;
            $check_mail    = $settings->mailer;
            $names_info    = $settings->smtp_names;
            $mlogo         = $settings->logo;
            $msite_url     = $settings->site_url;
            $msnames       = $settings->site_name;
            $smtphoste     = $settings->smtp_host;
            $smtpuser      = $settings->smtp_user;
            $smtppass      = $settings->smtp_password;
            $smtpport      = $settings->smtp_port;
            $smtpsecure    = $settings->smtp_secure;

            $subject = 'Shipment Update | ' . $fullshipment;

            // ----------------------------------------------------------------
            // 3. EMAIL notification
            // ----------------------------------------------------------------
            try {
                $email_tpl = cdp_getNotificationTemplate($new_status_id, 'email');

                if ($email_tpl && !empty($sender_data->email)) {

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
                            $status_label,
                            '',                 // no address context in auto-notify
                            '',                 // no comment context in auto-notify
                            $msite_url,
                            $mlogo,
                            $msnames,
                            $app_url
                        ),
                        $email_tpl->body
                    );

                    $newbody = cdp_cleanOut($body);

                    if ($check_mail === 'PHP') {

                        $to      = $sender_data->email;
                        $from    = $site_email;
                        $header  = "MIME-Version: 1.0\r\n";
                        $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                        $header .= "From: " . $from . " \r\n";
                        mail($to, $subject, $newbody, $header);

                    } elseif ($check_mail === 'SMTP') {

                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = $smtphoste;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $smtpuser;
                        $mail->Password   = $smtppass;
                        $mail->SMTPSecure = !empty($smtpsecure) ? $smtpsecure : 'tls';
                        $mail->Port       = !empty($smtpport)   ? intval($smtpport) : 587;

                        $mail->setFrom($site_email, $names_info);
                        $mail->addAddress($sender_data->email);
                        $mail->addCC($site_email, $msnames);

                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = $subject;
                        $mail->Body    = "<html><body><p>{$newbody}</p></body></html>";

                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true,
                            )
                        );

                        $mail->Send();
                    }
                }
            } catch (Exception $e) {
                error_log('[auto_notify] Email send error for shipment=' . $shipment_id . ': ' . $e->getMessage());
            }

            // ----------------------------------------------------------------
            // 4. WHATSAPP notification (sender)
            // ----------------------------------------------------------------
            try {
                if (function_exists('sendNotificationWhatsApp') && intval($settings->active_whatsapp) == 1) {
                    sendNotificationWhatsApp($sender_data, 4, null, $fullshipment);
                }
            } catch (Exception $e) {
                error_log('[auto_notify] WhatsApp send error for shipment=' . $shipment_id . ': ' . $e->getMessage());
            }

            // ----------------------------------------------------------------
            // 5. SMS notification (sender)
            // ----------------------------------------------------------------
            try {
                if (function_exists('sendNotificationSMS') && function_exists('generateSMSBody')
                    && intval($settings->active_sms) == 1) {

                    $sms_body = generateSMSBody($sender_data, $fullshipment, $status_label, $app_url, 4);
                    // Pass true so sendNotificationSMS actually sends (it checks this flag)
                    sendNotificationSMS($sender_data, $sms_body, true);
                }
            } catch (Exception $e) {
                error_log('[auto_notify] SMS send error for shipment=' . $shipment_id . ': ' . $e->getMessage());
            }

            return true;

        } catch (Exception $e) {
            error_log('[auto_notify] cdp_autoNotifyShipmentStatus fatal error (shipment=' . $shipment_id . '): ' . $e->getMessage());
            return false;
        }
    }

} // end if (!function_exists('cdp_autoNotifyShipmentStatus'))
