<?php
// ============================================================================
// Warehouse Delivery — deliver packages finance has cleared for delivery,
// grouped by customer. Only cleared packages appear here.
// ============================================================================
$userData = $user->cdp_getUserData();

$db = new Conexion;
$db->cdp_query("SELECT id, mod_style FROM cdb_styles WHERE id IN (1,4,32,33) ORDER BY mod_style");
$db->cdp_execute();
$wh_statuses = $db->cdp_registros() ?: [];
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
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row"><div class="col-12 align-self-center">
                    <h4 class="page-title">Warehouse Delivery</h4>
                    <small class="text-muted">Only packages cleared for delivery by Accounts appear here.</small>
                </div></div>
            </div>

            <div class="container-fluid">
                <div class="card"><div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-5 mb-1">
                            <input type="text" id="search" class="form-control" placeholder="Customer, locker or tracking #…">
                        </div>
                        <div class="col-md-4 mb-1">
                            <select id="status_courier" class="form-control custom-select" onchange="cdp_load(1);">
                                <option value="0">All warehouse statuses</option>
                                <?php foreach ($wh_statuses as $s): ?>
                                    <option value="<?php echo (int) $s->id; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string) $s->mod_style)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="loader" style="display:none;" class="text-center my-3">
                        <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
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
