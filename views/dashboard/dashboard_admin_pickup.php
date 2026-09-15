<?php
// ============================================================================
// Pickup Control Panel — pickup requests (is_pickup = 1) at a glance.
// order_incomplete = 0 is a request still awaiting acceptance; once accepted
// it becomes a registered order (order_incomplete = 1). Scoped like the list:
// agency-restricted staff see their agency, drivers their assignments,
// clients their own requests.
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

if ($user->cdp_hasPermission('view_dashboard_pick')) {
    $pickBase = "AND is_pickup=1" . $agency_where;

    $ct_total    = cdp_dashCount('cdb_add_order', "$pickBase AND status_courier != 21");
    $ct_awaiting = cdp_dashCount('cdb_add_order', "$pickBase AND order_incomplete=0 AND status_courier NOT IN (8,12,15,21)");
    $ct_accepted = cdp_dashCount('cdb_add_order', "$pickBase AND order_incomplete=1 AND status_courier != 21");
    $ct_delivered = cdp_dashCount('cdb_add_order', "$pickBase AND status_courier = 8");
    $ct_rejected = cdp_dashCount('cdb_add_order', "$pickBase AND status_courier = 12");
    $ct_cancel   = cdp_dashCount('cdb_add_order', "$pickBase AND status_courier = 21");

    $charts[] = [
        'el' => '#chart_pick_volume', 'type' => 'bar',
        'series' => [['name' => 'Pickup Requests', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "$pickBase AND status_courier != 21")]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#0077B6'], 'height' => 260,
    ];
    $bd = cdp_dashStatusBreakdown('cdb_add_order', "$pickBase AND YEAR(order_date)=YEAR(CURDATE())", 4);
    $ct_year = cdp_dashCount('cdb_add_order', "$pickBase AND status_courier != 21 AND YEAR(order_date)=YEAR(CURDATE())");
    $pickAge = cdp_dashAgeBuckets('cdb_add_order', 'order_datetime', "$pickBase AND order_incomplete=0 AND status_courier NOT IN (8,12,15,21)", [24, 48, 168]);
    $pickAgeMax = max(1, max($pickAge));
    $pickAgeRows = [['0 - 24 hrs', number_format($pickAge[0]), cdp_dashPct($pickAge[0], $pickAgeMax), 'var(--leaf-500)'],
                    ['24 - 48 hrs', number_format($pickAge[1]), cdp_dashPct($pickAge[1], $pickAgeMax), 'var(--amber-500)'],
                    ['2 - 7 days', number_format($pickAge[2]), cdp_dashPct($pickAge[2], $pickAgeMax), 'var(--warm-500)'],
                    ['7+ days', number_format($pickAge[3]), cdp_dashPct($pickAge[3], $pickAgeMax), 'var(--red-500)']];
    $hourLabels = []; for ($h = 0; $h < 24; $h++) { $hourLabels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT); }
    $charts[] = ['el' => '#chart_pick_hours', 'type' => 'heatmap', 'rows' => cdp_dashHourMatrix('cdb_add_order', 'order_datetime', "$pickBase AND status_courier != 21", 90), 'xLabels' => $hourLabels, 'colors' => ['#7C3EE2'], 'height' => 200];
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
    <title><?php echo $lang['left-menu-sidebar-19'] ?> | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-19'] ?></h4>
                        <div class="sw-hello-sub"><?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <a href="pickup_list.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['dash-general-20'] ?></a>
                        <a href="pickup_aging.php" class="btn btn-sm btn-outline-dark">Uncollected Packages</a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($user->cdp_hasPermission('view_dashboard_pick')) { ?>

                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:clock-circle-linear', 'label' => 'Pickup Requests', 'value' => number_format($ct_total), 'href' => 'pickup_list.php', 'accent' => '#0077B6', 'sub' => 'Non-Cancelled']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:hourglass-line-linear', 'label' => 'Awaiting Acceptance', 'value' => number_format($ct_awaiting), 'href' => 'pickup_list.php', 'accent' => '#FFCB01']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:clipboard-check-linear', 'label' => 'Accepted', 'value' => number_format($ct_accepted), 'href' => 'pickup_list.php', 'accent' => '#7C3EE2', 'sub' => 'Converted To Orders']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered', 'value' => number_format($ct_delivered), 'accent' => '#1b8a5a']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:forbidden-circle-linear', 'label' => 'Rejected', 'value' => number_format($ct_rejected), 'accent' => '#fb8c00']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:close-circle-linear', 'label' => 'Cancelled', 'value' => number_format($ct_cancel), 'accent' => '#f62d51']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_pick_volume', 'Monthly Pickup Requests', date('Y'), 'col-12 col-lg-8'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashRingsPanel('chart_pick_status', $bd, $charts, ['col' => 'col-12 col-lg-4', 'title' => 'Requests By Status', 'note' => 'Made this year', 'value' => number_format($ct_year)]); ?>
                </div>
                <div class="row">
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-4', 'title' => 'Awaiting Acceptance By Age', 'note' => 'Time since the request was made']); ?>
                        <?php cdp_dashBars($pickAgeRows); ?>
                    <?php cdp_dashPanel('close'); ?>
                    <?php cdp_dashPanel('open', ['col' => 'col-12 col-lg-8', 'title' => 'Requests By Hour', 'note' => 'Pickup requests per weekday and hour, last 90 days']); ?>
                        <div id="chart_pick_hours" class="sw-chart"></div>
                    <?php cdp_dashPanel('close'); ?>
                </div>
                <?php } ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['dash-general-28'] ?></h5>
                                <small class="text-muted"><?php echo $lang['dash-general-244'] ?></small>
                                <div class="d-flex justify-content-end mb-2"><div class="input-group" style="max-width:170px;"><select onchange="cdp_load(1);" class="form-control custom-select" id="per_page" name="per_page"><option value="25">25 rows</option><option value="50" selected>50 rows</option><option value="100">100 rows</option><option value="all"><?php echo $lang['rows-all'] ?? 'All'; ?></option></select></div></div>
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
    <script>window.cdpDashTable = { url: './ajax/dashboard/pickup/load_pickup_ajax.php', target: '.outer_div' };</script>
    <script src="<?= cdp_asset('dataJs/dashboard_table.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
