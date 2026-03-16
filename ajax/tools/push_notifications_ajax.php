<?php
// ini_set('display_errors', 1);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

$core = new Core;
$db = new Conexion;
$errors = array();
$messages = array();

$settings = cdp_getSettingsCourier();

// Basic validation
if (empty($_POST['notification_type'])) {
    $errors['notification_type'] = isset($lang['validate_notification_type']) ? $lang['validate_notification_type'] : 'Notification type is required.';
}

$notification_type = isset($_POST['notification_type']) ? cdp_sanitize($_POST['notification_type']) : '';

$uid = isset($_POST['user_id']) ? cdp_sanitize($_POST['user_id']) : '';
$cid = isset($_POST['consolidation_id']) ? cdp_sanitize($_POST['consolidation_id']) : '';

if (empty($_POST['subject'])) {
    $errors['subject'] = isset($lang['validate_subject_error']) ? $lang['validate_subject_error'] : 'Subject is required.';
}
if (empty($_POST['message'])) {
    $errors['message'] = isset($lang['validate_message_error']) ? $lang['validate_message_error'] : 'Message is required.';
}

// Enforce strict pairings: depending on notification_type, presence OR absence of other ids is validated
if ($notification_type === 'broadcast') {
    // broadcast must NOT include user_id or consolidation_id
    if (!empty($uid)) {
        $errors['broadcast_user_forbidden'] = 'Do not include user_id for broadcast.';
    }
    if (!empty($cid)) {
        $errors['broadcast_consolidation_forbidden'] = 'Do not include consolidation_id for broadcast.';
    }
}

if ($notification_type === 'single_user') {
    // single_user must include user_id and must NOT include consolidation_id
    if (empty($uid)) {
        $errors['user_id'] = isset($lang['validate_user_id_error']) ? $lang['validate_user_id_error'] : 'User ID is required for single user notification.';
    }
    if (!empty($cid)) {
        $errors['single_consolidation_forbidden'] = 'Do not include consolidation_id for single_user notifications.';
    }
}

if ($notification_type === 'consolidation') {
    // consolidation must include consolidation_id and must NOT include user_id
    if (empty($cid)) {
        $errors['consolidation_id'] = 'Consolidation ID is required for consolidation notifications.';
    }
    if (!empty($uid)) {
        $errors['consolidation_user_forbidden'] = 'Do not include user_id for consolidation notifications.';
    }
}

// Keep pushNotification helper (your original function with minor safety check)
function pushNotification($user, $subject, $message, $settings) {
    global $errors, $messages;
    $app_url = $settings->site_url;

    // WhatsApp
    $whatsappTemplateId = 12;
    $tpl = getTemplateWhatsApp($whatsappTemplateId);
    if ($tpl) {
        $body = str_replace(
            ['[USERNAME]', '[SUBJECT]', '[SITE_NAME]', '[MESSAGE]', '[URL]'],
            [ucfirst($user->fname . ' ' . $user->lname), $subject, $settings->site_name, $message, $app_url],
            $tpl->body
        );
        // you already have sendNotificationWhatsApp_v2
        sendNotificationWhatsApp_v2($user, $body);
        $messages[] = "WhatsApp sent to {$user->email}";
    } else {
        $errors[] = 'WhatsApp template not found.';
    }

    // Email
    $email_template = cdp_getEmailTemplatesdg1i4(29);
    $body = str_replace(
        ['[USERNAME]', '[MESSAGE]', '[URL]', '[SITE_NAME]'],
        [$user->fname . ' ' . $user->lname, $message, $app_url, $settings->site_name],
        $email_template->body
    );

    $newbody = cdp_cleanOutx($body);
    if ($settings->mailer == 'PHP') {
        $to     = $user->email;
        $from   = $settings->email_address;
        $header = "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=UTF-8\r\n";
        $header .= "From: {$from}\r\n";

        if (mail($to, $subject, $newbody, $header)) {
            $messages[] = "Email (PHP mail) sent to {$user->email}";
        } else {
            $errors[] = "PHP mail() failed for {$user->email}";
        }
    } elseif ($settings->mailer == 'SMTP') {
        $destinatario = $user->email;

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $settings->smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings->smtp_user;
        $mail->Password   = $settings->smtp_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($settings->email_address, $settings->smtp_names);
        $mail->addAddress($destinatario);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = "<html><body><p>{$newbody}</p></body></html>";

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ]
        ];

        try {
            $mail->send();
            $messages[] = "Email (SMTP) sent to {$user->email}";
        } catch (Exception $e) {
            $errors[] = "SMTP send failed for {$user->email}: " . $e->getMessage();
        }
    }
}

// If any validation errors so far, return early with errors
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors'  => $errors
    ]);
    exit;
}

// sanitize subject/message and prepare to send
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

if ($notification_type == 'broadcast') {
    // Send to all active users only
    $db->cdp_query("SELECT * FROM cdb_users WHERE active = 1");
    $db->cdp_execute();
    $users = $db->cdp_registros();

    if (empty($users)) {
        $errors[] = 'No active users found for broadcast.';
    } else {
        foreach ($users as $user) {
            pushNotification($user, $subject, $message, $settings);
        }
    }

} elseif ($notification_type == 'single_user') {
    // Single user: ensure the user exists and is active, and send only to them
    $uid_int = intval($uid);
    $db->cdp_query("SELECT * FROM cdb_users WHERE id = :id AND active = 1 LIMIT 1");
    $db->bind(':id', $uid_int);
    $db->cdp_execute();
    $user = $db->cdp_registro();

    if (!$user) {
        $errors[] = 'User not found or is not active.';
    } else {
        pushNotification($user, $subject, $message, $settings);
    }

} elseif ($notification_type == 'consolidation') {
    // Consolidation: validate consolidation exists, then find DISTINCT users with orders in that consolidate
    $cid_int = intval($cid);

    // verify consolidation exists
    $db->cdp_query("SELECT * FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', $cid_int);
    $db->cdp_execute();
    $con = $db->cdp_registro();

    if (!$con) {
        $errors[] = 'Consolidation not found.';
    } else {
        // find distinct order owners (user_id) in this consolidation via cdb_consolidate_detail -> cdb_add_order
        $db->cdp_query("
            SELECT DISTINCT o.user_id
            FROM cdb_consolidate_detail d
            INNER JOIN cdb_add_order o ON d.order_id = o.order_id
            WHERE d.consolidate_id = :cid
        ");
        $db->bind(':cid', $cid_int);
        $db->cdp_execute();
        $rows = $db->cdp_registros();

        $user_ids = [];
        if (!empty($rows)) {
            foreach ($rows as $r) {
                if (!empty($r->user_id)) {
                    $user_ids[] = intval($r->user_id);
                }
            }
        }

        // If no users found for consolidation, return error
        if (empty($user_ids)) {
            $errors[] = 'No users found for that consolidation.';
        } else {
            // fetch active users by ids (ensure only active users receive notifications)
            $unique_ids = array_values(array_unique($user_ids));
            // build safe IN list
            $in_list = implode(',', array_map('intval', $unique_ids));

            $db->cdp_query("SELECT * FROM cdb_users WHERE id IN ({$in_list}) AND active = 1");
            $db->cdp_execute();
            $users = $db->cdp_registros();

            if (empty($users)) {
                $errors[] = 'No active users found among consolidation members.';
            } else {
                // send once per user (we already deduped using DISTINCT)
                foreach ($users as $user) {
                    pushNotification($user, $subject, $message, $settings);
                }
            }
        }
    }
} else {
    $errors[] = 'Unknown notification type.';
}

// Final response
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors'  => $errors,
        'messages'=> $messages
    ]);
} else {
    echo json_encode([
        'success' => true,
        'messages'=> $messages
    ]);
}