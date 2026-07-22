<?php
// ============================================================================
// Consolidations Control Panel (Air Shipping) — cdb_consolidate_packages at a
// glance. Counts only: consolidation money lives on the Financial Sheet, so
// this panel links there instead of re-deriving figures from the legacy
// total_order snapshot.
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
$charts = [];

if ($user->cdp_hasPermission('view_dashboard_packages')) {
    $ct_total     = cdp_dashCount('cdb_consolidate_packages', "AND status_courier != 21" . $agency_where);
    $ct_open      = cdp_dashCount('cdb_consolidate_packages', "AND status_courier NOT IN (8,21)" . $agency_where);
    $ct_transit   = cdp_dashCount('cdb_consolidate_packages', "AND status_courier = 3" . $agency_where);
    $ct_delivered = cdp_dashCount('cdb_consolidate_packages', "AND status_courier = 8" . $agency_where);
    $ct_cancel    = cdp_dashCount('cdb_consolidate_packages', "AND status_courier = 21" . $agency_where);
    $ct_month     = cdp_dashCount('cdb_consolidate_packages', "AND status_courier != 21 AND c_date >= '" . date('Y-m-01') . "'" . $agency_where);

    $charts[] = [
        'el' => '#chart_consp_volume', 'type' => 'bar',
        'series' => [['name' => 'Air Consolidations', 'data' => cdp_dashMonthlySeries('cdb_consolidate_packages', 'c_date', 'COUNT(*)', "AND status_courier != 21" . $agency_where)]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#7460ee'], 'height' => 300,
    ];
    $bd = cdp_dashStatusBreakdown('cdb_consolidate_packages', "AND YEAR(c_date)=YEAR(CURDATE())" . $agency_where);
    $charts[] = [
        'el' => '#chart_consp_status', 'type' => 'donut',
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
    <title><?php echo $lang['left-menu-sidebar-23'] ?> | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-23'] . ' (Air Shipping)'?></h4>
                        <div class="sw-hello-sub"><?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('add_consolidate_package')) { ?>
                        <a href="consolidate_package_add.php" class="btn btn-sm btn-dark"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New Consolidation</a>
                        <?php } ?>
                        <a href="consolidate_package_list.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['dash-general-21'] ?></a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>

                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:layers-minimalistic-linear', 'label' => 'Air Consolidations', 'value' => number_format($ct_total), 'href' => 'consolidate_package_list.php', 'accent' => '#7460ee', 'sub' => 'Non-Cancelled']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:routing-2-linear', 'label' => 'Open / In Progress', 'value' => number_format($ct_open), 'href' => 'consolidate_package_list.php', 'accent' => '#2962ff']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:plain-2-linear', 'label' => 'In Transit', 'value' => number_format($ct_transit), 'accent' => '#00b3a4']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered', 'value' => number_format($ct_delivered), 'accent' => '#1b8a5a']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:calendar-linear', 'label' => 'New This Month', 'value' => number_format($ct_month), 'accent' => '#f2b21b', 'sub' => $monthName]); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:close-circle-linear', 'label' => 'Cancelled', 'value' => number_format($ct_cancel), 'accent' => '#f62d51']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_consp_volume', 'Monthly Air Consolidations', date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashChartCard('open', 'chart_consp_status', 'Status Breakdown', date('Y') . ' Consolidations By Current Status', 'col-12 col-lg-5'); cdp_dashChartCard('close'); ?>
                </div>
                <?php } ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['dash-general-21'] ?></h5>
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
    <script>window.cdpDashTable = { url: './ajax/dashboard/consolidated_package/load_consolidated_ajax.php', target: '.outer_div' };</script>
    <script src="<?= cdp_asset('dataJs/dashboard_table.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
