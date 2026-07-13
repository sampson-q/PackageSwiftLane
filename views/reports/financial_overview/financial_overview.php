<?php
// ============================================================================
// Financial Overview — the unifying finance hub. Full summaries across billing,
// receipts, discounts, receivables and deliverables, GHS/USD toggle.
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
    <title>Financial Overview | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" href="assets/template/assets/libs/daterangepicker/daterangepicker.css">
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-7 align-self-center">
                        <h4 class="page-title">Financial Overview</h4>
                    </div>
                    <div class="col-5 align-self-center text-right">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Display currency">
                            <button type="button" id="fo_cur_ghs" class="btn btn-dark" onclick="cdpFoSetCurrency('ghs');">GH&#8373;</button>
                            <button type="button" id="fo_cur_usd" class="btn btn-outline-dark" onclick="cdpFoSetCurrency('usd');">$ USD</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><span class="fa fa-calendar"></span></span></div>
                            <input type="text" name="daterange" id="daterange" class="form-control" placeholder="Date range (period tiles)">
                        </div>
                    </div>
                    <div class="col-md-8 text-right">
                        <a href="accounts_receivable.php" class="btn btn-sm btn-outline-secondary">Accounts Receivable</a>
                        <a href="transactions.php" class="btn btn-sm btn-outline-secondary">Transactions</a>
                        <a href="global_payments_gateways.php" class="btn btn-sm btn-outline-secondary">Gateways</a>
                    </div>
                </div>

                <div class="outer_div"></div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="assets/template/assets/libs/moment/moment.min.js"></script>
    <script src="assets/template/assets/libs/daterangepicker/daterangepicker.js"></script>
    <script src="assets/css_main_deprixa/js/apexcharts.min.js"></script>
    <script src="<?= cdp_asset('dataJs/financial_overview.js') ?>"></script>
</body>

</html>
