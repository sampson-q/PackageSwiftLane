<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet (consolidation list + search)            *
// * Hierarchy: consolidation -> customer -> packages -> items              *
// * Each consolidation opens in its own page (financial_sheet_consolidation)*
// *************************************************************************

if ((!$user->cdp_is_Admin() && (int) ($user->userlevel ?? 0) !== 3)) {
    cdp_redirect_to("login.php");
}

$userData = $user->cdp_getUserData();

// Last exchange-rate change (audit) — shown next to the rate hint.
$fs_rate_log = null;
try {
    $fs_db = new Conexion;
    $fs_db->cdp_query("SELECT l.new_rate, l.changed_at,
                              COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                       u.username, CONCAT('User ', l.changed_by)) AS uname
                       FROM cdb_exchange_rate_log l
                       LEFT JOIN cdb_users u ON u.id = l.changed_by
                       ORDER BY l.id DESC LIMIT 1");
    $fs_db->cdp_execute();
    $fs_rate_log = $fs_db->cdp_registro();
} catch (Throwable $e) {
    // Rate-log table missing (financial_sheet_v2.sql §5 not run).
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Financial Sheet | <?php echo $core->site_name ?></title>
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
                            <h4 class="card-title ml-4 mt-3"><i class="fas fa-file-invoice-dollar"></i> Financial Sheet</h4>

                            <div class="card-body">
                                <div class="row fs-toolbar">
                                    <div class="col-md-3 mb-2">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                                            </div>
                                            <input type="text" id="fs_q_consol" class="form-control" placeholder="Consolidation #" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="mdi mdi-package-variant-closed"></i></span>
                                            </div>
                                            <input type="text" id="fs_q_package" class="form-control" placeholder="Package / tracking #" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="mdi mdi-account-search"></i></span>
                                            </div>
                                            <input type="text" id="fs_q_customer" class="form-control" placeholder="Customer name or locker" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2 text-right">
                                        <div class="btn-group btn-group-sm mb-1" role="group" aria-label="Display currency">
                                            <button type="button" id="fs_cur_ghs" class="btn btn-outline-dark" onclick="fsSetCurrency('ghs');">&#8373;</button>
                                            <button type="button" id="fs_cur_usd" class="btn btn-outline-dark" onclick="fsSetCurrency('usd');">$</button>
                                        </div>
                                        <div class="fs-hint">
                                            Rate: $1 = &#8373;<span id="fs_rate_label"></span>
                                            <?php if ($fs_rate_log): ?>
                                                <span title="Exchange-rate audit log">(updated by <?php echo htmlspecialchars($fs_rate_log->uname); ?>
                                                on <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $fs_rate_log->changed_at))); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div id="loader" style="display:none;" class="text-center my-3">
                                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                </div>

                                <div class="outer_div mt-2"></div>
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
        // Storage stays canonical USD; the per-input $/₵ toggle only controls how
        // an operator-typed custom price is interpreted before conversion to USD.
        window.FS_RATE = <?php echo (float) ($core->exchange_rate ?: 1); ?>;
        window.FS_WEIGHT_RATE = <?php echo json_encode((string) ($core->value_weight ?? '')); ?>;
        window.FS_WEIGHT_UNIT = <?php echo json_encode((string) ($core->weight_p ?? 'lb')); ?>;
        window.FS_CUR_SYMBOL  = <?php echo json_encode((string) ($core->for_symbol ?: '$')); ?>;
    </script>
    <script src="<?= cdp_asset('dataJs/financial_sheet.js') ?>"></script>
</body>

</html>
