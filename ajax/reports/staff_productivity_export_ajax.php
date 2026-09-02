<?php
// *************************************************************************
// * Staff Productivity — CSV of the current selection.                    *
// *                                                                       *
// * Three sections in one file: the per-staff summary, the day-by-day     *
// * rows (check-in, check-out, active, idle, the shipment ids created and *
// * edited) and the full timeline — every active / idle stretch of every   *
// * day with what happened in it. The owner asked to "export the whole    *
// * thing", and a summary without the days behind it cannot be checked.   *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/staff_activity.php');
require_login();
require_permission('export_staff_productivity');

$user = new User();
$user->cdp_getUserPermissions();
if (!cdp_spCanView($user)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

$date = function ($k) {
    $v = trim((string) ($_REQUEST[$k] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
};
$from = $date('from');
$to   = $date('to');

$userIds = [];
if (!empty($_REQUEST['user_id'])) {
    $raw = is_array($_REQUEST['user_id']) ? $_REQUEST['user_id'] : explode(',', (string) $_REQUEST['user_id']);
    $userIds = array_values(array_filter(array_map('intval', $raw)));
}

$settings = cdp_spSettings();
$s        = cdp_spSummary($from, $to, $userIds);
$allDays  = cdp_spBuildDays($from, $to, $userIds);
$staff    = cdp_spStaffUsers();

$filename = 'staff-productivity-' . ($from !== '' ? $from : 'start') . '_to_' . ($to !== '' ? $to : 'today') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads the names correctly

$coverageText = function ($c) {
    return [
        'presence' => 'Presence data - idle measured from keyboard/mouse/screen input',
        'log'      => 'Actions only - idle is the gaps between recorded actions',
        'history'  => 'Package actions only - predates the activity trail, idle withheld',
        'full'     => 'Actions recorded - idle is reliable',
        'mixed'    => 'Part detail - some days predate the activity trail, idle covers the rest',
        'partial'  => 'Low detail - all days predate the activity trail, idle withheld',
    ][$c] ?? $c;
};

// ── Provenance, so the numbers can be argued with ───────────────────────────
fputcsv($out, ['Staff Productivity Report']);
fputcsv($out, ['Period', ($from !== '' ? $from : 'earliest') . ' to ' . ($to !== '' ? $to : 'today')]);
fputcsv($out, ['Check-in', 'The first package ' . ($settings['checkin_scope'] === 'create_only' ? 'created' : 'created or edited') . ' that day. A day without one uses its first recorded activity and is marked "first activity".']);
fputcsv($out, ['Check-out', 'The end of the last recorded activity that day.']);
fputcsv($out, ['Idle after', $settings['idle_minutes'] . ' minutes without any keyboard, mouse or screen input (days with presence data)']);
fputcsv($out, ['Legacy gap', $settings['gap_minutes'] . ' minutes between recorded actions ends a working stretch (days without presence data)']);
fputcsv($out, ['Active', 'Time inside working stretches between check-in and check-out.']);
fputcsv($out, ['Idle', 'Pauses inside the working window. Withheld for days that predate the activity trail. Work done outside this app is not visible and reads as idle.']);
fputcsv($out, ['Generated', date('Y-m-d H:i')]);
if ($s['cutover']) {
    fputcsv($out, ['Note', 'Page activity is recorded from ' . $s['cutover'] . '. Before that only package, consolidation and pickup actions are known.']);
}
fputcsv($out, []);

// ── Section 1: per-staff summary ────────────────────────────────────────────
fputcsv($out, ['SUMMARY BY STAFF MEMBER']);
fputcsv($out, [
    'Staff', 'Username', 'Role', 'Account', 'Data Coverage',
    'Active Hours', 'Idle Hours', 'Working Window Hours', 'Utilisation %', 'Days Idle Measured',
    'Days Worked', 'Check-Ins (package days)', 'Avg Active Hours/Day', 'Working Stretches',
    'Packages Created', 'Packages Edited', 'Deletions',
    'Consolidations', 'Pickups',
    'Packages Per Active Hour', 'First Check-In', 'Last Check-Out', 'Total Actions',
]);

foreach ($s['rows'] as $r) {
    fputcsv($out, [
        $r['name'], $r['username'], $r['role'], $r['is_active'] ? 'Active' : 'Inactive', $coverageText($r['coverage']),
        $r['active_hours'],
        $r['idle_reliable'] ? $r['idle_hours']  : 'not enough detail',
        $r['idle_reliable'] ? $r['span_hours']  : 'not enough detail',
        $r['idle_reliable'] ? $r['utilisation'] : '',
        $r['idle_days'],
        $r['days_worked'], $r['checkins'], $r['avg_hours_day'], $r['blocks'],
        $r['packages_added'], $r['packages_edited'], $r['deletions'],
        $r['consolidations'], $r['pickups'],
        $r['per_hour'], $r['first_at'], $r['last_at'], $r['events'],
    ]);
}

fputcsv($out, []);
fputcsv($out, [
    'TOTAL', '', '', '', '',
    $s['totals']['active_hours'], $s['totals']['idle_hours'], $s['totals']['span_hours'],
    $s['totals']['utilisation'], '',
    $s['totals']['days_worked'], $s['totals']['checkins'], '', '',
    $s['totals']['packages_added'], $s['totals']['packages_edited'], '',
    '', '',
    $s['totals']['per_hour'], '', '', $s['totals']['events'],
]);

// ── Section 2: day by day, per staff member ─────────────────────────────────
fputcsv($out, []);
fputcsv($out, ['DAY BY DAY']);
fputcsv($out, [
    'Staff', 'Date', 'Weekday', 'Data Coverage',
    'Check-In', 'Check-In Basis', 'Check-Out',
    'Active Hours', 'Idle Hours', 'Working Window Hours', 'Utilisation %',
    'Working Stretches', 'Packages Created', 'Packages Edited',
    'Actions Before Check-In', 'Total Actions',
    'Shipments Created', 'Shipments Edited', 'Stretch Times',
]);

$order = [];
foreach ($s['rows'] as $r) {
    $order[] = $r['user_id'];
}
foreach ($order as $uid) {
    $name = $staff[$uid]->display_name ?? ('User #' . $uid);
    foreach (($allDays[$uid] ?? []) as $d) {
        $times = [];
        foreach ($d['segments'] as $seg) {
            $times[] = ucfirst($seg['type']) . ' ' . $seg['from'] . '-' . $seg['to'] . ' (' . (int) round($seg['seconds'] / 60) . 'm)';
        }
        fputcsv($out, [
            $name, $d['date'], $d['weekday'], $coverageText($d['coverage']),
            $d['check_in_time'],
            $d['check_in_kind'] === 'package' ? ('package ' . $d['check_in_ref']) : 'first activity',
            $d['check_out_time'],
            round($d['active_seconds'] / 3600, 2),
            $d['idle_reliable'] ? round($d['idle_seconds'] / 3600, 2) : 'not enough detail',
            $d['idle_reliable'] ? round($d['window_seconds'] / 3600, 2) : 'not enough detail',
            $d['idle_reliable'] ? $d['utilisation'] : '',
            $d['blocks'], $d['created_count'], $d['edited_count'],
            $d['pre_checkin_events'], $d['events'],
            implode(' | ', $d['packages_created']),
            implode(' | ', $d['packages_edited']),
            implode(' | ', $times),
        ]);
    }
}

// ── Section 3: the timeline, stretch by stretch ─────────────────────────────
fputcsv($out, []);
fputcsv($out, ['TIMELINE - EVERY ACTIVE AND IDLE STRETCH']);
fputcsv($out, [
    'Staff', 'Date', 'Stretch', 'From', 'To', 'Minutes',
    'Actions', 'Shipments Created', 'Shipments Edited', 'Actions Performed',
]);

foreach ($order as $uid) {
    $name = $staff[$uid]->display_name ?? ('User #' . $uid);
    foreach (($allDays[$uid] ?? []) as $d) {
        foreach ($d['segments'] as $seg) {
            if ($seg['type'] === 'idle') {
                fputcsv($out, [$name, $d['date'], 'Idle', $seg['from'], $seg['to'], (int) round($seg['seconds'] / 60), '', '', '', '']);
                continue;
            }
            $acts = [];
            foreach ($seg['actions'] as $a) {
                $acts[] = $a['time'] . ' ' . $a['label'];
            }
            fputcsv($out, [
                $name, $d['date'], 'Active', $seg['from'], $seg['to'], (int) round($seg['seconds'] / 60),
                $seg['events'],
                implode(' | ', $seg['created']),
                implode(' | ', $seg['edited']),
                implode(' | ', $acts),
            ]);
        }
    }
}

fclose($out);

// Pulling staff performance figures out of the system is itself worth a row.
if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'  => 'reports',
        'verb'    => 'export',
        'action'  => 'reports.staff_productivity_export',
        'label'   => 'Reports · Staff Productivity Exported',
        'summary' => 'Exported the Staff Productivity report for '
                     . ($from !== '' ? $from : 'earliest') . ' to ' . ($to !== '' ? $to : 'today'),
        'meta'    => ['from' => $from, 'to' => $to, 'staff' => count($s['rows'])],
    ]);
}
