<?php
/**
 * ============================================================================
 * Message Costs — what the SMS, WhatsApp and e-mail the system sent cost.
 *
 * Counts come from the Message Log (cdb_message_log, helpers/message_log.php),
 * which already records every outbound message at the three send primitives.
 * This file adds the prices and the arithmetic:
 *
 *   cdb_message_cost_rates     one row per channel per price change:
 *                              (channel, unit_cost, effective_from)
 *   cdb_message_cost_settings  key / value: currency, sms_parts
 *
 * Both tables are created on first use (CREATE TABLE IF NOT EXISTS), so the
 * page works on a database nobody migrated by hand.
 *
 * How a message is priced
 *   - Only messages with status "sent" cost money. Failed and skipped messages
 *     are counted and shown, but priced at zero.
 *   - The price of a message is the rate of its channel in effect on the day it
 *     was sent: the rate with the latest effective_from on or before that day.
 *     A message older than the first rate of its channel uses that first rate,
 *     so entering a price once prices the whole history, and a later price
 *     change only affects messages from its date on.
 *   - With sms_parts on, a long SMS is billed per part the way carriers do:
 *     up to 160 characters is one part, longer texts are split into parts of
 *     153 characters.
 * ============================================================================
 */

require_once __DIR__ . '/message_log.php';

/** Channels that carry a price. Order is the display order. */
function cdp_mcChannels()
{
    return ['sms', 'whatsapp', 'email'];
}

function cdp_mcSettingDefaults()
{
    $currency = '';
    if (class_exists('Core')) {
        try {
            $core = new Core();
            $currency = trim((string) ($core->currency ?? ''));
        } catch (Throwable $e) {
            $currency = '';
        }
    }
    return [
        'currency'  => $currency !== '' ? $currency : 'USD',
        'sms_parts' => 0,
    ];
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

/**
 * Make sure both cost tables exist. Cached per request. Returns false when the
 * database user may not create tables (the page then explains that).
 */
function cdp_mcEnsureTables()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $db = new Conexion;
        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_message_cost_rates (
                id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                channel        VARCHAR(12)  NOT NULL,
                unit_cost      DECIMAL(14,6) NOT NULL DEFAULT 0,
                effective_from DATE         NOT NULL,
                created_at     DATETIME     NULL,
                created_by     INT          NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_channel_from (channel, effective_from)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ok1 = $db->cdp_execute();

        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_message_cost_settings (
                setting_key   VARCHAR(40)  NOT NULL,
                setting_value VARCHAR(190) NOT NULL DEFAULT '',
                updated_at    DATETIME     NULL,
                updated_by    INT          NOT NULL DEFAULT 0,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ok2 = $db->cdp_execute();

        $ready = (bool) ($ok1 && $ok2);
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

function cdp_mcSettings($fresh = false)
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $s = cdp_mcSettingDefaults();
    if (cdp_mcEnsureTables()) {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT setting_key, setting_value FROM cdb_message_cost_settings");
            $db->cdp_execute();
            $raw = [];
            foreach ((array) $db->cdp_registros() as $r) {
                $raw[(string) $r->setting_key] = (string) $r->setting_value;
            }
            $s = array_merge($s, cdp_mcCleanSettings($raw, $s));
        } catch (Throwable $e) {
            // defaults stand
        }
    }
    $cache = $s;
    return $cache;
}

/** Only the keys present and valid; anything else is dropped. */
function cdp_mcCleanSettings(array $in, array $current)
{
    $out = [];
    if (array_key_exists('currency', $in)) {
        $c = trim(preg_replace('/[^\p{L}\p{Sc}0-9 .\-]/u', '', (string) $in['currency']));
        $c = function_exists('mb_substr') ? mb_substr($c, 0, 12) : substr($c, 0, 12);
        $out['currency'] = $c !== '' ? $c : $current['currency'];
    }
    if (array_key_exists('sms_parts', $in)) {
        $b = $in['sms_parts'];
        $out['sms_parts'] = ($b === true || $b === 1 || (string) $b === '1') ? 1 : 0;
    }
    return $out;
}

function cdp_mcSaveSettings(array $in, $userId)
{
    $clean = cdp_mcCleanSettings($in, cdp_mcSettings());
    if ($clean && cdp_mcEnsureTables()) {
        $db = new Conexion;
        foreach ($clean as $k => $v) {
            $db->cdp_query("INSERT INTO cdb_message_cost_settings (setting_key, setting_value, updated_at, updated_by)
                            VALUES (:k, :v, :at, :u)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                                    updated_at    = VALUES(updated_at),
                                                    updated_by    = VALUES(updated_by)");
            $db->bind(':k', (string) $k);
            $db->bind(':v', (string) $v);
            $db->bind(':at', cdp_msgNow());
            $db->bind(':u', (int) $userId);
            $db->cdp_execute();
        }
    }
    return cdp_mcSettings(true);
}

// ---------------------------------------------------------------------------
// Rates
// ---------------------------------------------------------------------------

/**
 * Every rate, grouped by channel, oldest first.
 *
 * @return array<string, array<int, array{id:int,channel:string,unit_cost:float,effective_from:string,created_at:string}>>
 */
function cdp_mcRates()
{
    $out = array_fill_keys(cdp_mcChannels(), []);
    if (!cdp_mcEnsureTables()) {
        return $out;
    }
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT id, channel, unit_cost, effective_from, created_at
                        FROM cdb_message_cost_rates ORDER BY channel, effective_from ASC");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            if (!isset($out[$r->channel])) {
                continue;
            }
            $out[$r->channel][] = [
                'id'             => (int) $r->id,
                'channel'        => (string) $r->channel,
                'unit_cost'      => (float) $r->unit_cost,
                'effective_from' => (string) $r->effective_from,
                'created_at'     => (string) $r->created_at,
            ];
        }
    } catch (Throwable $e) {
        // no rates
    }
    return $out;
}

/** The rate of each channel in effect today (0 when none was entered). */
function cdp_mcCurrentRates(array $rates = null)
{
    $rates = $rates ?? cdp_mcRates();
    $today = substr(cdp_msgNow(), 0, 10);
    $out = [];
    foreach (cdp_mcChannels() as $ch) {
        $out[$ch] = cdp_mcRateOn($rates[$ch] ?? [], $today);
    }
    return $out;
}

/**
 * The unit cost in effect on a day. $list is one channel's rates, oldest
 * first. Before the first rate, the first rate applies; no rates at all is 0.
 */
function cdp_mcRateOn(array $list, $day)
{
    if (!$list) {
        return 0.0;
    }
    $rate = (float) $list[0]['unit_cost'];
    foreach ($list as $r) {
        if ((string) $r['effective_from'] <= (string) $day) {
            $rate = (float) $r['unit_cost'];
        } else {
            break;
        }
    }
    return $rate;
}

/**
 * Add a rate, or replace the one with the same channel and date.
 *
 * @return bool
 */
function cdp_mcSaveRate($channel, $unitCost, $from, $userId)
{
    if (!in_array($channel, cdp_mcChannels(), true) || !cdp_mcEnsureTables()) {
        return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)) {
        return false;
    }
    $db = new Conexion;
    $db->cdp_query("INSERT INTO cdb_message_cost_rates (channel, unit_cost, effective_from, created_at, created_by)
                    VALUES (:ch, :c, :f, :at, :u)
                    ON DUPLICATE KEY UPDATE unit_cost  = VALUES(unit_cost),
                                            created_at = VALUES(created_at),
                                            created_by = VALUES(created_by)");
    $db->bind(':ch', $channel);
    $db->bind(':c', number_format(max(0, (float) $unitCost), 6, '.', ''));
    $db->bind(':f', (string) $from);
    $db->bind(':at', cdp_msgNow());
    $db->bind(':u', (int) $userId);
    return (bool) $db->cdp_execute();
}

/** @return array|null the deleted row */
function cdp_mcDeleteRate($id)
{
    if (!cdp_mcEnsureTables()) {
        return null;
    }
    $db = new Conexion;
    $db->cdp_query("SELECT id, channel, unit_cost, effective_from FROM cdb_message_cost_rates WHERE id = :id");
    $db->bind(':id', (int) $id);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    if (!$row) {
        return null;
    }
    $db->cdp_query("DELETE FROM cdb_message_cost_rates WHERE id = :id");
    $db->bind(':id', (int) $id);
    $db->cdp_execute();
    return (array) $row;
}

/** Who may change prices: Super Admin and Administrator with the page. */
function cdp_mcCanEdit($user)
{
    if (!$user || !$user->cdp_hasPermission('view_message_logs')) {
        return false;
    }
    return in_array((int) ($user->userlevel ?? 0), [9, 2], true);
}

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

function cdp_mcFilters()
{
    $date = function ($k) {
        $v = trim((string) ($_REQUEST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    };
    $from = $date('from');
    $to   = $date('to');
    if ($from !== '' && $to !== '' && $from > $to) {
        list($from, $to) = [$to, $from];
    }
    $channel = preg_replace('/[^a-z]/', '', strtolower((string) ($_REQUEST['channel'] ?? '')));
    $group   = (string) ($_REQUEST['group'] ?? '');

    return [
        'from'    => $from,
        'to'      => $to,
        'channel' => in_array($channel, cdp_mcChannels(), true) ? $channel : '',
        'source'  => preg_replace('/[^a-z0-9_]/', '', strtolower(substr((string) ($_REQUEST['source'] ?? ''), 0, 80))),
        'group'   => in_array($group, ['day', 'month'], true) ? $group : 'auto',
    ];
}

/** WHERE + binds over cdb_message_log (alias l). */
function cdp_mcWhere(array $f)
{
    $w = ["l.channel IN ('sms','whatsapp','email')"];
    $b = [];
    if ($f['from'] !== '') {
        $w[] = 'l.created_at >= :from';
        $b[':from'] = $f['from'] . ' 00:00:00';
    }
    if ($f['to'] !== '') {
        $w[] = 'l.created_at <= :to';
        $b[':to'] = $f['to'] . ' 23:59:59';
    }
    if ($f['channel'] !== '') {
        $w[] = 'l.channel = :channel';
        $b[':channel'] = $f['channel'];
    }
    if ($f['source'] !== '') {
        $w[] = 'l.source = :source';
        $b[':source'] = $f['source'];
    }
    return [implode(' AND ', $w), $b];
}

/** Billable units of one row: 1, or the SMS part count when sms_parts is on. */
function cdp_mcUnitsSql($smsParts)
{
    if (!$smsParts) {
        return '1';
    }
    return "CASE WHEN l.channel = 'sms' AND CHAR_LENGTH(l.body) > 160
                 THEN CEIL(CHAR_LENGTH(l.body) / 153) ELSE 1 END";
}

// ---------------------------------------------------------------------------
// The report
// ---------------------------------------------------------------------------

/**
 * Fold per-day rows into the report. Pure: no database, so it can be tested.
 *
 * @param array  $rows   each: d (Y-m-d), ch, status, n (messages), u (units)
 * @param array  $srcRows each: d, ch, source, label, n, u (sent rows only)
 * @param array  $rates  cdp_mcRates()
 * @param string $group  day | month | auto
 * @param string $from   Y-m-d or '' (only for "auto" and to fill empty periods)
 * @param string $to     Y-m-d or ''
 * @return array
 */
function cdp_mcBuild(array $rows, array $srcRows, array $rates, $group, $from = '', $to = '')
{
    $channels = cdp_mcChannels();

    // Period size: a range up to 62 days reads best by day, longer by month.
    if ($group === 'auto') {
        $lo = $from;
        $hi = $to;
        if ($lo === '' || $hi === '') {
            $days = array_map(function ($r) { return (string) $r['d']; }, $rows);
            if ($lo === '') { $lo = $days ? min($days) : ''; }
            if ($hi === '') { $hi = $days ? max($days) : ''; }
        }
        $span = ($lo !== '' && $hi !== '') ? (int) ((strtotime($hi) - strtotime($lo)) / 86400) + 1 : 0;
        $group = $span > 62 ? 'month' : 'day';
    }
    $periodOf = function ($d) use ($group) {
        return $group === 'month' ? substr((string) $d, 0, 7) : (string) $d;
    };

    $blankCh = function () {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'units' => 0, 'cost' => 0.0];
    };

    $totals  = [];
    foreach ($channels as $ch) {
        $totals[$ch] = $blankCh();
    }
    $periods = [];

    foreach ($rows as $r) {
        $ch = (string) $r['ch'];
        if (!isset($totals[$ch])) {
            continue;
        }
        $status = (string) $r['status'];
        $n = (int) $r['n'];
        $p = $periodOf($r['d']);
        if (!isset($periods[$p])) {
            $periods[$p] = [];
            foreach ($channels as $c) {
                $periods[$p][$c] = $blankCh();
            }
        }
        if ($status === 'sent') {
            $units = (int) $r['u'];
            $cost  = $units * cdp_mcRateOn($rates[$ch] ?? [], (string) $r['d']);
            $totals[$ch]['sent']  += $n;
            $totals[$ch]['units'] += $units;
            $totals[$ch]['cost']  += $cost;
            $periods[$p][$ch]['sent']  += $n;
            $periods[$p][$ch]['units'] += $units;
            $periods[$p][$ch]['cost']  += $cost;
        } elseif ($status === 'failed' || $status === 'skipped') {
            $totals[$ch][$status] += $n;
            $periods[$p][$ch][$status] += $n;
        }
    }

    // Fill the empty periods of a bounded range so the chart has no gaps.
    if ($from !== '' && $to !== '') {
        $cursor = strtotime($group === 'month' ? substr($from, 0, 7) . '-01' : $from);
        $end    = strtotime($to);
        $guard  = 0;
        while ($cursor !== false && $cursor <= $end && $guard++ < 1200) {
            $p = $group === 'month' ? date('Y-m', $cursor) : date('Y-m-d', $cursor);
            if (!isset($periods[$p])) {
                $periods[$p] = [];
                foreach ($channels as $c) {
                    $periods[$p][$c] = $blankCh();
                }
            }
            $cursor = strtotime($group === 'month' ? '+1 month' : '+1 day', $cursor);
        }
    }
    ksort($periods);

    $periodList = [];
    foreach ($periods as $p => $byCh) {
        $row = ['period' => $p, 'channels' => [], 'sent' => 0, 'cost' => 0.0];
        foreach ($channels as $ch) {
            $byCh[$ch]['cost'] = round($byCh[$ch]['cost'], 6);
            $row['channels'][$ch] = $byCh[$ch];
            $row['sent'] += $byCh[$ch]['sent'];
            $row['cost'] += $byCh[$ch]['cost'];
        }
        $row['cost'] = round($row['cost'], 6);
        $periodList[] = $row;
    }

    // By origin: sent messages and their cost.
    $sources = [];
    foreach ($srcRows as $r) {
        $ch = (string) $r['ch'];
        if (!in_array($ch, $channels, true)) {
            continue;
        }
        $k = (string) $r['source'];
        if (!isset($sources[$k])) {
            $sources[$k] = ['key' => $k, 'label' => (string) ($r['label'] ?? ''), 'sent' => 0, 'cost' => 0.0,
                            'channels' => array_fill_keys($channels, 0)];
        }
        if ($sources[$k]['label'] === '' && !empty($r['label'])) {
            $sources[$k]['label'] = (string) $r['label'];
        }
        $sources[$k]['sent'] += (int) $r['n'];
        $sources[$k]['channels'][$ch] += (int) $r['n'];
        $sources[$k]['cost'] += (int) $r['u'] * cdp_mcRateOn($rates[$ch] ?? [], (string) $r['d']);
    }
    foreach ($sources as &$s) {
        $s['cost'] = round($s['cost'], 6);
    }
    unset($s);
    usort($sources, function ($a, $b) {
        if ($a['cost'] == $b['cost']) {
            return $b['sent'] <=> $a['sent'];
        }
        return $b['cost'] <=> $a['cost'];
    });

    $grand = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'units' => 0, 'cost' => 0.0];
    foreach ($channels as $ch) {
        $totals[$ch]['cost'] = round($totals[$ch]['cost'], 6);
        foreach ($grand as $k => $v) {
            $grand[$k] += $totals[$ch][$k];
        }
    }
    $grand['cost'] = round($grand['cost'], 6);

    return [
        'group'    => $group,
        'totals'   => $totals,
        'grand'    => $grand,
        'periods'  => $periodList,
        'sources'  => array_values($sources),
    ];
}

/**
 * Run the report for a filter set.
 *
 * @return array{ok:bool,error?:string,...}
 */
function cdp_mcReport(array $f)
{
    if (!cdp_msgTableReady()) {
        return ['ok' => false, 'error' => 'table_missing'];
    }
    $settings = cdp_mcSettings();
    $rates    = cdp_mcRates();
    $units    = cdp_mcUnitsSql((int) $settings['sms_parts'] === 1);
    list($where, $binds) = cdp_mcWhere($f);

    $db = new Conexion;
    $db->cdp_query("SELECT DATE(l.created_at) AS d, l.channel AS ch, l.status AS status,
                           COUNT(*) AS n, SUM($units) AS u
                    FROM cdb_message_log l
                    WHERE $where
                    GROUP BY DATE(l.created_at), l.channel, l.status");
    cdp_mlBind($db, $binds);
    $db->cdp_execute();
    $rows = array_map(function ($r) { return (array) $r; }, (array) $db->cdp_registros());

    $db->cdp_query("SELECT DATE(l.created_at) AS d, l.channel AS ch, l.source AS source,
                           MAX(l.source_label) AS label, COUNT(*) AS n, SUM($units) AS u
                    FROM cdb_message_log l
                    WHERE $where AND l.status = 'sent'
                    GROUP BY DATE(l.created_at), l.channel, l.source");
    cdp_mlBind($db, $binds);
    $db->cdp_execute();
    $srcRows = array_map(function ($r) { return (array) $r; }, (array) $db->cdp_registros());

    $out = cdp_mcBuild($rows, $srcRows, $rates, $f['group'], $f['from'], $f['to']);
    foreach ($out['sources'] as &$s) {
        if ($s['label'] === '') {
            $s['label'] = cdp_msgSourceLabel($s['key']);
        }
    }
    unset($s);

    $out['ok']       = true;
    $out['settings'] = $settings;
    $out['current']  = cdp_mcCurrentRates($rates);
    $out['priced']   = array_map(function ($l) { return count($l) > 0; }, $rates);
    $out['filters']  = $f;
    return $out;
}

/** Money for display / CSV: up to 4 decimals, at least 2. */
function cdp_mcMoney($v)
{
    $s = number_format((float) $v, 4, '.', ',');
    return preg_replace('/(\.\d\d)0{1,2}$/', '$1', $s);
}

// ---------------------------------------------------------------------------
// For other screens (e.g. a composer estimating "cost × recipients").
// ---------------------------------------------------------------------------

/** Unit cost of one message on a channel today: 'sms' | 'whatsapp' | 'email'. */
function cdp_notifyCostPerMessage($channel)
{
    static $current = null;
    if ($current === null) {
        $current = cdp_mcCurrentRates();
    }
    return (float) ($current[strtolower((string) $channel)] ?? 0.0);
}

/** Currency label the unit costs are entered in. */
function cdp_notifyCostCurrency()
{
    return (string) cdp_mcSettings()['currency'];
}
