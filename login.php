<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************


require_once("loader.php");
require_once("lib/OtpService.php");

$login = new User;
$core = new Core;

if ($login->cdp_loginCheck() == true) {
    header("location: index.php");
}

if (isset($_POST['login'])) {
    $otpService = new OtpService();
    $result = $login->cdp_login($_POST['username'], $_POST['password'], [
        'otp_service' => $otpService,
        'remember_me' => !empty($_POST['remember_me']),
    ]);

    if ($result) {
        if ($result === 'otp_required') {
            header("location: auth-otp.php?flow=login");
        } else {
            header("location: index.php");
        }
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />

        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
        <meta name="author" content="Jaomweb">
        <meta name="description" content="">
        <!-- favicon -->
        <title><?php echo $lang['message_title_login0'] ?> | <?php echo $core->site_name ?></title>
        <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">


        <!-- Bootstrap -->
        <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <!-- Icons -->
        <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
        <!-- Main Css -->
        <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
        <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">
        <link href="assets/css_main_deprixa/css/auth-pages.css" rel="stylesheet" type="text/css" />
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

        <div class="back-to-home">
            <a href="index.php" class="back-button btn btn-icon btn-primary" aria-label="Back to home"><i data-feather="arrow-left" class="icons"></i></a>
        </div>

        <section class="auth-shell">
            <div class="container-fluid px-0">
                <div class="row g-0 auth-shell__grid">
                    <div class="col-lg-6 auth-shell__panel auth-shell__panel--visual order-1 order-lg-1">
                        <div class="auth-visual d-flex flex-column justify-content-center h-100">
                            <a class="auth-mobile-logo auth-brand" href="index.php">
                                <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                            </a>
                            <div class="auth-visual-copy">
                                <span class="auth-badge">SwiftLane access</span>
                                <h1>Move shipments without friction.</h1>
                                <p>Manage pickups, tracking and exceptions from a single workspace built for fast daily operations.</p>
                                <div class="auth-mini-list">
                                    <span>Tracking</span>
                                    <span>Courier ops</span>
                                    <span>Consolidation</span>
                                </div>
                            </div>
                            <img src="assets/images/Login.svg" alt="Login illustration" class="auth-visual__image img-fluid">
                        </div>
                    </div>

                    <div class="col-lg-6 auth-shell__panel auth-shell__panel--form order-2 order-lg-2">
                        <div class="auth-card auth-card--compact card login-page border-0">
                            <div class="auth-card__top text-center">
                                <a class="logo" href="index.php">
                                    <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                                </a>
                            </div>

                            <div class="card-body">
                                <div class="text-center">
                                    <h4 class="auth-heading mb-2"><?php echo $lang['message_title_login0'] ?></h4>
                                    <p class="auth-subtitle">Use your account to continue.</p>
                                </div>

                                <div id="msgholder2" class="mt-4">
                                    <?php
                                    if (isset($login) && $login->errors) {
                                    ?>
                                        <div class="alert alert-danger" id="success-alert">
                                            <p class="mb-0"><span class="icon-minus-sign"></span>
                                                <i class="close icon-remove-circle"></i>
                                                <span>Error!</span>
                                                <?php
                                                foreach ($login->errors as $error) {
                                                    echo $error;
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div id="loader" style="display:none"></div>

                                <form class="login-form mt-4" method="post" name="login_form" id="login-form">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label class="form-label"><?php echo 'Swift ' . $lang['left115'] . ' / Email' ?> <span class="text-danger">*</span></label>
                                                <div class="form-icon position-relative">
                                                    <i data-feather="mail" class="fea icon-sm icons"></i>
                                                    <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['left116'] . ' / Email' ?>" name="username" id="username" required="" autocomplete="username">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label class="form-label"><?php echo $lang['left117'] ?> <span class="text-danger">*</span></label>
                                                <div class="form-icon position-relative">
                                                    <i data-feather="shield" class="fea icon-sm icons"></i>
                                                    <input type="password" class="form-control ps-5" placeholder="<?php echo $lang['left118'] ?>" name="password" id="password" required="" autocomplete="current-password">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="flexCheckDefault">
                                                    <label class="form-check-label" for="flexCheckDefault"><?php echo $lang['left120'] ?></label>
                                                </div>
                                                <p class="forgot-pass mb-0"><a href="forgot-password.php" class="text-dark fw-bold"><?php echo $lang['left119'] ?></a></p>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <div class="d-grid">
                                                <button class="btn btn-grad"><?php echo $lang['left121'] ?></button>
                                                <input name="login" type="hidden" value="1" />
                                            </div>
                                        </div>

                                        <div class="col-12 text-center auth-footer-links">
                                            <a href="tracking.php" class="text-dark fw-bold me-3"><?php echo $lang['langs_06'] ?></a>
                                            <a href="sign-up.php" class="text-dark fw-bold">Register</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- javascript -->
        <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
        <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
        <!-- Icons -->
        <script src="assets/css_main_deprixa/js/feather.min.js"></script>
        <!-- Main Js -->
        <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
        <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
        <script src="assets/css_main_deprixa/js/app.js"></script>
        <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->

    </body>
</html>
