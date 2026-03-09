<?php
    $userData = $user->cdp_getUserData();
    $statusrow = $core->cdp_getStatus();
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
    <title><?php echo $lang['postraltrackingsearch'] ?> | <?php echo $core->site_name ?></title>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
    <?php include 'views/inc/head_scripts.php'; ?>
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

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-5 align-self-center">
                        <h4 class="page-title"> <?php echo $lang['postraltrackingsearch']; ?></h4>

                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    <!-- Column -->

                    <div class="col-lg-12 col-xl-12 col-md-12">

                        <div class="card">
                            <div class="card-body">
                                <div id="resultados_ajax"></div>

                            <div class="row mb-3">

                                <?php
                                    // Define la URL del enlace basándote en el nivel de usuario
                                    $add_courier_url = ($userData->userlevel == 9) ? "courier_add.php" : "courier_add_client.php";
                                ?>

                                <div class=" col-sm-12 col-md-4 mb-2">
                                    <div class="input-group">
                                        <input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21553'] ?>" onkeyup="cdp_load(1);">
                                        <div class="input-group-append input-sm">
                                            <button type="submit" class="btn btn-outline-dark"><i class="fa fa-search"></i></button>
                                        </div>

                                    </div>
                                </div><!-- /.col -->

                                
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="col-md-12 hide" id="div-actions-checked">
                                        <!-- <div class="form-group"> -->
                                        <div class="btn-group mt-2">
                                            <span class="mt-2 mr-4"><strong> <?php echo $lang['global-2'] ?></strong> <strong id="countChecked"> 0</strong></span>&nbsp;&nbsp;
                                            <button class="btn btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <?php echo $lang['global-1'] ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalCheckboxStatus"><i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['left21550'] ?></a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modalDriverCheckbox"><i style="color:#ff0000" class="fas fa-car"></i>&nbsp;<?php echo $lang['left208'] ?></a>
                                                <a class="dropdown-item" onclick="cdp_printMultipleLabel();" target="_blank"> <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel'] ?> </a>
                                            </div>
                                        </div>
                                        <!-- </div> -->
                                    </div>
                                </div>

                            </div>


                            <div class="outer_divx"></div>


                        </div>
                    </div>
                </div>
                <!-- Column -->
            </div>
        </div>

        <?php
        include('views/modals/modal_update_status_checked.php');
        ?>
        <?php include('views/modals/modal_send_email.php'); ?>

        <?php include('views/modals/modal_update_driver.php'); ?>
        <?php include('views/modals/modal_update_driver_checked.php'); ?>
        <?php include('views/modals/modal_verify_payment_packages.php'); ?>

        <?php include('views/modals/modal_cancel_pickup.php'); ?>

        <?php include('views/modals/modal_delete_pickup.php'); ?>


        <?php include('views/modals/modal_charges_list.php'); ?>
        <?php include('views/modals/modal_charges_add.php'); ?>
        <?php include('views/modals/modal_charges_edit.php'); ?>
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

    <script src="dataJs/pickup_client.js"></script>
    <script src="dataJs/pickup_client_ajax.js"></script>

</body>

</html>