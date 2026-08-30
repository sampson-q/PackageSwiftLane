<?php
/**
 * ============================================================================
 * Activity Log — shared query building.
 *
 * The table, the statistics and the CSV export all read the same filters and
 * build the same WHERE clause from them. Keeping that in one place is what
 * guarantees the numbers in the stat tiles describe exactly the rows in the
 * table below them.
 * ============================================================================
 */

require_once __DIR__ . '/activity_log.php';

/**
 * Read the request into a normalised filter set.
 *
 * @return array
 */
function cdp_alFilters()
{
    $s = function ($k, $max = 90) {
        $v = trim((string) ($_REQUEST[$k] ?? ''));
        return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    };

    $date = function ($k) {
        $v = trim((string) ($_REQUEST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    };

    return [
        'from'        => $date('from'),
        'to'          => $date('to'),
        'user_id'     => (int) ($_REQUEST['user_id'] ?? 0),
        'role_id'     => (int) ($_REQUEST['role_id'] ?? 0),
        'module'      => preg_replace('/[^a-z_]/', '', strtolower($s('module', 60))),
        'verb'        => preg_replace('/[^a-z_]/', '', strtolower($s('verb', 20))),
        'action'      => $s('action', 90),
        'status_id'   => (int) ($_REQUEST['status_id'] ?? 0),
        'outcome'     => preg_replace('/[^a-z]/', '', strtolower($s('outcome', 12))),
        'entity_type' => preg_replace('/[^a-z_]/', '', strtolower($s('entity_type', 60))),
        'search'      => $s('search', 120),
        // Page views are the highest-volume rows; they are opt-in.
        'show_views'  => !empty($_REQUEST['show_views']) && $_REQUEST['show_views'] !== '0',
    ];
}

/**
 * Build the WHERE clause and the values to bind for a filter set.
 *
 * @param array $f From cdp_alFilters()
 * @return array{0:string,1:array} [sql, binds]
 */
function cdp_alWhere(array $f)
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
    if ($f['user_id'] > 0) {
        $w[] = 'l.user_id = :user_id';
        $b[':user_id'] = $f['user_id'];
    }
    if ($f['role_id'] > 0) {
        $w[] = 'l.role_id = :role_id';
        $b[':role_id'] = $f['role_id'];
    }
    if ($f['module'] !== '') {
        $w[] = 'l.module = :module';
        $b[':module'] = $f['module'];
    }
    if ($f['verb'] !== '') {
        $w[] = 'l.verb = :verb';
        $b[':verb'] = $f['verb'];
    }
    if ($f['action'] !== '') {
        $w[] = 'l.action = :action';
        $b[':action'] = $f['action'];
    }
    if ($f['status_id'] > 0) {
        $w[] = 'l.status_id = :status_id';
        $b[':status_id'] = $f['status_id'];
    }
    if ($f['outcome'] !== '') {
        $w[] = 'l.outcome = :outcome';
        $b[':outcome'] = $f['outcome'];
    }
    if ($f['entity_type'] !== '') {
        $w[] = 'l.entity_type = :entity_type';
        $b[':entity_type'] = $f['entity_type'];
    }
    if ($f['search'] !== '') {
        $w[] = '(l.actor_name LIKE :q OR l.actor_username LIKE :q OR l.summary LIKE :q '
             . 'OR l.entity_label LIKE :q OR l.entity_id LIKE :q OR l.action_label LIKE :q '
             . 'OR l.ip LIKE :q)';
        $b[':q'] = '%' . $f['search'] . '%';
    }
    // Page views drown out real actions, so they only appear on request — and
    // never when the operator has asked for one specific verb.
    if (!$f['show_views'] && $f['verb'] === '') {
        $w[] = "l.verb <> 'view'";
    }

    return [implode(' AND ', $w), $b];
}

/** Bind an array of :name => value pairs onto a prepared statement. */
function cdp_alBind($db, array $binds)
{
    foreach ($binds as $k => $v) {
        $db->bind($k, $v);
    }
}

/** Human date label for a row, tolerant of empty values. */
function cdp_alWhen($v, $fmt = 'Y-m-d H:i:s')
{
    if (!$v) {
        return '—';
    }
    $ts = strtotime((string) $v);
    return $ts ? date($fmt, $ts) : (string) $v;
}

/** Bootstrap contextual class for an outcome. */
function cdp_alOutcomeClass($outcome)
{
    switch ($outcome) {
        case 'denied':  return 'al-pill al-pill--denied';
        case 'failure': return 'al-pill al-pill--failure';
        default:        return 'al-pill al-pill--success';
    }
}

/** Colour token for a verb, used by the pills and the charts. */
function cdp_alVerbColor($verb)
{
    $map = [
        'create'      => '#0aa699',
        'update'      => '#336aea',
        'delete'      => '#f62d51',
        'status'      => '#9b6ef3',
        'assign'      => '#00a8b3',
        'deliver'     => '#3fa34d',
        'payment'     => '#e8a33d',
        'notify'      => '#e35d9c',
        'upload'      => '#7d8fa9',
        'export'      => '#b06ab3',
        'print'       => '#8f9aab',
        'login'       => '#2c9c6a',
        'logout'      => '#95a2b3',
        'impersonate' => '#d9822b',
        'view'        => '#c3cbd8',
        'other'       => '#6b7788',
    ];
    return $map[$verb] ?? '#6b7788';
}
