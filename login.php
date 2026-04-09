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
        <link rel="stylesheet" href="https://unicons.iconscout.com/release/v3.0.6/css/line.css">
        <!-- Main Css -->
        <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
        <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">
        <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    </head>

    <body>
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
            <a href="" class="back-button btn btn-icon btn-primary"><i data-feather="arrow-left" class="icons"></i></a>
        </div>

        <!-- Hero Start -->
        <section class="cover-user bg-white">
            <div class="container-fluid px-0">
                <div class="row position-relative">
                    <div class="col-lg-5 cover-my-30 order-2">
                        <div class="cover-user-img d-flex align-items-center">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card login-page border-0" style="z-index: 1">
                                        <div class="card-title text-center row">
                                            <a class="logo" href="index.php">
                                                <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                                            </a>
                                            
                                            <div class="col-3">
                                                <a href="tracking.php" class="btn btn-light"><iconify-icon icon="flowbite:tracking-solid" class="fs-5" style="color:grey"></iconify-icon> <?php echo $lang['langs_06'] ?></a>
                                            </div>
                                            <div class="col-3" href="index.php">
                                                <a href="https://swiftglobalhq.com/" target="_blank" class="btn btn-light"><iconify-icon icon="arcticons:emoji-web" class="fs-5" style="color:grey"></iconify-icon> <?php echo 'Website'?></a>
                                            </div>
                                            <div class="col-3">
                                                <a href="https://swiftglobalhq.com/authorization-shipping-form/" target="_blank" class="btn btn-light"><iconify-icon icon="clarity:form-line" class="fs-5" style="color:grey"></iconify-icon> <?php echo 'Auth. Form'; ?></a>
                                            </div>
                                            <div class="col-3" href="index.php">
                                                <a href="sign-up.php" class="btn btn-light"><iconify-icon icon="mdi:user-add" class="fs-5" style="color:grey"></iconify-icon> <?php echo 'Register'?></a>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="card-body p-0">

                                            <div id="msgholder2">
                                                <?php
                                                if (isset($login)) {
                                                    if ($login->errors) {
                                                ?>
                                                        <div class="alert alert-danger" id="success-alert">
                                                            <p><span class="icon-minus-sign"></span>
                                                                <i class="close icon-remove-circle"></i>
                                                                <span>Error!</span>
                                                                <?php
                                                                foreach ($login->errors as $error) {
                                                                    echo $error;
                                                                } ?>
                                                            </p>
                                                        </div>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <div id="loader" style="display:none"></div>
                                            <form class="login-form mt-4" method="post" name="login_form" id="login-form">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="mb-3">
                                                            <label class="form-label"><?php echo 'Swift ' . $lang['left115'] ?> <span class="text-danger">*</span></label>
                                                            <div class="form-icon position-relative">
                                                                <i data-feather="mail" class="fea icon-sm icons"></i>
                                                                <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['left116'] . ' / Email' ?>" name="username" id="username" required="">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!--end col-->

                                                    <div class="col-lg-12">
                                                        <div class="mb-3">
                                                            <label class="form-label"><?php echo $lang['left117'] ?> <span class="text-danger">*</span></label>
                                                            <div class="form-icon position-relative">
                                                                <i data-feather="shield" class="fea icon-sm icons"></i>
                                                                <input type="password" class="form-control ps-5" placeholder="<?php echo $lang['left118'] ?>" name="password" id="password" required="">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!--end col-->

                                                    <div class="col-lg-12">
                                                        <div class="d-flex justify-content-between">
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="flexCheckDefault">
                                                                    <label class="form-check-label" for="flexCheckDefault"><?php echo $lang['left120'] ?></label>
                                                                </div>
                                                            </div>
                                                            <p class="forgot-pass mb-0"><a href="forgot-password.php" class="text-dark fw-bold"><?php echo $lang['left119'] ?></a></p>
                                                        </div>
                                                    </div>
                                                    <!--end col-->

                                                    <div class="col-lg-12 mb-0">
                                                        <div class="d-grid">
                                                            <button class="btn btn-grad"><?php echo $lang['left121'] ?></button>
                                                            <input name="login" type="hidden" value="1" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--end row-->
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!--end col-->

                            </div>
                            <!--end row-->
                        </div> <!-- end about detail -->
                    </div> <!-- end col -->

                    <div class="col-lg-7 offset-lg-5 padding-less img order-1" data-jarallax='{"speed": 0.5}'>
                        <img src="assets/images/Login.svg" width="1000px" class="img-fluid" alt="">
                    </div>
                </div>
                <!--end row-->
            </div>
            <!--end container fluid-->
        </section>
        <!--end section-->
        <!-- Hero End -->

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