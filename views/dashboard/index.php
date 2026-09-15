<?php
// ============================================================================
// Admin Control Panel — the landing dashboard for admin-type roles.
// Layout follows the "Control Panel" screen of the Swift Lane Ops design:
// overview tiles, performance panels, activity by hour, service/weight/payment
// panels, ageing, exceptions + live activity, customers/drivers/offices, the
// consolidations banner and the shipping list.
//
// Data rules (see helpers/dashboard_data.php):
//   - money figures come ONLY from the Financial Sheet ledger, so they tally
//     with Financial Sheet / Transactions / Receivables;
//   - monetary tiles/panels are permission-gated via cdp_canViewMoney
//     ('view_monetary_values' global or 'view_money_dashboard');
//   - counts use the real order semantics: order_incomplete = 1 is a fully
//     registered order; is_pickup = 1 AND order_incomplete = 0 is a pickup
//     request still awaiting acceptance;
//   - every figure on this page is measured from the database. Nothing is
//     estimated or forecast: where the data does not exist (delivery SLA,
//     targets) the panel is simply not shown.
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
    $shipBase  = "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21";
    $shipYear  = "$shipBase AND YEAR(order_date)=YEAR(CURDATE())";
    $shipYearO = "AND o.is_pickup=0 AND o.order_incomplete=1 AND o.status_courier != 21 AND YEAR(o.order_date)=YEAR(CURDATE())";
    $monthStart = date('Y-m-01');

    // ---- Overview counts (one COUNT each) ------------------------------------
    $ct_ship      = cdp_dashCount('cdb_add_order', $shipBase);
    $ct_ship_year = cdp_dashCount('cdb_add_order', $shipYear);
    $ct_ship_month = cdp_dashCount('cdb_add_order', "$shipBase AND order_date >= '$monthStart'");
    $ct_pickreq   = cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND order_incomplete=0 AND status_courier != 21");
    $ct_consol    = cdp_dashCount('cdb_consolidate', "AND status_courier != 21");
    $ct_prealert  = cdp_dashCount('cdb_pre_alert', "AND is_package=0");
    $ct_packages  = cdp_dashCount('cdb_customers_packages', "AND status_courier != 21");
    $ct_warehouse = cdp_dashCount('cdb_add_order', "AND status_courier = 4");
    $ct_cleared   = cdp_dashCount('cdb_add_order', "AND fs_cleared_for_delivery = 1 AND status_courier NOT IN (8,21)");
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

    // ---- Performance: status rings, lanes, monthly volume ------------------
    $breakdown = cdp_dashStatusBreakdown('cdb_add_order', $shipYear, 3);
    $ringLegend = [];
    foreach ($breakdown['labels'] as $i => $lbl) {
        $ringLegend[] = [$lbl, $breakdown['colors'][$i], number_format($breakdown['totals'][$i])];
    }
    if ($breakdown['totals']) {
        $charts[] = [
            'el' => '#chart_status_rings', 'type' => 'rings', 'tone' => 'inverse',
            'series' => $breakdown['totals'], 'labels' => $breakdown['labels'],
            'colors' => $breakdown['colors'], 'height' => 230,
        ];
    }
    $lanes = cdp_dashTopDestinations("$shipYearO AND NULLIF(TRIM(a.recipient_country),'') IS NOT NULL", 5);
    $laneMax = $lanes ? (int) $lanes[0]->t : 0;
    $laneRows = [];
    $laneColors = ['var(--swift-amber)', 'var(--warm-500)', 'var(--blue-600)', 'var(--cyan-500)', 'var(--ink-700)'];
    foreach ($lanes as $i => $r) {
        $laneRows[] = [$r->lbl, number_format($r->t), cdp_dashPct((int) $r->t, $laneMax), $laneColors[$i % 5]];
    }
    $serShip = cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', $shipBase);
    $serPack = cdp_dashMonthlySeries('cdb_customers_packages', 'order_date', 'COUNT(*)', "AND status_courier != 21");
    $charts[] = [
        'el' => '#chart_volume', 'type' => 'bar',
        'series' => [
            ['name' => 'Shipments', 'data' => $serShip],
            ['name' => 'Registered Packages', 'data' => $serPack],
        ],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#FFCB01', '#192A3E'], 'height' => 230,
    ];

    // ---- Registrations by hour (last 90 days) --------------------------------
    $hourMatrix = cdp_dashHourMatrix('cdb_add_order', 'order_datetime', $shipBase, 90);
    $hourTotal  = 0; $peakHour = null; $peakVal = 0; $hourSums = array_fill(0, 24, 0);
    foreach ($hourMatrix as $row) {
        foreach ($row as $h => $v) { $hourTotal += $v; $hourSums[$h] += $v; }
    }
    foreach ($hourSums as $h => $v) { if ($v > $peakVal) { $peakVal = $v; $peakHour = $h; } }
    $hourLabels = [];
    for ($h = 0; $h < 24; $h++) { $hourLabels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT); }
    $charts[] = [
        'el' => '#chart_hours', 'type' => 'heatmap', 'rows' => $hourMatrix, 'xLabels' => $hourLabels,
        'colors' => ['#7C3EE2'], 'height' => 210,
    ];
    $ct_today = cdp_dashCount('cdb_add_order', "$shipBase AND order_date = CURDATE()");
    $ct_week  = cdp_dashCount('cdb_add_order', "$shipBase AND order_date >= CURDATE() - INTERVAL 6 DAY");
    $latest   = cdp_dashLatestOrders("AND o.is_pickup=0 AND o.order_incomplete=1", 7);

    // ---- Service mix + weight (this year) ------------------------------------
    $svc = cdp_dashRows("SELECT order_item_category cat, COUNT(*) t, COALESCE(SUM(total_weight),0) w
                         FROM cdb_add_order WHERE 1=1 $shipYear GROUP BY order_item_category");
    $svcCounts = ['air' => 0, 'sea' => 0, 'other' => 0];
    $svcWeight = ['air' => 0.0, 'sea' => 0.0, 'other' => 0.0];
    foreach ($svc as $r) {
        $k = (int) $r->cat === 26 ? 'air' : ((int) $r->cat === 27 ? 'sea' : 'other');
        $svcCounts[$k] += (int) $r->t;
        $svcWeight[$k] += (float) $r->w;
    }
    $ct_pick_year = cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND status_courier != 21 AND YEAR(order_date)=YEAR(CURDATE())");
    $svcMax = max(1, $svcCounts['air'], $svcCounts['sea'], $svcCounts['other'], $ct_pick_year);
    $serviceRows = [
        ['Air Shipments', number_format($svcCounts['air']), cdp_dashPct($svcCounts['air'], $svcMax), 'var(--swift-amber)'],
        ['Sea Shipments', number_format($svcCounts['sea']), cdp_dashPct($svcCounts['sea'], $svcMax), 'var(--blue-600)'],
        ['Pickup Requests', number_format($ct_pick_year), cdp_dashPct($ct_pick_year, $svcMax), 'var(--cyan-500)'],
    ];
    if ($svcCounts['other'] > 0) {
        $serviceRows[] = ['Uncategorised', number_format($svcCounts['other']), cdp_dashPct($svcCounts['other'], $svcMax), 'var(--slate-400)'];
    }
    $wAll = $svcWeight['air'] + $svcWeight['sea'] + $svcWeight['other'];
    $nAll = $svcCounts['air'] + $svcCounts['sea'] + $svcCounts['other'];
    $fmtKg = function ($kg) { return $kg >= 1000 ? number_format($kg / 1000, 1) . ' t' : number_format($kg, 1) . ' kg'; };
    $weightPanes = [
        'all' => ['total' => $wAll, 'n' => $nAll, 'bars' => [
            ['Air freight', $fmtKg($svcWeight['air']), cdp_dashPct($svcWeight['air'], max(1, $wAll)), 'var(--swift-amber)'],
            ['Sea freight', $fmtKg($svcWeight['sea']), cdp_dashPct($svcWeight['sea'], max(1, $wAll)), 'var(--blue-600)'],
        ]],
        'air' => ['total' => $svcWeight['air'], 'n' => $svcCounts['air'], 'bars' => [
            ['Share of all weight', $fmtKg($svcWeight['air']), cdp_dashPct($svcWeight['air'], max(1, $wAll)), 'var(--swift-amber)'],
        ]],
        'sea' => ['total' => $svcWeight['sea'], 'n' => $svcCounts['sea'], 'bars' => [
            ['Share of all weight', $fmtKg($svcWeight['sea']), cdp_dashPct($svcWeight['sea'], max(1, $wAll)), 'var(--blue-600)'],
        ]],
    ];

    // ---- Payment health (money) ----------------------------------------------
    $payRows = [];
    if ($canMoney) {
        $ini = date('Y-m-01 00:00:00'); $fin = date('Y-m-t 23:59:59');
        $byMode = cdp_dashRows("SELECT LOWER(COALESCE(mode,'')) m, COUNT(*) n, COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) usd
                                FROM cdb_fs_payments WHERE recorded_at BETWEEN '$ini' AND '$fin' AND " . cdp_fsMoneySqlFilter() . " GROUP BY m");
        $cashUsd = 0.0; $cashN = 0; $gwUsd = 0.0; $gwN = 0;
        foreach ($byMode as $r) {
            if ($r->m === 'cash') { $cashUsd += (float) $r->usd; $cashN += (int) $r->n; }
            else { $gwUsd += (float) $r->usd; $gwN += (int) $r->n; }
        }
        $payRows = [
            ['Cash received', $cashN . ' payment' . ($cashN === 1 ? '' : 's') . ' this month', cdb_money_format($cashUsd), null, 'transactions.php'],
            ['Online payments received', $gwN . ' gateway payment' . ($gwN === 1 ? '' : 's') . ' this month', cdb_money_format($gwUsd), null, 'transactions.php'],
            ['Billed this month', 'Financial Sheet', cdb_money_format($fs['billed_month']), null, 'financial_sheet.php'],
            ['Outstanding', 'All-time balance still owed', cdb_money_format($fs['outstanding']), 'var(--red-500)', 'accounts_receivable.php'],
        ];
    }

    // ---- Ageing --------------------------------------------------------------
    $pickAge = cdp_dashAgeBuckets('cdb_add_order', 'order_datetime', "AND is_pickup=1 AND order_incomplete=0 AND status_courier NOT IN (8,12,15,21)", [24, 48]);
    $pickAgeMax = max(1, max($pickAge));
    $pickAgeRows = [
        ['0 - 24 hrs', number_format($pickAge[0]), cdp_dashPct($pickAge[0], $pickAgeMax), 'var(--leaf-500)'],
        ['24 - 48 hrs', number_format($pickAge[1]), cdp_dashPct($pickAge[1], $pickAgeMax), 'var(--amber-500)'],
        ['48+ hrs', number_format($pickAge[2]), cdp_dashPct($pickAge[2], $pickAgeMax), 'var(--red-500)'],
    ];
    $recvRows = [];
    if ($canMoney) {
        $bal = "GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)";
        $age = "DATEDIFF(NOW(), billed_at)";
        $r = cdp_dashRows("SELECT COALESCE(SUM(CASE WHEN $age <= 30 THEN $bal END),0) a0,
                                  COALESCE(SUM(CASE WHEN $age > 30 AND $age <= 60 THEN $bal END),0) a1,
                                  COALESCE(SUM(CASE WHEN $age > 60 THEN $bal END),0) a2
                           FROM cdb_consolidate_customer_billing WHERE $bal > 0");
        $a0 = (float) ($r[0]->a0 ?? 0); $a1 = (float) ($r[0]->a1 ?? 0); $a2 = (float) ($r[0]->a2 ?? 0);
        $aMax = max(1, $a0, $a1, $a2);
        $recvRows = [
            ['0 - 30 days', cdb_money_format($a0), cdp_dashPct($a0, $aMax), 'var(--leaf-500)'],
            ['31 - 60 days', cdb_money_format($a1), cdp_dashPct($a1, $aMax), 'var(--amber-500)'],
            ['61+ days', cdb_money_format($a2), cdp_dashPct($a2, $aMax), 'var(--red-500)'],
        ];
    }
    $whIds = [4, 33, 6, 32, 1, 16, 35];
    $whCounts = cdp_dashStatusCounts('cdb_add_order', $whIds);
    $whMax = max(1, max($whCounts));
    $whRows = [
        ['In Warehouse', number_format($whCounts[4]), cdp_dashPct($whCounts[4], $whMax), 'var(--swift-amber)'],
        ['Sorting At Accra Office', number_format($whCounts[33]), cdp_dashPct($whCounts[33], $whMax), 'var(--warm-500)'],
        ['Ready For Pickup', number_format($whCounts[6] + $whCounts[32]), cdp_dashPct($whCounts[6] + $whCounts[32], $whMax), 'var(--leaf-500)'],
        ['Pending Collection', number_format($whCounts[1]), cdp_dashPct($whCounts[1], $whMax), 'var(--amber-500)'],
        ['Not Picked Up', number_format($whCounts[16]), cdp_dashPct($whCounts[16], $whMax), 'var(--red-500)'],
        ['Auction', number_format($whCounts[35]), cdp_dashPct($whCounts[35], $whMax), 'var(--ink-700)'],
    ];

    // ---- Exceptions + live activity -------------------------------------------
    $ex_hazmat   = cdp_dashCount('cdb_add_order', "AND is_dangerous_good = 1 AND status_courier NOT IN (8,21)");
    $ex_unpriced = cdp_dashCount('cdb_add_order o', "AND o.is_pickup=0 AND o.order_incomplete=1 AND o.status_courier NOT IN (8,21)
                                  AND NOT EXISTS (SELECT 1 FROM cdb_add_order_item i WHERE i.order_id = o.order_id AND (i.custom_price > 0 OR i.order_item_weight > 0))");
    $ex_notpicked = $whCounts[16];
    $ex_held     = cdp_dashCount('cdb_add_order', "AND status_courier IN (28,29)");
    $ex_cancel_m = cdp_dashCount('cdb_add_order', "AND status_courier = 21 AND order_date >= '$monthStart'");
    $ex_awaiting = $ct_pickreq;
    $exceptionRows = [
        ['Unpriced shipments', 'Open, with no weight or custom price on any item', number_format($ex_unpriced), 'var(--amber-500)', 'courier_list.php'],
        ['Pickup requests awaiting acceptance', 'Not yet converted to shipments', number_format($ex_awaiting), 'var(--sky-500)', 'pickup_list.php'],
        ['Dangerous goods in progress', 'Hazmat flag set, not yet delivered', number_format($ex_hazmat), 'var(--red-500)', 'courier_list.php?mode=air'],
        ['Not picked up', 'Collection window passed', number_format($ex_notpicked), 'var(--red-500)', 'pickup_aging.php'],
        ['Held or rejected by airline', 'Needs a decision', number_format($ex_held), 'var(--purple-500)', 'courier_list.php?mode=air'],
        ['Cancelled this month', $monthName . ' ' . date('Y'), number_format($ex_cancel_m), 'var(--slate-400)', 'courier_list.php'],
    ];
    $exceptionOpen = $ex_unpriced + $ex_awaiting + $ex_hazmat + $ex_notpicked + $ex_held;
    $activity = [];
    foreach (cdp_dashActivityFeed(7) as $a) { $activity[] = [$a['time'], $a['text']]; }

    // ---- Customers, drivers, offices -----------------------------------------
    $topSenders = [];
    foreach (cdp_dashTopSenders($shipYearO, 5) as $r) {
        $topSenders[] = [$r->lbl !== '' ? $r->lbl : ('Customer #' . (int) $r->sender_id), '', number_format($r->t) . ' shipments', null, 'customer_view.php?id=' . (int) $r->sender_id];
    }
    $topDrivers = [];
    foreach (cdp_dashTopDrivers("AND o.status_courier != 21 AND YEAR(o.order_date)=YEAR(CURDATE())", 5) as $r) {
        $topDrivers[] = [$r->lbl !== '' ? $r->lbl : ('Driver #' . (int) $r->driver_id), '', number_format($r->t) . ' orders', null, ''];
    }
    $offices = cdp_dashByOffice($shipYearO, 6);
    $offMax = $offices ? (int) $offices[0]->t : 0;
    $officeRows = [];
    $offColors = ['var(--warm-300)', 'var(--warm-500)', 'var(--warm-700)', 'var(--ink-700)', 'var(--blue-600)', 'var(--cyan-500)'];
    foreach ($offices as $i => $r) {
        $officeRows[] = [$r->lbl, number_format($r->t), cdp_dashPct((int) $r->t, $offMax), $offColors[$i % 6]];
    }

    // ---- Consolidations banner -----------------------------------------------
    $consOpenWhere = "AND status_courier NOT IN (8,15,21,27)";
    $cons_open    = cdp_dashCount('cdb_consolidate', $consOpenWhere);
    $cons_transit = cdp_dashCount('cdb_consolidate', "AND status_courier = 3");
    $consDetail = cdp_dashRows("SELECT COUNT(d.detail_id) n, COALESCE(SUM(CAST(NULLIF(TRIM(d.weight),'') AS DECIMAL(12,2))),0) w
                                FROM cdb_consolidate_detail d JOIN cdb_consolidate c ON c.consolidate_id = d.consolidate_id
                                WHERE c.status_courier NOT IN (8,15,21,27)");
    $cons_items  = (int) ($consDetail[0]->n ?? 0);
    $cons_weight = (float) ($consDetail[0]->w ?? 0);
    $cons_month  = cdp_dashCount('cdb_consolidate', "AND status_courier != 21 AND c_date >= '$monthStart'");

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
                        <?php if ($user->cdp_hasPermission('view_financial_overview')) { ?>
                        <a href="financial_overview.php" class="btn btn-sm btn-outline-dark">Financial Overview</a>
                        <?php } ?>
                        <?php if ($user->cdp_hasPermission('add_shipment')) { ?>
                        <a href="courier_add.php" class="btn btn-sm btn-primary"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> Add Air Package</a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($canStats) { ?>

                <!-- Overview -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:widget-4-linear', 'Overview', 'Live Counts'); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-minimalistic-linear', 'label' => 'Shipments', 'value' => number_format($ct_ship), 'href' => 'courier_list.php', 'sub' => number_format($ct_ship_month) . ' registered in ' . $monthName, 'col' => 'col-6 col-md-4 col-xl-3']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:clock-circle-linear', 'label' => 'Pickup Requests', 'value' => number_format($ct_pickreq), 'href' => 'pickup_list.php', 'sub' => 'Awaiting Acceptance']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:layers-minimalistic-linear', 'label' => 'Consolidations', 'value' => number_format($ct_consol), 'href' => 'consolidate_list.php', 'sub' => number_format($cons_open) . ' open']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bell-linear', 'label' => 'Pre-Alerts', 'value' => number_format($ct_prealert), 'href' => 'prealert_list.php']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:cart-large-2-linear', 'label' => 'Registered Packages', 'value' => number_format($ct_packages), 'href' => 'customer_packages_list.php']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:home-2-linear', 'label' => 'In Warehouse', 'value' => number_format($ct_warehouse), 'href' => 'warehouse.php']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-linear', 'label' => 'Cleared for Delivery', 'value' => number_format($ct_cleared), 'href' => 'warehouse_delivery.php', 'sub' => 'Packages']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:user-plus-linear', 'label' => 'Customers', 'value' => number_format($ct_customers), 'href' => 'customers_list.php', 'tone' => 'inverse']); ?>
                </div>

                <?php if ($canMoney) { ?>
                <!-- Financial Sheet headline (money-gated) -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:file-text-linear', 'Financial Sheet', $monthName . ' ' . date('Y')); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:file-text-linear', 'label' => 'Billed This Month', 'value' => cdb_money_format($fs['billed_month']), 'href' => 'financial_sheet.php', 'col' => 'col-12 col-md-4']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hand-money-linear', 'label' => 'Received This Month', 'value' => cdb_money_format($fs['received_month']), 'href' => 'transactions.php', 'sub' => 'Net Of Refunds', 'col' => 'col-12 col-md-4']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bill-list-linear', 'label' => 'Outstanding', 'value' => cdb_money_format($fs['outstanding']), 'href' => 'accounts_receivable.php', 'sub' => 'All-Time Balance Owed', 'tone' => 'inverse', 'col' => 'col-12 col-md-4']); ?>
                </div>
                <?php } ?>

                <!-- Performance -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:chart-2-linear', 'Performance', date('Y')); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4 col-xl-3', 'tone' => 'inverse', 'title' => 'Shipments By Status', 'value' => number_format($ct_ship_year), 'note' => 'Registered this year']); ?>
                        <?php if ($breakdown['totals']) { ?>
                        <div id="chart_status_rings" class="sw-chart"></div>
                        <?php cdp_dashLegend($ringLegend); ?>
                        <?php } else { ?>
                        <div class="swl-empty">No shipments registered this year</div>
                        <?php } ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Lane Volume', 'value' => number_format(count($laneRows)) . ' lanes', 'note' => 'Top destinations on shipment addresses this year']); ?>
                        <?php cdp_dashBars($laneRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4 col-xl-5', 'title' => 'Monthly Volume', 'note' => 'Shipments & Registered Packages — ' . date('Y')]); ?>
                        <div id="chart_volume" class="sw-chart"></div>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <!-- Activity by hour + latest entries -->
                <div class="row">
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-7', 'title' => 'Registrations By Hour', 'note' => 'Shipments registered per weekday and hour, last 90 days',
                        'aside' => '<a href="courier_list.php" class="btn btn-sm btn-outline-dark">All Shipments</a>']); ?>
                        <div id="chart_hours" class="sw-chart"></div>
                        <?php cdp_dashStats([
                            [number_format($ct_today), 'Registered today'],
                            [number_format($ct_week), 'Last 7 days'],
                            [$peakHour === null ? '—' : str_pad((string) $peakHour, 2, '0', STR_PAD_LEFT) . ':00', 'Busiest hour (90 days)'],
                        ], 3); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-5', 'title' => 'Latest Shipment Entries', 'note' => 'Most recently registered']); ?>
                        <?php
                        $latestRows = [];
                        foreach ($latest as $r) {
                            $ts = strtotime((string) ($r->order_datetime ?: $r->order_date));
                            $when = $ts ? (date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('M j', $ts)) : '';
                            $detail = trim(($r->sender !== '' ? $r->sender . ' · ' : '') . number_format((float) $r->total_weight, 1) . ' kg · ' . cdp_dashModeLabel($r->order_item_category));
                            $latestRows[] = [$r->order_prefix . $r->order_no, $when . ($detail !== '' ? ' · ' . $detail : ''), cdp_dashPill($r->mod_style ?: 'Registered', $r->color), null, 'courier_view.php?id=' . (int) $r->order_id];
                        }
                        cdp_dashKv($latestRows);
                        ?>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <!-- Services, weight, payments -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:scale-linear', 'Shipments, Weight' . ($canMoney ? ' & Payments' : ' & Collection'), date('Y')); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Shipments By Service', 'value' => number_format($ct_ship_year), 'note' => 'Registered this year']); ?>
                        <?php cdp_dashBars($serviceRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php
                    ob_start(); cdp_dashToggle('weight', ['all' => 'All', 'air' => 'Air', 'sea' => 'Sea'], 'all'); $toggle = ob_get_clean();
                    cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Weight Handled', 'value' => $fmtKg($wAll), 'note' => 'Gross weight on shipments registered this year', 'aside' => $toggle]);
                    foreach ($weightPanes as $key => $pane) {
                        echo '<div data-swl-pane="weight" data-swl-key="' . $key . '"' . ($key === 'all' ? '' : ' hidden') . '>';
                        cdp_dashStats([
                            [$fmtKg($pane['total']), 'Gross weight'],
                            [number_format($pane['n']), 'Shipments'],
                            [$pane['n'] > 0 ? number_format($pane['total'] / $pane['n'], 2) . ' kg' : '—', 'Average per shipment'],
                            [($wAll > 0 ? cdp_dashPct($pane['total'], $wAll) : 0) . '%', 'Share of all weight'],
                        ], 2);
                        echo '<div class="mt-3">'; cdp_dashBars($pane['bars']); echo '</div>';
                        echo '</div>';
                    }
                    cdp_dashPanel('close');
                    ?>
                    <?php if ($canMoney) { ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Payment Health', 'note' => $monthName . ' ' . date('Y') . ' — Financial Sheet']); ?>
                        <?php cdp_dashKv($payRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php } else { ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Pending Pickup Requests', 'note' => 'Time since the request was made']); ?>
                        <?php cdp_dashBars($pickAgeRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php } ?>
                </div>

                <!-- Ageing -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:hourglass-line-linear', 'Ageing'); ?>
                    <?php if ($canMoney) { ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Pending Pickup Requests', 'note' => 'Time since the request was made']); ?>
                        <?php cdp_dashBars($pickAgeRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Receivables', 'note' => 'Balance still owed, by age of the bill']); ?>
                        <?php cdp_dashBars($recvRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php } ?>
                    <?php cdp_dashPanel('open', ['col' => $canMoney ? 'col-12 col-lg-4' : 'col-12 col-lg-8', 'title' => 'Warehouse & Collection', 'note' => 'Shipments by warehouse stage, all time']); ?>
                        <?php cdp_dashBars($whRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <!-- Exceptions + activity -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:danger-triangle-linear', 'Exceptions & Activity'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-6', 'title' => 'Exception Queue', 'aside' => '<span class="swl-panel__note" style="color:var(--red-500);font-weight:700">' . number_format($exceptionOpen) . ' open</span>']); ?>
                        <?php cdp_dashKv($exceptionRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-6', 'title' => 'Live Activity', 'note' => 'Latest actions from the activity log',
                        'aside' => ($user->cdp_hasPermission('view_activity_logs') ? '<a href="activity_logs.php" class="btn btn-sm btn-outline-dark">Activity Logs</a>' : '')]); ?>
                        <?php cdp_dashFeed($activity); ?>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <!-- Customers, drivers, offices -->
                <div class="row">
                    <?php cdp_dashSectionTitle('solar:users-group-rounded-linear', 'Customers, Drivers & Offices', date('Y')); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Top Customers', 'note' => 'By shipments registered this year']); ?>
                        <?php cdp_dashKv($topSenders); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Top Drivers', 'note' => 'By orders assigned this year']); ?>
                        <?php cdp_dashKv($topDrivers); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Per-Office Comparison', 'note' => 'Shipments by origin office this year']); ?>
                        <?php cdp_dashBars($officeRows); ?>
                        <?php
                        $teamRows = [
                            ['Super Admins', '', number_format($uc['super']), null, 'users_list.php'],
                            ['Staff', '', number_format($uc['staff']), null, 'users_list.php'],
                            ['Drivers', '', number_format($uc['driver']), null, 'drivers_list.php'],
                        ];
                        foreach ($dash_depts as $d) { $teamRows[] = [(string) $d->name, 'Department', number_format((int) $d->total), null, 'departments.php']; }
                        ?>
                        <div>
                            <span class="swl-panel__title d-block mb-2"><?php echo $lang['messagesform97'] ?></span>
                            <?php cdp_dashKv($teamRows); ?>
                        </div>
                    <?php cdp_dashPanel('close'); ?>
                </div>

                <!-- Consolidations banner -->
                <div class="row">
                    <?php cdp_dashBanner([
                        'tag'   => 'Consolidations',
                        'title' => number_format($cons_open) . ' Open Consolidation' . ($cons_open === 1 ? '' : 's') . ' · ' . number_format($cons_transit) . ' In Transit',
                        'note'  => number_format($cons_month) . ' created in ' . $monthName . '. Shipments grouped for onward carriage.',
                        'stats' => [
                            ['solar:box-minimalistic-linear', number_format($cons_items), 'Shipments in open consolidations'],
                            ['solar:scale-linear', $fmtKg($cons_weight), 'Consolidated weight'],
                        ],
                        'btn'   => ['dashboard_admin_consolidated.php', 'Open Consolidation Panel'],
                    ]); ?>
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

                <!-- Shipping list (AJAX list, permission-scoped server-side) -->
                <div class="row">
                    <div class="col-12">
                        <?php cdp_dashSectionTitle('solar:list-linear', 'Shipping List'); ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:12px">
                                    <ul class="nav nav-pills custom-pills" id="pills-tab2" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="pill" href="#pills-shipment" role="tab" aria-selected="true"><?php echo $lang['dash-general-19'] ?></a>
                                        </li>
                                        <li class="nav-item"><a class="nav-link" href="pickup_list.php" role="tab"><?php echo $lang['dash-general-20'] ?></a></li>
                                        <li class="nav-item"><a class="nav-link" href="consolidate_list.php" role="tab"><?php echo $lang['dash-general-21'] ?></a></li>
                                        <li class="nav-item"><a class="nav-link" href="prealert_list.php"><?php echo $lang['dash-general-22'] ?></a></li>
                                        <li class="nav-item"><a class="nav-link" href="courier_list.php"><?php echo $lang['dash-general-23'] ?></a></li>
                                    </ul>
                                    <div style="flex:0 1 380px;min-width:220px">
                                        <input type="text" name="search_shipment" id="search_shipment" class="form-control input-sm" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                    </div>
                                </div>
                                <div class="tab-content" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-shipment" role="tabpanel">
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
</body>

</html>
