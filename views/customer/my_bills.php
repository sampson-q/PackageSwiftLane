<?php
// ============================================================================
// My Bills — the customer's own outstanding bills, and paying them by mobile
// money. Data + checkout: ajax/customer/my_bills_ajax.php.
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
    <title>My Bills | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .mb-bill-card { border: 1px solid #e9ecef; border-radius: .5rem; margin-bottom: 1rem; }
        .mb-bill-head { padding: 1rem; cursor: pointer; }
        .mb-bill-head:hover { background: #f8f9fa; }
        .mb-amount { font-size: 1.35rem; font-weight: 600; }
        .mb-owed { color: #f62d51; }
        .mb-settled { color: #26c6da; }
        .mb-pkg-row { padding: .5rem .75rem; border-top: 1px solid #f1f3f5; }
        .mb-pkg-cleared { opacity: .55; }
        .mb-chip { display: inline-block; padding: .15rem .5rem; border-radius: 1rem; font-size: .72rem; font-weight: 600; }
        .mb-chip-paid { background: #e8f8f5; color: #0aa699; }
        .mb-chip-due { background: #fdeaec; color: #f62d51; }
        .mb-total-bar { background: #fff; border: 1px solid #e9ecef; border-radius: .5rem; padding: 1rem 1.25rem; }
        .mb-momo-note { font-size: .8rem; color: #6c757d; }
    </style>
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
                        <h4 class="page-title">My Bills</h4>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="mb-total-bar mb-4 d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <div class="text-muted" style="font-size:.8rem">Total Outstanding</div>
                                <div class="mb-amount mb-owed" id="mb_total_owed">&mdash;</div>
                            </div>
                            <div class="mb-momo-note text-right">
                                <iconify-icon icon="solar:smartphone-2-linear"></iconify-icon>
                                Payments are by <b>Mobile Money</b> only.
                            </div>
                        </div>

                        <div id="mb_gateway_warn"></div>
                        <div id="mb_bills"></div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/my_bills.js') ?>"></script>
</body>

</html>
