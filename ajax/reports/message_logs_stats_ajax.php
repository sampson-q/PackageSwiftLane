<?php
// *************************************************************************
// * Message Logs — statistics for the current filter set (JSON).          *
// *                                                                       *
// * Same WHERE clause as the table, so the tiles describe the rows shown.  *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/message_log_query.php');
require_login();
require_permission('view_message_logs');

header('Content-Type: application/json; charset=utf-8');

if (!cdp_msgTableReady()) {
    echo json_encode(['ok' => false, 'error' => 'table_missing']);
    exit;
}

$db = new Conexion;
$f = cdp_mlFilters();
list($where, $binds) = cdp_mlWhere($f);

function ml_agg($select, $tail, $where, $binds)
{
    $db = new Conexion;
    $db->cdp_query("SELECT $select FROM cdb_message_log l WHERE $where $tail");
    cdp_mlBind($db, $binds);
    $db->cdp_execute();
    return (array) $db->cdp_registros();
}

// ── Headline ────────────────────────────────────────────────────────────────
$db->cdp_query("SELECT
        COUNT(*)                                                 AS total,
        SUM(CASE WHEN l.status = 'sent'    THEN 1 ELSE 0 END)    AS sent,
        SUM(CASE WHEN l.status = 'failed'  THEN 1 ELSE 0 END)    AS failed,
        SUM(CASE WHEN l.status = 'skipped' THEN 1 ELSE 0 END)    AS skipped,
        COUNT(DISTINCT CASE WHEN l.recipient_user_id > 0 THEN l.recipient_user_id ELSE l.recipient_to END) AS recipients,
        COUNT(DISTINCT l.sent_by)                                AS senders,
        MIN(l.created_at)                                        AS first_at,
        MAX(l.created_at)                                        AS last_at
    FROM cdb_message_log l WHERE $where");
cdp_mlBind($db, $binds);
$db->cdp_execute();
$h = $db->cdp_registro();

// ── Timeline per channel (daily) ────────────────────────────────────────────
$tl = ml_agg("DATE(l.created_at) AS d, l.channel AS ch, COUNT(*) AS c",
             "GROUP BY DATE(l.created_at), l.channel ORDER BY d ASC LIMIT 1200", $where, $binds);
$dates = [];
$byDate = [];
foreach ($tl as $r) {
    $dates[$r->d] = true;
    $byDate[$r->d][$r->ch] = (int) $r->c;
}
$dates = array_keys($dates);
$series = [];
foreach (cdp_msgChannels() as $ch => $meta) {
    $data = [];
    foreach ($dates as $d) {
        $data[] = (int) ($byDate[$d][$ch] ?? 0);
    }
    if (array_sum($data) > 0) {
        $series[] = ['name' => $meta['label'], 'color' => $meta['color'], 'data' => $data];
    }
}

// ── Breakdowns ──────────────────────────────────────────────────────────────
$byChannel = ml_agg("l.channel AS k, l.status AS s, COUNT(*) AS c", "GROUP BY l.channel, l.status", $where, $binds);
$channels = [];
foreach (cdp_msgChannels() as $ch => $meta) {
    $channels[$ch] = ['key' => $ch, 'label' => $meta['label'], 'color' => $meta['color'], 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0];
}
foreach ($byChannel as $r) {
    if (!isset($channels[$r->k])) {
        $channels[$r->k] = ['key' => $r->k, 'label' => ucfirst($r->k), 'color' => '#6b7788', 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0];
    }
    $channels[$r->k][$r->s] = (int) $r->c;
    $channels[$r->k]['total'] += (int) $r->c;
}
$channels = array_values(array_filter($channels, function ($c) { return $c['total'] > 0; }));

$bySource = ml_agg("l.source AS k, MAX(l.source_label) AS label, COUNT(*) AS c,
                    SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) AS failed",
                   "GROUP BY l.source ORDER BY c DESC LIMIT 14", $where, $binds);

$bySender = ml_agg("l.sent_by AS id, MAX(l.sent_by_name) AS k, MAX(l.sent_by_role) AS role, COUNT(*) AS c,
                    SUM(CASE WHEN l.status = 'sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN l.status = 'skipped' THEN 1 ELSE 0 END) AS skipped,
                    SUM(CASE WHEN l.channel = 'whatsapp' THEN 1 ELSE 0 END) AS wa,
                    SUM(CASE WHEN l.channel = 'email' THEN 1 ELSE 0 END) AS em,
                    SUM(CASE WHEN l.channel = 'sms' THEN 1 ELSE 0 END) AS sms,
                    MAX(l.created_at) AS last_at",
                   "GROUP BY l.sent_by ORDER BY c DESC LIMIT 15", $where, $binds);

$failures = ml_agg("l.status_detail AS k, l.channel AS ch, COUNT(*) AS c",
                   "AND l.status IN ('failed','skipped') AND l.status_detail <> '' GROUP BY l.status_detail, l.channel ORDER BY c DESC LIMIT 10",
                   $where . ' ', $binds);

$fmt = function ($v) { return $v ? cdp_mlWhen($v) : '—'; };

echo json_encode([
    'ok'       => true,
    'headline' => [
        'total'      => (int) ($h->total ?? 0),
        'sent'       => (int) ($h->sent ?? 0),
        'failed'     => (int) ($h->failed ?? 0),
        'skipped'    => (int) ($h->skipped ?? 0),
        'recipients' => (int) ($h->recipients ?? 0),
        'senders'    => (int) ($h->senders ?? 0),
        'first_at'   => $fmt($h->first_at ?? null),
        'last_at'    => $fmt($h->last_at ?? null),
    ],
    'timeline' => ['dates' => $dates, 'series' => $series],
    'channels' => $channels,
    'sources'  => array_map(function ($r) {
        return ['key' => $r->k, 'label' => $r->label ?: cdp_msgSourceLabel($r->k), 'count' => (int) $r->c, 'failed' => (int) $r->failed];
    }, $bySource),
    'senders'  => array_map(function ($r) {
        return ['id' => (int) $r->id, 'name' => $r->k ?: 'System', 'role' => (string) $r->role, 'count' => (int) $r->c,
                'sent' => (int) $r->sent, 'failed' => (int) $r->failed, 'skipped' => (int) $r->skipped,
                'wa' => (int) $r->wa, 'em' => (int) $r->em, 'sms' => (int) $r->sms, 'last_at' => cdp_mlWhen($r->last_at)];
    }, $bySender),
    'failures' => array_map(function ($r) {
        return ['label' => $r->k, 'channel' => cdp_mlChannelLabel($r->ch), 'count' => (int) $r->c];
    }, $failures),
], JSON_UNESCAPED_UNICODE);
