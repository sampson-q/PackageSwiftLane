<?php
// ============================================================================
// Warehouse Delivery — single consolidation page (mirrors
// financial_sheet_consolidation.php). Header summarises the consolidation's
// delivery progress; the body is the customer -> package -> item accordion,
// loaded by warehouse_delivery_ajax.php (window.WD_CID -> load its customers).
// ============================================================================
$userData = $user->cdp_getUserData();

$wd_cid = (int) ($_GET['id'] ?? 0);

$db = new Conexion;
$db->cdp_query("SELECT consolidate_id, c_prefix, c_no, c_date, status_courier FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $wd_cid);
$db->cdp_execute();
$wd_consol = $db->cdp_registro();

if (!$wd_consol) {
    header("location: warehouse_delivery.php");
    exit;
}

$wd_no = ($wd_consol->c_prefix ?? '') . ($wd_consol->c_no ?? '');

// Header summary: delivery progress across this consolidation's packages.
$WD_TERMINAL = [15, 16, 21, 27, 35];
$db->cdp_query("SELECT a.status_courier, a.fs_cleared_for_delivery, a.total_weight
                FROM cdb_consolidate_detail d
                INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                WHERE d.consolidate_id = :cid");
$db->bind(':cid', $wd_cid);
$db->cdp_execute();
$wd_rows = $db->cdp_registros() ?: [];

$wd_total = count($wd_rows);
$wd_delivered = 0; $wd_ready = 0; $wd_awaiting = 0; $wd_weight = 0.0;
foreach ($wd_rows as $r) {
    $wd_weight += (float) $r->total_weight;
    if ((int) $r->status_courier === 8) { $wd_delivered++; }
    elseif (in_array((int) $r->status_courier, $WD_TERMINAL, true)) { /* terminal */ }
    elseif ((int) $r->fs_cleared_for_delivery === 1) { $wd_ready++; }
    else { $wd_awaiting++; }
}

if ($wd_total > 0 && $wd_delivered >= $wd_total) {
    $wd_stateCls = 'wd-chip-done';    $wd_stateTxt = '<i class="mdi mdi-check-all"></i> Delivered';
} elseif ($wd_delivered > 0) {
    $wd_stateCls = 'wd-chip-partial'; $wd_stateTxt = '<i class="mdi mdi-truck-fast"></i> Partially Delivered';
} elseif ($wd_ready > 0) {
    $wd_stateCls = 'wd-chip-ready';   $wd_stateTxt = '<i class="mdi mdi-truck-check"></i> Ready to Deliver';
} else {
    $wd_stateCls = 'wd-chip-wait';    $wd_stateTxt = '<i class="mdi mdi-lock-clock"></i> Awaiting Clearance';
}
$wd_progCls = ($wd_delivered >= $wd_total && $wd_total > 0) ? 'badge-success' : 'badge-light';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo htmlspecialchars($wd_no); ?> — Warehouse Delivery | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <?php include 'views/courier/wd_styles.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="wd-banner">
                    <i class="mdi mdi-truck-delivery wd-banner-ico"></i>
                    <div>
                        <h4>Warehouse Delivery</h4>
                        <small>Deliver packages Accounts has cleared for delivery.</small>
                    </div>
                </div>

                <div class="card"><div class="card-body">
                    <a href="warehouse_delivery.php" class="btn btn-sm btn-outline-secondary mb-2">
                        <i class="mdi mdi-arrow-left"></i> All Consolidations
                    </a>

                    <div id="loader" style="display:none;" class="text-center my-3">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color:#1b8a5a;"></i>
                    </div>

                    <div class="card mb-2 wd-consol-card">
                        <div class="card-header wd-consol-header p-2" style="cursor:default;">
                            <i class="mdi mdi-package-variant-closed wd-consol-ico"></i>
                            <b class="wd-mono"><?php echo htmlspecialchars($wd_no); ?></b>
                            <span class="wd-dim ml-2"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $wd_consol->c_date); ?></span>
                            <span class="wd-dim ml-2" title="Sum of package weights"><i class="mdi mdi-weight"></i> <?php echo round($wd_weight, 2); ?> lb</span>
                            <span id="wd-hdr-prog"><span class="badge <?php echo $wd_progCls; ?> ml-2" title="Packages delivered"><?php echo $wd_delivered; ?>/<?php echo $wd_total; ?> Delivered</span></span>
                            <span class="wd-spacer"></span>
                            <span id="wd-hdr-right"><?php if ($wd_awaiting > 0): ?><span class="wd-chip-wait mr-1" title="Not yet cleared by Accounts"><i class="mdi mdi-lock"></i> <?php echo $wd_awaiting; ?> Awaiting</span><?php endif; ?><span class="<?php echo $wd_stateCls; ?>"><?php echo $wd_stateTxt; ?></span></span>
                        </div>
                        <div class="card-body p-2 wd-customers" data-cid="<?php echo $wd_cid; ?>" data-loaded="0">
                            <div class="text-muted small">Loading customers…</div>
                        </div>
                    </div>
                </div></div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script>
        window.WD_CID = <?php echo $wd_cid; ?>; // single-consolidation page: auto-load its customers
    </script>
    <script src="<?= cdp_asset('dataJs/warehouse_delivery.js') ?>"></script>
</body>

</html>
