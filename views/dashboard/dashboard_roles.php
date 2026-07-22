<?php
// ============================================================================
// Roles Dashboard — generic permission-aware control panel for department
// roles and agencies (any role whose dashboard_type is 'roles').
//
// Every block is gated by the SAME permission that guards its list page, and
// counts are agency-scoped through cdp_getAgencyContext(). Monetary figures:
//   - come from the Financial Sheet ledger (never legacy total_order sums);
//   - require cdp_canViewMoney (this page previously leaked money to any
//     role with main_dashboard_index — fixed);
//   - are hidden entirely for agency-restricted users, because the FS ledger
//     is company-wide and cannot be scoped to an agency.
// ============================================================================

require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/dashboard_data.php');
require_once(__DIR__ . '/../../helpers/rbac.php');
$db = new Conexion;

$userData = $user->cdp_getUserData();
$ctx = cdp_getAgencyContext();

$agency_where = '';
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $agency_where = ' AND agency = ' . (int)$ctx['agency_id'];
} elseif ($ctx['is_restricted']) {
    $agency_where = ' AND 1=0';
}

$monthName = obtenerNombreMes((int) date('n'));
$canStats = $user->cdp_hasPermission('main_dashboard_index');
$canMoney = $canStats && !$ctx['is_restricted'] && cdp_canViewMoney($user, 'dashboard');

$charts = [];
$fs = null;

if ($canMoney) {
    $fs = cdp_dashFsTotals();
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

// Per-permission operational counts (accurate order semantics + agency scope).
$tiles = [];
if ($user->cdp_hasPermission('view_dashboard_ship')) {
    $tiles[] = ['solar:box-minimalistic-linear', 'Shipments',
        cdp_dashCount('cdb_add_order', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21" . $agency_where),
        'courier_list.php', '#f2b21b', 'Non-Cancelled'];
}
if ($user->cdp_hasPermission('view_dashboard_pick')) {
    $tiles[] = ['solar:clock-circle-linear', 'Pickup Requests',
        cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND status_courier != 21" . $agency_where),
        'pickup_list.php', '#2962ff', ''];
}
if ($user->cdp_hasPermission('view_consolidate_list')) {
    $tiles[] = ['solar:layers-minimalistic-linear', 'Consolidations',
        cdp_dashCount('cdb_consolidate', "AND status_courier != 21" . $agency_where),
        'consolidate_list.php', '#ef2628', ''];
}
if ($user->cdp_hasPermission('prealert_list')) {
    $tiles[] = ['solar:bell-linear', 'Pre-Alerts',
        cdp_dashCount('cdb_pre_alert', "AND is_package=0"),
        'prealert_list.php', '#7460ee', ''];
}
if ($user->cdp_hasPermission('view_package_list')) {
    $tiles[] = ['solar:cart-large-2-linear', 'Registered Packages',
        cdp_dashCount('cdb_customers_packages', "AND status_courier != 21" . $agency_where),
        'customer_packages_list.php', '#36bea6', ''];
}
if ($user->cdp_hasPermission('view_receivable_accounts')) {
    $tiles[] = ['solar:wallet-money-linear', 'Credit Orders',
        cdp_dashCount('cdb_add_order', "AND order_payment_method > 1 AND status_courier != 21" . $agency_where),
        'accounts_receivable.php', '#e67e22', 'Billed On Account'];
}
if ($user->cdp_hasPermission('warehouse_view')) {
    $tiles[] = ['solar:home-2-linear', 'In Warehouse',
        cdp_dashCount('cdb_add_order', "AND status_courier = 4" . $agency_where),
        'warehouse.php', '#e0ce07', ''];
}

// Volume + status charts follow the shipments permission.
if ($user->cdp_hasPermission('view_dashboard_ship')) {
    $charts[] = [
        'el' => '#chart_volume', 'type' => 'bar',
        'series' => [['name' => 'Shipments', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21" . $agency_where)]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b'], 'height' => 300,
    ];
    $bd = cdp_dashStatusBreakdown('cdb_add_order', "AND is_pickup=0 AND order_incomplete=1 AND YEAR(order_date)=YEAR(CURDATE())" . $agency_where);
    if ($bd['totals']) {
        $charts[] = [
            'el' => '#chart_status', 'type' => 'donut',
            'series' => $bd['totals'], 'labels' => $bd['labels'], 'colors' => $bd['colors'], 'height' => 300,
        ];
    }
}

// User registrations (flag-based so every active role is counted).
$uc = null;
if ($canStats) {
    $uc = ['super' => 0, 'staff' => 0, 'driver' => 0, 'client' => 0];
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
    } catch (Throwable $e) { $uc = null; }
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
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($canMoney) { ?>
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:file-text-linear', 'Financial Sheet', $monthName . ' ' . date('Y')); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:file-text-linear', 'label' => 'Billed (This Month)', 'value' => cdb_money_format($fs['billed_month']), 'href' => 'financial_sheet.php', 'accent' => '#536dfe', 'col' => 'col-6 col-md-4']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hand-money-linear', 'label' => 'Received (This Month)', 'value' => cdb_money_format($fs['received_month']), 'accent' => '#1b8a5a', 'sub' => 'Net Of Refunds', 'col' => 'col-6 col-md-4']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bill-list-linear', 'label' => 'Outstanding (All-Time)', 'value' => cdb_money_format($fs['outstanding']), 'accent' => '#e67e22', 'col' => 'col-6 col-md-4']); ?>
                </div>
                <?php } ?>

                <?php if ($tiles) { ?>
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:box-minimalistic-linear', 'Operations', 'Live Counts'); ?>
                    <?php foreach ($tiles as $t) {
                        cdp_dashKpi(['icon' => $t[0], 'label' => $t[1], 'value' => number_format($t[2]), 'href' => $t[3], 'accent' => $t[4], 'sub' => $t[5]]);
                    } ?>
                </div>
                <?php } ?>

                <div class="row">
                    <?php if ($user->cdp_hasPermission('view_dashboard_ship')) { ?>
                    <?php cdp_dashChartCard('open', 'chart_volume', 'Monthly Shipments', date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php if (!empty($bd['totals'])) { cdp_dashChartCard('open', 'chart_status', 'Status Breakdown', date('Y') . ' Shipments By Current Status', 'col-12 col-lg-5'); cdp_dashChartCard('close'); } ?>
                    <?php } ?>
                </div>

                <div class="row">
                    <?php if ($canMoney) { cdp_dashChartCard('open', 'chart_money', 'Billed vs Received', 'Financial Sheet Ledger (USD) — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); } ?>

                    <?php if ($uc !== null) { ?>
                    <div class="col-12 col-lg-5 mb-4">
                        <div class="card h-100 mb-0">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['messagesform97'] ?></h5>
                                <small class="text-muted"><?php echo $lang['messagesform98'] ?></small>
                                <ul class="p-0 m-0 mt-3" style="list-style:none;">
                                    <?php
                                    $ucRows = [
                                        ['solar:shield-check-linear',            'Super Admins', $uc['super']],
                                        ['solar:users-group-two-rounded-linear', 'Staff',        $uc['staff']],
                                        ['solar:user-id-linear',                 'Drivers',      $uc['driver']],
                                        ['solar:user-plus-linear',               'Customers',    $uc['client']],
                                    ];
                                    foreach ($ucRows as $r): ?>
                                    <li class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="d-flex align-items-center">
                                            <span class="avatar-initial rounded bg-label-warning d-inline-flex align-items-center justify-content-center me-2" style="width:1.7rem;height:1.7rem;"><iconify-icon icon="<?php echo $r[0]; ?>"></iconify-icon></span>
                                            <small><?php echo htmlspecialchars((string) $r[1]); ?></small>
                                        </span>
                                        <b><?php echo number_format((int) $r[2]); ?></b>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>

                <!-- Recent shipments (AJAX list, permission/agency-scoped server-side) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-pills custom-pills" id="pills-tab2" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="pill" href="#pills-shipment" role="tab" aria-selected="true"><h5 class="card-title mb-0"><?php echo $lang['dash-general-19'] ?></h5></a>
                                    </li>
                                    <?php if ($user->cdp_hasPermission('view_pickup_list')) { ?>
                                    <li class="nav-item"><a class="nav-link" href="pickup_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-20'] ?></h5></a></li>
                                    <?php } ?>
                                    <?php if ($user->cdp_hasPermission('view_consolidate_list')) { ?>
                                    <li class="nav-item"><a class="nav-link" href="consolidate_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-21'] ?></h5></a></li>
                                    <?php } ?>
                                    <?php if ($user->cdp_hasPermission('prealert_list')) { ?>
                                    <li class="nav-item"><a class="nav-link" href="prealert_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-22'] ?></h5></a></li>
                                    <?php } ?>
                                    <?php if ($user->cdp_hasPermission('view_package_list')) { ?>
                                    <li class="nav-item"><a class="nav-link" href="customer_packages_list.php"><h5 class="card-title mb-0"><?php echo $lang['dash-general-23'] ?></h5></a></li>
                                    <?php } ?>
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
</body>

</html>
