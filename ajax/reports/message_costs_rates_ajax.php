<?php
// *************************************************************************
// * Message Costs — unit prices and settings.                             *
// *                                                                       *
// * GET                       rates (with history) + settings (JSON)       *
// * POST action=save          sms / whatsapp / email unit cost, from date,  *
// *                           currency, sms_parts                          *
// * POST action=delete id=N   remove one price from the history            *
// *                                                                       *
// * Changing prices: Super Admin and Administrator (cdp_mcCanEdit). Every  *
// * change is written to the activity log.                                 *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/message_log_query.php');
require_once(__DIR__ . '/../../helpers/message_cost.php');
require_login();
require_permission('view_message_logs');

header('Content-Type: application/json; charset=utf-8');

$payload = function () {
    $rates = cdp_mcRates();
    return [
        'ok'       => true,
        'ready'    => cdp_mcEnsureTables(),
        'rates'    => $rates,
        'current'  => cdp_mcCurrentRates($rates),
        'settings' => cdp_mcSettings(true),
    ];
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    echo json_encode($payload());
    exit;
}

if (!cdp_mcCanEdit($user)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['ok' => false, 'error' => 'Only an Administrator can change message prices.']);
    exit;
}
if (!cdp_mcEnsureTables()) {
    echo json_encode(['ok' => false, 'error' => 'The cost tables could not be created. The database user needs the CREATE privilege.']);
    exit;
}

$uid    = (int) ($_SESSION['userid'] ?? 0);
$action = (string) ($_POST['action'] ?? 'save');
$log    = function ($summary, $changes) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog([
            'module'  => 'reports',
            'verb'    => 'update',
            'action'  => 'reports.message_costs',
            'label'   => 'Reports · Message Costs',
            'summary' => $summary,
            'changes' => $changes,
        ]);
    }
};

if ($action === 'delete') {
    $row = cdp_mcDeleteRate((int) ($_POST['id'] ?? 0));
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'That price no longer exists.']);
        exit;
    }
    $log('Removed the ' . cdp_mlChannelLabel($row['channel']) . ' price of ' . cdp_mcMoney($row['unit_cost'])
         . ' from ' . $row['effective_from'], ['removed' => $row]);
    echo json_encode($payload());
    exit;
}

// ── save ────────────────────────────────────────────────────────────────────
$from = trim((string) ($_POST['from'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = substr(cdp_msgNow(), 0, 10);
}

$before = cdp_mcCurrentRates();
$errors = [];
$changes = [];
$bits = [];
foreach (cdp_mcChannels() as $ch) {
    if (!array_key_exists($ch, $_POST)) {
        continue;
    }
    $raw = trim(str_replace(',', '.', (string) $_POST[$ch]));
    if ($raw === '') {
        continue;
    }
    if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 1000000) {
        $errors[] = cdp_mlChannelLabel($ch) . ' cost must be a number of 0 or more.';
        continue;
    }
    // Same price as the one in effect on that day: nothing to record.
    $rates = cdp_mcRates();
    if ($rates[$ch] && abs(cdp_mcRateOn($rates[$ch], $from) - (float) $raw) < 0.0000005) {
        continue;
    }
    if (cdp_mcSaveRate($ch, (float) $raw, $from, $uid)) {
        $changes[$ch] = ['from' => $before[$ch], 'to' => (float) $raw, 'effective_from' => $from];
        $bits[] = cdp_mlChannelLabel($ch) . ' ' . cdp_mcMoney($before[$ch]) . ' → ' . cdp_mcMoney($raw);
    } else {
        $errors[] = 'Could not save the ' . cdp_mlChannelLabel($ch) . ' cost.';
    }
}

if ($errors) {
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

$sBefore = cdp_mcSettings(true);
$sAfter  = cdp_mcSaveSettings(array_intersect_key($_POST, ['currency' => 1, 'sms_parts' => 1]), $uid);
foreach ($sAfter as $k => $v) {
    if ((string) ($sBefore[$k] ?? '') !== (string) $v) {
        $changes[$k] = ['from' => $sBefore[$k] ?? null, 'to' => $v];
        $bits[] = $k . ' ' . $sBefore[$k] . ' → ' . $v;
    }
}

if ($bits) {
    $log('Changed message costs (from ' . $from . '): ' . implode(', ', $bits), $changes);
}

echo json_encode($payload() + ['changed' => array_keys($changes)]);
