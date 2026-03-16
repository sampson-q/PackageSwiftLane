<?php

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

// Collect inputs
$notification_type = isset($_POST['notification_type']) ? cdp_sanitize($_POST['notification_type']) : '';
$cid = isset($_POST['consolidation_id']) ? intval($_POST['consolidation_id']) : 0;

// collect sender_ids[] if provided
$sender_ids_post = array();
if (isset($_POST['sender_ids']) && is_array($_POST['sender_ids'])) {
    foreach ($_POST['sender_ids'] as $u) {
        $u_int = intval($u);
        if ($u_int > 0) $sender_ids_post[] = $u_int;
    }
}

// basic validation
if (empty($notification_type)) {
    $errors['notification_type'] = 'Notification type is required.';
}
if ($cid <= 0) {
    $errors['consolidation_id'] = 'Consolidation ID is required.';
}
if (empty($_POST['subject'])) {
    $errors['subject'] = 'Subject is required.';
}
if (empty($_POST['message'])) {
    $errors['message'] = 'Message is required.';
}

// Validate allowed combinations:
if ($notification_type === 'broadcast') {
    if (!empty($sender_ids_post)) {
        $errors['broadcast_user_forbidden'] = 'Do not include sender_ids for broadcast.';
    }
} elseif ($notification_type === 'selected_users') {
    if (empty($sender_ids_post)) {
        $errors['sender_ids'] = 'Please provide at least one user id.';
    }
} else {
    $errors['notification_type_unknown'] = 'Unknown notification type.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// sanitize subject/message
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// Helper: send notifications (WhatsApp & Email)
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

        $mail->setFrom($settings->email_address, $settings->smtp_names);
        $mail->addAddress($destinatario);

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

// Utility: fetch user records by unique id array (returns array of user objects)
function fetchUsersByIds($db, $ids) {
    if (empty($ids)) return [];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $in_list = implode(',', $ids);
    $db->cdp_query("SELECT * FROM cdb_users WHERE id IN ({$in_list}) AND active = 1");
    $db->cdp_execute();
    return $db->cdp_registros();
}

// ---------- main flows ----------

$sent_user_ids = []; // to ensure each recipient is messaged only once

if ($notification_type === 'broadcast') {
    // find distinct sender_ids for the consolidation
    $cid_int = intval($cid);
    $db->cdp_query("
        SELECT DISTINCT o.sender_id
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_add_order o ON d.order_id = o.order_id
        WHERE d.consolidate_id = :cid
    ");
    $db->bind(':cid', $cid_int);
    $db->cdp_execute();
    $rows = $db->cdp_registros();

    $sender_ids = [];
    if (!empty($rows)) {
        foreach ($rows as $r) {
            $sid = intval($r->sender_id);
            if ($sid > 0) $sender_ids[$sid] = $sid;
        }
    }

    if (empty($sender_ids)) {
        $errors[] = 'No users found for that consolidation.';
    } else {
        $unique_ids = array_values($sender_ids);
        $users = fetchUsersByIds($db, $unique_ids);

        if (empty($users)) {
            $errors[] = 'No active users found among consolidation members.';
        } else {
            // send once per unique user
            foreach ($users as $user) {
                $uid = intval($user->id);
                if (in_array($uid, $sent_user_ids, true)) continue;
                pushNotification($user, $subject, $message, $settings);
                $sent_user_ids[] = $uid;
            }
        }
    }

} elseif ($notification_type === 'selected_users') {
    // ensure provided sender_ids belong to consolidation
    $cid_int = intval($cid);
    $db->cdp_query("
        SELECT DISTINCT o.sender_id
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_add_order o ON d.order_id = o.order_id
        WHERE d.consolidate_id = :cid
    ");
    $db->bind(':cid', $cid_int);
    $db->cdp_execute();
    $rows = $db->cdp_registros();

    $valid_ids = [];
    if (!empty($rows)) {
        foreach ($rows as $r) {
            $sid = intval($r->sender_id);
            if ($sid > 0) $valid_ids[$sid] = $sid;
        }
    }
    $valid_ids = array_values($valid_ids);

    // sanitize and dedupe posted ids
    $selected_post = array_values(array_unique(array_map('intval', $sender_ids_post)));

    // intersect posted ids with valid_ids
    $allowed = array_intersect($selected_post, $valid_ids);
    $allowed = array_values(array_unique(array_map('intval', $allowed)));

    if (empty($allowed)) {
        $errors[] = 'No valid users selected from the consolidation.';
    } else {
        $users = fetchUsersByIds($db, $allowed);

        if (empty($users)) {
            $errors[] = 'No active users found among the selected users.';
        } else {
            foreach ($users as $user) {
                $uid = intval($user->id);
                if (in_array($uid, $sent_user_ids, true)) continue;
                pushNotification($user, $subject, $message, $settings);
                $sent_user_ids[] = $uid;
            }
        }
    }

} else {
    $errors[] = 'Unknown notification type.';
}

// response
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
