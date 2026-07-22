<?php
// ============================================================================
// Admin Control Panel — the landing dashboard for admin-type roles.
//
// Data rules (see helpers/dashboard_data.php):
//   - money figures come ONLY from the Financial Sheet ledger, so they tally
//     with Financial Sheet / Transactions / Receivables;
//   - monetary tiles/charts are permission-gated via cdp_canViewMoney
//     ('view_monetary_values' global or 'view_money_dashboard');
//   - counts use the real order semantics: order_incomplete = 1 is a fully
//     registered order; is_pickup = 1 AND order_incomplete = 0 is a pickup
//     request still awaiting acceptance.
// ============================================================================

require_once(dirname(__DIR__, 2) . '/helpers/fs_status.php');
require_once(dirname(__DIR__, 2) . '/helpers/dashboard_data.php');
require_once('helpers/rbac.php');

$db = new Conexion;
$userData = $user->cdp_getUserData();

// Agencies see the roles dashboard, never this panel.
if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    $base = (string) (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '');
    $base = ($base === '' || $base === '.') ? '' : rtrim($base, '/');
    header('Location: ' . $base . '/index.php');
    exit;
}

$canMoney  = cdp_canViewMoney($user, 'dashboard');
$canStats  = $user->cdp_hasPermission('main_dashboard_index');
$monthName = obtenerNombreMes((int) date('n'));
$charts    = [];

if ($canStats) {
    // ---- Operational counts (accurate WHERE clauses, one COUNT each) -------
    $ct_ship     = cdp_dashCount('cdb_add_order', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21");
    $ct_pickreq  = cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND order_incomplete=0 AND status_courier != 21");
    $ct_consol   = cdp_dashCount('cdb_consolidate', "AND status_courier != 21");
    $ct_prealert = cdp_dashCount('cdb_pre_alert', "AND is_package=0");
    $ct_packages = cdp_dashCount('cdb_customers_packages', "AND status_courier != 21");
    $ct_warehouse = cdp_dashCount('cdb_add_order', "AND status_courier = 4");
    $ct_cleared  = cdp_dashCount('cdb_add_order', "AND fs_cleared_for_delivery = 1 AND status_courier NOT IN (8,21)");
    try {
        $db->cdp_query("SELECT COUNT(u.id) t FROM cdb_users u
                        JOIN cdb_user_roles r ON r.role_id = u.userlevel
                        WHERE r.is_client = 1");
        $db->cdp_execute();
        $ct_customers = (int) ($db->cdp_registro()->t ?? 0);
    } catch (Throwable $e) {
        $ct_customers = cdp_dashCount('cdb_users', "AND userlevel = 1");
    }

    // ---- Financial Sheet headline money (single source of truth) -----------
    $fs = $canMoney ? cdp_dashFsTotals() : null;

    // ---- Chart data ---------------------------------------------------------
    $serShip = cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21");
    $serPack = cdp_dashMonthlySeries('cdb_customers_packages', 'order_date', 'COUNT(*)', "AND status_courier != 21");
    $charts[] = [
        'el' => '#chart_volume', 'type' => 'bar',
        'series' => [
            ['name' => 'Shipments', 'data' => $serShip],
            ['name' => 'Registered Packages', 'data' => $serPack],
        ],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b', '#111111'], 'height' => 300,
    ];

    $breakdown = cdp_dashStatusBreakdown('cdb_add_order', "AND is_pickup=0 AND order_incomplete=1 AND YEAR(order_date)=YEAR(CURDATE())");
    $charts[] = [
        'el' => '#chart_status', 'type' => 'donut',
        'series' => $breakdown['totals'], 'labels' => $breakdown['labels'],
        'colors' => $breakdown['colors'], 'height' => 300,
    ];

    if ($canMoney) {
        $fsMonthly = cdp_dashFsMonthly();
        $charts[] = [
            'el' => '#chart_money', 'type' => 'area',
            'series' => [
                ['name' => 'Billed (USD)',   'data' => $fsMonthly['billed']],
                ['name' => 'Received (USD)', 'data' => $fsMonthly['received']],
            ],
            'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b', '#36bea6'],
            'money' => true, 'height' => 300,
        ];
    }

    // ---- Users by role CATEGORY (flag-based, counts every active role) -----
    $uc = ['super' => 0, 'staff' => 0, 'driver' => 0, 'client' => 0];
    $dash_depts = array();
    try {
        $db->cdp_query("SELECT r.is_superadmin s, r.is_driver d, r.is_client c, COUNT(u.id) n
                        FROM cdb_user_roles r LEFT JOIN cdb_users u ON u.userlevel = r.role_id
                        WHERE r.rol_active = 1 GROUP BY r.role_id");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $rr) {
            $n = (int) $rr->n;
            if ((int) $rr->s)      { $uc['super']  += $n; }
            elseif ((int) $rr->d)  { $uc['driver'] += $n; }
            elseif ((int) $rr->c)  { $uc['client'] += $n; }
            else                   { $uc['staff']  += $n; }
        }
        $db->cdp_query("SELECT d.name, COUNT(m.user_id) total FROM cdb_departments d
                        LEFT JOIN cdb_department_members m ON m.department_id = d.id
                        GROUP BY d.id, d.name ORDER BY d.name");
        $db->cdp_execute();
        $dash_depts = $db->cdp_registros() ?: array();
    } catch (Throwable $e) { /* RBAC tables absent */ }
}
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
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $lang['left-menu-sidebar-2'] ?> | <?php echo $core->site_name ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>

    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="sw-dash-hello">
                    <div>
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-2'] ?></h4>
                        <div class="sw-hello-sub">Welcome back, <?php echo htmlspecialchars($userData->fname ?? '', ENT_QUOTES, 'UTF-8'); ?> &mdash; <?php echo $monthName . ' ' . date('j, Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('add_shipment')) { ?>
                        <a href="courier_add.php" class="btn btn-sm btn-dark"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New Shipment</a>
                        <?php } ?>
                        <?php if ($user->cdp_hasPermission('view_financial_overview')) { ?>
                        <a href="financial_overview.php" class="btn btn-sm btn-outline-dark">Financial Overview</a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($canStats) { ?>

                <?php if ($canMoney) { ?>
                <!-- Financial Sheet headline (money-gated) -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:file-text-linear', 'Financial Sheet', $monthName . ' ' . date('Y')); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:file-text-linear', 'label' => 'Billed (This Month)', 'value' => cdb_money_format($fs['billed_month']), 'href' => 'financial_sheet.php', 'accent' => '#536dfe']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hand-money-linear', 'label' => 'Received (This Month)', 'value' => cdb_money_format($fs['received_month']), 'href' => 'transactions.php', 'accent' => '#1b8a5a', 'sub' => 'Net Of Refunds']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bill-list-linear', 'label' => 'Outstanding (All-Time)', 'value' => cdb_money_format($fs['outstanding']), 'href' => 'accounts_receivable.php', 'accent' => '#e67e22']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-linear', 'label' => 'Cleared for Delivery', 'value' => (int) $ct_cleared, 'href' => 'warehouse_delivery.php', 'accent' => '#6c757d', 'sub' => 'Packages']); ?>
                </div>
                <?php } ?>

                <!-- Operations at a glance -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:box-minimalistic-linear', 'Operations', 'Live Counts'); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-minimalistic-linear', 'label' => 'Shipments', 'value' => number_format($ct_ship), 'href' => 'courier_list.php', 'accent' => '#f2b21b', 'sub' => 'Non-Cancelled', 'col' => 'col-6 col-md-4 col-xl-3']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:clock-circle-linear', 'label' => 'Pickup Requests', 'value' => number_format($ct_pickreq), 'href' => 'pickup_list.php', 'accent' => '#2962ff', 'sub' => 'Awaiting Acceptance']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:layers-minimalistic-linear', 'label' => 'Consolidations', 'value' => number_format($ct_consol), 'href' => 'consolidate_list.php', 'accent' => '#ef2628']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bell-linear', 'label' => 'Pre-Alerts', 'value' => number_format($ct_prealert), 'href' => 'prealert_list.php', 'accent' => '#7460ee']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:cart-large-2-linear', 'label' => 'Registered Packages', 'value' => number_format($ct_packages), 'href' => 'customer_packages_list.php', 'accent' => '#36bea6']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:home-2-linear', 'label' => 'In Warehouse', 'value' => number_format($ct_warehouse), 'href' => 'warehouse.php', 'accent' => '#e0ce07']); ?>
                    <?php if (!$canMoney) { cdp_dashKpi(['icon' => 'solar:box-linear', 'label' => 'Cleared for Delivery', 'value' => (int) $ct_cleared, 'href' => 'warehouse_delivery.php', 'accent' => '#6c757d', 'sub' => 'Packages']); } ?>
                    <?php cdp_dashKpi(['icon' => 'solar:user-plus-linear', 'label' => 'Customers', 'value' => number_format($ct_customers), 'href' => 'customers_list.php', 'accent' => '#17a1e6']); ?>
                    <?php if ($canMoney) { cdp_dashKpi(['icon' => 'solar:wallet-money-linear', 'label' => 'Financial Sheet', 'value' => 'Open', 'href' => 'financial_sheet.php', 'accent' => '#111111', 'sub' => 'Billing & Payments']); } ?>
                </div>

                <!-- Charts -->
                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_volume', 'Monthly Volume', 'Shipments & Registered Packages — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashChartCard('open', 'chart_status', 'Shipment Status', date('Y') . ' Shipments By Current Status', 'col-12 col-lg-5'); cdp_dashChartCard('close'); ?>
                </div>

                <div class="row">
                    <?php if ($canMoney) { ?>
                    <?php cdp_dashChartCard('open', 'chart_money', 'Billed vs Received', 'Financial Sheet Ledger (USD) — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php } ?>

                    <!-- Team & customers -->
                    <div class="col-12 <?php echo $canMoney ? 'col-lg-5' : ''; ?> mb-4">
                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['messagesform97'] ?></h5>
                                <small class="text-muted"><?php echo $lang['messagesform98'] ?></small>
                                <?php
                                $ucRows = [
                                    ['solar:shield-check-linear',            'Super Admins', $uc['super'],  'users_list.php'],
                                    ['solar:users-group-two-rounded-linear', 'Staff',        $uc['staff'],  'users_list.php'],
                                    ['solar:user-id-linear',                 'Drivers',      $uc['driver'], 'drivers_list.php'],
                                    ['solar:user-plus-linear',               'Customers',    $uc['client'], 'customers_list.php'],
                                ];
                                ?>
                                <ul class="p-0 m-0 mt-3" style="list-style:none;">
                                    <?php foreach ($ucRows as $r): ?>
                                    <li class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="d-flex align-items-center">
                                            <span class="avatar-initial rounded bg-label-warning d-inline-flex align-items-center justify-content-center me-2" style="width:1.7rem;height:1.7rem;"><iconify-icon icon="<?php echo $r[0]; ?>"></iconify-icon></span>
                                            <a href="<?php echo $r[3]; ?>" class="text-dark"><small><?php echo htmlspecialchars((string) $r[1]); ?></small></a>
                                        </span>
                                        <b><?php echo number_format((int) $r[2]); ?></b>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <hr class="my-2">
                                <h6 class="text-muted mb-1" style="font-size:.78rem;">Departments</h6>
                                <ul class="p-0 m-0" style="list-style:none;">
                                    <?php foreach ($dash_depts as $d): ?>
                                    <li class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="d-flex align-items-center">
                                            <iconify-icon icon="solar:buildings-2-linear" class="text-muted me-2"></iconify-icon>
                                            <small><?php echo htmlspecialchars((string) $d->name); ?></small>
                                        </span>
                                        <span class="badge bg-label-success"><?php echo (int) $d->total; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php if (!$dash_depts): ?><li class="text-muted small">None yet.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php } else { ?>
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card mb-0"><div class="card-body d-flex align-items-center">
                            <iconify-icon icon="solar:lock-keyhole-linear" class="text-muted me-2" style="font-size:1.6rem;"></iconify-icon>
                            <span class="text-muted">Dashboard statistics are hidden for your role. The lists below show what you have access to.</span>
                        </div></div>
                    </div>
                </div>
                <?php } ?>

                <!-- Recent shipments (AJAX list, permission-scoped server-side) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-pills custom-pills" id="pills-tab2" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="pill" href="#pills-shipment" role="tab" aria-selected="true"><h5 class="card-title mb-0"><?php echo $lang['dash-general-19'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="pickup_list.php" role="tab"><h5 class="card-title mb-0"><?php echo $lang['dash-general-20'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="consolidate_list.php" role="tab"><h5 class="card-title mb-0"><?php echo $lang['dash-general-21'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="prealert_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-22'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="courier_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-23'] ?></h5></a>
                                    </li>
                                </ul>

                                <div class="tab-content m-t-30" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-shipment" role="tabpanel">
                                        <div class="col-md-12 mt-12 mb-12">
                                            <div class="input-group">
                                                <input type="text" name="search_shipment" id="search_shipment" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                                <div class="input-group-append input-sm">
                                                    <button type="button" class="btn btn-info" onclick="cdp_load(1);"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="results_shipments"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script src="<?= cdp_asset('dataJs/dashboard_index.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
    <!-- Pickup-aging: prompt admins to notify senders of packages uncollected for 2 weeks. -->
    <script>window.cdpPaDashboard = true;</script>
    <script src="<?= cdp_asset('dataJs/pickup_aging.js') ?>"></script>
