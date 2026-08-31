<?php
// *************************************************************************
// * Staff Productivity — the figures, and one staff member's day detail.  *
// *                                                                       *
// * ?action=summary (default)  JSON: per-staff rows, totals, day and      *
// *                            hour-of-day distributions                   *
// * ?action=detail&user_id=N   HTML: that person's day-by-day working      *
// *                            blocks                                      *
// *                                                                       *
// * Admin and Super Admin only — the permission is checked AND the role's  *
// * is_admin flag, so a mis-grant to Employee cannot open a report about   *
// * Employees. See helpers/staff_activity.php::cdp_spCanView().            *
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

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// ── Filters ─────────────────────────────────────────────────────────────────
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

// The idle gap is what "active" means, so it is shown and adjustable on the
// page rather than buried in the code.
$gap = (int) ($_REQUEST['gap'] ?? CDP_SP_IDLE_GAP);
if (!in_array($gap, [5, 10, 15, 30, 60], true)) {
    $gap = CDP_SP_IDLE_GAP;
}

// ---------------------------------------------------------------------------
// One staff member's day-by-day detail
// ---------------------------------------------------------------------------
if (($_REQUEST['action'] ?? '') === 'detail') {

    $uid = (int) ($_REQUEST['detail_user'] ?? 0);
    $staff = cdp_spStaffUsers();

    if ($uid <= 0 || !isset($staff[$uid])) {
        echo '<div class="alert alert-warning mb-0">That is not a staff account.</div>';
        exit;
    }

    $days = cdp_spDailyDetail($uid, $from, $to, $gap);
    $u = $staff[$uid];

    // Idle on a day before the activity trail started is the gap between two
    // package actions, not a break — the trail simply was not recording
    // anything else. Those days show no idle figure at all.
    $cutDay = cdp_spCutover() ? substr(cdp_spCutover(), 0, 10) : null;
    ?>
    <div class="sp-detail">
        <div class="sp-detail__who">
            <div>
                <div class="sp-detail__name"><?php echo $e($u->display_name); ?></div>
                <div class="text-muted" style="font-size:.8rem;">
                    <?php echo $e($u->role_name); ?> · <?php echo $e($u->username); ?>
                    <?php if ((int) $u->active !== 1) : ?>
                        <span class="sp-pill sp-pill--off">Inactive Account</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="sp-k">Days Shown</div>
                <div class="sp-detail__n"><?php echo count($days); ?></div>
            </div>
        </div>

        <?php if (!$days) : ?>
            <div class="sp-empty">No recorded activity for this person in the selected period.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm sp-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:130px;">Date</th>
                            <th style="width:95px;" class="text-right">Active</th>
                            <th style="width:90px;" class="text-right">Idle</th>
                            <th style="width:80px;" class="text-right">Used</th>
                            <th style="width:80px;">First</th>
                            <th style="width:80px;">Last</th>
                            <th class="text-right" style="width:95px;">Packages</th>
                            <th class="text-right" style="width:80px;">Edits</th>
                            <th>Working Blocks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $d) : ?>
                        <tr>
                            <td class="text-nowrap">
                                <b><?php echo $e($d['date']); ?></b>
                                <small class="text-muted"><?php echo $e($d['weekday']); ?></small>
                            </td>
                            <td class="text-right"><b><?php echo $e(cdp_spDuration($d['active_hours'] * 3600)); ?></b></td>
                            <?php $idleOk = ($cutDay !== null && $d['date'] >= $cutDay); ?>
                            <td class="text-right text-muted">
                                <?php echo $idleOk
                                    ? $e(cdp_spDuration($d['idle_hours'] * 3600))
                                    : '<span title="Before the activity trail started, so breaks cannot be told from gaps in recording.">&mdash;</span>'; ?>
                            </td>
                            <td class="text-right">
                                <?php echo ($idleOk && $d['utilisation'] > 0) ? $e($d['utilisation']) . '%' : '&mdash;'; ?>
                            </td>
                            <td><?php echo $e($d['first_seen']); ?></td>
                            <td><?php echo $e($d['last_seen']); ?></td>
                            <td class="text-right"><?php echo (int) $d['packages_added']; ?></td>
                            <td class="text-right text-muted"><?php echo (int) $d['packages_edited']; ?></td>
                            <td>
                                <?php foreach ($d['blocks'] as $b) : ?>
                                    <span class="sp-block" title="<?php echo (int) $b['events']; ?> actions">
                                        <?php echo $e($b['from'] . '–' . $b['to']); ?>
                                        <small><?php echo (int) $b['minutes']; ?>m</small>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="sp-note mt-3">
                A working block is a run of activity with no gap longer than
                <b><?php echo (int) $gap; ?> minutes</b>. Active time is the sum of those blocks,
                so it measures time spent working in the system rather than time signed in.
                <b>Idle</b> is the rest of the working window — first action to last action that
                day — so it is the breaks between spells of work, not time after they stopped.
            </p>
        <?php endif; ?>
    </div>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

$s = cdp_spSummary($from, $to, $userIds, $gap);

// Durations formatted server-side so the table, the tiles and the CSV all read
// the same.
foreach ($s['rows'] as &$r) {
    $r['active_label'] = cdp_spDuration($r['active_seconds']);
    $r['idle_label']   = cdp_spDuration($r['idle_seconds']);
    $r['span_label']   = cdp_spDuration($r['span_seconds']);
}
unset($r);

$byDay = [];
foreach ($s['by_day'] as $day => $v) {
    $byDay[] = [
        'date'     => $day,
        'hours'    => round($v['seconds'] / 3600, 2),
        'idle'     => round(max(0, ($v['span'] ?? 0) - $v['seconds']) / 3600, 2),
        'packages' => (int) $v['packages'],
        'events'   => (int) $v['events'],
    ];
}

$byHour = [];
foreach ($s['by_hour'] as $h => $v) {
    $byHour[] = [
        'hour'     => sprintf('%02d:00', $h),
        'hours'    => round($v['seconds'] / 3600, 2),
        'packages' => (int) $v['packages'],
        'events'   => (int) $v['events'],
    ];
}

echo json_encode([
    'ok'      => true,
    'rows'    => $s['rows'],
    'totals'  => $s['totals'],
    'by_day'  => $byDay,
    'by_hour' => $byHour,
    'gap'     => $gap,
    // The page tells the reader where the numbers stop being complete.
    'cutover' => $s['cutover'],
]);
