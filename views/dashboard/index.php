<?php
require_once(dirname(__DIR__, 2) . '/helpers/fs_status.php');
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************
 

$db = new Conexion;

$userData = $user->cdp_getUserData();

// Monetary values are permission-gated: nobody sees money here unless granted
// 'view_monetary_values' (global) or 'view_money_dashboard', by role, department
// or individually. Money-only cards are dropped entirely; mixed rows keep their
// layout and mask just the figure, so the arrangement never collapses.
require_once('helpers/rbac.php');
$canMoney = cdp_canViewMoney($user, 'dashboard');
$money = function ($v) use ($canMoney) {
    return $canMoney ? cdb_money_format($v) : '<span class="text-muted" title="You do not have permission to view monetary values">&mdash;</span>';
};

// Agencia (userlevel 6) solo debe ver dashboard por roles (dashboard_roles.php), no este panel de administración
if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    $base = (string) (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '');
    $base = ($base === '' || $base === '.') ? '' : rtrim($base, '/');
    header('Location: ' . $base . '/index.php');
    exit;
}

// Obtener el mes y el año actual
$month = date('m');
$year = date('Y');

// Obtener el número del mes actual
$currentMonth = date('n');

// Obtener el nombre del mes actual
$monthName = obtenerNombreMes($currentMonth);

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Meta Description (for search results) -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Author (content owner) -->
    <meta name="author" content="CODDINGPRO">
    <!-- Keywords (related keywords) -->
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph Meta (for social media sharing, like Facebook) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $lang['left-menu-sidebar-2'] ?> | <?php echo $core->site_name ?></title>
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">

    <?php include 'views/inc/head_scripts.php'; ?>
    <script src="assets/template/assets/extra-libs/chart.js-2.8/Chart.min.js"></script>

</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>

    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12">
                        <h4 class="page-title mb-0"><?php echo $lang['left-menu-sidebar-2'] ?></h4>
                    </div>
                </div>
            </div>
            <!-- ============================================================== -->
            <!-- End Bread crumb and right sidebar toggle -->
            <!-- ============================================================== -->
            <!-- ============================================================== -->
            <!-- Container fluid  -->
            <!-- ============================================================== -->
            <div class="container-fluid">

                <?php if ($user->cdp_hasPermission('main_dashboard_index')) {
                    $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_add_order where status_courier != 21 and order_incomplete != 0 and is_pickup = 1 AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                    $db->bind(':month', $month); $db->bind(':year', $year); $db->cdp_execute();
                    $sum2 = $db->cdp_registro()->total ?? 0;
                    $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_add_order where status_courier != 21 and is_pickup = 0 AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                    $db->bind(':month', $month); $db->bind(':year', $year); $db->cdp_execute();
                    $sum1 = $db->cdp_registro()->total ?? 0;
                    $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_customers_packages where status_courier != 21 AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                    $db->bind(':month', $month); $db->bind(':year', $year); $db->cdp_execute();
                    $sum3 = $db->cdp_registro()->total ?? 0;
                    $db->cdp_query("SELECT IFNULL(SUM(total_order), 0) as total FROM cdb_add_order WHERE status_courier != 21 AND status_invoice != 0 AND order_payment_method > 1 AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH");
                    $db->bind(':month', $month); $db->bind(':year', $year); $db->cdp_execute();
                    $acct_total = $db->cdp_registro()->total ?? 0;

                    // ---- Financial Sheet money (single source of truth) — same
                    // queries as dashboard_admin_account / Financial Overview so the
                    // figures TALLY. Wrapped: if the FS migration hasn't run yet the
                    // dashboard must still render. Billed uses the USD snapshot;
                    // received/outstanding convert GHS via each row's stored rate.
                    $fs_billed = $fs_received = $fs_outstanding = 0.0;
                    $fs_cleared_ct = 0;
                    try {
                        $fs_ini = date('Y-m-01 00:00:00'); $fs_fin = date('Y-m-t 23:59:59');
                        $db->cdp_query("SELECT COALESCE(SUM(amount_usd),0) t FROM cdb_consolidate_customer_billing WHERE billed_at BETWEEN :i AND :f");
                        $db->bind(':i', $fs_ini); $db->bind(':f', $fs_fin); $db->cdp_execute();
                        $fs_billed = (float) ($db->cdp_registro()->t ?? 0);

                        $db->cdp_query("SELECT COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) t
                                        FROM cdb_fs_payments WHERE recorded_at BETWEEN :i AND :f
                                          AND " . cdp_fsMoneySqlFilter());
                        $db->bind(':i', $fs_ini); $db->bind(':f', $fs_fin); $db->cdp_execute();
                        $fs_received = (float) ($db->cdp_registro()->t ?? 0);

                        $db->cdp_query("SELECT COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0) t FROM cdb_consolidate_customer_billing");
                        $db->cdp_execute();
                        $fs_outstanding = (float) ($db->cdp_registro()->t ?? 0);

                        $db->cdp_query("SELECT COUNT(*) c FROM cdb_add_order WHERE fs_cleared_for_delivery = 1 AND status_courier NOT IN (8,21)");
                        $db->cdp_execute();
                        $fs_cleared_ct = (int) ($db->cdp_registro()->c ?? 0);
                    } catch (Throwable $e) {
                        // FS tables/columns not present — leave zeros.
                    }
                ?>
                <!-- ROW 1 - Panel actual: Summary of accounts receivable + Logistics Summary (con iconos)
                     Both cards in this row exist only to show money, so the whole
                     row is dropped for users without the monetary permission. -->
                <?php if ($canMoney): ?>
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-body position-relative">
                                <h5 class="card-title"><?php echo $lang['messagesform84']; ?></h5>
                                <p class="h4 mb-3"><?php echo cdb_money_format($acct_total); ?></p>
                                <a href="dashboard_admin_account.php" class="btn btn-primary"><?php echo $lang['messagesform83']; ?></a>
                                <div class="position-absolute" style="right: 1rem; top: 50%; transform: translateY(-50%); opacity: 0.85;">
                                    <iconify-icon icon="solar:wallet-money-linear" class="text-primary" style="font-size: 3.5rem;"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $lang['messagesform89']; ?></h5>
                                <p class="text-muted small"><?php echo $lang['messagesform90']; ?> <?php echo $monthName; ?></p>
                                <div class="row mt-3">
                                    <div class="col-12 col-md-4 mb-2 mb-md-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10"><span class="text-info"><iconify-icon icon="solar:clock-circle-linear" class="display-7"></iconify-icon></span></div>
                                            <p class="mb-0"><?php echo cdb_money_format($sum2); ?> <?php echo $lang['dash-general-11']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4 mb-2 mb-md-0">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10"><span class="text-primary"><iconify-icon icon="solar:box-minimalistic-linear" class="display-7"></iconify-icon></span></div>
                                            <p class="mb-0"><?php echo cdb_money_format($sum1); ?> <?php echo $lang['dash-general-10']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <div class="d-flex align-items-center">
                                            <div class="m-r-10"><span class="text-success"><iconify-icon icon="solar:cart-large-2-linear" class="display-7"></iconify-icon></span></div>
                                            <p class="mb-0"><?php echo cdb_money_format($sum3); ?> <?php echo $lang['messagesform85']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; // $canMoney — ROW 1 ?>

                <!-- ROW 1b - FINANCIAL SHEET (single source of truth). Figures match
                     the Financial Sheet / Financial Overview / Accounts pages.
                     The three money cards are gated; "Cleared for Delivery" is a
                     package count, not money, so it stays and widens to fill the
                     row on its own rather than leaving a gap. -->
                <div class="row">
                    <?php if ($canMoney): ?>
                    <div class="col-12 mb-2">
                        <h5 class="m-0"><iconify-icon icon="solar:file-text-linear" class="text-primary"></iconify-icon> Financial Sheet &mdash; <?php echo $monthName; ?></h5>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 mb-4">
                        <div class="card border-left-primary" style="border-left:4px solid #536dfe;">
                            <div class="card-body position-relative">
                                <h6 class="text-muted mb-1">Billed (This Month)</h6>
                                <p class="h4 mb-2"><?php echo cdb_money_format($fs_billed); ?></p>
                                <a href="financial_sheet.php" class="btn btn-sm btn-outline-primary">Financial Sheet</a>
                                <div class="position-absolute" style="right:1rem; top:1rem; opacity:.8;">
                                    <iconify-icon icon="solar:file-text-linear" class="text-primary" style="font-size:2.6rem;"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 mb-4">
                        <div class="card" style="border-left:4px solid #1b8a5a;">
                            <div class="card-body position-relative">
                                <h6 class="text-muted mb-1">Received (This Month)</h6>
                                <p class="h4 mb-2 text-success"><?php echo cdb_money_format($fs_received); ?></p>
                                <a href="transactions.php" class="btn btn-sm btn-outline-success">Transactions</a>
                                <div class="position-absolute" style="right:1rem; top:1rem; opacity:.8;">
                                    <iconify-icon icon="solar:hand-money-linear" class="text-success" style="font-size:2.6rem;"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 mb-4">
                        <div class="card" style="border-left:4px solid #e67e22;">
                            <div class="card-body position-relative">
                                <h6 class="text-muted mb-1">Outstanding (All-Time)</h6>
                                <p class="h4 mb-2 text-warning"><?php echo cdb_money_format($fs_outstanding); ?></p>
                                <a href="accounts_receivable.php" class="btn btn-sm btn-outline-warning">Receivables</a>
                                <div class="position-absolute" style="right:1rem; top:1rem; opacity:.8;">
                                    <iconify-icon icon="solar:bill-list-linear" class="text-warning" style="font-size:2.6rem;"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; // $canMoney — the three money cards ?>
                    <div class="col-12 col-md-6 col-lg-<?php echo $canMoney ? '3' : '4'; ?> mb-4">
                        <div class="card" style="border-left:4px solid #6c757d;">
                            <div class="card-body position-relative">
                                <h6 class="text-muted mb-1">Cleared for Delivery</h6>
                                <p class="h4 mb-2"><?php echo (int) $fs_cleared_ct; ?> <small class="text-muted">pkgs</small></p>
                                <a href="warehouse_delivery.php" class="btn btn-sm btn-outline-secondary">Warehouse</a>
                                <div class="position-absolute" style="right:1rem; top:1rem; opacity:.8;">
                                    <iconify-icon icon="solar:box-linear" class="text-secondary" style="font-size:2.6rem;"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Earning Reports -->
                    <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4 mb-2">
                      <div class="card">
                        <div class="card-body pb-4">
                            <div class="card-header-title d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="m-0 me-2"><?php echo $lang['messagesform95'] ?></h5>
                                    <small class="text-muted"><?php echo $lang['messagesform96'] ?> <?php echo $monthName; ?></small>
                                </div>
                            </div>
                            <div><br></div>
                            <ul class="list-style-none">
                                <li class="mb-0">
                                    <div class="row">
                                        <div class="col-xl-7 col-md-7 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['dash-general-11'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                        // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_add_order where status_courier != 21 and order_incomplete != 0 and is_pickup = 1
                                                            AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                                                        // Vincular parámetros
                                                        $db->bind(':month', $month);
                                                        $db->bind(':year', $year);
                                                        // Ejecutar la consulta
                                                        $db->cdp_execute();
                                                        // Obtener el registro
                                                        $count = $db->cdp_registro();
                                                        $total_orders = isset($count->total) ? (float)$count->total : 0;
                                                        echo $money($total_orders);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-5 col-md-5 mb-0">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                        <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $totalDays = max(1, (int)$totalDays);
                                                        $progressPercentage = min(100, ($total_orders / $totalDays) * 100);
                                                        ?>
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $total_orders; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <li class="mb-0">
                                    <div class="row">
                                        <div class="col-xl-7 col-md-7 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['dash-general-10'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                        // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_add_order where status_courier != 21 and is_pickup = 0
                                                            AND order_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND order_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                                                        // Vincular parámetros
                                                        $db->bind(':month', $month);
                                                        $db->bind(':year', $year);
                                                        // Ejecutar la consulta
                                                        $db->cdp_execute();
                                                        // Obtener el registro
                                                        $count = $db->cdp_registro();
                                                        $total_orders2 = isset($count->total) ? (float)$count->total : 0;
                                                        echo $money($total_orders2);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-5 col-md-5 mb-0">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                       <?php
                                                        $totalDays = max(1, (int)date('t'));
                                                        $progressPercentage = min(100, ($total_orders2 / $totalDays) * 100);
                                                        ?>
                                                        <div class="progress-bar bg-label-blue" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $total_orders2; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <li class="mb-0">
                                    <div class="row">
                                        <div class="col-xl-7 col-md-7 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['messagesform94'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                            // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_consolidate where status_courier != 21
                                                            AND c_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND c_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                                                        // Vincular parámetros
                                                        $db->bind(':month', $month);
                                                        $db->bind(':year', $year);
                                                        // Ejecutar la consulta
                                                        $db->cdp_execute();
                                                        // Obtener el registro
                                                        $count = $db->cdp_registro();
                                                        $total_orders3 = isset($count->total) ? (float)$count->total : 0;
                                                        echo $money($total_orders3);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-5 col-md-5 mb-6">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                        <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $progressPercentage = min(100, ($total_orders3 / max(1, (int)$totalDays)) * 100);
                                                        ?>
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $total_orders3; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>


                                <li class="mb-0">
                                    <div class="row">
                                        <div class="col-xl-7 col-md-7 mb-2">
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <h6 class="mb-0"><?php echo $lang['messagesform93'] ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $core->currency; ?>
                                                        <?php
                                                        // Ejecutar la consulta SQL para obtener el total de órdenes de compra
                                                        $db->cdp_query('SELECT IFNULL(SUM(total_order),0) as total FROM cdb_consolidate_packages where status_courier != 21
                                                            AND c_date >= MAKEDATE(:year,1) + INTERVAL (:month-1) MONTH AND c_date < MAKEDATE(:year,1) + INTERVAL :month MONTH');
                                                        // Vincular parámetros
                                                        $db->bind(':month', $month);
                                                        $db->bind(':year', $year);
                                                        // Ejecutar la consulta
                                                        $db->cdp_execute();
                                                        // Obtener el registro
                                                        $count = $db->cdp_registro();
                                                        $total_orders4 = isset($count->total) ? (float)$count->total : 0;
                                                        echo $money($total_orders4);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-5 col-md-5 mb-6">
                                            <div class="user-progress align-items-center gap-3">
                                                <div class="align-items-center gap-1">
                                                    <div class="progress m-t-10">
                                                        <?php
                                                        // Calcular el progreso actual del mes
                                                        $currentDay = date('j');
                                                        $totalDays = date('t');
                                                        $progressPercentage = min(100, ($total_orders4 / max(1, (int)$totalDays)) * 100);
                                                        ?>
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" aria-valuenow="<?php echo $total_orders4; ?>" aria-valuemin="0" aria-valuemax="<?php echo $totalDays; ?>"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                      </div>
                    </div>
                    <!--/ Earning Reports -->

                     <div class="col-12 col-sm-8 col-md-8 col-lg-8 col-xl-8 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-8 col-md-8 col-lg-8 col-xl-8 mb-4">

                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-6">
                                                <h5 class="m-0 me-2"><?php echo $lang['messagesform91'] ?></h5>
                                                <small class="text-muted"><?php echo $lang['messagesform92'] ?></small>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="pb-0">
                                            <div class="row">
                                                <!-- Primer grupo de 3 elementos -->
                                                <div class="col-sm-6 col-md-6 col-lg-6">
                                                    <!-- Primer elemento contador de envios -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10">
                                                                <a href="dashboard_admin_shipments.php">
                                                                    <span class="text-orange display-7">
                                                                        <iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
                                                                    </span>
                                                                </a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_add_order WHERE order_incomplete=1');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                    ?>            
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-1'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Segundo elemento contador de recogida envio -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="pickup_list.php"><span class="text-cyan display-7"><iconify-icon icon="solar:clock-circle-linear" class="fs-5"></iconify-icon></span> </a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_add_order WHERE order_incomplete != 0 and is_pickup=1');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                ?>            
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-2'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Tercer elemento contador de consolidados de envios-->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="consolidate_list.php"><span class="text-danger display-7"><iconify-icon icon="solar:gift-linear" class="fs-5"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_consolidate');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                ?>           
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-3'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                          
                                                <!-- Segundo grupo de 3 elementos -->
                                                <div class="col-sm-6 col-md-6 col-lg-6">
                                                    <!-- Cuarto elemento contador de cuentas por cobrar -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="accounts_receivable.php"><span class="text-primary display-7"><iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_add_order WHERE order_payment_method >1');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                ?>         
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-4'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Quinto elemento contador de pre alertas -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="prealert_list.php"><span class="text-warning display-7"><iconify-icon icon="solar:bell-linear" class="fs-5"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                 <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_pre_alert where is_package=0');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                ?>       
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-5'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Sexto elemento de contador de paquetes -->
                                                    <div class="col-lg-12 col-md-12 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="m-r-10"><a href="courier_list.php"><span class="text-success display-7"><iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon></span></a>
                                                            </div>

                                                            <div class="card-info-statics">
                                                              <h5 class="mb-0">
                                                                 <?php
                                                                    $db->cdp_query('SELECT COUNT(*) as total FROM cdb_add_order where is_pickup=0 and order_incomplete=0 and status_courier != 21');
                                                                    $db->cdp_execute();
                                                                    $count = $db->cdp_registro();
                                                                    echo (int)($count->total ?? 0);
                                                                ?> 
                                                              </h5>
                                                              <small><?php echo $lang['dash-general-661'] ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-12 col-sm-4 col-md-4 col-lg-4 col-xl-4 mb-4">
                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-6">
                                                <h5 class="m-0 me-2"><?php echo $lang['messagesform97'] ?></h5>
                                                <small class="text-muted"><?php echo $lang['messagesform98'] ?></small>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <?php
                                        // Counts by role CATEGORY (role-flag based, so every active role is
                                        // counted, not just the 4 legacy userlevels), plus departments.
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
                                        $ucRows = [
                                            ['solar:shield-check-linear',            ($lang['dash-general-14'] ?? 'Super Admin'), $uc['super']],
                                            ['solar:users-group-two-rounded-linear', ($lang['dash-general-15'] ?? 'Managers'),    $uc['staff']],
                                            ['solar:user-id-linear',                 ($lang['dash-general-16'] ?? 'Drivers'),     $uc['driver']],
                                            ['solar:user-plus-linear',               ($lang['dash-general-17'] ?? 'Customers'),   $uc['client']],
                                        ];
                                        ?>
                                        <div class="pb-0">
                                            <h6 class="text-muted mb-1" style="font-size:.78rem;">User Registrations</h6>
                                            <ul class="p-0 m-0" style="list-style:none;">
                                                <?php foreach ($ucRows as $r): ?>
                                                <li class="d-flex align-items-center justify-content-between mb-1">
                                                    <span class="d-flex align-items-center">
                                                        <span class="avatar-initial rounded bg-label-warning d-inline-flex align-items-center justify-content-center me-2" style="width:1.5rem;height:1.5rem;"><iconify-icon icon="<?php echo $r[0]; ?>" style="font-size:.9rem"></iconify-icon></span>
                                                        <small><?php echo htmlspecialchars((string) $r[1]); ?></small>
                                                    </span>
                                                    <b><?php echo (int) $r[2]; ?></b>
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
                        </div>
                    </div>
                </div>


            <?php } ?>

                <div class="row">
                    <div class="col-12 col-sm-12 col-md-12">
                        <div class="card">
                            <div class="card-body">

                                <!-- title -->
                                <ul class="nav nav-pills custom-pills m-t-20" id="pills-tab2" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="pills-home-tab2" data-toggle="pill" href="#pills-shipment" role="tab" aria-selected="true"><h5 class="card-title mb-0"><?php echo $lang['dash-general-19'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab2" href="pickup_list.php" role="tab" aria-selected="false"><h5 class="card-title mb-0"><?php echo $lang['dash-general-20'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab2" href="consolidate_list.php" role="tab" aria-selected="false"><h5 class="card-title mb-0"><?php echo $lang['dash-general-21'] ?></h5></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab" href="prealert_list.php">
                                            <h5 class="card-title mb-0"><?php echo $lang['dash-general-22'] ?></h5>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="pills-profile-tab" href="courier_list.php">
                                            <h5 class="card-title mb-0"><?php echo $lang['dash-general-23'] ?></h5>
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content  m-t-30" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-shipment" role="tabpanel" aria-labelledby="pills-home-tab">

                                        <div class="col-md-12 mt-12 mb-12">
                                            <div class="input-group">
                                                <input type="text" name="search_shipment" id="search_shipment" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                                <div class="input-group-append input-sm">
                                                    <button type="submit" class="btn btn-info"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div><br></div>

                                        <div class="results_shipments"></div>
                                    </div>
                                    <div class="tab-pane fade" id="pills-pickup" role="tabpanel" aria-labelledby="pills-profile-tab">

                                        <div class="col-md-4 mt-4 mb-4">
                                            <div class="input-group">
                                                <input type="text" name="search_pickup" id="search_pickup" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                                <div class="input-group-append input-sm">
                                                    <button type="submit" class="btn btn-info"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="results_pickup"></div>

                                    </div>
                                    <div class="tab-pane fade" id="pills-consolidated" role="tabpanel" aria-labelledby="pills-contact-tab">
                                        <div class="col-md-4 mt-4 mb-4">
                                            <div class="input-group">
                                                <input type="text" name="search_consolidated" id="search_consolidated" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                                <div class="input-group-append input-sm">
                                                    <button type="submit" class="btn btn-info"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="results_consolidated"></div>
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
    <!-- Pickup-aging: prompt admins to notify senders of packages uncollected for 2 weeks. -->
    <script>window.cdpPaDashboard = true;</script>
    <script src="<?= cdp_asset('dataJs/pickup_aging.js') ?>"></script>