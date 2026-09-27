<?php
if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }
    require_once ("loader.php");
    $login = new User;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['left127'] ?> | <?php echo $core->site_name; ?></title>
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
    <?php $cdpAuthPhoto = 'tracking'; include 'views/inc/auth_head.php'; ?>
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

    <?php cdp_authFrameOpen($core, [
        'photo' => 'tracking',
        'badge' => 'Live Tracking',
        'title' => 'Every parcel, every stage, in one place.',
        'links' => ($login->cdp_loginCheck() ? [['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'grid', 'primary' => true]] : [['href' => 'login.php', 'label' => 'Sign In', 'icon' => 'log-in', 'primary' => true]]),
    ]); ?>

        <div class="auth-card auth-card--compact auth-card--tracking card login-page border-0">
            <div class="auth-card__top text-center">
                <a class="logo" href="index.php">
                    <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                </a>
            </div>

            <div class="card-body">
                <div class="text-center">
                    <h4 class="auth-heading mb-2"><?php echo $lang['left127'] ?></h4>
                    <p class="auth-subtitle"><?php echo $lang['left129'] ?? 'Enter one or more tracking numbers below.' ?></p>
                </div>

                <div id="msgholder2" class="mt-3"></div>
                <div id="loader" style="display:none"></div>

                <form class="login-form mt-4" method="POST" name="ib_form" id="ib_form">
                    <div class="row">
                        <!-- Tracking type selector -->
                        <div class="col-12">
                            <div class="mb-3 d-flex gap-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio"
                                           name="trackingType" id="trackingType1" value="1" checked>
                                    <label class="form-check-label" for="trackingType1">
                                        <?php echo $lang['message_title_tracking2'] ?>
                                    </label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio"
                                           name="trackingType" id="trackingType2" value="2">
                                    <label class="form-check-label" for="trackingType2">
                                        <?php echo $lang['message_title_tracking1'] ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Tracking number(s) input -->
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['left130'] ?></label>
                                <div class="form-icon position-relative">
                                    <i data-feather="package" class="fea icon-sm icons"></i>
                                    <textarea name="order_track" id="order_track"
                                              rows="4" class="form-control ps-5"
                                              placeholder="<?php echo $lang['left130'] ?>"
                                              required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" name="submit" class="btn btn-grad">
                                    <i data-feather="search" class="fea icon-sm me-1"></i>
                                    <?php echo $lang['left131'] ?>
                                </button>
                            </div>
                        </div>

                        <!-- Footer link -->
                        <?php if (!$login->cdp_loginCheck()) { ?>
                        <div class="col-12 text-center auth-footer-links">
                            <a href="login.php" class="text-dark fw-bold">
                                <?php echo $lang['langs_010111'] ?? 'Sign in' ?>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                </form>

                <!-- Tracking result injected here by tracking.js -->
                <div id="tracking_result" class="mt-4"></div>
            </div>
        </div>

    <?php cdp_authFrameClose($core); ?>

    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/feather.min.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/plugins.init.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/app.js') ?>"></script>
    <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->

    <script src="<?= cdp_asset('dataJs/tracking.js') ?>"></script>

</body>

</html>
