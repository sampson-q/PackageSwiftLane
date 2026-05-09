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



if (!$user->cdp_is_Admin())
    cdp_redirect_to("login.php");


$userData = $user->cdp_getUserData();

$db = new Conexion;

$db->cdp_query("SELECT * FROM cdb_info_ship_default where id= '1'");
$infoship = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_category where id= '" . $infoship->logistics_default1 . "'");
$s_logistics = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $infoship->packaging_default2 . "'");
$packaging_box = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $infoship->courier_default3 . "'");
$courier_comp = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $infoship->service_default4 . "'");
$ship_modes = $db->cdp_registro();

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
    <title><?php echo $lang['leftorder300'] ?>| <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

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

        <?php $packrow = $core->cdp_getPack(); ?>
        <?php $moderow = $core->cdp_getShipmode(); ?>
        <?php $courierrow = $core->cdp_getCouriercom(); ?>
        <?php $categories = $core->cdp_getCategories(); ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <!-- Action part -->
            <!-- Button group part -->
            <div class="bg-light">
                <div class="row justify-content-center">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-12">
                                <!-- <div id="loader" style="display:none"></div> -->
                                <div id="resultados_ajax"></div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Action part -->


            <div class="container-fluid mb-4">

                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card">
                            <div class="card-body">

                                 <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['leftorder300'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

                                <form class="form-horizontal form-material" id="save_data" name="save_data" method="post" autocomplete="off">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <section>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label>Client (optional)</label>
                                                    <select style="width: 100% !important;" class="select2 form-control" name="client_id" id="client_id"></select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- País de origen -->
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label><?php echo $lang['leftorder296'] ?></label>
                                                    <select style="width: 100% !important;" class="select2 form-control required" name="country_origin" id="country_origin">
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- País/Estado/Ciudad destino -->
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><?php echo $lang['leftorder293'] ?></label>
                                                    <select style="width: 100% !important;" class="select2 form-control required" name="country_destiny" id="country_destiny">
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><?php echo $lang['leftorder295'] ?></label>
                                                    <select style="width: 100% !important;" class="select2 form-control required" name="state_destinystates" id="state_destinystates" disabled>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><?php echo $lang['leftorder294'] ?></label>
                                                    <select style="width: 100% !important;" class="select2 form-control required" name="city_destinycities" id="city_destinycities" disabled>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Rango inicial -->
                                            <div class="col-md-6">
                                                <label for="initial_range"><?php echo $lang['leftorder297'] ?></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-sort-amount-down"></i></span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        inputmode="decimal"
                                                        step="0.01"
                                                        min="0"
                                                        class="form-control required"
                                                        name="initial_range"
                                                        id="initial_range"
                                                        placeholder="<?php echo $lang['leftorder297'] ?>">
                                                </div>
                                            </div>

                                            <!-- Rango final -->
                                            <div class="col-md-6">
                                                <label for="final_range"><?php echo $lang['leftorder298'] ?></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-sort-amount-up"></i></span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        inputmode="decimal"
                                                        step="0.01"
                                                        min="0"
                                                        class="form-control required"
                                                        name="final_range"
                                                        id="final_range"
                                                        placeholder="<?php echo $lang['leftorder298'] ?>">
                                                </div>
                                            </div>

                                            <!-- Precio tarifa -->
                                            <div class="col-md-6">
                                                <label for="tariff_price"><?php echo $lang['leftorder299'] ?></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        inputmode="decimal"
                                                        step="0.01"
                                                        min="0"
                                                        class="form-control required"
                                                        name="tariff_price"
                                                        id="tariff_price"
                                                        placeholder="<?php echo $lang['leftorder299'] ?>">
                                                </div>
                                            </div>

                                            <!-- Modo de envío: cdb_category (mismo ID que courier order_service_options) -->
                                            <div class="col-md-6">
                                                <label><?php echo $lang['tools-shipmode10']; ?></label>
                                                <select style="width: 100% !important;" class="select2 form-control required" name="ship_mode" id="ship_mode">
                                                    <?php foreach ($categories as $row) : ?>
                                                        <option value="<?php echo (int)$row->id; ?>"><?php echo htmlspecialchars($row->name_item, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Factor volumétrico (requerido por tu validación) -->
                                            <div class="col-md-6">
                                                <label>Volumetric Factor</label>
                                                <input
                                                    type="number"
                                                    inputmode="decimal"
                                                    step="1"
                                                    min="0"
                                                    class="form-control required"
                                                    name="volumetric_percentage"
                                                    id="volumetric_percentage"
                                                    placeholder="5000">
                                            </div>

                                            <!-- Precio por milla (requerido por tu validación) -->
                                            <div class="col-md-6">
                                                <label>Price per Mile</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        inputmode="decimal"
                                                        step="0.01"
                                                        min="0"
                                                        class="form-control required"
                                                        name="price_mile"
                                                        id="price_mile"
                                                        placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <br><br>

                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit">
                                                <?php echo $lang['leftorder301'] ?>
                                                <span><i class="icon-ok"></i></span>
                                            </button>
                                            <a href="shipping_tariffs_list.php" class="btn btn-outline-secondary btn-confirmation">
                                                <span><i class="ti-share-alt"></i></span> <?php echo $lang['global-buttons-3'] ?>
                                            </a>
                                        </div>
                                    </div>
                                </form>


                            </div>
                        </div>
                    </div>
                    <!-- Column -->
                </div>
            </div>

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

    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="dataJs/shipping_tariffs_add.js"></script>  
</body>

</html>