<?php
// ============================================================================
// Driver Dashboard — the driver's own workload only (driver_id = session).
// Tiles map to the driver's actual working statuses: To Collect (14),
// On Route (7), Delivered (8) — no monetary values are shown to drivers.
// ============================================================================

require_once(__DIR__ . '/../../helpers/dashboard_data.php');

$db = new Conexion;
$userData = $user->cdp_getUserData();
$uid = (int) $_SESSION['userid'];
$own = " AND driver_id = $uid";

$monthName = obtenerNombreMes((int) date('n'));

$ct_ship      = cdp_dashCount('cdb_add_order', "AND is_pickup=0 AND status_courier != 21 $own");
$ct_pickups   = cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND status_courier != 21 $own");
$ct_collect   = cdp_dashCount('cdb_add_order', "AND status_courier = 14 $own");
$ct_onroute   = cdp_dashCount('cdb_add_order', "AND status_courier = 7 $own");
$ct_delivered = cdp_dashCount('cdb_add_order', "AND status_courier = 8 $own");
$ct_consol    = cdp_dashCount('cdb_consolidate', "AND status_courier != 21 $own");

$charts = [
    [
        'el' => '#chart_drv_volume', 'type' => 'bar',
        'series' => [['name' => 'Assigned Orders', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "AND status_courier != 21 $own")]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b'], 'height' => 280,
    ],
];
$bd = cdp_dashStatusBreakdown('cdb_add_order', $own);
if ($bd['totals']) {
    $charts[] = [
        'el' => '#chart_drv_status', 'type' => 'donut',
        'series' => $bd['totals'], 'labels' => $bd['labels'], 'colors' => $bd['colors'], 'height' => 280,
    ];
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
    <div id="main-wrapper">
        <?php include 'views/inc/preloader.php'; ?>
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
                        <a href="pickup_list.php" class="btn btn-sm btn-dark"><?php echo $lang['dash-general-20'] ?></a>
                        <a href="courier_list.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['dash-general-19'] ?></a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:box-minimalistic-linear', 'label' => 'Assigned Shipments', 'value' => number_format($ct_ship), 'href' => 'courier_list.php', 'accent' => '#f2b21b']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:clock-circle-linear', 'label' => 'Assigned Pickups', 'value' => number_format($ct_pickups), 'href' => 'pickup_list.php', 'accent' => '#2962ff']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:map-point-wave-linear', 'label' => 'To Collect', 'value' => number_format($ct_collect), 'href' => 'pickup_list.php', 'accent' => '#7460ee', 'sub' => 'Pick Up Package']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:delivery-linear', 'label' => 'On Route', 'value' => number_format($ct_onroute), 'accent' => '#00adf2', 'sub' => 'Out For Delivery']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered', 'value' => number_format($ct_delivered), 'accent' => '#1b8a5a', 'sub' => 'All Time']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:layers-minimalistic-linear', 'label' => 'My Consolidations', 'value' => number_format($ct_consol), 'href' => 'consolidate_list.php', 'accent' => '#ef2628']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_drv_volume', 'My Monthly Workload', 'Assigned Orders — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php if ($bd['totals']) { cdp_dashChartCard('open', 'chart_drv_status', 'My Orders By Status', 'All Time', 'col-12 col-lg-5'); cdp_dashChartCard('close'); } ?>
                </div>

                <!-- My assigned orders (AJAX, session-scoped server-side) -->
                <div class="row">
                    <div class="col-md-12 col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $lang['dash-general-19'] ?></h5>
                                <input type="hidden" name="userid" id="userid" value="<?php echo (int) $_SESSION['userid']; ?>">
                                <div class="outer_div"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script src="<?= cdp_asset('dataJs/dashboard_driver.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
