<?php
// *************************************************************************
// * Activity Logs — CSV of the current filter set.                        *
// *                                                                       *
// * Streams rather than buffers: an audit export can be hundreds of        *
// * thousands of rows and must not be held in memory. Capped so a stray    *
// * unfiltered click cannot pull the whole table.                          *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/activity_log_query.php');
require_login();
require_permission('export_activity_logs');

const AL_EXPORT_MAX = 50000;

$db = new Conexion;
$f = cdp_alFilters();
list($where, $binds) = cdp_alWhere($f);

$filename = 'activity-log-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM so Excel reads the UTF-8 names correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'When', 'User ID', 'Who', 'Username', 'Role', 'Acting Via (View As)',
    'Module', 'Action', 'Action Type', 'Outcome', 'Summary',
    'Record Type', 'Record ID', 'Record', 'Status Set',
    'Changes', 'IP Address', 'Method', 'Endpoint',
]);

$db->cdp_query("SELECT l.* FROM cdb_activity_log l WHERE $where
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT " . AL_EXPORT_MAX);
cdp_alBind($db, $binds);
$db->cdp_execute();

$n = 0;
foreach ((array) $db->cdp_registros() as $r) {
    $changes = json_decode((string) $r->changes, true);
    $flat = '';
    if (is_array($changes)) {
        $bits = [];
        foreach ($changes as $field => $c) {
            $bits[] = $field . ': ' . (is_array($c) ? (($c['from'] ?? '') . ' -> ' . ($c['to'] ?? '')) : (string) $c);
        }
        $flat = implode(' | ', $bits);
    }

    fputcsv($out, [
        cdp_alWhen($r->created_at),
        (int) $r->user_id,
        $r->actor_name,
        $r->actor_username,
        $r->role_name,
        (int) $r->impersonated_by > 0 ? ('operator #' . (int) $r->impersonated_by) : '',
        cdp_activityModuleLabel($r->module),
        $r->action,
        cdp_activityVerbLabel($r->verb),
        ucfirst((string) $r->outcome),
        $r->summary,
        $r->entity_type,
        $r->entity_id,
        $r->entity_label,
        $r->status_name,
        $flat,
        $r->ip,
        $r->method,
        $r->endpoint,
    ]);
    $n++;
}

if ($n >= AL_EXPORT_MAX) {
    fputcsv($out, ['', '', '', '', '', '', '', '', '', '', 'TRUNCATED at ' . AL_EXPORT_MAX . ' rows — narrow the date range to export the rest.']);
}

fclose($out);

// The export itself is an action worth recording.
cdp_activityLog([
    'module'  => 'system',
    'verb'    => 'export',
    'action'  => 'system.activity_log_export',
    'label'   => 'System · Activity Log Exported',
    'summary' => 'Exported ' . $n . ' activity log entries',
    'meta'    => ['filters' => $f, 'rows' => $n],
]);
