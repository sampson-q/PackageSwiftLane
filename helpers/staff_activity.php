<?php
/**
 * ============================================================================
 * Staff Productivity — the engine behind the Staff Productivity report.
 *
 * Answers, per staff member and per day: when did they check in, when did they
 * finish, which stretches of the day were they working and which were they
 * idle, and what exactly did they do in each stretch (with the shipment ids).
 *
 * THE MODEL
 * ---------
 * · CHECK-IN is the first package the person created or edited that day
 *   (setting `checkin_scope` narrows it to created only). Logging in is not a
 *   check-in — login is login. A day with activity but no package action still
 *   counts: its check-in is the first recorded activity and is marked as such.
 * · CHECK-OUT is the end of the last recorded activity that day.
 * · Between the two, time is split into ACTIVE and IDLE segments. Every
 *   segment knows its start, end, duration and — for active ones — the actions
 *   performed inside it and the packages created / edited.
 * · Activity BEFORE the check-in is reported (count of actions and minutes)
 *   but is not part of the working window.
 *
 * WHERE THE DATA COMES FROM
 * -------------------------
 *   · cdb_staff_presence — one row per staff member per minute in which they
 *     touched the keyboard, mouse or screen in this app. Written by
 *     dataJs/presence_beacon.js through staff_presence_ping_ajax.php. This is
 *     the only honest idle signal the system has.
 *   · cdb_activity_log — every recorded action, from the moment the audit
 *     trail was deployed. Carries the action text and the shipment id.
 *   · cdb_order_user_history — package / consolidation / pickup actions back
 *     to 2022, read strictly BEFORE the activity log's first row (both tables
 *     get a row on package create, so reading both in full double-counts).
 *
 * TWO WAYS OF TELLING ACTIVE FROM IDLE
 * ------------------------------------
 * PRESENCE MODE — the day has presence minutes. Active minutes are the
 * presence minutes plus the minute of every recorded action. A pause with no
 * input longer than `idle_minutes` (setting) is idle, in full. Shorter pauses
 * (reading a screen, thinking) are bridged and count as active.
 *
 * GAP MODE — no presence data for the day (before the beacon was deployed).
 * Only server-side actions are known, so a working block is a run of actions
 * with no gap longer than `gap_minutes` (setting). This cannot tell "filling a
 * long form" from "walked away", which is why presence mode exists.
 *
 * COVERAGE per day: 'presence' | 'log' (post-cutover, gap mode) | 'history'
 * (pre-cutover, package actions only). Idle is NOT reliable on 'history' days —
 * a full day of work leaves two marks and everything between reads as idle —
 * so those days are excluded from every idle figure and shown with a dash.
 * Never "fix" that by just showing the number; someone could be disciplined
 * over a gap in our own records.
 *
 * All timestamps are read from the database as strings and turned into unix
 * time with strtotime() in the process timezone, then formatted back with
 * date() in the same timezone, so day boundaries match the stored values.
 * ============================================================================
 */

require_once __DIR__ . '/rbac.php';

/** Credit for a gap-mode block containing a single action, in seconds. */
if (!defined('CDP_SP_MIN_BLOCK')) {
    define('CDP_SP_MIN_BLOCK', 60);
}

/** The beacon may report minutes up to this far in the past (a flush that was delayed). */
if (!defined('CDP_SP_PING_MAX_AGO')) {
    define('CDP_SP_PING_MAX_AGO', 30);
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/**
 * The knobs, with their defaults. Mirrored by sql/staff_productivity_v2.sql.
 *
 * @return array<string,mixed>
 */
function cdp_spSettingDefaults()
{
    return [
        'idle_minutes'   => 5,                 // presence mode: a longer pause is idle
        'gap_minutes'    => 15,                // gap mode: a longer gap ends a block
        'checkin_scope'  => 'create_or_edit',  // or 'create_only'
        'ping_seconds'   => 60,                // beacon interval
        'beacon_enabled' => 1,
    ];
}

function cdp_spSettingsTableReady()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $db = new Conexion;
        $db->cdp_query("SHOW TABLES LIKE 'cdb_staff_productivity_settings'");
        $db->cdp_execute();
        $ready = $db->cdp_rowCount() > 0;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Effective settings: defaults overlaid with whatever the table holds.
 *
 * @param bool $fresh Bypass the per-request cache
 * @return array<string,mixed>
 */
function cdp_spSettings($fresh = false)
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $s = cdp_spSettingDefaults();
    if (cdp_spSettingsTableReady()) {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT setting_key, setting_value FROM cdb_staff_productivity_settings");
            $db->cdp_execute();
            $raw = [];
            foreach ((array) $db->cdp_registros() as $r) {
                $raw[(string) $r->setting_key] = (string) $r->setting_value;
            }
            $s = array_merge($s, cdp_spCleanSettings($raw, $s));
        } catch (Throwable $e) {
            // defaults stand
        }
    }
    $cache = $s;
    return $cache;
}

/** One setting, typed. */
function cdp_spSetting($key)
{
    $s = cdp_spSettings();
    return $s[$key] ?? (cdp_spSettingDefaults()[$key] ?? null);
}

/**
 * Validate a set of proposed settings. Unknown keys are dropped, out-of-range
 * values fall back to the current value, so a bad form post can never leave
 * the report with an idle threshold of zero.
 *
 * @param array      $in
 * @param array|null $current
 * @return array<string,mixed> only the keys that were present and valid
 */
function cdp_spCleanSettings(array $in, array $current = null)
{
    $cur = $current ?: cdp_spSettingDefaults();
    $out = [];

    $int = function ($key, $min, $max) use ($in, $cur, &$out) {
        if (!array_key_exists($key, $in)) {
            return;
        }
        $v = (int) $in[$key];
        $out[$key] = ($v >= $min && $v <= $max) ? $v : (int) $cur[$key];
    };
    $int('idle_minutes', 1, 120);
    $int('gap_minutes', 1, 240);
    $int('ping_seconds', 15, 600);

    if (array_key_exists('checkin_scope', $in)) {
        $v = (string) $in['checkin_scope'];
        $out['checkin_scope'] = in_array($v, ['create_or_edit', 'create_only'], true) ? $v : $cur['checkin_scope'];
    }
    if (array_key_exists('beacon_enabled', $in)) {
        $b = $in['beacon_enabled'];
        $out['beacon_enabled'] = ($b === true || $b === 1 || (string) $b === '1') ? 1 : 0;
    }
    return $out;
}

/**
 * Persist settings. Returns the effective settings afterwards.
 *
 * @param array $in
 * @param int   $userId who changed them
 * @return array<string,mixed>
 */
function cdp_spSaveSettings(array $in, $userId)
{
    $clean = cdp_spCleanSettings($in, cdp_spSettings());
    if ($clean && cdp_spSettingsTableReady()) {
        $db = new Conexion;
        foreach ($clean as $k => $v) {
            $db->cdp_query("INSERT INTO cdb_staff_productivity_settings (setting_key, setting_value, updated_at, updated_by)
                            VALUES (:k, :v, NOW(), :u)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                                    updated_at    = VALUES(updated_at),
                                                    updated_by    = VALUES(updated_by)");
            $db->bind(':k', (string) $k);
            $db->bind(':v', (string) $v);
            $db->bind(':u', (int) $userId);
            $db->cdp_execute();
        }
    }
    return cdp_spSettings(true);
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
// Presence
// ---------------------------------------------------------------------------

function cdp_spPresenceTableReady()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $db = new Conexion;
        $db->cdp_query("SHOW TABLES LIKE 'cdb_staff_presence'");
        $db->cdp_execute();
        $ready = $db->cdp_rowCount() > 0;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Should this page load the presence beacon? Staff roles only, beacon switched
 * on, table present. Under "View as" the ORIGINAL operator's role decides.
 *
 * @return bool
 */
function cdp_spBeaconWanted()
{
    if (empty($_SESSION['userid'])) {
        return false;
    }
    $role = (int) ($_SESSION['imp_original_userlevel'] ?? ($_SESSION['userlevel'] ?? 0));
    if (!in_array($role, cdp_spStaffRoleIds(), true)) {
        return false;
    }
    if ((int) cdp_spSetting('beacon_enabled') !== 1) {
        return false;
    }
    return cdp_spPresenceTableReady();
}

/**
 * Store the minutes the beacon reports as active. Offsets are "minutes ago"
 * relative to the server clock, so a wrong client clock cannot backdate rows.
 *
 * @param int    $userId
 * @param int[]  $agoList 0 = the current minute
 * @param string $page
 * @return int minutes written (or already present)
 */
function cdp_spRecordPresence($userId, array $agoList, $page = '')
{
    $userId = (int) $userId;
    if ($userId <= 0 || !cdp_spPresenceTableReady()) {
        return 0;
    }
    require_once __DIR__ . '/activity_log.php';
    $now = new DateTime(cdp_activityNow());
    $now->setTime((int) $now->format('G'), (int) $now->format('i'), 0);

    $minutes = [];
    foreach ($agoList as $a) {
        $a = (int) $a;
        if ($a < 0 || $a > CDP_SP_PING_MAX_AGO) {
            continue;
        }
        $m = clone $now;
        if ($a > 0) {
            $m->modify('-' . $a . ' minutes');
        }
        $minutes[$m->format('Y-m-d H:i:s')] = true;
    }
    if (!$minutes) {
        return 0;
    }

    $page = substr(preg_replace('/[^A-Za-z0-9_.\-]/', '', (string) $page), 0, 80);

    $values = [];
    $binds  = [];
    $i = 0;
    foreach (array_keys($minutes) as $at) {
        $values[] = "(:u, :m$i, :p)";
        $binds[":m$i"] = $at;
        $i++;
    }
    try {
        $db = new Conexion;
        $db->cdp_query("INSERT IGNORE INTO cdb_staff_presence (user_id, minute_at, page) VALUES " . implode(',', $values));
        $db->bind(':u', $userId);
        $db->bind(':p', $page);
        foreach ($binds as $k => $v) {
            $db->bind($k, $v);
        }
        $db->cdp_execute();
    } catch (Throwable $e) {
        error_log('STAFF_PRESENCE_FAIL ' . $e->getMessage());
        return 0;
    }
    return count($minutes);
}

/**
 * Presence minutes for a period, as minute-of-day sets.
 *
 * @return array<int,array<string,array<int,bool>>> [user id]['Y-m-d'][minute of day] = true
 */
function cdp_spPresence($from, $to, array $userIds)
{
    if (!$userIds || !cdp_spPresenceTableReady()) {
        return [];
    }
    $in = implode(',', array_map('intval', $userIds));
    $lo = $from !== '' ? $from . ' 00:00:00' : '1970-01-01 00:00:00';
    $hi = $to   !== '' ? $to . ' 23:59:59'   : '2999-12-31 23:59:59';

    $out = [];
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT user_id, minute_at FROM cdb_staff_presence
                        WHERE user_id IN ($in) AND minute_at >= :lo AND minute_at <= :hi
                        ORDER BY user_id, minute_at");
        $db->bind(':lo', $lo);
        $db->bind(':hi', $hi);
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $s   = (string) $r->minute_at;               // 'Y-m-d H:i:s'
            $day = substr($s, 0, 10);
            $min = ((int) substr($s, 11, 2)) * 60 + (int) substr($s, 14, 2);
            $out[(int) $r->user_id][$day][$min] = true;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
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

/** Map an activity-log module onto this report's entity vocabulary. */
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
 * Each event: user_id, at (unix), verb, entity, source (history|log), label
 * (what the person did, in words) and ref (the shipment / consolidation id
 * when there is one).
 *
 * @param string $from    'Y-m-d' inclusive, '' for no lower bound
 * @param string $to      'Y-m-d' inclusive, '' for no upper bound
 * @param int[]  $userIds Restrict to these staff (empty = all staff)
 * @return array<int,array>
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
            $db->cdp_query("SELECT user_id, action, date_history, is_consolidate, order_track
                            FROM cdb_order_user_history
                            WHERE user_id IN ($in)
                              AND date_history >= :lo AND date_history < :hi
                            ORDER BY user_id, date_history");
            $db->bind(':lo', $lo);
            $db->bind(':hi', $histHi);
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $r) {
                $c = cdp_spClassifyHistory($r->action, $r->is_consolidate);
                $track = trim((string) $r->order_track);
                $events[] = [
                    'user_id' => (int) $r->user_id,
                    'at'      => strtotime((string) $r->date_history),
                    'verb'    => $c['verb'],
                    'entity'  => $c['entity'],
                    'source'  => 'history',
                    'label'   => ucfirst(trim((string) $r->action)) . ($track !== '' ? ' ' . $track : ''),
                    'ref'     => $track,
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
                $db->cdp_query("SELECT user_id, verb, module, created_at, summary, action_label, entity_label
                                FROM cdb_activity_log
                                WHERE user_id IN ($in)
                                  AND created_at >= :lo AND created_at <= :hi
                                ORDER BY user_id, created_at");
                $db->bind(':lo', $logLo);
                $db->bind(':hi', $hi);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $r) {
                    $label = trim((string) $r->summary);
                    if ($label === '') {
                        $label = (string) $r->action_label;
                    }
                    $events[] = [
                        'user_id' => (int) $r->user_id,
                        'at'      => strtotime((string) $r->created_at),
                        'verb'    => (string) $r->verb,
                        'entity'  => cdp_spEntityForModule((string) $r->module),
                        'source'  => 'log',
                        'label'   => $label,
                        'ref'     => trim((string) $r->entity_label),
                    ];
                }
            } catch (Throwable $e) {
                // No activity log yet — history alone still produces a report.
            }
        }
    }

    usort($events, function ($a, $b) {
        if ($a['user_id'] !== $b['user_id']) {
            return $a['user_id'] - $b['user_id'];
        }
        return $a['at'] - $b['at'];
    });

    return $events;
}

// ---------------------------------------------------------------------------
// Reconstruction — one person, one day
// ---------------------------------------------------------------------------

/** Is this event a package action that starts the working day? */
function cdp_spIsCheckInEvent(array $e, $scope)
{
    if ($e['entity'] !== 'package') {
        return false;
    }
    if ($e['verb'] === 'create') {
        return true;
    }
    return $scope === 'create_or_edit' && ($e['verb'] === 'update' || $e['verb'] === 'status');
}

/**
 * Build one staff member's day: check-in, check-out, active/idle segments with
 * the actions inside them, package ids, hour distribution.
 *
 * @param int         $uid
 * @param string      $day      'Y-m-d'
 * @param array       $events   that person's events on that day (any order)
 * @param array       $presence minute-of-day => true, may be empty
 * @param array       $s        settings
 * @param string|null $cutDay   'Y-m-d' of the activity log's first row
 * @return array|null null when there is nothing at all to report
 */
function cdp_spBuildDay($uid, $day, array $events, array $presence, array $s, $cutDay)
{
    $dayStart = strtotime($day . ' 00:00:00');
    $idleMin  = max(1, (int) $s['idle_minutes']);
    $gapSec   = max(1, (int) $s['gap_minutes']) * 60;
    $scope    = (string) $s['checkin_scope'];

    usort($events, function ($a, $b) { return $a['at'] - $b['at']; });

    $hasPresence = !empty($presence);
    $coverage = $hasPresence ? 'presence' : (($cutDay !== null && $day >= $cutDay) ? 'log' : 'history');

    // ── Check-in ─────────────────────────────────────────────────────────────
    $checkIn = null;
    $kind = 'package';
    $ref = '';
    foreach ($events as $e) {
        if (cdp_spIsCheckInEvent($e, $scope)) {
            $checkIn = $e['at'];
            $ref = $e['ref'];
            break;
        }
    }
    if ($checkIn === null) {
        $cands = [];
        if ($events) {
            $cands[] = $events[0]['at'];
        }
        if ($hasPresence) {
            $cands[] = $dayStart + min(array_keys($presence)) * 60;
        }
        if (!$cands) {
            return null;
        }
        $checkIn = min($cands);
        $kind = 'activity';
    }

    // ── Active blocks ────────────────────────────────────────────────────────
    $blocks = [];      // [['start'=>ts,'end'=>ts], ...]
    $preMinutes = 0;

    if ($hasPresence) {
        $mode = 'presence';
        $checkInMin = intdiv($checkIn - $dayStart, 60);
        $mins = $presence;
        foreach ($events as $e) {
            $m = intdiv($e['at'] - $dayStart, 60);
            if ($m >= 0 && $m < 1440) {
                $mins[$m] = true;
            }
        }
        ksort($mins);

        $cur = null;
        foreach ($mins as $m => $_) {
            if ($m < $checkInMin) {
                $preMinutes++;
                continue;
            }
            if ($cur === null) {
                $cur = ['s' => $m, 'e' => $m];
                continue;
            }
            // Minutes with no input strictly between two active minutes.
            if (($m - $cur['e'] - 1) > $idleMin) {
                $blocks[] = $cur;
                $cur = ['s' => $m, 'e' => $m];
                continue;
            }
            $cur['e'] = $m;
        }
        if ($cur !== null) {
            $blocks[] = $cur;
        }
        foreach ($blocks as $i => $b) {
            $blocks[$i] = ['start' => $dayStart + $b['s'] * 60, 'end' => $dayStart + ($b['e'] + 1) * 60];
        }
        if ($blocks) {
            // The check-in minute started before the check-in action itself.
            $blocks[0]['start'] = max($blocks[0]['start'], $checkIn);
        }
    } else {
        $mode = 'gap';
        $cur = null;
        foreach ($events as $e) {
            if ($e['at'] < $checkIn) {
                continue;
            }
            if ($cur === null) {
                $cur = ['start' => $e['at'], 'end' => $e['at']];
                continue;
            }
            if (($e['at'] - $cur['end']) > $gapSec) {
                $blocks[] = $cur;
                $cur = ['start' => $e['at'], 'end' => $e['at']];
                continue;
            }
            $cur['end'] = $e['at'];
        }
        if ($cur !== null) {
            $blocks[] = $cur;
        }
        foreach ($blocks as $i => $b) {
            if ($b['end'] - $b['start'] < CDP_SP_MIN_BLOCK) {
                $blocks[$i]['end'] = $b['start'] + CDP_SP_MIN_BLOCK;
            }
        }
    }

    $checkOut = $blocks ? $blocks[count($blocks) - 1]['end'] : $checkIn;

    // ── Segments: active / idle / active / … ─────────────────────────────────
    $segments = [];
    $activeSec = 0;
    $n = count($blocks);
    for ($i = 0; $i < $n; $i++) {
        $b = $blocks[$i];
        $sec = max(0, $b['end'] - $b['start']);
        $activeSec += $sec;
        $segments[] = [
            'type' => 'active', 'start' => $b['start'], 'end' => $b['end'], 'seconds' => $sec,
            'events' => 0, 'created' => [], 'edited' => [], 'other' => 0, 'actions' => [],
        ];
        if ($i < $n - 1) {
            $nx = $blocks[$i + 1];
            $segments[] = [
                'type' => 'idle', 'start' => $b['end'], 'end' => $nx['start'],
                'seconds' => max(0, $nx['start'] - $b['end']),
            ];
        }
    }

    // ── Place every action in its segment; tally the day ────────────────────
    $created = [];       // unique shipment ids created today
    $edited  = [];       // unique shipment ids edited today
    $createdCount = 0;   // create actions (an id-less row still counts one)
    $editedCount  = 0;
    $tally = ['consolidations' => 0, 'pickups' => 0, 'deletions' => 0, 'logins' => 0, 'pre' => 0];
    $hourPackages = array_fill(0, 24, 0);
    $p = 0;
    $segCount = count($segments);

    foreach ($events as $e) {
        $isPkg    = $e['entity'] === 'package';
        $isCreate = $isPkg && $e['verb'] === 'create';
        $isEdit   = $isPkg && ($e['verb'] === 'update' || $e['verb'] === 'status');

        if ($isCreate) {
            if ($e['ref'] === '' || !in_array($e['ref'], $created, true)) {
                $createdCount++;
                if ($e['ref'] !== '') {
                    $created[] = $e['ref'];
                }
            }
            $hourPackages[(int) date('G', $e['at'])]++;
        } elseif ($isEdit) {
            if ($e['ref'] === '' || !in_array($e['ref'], $edited, true)) {
                $editedCount++;
                if ($e['ref'] !== '') {
                    $edited[] = $e['ref'];
                }
            }
        } elseif ($isPkg && $e['verb'] === 'delete') {
            $tally['deletions']++;
        } elseif ($e['entity'] === 'consolidation') {
            $tally['consolidations']++;
        } elseif ($e['entity'] === 'pickup') {
            $tally['pickups']++;
        }
        if ($e['verb'] === 'login') {
            $tally['logins']++;
        }

        if ($e['at'] < $checkIn) {
            $tally['pre']++;
            continue;
        }
        while ($p < $segCount && ($segments[$p]['type'] === 'idle' || $segments[$p]['end'] < $e['at'])) {
            $p++;
        }
        if ($p >= $segCount) {
            break; // cannot happen: every post-check-in action sits inside a block
        }
        $segments[$p]['events']++;
        if ($isCreate) {
            if ($e['ref'] !== '' && !in_array($e['ref'], $segments[$p]['created'], true)) {
                $segments[$p]['created'][] = $e['ref'];
            }
        } elseif ($isEdit) {
            if ($e['ref'] !== '' && !in_array($e['ref'], $segments[$p]['edited'], true)) {
                $segments[$p]['edited'][] = $e['ref'];
            }
        } else {
            $segments[$p]['other']++;
        }
        $segments[$p]['actions'][] = [
            'at'     => $e['at'],
            'time'   => date('H:i:s', $e['at']),
            'label'  => $e['label'],
            'ref'    => $e['ref'],
            'verb'   => $e['verb'],
            'entity' => $e['entity'],
            'source' => $e['source'],
        ];
    }

    // ── Hour distribution of active time ────────────────────────────────────
    $hourSec = array_fill(0, 24, 0);
    foreach ($segments as $seg) {
        if ($seg['type'] !== 'active') {
            continue;
        }
        $t = $seg['start'];
        while ($t < $seg['end']) {
            $h = (int) date('G', $t);
            $hourEnd = strtotime(date('Y-m-d H:00:00', $t)) + 3600;
            $chunk = min($seg['end'], $hourEnd) - $t;
            if ($chunk <= 0) {
                break;
            }
            $hourSec[$h] += $chunk;
            $t += $chunk;
        }
    }

    // Presentation fields on the segments.
    foreach ($segments as $i => $seg) {
        $segments[$i]['from']  = date('H:i', $seg['start']);
        $segments[$i]['to']    = date('H:i', $seg['end']);
        $segments[$i]['label'] = cdp_spDuration($seg['seconds']);
    }

    $window = max(0, $checkOut - $checkIn);
    if ($activeSec > $window) {
        $window = $activeSec; // a one-action day: the window cannot be shorter than the work in it
    }
    $idle = max(0, $window - $activeSec);

    return [
        'date'                => $day,
        'weekday'             => date('D', $dayStart),
        'user_id'             => (int) $uid,
        'coverage'            => $coverage,
        'mode'                => $mode,
        'idle_reliable'       => $coverage !== 'history',
        'check_in'            => $checkIn,
        'check_in_time'       => date('H:i', $checkIn),
        'check_in_kind'       => $kind,
        'check_in_ref'        => $ref,
        'check_out'           => $checkOut,
        'check_out_time'      => date('H:i', $checkOut),
        'window_seconds'      => $window,
        'active_seconds'      => $activeSec,
        'idle_seconds'        => $idle,
        'utilisation'         => $window > 0 ? round(($activeSec / $window) * 100, 1) : 0.0,
        'blocks'              => $n,
        'segments'            => $segments,
        'packages_created'    => $created,
        'packages_edited'     => $edited,
        'created_count'       => $createdCount,
        'edited_count'        => $editedCount,
        'consolidations'      => $tally['consolidations'],
        'pickups'             => $tally['pickups'],
        'deletions'           => $tally['deletions'],
        'logins'              => $tally['logins'],
        'events'              => count($events),
        'pre_checkin_events'  => $tally['pre'],
        'pre_checkin_minutes' => $preMinutes,
        'hour_seconds'        => $hourSec,
        'hour_packages'       => $hourPackages,
    ];
}

/**
 * Every (staff member, day) in the period.
 *
 * @return array<int,array<string,array>> [user id]['Y-m-d'] => day, days ascending
 */
function cdp_spBuildDays($from, $to, array $userIds = [])
{
    $staff = cdp_spStaffUsers();
    $ids = $userIds ? array_values(array_intersect($userIds, array_keys($staff))) : array_keys($staff);
    if (!$ids) {
        return [];
    }

    $events   = cdp_spEvents($from, $to, $ids);
    $presence = cdp_spPresence($from, $to, $ids);
    $s        = cdp_spSettings();
    $cut      = cdp_spCutover();
    $cutDay   = $cut !== null ? substr($cut, 0, 10) : null;

    $byUD = [];
    foreach ($events as $e) {
        $byUD[$e['user_id']][date('Y-m-d', $e['at'])][] = $e;
    }
    unset($events);

    $out = [];
    $uids = array_unique(array_merge(array_keys($byUD), array_keys($presence)));
    foreach ($uids as $uid) {
        $days = array_unique(array_merge(array_keys($byUD[$uid] ?? []), array_keys($presence[$uid] ?? [])));
        sort($days);
        foreach ($days as $day) {
            $model = cdp_spBuildDay($uid, $day, $byUD[$uid][$day] ?? [], $presence[$uid][$day] ?? [], $s, $cutDay);
            if ($model !== null) {
                $out[$uid][$day] = $model;
            }
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Aggregates
// ---------------------------------------------------------------------------

/**
 * Everything the report page shows, per staff member, for one period.
 *
 * Idle, window and utilisation are built ONLY from days whose idle figure is
 * reliable; active time and package counts span every day.
 *
 * @return array{rows:array,totals:array,by_day:array,by_hour:array,heat:array,cutover:?string,settings:array}
 */
function cdp_spSummary($from, $to, array $userIds = [])
{
    $staff = cdp_spStaffUsers();
    $all   = cdp_spBuildDays($from, $to, $userIds);

    $rows   = [];
    $byDay  = [];
    $byHour = array_fill(0, 24, ['seconds' => 0, 'packages' => 0, 'events' => 0]);
    $heat   = [];

    foreach ($all as $uid => $days) {
        $u = $staff[$uid] ?? null;
        $a = [
            'active' => 0, 'rel_active' => 0, 'rel_window' => 0, 'idle_days' => 0,
            'blocks' => 0, 'events' => 0, 'created' => 0, 'edited' => 0, 'deletions' => 0,
            'consolidations' => 0, 'pickups' => 0, 'logins' => 0, 'checkins' => 0,
            'first' => null, 'last' => null,
            'presence_days' => 0, 'log_days' => 0, 'history_days' => 0,
        ];

        foreach ($days as $day => $d) {
            $a['active'] += $d['active_seconds'];
            if ($d['idle_reliable']) {
                $a['rel_active'] += $d['active_seconds'];
                $a['rel_window'] += $d['window_seconds'];
                $a['idle_days']++;
            }
            $a['blocks']         += $d['blocks'];
            $a['events']         += $d['events'];
            $a['created']        += $d['created_count'];
            $a['edited']         += $d['edited_count'];
            $a['deletions']      += $d['deletions'];
            $a['consolidations'] += $d['consolidations'];
            $a['pickups']        += $d['pickups'];
            $a['logins']         += $d['logins'];
            if ($d['check_in_kind'] === 'package') {
                $a['checkins']++;
            }
            $a['first'] = $a['first'] === null ? $d['check_in'] : min($a['first'], $d['check_in']);
            $a['last']  = $a['last']  === null ? $d['check_out'] : max($a['last'], $d['check_out']);
            $a[$d['coverage'] . '_days']++;

            if (!isset($byDay[$day])) {
                $byDay[$day] = ['seconds' => 0, 'rel_seconds' => 0, 'span' => 0, 'packages' => 0, 'events' => 0, 'staff' => 0];
            }
            $byDay[$day]['seconds']  += $d['active_seconds'];
            $byDay[$day]['packages'] += $d['created_count'];
            $byDay[$day]['events']   += $d['events'];
            $byDay[$day]['staff']++;
            if ($d['idle_reliable']) {
                $byDay[$day]['rel_seconds'] += $d['active_seconds'];
                $byDay[$day]['span']        += $d['window_seconds'];
            }
            if (!isset($heat[$day])) {
                $heat[$day] = array_fill(0, 24, 0);
            }
            for ($h = 0; $h < 24; $h++) {
                $byHour[$h]['seconds']  += $d['hour_seconds'][$h];
                $byHour[$h]['packages'] += $d['hour_packages'][$h];
                $heat[$day][$h]         += $d['hour_seconds'][$h];
            }
            foreach ($d['segments'] as $seg) {
                if ($seg['type'] === 'active') {
                    foreach ($seg['actions'] as $act) {
                        $byHour[(int) date('G', $act['at'])]['events']++;
                    }
                }
            }
        }

        $dayCount = count($days);
        if ($a['history_days'] === 0 && $a['presence_days'] === $dayCount) {
            $coverage = 'presence';
        } elseif ($a['history_days'] === 0) {
            $coverage = 'full';
        } elseif ($a['presence_days'] + $a['log_days'] === 0) {
            $coverage = 'partial';
        } else {
            $coverage = 'mixed';
        }
        $idle = max(0, $a['rel_window'] - $a['rel_active']);

        $rows[] = [
            'user_id'         => (int) $uid,
            'name'            => $u ? $u->display_name : ('User #' . $uid),
            'username'        => $u ? (string) $u->username : '',
            'role'            => $u ? (string) $u->role_name : '',
            'is_active'       => $u ? (int) $u->active : 1,
            'coverage'        => $coverage,
            'presence_days'   => $a['presence_days'],
            'log_days'        => $a['log_days'],
            'history_days'    => $a['history_days'],
            'idle_reliable'   => $a['idle_days'] > 0,
            'idle_days'       => $a['idle_days'],
            'active_seconds'  => $a['active'],
            'active_hours'    => round($a['active'] / 3600, 2),
            'rel_active_seconds' => $a['rel_active'],
            'span_seconds'    => $a['rel_window'],
            'span_hours'      => round($a['rel_window'] / 3600, 2),
            'idle_seconds'    => $idle,
            'idle_hours'      => round($idle / 3600, 2),
            'utilisation'     => $a['rel_window'] > 0 ? round(($a['rel_active'] / $a['rel_window']) * 100, 1) : 0.0,
            'blocks'          => $a['blocks'],
            'days_worked'     => $dayCount,
            'checkins'        => $a['checkins'],
            'avg_hours_day'   => $dayCount > 0 ? round(($a['active'] / 3600) / $dayCount, 2) : 0.0,
            'events'          => $a['events'],
            'packages_added'  => $a['created'],
            'packages_edited' => $a['edited'],
            'deletions'       => $a['deletions'],
            'consolidations'  => $a['consolidations'],
            'pickups'         => $a['pickups'],
            'logins'          => $a['logins'],
            'first_at'        => $a['first'] ? date('Y-m-d H:i', $a['first']) : '',
            'last_at'         => $a['last']  ? date('Y-m-d H:i', $a['last'])  : '',
            'per_hour'        => $a['active'] > 0 ? round($a['created'] / ($a['active'] / 3600), 2) : 0.0,
        ];
    }

    usort($rows, function ($a, $b) {
        return $b['active_seconds'] <=> $a['active_seconds'];
    });
    ksort($byDay);
    ksort($heat);

    $relActive = 0;
    $relSpan   = 0;
    $relRows   = 0;
    foreach ($rows as $r) {
        if ($r['idle_reliable']) {
            $relActive += $r['rel_active_seconds'];
            $relSpan   += $r['span_seconds'];
            $relRows++;
        }
    }

    $totals = [
        'staff'           => count($rows),
        'active_hours'    => round(array_sum(array_column($rows, 'active_seconds')) / 3600, 2),
        'span_hours'      => round($relSpan / 3600, 2),
        'idle_hours'      => round(max(0, $relSpan - $relActive) / 3600, 2),
        'utilisation'     => $relSpan > 0 ? round(($relActive / $relSpan) * 100, 1) : 0.0,
        'idle_rows'       => $relRows,
        'packages_added'  => array_sum(array_column($rows, 'packages_added')),
        'packages_edited' => array_sum(array_column($rows, 'packages_edited')),
        'checkins'        => array_sum(array_column($rows, 'checkins')),
        'staff_days'      => array_sum(array_column($rows, 'days_worked')),
        'events'          => array_sum(array_column($rows, 'events')),
        'days_worked'     => count($byDay),
    ];
    $totals['per_hour'] = $totals['active_hours'] > 0
        ? round($totals['packages_added'] / $totals['active_hours'], 2)
        : 0.0;

    $heatOut = [];
    foreach ($heat as $day => $hours) {
        $heatOut[] = [
            'date'    => $day,
            'minutes' => array_map(function ($sec) { return (int) round($sec / 60); }, $hours),
        ];
    }

    return [
        'rows'     => $rows,
        'totals'   => $totals,
        'by_day'   => $byDay,
        'by_hour'  => $byHour,
        'heat'     => $heatOut,
        'cutover'  => cdp_spCutover(),
        'settings' => cdp_spSettings(),
    ];
}

/**
 * One staff member's days, newest first.
 *
 * @return array<int,array>
 */
function cdp_spDailyDetail($userId, $from, $to)
{
    $days = cdp_spBuildDays($from, $to, [(int) $userId])[(int) $userId] ?? [];
    krsort($days);
    return array_values($days);
}

/** One staff member's single day, or null. */
function cdp_spDay($userId, $day)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
        return null;
    }
    return cdp_spBuildDays($day, $day, [(int) $userId])[(int) $userId][$day] ?? null;
}

/** Seconds → "6h 12m", the way the report shows durations. */
function cdp_spDuration($seconds)
{
    $seconds = max(0, (int) $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h === 0 && $m === 0) {
        return $seconds > 0 ? '<1m' : '—';
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
