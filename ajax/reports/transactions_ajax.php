<?php
// ============================================================================
// Transactions dashboard — data endpoint. Unifies every money-in event that
// flows through the Financial Sheet ledger (cdb_fs_payments): cash + gateway
// (Paystack/Hubtel) payments, each enriched with audit detail — customer,
// consolidation, and the package tracking numbers the payment cleared.
//   action=list    -> summary tiles + paginated table (HTML)
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/fs_status.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/autoload_lang.php');
require_login();
require_permission('view_transactions');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$range  = cdp_sanitize($_REQUEST['range'] ?? '');
$search = trim((string) ($_REQUEST['search'] ?? ''));
$mode   = strtolower(trim((string) ($_REQUEST['mode'] ?? '')));
$status = strtolower(trim((string) ($_REQUEST['status'] ?? '')));

$where = " WHERE 1=1 ";
$bind  = [];

// userlevel 1 (customer) only sees their own payments.
if (isset($userData->userlevel) && (int) $userData->userlevel === 1) {
    $where .= " AND p.sender_id = :own ";
    $bind[':own'] = (int) $_SESSION['userid'];
}

if (in_array($mode, ['cash', 'paystack', 'hubtel', 'paypal'], true)) {
    $where .= " AND p.mode = :mode ";
    $bind[':mode'] = $mode;
}
// Accept any status in the shared vocabulary — previously only four were
// filterable, so you could not search for reversed or refunded payments.
if ($status !== '' && in_array($status, cdp_fsKnownStatuses(), true)) {
    $where .= " AND p.gateway_status = :st ";
    $bind[':st'] = $status;
}
if ($search !== '') {
    // reference, customer name/locker, or a package tracking number.
    $where .= " AND (p.reference LIKE :q
                     OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q
                     OR u.locker LIKE :q
                     OR EXISTS (SELECT 1 FROM cdb_add_order a
                                WHERE FIND_IN_SET(a.order_id, REPLACE(REPLACE(REPLACE(p.cleared_orders,'[',''),']',''),' ',''))
                                  AND CONCAT(a.order_prefix,a.order_no) LIKE :q)) ";
    $bind[':q'] = '%' . $search . '%';
}
if ($range !== '') {
    $parts = explode(' - ', $range);
    if (count($parts) === 2) {
        $ini = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $parts[0])));
        $fin = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $parts[1])));
        $where .= " AND p.recorded_at BETWEEN :ini AND :fin ";
        $bind[':ini'] = $ini;
        $bind[':fin'] = $fin;
    }
}

$from = " FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.sender_id ";

// ---- Summary over the whole filtered set. ----------------------------------
// The tiles say "Received", so they must count ONLY money we actually hold —
// a reversed or pending row would otherwise be reported as revenue. The LIST
// below stays unfiltered on purpose: it is a transaction log, and it has its
// own status filter for looking at failures.
$moneyOnly = ' AND ' . cdp_fsMoneySqlFilter('p') . ' ';
$sumSql = "SELECT COUNT(*) AS n,
                  COALESCE(SUM(" . cdp_fsMoneyExpr('p') . "),0) AS total,
                  COALESCE(SUM(CASE WHEN p.mode='cash' THEN " . cdp_fsMoneyExpr('p') . " ELSE 0 END),0) AS cash,
                  COALESCE(SUM(CASE WHEN p.mode<>'cash' THEN " . cdp_fsMoneyExpr('p') . " ELSE 0 END),0) AS electronic,
                  COALESCE(SUM(" . cdp_fsMoneyExpr('p') . " / NULLIF(p.exchange_rate,0)),0) AS total_usd,
                  COALESCE(SUM(CASE WHEN p.mode='cash' THEN " . cdp_fsMoneyExpr('p') . " / NULLIF(p.exchange_rate,0) ELSE 0 END),0) AS cash_usd,
                  COALESCE(SUM(CASE WHEN p.mode<>'cash' THEN " . cdp_fsMoneyExpr('p') . " / NULLIF(p.exchange_rate,0) ELSE 0 END),0) AS electronic_usd
           $from $where $moneyOnly";
$db->cdp_query($sumSql);
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$sum = $db->cdp_registro();

// ---- Pagination. -----------------------------------------------------------
$page = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000
    : (in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int) $_REQUEST['per_page'] : 25);
$numrows = $sum ? (int) $sum->n : 0;
$total_pages = (int) ceil($numrows / $per_page);
$offset = ($page - 1) * $per_page;

$listSql = "SELECT p.*,
                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', p.sender_id)) AS customer,
                   u.locker AS locker,
                   ub.fname AS by_fname, ub.lname AS by_lname
            $from
            LEFT JOIN cdb_users ub ON ub.id = p.recorded_by
            $where
            ORDER BY p.id DESC
            LIMIT $offset, $per_page";
$db->cdp_query($listSql);
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$rows = $db->cdp_registros();

// Resolve cleared package tracking numbers for the visible rows in one query.
$allOids = [];
$rowOids = [];
foreach ((array) $rows as $r) {
    $ids = json_decode((string) $r->cleared_orders, true);
    $ids = is_array($ids) ? array_map('intval', $ids) : [];
    $rowOids[(int) $r->id] = $ids;
    foreach ($ids as $o) { $allOids[$o] = true; }
}
$trackByOid = [];
if ($allOids) {
    $in = implode(',', array_map('intval', array_keys($allOids)));
    $db->cdp_query("SELECT order_id, CONCAT(order_prefix, order_no) AS track FROM cdb_add_order WHERE order_id IN ($in)");
    $db->cdp_execute();
    foreach ((array) $db->cdp_registros() as $t) { $trackByOid[(int) $t->order_id] = $t->track; }
}

function cdp_txStatusLabel($mode, $status)
{
    // Delegates to the shared vocabulary (helpers/fs_status.php) so this screen,
    // the Financial Sheet, the Overview and Global Payments can never disagree
    // about what a Paystack status means.
    return cdp_fsStatusLabel($status, $mode);
}
?>
<div class="row">
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Transactions</h6>
        <h4 class="mb-0"><?php echo (int) $numrows; ?></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Total Received</h6>
        <h4 class="mb-0 tx-money" data-ghs="<?php echo (float) ($sum ? $sum->total : 0); ?>" data-usd="<?php echo (float) ($sum ? $sum->total_usd : 0); ?>"></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Cash</h6>
        <h4 class="mb-0 tx-money" data-ghs="<?php echo (float) ($sum ? $sum->cash : 0); ?>" data-usd="<?php echo (float) ($sum ? $sum->cash_usd : 0); ?>"></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Electronic</h6>
        <h4 class="mb-0 tx-money" data-ghs="<?php echo (float) ($sum ? $sum->electronic : 0); ?>" data-usd="<?php echo (float) ($sum ? $sum->electronic_usd : 0); ?>"></h4>
    </div></div></div>
</div>

<div class="table-responsive">
    <table class="table table-condensed table-hover table-striped">
        <thead>
            <tr>
                <th><b>Date</b></th>
                <th><b>Customer</b></th>
                <th><b>Packages Cleared</b></th>
                <th class="text-center"><b>Method</b></th>
                <th class="text-center"><b>Reference</b></th>
                <th class="text-center"><b>Status</b></th>
                <th class="text-right"><b>Amount</b></th>
                <th class="text-center tx-rate-col"><b>Rate</b></th>
                <th><b>By</b></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows) { ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No transactions found.</td></tr>
        <?php } else {
            foreach ($rows as $r) {
                $tracks = [];
                foreach ($rowOids[(int) $r->id] as $o) { if (isset($trackByOid[$o])) { $tracks[] = $trackByOid[$o]; } }
                list($stLabel, $stClass) = cdp_txStatusLabel($r->mode, $r->gateway_status);
                $by = trim((string) $r->by_fname . ' ' . (string) $r->by_lname);
                $rate = (float) $r->exchange_rate;
                $usd  = $rate > 0 ? ((float) $r->amount_ghs / $rate) : 0.0;
        ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', strtotime((string) $r->recorded_at)); ?></td>
                <td><?php echo htmlspecialchars($r->customer); ?><?php if ($r->locker) { ?><br><small class="text-muted"><?php echo htmlspecialchars($r->locker); ?></small><?php } ?></td>
                <td><small><?php echo $tracks ? htmlspecialchars(implode(', ', $tracks)) : '<span class="text-muted">—</span>'; ?></small></td>
                <td class="text-center"><?php echo htmlspecialchars(ucfirst((string) $r->mode)); ?></td>
                <td class="text-center"><small><?php echo $r->reference ? htmlspecialchars((string) $r->reference) : '<span class="text-muted">—</span>'; ?></small></td>
                <td class="text-center"><span class="label <?php echo $stClass; ?>"><?php echo htmlspecialchars($stLabel); ?></span></td>
                <td class="text-right"><b class="tx-money" data-ghs="<?php echo (float) $r->amount_ghs; ?>" data-usd="<?php echo round($usd, 2); ?>"></b></td>
                <td class="text-center tx-rate-col"><small class="text-muted"><?php echo $rate > 0 ? '@ ' . number_format($rate, 4) : '—'; ?></small></td>
                <td><small><?php echo htmlspecialchars($by !== '' ? $by : ('User ' . $r->recorded_by)); ?></small></td>
            </tr>
        <?php } } ?>
        </tbody>
    </table>
    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, 4, $lang, 'transactions'); ?>
    </div>
</div>
