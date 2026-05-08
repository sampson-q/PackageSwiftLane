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



$userData = $user->cdp_getUserData();

if (intval($userData->id) !== intval($_GET['user']) && !$user->cdp_is_Admin()) {
    cdp_redirect_to("login.php");
}

require_once('helpers/querys.php');

if (isset($_GET['user'])) {
    $data = cdp_getUserEdit4bozo($_GET['user']);
}

if (!isset($_GET['user']) or $data['rowCount'] != 1) {
    cdp_redirect_to("users_list.php");
}

$row = $data['data'];

$db->cdp_query("SELECT * FROM cdb_senders_addresses WHERE user_id='" . $_GET['user'] . "'");
$user_addreses = $db->cdp_registros();

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
    <title><?php echo $lang['filter4'] ?> | <?php echo $core->site_name ?></title>

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
                <div class="row">
                    <!-- Column -->
                    <div class="col-lg-4 col-xlg-3 col-md-5">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['filter4']; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>


                                <center class="m-t-30">
                                    <label for="avatarInput">
                                        <img src="assets/<?php echo ($row->avatar) ? $row->avatar : "/uploads/blank.png"; ?>" class="rounded-circle" width="150" />
                                    </label>
                                    <div><br><br></div>

                                    <form class="form-horizontal form-material" id="edit_avatar_form" name="edit_avatar_form" method="post" enctype="multipart/form-data">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group" style="display: none;">
                                                <!-- Este input está oculto y se activa haciendo clic en la imagen -->
                                                <input class="form-control" id="avatarInput" name="avatar" type="file" />
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <button type="submit" class="btn btn-outline-warning btn-confirmation"><?php echo $lang['messageerrorform13'] ?></button>
                                        </div>
                                        <input name="id" id="id" type="hidden" value="<?php echo $row->id; ?>" />
                                    </form>

                                    <h4 class="card-title m-t-10"><?php echo $row->fname; ?> <?php echo $row->lname; ?></h4>
                                    <h6 class="card-subtitle"><span><?php echo $lang['user_manage2'] ?> <i class="icon-double-angle-right"></i></span>
                                        <div class="badge badge-pill badge-light font-16"><span class="ti-user text-warning"></span> <?php echo $row->username; ?></div>
                                    </h6>
                                    <h6 class="card-subtitle"><span><?php echo $lang['user-account21000'] ?> <i class="icon-double-angle-right"></i></span>
                                        <div class="badge badge-pill badge-light font-16"> <?php echo $row->locker; ?></div>
                                    </h6>
                                </center>
                            </div>
                            <div> 
                                <hr>
                            </div>
                            <div class="card-body"> <small class="text-muted"><?php echo $lang['user-account4'] ?> </small>
                                <h6><?php echo $row->email; ?></h6> <small class="text-muted p-t-30 db"><?php echo $lang['user-account8'] ?></small>
                                <h6> <?php echo $row->phone; ?></h6>
                            </div>
                            <div class="card-body row text-center">
                                <div class="col-6 border-right">
                                    <h6><?php echo $row->created; ?></h6>
                                    <span><?php echo $lang['user-account18'] ?></span>
                                </div>
                                <div class="col-6">
                                    <h6><?php echo $row->lastlogin; ?></h6>
                                    <span><?php echo $lang['user-account19'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Column -->
                    <div class="col-lg-8 col-xlg-9 col-md-7">
                        <div class="card">
                            <!-- Tabs -->
                            <ul class="nav nav-pills custom-pills" id="pills-tab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="pills-setting-tab" data-toggle="pill" href="#previous-month" role="tab" aria-controls="pills-setting" aria-selected="false"><span><?php echo $lang['edit-clien2'] ?> <i class="icon-double-angle-right"></i> <?php echo $row->username; ?></span></a>
                                </li>
                            </ul>
                            <!-- Tabs -->
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="previous-month" role="tabpanel" aria-labelledby="pills-setting-tab">
                                    <div class="card-body">

                                        <form enctype="multipart/form-data" class="form-horizontal form-material" id="edit_user" name="edit_user" method="post">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <section>

                                                <?php if ($userData->userlevel == 9) { ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="firstName1"><?php echo $lang['user_manage3'] ?></label>
                                                            <input type="text" class="form-control"  name="username"  value="<?php echo $row->username; ?>" placeholder="<?php echo $lang['user_manage3'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="lastName1"><?php echo $lang['user_manage4'] ?></label>
                                                            <input type="text" class="form-control" id="password" name="password" placeholder="<?php echo $lang['user_manage32'] ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php }  ?>

                                                <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['leftorder164'] ?></label>
                                                            <select class="custom-select form-control" id="document_type" name="document_type" value="<?php echo $row->document_type; ?>">


                                                                <option value="DNI" <?php if ($row->document_type == 'DNI') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder165'] ?></option>

                                                                <option value="RIC" <?php if ($row->document_type == 'RIC') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder166'] ?></option>

                                                                <option value="CI" <?php if ($row->document_type == 'CI') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder167'] ?></option>

                                                                <option value="CIE" <?php if ($row->document_type == 'CIE') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder168'] ?></option>

                                                                <option value="CIN" <?php if ($row->document_type == 'CIN') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder169'] ?></option>

                                                                <option value="CIE" <?php if ($row->document_type == 'CIE') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder170'] ?></option>

                                                                <option value="CC" <?php if ($row->document_type == 'CC') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder171'] ?></option>

                                                                <option value="TI" <?php if ($row->document_type == 'TI') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder172'] ?></option>

                                                                <option value="PSP" <?php if ($row->document_type == 'PSP') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder174'] ?></option>


                                                                <option value="NIT" <?php if ($row->document_type == 'NIT') {
                                                                                        echo 'selected';
                                                                                    } ?>><?php echo $lang['leftorder1745'] ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['leftorder175'] ?></label>
                                                            <input type="text" class="form-control" id="document_number" name="document_number" value="<?php echo $row->document_number; ?>" placeholder="<?php echo $lang['leftorder175'] ?>">
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['user_manage6'] ?></label>
                                                            <input type="text" class="form-control" name="fname" id="fname" value="<?php echo $row->fname; ?>" placeholder="<?php echo $lang['user_manage6'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage7'] ?></label>
                                                            <input type="text" class="form-control" name="lname" id="lname" value="<?php echo $row->lname; ?>" placeholder="<?php echo $lang['user_manage7'] ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                            
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['user_manage5'] ?></label>
                                                            <input type="text" class="form-control" id="email" name="email" value="<?php echo $row->email; ?>" placeholder="<?php echo $lang['user_manage5'] ?>">
                                                        </div>

                                                    </div>
                                                
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage9'] ?></label>
                                                            <input type="text" class="form-control" id="phone_custom" name="phone_custom" value="<?php echo $row->phone; ?>" placeholder="<?php echo $lang['user_manage9'] ?>">
                                                            <span id="valid-msg" class="hide"></span>
                                                            <div id="error-msg" class="hide text-danger"></div>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="row">

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage11'] ?></label>
                                                            <select class="custom-select form-control" id="gender" name="gender" value="<?php echo $row->gender; ?>" placeholder="<?php echo $lang['user_manage11'] ?>">
                                                                <option value="Male" <?php if ($row->gender == 'Male') {
                                                                                            echo 'selected';
                                                                                        } ?>><?php echo $lang['leftorder179'] ?></option>
                                                                <option value="Female" <?php if ($row->gender == 'Female') {
                                                                                            echo 'selected';
                                                                                        } ?>><?php echo $lang['leftorder178'] ?></option>
                                                                <option value="Other" <?php if ($row->gender == 'Other') {
                                                                                            echo 'selected';
                                                                                        } ?>><?php echo $lang['leftorder180'] ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <hr>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="phoneNumber1"><?php echo $lang['user_manage20'] ?></label>
                                                            <div class="btn-group">
                                                                <label class="btn">
                                                                    <div class="custom-control custom-radio">
                                                                        <input type="radio" id="customRadio4" class="custom-control-input" name="active" value="1" <?php cdp_getChecked($row->active, "1"); ?>>
                                                                        <label class="custom-control-label" for="customRadio4"> <?php echo $lang['user_manage16'] ?></label>
                                                                    </div>
                                                                </label>
                                                                <label class="btn">
                                                                    <div class="custom-control custom-radio">
                                                                        <input type="radio" id="customRadio3" class="custom-control-input" name="active" value="0" <?php cdp_getChecked($row->active, "0"); ?>>
                                                                        <label class="custom-control-label" for="customRadio3"> <?php echo $lang['user_manage17'] ?></label>
                                                                    </div>
                                                                </label>

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
                                                                        <input type="radio" id="customRadio4" name="newsletter" value="1" <?php cdp_getChecked($row->newsletter, 1); ?> class="custom-control-input">
                                                                        <label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config14'] ?></label>
                                                                    </div>
                                                                </label>
                                                                <label class="btn">
                                                                    <div class="custom-control custom-radio">
                                                                        <input type="radio" id="customRadio5" name="newsletter" value="0" <?php cdp_getChecked($row->newsletter, 0); ?> class="custom-control-input">
                                                                        <label class="custom-control-label" for="customRadio5"> <?php echo $lang['tools-config15'] ?></label>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php }  ?>

                                                <hr>
                                                <h4><?php echo $lang['leftorder176'] ?></h4>
                                                <br>

                                                <div id="resultados_ajax"></div>

                                                <?php

                                                $count = 0;

                                                    foreach ($user_addreses as $rowAddress) {
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
                                                                    <input type="text" class="form-control form-control-sm" value="<?php echo $rowAddress->zip_code; ?>" name="postal[]" id="postal<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage14'] ?>">
                                                                </div>
                                                            </div>

                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label for="phoneNumber1"><?php echo $lang['user_manage10'] ?></label>
                                                                    <input type="text" class="form-control form-control-sm" value="<?php echo $rowAddress->address; ?>" name="address[]" id="address<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage10'] ?>">
                                                                </div>
                                                            </div>

                                                            <input type="hidden" name="address_id[]" id="address_id<?php echo $count; ?>" value="<?php echo $rowAddress->id_addresses; ?>" />

                                                            <?php

                                                            if ($count > 1) { ?>
                                                                <div align="center" class="col-md-4">
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
                                                <?php }  ?>

                                                <input type="hidden" name="total_address" id="total_address" value="<?php echo $count; ?>" />
                                                <input type="hidden" name="phone" id="phone" value="<?php echo $row->phone; ?>" />

                                                <div id="div_address_multiple"></div>


                                                <div align="left">
                                                    <button type="button" name="add_row" id="add_row" class="btn btn-success mb-2"><span class="fa fa-plus"></span> <?php echo $lang['add_address_recepient'] ?></button>
                                                </div>


                                                <hr />
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label for="emailAddress1"><?php echo $lang['user_manage28'] ?></label>
                                                            <textarea class="form-control" name="notes" id="notes" rows="6" name="notes" placeholder="<?php echo $lang['user_manage31'] ?>">
                                                            <?php echo $row->notes; ?>
                                                        </textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                            </section>
                                            <div class="form-group">
                                                <div class="col-sm-12">

                                                    <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['user-account20'] ?><span><i class="icon-ok"></i></span></button>
                                                    <a href="customers_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['user_manage30'] ?></a>
                                                </div>
                                                <input name="id" id="id" type="hidden" value="<?php echo $row->id; ?>" />

                                                 <input type="hidden" name="count_address" id="count_address" value="<?php echo $count; ?>" />
                                            </div>
                                        </form>
                                    </div>
                                </div>
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
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

    <script src="dataJs/customers_edit.js"></script>



</body>

</html>