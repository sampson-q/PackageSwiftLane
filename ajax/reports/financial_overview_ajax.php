<?php
// ============================================================================
// Financial Overview — the unifying finance hub. Full summaries drawn from the
// Financial Sheet ledger (the single source of truth), cutting across billing,
// receipts (cash + gateway), discounts, receivables, and deliverables.
// All money carries BOTH GHS and USD (converted via each row's OWN recorded
// exchange_rate, so figures never drift when the live rate moves).
//   action=load  -> the whole dashboard body (HTML), for a date range.
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/fs_status.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_financial_overview');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$core = new Core;

$range = cdp_sanitize($_REQUEST['range'] ?? '');
$ini = date('Y-m-01 00:00:00');
$fin = date('Y-m-t 23:59:59');
if ($range !== '') {
    $parts = explode(' - ', $range);
    if (count($parts) === 2) {
        $ini = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $parts[0])));
        $fin = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $parts[1])));
    }
}

/** Render a GHS+USD amount as a toggle-aware span (blank text; JS fills it). */
function fo_money($ghs, $usd)
{
    return '<span class="fo-money" data-ghs="' . (float) $ghs . '" data-usd="' . round((float) $usd, 2) . '"></span>';
}

// ---- Period receipts (cash + gateway), with USD via each row's own rate. ----
$db->cdp_query("SELECT COALESCE(SUM(" . cdp_fsMoneyExpr() . "),0) g,
                       COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) u
                FROM cdb_fs_payments WHERE recorded_at BETWEEN :i AND :f
                  AND " . cdp_fsMoneySqlFilter());
$db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
$recv = $db->cdp_registro();

// By method (period).
$db->cdp_query("SELECT mode, COUNT(*) n, COALESCE(SUM(" . cdp_fsMoneyExpr() . "),0) g,
                       COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) u
                FROM cdb_fs_payments WHERE recorded_at BETWEEN :i AND :f
                  AND " . cdp_fsMoneySqlFilter() . " GROUP BY mode");
$db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
$byMethod = (array) $db->cdp_registros();

// Gateway health (period, online only). Deliberately UNfiltered by the money
// rule — this panel exists to surface failures, so hiding them would defeat it.
$db->cdp_query("SELECT gateway_status st, COUNT(*) n FROM cdb_fs_payments
                WHERE mode<>'cash' AND recorded_at BETWEEN :i AND :f GROUP BY gateway_status");
$db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
$gw = (array) $db->cdp_registros();

// Is Paystack actually talking to us? If the webhook URL is not configured in
// the Paystack dashboard, everything still "works" until a customer closes
// their browser mid-payment or a refund lands — and then it silently doesn't.
// Nothing else in the app would ever say so, so surface it here.
$whLast = null; $whCount = 0;
try {
    $db->cdp_query("SELECT MAX(created_at) last_at, COUNT(*) n FROM cdb_fs_payment_events
                    WHERE source = 'paystack_webhook'");
    $db->cdp_execute();
    $whRow = $db->cdp_registro();
    $whLast = $whRow && $whRow->last_at ? (string) $whRow->last_at : null;
    $whCount = $whRow ? (int) $whRow->n : 0;
} catch (Throwable $e) {
    // Event trail not migrated yet.
}
$whOnlinePayments = 0;
try {
    $db->cdp_query("SELECT COUNT(*) n FROM cdb_fs_payments WHERE mode <> 'cash'");
    $db->cdp_execute();
    $whOnlinePayments = (int) $db->cdp_registro()->n;
} catch (Throwable $e) {
}

// ---- Period billing (billed, handling fees). --------------------------------
$db->cdp_query("SELECT COALESCE(SUM(amount_ghs),0) g, COALESCE(SUM(amount_usd),0) u, COALESCE(SUM(handling_ghs),0) fee,
                       COALESCE(SUM(handling_ghs/NULLIF(exchange_rate,0)),0) fee_u
                FROM cdb_consolidate_customer_billing WHERE billed_at BETWEEN :i AND :f");
$db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
$billed = $db->cdp_registro();

// Period discounts.
$db->cdp_query("SELECT COALESCE(SUM(amount_ghs),0) g, COALESCE(SUM(amount_ghs/NULLIF(exchange_rate,0)),0) u
                FROM cdb_fs_discounts WHERE applied_at BETWEEN :i AND :f");
$db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
$disc = $db->cdp_registro();

// ---- Outstanding receivables (ALL-TIME). ------------------------------------
$db->cdp_query("SELECT COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))),0) g,
                       COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0) u,
                       COUNT(DISTINCT CASE WHEN GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))>0 THEN sender_id END) custs
                FROM cdb_consolidate_customer_billing");
$db->cdp_execute();
$out = $db->cdp_registro();

// Aging buckets (all-time, outstanding only).
$db->cdp_query("SELECT
        CASE WHEN DATEDIFF(NOW(), billed_at) <= 7 THEN '0-7'
             WHEN DATEDIFF(NOW(), billed_at) <= 14 THEN '8-14'
             WHEN DATEDIFF(NOW(), billed_at) <= 30 THEN '15-30'
             ELSE '30+' END bucket,
        COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))),0) g,
        COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0) u
     FROM cdb_consolidate_customer_billing
     WHERE GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0)) > 0
     GROUP BY bucket");
$db->cdp_execute();
$agingRows = (array) $db->cdp_registros();
$aging = ['0-7' => [0, 0], '8-14' => [0, 0], '15-30' => [0, 0], '30+' => [0, 0]];
foreach ($agingRows as $a) { $aging[$a->bucket] = [(float) $a->g, (float) $a->u]; }

// Top debtors (all-time).
$db->cdp_query("SELECT b.sender_id,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', b.sender_id)) name,
                       u.locker,
                       SUM(GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))) g,
                       SUM(GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))/NULLIF(b.exchange_rate,0)) u
                FROM cdb_consolidate_customer_billing b
                LEFT JOIN cdb_users u ON u.id = b.sender_id
                GROUP BY b.sender_id
                HAVING g > 0
                ORDER BY g DESC LIMIT 8");
$db->cdp_execute();
$debtors = (array) $db->cdp_registros();

// ---- Deliverables (packages cleared for delivery). Uses idx_fs_cleared so it
// only touches the cleared rows, never a full 40k-row scan. ------------------
$db->cdp_query("SELECT COUNT(*) cleared_n, COALESCE(SUM(total_order),0) cleared_usd
                FROM cdb_add_order WHERE fs_cleared_for_delivery = 1");
$db->cdp_execute();
$deliv = $db->cdp_registro();
$rate = (float) $core->exchange_rate;
$clearedUsd = (float) $deliv->cleared_usd;
$clearedGhs = $rate > 0 ? $clearedUsd * $rate : 0;

// ---- Recent transactions (last 8). ------------------------------------------
$db->cdp_query("SELECT p.amount_ghs, p.exchange_rate, p.mode, p.gateway_status, p.recorded_at,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', p.sender_id)) name
                FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.sender_id
                ORDER BY p.id DESC LIMIT 8");
$db->cdp_execute();
$recent = (array) $db->cdp_registros();

// ---- Per-consolidation snapshot (recent 8 billed). --------------------------
$db->cdp_query("SELECT b.consolidate_id,
                       COALESCE(NULLIF(CONCAT(c.c_prefix,c.c_no),''), CONCAT('#', b.consolidate_id)) cno,
                       SUM(COALESCE(b.amount_ghs,0)) billed_g,
                       SUM(COALESCE(b.paid_ghs,0)) paid_g,
                       SUM(GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))) out_g,
                       MAX(b.billed_at) last_at
                FROM cdb_consolidate_customer_billing b
                LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id
                GROUP BY b.consolidate_id ORDER BY last_at DESC LIMIT 8");
$db->cdp_execute();
$consols = (array) $db->cdp_registros();

// ---- Monthly trend (last 6 months): billed vs received (GHS). ---------------
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("first day of -$i month"));
    $months[$ym] = ['label' => date('M', strtotime($ym . '-01')), 'billed' => 0.0, 'received' => 0.0];
}
$mIni = date('Y-m-01 00:00:00', strtotime('first day of -5 month'));
$db->cdp_query("SELECT DATE_FORMAT(billed_at,'%Y-%m') ym, COALESCE(SUM(amount_ghs),0) g
                FROM cdb_consolidate_customer_billing WHERE billed_at >= :i GROUP BY ym");
$db->bind(':i', $mIni); $db->cdp_execute();
foreach ((array) $db->cdp_registros() as $r) { if (isset($months[$r->ym])) { $months[$r->ym]['billed'] = (float) $r->g; } }
$db->cdp_query("SELECT DATE_FORMAT(recorded_at,'%Y-%m') ym, COALESCE(SUM(" . cdp_fsMoneyExpr() . "),0) g
                FROM cdb_fs_payments WHERE recorded_at >= :i
                  AND " . cdp_fsMoneySqlFilter() . " GROUP BY ym");
$db->bind(':i', $mIni); $db->cdp_execute();
foreach ((array) $db->cdp_registros() as $r) { if (isset($months[$r->ym])) { $months[$r->ym]['received'] = (float) $r->g; } }

// Chart payload (all GHS; the page already shows GHS by default).
$fo_charts = [
    'trend' => [
        'labels'   => array_values(array_map(function ($m) { return $m['label']; }, $months)),
        'billed'   => array_values(array_map(function ($m) { return round($m['billed'], 2); }, $months)),
        'received' => array_values(array_map(function ($m) { return round($m['received'], 2); }, $months)),
    ],
    'method' => [
        'labels' => array_map(function ($m) { return ucfirst((string) $m->mode); }, $byMethod),
        'values' => array_map(function ($m) { return round((float) $m->g, 2); }, $byMethod),
    ],
    'aging' => [
        'labels' => ['0–7d', '8–14d', '15–30d', '30+d'],
        'values' => [round($aging['0-7'][0], 2), round($aging['8-14'][0], 2), round($aging['15-30'][0], 2), round($aging['30+'][0], 2)],
    ],
];

function fo_txStatus($mode, $st)
{
    // Shared vocabulary — see helpers/fs_status.php.
    return cdp_fsStatusLabel($st, $mode);
}
?>
<!-- KPI tiles -->
<div class="row">
    <?php
    $tiles = [
        ['Billed (period)', (float) $billed->g, (float) $billed->u, 'primary'],
        ['Received (period)', (float) $recv->g, (float) $recv->u, 'success'],
        ['Outstanding (all)', (float) $out->g, (float) $out->u, 'danger'],
        ['Discounts (period)', (float) $disc->g, (float) $disc->u, 'warning'],
        ['Handling fees (period)', (float) $billed->fee, (float) $billed->fee_u, 'info'],
        ['Cleared for delivery', $clearedGhs, $clearedUsd, 'secondary'],
    ];
    foreach ($tiles as $t) { ?>
        <div class="col-md-2 col-6 mb-2"><div class="card h-100 border-<?php echo $t[3]; ?>"><div class="card-body py-3">
            <h6 class="text-muted mb-1" style="font-size:12px;"><?php echo $t[0]; ?></h6>
            <h4 class="mb-0"><?php echo fo_money($t[1], $t[2]); ?></h4>
        </div></div></div>
    <?php } ?>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-lg-7 mb-3"><div class="card h-100"><div class="card-body">
        <h5 class="card-title">Billed vs Received <small class="text-muted">— last 6 months (GH&#8373;)</small></h5>
        <div id="fo_chart_trend" style="min-height:280px;"></div>
    </div></div></div>
    <div class="col-lg-5 mb-3"><div class="card h-100"><div class="card-body">
        <h5 class="card-title">Money In by Method <small class="text-muted">(period, GH&#8373;)</small></h5>
        <div id="fo_chart_method" style="min-height:280px;"></div>
    </div></div></div>
</div>
<script type="application/json" id="fo_chart_data"><?php echo json_encode($fo_charts); ?></script>

<div class="row">
    <!-- Money in by method -->
    <div class="col-lg-4 mb-3"><div class="card h-100"><div class="card-body">
        <h5 class="card-title">Money In by Method</h5>
        <table class="table table-sm mb-0">
            <tbody>
            <?php if (!$byMethod) { echo '<tr><td class="text-muted">No receipts in this period.</td></tr>'; }
            foreach ($byMethod as $m) { ?>
                <tr>
                    <td><?php echo htmlspecialchars(ucfirst((string) $m->mode)); ?> <small class="text-muted">(<?php echo (int) $m->n; ?>)</small></td>
                    <td class="text-right"><b><?php echo fo_money($m->g, $m->u); ?></b></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php if ($gw) { ?>
        <hr class="my-2">
        <div class="small text-muted mb-1">Gateway health (period)</div>
        <?php foreach ($gw as $g2) { list($lbl, $cls) = fo_txStatus('paystack', $g2->st); ?>
            <span class="label <?php echo $cls; ?>"><?php echo htmlspecialchars($lbl); ?>: <?php echo (int) $g2->n; ?></span>
        <?php } } ?>

        <hr class="my-2">
        <div class="small text-muted mb-1">Gateway Notifications</div>
        <?php if ($whLast) { ?>
            <span class="label label-success">Paystack Is Reporting In</span>
            <div class="small text-muted mt-1">
                Last notification <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($whLast))); ?>
                (<?php echo (int) $whCount; ?> received).
            </div>
        <?php } elseif ($whOnlinePayments > 0) { ?>
            <span class="label label-danger">No Notifications Received</span>
            <div class="small text-muted mt-1">
                Online payments exist but Paystack has never notified this system. Payments where the
                customer closes their browser, and any refund or chargeback, will NOT appear on their own —
                someone must press "Check Status" on the Financial Sheet. Ask the developers to set the
                webhook URL in Paystack.
            </div>
        <?php } else { ?>
            <span class="label label-default">Nothing Yet</span>
            <div class="small text-muted mt-1">No online payments have been taken yet.</div>
        <?php } ?>
    </div></div></div>

    <!-- Receivables: aging + top debtors -->
    <div class="col-lg-8 mb-3"><div class="card h-100"><div class="card-body">
        <h5 class="card-title">Receivables <small class="text-muted">— <?php echo (int) $out->custs; ?> customer(s) owing</small></h5>
        <div class="row">
            <div class="col-md-5">
                <div class="small text-muted mb-1">Aging (outstanding)</div>
                <div id="fo_chart_aging" style="min-height:170px;"></div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach (['0-7' => '0–7 days', '8-14' => '8–14 days', '15-30' => '15–30 days', '30+' => '30+ days'] as $k => $lbl) { ?>
                        <tr><td><?php echo $lbl; ?></td><td class="text-right"><?php echo fo_money($aging[$k][0], $aging[$k][1]); ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-7">
                <div class="small text-muted mb-1">Top Debtors</div>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php if (!$debtors) { echo '<tr><td class="text-muted">Nobody owes right now.</td></tr>'; }
                    foreach ($debtors as $d) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d->name); ?><?php if ($d->locker) echo ' <small class="text-muted">' . htmlspecialchars($d->locker) . '</small>'; ?></td>
                            <td class="text-right text-danger"><b><?php echo fo_money($d->g, $d->u); ?></b></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div></div>
</div>

<div class="row">
    <!-- Recent transactions -->
    <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recent Transactions</h5>
            <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <table class="table table-sm mt-2 mb-0">
            <tbody>
            <?php if (!$recent) { echo '<tr><td class="text-muted">No transactions yet.</td></tr>'; }
            foreach ($recent as $t) { list($lbl, $cls) = fo_txStatus($t->mode, $t->gateway_status);
                $usd = (float) $t->exchange_rate > 0 ? (float) $t->amount_ghs / (float) $t->exchange_rate : 0; ?>
                <tr>
                    <td><small><?php echo date('m-d H:i', strtotime((string) $t->recorded_at)); ?></small></td>
                    <td><?php echo htmlspecialchars($t->name); ?></td>
                    <td><span class="label <?php echo $cls; ?>"><?php echo htmlspecialchars(ucfirst((string) $t->mode)); ?></span></td>
                    <td class="text-right"><b><?php echo fo_money($t->amount_ghs, $usd); ?></b></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div></div></div>

    <!-- Per-consolidation snapshot -->
    <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Consolidations</h5>
            <a href="financial_sheet.php" class="btn btn-sm btn-outline-primary">Financial Sheet</a>
        </div>
        <table class="table table-sm mt-2 mb-0">
            <thead><tr><th>Consol</th><th class="text-right">Billed</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th></tr></thead>
            <tbody>
            <?php if (!$consols) { echo '<tr><td colspan="4" class="text-muted">No billed consolidations yet.</td></tr>'; }
            foreach ($consols as $c) {
                $bu = $rate > 0 ? (float) $c->billed_g / $rate : 0;
                $pu = $rate > 0 ? (float) $c->paid_g / $rate : 0;
                $ou = $rate > 0 ? (float) $c->out_g / $rate : 0; ?>
                <tr>
                    <td><b><?php echo htmlspecialchars($c->cno); ?></b></td>
                    <td class="text-right"><?php echo fo_money($c->billed_g, $bu); ?></td>
                    <td class="text-right text-success"><?php echo fo_money($c->paid_g, $pu); ?></td>
                    <td class="text-right text-danger"><?php echo fo_money($c->out_g, $ou); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <div class="small text-muted mt-2">Cleared for delivery: <b><?php echo (int) $deliv->cleared_n; ?></b> package(s).</div>
    </div></div></div>
</div>
