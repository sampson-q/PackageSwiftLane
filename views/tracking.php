<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['left127'] ?> | <?php echo $core->site_name; ?></title>
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <!-- favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Slider -->
    <link rel="stylesheet" href="assets/css_main_deprixa/css/tiny-slider.css" />
    <!-- Date picker -->
    <link rel="stylesheet" href="assets/css_main_deprixa/css/datepicker.min.css">
    <!-- Main Css -->
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">


</head>

<body class="cover-user">
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

    <!-- Navbar STart -->
    <header id="topnav" class="defaultscroll sticky">
        <div class="container">
            <!-- Logo container-->
            <a class="logo" href="index.php">
                <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>


            </a>

            <!-- End Logo container-->
            <div class="menu-extras">
                <div class="menu-item">
                    <!-- Mobile menu toggle-->
                    <a class="navbar-toggle" id="isToggle" onclick="toggleMenu()">
                        <div class="lines">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </a>
                    <!-- End mobile menu toggle-->
                </div>
            </div>

            <!--Login button Start-->
            <ul class="buy-button list-inline mb-0">
                <li class="list-inline-item mb-0">
                    <a href="index.php">
                        <div class="login-btn-primary"><span class="btn btn-icon btn-pills btn-soft-primary"><i data-feather="home" class="fea icon-sm"></i></span></div>
                        <div class="login-btn-light"><span class="btn btn-icon btn-pills btn-light"><i data-feather="home" class="fea icon-sm"></i></span></div>
                    </a>
                </li>

            </ul>
            <!--Login button End-->

        </div>
        <!--end container-->
    </header>
    <!--end header-->
    <!-- Navbar End -->

    <!-- Hero Start -->
    <section class="bg-half-170 d-table w-100 h-full" style="background: url('assets/images/PackageTracking.svg') center; background-repeat: no-repeat;">
        <div class="bg-overlay"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 col-md-6">
                    <div class="title-heading mt-4">
                        <h1 class="display-4 fw-bold text-white title-dark mb-3"><?php echo $lang['left127'] ?> </span> <br> <?php echo $lang['left128'] ?></h1>

                    </div>
                </div>
                <!--end col-->

                <div class="col-lg-5 col-md-6 mt-4 pt-2 mt-sm-0 pt-sm-0">
                    <div class="card shadow rounded border-0 ms-lg-5">
                        <div class="card-body">

                            <form class="login-form" method="POST" name="ib_form" id="ib_form">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group position-relative">

                                            <div class="col-md-12">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="trackingType" id="trackingType" value="1" checked>
                                                    <label class="form-check-label" for="trackingType"><?php echo $lang['message_title_tracking1'] ?></label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="trackingType" id="trackingType" value="2">
                                                    <label class="form-check-label" for="trackingType"><?php echo $lang['message_title_tracking2'] ?></label>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">

                                            <div class="form-icon position-relative">
                                                <i class="mdi mdi-cube-send ml-3 icons"></i>
                                                <textarea name="order_track" placeholder="<?php echo $lang['left130'] ?>" id="order_track" rows="4" class="form-control ps-5" required></textarea>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-12">
                                        <div class="d-grid">
                                            <button type="submit" name="submit" class="btn btn-danger rounded w-100"><i class="mdi mdi-cube-outline ml-3 icons"></i> <?php echo $lang['left131'] ?></button>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!--end col-->
            </div>
            <!--end row-->
        </div>
        <!--end container-->
    </section>
    <!--end section-->

    <!-- Hero End -->




    <!-- javascript -->
    <script src="assets/css_main_deprixa/main_deprixa/js/jquery.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <!-- SLIDER -->
    <script src="assets/css_main_deprixa/js/tiny-slider.js "></script>
    <!-- Datepicker -->
    <script src="assets/css_main_deprixa/js/datepicker.min.js"></script>
    <!-- Icons -->
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <!-- Main Js -->
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->

    <script src="dataJs/tracking.js"></script>

</body>

</html>
