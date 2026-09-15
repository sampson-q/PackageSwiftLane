<?php
// ============================================================================
// Transactions Control Panel — receivables at a glance, driven ENTIRELY by the
// Financial Sheet ledger (single source of truth). All figures are USD to
// match $core->currency: billed uses the USD snapshot; received/outstanding
// convert GHS via each row's OWN stored exchange rate, net of refunds.
// (Agency-restricted admins see company-wide finance — the FS billing ledger
// has no agency column.)
// ============================================================================

require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/fs_status.php');
require_once(__DIR__ . '/../../helpers/dashboard_data.php');
$db = new Conexion;
$userData = $user->cdp_getUserData();

// Clients reaching this panel only ever see their own ledger.
$fs_sender = ((int) $userData->userlevel === 1) ? (int) $_SESSION['userid'] : null;

$monthName = obtenerNombreMes((int) date('n'));

$fs = cdp_dashFsTotals($fs_sender);
$ct_payments_month = cdp_dashCount(
    'cdb_fs_payments',
    "AND recorded_at >= '" . date('Y-m-01') . "' AND " . cdp_fsMoneySqlFilter()
    . ($fs_sender !== null ? " AND sender_id = $fs_sender" : '')
);

$fsMonthly = cdp_dashFsMonthly($fs_sender);
// Payment methods this year + receivables ageing (Financial Sheet, USD)
$fsOwn = $fs_sender !== null ? " AND sender_id = $fs_sender" : '';
$modes = cdp_dashRows("SELECT LOWER(COALESCE(mode,'cash')) m, COUNT(*) n, COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) usd
                       FROM cdb_fs_payments WHERE YEAR(recorded_at)=YEAR(CURDATE()) AND " . cdp_fsMoneySqlFilter() . $fsOwn . " GROUP BY m ORDER BY usd DESC");
$modeBd = ['labels' => [], 'colors' => [], 'totals' => []];
$modeColors = ['cash' => '#FFCB01', 'paystack' => '#0077B6', 'hubtel' => '#00B4D8', 'paypal' => '#7C3EE2'];
foreach ($modes as $i => $m) { $modeBd['labels'][] = ucwords($m->m); $modeBd['colors'][] = $modeColors[$m->m] ?? '#9BA9BB'; $modeBd['totals'][] = round((float) $m->usd, 2); }
$bal = "GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)"; $age = "DATEDIFF(NOW(), billed_at)";
$r = cdp_dashRows("SELECT COALESCE(SUM(CASE WHEN $age <= 30 THEN $bal END),0) a0, COALESCE(SUM(CASE WHEN $age > 30 AND $age <= 60 THEN $bal END),0) a1, COALESCE(SUM(CASE WHEN $age > 60 THEN $bal END),0) a2
                   FROM cdb_consolidate_customer_billing WHERE $bal > 0" . $fsOwn);
$a0 = (float) ($r[0]->a0 ?? 0); $a1 = (float) ($r[0]->a1 ?? 0); $a2 = (float) ($r[0]->a2 ?? 0); $aMax = max(1, $a0, $a1, $a2);
$recvRows = [['0 - 30 days', cdb_money_format($a0), cdp_dashPct($a0, $aMax), 'var(--leaf-500)'], ['31 - 60 days', cdb_money_format($a1), cdp_dashPct($a1, $aMax), 'var(--amber-500)'], ['61+ days', cdb_money_format($a2), cdp_dashPct($a2, $aMax), 'var(--red-500)']];
$owing = cdp_dashRows("SELECT b.sender_id, COALESCE(NULLIF(TRIM(u.company),''), TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')))) lbl, COUNT(*) n, MAX($age) oldest, SUM($bal) usd
                       FROM cdb_consolidate_customer_billing b JOIN cdb_users u ON u.id = b.sender_id WHERE $bal > 0" . str_replace(' AND sender_id', ' AND b.sender_id', $fsOwn) . " GROUP BY b.sender_id, lbl ORDER BY usd DESC LIMIT 5");
$owingRows = [];
foreach ($owing as $o) { $owingRows[] = [$o->lbl, (int) $o->n . ' bill' . ((int) $o->n === 1 ? '' : 's') . ' · oldest ' . (int) $o->oldest . ' days', cdb_money_format((float) $o->usd), (int) $o->oldest > 60 ? 'var(--red-500)' : ((int) $o->oldest > 30 ? 'var(--amber-500)' : 'var(--leaf-500)'), 'accounts_receivable.php']; }
$charts = [
    [
        'el' => '#chart_fs_money', 'type' => 'area',
        'series' => [
            ['name' => 'Billed (USD)',   'data' => $fsMonthly['billed']],
            ['name' => 'Received (USD)', 'data' => $fsMonthly['received']],
        ],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#FFCB01', '#00B4D8'],
        'money' => true, 'height' => 260,
    ],
];
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $lang['left-menu-sidebar-28'] ?> | <?php echo $core->site_name ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <div id="main-wrapper">
        <?php include 'views/inc/preloader.php'; ?>
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="sw-dash-hello">
                    <div>
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-28'] ?></h4>
                        <div class="sw-hello-sub">Financial Sheet Ledger &mdash; <?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('view_financial_overview')) { ?>
                        <a href="financial_overview.php" class="btn btn-sm btn-dark">Financial Overview</a>
                        <?php } ?>
                        <a href="transactions.php" class="btn btn-sm btn-outline-dark">Transactions</a>
                        <a href="accounts_receivable.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['messagesform83'] ?></a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:file-text-linear', 'label' => 'Billed (This Month)', 'value' => cdb_money_format($fs['billed_month']), 'href' => 'financial_sheet.php', 'accent' => '#536dfe']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hand-money-linear', 'label' => 'Received (This Month)', 'value' => cdb_money_format($fs['received_month']), 'href' => 'transactions.php', 'accent' => '#1b8a5a', 'sub' => 'Net Of Refunds']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bill-list-linear', 'label' => 'Outstanding (All-Time)', 'value' => cdb_money_format($fs['outstanding']), 'href' => 'accounts_receivable.php', 'accent' => '#e67e22']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:card-recive-linear', 'label' => 'Payments (This Month)', 'value' => number_format($ct_payments_month), 'href' => 'transactions.php', 'accent' => '#00adf2', 'sub' => 'Recorded Receipts']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_fs_money', 'Billed vs Received', 'Financial Sheet Ledger (USD) — ' . date('Y'), 'col-12 col-lg-8'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashRingsPanel('chart_fs_modes', $modeBd, $charts, ['col' => 'col-12 col-lg-4', 'title' => 'Payment Methods', 'note' => 'Received this year, by mode (USD)']); ?>
                </div>
                <div class="row">
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-5', 'title' => 'Receivables Ageing', 'note' => 'Balance still owed, by age of the bill']); ?>
                        <?php cdp_dashBars($recvRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-7', 'title' => 'Top Outstanding Accounts', 'note' => 'Customers with the largest balance owed']); ?>
                        <?php cdp_dashKv($owingRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['dash-general-30'] ?></h5>
                                <div class="d-flex justify-content-end mb-2"><div class="input-group" style="max-width:170px;"><select onchange="cdp_load(1);" class="form-control custom-select" id="per_page" name="per_page"><option value="25">25 rows</option><option value="50">50 rows</option><option value="100">100 rows</option><option value="all"><?php echo $lang['rows-all'] ?? 'All'; ?></option></select></div></div>
                                <div class="outer_div"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script>window.cdpDashTable = { url: './ajax/dashboard/account_receivable/load_account_receivable_ajax.php', target: '.outer_div' };</script>
    <script src="<?= cdp_asset('dataJs/dashboard_table.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
