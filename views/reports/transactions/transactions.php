<?php
// ============================================================================
// Transactions dashboard — every money-in event (cash + Paystack/Hubtel) from
// the Financial Sheet ledger, with filters + summary tiles.
// ============================================================================
$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Transactions | <?php echo $core->site_name ?></title>
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
                    <div class="col-5 align-self-center">
                        <h4 class="page-title">Transactions</h4>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <!-- All filters on one row. -->
                                <div class="form-row align-items-center mb-3">
                                    <div class="col-md-3 mb-1">
                                        <div class="input-group">
                                            <div class="input-group-prepend"><span class="input-group-text"><span class="fa fa-calendar"></span></span></div>
                                            <input type="text" name="daterange" id="daterange" class="form-control" placeholder="Date range">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-1">
                                        <input type="text" id="search" class="form-control" placeholder="Reference, customer, locker or tracking…" onkeyup="cdp_txDebounced();">
                                    </div>
                                    <div class="col-md-2 mb-1">
                                        <select class="form-control custom-select" id="f_mode" onchange="cdp_load(1);">
                                            <option value="">All Methods</option>
                                            <option value="cash">Cash</option>
                                            <option value="paystack">Paystack</option>
                                            <option value="hubtel">Hubtel</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-1">
                                        <select class="form-control custom-select" id="f_status" onchange="cdp_load(1);">
                                            <option value="">All Statuses</option>
                                            <option value="manual">Cash / Manual</option>
                                            <option value="success">Confirmed</option>
                                            <option value="pending">Pending</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-1">
                                        <select onchange="cdp_load(1);" class="form-control custom-select" id="per_page">
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="all">All</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-1 text-right">
                                        <div class="btn-group btn-group-sm w-100" role="group" aria-label="Display currency">
                                            <button type="button" id="tx_cur_usd" class="btn btn-dark" onclick="cdpTxSetCurrency('usd');">$</button>
                                            <button type="button" id="tx_cur_ghs" class="btn btn-outline-dark" onclick="cdpTxSetCurrency('ghs');">&#8373;</button>
                                        </div>
                                    </div>
                                </div>

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
    <script src="assets/template/assets/libs/moment/moment.min.js"></script>
    <script src="assets/template/assets/libs/daterangepicker/daterangepicker.js"></script>
    <script src="<?= cdp_asset('dataJs/transactions.js') ?>"></script>
</body>

</html>
