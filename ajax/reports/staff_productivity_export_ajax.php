<?php
// *************************************************************************
// * Staff Productivity — CSV of the current selection.                    *
// *                                                                       *
// * Two sections in one file: the per-staff summary, then the day-by-day  *
// * breakdown for every staff member in the selection. The owner asked to *
// * "export the whole thing", and a summary without the days behind it     *
// * cannot be checked.                                                     *
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

$gap = (int) ($_REQUEST['gap'] ?? CDP_SP_IDLE_GAP);
if (!in_array($gap, [5, 10, 15, 30, 60], true)) {
    $gap = CDP_SP_IDLE_GAP;
}

$s = cdp_spSummary($from, $to, $userIds, $gap);

$filename = 'staff-productivity-' . ($from !== '' ? $from : 'start') . '_to_' . ($to !== '' ? $to : 'today') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads the names correctly

// ── Provenance, so the numbers can be argued with ───────────────────────────
fputcsv($out, ['Staff Productivity Report']);
fputcsv($out, ['Period', ($from !== '' ? $from : 'earliest') . ' to ' . ($to !== '' ? $to : 'today')]);
fputcsv($out, ['Idle gap', $gap . ' minutes — a longer gap ends an active block']);
fputcsv($out, ['Active hours', 'Reconstructed from recorded actions. Measures time working in the system, not time signed in.']);
fputcsv($out, ['Generated', date('Y-m-d H:i')]);
if ($s['cutover']) {
    fputcsv($out, ['Note', 'Logins and page activity are only recorded from ' . $s['cutover'] . '. Before that date only package, consolidation and pickup actions are known, so active hours read low.']);
} else {
    fputcsv($out, ['Note', 'The activity trail is not deployed yet, so only package, consolidation and pickup actions are known and active hours read low.']);
}
fputcsv($out, []);

// ── Section 1: per-staff summary ────────────────────────────────────────────
fputcsv($out, ['SUMMARY BY STAFF MEMBER']);
fputcsv($out, [
    'Staff', 'Username', 'Role', 'Account', 'Data Coverage',
    'Active Hours', 'Days Worked', 'Avg Hours/Day', 'Working Blocks',
    'Packages Added', 'Packages Edited', 'Deletions',
    'Consolidations', 'Pickups', 'Logins',
    'Packages Per Active Hour', 'First Activity', 'Last Activity', 'Total Actions',
]);

foreach ($s['rows'] as $r) {
    $coverage = [
        'full'    => 'Full',
        'mixed'   => 'Part detail - some days predate the activity trail, hours understated',
        'partial' => 'Low detail - all days predate the activity trail, hours understated',
    ][$r['coverage']] ?? '';

    fputcsv($out, [
        $r['name'], $r['username'], $r['role'], $r['is_active'] ? 'Active' : 'Inactive', $coverage,
        $r['active_hours'], $r['days_worked'], $r['avg_hours_day'], $r['blocks'],
        $r['packages_added'], $r['packages_edited'], $r['deletions'],
        $r['consolidations'], $r['pickups'], $r['logins'],
        $r['per_hour'], $r['first_at'], $r['last_at'], $r['events'],
    ]);
}

fputcsv($out, []);
fputcsv($out, [
    'TOTAL', '', '', '', '',
    $s['totals']['active_hours'], $s['totals']['days_worked'], '', '',
    $s['totals']['packages_added'], $s['totals']['packages_edited'], '',
    '', '', '',
    $s['totals']['per_hour'], '', '', $s['totals']['events'],
]);

// ── Section 2: day by day, per staff member ─────────────────────────────────
fputcsv($out, []);
fputcsv($out, ['DAY BY DAY']);
fputcsv($out, [
    'Staff', 'Date', 'Weekday', 'Active Hours', 'First Seen', 'Last Seen',
    'Working Blocks', 'Packages Added', 'Packages Edited', 'Logins', 'Total Actions',
    'Block Times',
]);

foreach ($s['rows'] as $r) {
    foreach (cdp_spDailyDetail($r['user_id'], $from, $to, $gap) as $d) {
        $blocks = [];
        foreach ($d['blocks'] as $b) {
            $blocks[] = $b['from'] . '-' . $b['to'] . ' (' . $b['minutes'] . 'm)';
        }
        fputcsv($out, [
            $r['name'], $d['date'], $d['weekday'], $d['active_hours'],
            $d['first_seen'], $d['last_seen'], $d['block_count'],
            $d['packages_added'], $d['packages_edited'], $d['logins'], $d['events'],
            implode(' | ', $blocks),
        ]);
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
        'meta'    => ['from' => $from, 'to' => $to, 'gap' => $gap, 'staff' => count($s['rows'])],
    ]);
}
