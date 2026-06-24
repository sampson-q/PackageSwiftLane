<?php
// ============================================================================
// Pickup-aging — popup data + the "notify senders" action.
//   action=pending : runs the scan (discover + auto-transitions) and returns the
//                    JSON list of packages awaiting the 2-week notification.
//   action=notify  : POST order_ids[] -> message senders + set Pending Collection.
// Admin / super-admin only.
// ============================================================================

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/pickup_aging.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User;
if (!$user->cdp_is_Admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$userData = $user->cdp_getUserData();
$uid    = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));
$action = isset($_REQUEST['action']) ? cdp_sanitize($_REQUEST['action']) : 'pending';

// The dashboard scan: refresh the state machine on every check.
cdp_processPickupAging();

if ($action === 'notify') {
    $raw = $_POST['order_ids'] ?? '[]';
    $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($ids)) $ids = [];
    $res = cdp_pickupAgingNotifySenders($ids, $uid);
    echo json_encode(array_merge(['ok' => true], $res));
    exit;
}

// action = pending (default)
$rows = cdp_pickupAgingPending();
$out  = [];
foreach ($rows as $r) {
    $out[] = [
        'order_id' => (int) $r->order_id,
        'tracking' => $r->order_track,
        'days'     => (int) $r->days_ready,
    ];
}
echo json_encode(['ok' => true, 'count' => count($out), 'packages' => $out]);
