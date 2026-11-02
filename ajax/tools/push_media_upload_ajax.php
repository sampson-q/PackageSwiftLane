<?php
// *************************************************************************
// * Push Notifications: the image, video or document attached to the      *
// * WhatsApp copy of a push. Stores the file (helpers/push_compose.php)    *
// * and answers with the stored name the page posts with the push.        *
// *************************************************************************

require_once("../../loader.php");
require_once("../../helpers/ajax_guard.php");
require_login();
require_permission('push_notifications');
require_once("../../helpers/push_compose.php");

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['media']) || !is_array($_FILES['media'])) {
    // A file over post_max_size arrives as an empty request.
    echo json_encode(['success' => false, 'message' => 'No file was received. WhatsApp accepts files up to 16 MB.']);
    exit;
}

$settings = cdp_getSettingsCourier();
$r = cdp_pushMediaStore($_FILES['media'], (string) ($settings->site_url ?? ''));
echo json_encode($r);
