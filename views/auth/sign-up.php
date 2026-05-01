<?php

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $lang['langs_010112'] ?> | <?php echo $core->site_name; ?></title>
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

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

    <script type="text/javascript" src="assets/js/jquery.js"></script>
    <script type="text/javascript" src="assets/js/jquery-ui.js"></script>
    <script src="assets/js/jquery.ui.touch-punch.js"></script>
    <script src="assets/js/jquery.wysiwyg.js"></script>
    <script src="assets/js/global.js"></script>
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/checkbox.js"></script>

    <style> 
        .select2-selection__rendered {
            line-height: 31px !important;
        }

        .select2-container .select2-selection--single {
            height: 35px !important;
        }

        .select2-selection__arrow {
            height: 34px !important;
        }
    </style>

</head>

<body class="auth-page">
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

    

    <section class="auth-shell">
        <div class="container-fluid px-0">
            <div class="row g-0 auth-shell__grid">
                <div class="col-lg-5 auth-shell__panel auth-shell__panel--visual order-1 order-lg-1">
                    <div class="auth-visual d-flex flex-column justify-content-center h-100">
                        <a class="auth-mobile-logo auth-brand" href="index.php">
                            <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                        </a>
                        <div class="auth-visual-copy">
                            <span class="auth-badge">Create account</span>
                            <h1>Set up your profile once, move faster later.</h1>
                            <p>Enter the essentials, add your contact details, and finish registration in a clean guided flow.</p>
                            <div class="auth-mini-list">
                                <span>Profile</span>
                                <span>Contact</span>
                                <span>Security</span>
                            </div>
                        </div>
                        <img src="assets/images/Registration.svg" class="auth-visual__image img-fluid" alt="Registration illustration">
                    </div>
                </div>

                <div class="col-lg-7 auth-shell__panel auth-shell__panel--form order-2 order-lg-2">
                    <div class="auth-card auth-card--signup card border-0" style="z-index: 1">
                        <div class="auth-card__top text-center">
                            <a class="logo" href="index.php">
                                <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                            </a>
                            <h4 class="auth-heading mb-2"><?php echo $lang['left136'] ?></h4>
                            <p class="auth-subtitle">Create your account and complete the onboarding flow.</p>
                        </div>

                        <div class="card-body">
                            <div id="resultados_ajax"></div>

                            <?php if (!$core->reg_allowed) : ?>
                                <div class="alert alert-warning" id="success-alert">
                                    <p class="mb-0"><?php echo $lang['langs_010133']; ?></p>
                                </div>
                            <?php else : ?>
                                <form class="login-form mt-3" id="new_register" name="new_register" method="post" enctype="multipart/form-data">
                                    <div class="auth-form-section">
                                        <div class="auth-section-title">Identity</div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left139'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="user" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['left139'] ?>" name="fname" id="fname">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left140'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="user" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['left141'] ?>" name="lname" id="lname">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left144'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="users" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['left145'] ?>" name="username" id="username">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auth-form-section">
                                        <div class="auth-section-title">Contact</div>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left142'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="mail" class="fea icon-sm icons"></i>
                                                        <input type="email" class="form-control ps-5" placeholder="<?php echo $lang['left143'] ?>" name="email" id="email">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="phone_custom" class="form-label"><?php echo $lang['user_manage9'] ?> <span class="text-danger">*</span></label>
                                                    <div class="position-relative">
                                                        <input type="tel" class="form-control iti__tel-input ps-5" name="phone_custom" id="phone_custom" autocomplete="off" data-intl-tel-input-id="0" placeholder="<?php echo $lang['user_manage9'] ?>">
                                                    </div>
                                                </div>
                                                <span id="valid-msg" class="hide"></span>
                                                <div id="error-msg" class="hide text-danger"></div>
                                            </div>
                                            <input type="hidden" name="phone" id="phone" />
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['user_manage14'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="flag" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['user_manage14'] ?>" name="postal" id="postal">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['user_manage10'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="map-pin" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" placeholder="<?php echo $lang['user_manage10'] ?>" name="address" id="address">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auth-form-section">
                                        <div class="auth-section-title">Document</div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['leftorder164'] ?></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="list" class="fea icon-sm icons"></i>
                                                        <select class="custom-select form-control ps-5" id="document_type" name="document_type">
                                                            <option value="PSP"><?php echo $lang['leftorder174'] ?></option>
                                                            <option value="ECW"><?php echo $lang['leftorder1746'] ?></option>
                                                            <option value="DNI"><?php echo $lang['leftorder165'] ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['leftorder175'] ?></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="more-horizontal" class="fea icon-sm icons"></i>
                                                        <input type="text" class="form-control ps-5" id="document_number" name="document_number" placeholder="<?php echo $lang['leftorder175'] ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['translate_search_address_country'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-group">
                                                        <select style="height: 45px !important;" class="select2 form-control ps-5" name="country" id="country"></select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['translate_search_address_state'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-group">
                                                        <select style="width: 100% !important;" disabled class="select2 form-control ps-5" id="state" name="state"></select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['translate_search_address_city'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-group">
                                                        <select style="width: 100% !important;" disabled class="select2 form-control ps-5" id="city" name="city"></select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['leftorder332'] ?></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="image" class="fea icon-sm icons"></i>
                                                        <input type="file" class="form-control ps-5" name="avatar" id="avatar" accept="image/*">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['leftorder333'] ?></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="image" class="fea icon-sm icons"></i>
                                                        <input type="file" class="form-control ps-5" name="document_photo" id="document_photo" accept="image/*">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auth-form-section">
                                        <div class="auth-section-title">Security</div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left146'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="key" class="fea icon-sm icons"></i>
                                                        <input type="password" class="form-control ps-5" placeholder="<?php echo $lang['left147'] ?>" name="pass" id="pass">
                                                    </div>
                                                    <div id="password-strength-meter"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['left148'] ?> <span class="text-danger">*</span></label>
                                                    <div class="form-icon position-relative">
                                                        <i data-feather="key" class="fea icon-sm icons"></i>
                                                        <input type="password" class="form-control ps-5" name="pass2" id="pass2" placeholder="<?php echo $lang['left149'] ?>">
                                                    </div>
                                                    <div id="passwordMatch" class="text-danger"></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="terms" name="terms" value="yes">
                                                    <label class="form-check-label" for="terms"><?php echo $lang['left164'] ?> <a href="terms.php" class="text-primary"><?php echo $lang['left165'] ?></a></label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auth-form-section">
                                        <div class="d-grid">
                                            <button class="btn btn-grad-register" name="dosubmit"><?php echo $lang['left166'] ?></button>
                                        </div>

                                        <?php
                                        if ($core->code_number_locker == 1) {
                                        ?>
                                            <div class="form-group col-md-6" style="display:none;">
                                                <label for="inputcom" class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
                                                <div class="input-group mb-3">
                                                    <input type="number" class="form-control" name="locker" id="locker" value="<?php echo $lockerauto; ?>" onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>');">
                                                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>">
                                                </div>
                                            </div>
                                        <?php } elseif ($core->code_number_locker == 2) {
                                        ?>
                                            <div class="form-group col-md-6" style="display:none;">
                                                <label for="inputcom" class="control-label col-form-label"><?php echo $lang['leftorder14442'] ?></label>
                                                <div class="input-group mb-3">
                                                    <input type="number" class="form-control" name="locker" id="locker" value="<?php echo str_pad((string) random_int(0, (int) pow(10, max(1, (int) $core->digit_random_locker)) - 1), max(1, (int) $core->digit_random_locker), '0', STR_PAD_LEFT); ?>" onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>');">
                                                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>">
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div class="auth-footer-links text-center">
                                            <small class="text-dark me-2"><?php echo $lang['left167'] ?></small>
                                            <a href="index.php" class="text-dark fw-bold"><?php echo $lang['left168'] ?></a>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <?php include('helpers/languages/translate_to_js.php'); ?>

    <!-- javascript -->
    <script src="assets/css_main_deprixa/main_deprixa/js/jquery.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <!-- Icons -->
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <!-- Main Js -->
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    

    <script> 
        function cdp_validateLockerNumber(value, lockDigits) {
          cdp_convertStrPad(value, lockDigits);

          $.ajax({
            type: "POST",
            dataType: "json",
            url: "./ajax/validate_locker_virtual.php?track=" + value,
            success: function (data) {
              var main = $("#order_no_main").val();

              if (data) {
                alert(message_error_exist_locker);
                $("#digitslockers").val(main);
              }
            },
          });
        }

        function cdp_convertStrPad(value, dbDigits) {
          var pad = value.padStart(dbDigits, "0");

          $("#digitslockers").val(pad);
        }

        var input = document.getElementById("digitslockers");


    </script>

    <script src="dataJs/sign-up.js"></script>

</body>

</html>
