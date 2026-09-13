<?php
// *************************************************************************
// * Message Logs — CSV of the current filter set.                         *
// *                                                                       *
// * Streams; capped so an unfiltered click cannot pull the whole table.    *
// * Bodies are included (flattened) so a dispute can be settled from the   *
// * export alone.                                                          *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/message_log_query.php');
require_login();
require_permission('export_message_logs');

const ML_EXPORT_MAX = 50000;

$db = new Conexion;
$f = cdp_mlFilters();
list($where, $binds) = cdp_mlWhere($f);

$filename = 'message-log-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'When', 'Channel', 'Status', 'Result',
    'Recipient', 'Recipient User ID', 'Sent To',
    'Subject', 'Message',
    'Origin', 'Template', 'Record Type', 'Record ID', 'Record', 'Batch',
    'Sent By', 'Sent By Role', 'IP Address', 'Endpoint',
]);

$db->cdp_query("SELECT l.* FROM cdb_message_log l WHERE $where
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT " . ML_EXPORT_MAX);
cdp_mlBind($db, $binds);
$db->cdp_execute();

$n = 0;
foreach ((array) $db->cdp_registros() as $r) {
    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r->body)));
    fputcsv($out, [
        cdp_mlWhen($r->created_at),
        cdp_mlChannelLabel($r->channel),
        cdp_mlStatusLabel($r->status),
        $r->status_detail,
        $r->recipient_name,
        (int) $r->recipient_user_id,
        $r->recipient_to,
        $r->subject,
        $body,
        $r->source_label ?: cdp_msgSourceLabel($r->source),
        $r->template_id,
        $r->entity_type,
        $r->entity_id,
        $r->entity_label,
        $r->batch_id,
        $r->sent_by_name ?: 'System',
        $r->sent_by_role,
        $r->ip,
        $r->endpoint,
    ]);
    $n++;
}

if ($n >= ML_EXPORT_MAX) {
    fputcsv($out, ['TRUNCATED at ' . ML_EXPORT_MAX . ' rows — narrow the date range to export the rest.']);
}

fclose($out);

if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'  => 'system',
        'verb'    => 'export',
        'action'  => 'system.message_log_export',
        'label'   => 'System · Message Log Exported',
        'summary' => 'Exported ' . $n . ' message log entries',
        'meta'    => ['filters' => $f, 'rows' => $n],
    ]);
}
