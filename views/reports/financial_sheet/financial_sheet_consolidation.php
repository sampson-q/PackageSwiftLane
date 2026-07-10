<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet: one consolidation (own page)            *
// * Header: number, date, weight, priced customers, due + paid, exports.   *
// * Body: customer -> packages -> items tree (same AJAX as the list page). *
// *************************************************************************

if ((!$user->cdp_is_Admin() && (int) ($user->userlevel ?? 0) !== 3)) {
    cdp_redirect_to("login.php");
}

$userData = $user->cdp_getUserData();

$fs_cid = (int) ($_GET['id'] ?? 0);

$db = new Conexion;
$db->cdp_query("SELECT c.* FROM cdb_consolidate c WHERE c.consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $fs_cid);
$db->cdp_execute();
$fs_consol = $db->cdp_registro();

if (!$fs_consol) {
    cdp_redirect_to("financial_sheet.php");
}

$fs_no = ($fs_consol->c_prefix ?? '') . ($fs_consol->c_no ?? '');

// Money due + customers-priced ratio (deduped join — (order_prefix, order_no)
// is NOT unique in cdb_add_order; each detail row maps to exactly one order).
$db->cdp_query("
    SELECT a.sender_id AS sid, SUM(a.total_order) AS money,
           SUM(COALESCE(NULLIF(a.total_weight, 0), d.weight)) AS wsum,
           MIN(CASE WHEN EXISTS (SELECT 1 FROM cdb_add_order_item i
                                 WHERE i.order_id = a.order_id AND i.priced_at IS NULL)
                    THEN 0 ELSE 1 END) AS all_priced
    FROM cdb_consolidate_detail d
    INNER JOIN cdb_add_order a ON a.order_id = (
        SELECT a2.order_id FROM cdb_add_order a2
        WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
        ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
        LIMIT 1)
    WHERE d.consolidate_id = :cid
    GROUP BY a.sender_id");
$db->bind(':cid', $fs_cid);
$db->cdp_execute();
$fs_rate = (float) $core->exchange_rate;
$fs_money = 0.0;       // packages total (USD, before fees)
$fs_fees_ghs = 0.0;    // one handling fee per customer, from their TOTAL
$fs_weight = 0.0;
$fs_custs = 0;
$fs_custs_priced = 0;
foreach ((array) $db->cdp_registros() as $r) {
    $fs_money += (float) $r->money;
    $fs_fees_ghs += cdp_handlingFeeGhs(cdp_usdToGhs((float) $r->money, $fs_rate));
    $fs_weight += (float) $r->wsum;
    $fs_custs++;
    $fs_custs_priced += (int) $r->all_priced;
}
$fs_fee_usd = cdp_ghsToUsd($fs_fees_ghs, $fs_rate);
$fs_due_usd = $fs_money + $fs_fee_usd;
$fs_badge_cls = ($fs_custs > 0 && $fs_custs_priced >= $fs_custs) ? 'badge-success' : 'badge-warning';

$db->cdp_query("SELECT COALESCE(SUM(paid_ghs),0) AS p FROM cdb_consolidate_customer_billing
                WHERE consolidate_id = :cid AND paid_ghs IS NOT NULL");
$db->bind(':cid', $fs_cid);
$db->cdp_execute();
$fs_paid_row = $db->cdp_registro();
$fs_paid = $fs_paid_row ? (float) $fs_paid_row->p : 0.0;
$fs_paid_usd = function_exists('cdp_ghsToUsd')
    ? cdp_ghsToUsd($fs_paid, (float) $core->exchange_rate)
    : ($fs_paid / max(0.0001, (float) $core->exchange_rate));

// Last exchange-rate change (audit) — shown next to the rate hint.
$fs_rate_log = null;
try {
    $db->cdp_query("SELECT l.new_rate, l.changed_at,
                           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                    u.username, CONCAT('User ', l.changed_by)) AS uname
                    FROM cdb_exchange_rate_log l
                    LEFT JOIN cdb_users u ON u.id = l.changed_by
                    ORDER BY l.id DESC LIMIT 1");
    $db->cdp_execute();
    $fs_rate_log = $db->cdp_registro();
} catch (Throwable $e) {
    // Rate-log table missing (financial_sheet_v2.sql §5 not run).
}

$fs_dg_style = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
$fs_dg_color = ($fs_dg_style && !empty($fs_dg_style->color)) ? $fs_dg_style->color : '#ff6d00';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo htmlspecialchars($fs_no); ?> — Financial Sheet | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <?php include 'views/reports/financial_sheet/fs_styles.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card card-outline" style="border-top: 3px solid #bbb">
                            <h4 class="card-title ml-4 mt-3">
                                <i class="fas fa-file-invoice-dollar"></i> Financial Sheet
                                <a href="financial_sheet.php" class="btn btn-sm btn-outline-secondary ml-3">
                                    <i class="mdi mdi-arrow-left"></i> All consolidations
                                </a>
                            </h4>

                            <div class="card-body">
                                <div id="loader" style="display:none;" class="text-center my-3">
                                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                </div>

                                <div class="card mb-2 fs-consol-card fs-active">
                                    <div class="card-header fs-consol-header" style="cursor:default;">
                                        <i class="fas fa-boxes"></i>
                                        <b><?php echo htmlspecialchars($fs_no); ?></b>
                                        <span class="fs-dim ml-3"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $fs_consol->c_date); ?></span>
                                        <span class="fs-dim ml-3" title="Sum of package weights">
                                            <i class="mdi mdi-weight"></i> <?php echo round($fs_weight, 2); ?> lb
                                        </span>
                                        <span class="badge <?php echo $fs_badge_cls; ?> ml-3 fs-consol-custpriced" data-cid="<?php echo $fs_cid; ?>"
                                              title="Customers whose packages are fully priced">
                                            <i class="mdi mdi-account-check"></i> <?php echo $fs_custs_priced; ?>/<?php echo $fs_custs; ?> customers priced
                                        </span>
                                        <?php if ((int) ($fs_consol->is_dangerous_good ?? 0) === 1): ?>
                                            <span class="fs-dg-badge" style="background:<?php echo htmlspecialchars($fs_dg_color); ?>;"
                                                  title="This consolidation contains dangerous goods">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fs-spacer"></span>
                                        <span class="fs-dim fs-consol-fees" data-cid="<?php echo $fs_cid; ?>"
                                              title="Packages + handling fees">$<?php echo number_format($fs_money, 2); ?> + $<?php echo number_format($fs_fee_usd, 2); ?> fees</span>
                                        <span class="fs-money fs-consol-total" data-cid="<?php echo $fs_cid; ?>" data-usd="<?php echo $fs_due_usd; ?>"
                                              title="Amount due incl. handling fees">Due $<?php echo number_format($fs_due_usd, 2); ?></span>
                                        <span class="fs-chip-paid fs-consol-paid" data-cid="<?php echo $fs_cid; ?>"
                                              title="Amount received from customers so far">Received $<?php echo number_format($fs_paid_usd, 2); ?></span>
                                        <button type="button" class="btn btn-sm btn-light ml-2"
                                                onclick="fsExportConsolidation(<?php echo $fs_cid; ?>);"
                                                title="Export this consolidation to PDF">
                                            <i class="fa fa-file-pdf text-danger"></i> PDF
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light"
                                                onclick="fsExportConsolidationExcel(<?php echo $fs_cid; ?>);"
                                                title="Export this consolidation to Excel">
                                            <i class="fa fa-file-excel text-success"></i> Excel
                                        </button>
                                    </div>
                                    <div class="fs-consol-body" data-cid="<?php echo $fs_cid; ?>" style="display:block;">
                                        <div class="card-body p-2 fs-customers" data-cid="<?php echo $fs_cid; ?>" data-loaded="0">
                                            <div class="text-muted small">Loading customers…</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Display currency">
                                        <button type="button" id="fs_cur_ghs" class="btn btn-outline-dark" onclick="fsSetCurrency('ghs');">GH&#8373;</button>
                                        <button type="button" id="fs_cur_usd" class="btn btn-outline-dark" onclick="fsSetCurrency('usd');">$ USD</button>
                                    </div>
                                </div>
                                <div class="fs-hint mt-2">
                                    Rate: $1 = &#8373;<span id="fs_rate_label"></span>
                                    <?php if ($fs_rate_log): ?>
                                        <span title="Exchange-rate audit log">(updated by <?php echo htmlspecialchars($fs_rate_log->uname); ?>
                                        on <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $fs_rate_log->changed_at))); ?>)</span>
                                    <?php endif; ?>
                                    &middot; a customer's bill includes the tiered handling fee on their total (added at billing time).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script>
        window.FS_RATE = <?php echo (float) ($core->exchange_rate ?: 1); ?>;
        window.FS_CID = <?php echo $fs_cid; ?>; // page mode: auto-load this consolidation
    </script>
    <script src="<?= cdp_asset('dataJs/financial_sheet.js') ?>"></script>
</body>

</html>
