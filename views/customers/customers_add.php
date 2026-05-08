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


// Permiso de acceso al formulario de clientes:
// - Admin/empleado con permiso add_client
// - Agencia (userlevel 6) siempre puede crear clientes propios
$userData = $user->cdp_getUserData();
if (($userData->userlevel ?? 0) != 6 && !$user->cdp_hasPermission('add_client')) {
    header("location: error403.php");
    exit;
}

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
    <title><?php echo $lang['filter5'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

    <?php include 'views/inc/head_scripts.php'; ?>

    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">


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

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="container-fluid">
                <div class="row justify-content-center">
                    <!-- Column -->
                    <div class="col-lg-12 col-xlg-12 col-md-12">
                        <div class="card">

                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['filter5']; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

                                <div id="resultados_ajax"></div>
                                <form class="form-horizontal form-material" id="save_user" name="save_user" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <section>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="firstName1"><?php echo $lang['user_manage3'] ?></label>
                                                    <input type="text" class="form-control" id="username" name="username" placeholder="<?php echo $lang['user_manage3'] ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="lastName1"><?php echo $lang['user_manage32'] ?></label>
                                                    <input type="text" class="form-control" name="password" id="password" placeholder="<?php echo $lang['user_manage32'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['leftorder164'] ?></label>
                                                    <select class="custom-select form-control" id="document_type" name="document_type">
                                                        <option value="DNI"><?php echo $lang['leftorder165'] ?></option>
                                                        <option value="RIC"><?php echo $lang['leftorder166'] ?></option>
                                                        <option value="CI"><?php echo $lang['leftorder167'] ?></option>
                                                        <option value="CIE"><?php echo $lang['leftorder168'] ?></option>
                                                        <option value="CIN"><?php echo $lang['leftorder169'] ?></option>
                                                        <option value="CIE"><?php echo $lang['leftorder170'] ?></option>
                                                        <option value="CC"><?php echo $lang['leftorder171'] ?></option>
                                                        <option value="TI"><?php echo $lang['leftorder172'] ?></option>
                                                        <option value="CE"><?php echo $lang['leftorder173'] ?></option>
                                                        <option value="PSP"><?php echo $lang['leftorder174'] ?></option>
                                                        <option value="NIT"><?php echo $lang['leftorder1745'] ?></option>
                                                    </select>
                                                </div>
                                            </div>


                                            <div class="col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['leftorder175'] ?></label>
                                                    <input type="text" class="form-control" id="document_number" name="document_number" placeholder="<?php echo $lang['leftorder175'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['user_manage6'] ?></label>
                                                    <input type="text" class="form-control" name="fname" id="fname" placeholder="<?php echo $lang['user_manage6'] ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage7'] ?></label>
                                                    <input type="text" class="form-control" name="lname" id="lname" placeholder="<?php echo $lang['user_manage7'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['user_manage5'] ?></label>
                                                    <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo $lang['user_manage5'] ?>">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage9'] ?></label>
                                                    <input type="tel" class="form-control" name="phone_custom" id="phone_custom" placeholder="<?php echo $lang['user_manage9'] ?>">

                                                    <span id="valid-msg" class="hide"></span>
                                                    <div id="error-msg" class="hide text-danger"></div>
                                                </div>
                                            </div>


                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage11'] ?></label>
                                                    <select class="custom-select form-control" name="gender" id="gender" placeholder="<?php echo $lang['user_manage11'] ?>">
                                                        <option value=""><?php echo $lang['leftorder177'] ?></option>
                                                        <option value="Male"><?php echo $lang['leftorder178'] ?></option>
                                                        <option value="Female"><?php echo $lang['leftorder179'] ?></option>
                                                        <option value="Other"><?php echo $lang['leftorder180'] ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>
                                        <h4><?php echo $lang['leftorder176'] ?></h4>
                                        <br>

                                        <h4><?php echo $lang['laddress'] ?> 1 </h4>

                                        <div class="row">

                                            <div class="col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="control-label col-form-label"><?php echo $lang['leftorder318'] ?></label>
                                                    <select class="select2 form-control custom-select" name="country[]" id="country1">
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="control-label col-form-label"><?php echo $lang['leftorder319'] ?></label>
                                                    <select class="select2 form-control custom-select" id="state1" name="state[]">
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="control-label col-form-label"><?php echo $lang['leftorder320'] ?></label>
                                                    <select class="select2 form-control custom-select" id="city1" name="city[]">
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage14'] ?></label>
                                                    <input type="text" class="form-control form-control-sm" name="postal[]" id="postal1" placeholder="<?php echo $lang['user_manage14'] ?>">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage10'] ?></label>
                                                    <input type="text" class="form-control form-control-sm" name="address[]" id="address1" placeholder="<?php echo $lang['user_manage10'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <input type="hidden" name="total_address" id="total_address" value="1" />
                                        <input type="hidden" name="phone" id="phone" />

                                        <div id="div_address_multiple"></div>

                                        <div align="left">
                                            <button type="button" name="add_row" id="add_row" class="btn btn-success mb-2"><span class="fa fa-plus"></span> <?php echo $lang['add_address_recepient'] ?></button>
                                        </div>

                                        <hr>

                                        <div class="row">

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage20'] ?></label>
                                                    <div class="btn-group">
                                                        <label class="btn">
                                                            <div class="custom-control custom-radio">
                                                                <input type="radio" id="customRadio4" class="custom-control-input" name="active" value="1" checked="checked">
                                                                <label class="custom-control-label" for="customRadio4"> <?php echo $lang['user_manage16'] ?></label>
                                                            </div>
                                                        </label>
                                                        <label class="btn">
                                                            <div class="custom-control custom-radio">
                                                                <input type="radio" id="customRadio3" class="custom-control-input" name="active" value="0">
                                                                <label class="custom-control-label" for="customRadio3"> <?php echo $lang['user_manage17'] ?></label>
                                                            </div>
                                                        </label>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage23'] ?></label>
                                                    <div class="btn-group" data-toggle="buttons">
                                                        <label class="btn">
                                                            <div class="custom-control custom-radio">
                                                                <input type="radio" id="customRadio4" name="newsletter" value="1" checked="checked" class="custom-control-input">
                                                                <label class="custom-control-label" for="customRadio4"> <?php echo $lang['user_manage21'] ?></label>
                                                            </div>
                                                        </label>
                                                        <label class="btn">
                                                            <div class="custom-control custom-radio">
                                                                <input type="radio" id="customRadio5" name="newsletter" value="0" class="custom-control-input">
                                                                <label class="custom-control-label" for="customRadio5"> <?php echo $lang['user_manage22'] ?></label>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">


                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" name="notify" id="notify" value="1">
                                                        <i></i><?php echo $lang['edit-clien34'] ?>
                                                        <span class="custom-control-indicator"></span><br><br>
                                                        <label><span><i class="ti-email" style="color:#6610f2"></i>&nbsp; <?php echo $lang['edit-clien35'] ?></span></label>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['user_manage36'] ?></label>
                                                    <textarea class="form-control" id="notes" name="notes" rows="6" name="notes" placeholder="<?php echo $lang['user_manage36'] ?>"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                    </section>
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" id="save_data" name="save_data" type="submit"><?php echo $lang['user_manage37'] ?><span><i class="icon-ok"></i></span></button>
                                            <a href="customers_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['user_manage30'] ?></a>
                                        </div>
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
                                                    <input type="number" class="form-control" name="locker" id="locker" value="<?php print_r(cdp_generarCodigo('' . $core->digit_random_locker . '')); ?>" onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>');">
                                                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>">
                                                </div>
                                            </div>
                                        <?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div> 
        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <?php include 'views/inc/footer.php'; ?>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

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

        input.addEventListener("keypress", function (event) {
          if (event.charCode < 48 || event.charCode > 57) {
            event.preventDefault();
          }
        });

    </script>

    <script src="dataJs/customers_add.js"></script>


</body>

</html>