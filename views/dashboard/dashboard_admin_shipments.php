<?php
// ============================================================================
// Shipping Control Panel — shipments (is_pickup = 0) at a glance.
// Counts use the real order semantics (order_incomplete = 1 is a registered
// order) and are scoped exactly like the list page: agency-restricted staff
// see their agency, drivers their own assignments, clients their own orders.
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
} elseif ((int)$userData->userlevel === 3) {
    $agency_where = ' AND driver_id = ' . (int)$_SESSION['userid'];
} elseif ((int)$userData->userlevel === 1) {
    $agency_where = ' AND sender_id = ' . (int)$_SESSION['userid'];
}

$monthName = obtenerNombreMes((int) date('n'));
$charts = [];

if ($user->cdp_hasPermission('view_dashboard_ship')) {
    $shipBase = "AND is_pickup=0 AND order_incomplete=1" . $agency_where;

    $ct_total     = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier != 21");
    $ct_open      = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier NOT IN (8,15,21,35)");
    $ct_delivered = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier = 8");
    $ct_collected = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier = 15");
    $ct_consol    = cdp_dashCount('cdb_add_order', "$shipBase AND is_consolidate = 1 AND status_courier != 21");
    $ct_cancel    = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier = 21");
    $ct_hazmat    = cdp_dashCount('cdb_add_order', "$shipBase AND is_dangerous_good = 1 AND status_courier != 21");
    $ct_month     = cdp_dashCount('cdb_add_order', "$shipBase AND status_courier != 21 AND order_date >= '" . date('Y-m-01') . "'");

    $charts[] = [
        'el' => '#chart_ship_volume', 'type' => 'bar',
        'series' => [['name' => 'Registered Shipments', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "$shipBase AND status_courier != 21")]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b'], 'height' => 300,
    ];
    $bd = cdp_dashStatusBreakdown('cdb_add_order', "$shipBase AND YEAR(order_date)=YEAR(CURDATE())");
    $charts[] = [
        'el' => '#chart_ship_status', 'type' => 'donut',
        'series' => $bd['totals'], 'labels' => $bd['labels'], 'colors' => $bd['colors'], 'height' => 300,
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
    <title><?php echo $lang['left-menu-sidebar-14'] ?> | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-14'] ?></h4>
                        <div class="sw-hello-sub"><?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('add_shipment')) { ?>
                        <a href="courier_add.php" class="btn btn-sm btn-dark"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New Shipment</a>
                        <?php } ?>
                        <a href="courier_list.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['dash-general-19'] ?></a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($user->cdp_hasPermission('view_dashboard_ship')) { ?>

                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:box-minimalistic-linear', 'label' => 'Registered Shipments', 'value' => number_format($ct_total), 'href' => 'courier_list.php', 'accent' => '#f2b21b', 'sub' => 'Non-Cancelled']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:routing-2-linear', 'label' => 'Open / In Progress', 'value' => number_format($ct_open), 'href' => 'courier_list.php', 'accent' => '#2962ff']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered', 'value' => number_format($ct_delivered), 'href' => 'courier_list.php', 'accent' => '#1b8a5a']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:user-check-rounded-linear', 'label' => 'Picked Up (Collected)', 'value' => number_format($ct_collected), 'href' => 'courier_list.php', 'accent' => '#00adf2']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:layers-minimalistic-linear', 'label' => 'In Consolidations', 'value' => number_format($ct_consol), 'href' => 'consolidate_list.php', 'accent' => '#7460ee']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:calendar-linear', 'label' => 'New This Month', 'value' => number_format($ct_month), 'accent' => '#36bea6', 'sub' => $monthName]); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:danger-triangle-linear', 'label' => 'Dangerous Goods', 'value' => number_format($ct_hazmat), 'accent' => '#ff6d00']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:close-circle-linear', 'label' => 'Cancelled', 'value' => number_format($ct_cancel), 'accent' => '#f62d51']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_ship_volume', 'Monthly Shipments', 'Registered Shipments — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashChartCard('open', 'chart_ship_status', 'Status Breakdown', date('Y') . ' Shipments By Current Status', 'col-12 col-lg-5'); cdp_dashChartCard('close'); ?>
                </div>
                <?php } ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['dash-general-19'] ?></h5>
                                <div class="d-flex justify-content-end mb-2"><div class="input-group" style="max-width:170px;"><select onchange="cdp_load(1);" class="form-control custom-select" id="per_page" name="per_page"><option value="25">25 rows</option><option value="50" selected>50 rows</option><option value="100">100 rows</option><option value="all"><?php echo $lang['rows-all'] ?? 'All'; ?></option></select></div></div>
                                <div class="outer_divx"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script>window.cdpDashTable = { url: './ajax/dashboard/shipments/load_shipments_ajax.php', target: '.outer_divx' };</script>
    <script src="<?= cdp_asset('dataJs/dashboard_table.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
