<?php
// ============================================================================
// Warehouse Control Panel — stock on hand, deliverables and collection aging.
//
// Statuses (cdb_styles): 4 In Warehouse, 33 Sorting At Accra Office,
// 6 Available, 32 Ready For Pickup, 1 Pending Collection, 16 Not Picked Up,
// 35 Auction. "Cleared For Delivery" is the Financial Sheet flag
// (fs_cleared_for_delivery) that feeds the Warehouse Delivery queue.
// ============================================================================

require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/dashboard_data.php');
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

$ct_wh_ship   = cdp_dashCount('cdb_add_order', "AND status_courier = 4" . $agency_where);
$ct_wh_pkg    = cdp_dashCount('cdb_customers_packages', "AND status_courier = 4" . $agency_where);
$ct_sorting   = cdp_dashCount('cdb_add_order', "AND status_courier = 33" . $agency_where);
$ct_ready     = cdp_dashCount('cdb_add_order', "AND status_courier IN (6,32)" . $agency_where);
$ct_cleared   = cdp_dashCount('cdb_add_order', "AND fs_cleared_for_delivery = 1 AND status_courier NOT IN (8,21)" . $agency_where);
$ct_uncollect = cdp_dashCount('cdb_add_order', "AND status_courier IN (1,16)" . $agency_where);
$ct_auction   = cdp_dashCount('cdb_add_order', "AND status_courier = 35" . $agency_where);

// Collection-aging ledger (helpers/pickup_aging.php writes it): a package's
// stage is the LATEST milestone stamped on its row.
$aging = ['ready' => 0, 'notified' => 0, 'not_picked' => 0, 'auction' => 0];
try {
    $db->cdp_query("SELECT
            SUM(auction_at IS NOT NULL) auc,
            SUM(auction_at IS NULL AND not_picked_at IS NOT NULL) np,
            SUM(auction_at IS NULL AND not_picked_at IS NULL AND notified_at IS NOT NULL) noti,
            SUM(auction_at IS NULL AND not_picked_at IS NULL AND notified_at IS NULL) rdy
        FROM cdb_package_pickup_aging");
    $db->cdp_execute();
    $r = $db->cdp_registro();
    if ($r) {
        $aging = [
            'ready'      => (int) ($r->rdy ?? 0),
            'notified'   => (int) ($r->noti ?? 0),
            'not_picked' => (int) ($r->np ?? 0),
            'auction'    => (int) ($r->auc ?? 0),
        ];
    }
} catch (Throwable $e) { /* aging ledger absent */ }

// Warehouse-pipeline donut: only the statuses that live in the warehouse flow.
$bd = cdp_dashStatusBreakdown('cdb_add_order', "AND status_courier IN (4,33,6,32,1,16,35)" . $agency_where);
$charts = [];
if ($bd['totals']) {
    $charts[] = [
        'el' => '#chart_wh_status', 'type' => 'donut',
        'series' => $bd['totals'], 'labels' => $bd['labels'], 'colors' => $bd['colors'], 'height' => 300,
    ];
}
$charts[] = [
    'el' => '#chart_wh_arrivals', 'type' => 'bar',
    'series' => [['name' => 'Registered Shipments', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21" . $agency_where)]],
    'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b'], 'height' => 300,
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
    <title>Warehouse Control Panel | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title mb-0">Warehouse Control Panel</h4>
                        <div class="sw-hello-sub"><?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('warehouse_view')) { ?>
                        <a href="warehouse.php" class="btn btn-sm btn-dark">Warehouse View</a>
                        <?php } ?>
                        <?php if ($user->cdp_hasPermission('view_warehouse_delivery')) { ?>
                        <a href="warehouse_delivery.php" class="btn btn-sm btn-outline-dark">Warehouse Delivery</a>
                        <?php } ?>
                        <?php if ($user->cdp_hasPermission('view_dashboard_pick')) { ?>
                        <a href="pickup_aging.php" class="btn btn-sm btn-outline-dark">Uncollected Packages</a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <?php cdp_dashSectionTitle('mdi:warehouse', 'Stock On Hand', 'Live Counts'); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:home-2-linear', 'label' => 'Shipments In Warehouse', 'value' => number_format($ct_wh_ship), 'href' => 'warehouse.php', 'accent' => '#e0ce07']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:cart-large-2-linear', 'label' => 'Packages In Warehouse', 'value' => number_format($ct_wh_pkg), 'href' => 'customer_packages_list.php', 'accent' => '#36bea6']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:sort-by-time-linear', 'label' => 'Sorting At Accra Office', 'value' => number_format($ct_sorting), 'accent' => '#4fa82f']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-linear', 'label' => 'Cleared For Delivery', 'value' => number_format($ct_cleared), 'href' => 'warehouse_delivery.php', 'accent' => '#536dfe', 'sub' => 'Financial Sheet Cleared']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashSectionTitle('solar:user-check-rounded-linear', 'Customer Collection', 'Pickup Pipeline'); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-read-linear', 'label' => 'Available / Ready For Pickup', 'value' => number_format($ct_ready), 'accent' => '#0ae4ff']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hourglass-line-linear', 'label' => 'Awaiting Collection', 'value' => number_format($ct_uncollect), 'href' => 'pickup_aging.php', 'accent' => '#f2b21b', 'sub' => 'Pending / Not Picked Up']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:danger-triangle-linear', 'label' => 'Moved To Auction', 'value' => number_format($ct_auction), 'href' => 'pickup_aging.php', 'accent' => '#7a1f1f']); ?>
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <h6 class="text-muted mb-2" style="font-size:.78rem;">Collection Aging Ledger</h6>
                                <ul class="p-0 m-0" style="list-style:none;font-size:.8rem;">
                                    <li class="d-flex justify-content-between"><span>Ready</span><b><?php echo number_format($aging['ready']); ?></b></li>
                                    <li class="d-flex justify-content-between"><span>Pending Collection</span><b><?php echo number_format($aging['notified']); ?></b></li>
                                    <li class="d-flex justify-content-between"><span>Not Picked Up</span><b><?php echo number_format($aging['not_picked']); ?></b></li>
                                    <li class="d-flex justify-content-between"><span>Auction</span><b><?php echo number_format($aging['auction']); ?></b></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if ($bd['totals']) { cdp_dashChartCard('open', 'chart_wh_status', 'Warehouse Pipeline', 'Shipments Currently In The Warehouse Flow', 'col-12 col-lg-5'); cdp_dashChartCard('close'); } ?>
                    <?php cdp_dashChartCard('open', 'chart_wh_arrivals', 'Monthly Shipment Volume', 'Registered Shipments — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
