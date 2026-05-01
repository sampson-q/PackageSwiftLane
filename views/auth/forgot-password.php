<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $lang['langs_010106'] ?> | <?php echo $core->site_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <!-- favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Main Css -->
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">
    <link href="assets/css_main_deprixa/css/auth-pages.css" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="assets/js/jquery.js"></script>
    <script type="text/javascript" src="assets/js/jquery-ui.js"></script>
    <script src="assets/js/jquery.ui.touch-punch.js"></script>
    <script src="assets/js/jquery.wysiwyg.js"></script>
    <script src="assets/js/global.js"></script>
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/checkbox.js"></script>

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
        <a href="login.php" class="back-button btn btn-icon btn-primary" aria-label="Back to login"><i data-feather="arrow-left" class="icons"></i></a>
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
                            <span class="auth-badge">Account recovery</span>
                            <h1>Reset access without starting over.</h1>
                            <p>Use the email tied to your account and continue the recovery flow.</p>
                            <div class="auth-mini-list">
                                <span>Email</span>
                                <span>OTP</span>
                                <span>Recovery</span>
                            </div>
                        </div>
                        <img src="assets/images/ForgotPassword.svg" alt="Forgot password illustration" class="auth-visual__image img-fluid">
                    </div>
                </div>

                <div class="col-lg-6 auth-shell__panel auth-shell__panel--form order-2 order-lg-2">
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
                </div>
            </div>
        </div>
    </section>




    <!-- javascript -->
    <script src="assets/css_main_deprixa/main_deprixa/js/jquery.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <!-- Icons -->
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <!-- Main Js -->
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->

    <script src="dataJs/forgot_password.js"></script>


</body>

</html>
