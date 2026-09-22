<?php
// *************************************************************************
// * Push Notifications — broadcast / single user / consolidation.          *
// *                                                                       *
// * Delivery goes through helpers/push_notify.php, which returns the real  *
// * per-channel outcome and logs every attempt to cdb_message_log.         *
// *                                                                       *
// * Consolidation recipients are the package OWNERS (cdb_add_order        *
// * .sender_id). The previous version collected cdb_add_order.user_id —   *
// * the staff account that registered each order — so customers never     *
// * received consolidation pushes.                                         *
// *                                                                       *
// * One request delivers to CDP_PUSH_CHUNK recipients only. The page calls *
// * this endpoint repeatedly (after_id = last recipient done, batch_id =   *
// * the id returned by the first call) until `done` comes back true. The   *
// * broadcast used to loop over every active customer inside one request - *
// * thousands of SMTP + WhatsApp round trips - and the web server cut the  *
// * request off long before it finished.                                   *
// *************************************************************************

require_once("../../loader.php");
require_once("../../helpers/ajax_guard.php");
require_login();
require_permission('push_notifications');
require_once("../../helpers/push_notify.php");

$core = new Core;
$db = new Conexion;
$errors = array();

$settings = cdp_getSettingsCourier();

$notification_type = isset($_POST['notification_type']) ? cdp_sanitize($_POST['notification_type']) : '';
$uid = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$cid = isset($_POST['consolidation_id']) ? (int) $_POST['consolidation_id'] : 0;
$channels = cdp_pushChannels($_POST['channels'] ?? 'both');
$after_id = isset($_POST['after_id']) ? max(0, (int) $_POST['after_id']) : 0;

if ($notification_type === '') {
    $errors['notification_type'] = $lang['validate_notification_type'] ?? 'Notification type is required.';
}
if (empty($_POST['subject'])) {
    $errors['subject'] = $lang['validate_subject_error'] ?? 'Subject is required.';
}
if (empty($_POST['message'])) {
    $errors['message'] = $lang['validate_message_error'] ?? 'Message is required.';
}
if ($notification_type === 'single_user' && $uid <= 0) {
    $errors['user_id'] = $lang['validate_user_id_error'] ?? 'A user is required for a single-user notification.';
}
if ($notification_type === 'consolidation' && $cid <= 0) {
    $errors['consolidation_id'] = 'A consolidation is required for a consolidation notification.';
}
if (!in_array($notification_type, ['broadcast', 'single_user', 'consolidation'], true) && $notification_type !== '') {
    $errors['notification_type'] = 'Unknown notification type.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$subject = trim((string) $_POST['subject']);
$message = trim((string) $_POST['message']);

$recipientIds = [];
$ctx = [
    'source'   => 'push_notification',
    'subject'  => $subject,
    'batch_id' => cdp_pushBatchIdFromRequest($_POST['batch_id'] ?? ''),
];

if ($notification_type === 'broadcast') {
    // Every active customer. Staff and admins are not the audience of a
    // customer broadcast.
    $db->cdp_query("SELECT id FROM cdb_users WHERE active = 1 AND userlevel = 1");
    $db->cdp_execute();
    foreach ((array) $db->cdp_registros() as $row) {
        $recipientIds[] = (int) $row->id;
    }
    if (!$recipientIds) {
        $errors[] = 'No active customers found for the broadcast.';
    }
    $ctx['entity_type']  = 'broadcast';
    $ctx['entity_label'] = 'All customers';

} elseif ($notification_type === 'single_user') {
    $db->cdp_query("SELECT * FROM cdb_users WHERE id = :id AND active = 1 LIMIT 1");
    $db->bind(':id', $uid);
    $db->cdp_execute();
    $one = $db->cdp_registro();
    if (!$one) {
        $errors[] = 'User not found or not active.';
    } else {
        $recipientIds = [(int) $one->id];
        $ctx['entity_type'] = 'user';
        $ctx['entity_id']   = (string) $uid;
        $ctx['entity_label'] = trim($one->fname . ' ' . $one->lname);
    }

} elseif ($notification_type === 'consolidation') {
    $db->cdp_query("SELECT consolidate_id, c_prefix, c_no FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $con = $db->cdp_registro();

    if (!$con) {
        $errors[] = 'Consolidation not found.';
    } else {
        $ownerIds = cdp_pushConsolidationOwnerIds($cid, 'consolidate');
        if (!$ownerIds) {
            $errors[] = 'No packages with an owner were found in that consolidation.';
        } else {
            foreach (cdp_pushFetchUsers($ownerIds) as $owner) {
                $recipientIds[] = (int) $owner->id;
            }
            if (!$recipientIds) {
                $errors[] = 'None of the package owners in that consolidation has an active account.';
            }
        }
        $ctx['entity_type']  = 'consolidation';
        $ctx['entity_id']    = (string) $cid;
        $ctx['entity_label'] = cdp_consolidationRef($con, 'consolidate');
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => array_values($errors)]);
    exit;
}

$sum = cdp_pushNotifyChunk($recipientIds, $after_id, $subject, $message, $settings, $ctx, $channels);

// Logged once per push, on its first chunk; the per-recipient outcome of the
// whole push is in the Message Logs under the batch id.
if ($after_id === 0 && function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'       => 'notifications',
        'verb'         => 'notify',
        'action'       => 'notifications.push',
        'label'        => 'Notifications · Push Notification Sent',
        'summary'      => sprintf('Push notification "%s" started to %d recipient(s) [%s] by %s — batch %s',
            $subject, $sum['total'], $notification_type, $sum['channels_label'], $sum['batch_id']),
        'entity_type'  => $ctx['entity_type'] ?? '',
        'entity_id'    => $ctx['entity_id'] ?? '',
        'entity_label' => $ctx['entity_label'] ?? '',
        'meta'         => ['batch_id' => $sum['batch_id'], 'type' => $notification_type, 'channels' => $channels, 'recipients' => $sum['total']],
    ]);
}

// success = this chunk was processed; whether anything was DELIVERED is judged
// by the page over the whole push, from the summaries it adds up.
echo json_encode([
    'success'  => true,
    'summary'  => $sum,
    'messages' => $sum['lines'],
    'errors'   => [],
]);
