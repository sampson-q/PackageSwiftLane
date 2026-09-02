<?php
// *************************************************************************
// * Staff Productivity — the figures, one person's days, one person's day. *
// *                                                                       *
// * ?action=summary (default)      JSON: per-staff rows, totals, day and   *
// *                                hour distributions, date×hour heatmap   *
// * ?action=detail&detail_user=N   HTML: that person's days — check-in,    *
// *                                check-out, active, idle, packages       *
// * ?action=day&detail_user=N&day=Y-m-d                                    *
// *                                HTML: that day's timeline — every        *
// *                                active / idle stretch with the actions   *
// *                                and shipment ids inside it               *
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

$settings = cdp_spSettings();
$action   = (string) ($_REQUEST['action'] ?? 'summary');

// Shared bits of markup ------------------------------------------------------

/** The pill that says how the day's figures were obtained. */
function cdp_spCoveragePill($coverage)
{
    switch ($coverage) {
        case 'presence':
            return '<span class="sp-pill sp-pill--ok" title="Keyboard, mouse and screen input was recorded for this day, so idle time is measured, not guessed.">presence data</span>';
        case 'log':
            return '<span class="sp-pill sp-pill--mid" title="No presence data for this day — only recorded actions. Idle is the gaps between actions longer than the legacy gap setting.">actions only</span>';
        default:
            return '<span class="sp-pill sp-pill--thin" title="Before the activity trail started only package actions were recorded, so breaks cannot be told from gaps in recording. No idle figure is shown.">package actions only</span>';
    }
}

/** How the day started. */
function cdp_spCheckInPill(array $d, $e)
{
    if ($d['check_in_kind'] === 'package') {
        $ref = $d['check_in_ref'] !== '' ? $d['check_in_ref'] : 'package';
        return '<span class="sp-pill sp-pill--pkg" title="Check-in: the first package created or edited that day.">' . $e($ref) . '</span>';
    }
    return '<span class="sp-pill sp-pill--act" title="No package was created or edited this day, so the first recorded activity stands in as the check-in.">first activity</span>';
}

/** Shipment ids as chips. */
function cdp_spRefChips(array $refs, $e, $class = 'sp-ref')
{
    if (!$refs) {
        return '<span class="text-muted">—</span>';
    }
    $out = [];
    foreach ($refs as $r) {
        $out[] = '<span class="' . $class . '">' . $e($r) . '</span>';
    }
    return implode(' ', $out);
}

// ---------------------------------------------------------------------------
// One day, told as a timeline
// ---------------------------------------------------------------------------
if ($action === 'day') {

    $uid = (int) ($_REQUEST['detail_user'] ?? 0);
    $day = (string) ($_REQUEST['day'] ?? '');
    $staff = cdp_spStaffUsers();

    if ($uid <= 0 || !isset($staff[$uid])) {
        echo '<div class="alert alert-warning mb-0">That is not a staff account.</div>';
        exit;
    }
    $d = cdp_spDay($uid, $day);
    $u = $staff[$uid];
    if ($d === null) {
        echo '<div class="sp-empty">No recorded activity for ' . $e($u->display_name) . ' on ' . $e($day) . '.</div>';
        exit;
    }

    $window = max(1, $d['check_out'] - $d['check_in']);
    $pos = function ($ts) use ($d, $window) {
        return max(0, min(100, (($ts - $d['check_in']) / $window) * 100));
    };
    ?>
    <div class="sp-day">
        <div class="sp-detail__who">
            <div>
                <button type="button" class="btn btn-sm btn-light sp-back" onclick="cdpSpDetail(<?php echo (int) $uid; ?>)">
                    <i class="fa fa-arrow-left"></i> All Days
                </button>
                <span class="sp-detail__name ml-2"><?php echo $e($u->display_name); ?></span>
                <span class="text-muted ml-2" style="font-size:.85rem;">
                    <?php echo $e(date('l, j F Y', $d['check_in'])); ?>
                </span>
                <?php echo cdp_spCoveragePill($d['coverage']); ?>
            </div>
            <div class="text-right text-nowrap">
                <div class="sp-k">Check-In → Check-Out</div>
                <div class="sp-detail__n">
                    <?php echo $e($d['check_in_time']); ?> <span class="text-muted">→</span> <?php echo $e($d['check_out_time']); ?>
                </div>
                <div><?php echo cdp_spCheckInPill($d, $e); ?></div>
            </div>
        </div>

        <div class="sp-kpis sp-kpis--tight">
            <div class="sp-kpi" style="--c:#336aea"><div class="sp-k">Active</div><div class="sp-kpi__v"><?php echo $e(cdp_spDuration($d['active_seconds'])); ?></div><div class="sp-kpi__s"><?php echo (int) $d['blocks']; ?> working stretch<?php echo $d['blocks'] === 1 ? '' : 'es'; ?></div></div>
            <div class="sp-kpi" style="--c:#b4770d"><div class="sp-k">Idle</div><div class="sp-kpi__v"><?php echo $d['idle_reliable'] ? $e(cdp_spDuration($d['idle_seconds'])) : '—'; ?></div><div class="sp-kpi__s"><?php echo $d['idle_reliable'] ? 'Pauses inside the working window' : 'Not measurable for this day'; ?></div></div>
            <div class="sp-kpi" style="--c:#7d8fa9"><div class="sp-k">Utilisation</div><div class="sp-kpi__v"><?php echo $d['idle_reliable'] ? $e($d['utilisation']) . '%' : '—'; ?></div><div class="sp-kpi__s">Window <?php echo $e(cdp_spDuration($d['window_seconds'])); ?></div></div>
            <div class="sp-kpi" style="--c:#0aa699"><div class="sp-k">Packages Created</div><div class="sp-kpi__v"><?php echo (int) $d['created_count']; ?></div><div class="sp-kpi__s">Edited <?php echo (int) $d['edited_count']; ?></div></div>
            <div class="sp-kpi" style="--c:#9b6ef3"><div class="sp-k">Actions</div><div class="sp-kpi__v"><?php echo (int) $d['events']; ?></div><div class="sp-kpi__s">Recorded this day</div></div>
        </div>

        <?php if ($d['pre_checkin_events'] > 0 || $d['pre_checkin_minutes'] > 0) : ?>
            <p class="sp-note mb-2">
                Before the check-in at <b><?php echo $e($d['check_in_time']); ?></b>:
                <?php if ($d['pre_checkin_events'] > 0) : ?><b><?php echo (int) $d['pre_checkin_events']; ?></b> action<?php echo $d['pre_checkin_events'] === 1 ? '' : 's'; ?><?php endif; ?>
                <?php if ($d['pre_checkin_events'] > 0 && $d['pre_checkin_minutes'] > 0) : ?> and <?php endif; ?>
                <?php if ($d['pre_checkin_minutes'] > 0) : ?><b><?php echo (int) $d['pre_checkin_minutes']; ?></b> minute<?php echo $d['pre_checkin_minutes'] === 1 ? '' : 's'; ?> with input<?php endif; ?>
                were recorded. They are not part of the working window.
            </p>
        <?php endif; ?>

        <!-- ── The strip ─────────────────────────────────────────────── -->
        <div class="sp-strip-wrap">
            <div class="sp-strip">
                <?php foreach ($d['segments'] as $seg) :
                    $left  = $pos($seg['start']);
                    $width = max(0.15, ($seg['seconds'] / $window) * 100);
                    ?>
                    <div class="sp-strip__seg sp-strip__seg--<?php echo $e($seg['type']); ?>"
                         style="left:<?php echo $left; ?>%;width:<?php echo min($width, 100 - $left); ?>%"
                         title="<?php echo $e(ucfirst($seg['type']) . ' ' . $seg['from'] . '–' . $seg['to'] . ' (' . $seg['label'] . ')'); ?>"></div>
                <?php endforeach; ?>
                <?php foreach ($d['segments'] as $seg) :
                    if ($seg['type'] !== 'active') { continue; }
                    foreach ($seg['actions'] as $act) :
                        if ($act['entity'] !== 'package') { continue; }
                        $isCreate = $act['verb'] === 'create';
                        $isEdit   = $act['verb'] === 'update' || $act['verb'] === 'status';
                        if (!$isCreate && !$isEdit) { continue; }
                        ?>
                        <span class="sp-strip__dot sp-strip__dot--<?php echo $isCreate ? 'create' : 'edit'; ?>"
                              style="left:<?php echo $pos($act['at']); ?>%"
                              title="<?php echo $e($act['time'] . ' · ' . $act['label']); ?>"></span>
                    <?php endforeach;
                endforeach; ?>
            </div>
            <div class="sp-strip__axis">
                <span style="left:0%"><?php echo $e($d['check_in_time']); ?></span>
                <?php
                $tick = (int) (ceil($d['check_in'] / 3600) * 3600);
                $step = $window > 12 * 3600 ? 7200 : 3600;
                for (; $tick < $d['check_out']; $tick += $step) :
                    $p = $pos($tick);
                    if ($p < 5 || $p > 95) { continue; }
                    ?>
                    <span style="left:<?php echo $p; ?>%"><?php echo $e(date('H:i', $tick)); ?></span>
                <?php endfor; ?>
                <span style="left:100%" class="sp-strip__axis-end"><?php echo $e($d['check_out_time']); ?></span>
            </div>
            <div class="sp-legend">
                <span><i class="sp-legend__sw" style="background:#336aea"></i>Active</span>
                <span><i class="sp-legend__sw" style="background:#f0c674"></i>Idle</span>
                <span><i class="sp-legend__dot" style="background:#0aa699"></i>Package created</span>
                <span><i class="sp-legend__dot" style="background:#9b6ef3"></i>Package edited</span>
            </div>
        </div>

        <!-- ── Segment by segment ────────────────────────────────────── -->
        <div class="sp-segments">
            <?php foreach ($d['segments'] as $i => $seg) : ?>
                <?php if ($seg['type'] === 'idle') : ?>
                    <div class="sp-seg sp-seg--idle">
                        <div class="sp-seg__head">
                            <span class="sp-seg__type">Idle</span>
                            <span class="sp-seg__time"><?php echo $e($seg['from']); ?> – <?php echo $e($seg['to']); ?></span>
                            <span class="sp-seg__dur"><?php echo $e($seg['label']); ?></span>
                            <span class="sp-seg__meta text-muted">
                                <?php echo $d['mode'] === 'presence' ? 'No keyboard, mouse or screen input' : 'No recorded action'; ?>
                            </span>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="sp-seg sp-seg--active">
                        <div class="sp-seg__head">
                            <span class="sp-seg__type">Active</span>
                            <span class="sp-seg__time"><?php echo $e($seg['from']); ?> – <?php echo $e($seg['to']); ?></span>
                            <span class="sp-seg__dur"><?php echo $e($seg['label']); ?></span>
                            <span class="sp-seg__meta text-muted">
                                <?php echo (int) $seg['events']; ?> action<?php echo $seg['events'] === 1 ? '' : 's'; ?>
                                <?php if ($seg['created']) : ?> · created <b><?php echo count($seg['created']); ?></b><?php endif; ?>
                                <?php if ($seg['edited']) : ?> · edited <b><?php echo count($seg['edited']); ?></b><?php endif; ?>
                            </span>
                        </div>
                        <?php if ($seg['created'] || $seg['edited']) : ?>
                            <div class="sp-seg__refs">
                                <?php if ($seg['created']) : ?>
                                    <div><span class="sp-k">Created</span> <?php echo cdp_spRefChips($seg['created'], $e, 'sp-ref sp-ref--create'); ?></div>
                                <?php endif; ?>
                                <?php if ($seg['edited']) : ?>
                                    <div><span class="sp-k">Edited</span> <?php echo cdp_spRefChips($seg['edited'], $e, 'sp-ref sp-ref--edit'); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($seg['actions']) : ?>
                            <details class="sp-seg__actions">
                                <summary>Every action in this stretch (<?php echo count($seg['actions']); ?>)</summary>
                                <table class="table table-sm sp-table mb-0">
                                    <thead><tr><th style="width:90px;">Time</th><th>Action</th><th style="width:170px;">Record</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($seg['actions'] as $act) : ?>
                                        <tr>
                                            <td class="text-muted"><?php echo $e($act['time']); ?></td>
                                            <td><?php echo $e($act['label']); ?></td>
                                            <td><?php echo $act['ref'] !== '' ? '<span class="sp-ref">' . $e($act['ref']) . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- ── The day in one line ───────────────────────────────────── -->
        <div class="sp-day__totals">
            <div>
                <span class="sp-k">Packages Created Today (<?php echo (int) $d['created_count']; ?>)</span>
                <div class="mt-1"><?php echo cdp_spRefChips($d['packages_created'], $e, 'sp-ref sp-ref--create'); ?></div>
            </div>
            <div class="mt-2">
                <span class="sp-k">Packages Edited Today (<?php echo (int) $d['edited_count']; ?>)</span>
                <div class="mt-1"><?php echo cdp_spRefChips($d['packages_edited'], $e, 'sp-ref sp-ref--edit'); ?></div>
            </div>
            <?php if ($d['consolidations'] || $d['pickups'] || $d['deletions']) : ?>
                <div class="mt-2 text-muted" style="font-size:.8rem;">
                    Also: <?php echo (int) $d['consolidations']; ?> consolidation action<?php echo $d['consolidations'] === 1 ? '' : 's'; ?>,
                    <?php echo (int) $d['pickups']; ?> pickup action<?php echo $d['pickups'] === 1 ? '' : 's'; ?>,
                    <?php echo (int) $d['deletions']; ?> deletion<?php echo $d['deletions'] === 1 ? '' : 's'; ?>.
                </div>
            <?php endif; ?>
        </div>

        <p class="sp-note mt-3 mb-0">
            <?php if ($d['mode'] === 'presence') : ?>
                <b>Presence data</b> was recorded for this day. A minute counts as active when any keyboard,
                mouse or screen input reached this app, or an action was recorded. A pause longer than
                <b><?php echo (int) $settings['idle_minutes']; ?> minutes</b> with no input at all is idle, in full;
                shorter pauses count as active. Work done outside this app (a phone call, WhatsApp on another
                device) is not visible here and reads as idle.
            <?php else : ?>
                <b>No presence data</b> for this day, so only recorded actions are known. A working stretch is a
                run of actions with no gap longer than <b><?php echo (int) $settings['gap_minutes']; ?> minutes</b>;
                the gaps between stretches are shown as idle.
                <?php if (!$d['idle_reliable']) : ?>
                    This day predates the activity trail — only package actions were recorded — so the idle
                    figure is withheld: it would mostly measure gaps in our own records.
                <?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// One staff member's days
// ---------------------------------------------------------------------------
if ($action === 'detail') {

    $uid = (int) ($_REQUEST['detail_user'] ?? 0);
    $staff = cdp_spStaffUsers();

    if ($uid <= 0 || !isset($staff[$uid])) {
        echo '<div class="alert alert-warning mb-0">That is not a staff account.</div>';
        exit;
    }

    $days = cdp_spDailyDetail($uid, $from, $to);
    $u = $staff[$uid];

    $tActive = 0; $tIdle = 0; $tWindow = 0; $tCreated = 0; $tEdited = 0; $checkins = 0; $relDays = 0;
    $heat = [];
    foreach ($days as $d) {
        $tActive += $d['active_seconds'];
        if ($d['idle_reliable']) {
            $tIdle += $d['idle_seconds'];
            $tWindow += $d['window_seconds'];
            $relDays++;
        }
        $tCreated += $d['created_count'];
        $tEdited  += $d['edited_count'];
        if ($d['check_in_kind'] === 'package') {
            $checkins++;
        }
        $heat[] = ['date' => $d['date'], 'minutes' => array_map(function ($s) { return (int) round($s / 60); }, $d['hour_seconds'])];
    }
    $heat = array_reverse($heat); // oldest first for the chart
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
                <div class="sp-k">Days · Check-Ins</div>
                <div class="sp-detail__n"><?php echo count($days); ?> <span class="text-muted">·</span> <?php echo $checkins; ?></div>
            </div>
        </div>

        <?php if (!$days) : ?>
            <div class="sp-empty">No recorded activity for this person in the selected period.</div>
        <?php else : ?>
            <div class="sp-kpis sp-kpis--tight">
                <div class="sp-kpi" style="--c:#336aea"><div class="sp-k">Active</div><div class="sp-kpi__v"><?php echo $e(cdp_spDuration($tActive)); ?></div><div class="sp-kpi__s">All days shown</div></div>
                <div class="sp-kpi" style="--c:#b4770d"><div class="sp-k">Idle</div><div class="sp-kpi__v"><?php echo $relDays ? $e(cdp_spDuration($tIdle)) : '—'; ?></div><div class="sp-kpi__s"><?php echo $relDays ? $relDays . ' of ' . count($days) . ' days measurable' : 'Not measurable'; ?></div></div>
                <div class="sp-kpi" style="--c:#7d8fa9"><div class="sp-k">Utilisation</div><div class="sp-kpi__v"><?php echo $tWindow > 0 ? round((($tWindow - $tIdle) / $tWindow) * 100, 1) . '%' : '—'; ?></div><div class="sp-kpi__s">Active share of the working window</div></div>
                <div class="sp-kpi" style="--c:#0aa699"><div class="sp-k">Packages Created</div><div class="sp-kpi__v"><?php echo (int) $tCreated; ?></div><div class="sp-kpi__s">Edited <?php echo (int) $tEdited; ?></div></div>
            </div>

            <div class="sp-card mb-3">
                <div class="sp-card__h">Active Minutes By Hour <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">darker = more of that hour spent working</span></div>
                <div class="sp-card__b" style="padding:8px 12px;">
                    <div id="sp_detail_heat" data-heat="<?php echo $e(json_encode($heat)); ?>"></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover sp-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:120px;">Date</th>
                            <th style="width:150px;">Check-In</th>
                            <th style="width:75px;">Check-Out</th>
                            <th style="width:85px;" class="text-right">Active</th>
                            <th style="width:80px;" class="text-right">Idle</th>
                            <th style="width:70px;" class="text-right">Used</th>
                            <th style="width:70px;" class="text-right">Stretches</th>
                            <th style="width:80px;" class="text-right">Created</th>
                            <th style="width:70px;" class="text-right">Edited</th>
                            <th>Detail</th>
                            <th style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $d) : ?>
                        <tr class="sp-row" onclick="cdpSpDay(<?php echo (int) $uid; ?>, '<?php echo $e($d['date']); ?>')">
                            <td class="text-nowrap">
                                <b><?php echo $e($d['date']); ?></b>
                                <small class="text-muted"><?php echo $e($d['weekday']); ?></small>
                            </td>
                            <td class="text-nowrap"><b><?php echo $e($d['check_in_time']); ?></b> <?php echo cdp_spCheckInPill($d, $e); ?></td>
                            <td><?php echo $e($d['check_out_time']); ?></td>
                            <td class="text-right"><b><?php echo $e(cdp_spDuration($d['active_seconds'])); ?></b></td>
                            <td class="text-right text-muted">
                                <?php echo $d['idle_reliable']
                                    ? $e(cdp_spDuration($d['idle_seconds']))
                                    : '<span title="Before the activity trail started, so breaks cannot be told from gaps in recording.">&mdash;</span>'; ?>
                            </td>
                            <td class="text-right"><?php echo ($d['idle_reliable'] && $d['window_seconds'] > 0) ? $e($d['utilisation']) . '%' : '&mdash;'; ?></td>
                            <td class="text-right"><?php echo (int) $d['blocks']; ?></td>
                            <td class="text-right"><b><?php echo (int) $d['created_count']; ?></b></td>
                            <td class="text-right text-muted"><?php echo (int) $d['edited_count']; ?></td>
                            <td><?php echo cdp_spCoveragePill($d['coverage']); ?></td>
                            <td class="text-right"><span class="btn btn-xs btn-outline-dark sp-btn-xs">Timeline</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="sp-note mt-3 mb-0">
                <b>Check-in</b> is the first package created or edited that day; a day without one uses its first
                recorded activity and is marked so. <b>Check-out</b> is the end of the last recorded activity.
                Click a day for the minute-by-minute timeline.
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

$s = cdp_spSummary($from, $to, $userIds);

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
        'idle'     => round(max(0, $v['span'] - $v['rel_seconds']) / 3600, 2),
        'packages' => (int) $v['packages'],
        'events'   => (int) $v['events'],
        'staff'    => (int) $v['staff'],
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
    'ok'       => true,
    'rows'     => $s['rows'],
    'totals'   => $s['totals'],
    'by_day'   => $byDay,
    'by_hour'  => $byHour,
    'heat'     => $s['heat'],
    'settings' => $s['settings'],
    'cutover'  => $s['cutover'],
    'presence' => cdp_spPresenceTableReady(),
]);
