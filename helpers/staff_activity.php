<?php
/**
 * ============================================================================
 * Staff Productivity — the engine behind the Staff Productivity report.
 *
 * Answers, per staff member and per period: how long were they actually
 * working in the system, how many packages did they register, what else did
 * they touch, and when did they start and finish.
 *
 * WHERE THE DATA COMES FROM
 * -------------------------
 * Nothing new is recorded for this report. It reads one unified event stream
 * built from two existing tables:
 *
 *   · cdb_order_user_history — 140k+ rows going back to 2022, with a real
 *     datetime and the acting user. Package/consolidation/pickup events only,
 *     and its `action` text is free-form ("create shipment", "Shipment
 *     created", "Updated shipment"), so it is normalised by keyword here.
 *
 *   · cdb_activity_log — everything, from the moment the audit trail was
 *     deployed (see helpers/activity_log.php).
 *
 * Both tables receive a row when a package is created, so reading both in full
 * would double-count. The CUTOVER is the activity log's first row: history is
 * used strictly before it, the activity log strictly from it onwards.
 *
 * WHAT "ACTIVE HOURS" MEANS
 * -------------------------
 * Nothing in this system sends a heartbeat, so there is no true "logged in for
 * 6h12m" to read. Active hours are RECONSTRUCTED: a person's events are sorted
 * and split into blocks wherever the gap between two consecutive events exceeds
 * CDP_SP_IDLE_GAP minutes. The sum of those blocks is the active time.
 *
 * This measures time spent working, not time with a tab open — somebody who
 * signs in and walks away is not credited. Every screen that shows this number
 * must call it "active", never "attendance".
 *
 * WHAT "IDLE HOURS" MEANS
 * ----------------------
 * The working window is a day's first action to its last. Idle is whatever
 * inside that window was not part of an active block — the breaks between
 * spells of work. Utilisation is active ÷ window.
 *
 * That is the only inactivity this system can honestly report. Time signed in
 * but doing nothing is NOT knowable here: there is no heartbeat and no reliable
 * session end, so nothing records somebody leaving a tab open at six o'clock.
 * Do not add a figure that claims to measure it.
 * ============================================================================
 */

require_once __DIR__ . '/rbac.php';

/** A gap longer than this (minutes) ends an active block. */
if (!defined('CDP_SP_IDLE_GAP')) {
    define('CDP_SP_IDLE_GAP', 15);
}

/**
 * Credit for a block containing a single event, in seconds.
 *
 * A lone action has a duration of zero by definition (first event == last
 * event), which would report a real piece of work as no time at all. One
 * minute is the smallest honest non-zero credit.
 */
if (!defined('CDP_SP_MIN_BLOCK')) {
    define('CDP_SP_MIN_BLOCK', 60);
}

// ---------------------------------------------------------------------------
// Who counts as staff
// ---------------------------------------------------------------------------

/**
 * Role ids flagged is_staff — Administrator, Employee, Super Admin on this
 * install. Customers, drivers and agencies are deliberately excluded: customers
 * register their own shipments through courier_add_client and would otherwise
 * dominate every figure on the report.
 *
 * @return int[]
 */
function cdp_spStaffRoleIds()
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = [];
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT role_id FROM cdb_user_roles
                        WHERE rol_active = 1 AND (is_staff = 1 OR is_admin = 1 OR is_superadmin = 1)");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $ids[] = (int) $r->role_id;
        }
    } catch (Throwable $e) {
        $ids = [];
    }
    if (!$ids) {
        $ids = [2, 4, 9]; // sane fallback if the flags are missing
    }
    return $ids;
}

/**
 * Every staff account, for the filter dropdown and for labelling rows.
 *
 * @return array<int,object> keyed by user id
 */
function cdp_spStaffUsers()
{
    static $users = null;
    if ($users !== null) {
        return $users;
    }
    $users = [];
    $roles = implode(',', array_map('intval', cdp_spStaffRoleIds()));
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT u.id, u.username, u.fname, u.lname, u.userlevel, u.active, u.lastlogin,
                               r.role_name
                        FROM cdb_users u
                        LEFT JOIN cdb_user_roles r ON r.role_id = u.userlevel
                        WHERE u.userlevel IN ($roles)
                        ORDER BY u.fname, u.lname");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $r->display_name = trim($r->fname . ' ' . $r->lname);
            if ($r->display_name === '') {
                $r->display_name = (string) $r->username;
            }
            $users[(int) $r->id] = $r;
        }
    } catch (Throwable $e) {
        $users = [];
    }
    return $users;
}

// ---------------------------------------------------------------------------
// The event stream
// ---------------------------------------------------------------------------

/**
 * The instant the activity log took over. History is read strictly before it.
 *
 * @return string|null 'Y-m-d H:i:s', or null when the log is empty/absent
 */
function cdp_spCutover()
{
    static $cut = false;
    if ($cut !== false) {
        return $cut;
    }
    $cut = null;
    try {
        $db = new Conexion;
        $db->cdp_query("SHOW TABLES LIKE 'cdb_activity_log'");
        $db->cdp_execute();
        if ($db->cdp_rowCount() > 0) {
            $db->cdp_query("SELECT MIN(created_at) AS c FROM cdb_activity_log");
            $db->cdp_execute();
            $row = $db->cdp_registro();
            $cut = ($row && $row->c) ? (string) $row->c : null;
        }
    } catch (Throwable $e) {
        $cut = null;
    }
    return $cut;
}

/**
 * Normalise cdb_order_user_history's free-text action into a verb + entity.
 *
 * @param string $action
 * @param int    $isConsolidate
 * @return array{verb:string,entity:string}
 */
function cdp_spClassifyHistory($action, $isConsolidate)
{
    $a = strtolower(trim((string) $action));

    $verb = 'other';
    if (strpos($a, 'cancel') !== false || strpos($a, 'delete') !== false || strpos($a, 'remov') !== false) {
        $verb = 'delete';
    } elseif (strpos($a, 'creat') !== false || strpos($a, 'add') !== false || strpos($a, 'register') !== false) {
        $verb = 'create';
    } elseif (strpos($a, 'updat') !== false || strpos($a, 'edit') !== false || strpos($a, 'chang') !== false) {
        $verb = 'update';
    }

    $entity = 'package';
    if ((int) $isConsolidate === 1 || strpos($a, 'consolidat') !== false) {
        $entity = 'consolidation';
    } elseif (strpos($a, 'pickup') !== false || strpos($a, 'pick up') !== false) {
        $entity = 'pickup';
    }

    return ['verb' => $verb, 'entity' => $entity];
}

/**
 * Map an activity-log row's module onto this report's entity vocabulary.
 *
 * @param string $module
 * @return string
 */
function cdp_spEntityForModule($module)
{
    switch ($module) {
        case 'packages':
        case 'shipments':
            return 'package';
        case 'consolidations':
            return 'consolidation';
        case 'pickups':
            return 'pickup';
        default:
            return 'other';
    }
}

/**
 * The unified event stream for a period and a set of staff.
 *
 * Every event is used for the active-hours reconstruction — including page
 * views, which are the strongest presence signal the system has. Only `create`
 * events on packages are counted as packages added.
 *
 * @param string $from    'Y-m-d' inclusive, '' for no lower bound
 * @param string $to      'Y-m-d' inclusive, '' for no upper bound
 * @param int[]  $userIds Restrict to these staff (empty = all staff)
 * @return array<int,array{at:int,verb:string,entity:string,source:string,user_id:int}>
 */
function cdp_spEvents($from, $to, array $userIds = [])
{
    $staff = cdp_spStaffUsers();
    $ids = $userIds ? array_values(array_intersect($userIds, array_keys($staff))) : array_keys($staff);
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_map('intval', $ids));

    $lo = $from !== '' ? $from . ' 00:00:00' : '1970-01-01 00:00:00';
    $hi = $to   !== '' ? $to . ' 23:59:59'   : '2999-12-31 23:59:59';
    $cut = cdp_spCutover();

    $events = [];
    $db = new Conexion;

    // ── Historical half: cdb_order_user_history, strictly before the cutover ──
    $histHi = ($cut !== null && $cut < $hi) ? $cut : $hi;
    if ($histHi > $lo) {
        try {
            $db->cdp_query("SELECT user_id, action, date_history, is_consolidate
                            FROM cdb_order_user_history
                            WHERE user_id IN ($in)
                              AND date_history >= :lo AND date_history < :hi
                            ORDER BY user_id, date_history");
            $db->bind(':lo', $lo);
            $db->bind(':hi', $histHi);
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $r) {
                $c = cdp_spClassifyHistory($r->action, $r->is_consolidate);
                $events[] = [
                    'user_id' => (int) $r->user_id,
                    'at'      => strtotime((string) $r->date_history),
                    'verb'    => $c['verb'],
                    'entity'  => $c['entity'],
                    'source'  => 'history',
                ];
            }
        } catch (Throwable $e) {
            // A missing history table just means no pre-cutover data.
        }
    }

    // ── Current half: cdb_activity_log, from the cutover onwards ─────────────
    if ($cut !== null) {
        $logLo = ($cut > $lo) ? $cut : $lo;
        if ($logLo <= $hi) {
            try {
                $db->cdp_query("SELECT user_id, verb, module, created_at
                                FROM cdb_activity_log
                                WHERE user_id IN ($in)
                                  AND created_at >= :lo AND created_at <= :hi
                                ORDER BY user_id, created_at");
                $db->bind(':lo', $logLo);
                $db->bind(':hi', $hi);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $r) {
                    $events[] = [
                        'user_id' => (int) $r->user_id,
                        'at'      => strtotime((string) $r->created_at),
                        'verb'    => (string) $r->verb,
                        'entity'  => cdp_spEntityForModule((string) $r->module),
                        'source'  => 'log',
                    ];
                }
            } catch (Throwable $e) {
                // No activity log yet — history alone still produces a report.
            }
        }
    }

    // One ordering for the whole stream, since the two halves were read apart.
    usort($events, function ($a, $b) {
        if ($a['user_id'] !== $b['user_id']) {
            return $a['user_id'] - $b['user_id'];
        }
        return $a['at'] - $b['at'];
    });

    return $events;
}

// ---------------------------------------------------------------------------
// Reconstruction
// ---------------------------------------------------------------------------

/**
 * Split each person's events into active blocks.
 *
 * A block ends when the next event is more than $gapMinutes away. A block is
 * attributed to the calendar date of its FIRST event, so one that runs past
 * midnight counts against the day it started — rare, and the alternative
 * (splitting it) reports two part-days nobody worked.
 *
 * @param array $events From cdp_spEvents()
 * @param int   $gapMinutes
 * @return array<int,array<int,array{start:int,end:int,seconds:int,events:int}>> keyed by user id
 */
function cdp_spSessionize(array $events, $gapMinutes = null)
{
    $gap = (int) ($gapMinutes ?: CDP_SP_IDLE_GAP) * 60;
    $out = [];

    $curUser = null;
    $block = null;

    $close = function () use (&$block, &$curUser, &$out) {
        if ($block === null) {
            return;
        }
        $block['seconds'] = max($block['end'] - $block['start'], CDP_SP_MIN_BLOCK);
        $out[$curUser][] = $block;
        $block = null;
    };

    foreach ($events as $e) {
        if ($e['user_id'] !== $curUser) {
            $close();
            $curUser = $e['user_id'];
            if (!isset($out[$curUser])) {
                $out[$curUser] = [];
            }
        }

        if ($block === null) {
            $block = ['start' => $e['at'], 'end' => $e['at'], 'events' => 1];
            continue;
        }

        if (($e['at'] - $block['end']) > $gap) {
            $close();
            $block = ['start' => $e['at'], 'end' => $e['at'], 'events' => 1];
            continue;
        }

        $block['end'] = $e['at'];
        $block['events']++;
    }
    $close();

    return $out;
}

/**
 * Everything the report shows, per staff member, for one period.
 *
 * @param string $from
 * @param string $to
 * @param int[]  $userIds
 * @param int    $gapMinutes
 * @return array{rows:array,totals:array,by_day:array,by_hour:array,cutover:?string}
 */
function cdp_spSummary($from, $to, array $userIds = [], $gapMinutes = null)
{
    $staff  = cdp_spStaffUsers();
    $events = cdp_spEvents($from, $to, $userIds);
    $blocks = cdp_spSessionize($events, $gapMinutes);

    // Per-user counters.
    $acc = [];
    $init = function ($uid) use (&$acc, $staff) {
        if (isset($acc[$uid])) {
            return;
        }
        $u = $staff[$uid] ?? null;
        $acc[$uid] = [
            'user_id'        => $uid,
            'name'           => $u ? $u->display_name : ('User #' . $uid),
            'username'       => $u ? (string) $u->username : '',
            'role'           => $u ? (string) $u->role_name : '',
            'active'         => $u ? (int) $u->active : 1,
            'seconds'        => 0,
            'blocks'         => 0,
            'events'         => 0,
            'packages_added' => 0,
            'packages_edited'=> 0,
            'deletions'      => 0,
            'consolidations' => 0,
            'pickups'        => 0,
            'logins'         => 0,
            'first_at'       => null,
            'last_at'        => null,
            'days'           => [],
            'daymm'          => [],   // day => [first event ts, last event ts]
            'src_history'    => 0,
            'src_log'        => 0,
        ];
    };

    // Day and hour-of-day distributions, across the whole selection.
    $byDay  = [];   // 'Y-m-d' => ['seconds'=>, 'packages'=>, 'events'=>]
    $byHour = array_fill(0, 24, ['seconds' => 0, 'packages' => 0, 'events' => 0]);

    foreach ($events as $e) {
        $uid = $e['user_id'];
        $init($uid);
        $a = &$acc[$uid];

        $a['events']++;
        if ($e['source'] === 'log') {
            $a['src_log']++;
        } else {
            $a['src_history']++;
        }
        $a['first_at'] = $a['first_at'] === null ? $e['at'] : min($a['first_at'], $e['at']);
        $a['last_at']  = $a['last_at']  === null ? $e['at'] : max($a['last_at'],  $e['at']);

        $day = date('Y-m-d', $e['at']);
        $hr  = (int) date('G', $e['at']);
        $a['days'][$day] = true;
        if (!isset($a['daymm'][$day])) {
            $a['daymm'][$day] = [$e['at'], $e['at']];
        } else {
            if ($e['at'] < $a['daymm'][$day][0]) { $a['daymm'][$day][0] = $e['at']; }
            if ($e['at'] > $a['daymm'][$day][1]) { $a['daymm'][$day][1] = $e['at']; }
        }

        if (!isset($byDay[$day])) {
            $byDay[$day] = ['seconds' => 0, 'span' => 0, 'packages' => 0, 'events' => 0];
        }
        $byDay[$day]['events']++;
        $byHour[$hr]['events']++;

        if ($e['verb'] === 'login') {
            $a['logins']++;
        }

        if ($e['entity'] === 'package') {
            if ($e['verb'] === 'create') {
                $a['packages_added']++;
                $byDay[$day]['packages']++;
                $byHour[$hr]['packages']++;
            } elseif ($e['verb'] === 'update' || $e['verb'] === 'status') {
                $a['packages_edited']++;
            } elseif ($e['verb'] === 'delete') {
                $a['deletions']++;
            }
        } elseif ($e['entity'] === 'consolidation') {
            $a['consolidations']++;
        } elseif ($e['entity'] === 'pickup') {
            $a['pickups']++;
        }

        unset($a);
    }

    // Active time, from the reconstructed blocks.
    foreach ($blocks as $uid => $list) {
        $init($uid);
        foreach ($list as $b) {
            $acc[$uid]['seconds'] += $b['seconds'];
            $acc[$uid]['blocks']++;

            $day = date('Y-m-d', $b['start']);
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['seconds' => 0, 'span' => 0, 'packages' => 0, 'events' => 0];
            }
            $byDay[$day]['seconds'] += $b['seconds'];
            $byHour[(int) date('G', $b['start'])]['seconds'] += $b['seconds'];
        }
    }

    // Each day's working window across all staff shown, so the day chart can
    // stack active against idle.
    foreach ($acc as $a) {
        foreach ($a['daymm'] as $day => $mm) {
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['seconds' => 0, 'span' => 0, 'packages' => 0, 'events' => 0];
            }
            $byDay[$day]['span'] += max(0, $mm[1] - $mm[0]);
        }
    }
    foreach ($byDay as $day => $v) {
        if ($v['seconds'] > $v['span']) {
            $byDay[$day]['span'] = $v['seconds'];
        }
    }

    // Shape the rows.
    $rows = [];
    foreach ($acc as $uid => $a) {
        $days = count($a['days']);

        // The working window: for each day, first action to last action. Idle
        // time is whatever inside that window was not part of an active block —
        // the breaks between spells of work.
        //
        // This is the ONLY inactivity the system can honestly report. Time
        // signed in but doing nothing is not knowable: there is no heartbeat
        // and no reliable session end, so nothing records someone leaving a tab
        // open. Anything beyond the window would be invented.
        $span = 0;
        foreach ($a['daymm'] as $mm) {
            $span += max(0, $mm[1] - $mm[0]);
        }
        // A day with a single action has a zero-length window but is credited
        // CDP_SP_MIN_BLOCK of active time, which would make idle negative.
        // The window can never be shorter than the work inside it.
        if ($a['seconds'] > $span) {
            $span = $a['seconds'];
        }
        $idle = max(0, $span - $a['seconds']);

        // How complete is this row? Before the activity log existed the only
        // events on record are package/consolidation/pickup actions — no page
        // views, no logins — so active hours for that period are understated,
        // sometimes badly (a person who registered two packages in a day shows
        // as two minutes). The row says so rather than leaving the reader to
        // infer it from a banner.
        if ($a['src_history'] === 0) {
            $coverage = 'full';
        } elseif ($a['src_log'] === 0) {
            $coverage = 'partial';
        } else {
            $coverage = 'mixed';
        }

        // Idle is only meaningful when the working window is real. Before the
        // activity log existed the only events recorded are package actions, so
        // a full day's work leaves two marks and the window between them reads
        // as hours of idleness. That is an artifact of missing data, not a
        // finding about the person — so the figure is computed but marked
        // unreliable, and the report shows nothing rather than a number that
        // would get somebody blamed for a gap in our own records.
        $idleReliable = ($coverage === 'full');

        $rows[] = [
            'coverage'        => $coverage,
            'idle_reliable'   => $idleReliable,
            'events_history'  => $a['src_history'],
            'events_log'      => $a['src_log'],
            'user_id'         => $uid,
            'name'            => $a['name'],
            'username'        => $a['username'],
            'role'            => $a['role'],
            'is_active'       => $a['active'],
            'active_seconds'  => $a['seconds'],
            'active_hours'    => round($a['seconds'] / 3600, 2),
            'span_seconds'    => $span,
            'span_hours'      => round($span / 3600, 2),
            'idle_seconds'    => $idle,
            'idle_hours'      => round($idle / 3600, 2),
            'utilisation'     => $span > 0 ? round(($a['seconds'] / $span) * 100, 1) : 0.0,
            'blocks'          => $a['blocks'],
            'days_worked'     => $days,
            'avg_hours_day'   => $days > 0 ? round(($a['seconds'] / 3600) / $days, 2) : 0.0,
            'events'          => $a['events'],
            'packages_added'  => $a['packages_added'],
            'packages_edited' => $a['packages_edited'],
            'deletions'       => $a['deletions'],
            'consolidations'  => $a['consolidations'],
            'pickups'         => $a['pickups'],
            'logins'          => $a['logins'],
            'first_at'        => $a['first_at'] ? date('Y-m-d H:i', $a['first_at']) : '',
            'last_at'         => $a['last_at']  ? date('Y-m-d H:i', $a['last_at'])  : '',
            'per_hour'        => $a['seconds'] > 0
                ? round($a['packages_added'] / ($a['seconds'] / 3600), 2)
                : 0.0,
        ];
    }

    usort($rows, function ($a, $b) {
        return $b['active_seconds'] <=> $a['active_seconds'];
    });

    ksort($byDay);

    $totalActive = array_sum(array_column($rows, 'active_seconds'));

    // The idle total is built ONLY from rows whose window is trustworthy;
    // mixing in the rest would produce a headline figure made mostly of gaps in
    // our own recording. `idle_rows` says how many of the staff it covers.
    $relActive = 0;
    $relSpan   = 0;
    $relRows   = 0;
    foreach ($rows as $r) {
        if (!empty($r['idle_reliable'])) {
            $relActive += $r['active_seconds'];
            $relSpan   += $r['span_seconds'];
            $relRows++;
        }
    }

    $totals = [
        'staff'          => count($rows),
        'active_hours'   => round($totalActive / 3600, 2),
        'span_hours'     => round($relSpan / 3600, 2),
        'idle_hours'     => round(max(0, $relSpan - $relActive) / 3600, 2),
        'utilisation'    => $relSpan > 0 ? round(($relActive / $relSpan) * 100, 1) : 0.0,
        'idle_rows'      => $relRows,
        'packages_added' => array_sum(array_column($rows, 'packages_added')),
        'packages_edited'=> array_sum(array_column($rows, 'packages_edited')),
        'events'         => array_sum(array_column($rows, 'events')),
        'days_worked'    => count($byDay),
    ];
    $totals['per_hour'] = $totals['active_hours'] > 0
        ? round($totals['packages_added'] / $totals['active_hours'], 2)
        : 0.0;

    return [
        'rows'    => $rows,
        'totals'  => $totals,
        'by_day'  => $byDay,
        'by_hour' => $byHour,
        'cutover' => cdp_spCutover(),
    ];
}

/**
 * One staff member's day-by-day detail: the working blocks of each day, with
 * what they did in them.
 *
 * @return array<int,array>
 */
function cdp_spDailyDetail($userId, $from, $to, $gapMinutes = null)
{
    $events = cdp_spEvents($from, $to, [(int) $userId]);
    $blocks = cdp_spSessionize($events, $gapMinutes)[(int) $userId] ?? [];

    // Packages added per day, so the day rows carry output as well as time.
    $perDay = [];
    foreach ($events as $e) {
        $d = date('Y-m-d', $e['at']);
        if (!isset($perDay[$d])) {
            $perDay[$d] = ['packages' => 0, 'edits' => 0, 'events' => 0, 'logins' => 0];
        }
        $perDay[$d]['events']++;
        if ($e['verb'] === 'login') {
            $perDay[$d]['logins']++;
        }
        if ($e['entity'] === 'package') {
            if ($e['verb'] === 'create') {
                $perDay[$d]['packages']++;
            } elseif ($e['verb'] === 'update' || $e['verb'] === 'status') {
                $perDay[$d]['edits']++;
            }
        }
    }

    // A day's window is its first to its last event, which is not the same as
    // its first to last active BLOCK — an event can fall outside any block only
    // at the edges, so they usually agree, but the window is what idle is
    // measured against.
    $window = [];
    foreach ($events as $e) {
        $d = date('Y-m-d', $e['at']);
        if (!isset($window[$d])) {
            $window[$d] = [$e['at'], $e['at']];
        } else {
            if ($e['at'] < $window[$d][0]) { $window[$d][0] = $e['at']; }
            if ($e['at'] > $window[$d][1]) { $window[$d][1] = $e['at']; }
        }
    }

    $days = [];
    foreach ($blocks as $b) {
        $d = date('Y-m-d', $b['start']);
        if (!isset($days[$d])) {
            $days[$d] = [
                'date'    => $d,
                'seconds' => 0,
                'blocks'  => [],
                'first'   => $b['start'],
                'last'    => $b['end'],
            ];
        }
        $days[$d]['seconds'] += $b['seconds'];
        $days[$d]['first'] = min($days[$d]['first'], $b['start']);
        $days[$d]['last']  = max($days[$d]['last'], $b['end']);
        $days[$d]['blocks'][] = [
            'from'    => date('H:i', $b['start']),
            'to'      => date('H:i', $b['end']),
            'minutes' => (int) round($b['seconds'] / 60),
            'events'  => $b['events'],
        ];
    }

    krsort($days);

    $out = [];
    foreach ($days as $d => $row) {
        $p = $perDay[$d] ?? ['packages' => 0, 'edits' => 0, 'events' => 0, 'logins' => 0];

        $span = isset($window[$d]) ? max(0, $window[$d][1] - $window[$d][0]) : 0;
        if ($row['seconds'] > $span) {
            $span = $row['seconds']; // the window cannot be shorter than the work in it
        }
        $idle = max(0, $span - $row['seconds']);

        $out[] = [
            'date'            => $d,
            'weekday'         => date('D', strtotime($d)),
            'active_hours'    => round($row['seconds'] / 3600, 2),
            'span_hours'      => round($span / 3600, 2),
            'idle_hours'      => round($idle / 3600, 2),
            'utilisation'     => $span > 0 ? round(($row['seconds'] / $span) * 100, 1) : 0.0,
            'first_seen'      => date('H:i', $row['first']),
            'last_seen'       => date('H:i', $row['last']),
            'blocks'          => $row['blocks'],
            'block_count'     => count($row['blocks']),
            'packages_added'  => $p['packages'],
            'packages_edited' => $p['edits'],
            'logins'          => $p['logins'],
            'events'          => $p['events'],
        ];
    }

    return $out;
}

/** Seconds → "6h 12m", the way the report shows durations. */
function cdp_spDuration($seconds)
{
    $seconds = max(0, (int) $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h === 0 && $m === 0) {
        return '—';
    }
    return ($h > 0 ? $h . 'h ' : '') . $m . 'm';
}

/**
 * May this user see the report? Admin and Super Admin only.
 *
 * The permission is checked as well, but the is_admin flag is the real gate:
 * a mis-grant of view_staff_productivity to Employee must not open a screen
 * that reports on Employees.
 *
 * @param User $user
 * @return bool
 */
function cdp_spCanView($user)
{
    if (!$user instanceof User) {
        return false;
    }
    $roleId = (int) $user->userlevel;
    $isAdmin = cdp_roleHasFlag($roleId, 'is_admin') || cdp_roleHasFlag($roleId, 'is_superadmin');
    return $isAdmin && $user->cdp_hasPermission('view_staff_productivity');
}
