<?php
// The admin cancelled the (blocking) email-verification step in client_add.
// Roll back the pending account so the email/username are freed for a fresh
// attempt. Only ever touches the session-bound pending id, and only while it is
// still unverified + inactive — a verified/active account is never deleted.

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

header('Content-Type: application/json; charset=UTF-8');

$db  = new Conexion;

$uid = isset($_SESSION['client_add_pending_user_id']) ? (int) $_SESSION['client_add_pending_user_id'] : 0;

if ($uid > 0) {
    $db->cdp_query("DELETE FROM cdb_users WHERE id = :id AND registration_complete = 0 AND active = 0");
    $db->bind(':id', $uid);
    $db->cdp_execute();

    $db->cdp_query("DELETE FROM cdb_senders_addresses WHERE user_id = :id");
    $db->bind(':id', $uid);
    $db->cdp_execute();

    $db->cdp_query("DELETE FROM cdb_user_details_update_check WHERE user_id = :id");
    $db->bind(':id', $uid);
    $db->cdp_execute();
}

unset($_SESSION['client_add_pending_user_id'], $_SESSION['client_add_otp_challenge']);

echo json_encode(['ok' => true]);
exit;
