<?php
// ============================================================================
// System Logs — one categorized, searchable timeline of activity from across
// the system (package history, payments, discounts, billing notes, pickup
// aging, notifications, legacy charges). Read-only.
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
    <title>System Logs | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
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
                        <h4 class="page-title"><iconify-icon icon="mdi:history"></iconify-icon> System Logs</h4>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <!-- Category tabs -->
                                <div class="btn-group btn-group-sm flex-wrap mb-3" id="log_cats" role="group">
                                    <?php
                                    $tabs = [
                                        'all'           => 'All',
                                        'package'       => 'Package History',
                                        'payments'      => 'Payments',
                                        'discounts'     => 'Discounts',
                                        'notes'         => 'Billing Notes',
                                        'aging'         => 'Pickup Aging',
                                        'notifications' => 'Notifications',
                                        'charges'       => 'Legacy Charges',
                                    ];
                                    foreach ($tabs as $k => $label): ?>
                                        <button type="button" class="btn btn-outline-primary log-cat<?php echo $k === 'all' ? ' active' : ''; ?>"
                                            data-cat="<?php echo $k; ?>" onclick="cdpLogsSetCat('<?php echo $k; ?>')"><?php echo $label; ?></button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Filters on one row -->
                                <div class="form-row align-items-center mb-3">
                                    <div class="col-md-4 mb-1">
                                        <input type="text" id="search" class="form-control" placeholder="Search actor, action, tracking or reference…" onkeyup="cdpLogsDebounced();">
                                    </div>
                                    <div class="col-md-2 mb-1">
                                        <select class="form-control custom-select" id="per_page" onchange="cdpLogsGo(1);">
                                            <option value="25">25 rows</option>
                                            <option value="50" selected>50 rows</option>
                                            <option value="100">100 rows</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="loader" style="display:none;" class="text-center my-3">
                                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                </div>

                                <div class="table-responsive outer_div"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/system_logs.js') ?>"></script>
</body>

</html>
