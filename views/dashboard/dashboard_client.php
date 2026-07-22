<?php
// ============================================================================
// Customer Dashboard — everything scoped to the signed-in customer.
// The outstanding balance comes from the Financial Sheet ledger (the same
// numbers My Bills shows), NOT the legacy cdb_charges_order sums, so what the
// customer sees here always matches what they are asked to pay.
// ============================================================================

require_once(__DIR__ . '/../../helpers/dashboard_data.php');

$userData = $user->cdp_getUserData();
$virtualMailboxes = $core->cdp_getVirtualMailboxes('');

$db = new Conexion;
$uid = (int) $_SESSION['userid'];
$own = " AND sender_id = $uid";

$monthName = obtenerNombreMes((int) date('n'));

// ---- My counts (accurate order semantics, own rows only) -------------------
$ct_ship      = cdp_dashCount('cdb_add_order', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21 $own");
$ct_pickups   = cdp_dashCount('cdb_add_order', "AND is_pickup=1 AND status_courier != 21 $own");
$ct_packages  = cdp_dashCount('cdb_customers_packages', "AND status_courier != 21 $own");
$ct_prealerts = cdp_dashCount('cdb_pre_alert', "AND is_package=0 AND customer_id = $uid");
$ct_delivered = cdp_dashCount('cdb_add_order', "AND status_courier IN (8,15) AND order_incomplete=1 $own");
$ct_ready     = cdp_dashCount('cdb_add_order', "AND status_courier IN (6,32) AND order_incomplete=1 $own");

// ---- My balance (Financial Sheet — same figures as My Bills) ---------------
$fs = cdp_dashFsTotals($uid);

// ---- My charts -------------------------------------------------------------
$charts = [
    [
        'el' => '#chart_my_volume', 'type' => 'bar',
        'series' => [
            ['name' => 'Shipments', 'data' => cdp_dashMonthlySeries('cdb_add_order', 'order_date', 'COUNT(*)', "AND is_pickup=0 AND order_incomplete=1 AND status_courier != 21 $own")],
            ['name' => 'Packages',  'data' => cdp_dashMonthlySeries('cdb_customers_packages', 'order_date', 'COUNT(*)', "AND status_courier != 21 $own")],
        ],
        'labels' => cdp_dashMonthLabels(), 'colors' => ['#f2b21b', '#111111'], 'height' => 280,
    ],
];
$bd = cdp_dashStatusBreakdown('cdb_add_order', "AND order_incomplete=1 $own");
if ($bd['totals']) {
    $charts[] = [
        'el' => '#chart_my_status', 'type' => 'donut',
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
                        <div class="sw-hello-sub">Welcome back, <?php echo htmlspecialchars(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($userData->locker)) { ?> &mdash; Locker <span class="text-danger font-weight-bold"><?php echo htmlspecialchars($userData->locker, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?></div>
                    </div>
                    <div class="sw-quick-actions">
                        <a href="prealert_add.php" class="btn btn-sm btn-dark"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New Pre-Alert</a>
                        <a href="my_bills.php" class="btn btn-sm btn-outline-dark">My Bills</a>
                        <a href="tracking.php" class="btn btn-sm btn-outline-dark">Track</a>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <!-- My balance + activity -->
                <div class="row">
                    <?php cdp_dashKpi(['icon' => 'solar:bill-list-linear', 'label' => 'Outstanding Balance', 'value' => cdb_money_format($fs['outstanding']), 'href' => 'my_bills.php', 'accent' => ($fs['outstanding'] > 0 ? '#e67e22' : '#1b8a5a'), 'sub' => 'Matches My Bills']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-minimalistic-linear', 'label' => 'My Shipments', 'value' => number_format($ct_ship), 'href' => 'courier_list.php', 'accent' => '#f2b21b']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:clock-circle-linear', 'label' => 'My Pickup Requests', 'value' => number_format($ct_pickups), 'href' => 'pickup_list.php', 'accent' => '#2962ff']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:cart-large-2-linear', 'label' => 'My Packages', 'value' => number_format($ct_packages), 'href' => 'customer_packages_list.php', 'accent' => '#36bea6']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:bell-linear', 'label' => 'My Pre-Alerts', 'value' => number_format($ct_prealerts), 'href' => 'prealert_list.php', 'accent' => '#7460ee']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:box-linear', 'label' => 'Ready For Collection', 'value' => number_format($ct_ready), 'accent' => '#0ae4ff', 'sub' => 'Available At Office']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:check-circle-linear', 'label' => 'Delivered / Collected', 'value' => number_format($ct_delivered), 'accent' => '#1b8a5a', 'sub' => 'All Time']); ?>
                    <?php cdp_dashKpi(['icon' => 'solar:card-linear', 'label' => 'My Bills', 'value' => 'Pay Online', 'href' => 'my_bills.php', 'accent' => '#111111', 'sub' => 'Mobile Money']); ?>
                </div>

                <!-- Virtual mailbox addresses -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card mb-0">
                            <div class="card-body">
                                <h5 class="card-title mb-0"><?php echo $lang['virtual_mailbox-4'] ?></h5>
                                <small class="text-muted">Use these addresses when shopping online — include your locker code.</small>
                                <div class="sw-mailbox-strip mt-3">
                                    <?php foreach ($virtualMailboxes as $virtualMailbox) {
                                        $db->cdp_query("SELECT * FROM cdb_countries WHERE id = :cid");
                                        $db->bind(':cid', $virtualMailbox->cdb_countries_id);
                                        $db->cdp_execute();
                                        $country_data = $db->cdp_registro();
                                        if (!$country_data) { continue; }
                                    ?>
                                    <div class="sw-mailbox-card">
                                        <h6><span class="flag-circle mr-1"><i class="fi fi-<?php echo strtolower($country_data->iso2); ?>"></i></span> <?php echo htmlspecialchars($country_data->name, ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <div class="mb-1"><?php echo htmlspecialchars(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''), ENT_QUOTES, 'UTF-8'); ?> <span class="sw-locker">(<?php echo htmlspecialchars($userData->locker ?? '', ENT_QUOTES, 'UTF-8'); ?>)</span></div>
                                        <div class="text-muted small"><?php echo $virtualMailbox->address; ?></div>
                                        <div class="text-muted small"><?php echo $virtualMailbox->city . ' ' . $virtualMailbox->postcode; ?></div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My activity charts -->
                <div class="row">
                    <?php cdp_dashChartCard('open', 'chart_my_volume', 'My Monthly Activity', 'Shipments & Packages — ' . date('Y'), 'col-12 col-lg-7'); cdp_dashChartCard('close'); ?>
                    <?php if ($bd['totals']) { cdp_dashChartCard('open', 'chart_my_status', 'My Orders By Status', 'All Time', 'col-12 col-lg-5'); cdp_dashChartCard('close'); } ?>
                </div>

                <!-- My recent shipments (AJAX, session-scoped server-side) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="pill" href="#pills-shipment" role="tab" aria-selected="true"><?php echo $lang['dash-general-19'] ?></a>
                                        <input type="hidden" name="userid" id="userid" value="<?php echo (int) $_SESSION['userid']; ?>">
                                    </li>
                                    <li class="nav-item"><a class="nav-link" href="prealert_list.php"><?php echo $lang['dash-general-22'] ?></a></li>
                                    <li class="nav-item"><a class="nav-link" href="customer_packages_list.php"><?php echo $lang['dash-general-23'] ?></a></li>
                                    <li class="nav-item"><a class="nav-link" href="consolidate_list.php"><?php echo $lang['dash-general-21'] ?></a></li>
                                </ul>

                                <div class="tab-content" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-shipment" role="tabpanel">
                                        <div class="outer_div"></div>
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

    <script src="<?= cdp_asset('dataJs/dashboard_client.js') ?>"></script>
    <?php cdp_dashChartsRender($charts, $core->currency); ?>
</body>

</html>
