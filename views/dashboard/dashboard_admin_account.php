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
$charts = [
    [
        'el' => '#chart_fs_money', 'type' => 'area',
        'series' => [
            ['name' => 'Billed (USD)',   'data' => $fsMonthly['billed']],
            ['name' => 'Received (USD)', 'data' => $fsMonthly['received']],
        ],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b', '#36bea6'],
        'money' => true, 'height' => 330,
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
                    <?php cdp_dashChartCard('open', 'chart_fs_money', 'Billed vs Received', 'Financial Sheet Ledger (USD) — ' . date('Y'), 'col-12'); cdp_dashChartCard('close'); ?>
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
