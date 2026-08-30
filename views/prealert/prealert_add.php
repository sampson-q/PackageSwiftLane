<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************
//
// Create Pre-Alert. Laid out like every other create form in the system
// (page-breadcrumb → container-fluid → cards with card-title + hr → action
// bar), instead of the old centred marketing-style page. Field names and
// element ids are unchanged so dataJs/pre_alert_add.js keeps working.

$userData = $user->cdp_getUserData();


?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Meta Description (for search results) -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Author (content owner) -->
    <meta name="author" content="CODDINGPRO">
    <!-- Keywords (related keywords) -->
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph Meta (for social media sharing, like Facebook) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title> <?php echo $lang['left55'] ?> | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <style type="text/css">
        .custom-file-input.is-invalid {
            border-color: #dc3545;
        }
        /* Attachment drop area — same visual language as the other upload
           controls in the system (dashed outline, muted body, single action). */
        .pa-drop {
            border: 2px dashed #d7dce5;
            border-radius: .5rem;
            padding: 26px 18px;
            text-align: center;
            background: #fbfcfe;
            transition: border-color .15s ease, background .15s ease;
        }
        .pa-drop:hover { border-color: #f62d51; background: #fff; }
        .pa-drop__ico { font-size: 34px; color: #a7b0c0; line-height: 1; }
        .pa-drop__hint { font-size: .78rem; color: #8a94a6; margin-top: 6px; }
        .pa-required { color: #f62d51; }
    </style>
</head>

<body>
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->


    <?php include 'views/inc/preloader.php'; ?>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>
        <?php $courierrow = $core->cdp_getCouriercom(); ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><i class="mdi mdi-bell-outline" aria-hidden="true"></i> <?php echo $lang['left53'] ?></h4>
                        <span class="text-muted"><?php echo $lang['left57'] ?></span>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" accept-charset="utf-8" name="form_prealert" id="form_prealert" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="container-fluid">

                    <div id="resultados_ajax"></div>

                    <div class="row">

                        <!-- ── Purchase details ───────────────────────────── -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-information-outline" style="color:#20c997"></i> <?php echo $lang['left55'] ?></h4>
                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tracking_prealert" class="control-label col-form-label"><?php echo $lang['left63'] ?> <span class="pa-required">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="ti-package"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control add-listing_form required" name="tracking_prealert" id="tracking_prealert" placeholder="<?php echo $lang['left63'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="courier_prealert" class="control-label col-form-label"><?php echo $lang['add-title18'] ?> <span class="pa-required">*</span></label>
                                                <select class="form-control custom-select" name="courier_prealert" id="courier_prealert">
                                                    <option value="">--<?php echo $lang['left62'] ?>--</option>
                                                    <?php foreach ($courierrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>"><?php echo $row->name_com; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="provider_prealert" class="control-label col-form-label"><?php echo $lang['left64'] ?> <span class="pa-required">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="ti-shopping-cart"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control add-listing_form required" name="provider_prealert" id="provider_prealert" placeholder="<?php echo $lang['left65'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="price_prealert" class="control-label col-form-label"><?php echo $lang['left66'] ?> (<?php echo $core->currency; ?>) <span class="pa-required">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input type="text" onkeypress="return cdp_soloNumeros(event)" class="form-control add-listing_form required" name="price_prealert" id="price_prealert" placeholder="<?php echo $lang['left67'] ?>">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">.00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="date_prealert" class="control-label col-form-label"><?php echo $lang['add-title15'] ?> <span class="pa-required">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend" data-target="#date_prealert" data-toggle="datetimepicker">
                                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control" name="date_prealert" id="date_prealert" placeholder="--<?php echo $lang['left206'] ?>--" data-toggle="tooltip" data-placement="bottom" title="<?php echo $lang['add-title16'] ?>" readonly value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 nondoc">
                                            <div class="form-group">
                                                <label for="description_prealert" class="control-label col-form-label"><?php echo $lang['left68'] ?> <span class="pa-required">*</span></label>
                                                <textarea class="form-control" rows="3" name="description_prealert" id="description_prealert" placeholder="<?php echo $lang['left69'] ?>"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- ── Purchase invoice ───────────────────────────── -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-paperclip" style="color:#20c997"></i> <?php echo $lang['leftorder01215'] ?></h4>
                                    <hr>

                                    <div class="resultados_file"></div>

                                    <div class="form-group mb-0">
                                        <label class="control-label col-form-label" id="selectItem"><?php echo $lang['messagesform40'] ?> <span class="pa-required">*</span></label>

                                        <input class="custom-file-input" id="file_invoice" name="file_invoice" type="file" style="display: none;" onchange="cdp_validateZiseFiles();" accept="image/*,.pdf" />

                                        <div class="pa-drop mt-2" id="openMultiFile" role="button" tabindex="0">
                                            <div class="pa-drop__ico"><iconify-icon icon="solar:cloud-upload-linear"></iconify-icon></div>
                                            <div class="mt-2"><b><?php echo $lang['leftorder01215'] ?></b></div>
                                            <div class="pa-drop__hint">JPG, PNG or PDF &middot; max 5 MB</div>
                                        </div>

                                        <div id="clean_files" class="hide mt-2">
                                            <button type="button" id="clean_file_button" class="btn btn-danger btn-sm btn-block"><i class="fa fa-trash"></i> <?php echo $lang['leftorder17'] ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ── Action bar ─────────────────────────────────────── -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-right">
                                    <a href="prealert_list.php" class="btn btn-secondary btn-confirmation"><i class="ti-share-alt"></i> <?php echo $lang['global-buttons-3'] ?></a>
                                    <button type="submit" name="create_prealert" id="create_prealert" class="btn btn-danger btn-confirmation ml-2"><i class="mdi mdi-bell mr-1"></i> <?php echo $lang['left70'] ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>

            <?php include 'views/inc/footer.php'; ?>
        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="<?= cdp_asset('dataJs/pre_alert_add.js') ?>" type="text/javascript"></script>


</body>

</html>
