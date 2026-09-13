<?php
// *************************************************************************
// * Push Notifications for ONE consolidation — all package owners, or a    *
// * chosen subset of them.                                                 *
// *                                                                       *
// * Delivery goes through helpers/push_notify.php (real per-channel        *
// * outcome, every attempt logged to cdb_message_log).                     *
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
$cid = isset($_POST['consolidation_id']) ? (int) $_POST['consolidation_id'] : 0;

$sender_ids_post = array();
if (isset($_POST['sender_ids']) && is_array($_POST['sender_ids'])) {
    foreach ($_POST['sender_ids'] as $u) {
        if ((int) $u > 0) $sender_ids_post[] = (int) $u;
    }
}

if ($notification_type === '') {
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
if ($notification_type === 'selected_users' && !$sender_ids_post) {
    $errors['sender_ids'] = 'Choose at least one user from the consolidation.';
} elseif (!in_array($notification_type, ['broadcast', 'selected_users'], true) && $notification_type !== '') {
    $errors['notification_type_unknown'] = 'Unknown notification type.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$subject = trim((string) $_POST['subject']);
$message = trim((string) $_POST['message']);

$db->cdp_query("SELECT consolidate_id, c_prefix, c_no FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $cid);
$db->cdp_execute();
$con = $db->cdp_registro();
if (!$con) {
    echo json_encode(['success' => false, 'errors' => ['Consolidation not found.']]);
    exit;
}

// Package owners inside this consolidation (customers, not staff).
$ownerIds = cdp_pushConsolidationOwnerIds($cid, 'consolidate');

if ($notification_type === 'selected_users') {
    // Only owners that really belong to this consolidation.
    $ownerIds = array_values(array_intersect($ownerIds, array_unique($sender_ids_post)));
    if (!$ownerIds) {
        echo json_encode(['success' => false, 'errors' => ['None of the selected users owns a package in this consolidation.']]);
        exit;
    }
}

if (!$ownerIds) {
    echo json_encode(['success' => false, 'errors' => ['No packages with an owner were found in that consolidation.']]);
    exit;
}

$users = cdp_pushFetchUsers($ownerIds);
if (!$users) {
    echo json_encode(['success' => false, 'errors' => ['None of the package owners has an active account.']]);
    exit;
}

$ctx = [
    'source'       => 'push_notification_consolidation',
    'subject'      => $subject,
    'entity_type'  => 'consolidation',
    'entity_id'    => (string) $cid,
    'entity_label' => $con->c_prefix . $con->c_no,
];
$sum = cdp_pushNotifyUsers($users, $subject, $message, $settings, $ctx);

if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'       => 'notifications',
        'verb'         => 'notify',
        'action'       => 'notifications.push_consolidation',
        'label'        => 'Notifications · Consolidation Push Sent',
        'summary'      => sprintf('Push "%s" to %d owner(s) of %s — WhatsApp %d sent / %d failed / %d skipped; e-mail %d sent / %d failed / %d skipped',
            $subject, $sum['recipients'], $ctx['entity_label'],
            $sum['whatsapp']['sent'], $sum['whatsapp']['failed'], $sum['whatsapp']['skipped'],
            $sum['email']['sent'], $sum['email']['failed'], $sum['email']['skipped']),
        'entity_type'  => 'consolidation',
        'entity_id'    => (string) $cid,
        'entity_label' => $ctx['entity_label'],
        'meta'         => ['batch_id' => $sum['batch_id'], 'type' => $notification_type],
    ]);
}

$delivered = $sum['whatsapp']['sent'] + $sum['email']['sent'];
echo json_encode([
    'success'  => $delivered > 0,
    'summary'  => $sum,
    'messages' => $sum['lines'],
    'errors'   => $delivered > 0 ? [] : ['Nothing was delivered. See the per-recipient results and the Message Logs page.'],
]);
