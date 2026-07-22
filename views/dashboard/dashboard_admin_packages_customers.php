<?php
// ============================================================================
// Registered Packages Control Panel — cdb_customers_packages at a glance.
// Now agency-scoped like its sibling panels (previously this panel leaked
// company-wide counts to agency-restricted staff).
// ============================================================================

require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/dashboard_data.php');
$db = new Conexion;
$userData = $user->cdp_getUserData();
$statusrow = $core->cdp_getStatus();

$ctx = cdp_getAgencyContext();
$agency_where = '';
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $agency_where = ' AND agency = ' . (int)$ctx['agency_id'];
} elseif ($ctx['is_restricted']) {
    $agency_where = ' AND 1=0';
}

$monthName = obtenerNombreMes((int) date('n'));
$charts = [];

if ($user->cdp_hasPermission('main_dashboard_package_locker')) {
    $ct_total     = cdp_dashCount('cdb_customers_packages', "AND status_courier != 21" . $agency_where);
    $ct_prealerted = cdp_dashCount('cdb_customers_packages', "AND is_prealert = 1 AND status_courier != 21" . $agency_where);
    $ct_warehouse = cdp_dashCount('cdb_customers_packages', "AND status_courier = 4" . $agency_where);
    $ct_delivered = cdp_dashCount('cdb_customers_packages', "AND status_courier = 8" . $agency_where);
    $ct_cancel    = cdp_dashCount('cdb_customers_packages', "AND status_courier = 21" . $agency_where);
    $ct_month     = cdp_dashCount('cdb_customers_packages', "AND status_courier != 21 AND order_date >= '" . date('Y-m-01') . "'" . $agency_where);

    $charts[] = [
        'el' => '#chart_pkg_volume', 'type' => 'bar',
        'series' => [['name' => 'Registered Packages', 'data' => cdp_dashMonthlySeries('cdb_customers_packages', 'order_date', 'COUNT(*)', "AND status_courier != 21" . $agency_where)]],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#36bea6'], 'height' => 300,
    ];
    $bd = cdp_dashStatusBreakdown('cdb_customers_packages', "AND YEAR(order_date)=YEAR(CURDATE())" . $agency_where);
    $charts[] = [
        'el' => '#chart_pkg_status', 'type' => 'donut',
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
    <title><?php echo $lang['left-menu-sidebar-6'] ?> | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-6'] ?></h4>
                        <div class="sw-hello-sub"><?php echo $monthName . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <?php if ($user->cdp_hasPermission('add_package')) { ?>
                        <a href="customer_packages_add.php" class="btn btn-sm btn-dark"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> <?php echo $lang['global-buttons-2'] ?></a>
                        <?php } ?>
                        <a href="customer_packages_list.php" class="btn btn-sm btn-outline-dark"><?php echo $lang['dash-general-23'] ?></a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <?php if ($user->cdp_hasPermission('main_dashboard_package_locker')) { ?>

                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:cart-large-2-linear', 'label' => 'Registered Packages', 'value' => number_format($ct_total), 'href' => 'customer_packages_list.php', 'accent' => '#36bea6', 'sub' => 'Non-Cancelled']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bell-linear', 'label' => 'From Pre-Alerts', 'value' => number_format($ct_prealerted), 'href' => 'prealert_list.php', 'accent' => '#7460ee']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:home-2-linear', 'label' => 'In Warehouse', 'value' => number_format($ct_warehouse), 'href' => 'warehouse.php', 'accent' => '#e0ce07']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered', 'value' => number_format($ct_delivered), 'accent' => '#1b8a5a']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:calendar-linear', 'label' => 'New This Month', 'value' => number_format($ct_month), 'accent' => '#f2b21b', 'sub' => $monthName]); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:close-circle-linear', 'label' => 'Cancelled', 'value' => number_format($ct_cancel), 'accent' => '#f62d51']); ?>
                </div>

                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_pkg_volume', 'Monthly Registered Packages', date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php cdp_dashChartCard('open', 'chart_pkg_status', 'Status Breakdown', date('Y') . ' Packages By Current Status', 'col-12 col-lg-5'); cdp_dashChartCard('close'); ?>
                </div>
                <?php } ?>

                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div id="resultados_ajax"></div>

                                <div class="row mb-5">
                                    <div class="col-sm-12 col-md-6 mb-2">
                                        <div class="input-group">
                                            <input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                            <div class="input-group-append input-sm">
                                                <button type="button" class="btn btn-outline-dark" onclick="cdp_load(1);"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 col-md-6 mb-2">
                                        <div class="input-group">
                                            <select onchange="cdp_load(1);" class="form-control custom-select" id="status_courier" name="status_courier">
                                                <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                <?php foreach ($statusrow as $row) : ?>
                                                    <option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="btn-group mt-2 hide" id="div-actions-checked">
                                            <span class="mt-2 mr-4"><strong> <?php echo $lang['global-2'] ?></strong> <strong id="countChecked"> 0</strong></span>
                                            <button class="btn btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <?php echo $lang['global-1'] ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($user->cdp_hasPermission('select_change_status')) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalCheckboxStatus"><i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['left21550'] ?></a>
                                                <?php } ?>

                                                <?php if ($user->cdp_hasPermission('assign_drivers')) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalDriverCheckbox"><iconify-icon icon="solar:delivery-linear" style="color:#ff0000"></iconify-icon>&nbsp;<?php echo $lang['left208'] ?></a>
                                                <?php } ?>

                                                <?php if ($user->cdp_hasPermission('print_label')) { ?>
                                                <a class="dropdown-item" onclick="cdp_printMultipleLabel();" target="_blank"> <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?> </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div><br></div>

                                <div class="outer_divz"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include('views/modals/modal_update_status_checked.php'); ?>
                <?php include('views/modals/modal_send_email.php'); ?>
                <?php include('views/modals/modal_update_driver.php'); ?>
                <?php include('views/modals/modal_update_driver_checked.php'); ?>
                <?php include('views/modals/modal_delete_package.php'); ?>
                <?php include('views/modals/modal_add_payment_package.php'); ?>
                <?php include('views/modals/modal_verify_payment_packages.php'); ?>

                <?php include 'views/inc/footer.php'; ?>
            </div>
        </div>

        <?php include('helpers/languages/translate_to_js.php'); ?>
        <!-- SweetAlert2 v11 already ships via views/inc/footer.php — do NOT
             re-load the template v7 copy here, it clobbers the shim. -->
        <script src="<?= cdp_asset('dataJs/customers_packages.js') ?>"></script>
        <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
