<?php
// ============================================================================
// Warehouse Delivery — consolidation LIST page (mirrors financial_sheet.php).
// Lists consolidations that have packages cleared for delivery; clicking a card
// opens that consolidation's own delivery page (warehouse_delivery_consolidation
// .php), where the customer -> package -> item accordion and delivery actions
// live. Deliberately a different (green "delivery") skin from the Financial
// Sheet so the two screens are never confused.
// ============================================================================
$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Warehouse Delivery | <?php echo $core->site_name ?></title>
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
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="mdi mdi-package-variant-closed"></i></span></div>
                                <input type="text" id="wd_q_consol" class="form-control" placeholder="Consolidation #" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="mdi mdi-barcode"></i></span></div>
                                <input type="text" id="wd_q_package" class="form-control" placeholder="Package / tracking #" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="mdi mdi-account-search"></i></span></div>
                                <input type="text" id="wd_q_customer" class="form-control" placeholder="Customer name / locker" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="wd-dim mb-2">Filters apply as you type.</div>

                    <div id="loader" style="display:none;" class="text-center my-3">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color:#1b8a5a;"></i>
                    </div>

                    <div class="outer_div"></div>
                </div></div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/warehouse_delivery.js') ?>"></script>
</body>

</html>
