<?php
// *************************************************************************
// * Staff Productivity — settings.                                        *
// *                                                                       *
// * GET   current effective settings (JSON)                                *
// * POST  save any of: idle_minutes, gap_minutes, checkin_scope,           *
// *       ping_seconds, beacon_enabled — validated and clamped in           *
// *       helpers/staff_activity.php::cdp_spCleanSettings()                 *
// *                                                                       *
// * Admin and Super Admin only (cdp_spCanView). Every change is written to  *
// * the activity log with the before/after values.                         *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/staff_activity.php');
require_login();
require_permission('view_staff_productivity');

$user = new User();
$user->cdp_getUserPermissions();
if (!cdp_spCanView($user)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    echo json_encode(['ok' => true, 'settings' => cdp_spSettings(), 'table' => cdp_spSettingsTableReady()]);
    exit;
}

if (!cdp_spSettingsTableReady()) {
    echo json_encode(['ok' => false, 'error' => 'The settings table is not deployed yet. Apply sql/staff_productivity_v2.sql first.']);
    exit;
}

$before = cdp_spSettings();

ob_start();
$after = cdp_spSaveSettings($_POST, (int) ($_SESSION['userid'] ?? 0));
ob_end_clean();

$changes = [];
foreach ($after as $k => $v) {
    if ((string) ($before[$k] ?? '') !== (string) $v) {
        $changes[$k] = ['from' => $before[$k] ?? null, 'to' => $v];
    }
}

if ($changes && function_exists('cdp_activityLog')) {
    $bits = [];
    foreach ($changes as $k => $c) {
        $bits[] = $k . ' ' . $c['from'] . ' → ' . $c['to'];
    }
    cdp_activityLog([
        'module'  => 'reports',
        'verb'    => 'update',
        'action'  => 'reports.staff_productivity_settings',
        'label'   => 'Reports · Staff Productivity Settings',
        'summary' => 'Changed Staff Productivity settings: ' . implode(', ', $bits),
        'changes' => $changes,
    ]);
}

echo json_encode(['ok' => true, 'settings' => $after, 'changed' => array_keys($changes)]);
