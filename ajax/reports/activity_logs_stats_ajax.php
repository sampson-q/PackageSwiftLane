<?php
// *************************************************************************
// * Activity Logs — statistics for the current filter set.                *
// *                                                                       *
// * Returns JSON: headline counts, a daily timeline, and the breakdowns    *
// * by action type, module, actor and status. Built from the SAME filter   *
// * clause as the table, so the numbers always describe the rows shown.    *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/activity_log_query.php');
require_login();
require_permission('view_activity_logs');

header('Content-Type: application/json; charset=utf-8');

$db = new Conexion;
$f = cdp_alFilters();
list($where, $binds) = cdp_alWhere($f);

/**
 * Run one aggregate against the filtered set.
 *
 * @param string $select  Column list
 * @param string $tail    GROUP BY / ORDER BY / LIMIT
 * @return array
 */
function al_agg($select, $tail, $where, $binds)
{
    $db = new Conexion;
    $db->cdp_query("SELECT $select FROM cdb_activity_log l WHERE $where $tail");
    cdp_alBind($db, $binds);
    $db->cdp_execute();
    return (array) $db->cdp_registros();
}

// ── Headline counts ─────────────────────────────────────────────────────────
$db->cdp_query("SELECT
        COUNT(*)                                                   AS total,
        COUNT(DISTINCT l.user_id)                                  AS actors,
        SUM(CASE WHEN l.verb IN ('create','update','delete','status','payment','assign','deliver') THEN 1 ELSE 0 END) AS writes,
        SUM(CASE WHEN l.verb = 'delete'  THEN 1 ELSE 0 END)        AS deletions,
        SUM(CASE WHEN l.outcome = 'denied' THEN 1 ELSE 0 END)      AS denied,
        SUM(CASE WHEN l.outcome = 'failure' THEN 1 ELSE 0 END)     AS failures,
        MIN(l.created_at)                                          AS first_at,
        MAX(l.created_at)                                          AS last_at
    FROM cdb_activity_log l WHERE $where");
cdp_alBind($db, $binds);
$db->cdp_execute();
$head = $db->cdp_registro();

// ── Timeline (daily) ────────────────────────────────────────────────────────
$timeline = al_agg(
    "DATE(l.created_at) AS d, COUNT(*) AS c",
    "GROUP BY DATE(l.created_at) ORDER BY d ASC LIMIT 400",
    $where, $binds
);

// ── Breakdowns ──────────────────────────────────────────────────────────────
$byVerb = al_agg(
    "l.verb AS k, COUNT(*) AS c",
    "GROUP BY l.verb ORDER BY c DESC",
    $where, $binds
);

$byModule = al_agg(
    "l.module AS k, COUNT(*) AS c",
    "GROUP BY l.module ORDER BY c DESC LIMIT 12",
    $where, $binds
);

$byActor = al_agg(
    "l.user_id AS id, l.actor_name AS k, l.role_name AS role, COUNT(*) AS c,
     SUM(CASE WHEN l.verb = 'create' THEN 1 ELSE 0 END) AS creates,
     SUM(CASE WHEN l.verb = 'update' THEN 1 ELSE 0 END) AS updates,
     SUM(CASE WHEN l.verb = 'delete' THEN 1 ELSE 0 END) AS deletes,
     SUM(CASE WHEN l.verb = 'status' THEN 1 ELSE 0 END) AS statuses,
     MAX(l.created_at) AS last_at",
    "GROUP BY l.user_id, l.actor_name, l.role_name ORDER BY c DESC LIMIT 15",
    $where, $binds
);

$byRole = al_agg(
    "l.role_id AS id, l.role_name AS k, COUNT(*) AS c",
    "GROUP BY l.role_id, l.role_name ORDER BY c DESC LIMIT 12",
    $where, $binds
);

// Status moves: only rows that actually set a status.
$byStatus = al_agg(
    "l.status_name AS k, l.status_id AS id, COUNT(*) AS c",
    "AND l.status_id IS NOT NULL GROUP BY l.status_id, l.status_name ORDER BY c DESC LIMIT 15",
    $where, $binds
);

$byAction = al_agg(
    "l.action AS k, l.action_label AS label, COUNT(*) AS c",
    "GROUP BY l.action, l.action_label ORDER BY c DESC LIMIT 15",
    $where, $binds
);

// ── Shape the response ──────────────────────────────────────────────────────
$verbs = [];
foreach ($byVerb as $r) {
    $verbs[] = [
        'key'   => (string) $r->k,
        'label' => cdp_activityVerbLabel($r->k),
        'count' => (int) $r->c,
        'color' => cdp_alVerbColor($r->k),
    ];
}

$modules = [];
foreach ($byModule as $r) {
    $modules[] = [
        'key'   => (string) $r->k,
        'label' => cdp_activityModuleLabel($r->k),
        'count' => (int) $r->c,
    ];
}

$actors = [];
foreach ($byActor as $r) {
    $actors[] = [
        'id'       => (int) $r->id,
        'name'     => (string) ($r->k ?: '—'),
        'role'     => (string) $r->role,
        'count'    => (int) $r->c,
        'creates'  => (int) $r->creates,
        'updates'  => (int) $r->updates,
        'deletes'  => (int) $r->deletes,
        'statuses' => (int) $r->statuses,
        'last_at'  => cdp_alWhen($r->last_at, 'Y-m-d H:i'),
    ];
}

$roles = [];
foreach ($byRole as $r) {
    $roles[] = ['id' => (int) $r->id, 'label' => (string) ($r->k ?: '—'), 'count' => (int) $r->c];
}

$statuses = [];
foreach ($byStatus as $r) {
    $statuses[] = ['id' => (int) $r->id, 'label' => (string) ($r->k ?: '—'), 'count' => (int) $r->c];
}

$actions = [];
foreach ($byAction as $r) {
    $actions[] = ['key' => (string) $r->k, 'label' => (string) ($r->label ?: $r->k), 'count' => (int) $r->c];
}

echo json_encode([
    'ok' => true,
    'headline' => [
        'total'     => (int) ($head->total ?? 0),
        'actors'    => (int) ($head->actors ?? 0),
        'writes'    => (int) ($head->writes ?? 0),
        'deletions' => (int) ($head->deletions ?? 0),
        'denied'    => (int) ($head->denied ?? 0),
        'failures'  => (int) ($head->failures ?? 0),
        'first_at'  => cdp_alWhen($head->first_at ?? null, 'Y-m-d H:i'),
        'last_at'   => cdp_alWhen($head->last_at ?? null, 'Y-m-d H:i'),
    ],
    'timeline' => array_map(function ($r) {
        return ['date' => (string) $r->d, 'count' => (int) $r->c];
    }, $timeline),
    'verbs'    => $verbs,
    'modules'  => $modules,
    'actors_top' => $actors,
    'roles'    => $roles,
    'statuses' => $statuses,
    'actions'  => $actions,
]);
