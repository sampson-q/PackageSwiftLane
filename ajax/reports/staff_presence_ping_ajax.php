<?php
// *************************************************************************
// * Staff presence ping.                                                  *
// *                                                                       *
// * dataJs/presence_beacon.js POSTs `ago[]` — offsets in minutes from now  *
// * for every minute in which the signed-in staff member touched the      *
// * keyboard, mouse or screen. One row per (user, minute) lands in         *
// * cdb_staff_presence. See helpers/staff_activity.php.                    *
// *                                                                       *
// * Staff roles only; customers, drivers and agencies are ignored even if  *
// * something posts here. Under "View as" the ORIGINAL operator is         *
// * credited, not the account being viewed.                                *
// *                                                                       *
// * Classified read-only by the activity log (token 'presence'), so the    *
// * minute-by-minute pings do not flood the audit trail.                   *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/staff_activity.php');
require_login();

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

if (!cdp_spBeaconWanted()) {
    echo json_encode(['ok' => true, 'stored' => 0, 'ignored' => true]);
    exit;
}

$uid = (int) ($_SESSION['imp_original_userid'] ?? 0);
if ($uid <= 0) {
    $uid = (int) ($_SESSION['userid'] ?? 0);
}

$ago = $_POST['ago'] ?? [];
if (!is_array($ago)) {
    $ago = [$ago];
}
$ago = array_slice(array_map('intval', $ago), 0, CDP_SP_PING_MAX_AGO + 1);

// The DB wrapper echoes on failure; nothing but JSON may leave this endpoint.
ob_start();
$stored = cdp_spRecordPresence($uid, $ago, (string) ($_POST['page'] ?? ''));
ob_end_clean();

echo json_encode(['ok' => true, 'stored' => $stored]);
