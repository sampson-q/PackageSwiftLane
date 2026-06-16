<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet (consolidation -> packages -> items)    *
// *************************************************************************

if ((!$user->cdp_is_Admin() && !$user->userlevel === 3))
    cdp_redirect_to("login.php");

$userData = $user->cdp_getUserData();
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
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <style type="text/css">
        /* Force visibility regardless of the template's .card-header theme rules. */
        /* .fs-consol-header { background:#3e5569 !important; color: #fff !important; cursor:pointer; }
        .fs-consol-header b, .fs-consol-header i, .fs-consol-header span { color: #fff !important; } */
        .fs-consol-header .btn-light, .fs-consol-header .btn-light i { color: #212529 !important; }
        .fs-consol-header .fs-dim { opacity:.85; }
        .fs-pkg-header { background:#eef1f4 !important; color:#222 !important; cursor:pointer; }
        .fs-pkg-header b, .fs-pkg-header i { color:#222 !important; }
        .fs-pkg-caret, .fs-consol-caret { transition: transform .2s; }
        .fs-items-table input:disabled { background: #f3f4f6; }
    </style>
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
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><span class="fa fa-search"></span></span>
                                            </div>
                                            <input type="text" id="fs_search" class="form-control" placeholder="Search by consolidation number…">
                                            <div class="input-group-append">
                                                <button id="fs_search_btn" class="btn btn-secondary" type="button">Search</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <small class="text-muted">Use the <b>PDF</b> button on each consolidation to export it.</small>
                                    </div>
                                </div>

                                <div id="loader" style="display:none;" class="text-center my-3">
                                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                </div>

                                <div class="outer_div mt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="dataJs/financial_sheet.js"></script>
</body>

</html>
