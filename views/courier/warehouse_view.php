<?php
$userData = $user->cdp_getUserData();
$statusrow = $core->cdp_getStatus();

$warehouse_status_ids = [2, 4, 6, 10, 12, 13, 19, 21, 23, 25];
$statusrow = array_filter($statusrow, function($row) use ($warehouse_status_ids) {
    return in_array((int)$row->id, $warehouse_status_ids);
});
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo 'Warehouse'; ?> | <?php echo $core->site_name ?></title>
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
                        <h4 class="page-title"><?php echo 'Warehouse'; ?></h4>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div id="resultados_ajax"></div>

                                <div class="row mb-3">

                                    <!-- Search -->
                                    <div class="col-sm-12 col-md-3 mb-2">
                                        <div class="input-group">
                                            <input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
                                            <div class="input-group-append input-sm">
                                                <button type="button" class="btn btn-outline-danger"><i class="fa fa-search"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status filter -->
                                    <div class="col-sm-12 col-md-2 mb-2">
                                        <div class="input-group">
                                            <select onchange="cdp_load(1);" class="form-control custom-select" id="status_courier" name="status_courier">
                                                <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                <?php foreach ($statusrow as $row) : ?>
                                                    <option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Filter by pickup/consolidate -->
                                    <div class="col-sm-12 col-md-2 mb-2">
                                        <div class="input-group">
                                            <select onchange="cdp_load(1);" class="form-control custom-select" id="filterby" name="filterby">
                                                <option value="0"><?php echo $lang['leftorder128'] ?></option>
                                                <option value="1"><?php echo $lang['left1077'] ?></option>
                                                <option value="2"><?php echo $lang['leftorder129'] ?></option>
                                                <option value="3"><?php echo $lang['leftorder130'] ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Date range filter -->
                                    <div class="col-sm-12 col-md-4 mb-2">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <span class="fa fa-calendar"></span>
                                                </span>
                                            </div>
                                            <input type="text" name="daterange-warehouse" id="daterange-warehouse" class="form-control float-right" placeholder="<?php echo $lang['report-text90'] ?>" />
                                            <button type="button" id="btn-clear-daterange" class="btn btn-outline-secondary ml-2" title="Clear period">✕</button>
                                        </div>
                                    </div>

                                </div><!-- /.row filters -->

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="btn-group mt-2 hide" id="div-actions-checked">
                                            <span class="mt-2 mr-4">
                                                <strong><?php echo $lang['global-2'] ?></strong>
                                                <strong id="countChecked"> 0</strong>
                                            </span>
                                            <button class="btn btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <?php echo $lang['global-1'] ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($user->cdp_hasPermission('select_change_status_courier')) { ?>
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalCheckboxStatus">
                                                        <i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['left21550'] ?>
                                                    </a>
                                                <?php } ?>
                                                <?php if ($user->cdp_hasPermission('assign_drivers')) { ?>
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalDriverCheckbox">
                                                        <i style="color:#ff0000" class="fas fa-car"></i>&nbsp;<?php echo $lang['left208'] ?>
                                                    </a>
                                                <?php } ?>
                                                <?php if ($user->cdp_hasPermission('print_label')) { ?>
                                                    <a class="dropdown-item" onclick="cdp_printMultipleLabel();" target="_blank">
                                                        <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div><br></div>
                                <div class="outer_divx"></div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Only the two bulk-action modals are triggered from this page -->
        <?php include('views/modals/modal_update_status_checked.php'); ?>
        <?php include('views/modals/modal_update_driver_checked.php'); ?>

        <?php include 'views/inc/footer.php'; ?>

    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/moment/moment.min.js"></script>
    <script src="assets/template/assets/libs/daterangepicker/daterangepicker.js"></script>
    <script src="dataJs/warehouse.js"></script>

</body>

</html>