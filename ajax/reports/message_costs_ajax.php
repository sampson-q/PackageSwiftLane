<?php
// *************************************************************************
// * Message Costs — counts and costs for the current filter set.          *
// *                                                                       *
// * GET            JSON: totals per channel, per period, per origin        *
// * GET format=csv the per-period breakdown as a CSV file                  *
// *                                                                       *
// * Counts come from cdb_message_log; prices and the arithmetic from       *
// * helpers/message_cost.php, so the tiles, the chart, the table and the   *
// * export always agree.                                                   *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/message_log_query.php');
require_once(__DIR__ . '/../../helpers/message_cost.php');
require_login();
require_permission('view_message_logs');

$f = cdp_mcFilters();
$r = cdp_mcReport($f);

if (($_GET['format'] ?? '') !== 'csv') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$user->cdp_hasPermission('export_message_logs')) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

$filename = 'message-costs-' . ($f['from'] ?: 'start') . '-to-' . ($f['to'] ?: 'now') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

if (empty($r['ok'])) {
    fputcsv($out, ['The message log is not set up on this database.']);
    exit;
}

$cur  = $r['settings']['currency'];
$head = [$r['group'] === 'month' ? 'Month' : 'Day'];
foreach (cdp_mcChannels() as $ch) {
    $label = cdp_mlChannelLabel($ch);
    $head[] = $label . ' Sent';
    if ($ch === 'sms' && (int) $r['settings']['sms_parts'] === 1) {
        $head[] = 'SMS Parts';
    }
    $head[] = $label . ' Cost (' . $cur . ')';
}
$head[] = 'Total Sent';
$head[] = 'Total Cost (' . $cur . ')';
fputcsv($out, $head);

$line = function ($label, array $byCh, $sent, $cost) use ($r) {
    $row = [$label];
    foreach (cdp_mcChannels() as $ch) {
        $row[] = (int) $byCh[$ch]['sent'];
        if ($ch === 'sms' && (int) $r['settings']['sms_parts'] === 1) {
            $row[] = (int) $byCh[$ch]['units'];
        }
        $row[] = number_format((float) $byCh[$ch]['cost'], 4, '.', '');
    }
    $row[] = (int) $sent;
    $row[] = number_format((float) $cost, 4, '.', '');
    return $row;
};

foreach ($r['periods'] as $p) {
    fputcsv($out, $line($p['period'], $p['channels'], $p['sent'], $p['cost']));
}
fputcsv($out, $line('Total', $r['totals'], $r['grand']['sent'], $r['grand']['cost']));

fputcsv($out, []);
fputcsv($out, ['Unit cost in effect today']);
foreach (cdp_mcChannels() as $ch) {
    fputcsv($out, [cdp_mlChannelLabel($ch), number_format((float) $r['current'][$ch], 6, '.', ''), $cur]);
}
