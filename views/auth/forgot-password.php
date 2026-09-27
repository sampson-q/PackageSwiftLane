<?php
if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; } ?><!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $lang['langs_010106'] ?> | <?php echo $core->site_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="keywords" content="Swiftlane - Integrated Web Shipping System">
    <meta name="author" content="iSolveAfrica Ltd.">
    <meta name="description" content="">
    <!-- favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/materialdesignicons.min.css') ?>" rel="stylesheet" type="text/css" />
    <!-- Main Css -->
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/style.css') ?>" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/colors/default.css') ?>" rel="stylesheet" id="color-opt">
    <?php $cdpAuthPhoto = 'forgot'; include 'views/inc/auth_head.php'; ?>

    <script type="text/javascript" src="<?= cdp_asset('assets/js/jquery.js') ?>"></script>
    <script type="text/javascript" src="<?= cdp_asset('assets/js/jquery-ui.js') ?>"></script>
    <script src="<?= cdp_asset('assets/js/jquery.ui.touch-punch.js') ?>"></script>
    <script src="<?= cdp_asset('assets/js/jquery.wysiwyg.js') ?>"></script>
    <script src="<?= cdp_asset('assets/js/global.js') ?>"></script>
    <script src="<?= cdp_asset('assets/js/custom.js') ?>"></script>
    <script src="<?= cdp_asset('assets/js/checkbox.js') ?>"></script>

</head>

<body class="auth-page">

    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner">
                <div class="double-bounce1"></div>
                <div class="double-bounce2"></div>
            </div>
        </div>
    </div>
    <!-- Loader -->

    <?php cdp_authFrameOpen($core, [
        'photo' => 'forgot',
        'badge' => 'Account Recovery',
        'title' => 'Reset access without starting over.',
        'links' => [['href' => 'login.php', 'label' => 'Sign In', 'icon' => 'log-in', 'primary' => true]],
    ]); ?>

        <div class="auth-card auth-card--compact card auth-card--forgot border-0" style="z-index: 1">
            <div class="auth-card__top text-center">
                <a class="logo" href="index.php">
                    <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                </a>
            </div>
            <div class="card-body">
                <h4 class="card-title text-center"><?php echo $lang['left172'] ?></h4>
                <div id="resultados_ajax"></div>
                <div id="loader" style="display:none"></div>
                <form class="login-form mt-4" name="forgotPassword" id="forgotPassword" method="post">
                    <div class="row">
                        <div class="col-12">
                            <p class="text-muted"><?php echo $lang['message_title_forgot1'] ?></p>
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['lemailad'] ?> <span class="text-danger">*</span></label>
                                <div class="form-icon position-relative">
                                    <i data-feather="mail" class="fea icon-sm icons"></i>
                                    <input type="email" class="form-control ps-5" placeholder="<?php echo $lang['left176'] ?>" id="email" name="email" required="">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" name="dosubmit" class="btn btn-danger"><?php echo $lang['langs_010108'] ?></button>
                            </div>
                        </div>
                        <div class="col-12 text-center auth-footer-links">
                            <a href="sign-up.php" class="text-dark fw-bold"><?php echo $lang['langs_010110'] ?></a>
                            <span class="mx-2 text-muted">|</span>
                            <a href="index.php" class="text-dark fw-bold"><?php echo $lang['langs_010111'] ?></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    <?php cdp_authFrameClose($core); ?>




    <!-- javascript -->
    <script src="<?= cdp_asset('assets/css_main_swiftlane/main_swiftlane/js/jquery.min.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/bootstrap.bundle.min.js') ?>"></script>
    <!-- Icons -->
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/feather.min.js') ?>"></script>
    <!-- Main Js -->
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/plugins.init.js') ?>"></script>
    <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/app.js') ?>"></script>
    <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->

    <script src="<?= cdp_asset('dataJs/forgot_password.js') ?>"></script>


</body>

</html>
