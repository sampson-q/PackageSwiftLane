<?php
/**
 * ============================================================================
 * Message Log — shared query building.
 *
 * The table, the statistics and the CSV export read the same filters and build
 * the same WHERE clause, so the stat tiles always describe exactly the rows in
 * the table below them.
 * ============================================================================
 */

require_once __DIR__ . '/message_log.php';

/**
 * Read the request into a normalised filter set.
 */
function cdp_mlFilters()
{
    $s = function ($k, $max = 120) {
        $v = trim((string) ($_REQUEST[$k] ?? ''));
        return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    };
    $date = function ($k) {
        $v = trim((string) ($_REQUEST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    };

    return [
        'from'         => $date('from'),
        'to'           => $date('to'),
        'channel'      => preg_replace('/[^a-z]/', '', strtolower($s('channel', 12))),
        'status'       => preg_replace('/[^a-z]/', '', strtolower($s('status', 12))),
        'source'       => preg_replace('/[^a-z0-9_]/', '', strtolower($s('source', 80))),
        'sent_by'      => (int) ($_REQUEST['sent_by'] ?? 0),
        'recipient_id' => (int) ($_REQUEST['recipient_id'] ?? 0),
        'template_id'  => (int) ($_REQUEST['template_id'] ?? 0),
        'batch_id'     => preg_replace('/[^a-zA-Z0-9_\-]/', '', $s('batch_id', 40)),
        'search'       => $s('search', 120),
    ];
}

/**
 * WHERE clause + binds for a filter set. Alias: l
 *
 * @return array{0:string,1:array}
 */
function cdp_mlWhere(array $f)
{
    $w = ['1=1'];
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
    if ($f['status'] !== '') {
        $w[] = 'l.status = :status';
        $b[':status'] = $f['status'];
    }
    if ($f['source'] !== '') {
        $w[] = 'l.source = :source';
        $b[':source'] = $f['source'];
    }
    if ($f['sent_by'] > 0) {
        $w[] = 'l.sent_by = :sent_by';
        $b[':sent_by'] = $f['sent_by'];
    } elseif ($f['sent_by'] === -1) {
        // "System" — cron, public flows, API
        $w[] = 'l.sent_by = 0';
    }
    if ($f['recipient_id'] > 0) {
        $w[] = 'l.recipient_user_id = :rid';
        $b[':rid'] = $f['recipient_id'];
    }
    if ($f['template_id'] > 0) {
        $w[] = 'l.template_id = :tpl';
        $b[':tpl'] = $f['template_id'];
    }
    if ($f['batch_id'] !== '') {
        $w[] = 'l.batch_id = :batch';
        $b[':batch'] = $f['batch_id'];
    }
    if ($f['search'] !== '') {
        $w[] = '(l.recipient_name LIKE :q OR l.recipient_to LIKE :q OR l.subject LIKE :q
                 OR l.entity_label LIKE :q OR l.sent_by_name LIKE :q OR l.status_detail LIKE :q
                 OR l.body LIKE :q)';
        $b[':q'] = '%' . $f['search'] . '%';
    }

    return [implode(' AND ', $w), $b];
}

function cdp_mlBind($db, array $binds)
{
    foreach ($binds as $k => $v) {
        $db->bind($k, $v);
    }
}

/** Human date label, tolerant of empty values. */
function cdp_mlWhen($v, $fmt = 'Y-m-d H:i:s')
{
    if (!$v) {
        return '—';
    }
    $ts = strtotime((string) $v);
    return $ts ? date($fmt, $ts) : (string) $v;
}

function cdp_mlChannelLabel($c)
{
    $all = cdp_msgChannels();
    return $all[$c]['label'] ?? ucfirst((string) $c);
}

function cdp_mlChannelColor($c)
{
    $all = cdp_msgChannels();
    return $all[$c]['color'] ?? '#6b7788';
}

function cdp_mlStatusLabel($s)
{
    $all = cdp_msgStatuses();
    return $all[$s]['label'] ?? ucfirst((string) $s);
}

function cdp_mlStatusColor($s)
{
    $all = cdp_msgStatuses();
    return $all[$s]['color'] ?? '#6b7788';
}
