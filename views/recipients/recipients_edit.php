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

require_once('helpers/querys.php');

if (isset($_GET['recipient'])) {
    $data = cdp_getRecipientEdit($_GET['recipient']);
}

if (!isset($_GET['recipient']) or $data['rowCount'] != 1) {
    cdp_redirect_to("recipients_list.php");
}

$row_recipient = $data['data'];

$db->cdp_query("SELECT * FROM cdb_recipients_addresses WHERE recipient_id='" . $_GET['recipient'] . "'");
$recipient_addreses = $db->cdp_registros();



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
    <title><?php echo $lang['edit_recipient'] ?> | <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

    <?php include 'views/inc/head_scripts.php'; ?>

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

                <div class="row">
                    <!-- Column -->
                    <div class="col-lg-12 col-xlg-12 col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['edit_recipient']; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

                                <div id="resultados_ajax"></div>
                                <form class="form-horizontal form-material" id="save_user" name="save_user" method="post">
                                    <section>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['user_manage6'] ?></label>
                                                    <input type="text" class="form-control" name="fname" id="fname" placeholder="<?php echo $lang['user_manage6'] ?>" value="<?php echo $row_recipient->fname; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage7'] ?></label>
                                                    <input type="text" class="form-control" name="lname" id="lname" placeholder="<?php echo $lang['user_manage7'] ?>" value="<?php echo $row_recipient->lname; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="emailAddress1"><?php echo $lang['user_manage5'] ?></label>
                                                    <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo $lang['user_manage5'] ?>" value="<?php echo $row_recipient->email; ?>">
                                                    <!-- Campo oculto para almacenar el correo electrónico original -->
                                                    <input type="hidden" id="original_email" value="<?php echo $row_recipient->email; ?>">
                                                </div>
                                            </div>



                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phoneNumber1"><?php echo $lang['user_manage9'] ?></label>
                                                    <input type="tel" class="form-control" name="phone_custom" id="phone_custom" value="<?php echo $row_recipient->phone; ?>">

                                                    <span id="valid-msg" class="hide"></span>
                                                    <div id="error-msg" class="hide text-danger"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <br>
                                        <hr>
                                        <h4><?php echo $lang['leftorder176'] ?></h4>
                                        <br>

                                        <?php

                                        $count = 0;

                                        foreach ($recipient_addreses as $rowAddress) {
                                            $count++;

                                            $db->cdp_query("SELECT * FROM cdb_countries where id= '" . $rowAddress->country . "'");
                                            $country = $db->cdp_registro();

                                            $db->cdp_query("SELECT * FROM cdb_states where id= '" . $rowAddress->state . "'");
                                            $state = $db->cdp_registro();

                                            $db->cdp_query("SELECT * FROM cdb_cities where id= '" . $rowAddress->city . "'");
                                            $city = $db->cdp_registro();

                                        ?>
                                            <div id="div_parent_<?php echo $count; ?>">

                                                <?php if ($count > 1) {
                                                    echo '<hr>';
                                                } ?>

                                                <h4><?php echo $lang['laddress'];
                                                    echo ' ' . $count; ?> </h4>

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="control-label col-form-label"><?php echo $lang['leftorder318'] ?></label>
                                                            <select class="select2 form-control custom-select" name="country[]" id="country<?php echo $count; ?>">
                                                                <option value="<?php echo $country->id; ?>"><?php echo $country->name; ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="control-label col-form-label"><?php echo $lang['leftorder319'] ?></label>
                                                            <select class="select2 form-control custom-select" id="state<?php echo $count; ?>" name="state[]">
                                                                <option value="<?php echo $state->id; ?>"><?php echo $state->name; ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="control-label col-form-label"><?php echo $lang['leftorder320'] ?></label>
                                                            <select class="select2 form-control custom-select" id="city<?php echo $count; ?>" name="city[]">
                                                                <option value="<?php echo $city->id; ?>"><?php echo $city->name; ?></option>

                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage14'] ?></label>
                                                            <input type="text" class="form-control" value="<?php echo $rowAddress->zip_code; ?>" name="postal[]" id="postal<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage14'] ?>">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage10'] ?></label>
                                                            <input type="text" class="form-control" value="<?php echo $rowAddress->address; ?>" name="address[]" id="address<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage10'] ?>">
                                                        </div>
                                                    </div>

                                                    <input type="hidden" name="address_id[]" id="address_id<?php echo $count; ?>" value="<?php echo $rowAddress->id_addresses; ?>" />


                                                    <?php

                                                    if ($count > 1) { ?>
                                                        <div class="col-md-4">
                                                            <label> &nbsp;</label>
                                                            <div class="form-group">
                                                                <button type="button" name="remove_row" id="<?php echo $count; ?>" class="btn btn-danger remove_row">
                                                                    <span class="fa fa-trash"></span> <?php echo $lang['delete_address_recepient'] ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>

                                            </div>
                                        <?php
                                        }

                                        ?>

                                        <input type="hidden" name="total_address" id="total_address" value="<?php echo $count; ?>" />
                                        <input type="hidden" name="phone" id="phone" value="<?php echo $row_recipient->phone; ?>" />

                                        <input type="hidden" name="recipient_id" id="recipient_id" value="<?php echo $row_recipient->id; ?>" />

                                        <div id="div_address_multiple"></div>

                                        <div align="left">
                                            <button type="button" name="add_row" id="add_row" class="btn btn-success mb-2"><span class="fa fa-plus"></span> <?php echo $lang['add_address_recepient'] ?></button>
                                        </div>
                                    </section>
                                    <br>
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" id="save_data" name="save_data" type="submit"><?php echo $lang['edit_recipient'] ?><span><i class="icon-ok"></i></span></button>
                                            <a href="recipients_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['user_manage30'] ?></a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Column -->
                </div>

                <input type="hidden" name="count_address" id="count_address" value="<?php echo $count; ?>" />
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
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <script src="dataJs/recipients_edit.js"></script>


</body>

</html>