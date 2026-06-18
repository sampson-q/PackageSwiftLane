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
if (!$user->cdp_is_Admin()) {
    cdp_redirect_to("customers_profile_edit.php?user=" . $userData->id);
}

if (intval($userData->id) !== intval($_GET['user']) && !$user->cdp_is_Admin()) {
    cdp_redirect_to("login.php");
}

require_once('helpers/querys.php');

if (isset($_GET['user'])) {
    $data = cdp_getUserEdit4bozo($_GET['user']);
}

if (!isset($_GET['user']) or $data['rowCount'] != 1) {
    cdp_redirect_to("customers_list.php");
}

$row = $data['data'];

$db->cdp_query("SELECT * FROM cdb_senders_addresses WHERE user_id='" . $_GET['user'] . "'");
$user_addreses = $db->cdp_registros();

// 1) Prepare SQL with LEFT JOIN to include updater names
$sql = "
  SELECT
    h.*,
    u.fname   AS update_by_fname,
    u.lname   AS update_by_lname
  FROM
    cdb_profile_update_history AS h
  LEFT JOIN
    cdb_users AS u
    ON h.update_by = u.id
  WHERE
    h.user_id = :user_id
  ORDER BY
    h.datetime DESC
";

// 2) Run query
$db->cdp_query($sql);
$db->bind(':user_id', $_GET['user']);
$db->cdp_execute();

// 3) Fetch all history rows, each now with fname/lname of who made the update
$history = $db->cdp_registros();

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!-- Tell the browser to be responsive to screen width -->
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">
        <!-- Favicon icon -->
        <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
        <title><?php echo "View Customer" ?> | <?php echo $core->site_name ?></title>

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
                        <div class="col-lg-3 col-xlg-2 col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <h4 class="card-title"><span><?php echo "View Customer"; ?></span></h4>
                                        </div>
                                    </div>
                                    <div><hr></div>
                                    
                                    <small class="text-muted p-t-30 db"><?php echo "Click on Images to Preview them" ?></small>
                                    
                                    <div style="display: flex; justify-content: center; align-items: center; gap: 50px; margin-top: 20px;" class="mb-30">
                                        <!-- Left Section (Profile Image) -->
                                        <div style="text-align: center;">
                                            <label for="avatarInput">
                                                <img src="assets/<?php echo ($row->avatar) ? $row->avatar : "/uploads/blank.png"; ?>" class="rounded-circle" width="90" height="90" onclick="previewCustomer(this.src, `<?php echo ucwords(strtolower($row->username)) . '\'s Avatar'; ?>`)" />
                                            </label>
                                        </div>

                                        <!-- Right Section (Document Image) -->
                                        <div style="text-align: center;">
                                            <label for="documentInput">
                                                <img src="assets/<?php echo ($row->document_photo) ? $row->document_photo : "/uploads/blankID.jpg"; ?>" style="border-radius: 15px;" height="120" onclick="previewCustomer(this.src, `<?php echo ucwords(strtolower($row->username . '\'s Document')); ?>`)" />
                                            </label>
                                        </div>
                                    </div>
                                    <div><br><hr></div><br>
                                    <div style="text-align: center;">
                                        <h4 class="card-title m-t-10"><?php echo $row->fname; ?> <?php echo $row->lname; ?></h4>
                                        <h6 class="card-subtitle">
                                            <span><?php echo $lang['user_manage2'] ?> <i class="icon-double-angle-right"></i></span>
                                            <div class="badge badge-pill badge-light font-16">
                                                <span class="ti-user text-warning"></span> <?php echo $row->username; ?>
                                            </div>
                                        </h6>
                                        <h6 class="card-subtitle">
                                            <span><?php echo $lang['user-account21000'] ?> <i class="icon-double-angle-right"></i></span>
                                            <div class="badge badge-pill badge-light font-16"> <?php echo $row->locker; ?></div>
                                        </h6>
                                    </div>
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
                        <div class="col-lg-9 col-xlg-10 col-md-8">
                            <div class="card">
                                <!-- Tabs -->
                                <ul class="nav nav-pills custom-pills" id="pills-tab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="pills-setting-tab" data-toggle="pill" href="#previous-month" role="tab" aria-controls="pills-setting" aria-selected="false"><span><i class="icon-double-angle-right"></i> <?php echo ucwords(strtolower($row->username)) . "'s Packages"; ?></span></a>
                                    </li>
                                </ul>
                                <!-- Tabs -->
                                <input type="hidden" id="search" name="search" value="<?php echo $_GET['user']; ?>">
                                <div class="p-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                                        <input type="text" id="track" class="form-control" placeholder="<?php echo $lang['ltracking']; ?>">
                                    </div>
                                </div>
                                <div class="outer_divx"></div>
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
        <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

        <!-- <script src="dataJs/customer_view.js"></script> -->
            <script src="dataJs/customer_view.js"></script> 
    <script src="dataJs/customer_view_ajax.js"></script>
        
    </body>

</html>