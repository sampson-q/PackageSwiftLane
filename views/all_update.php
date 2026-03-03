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

header('Cache-Control: no-cache');

if (!$user->cdp_is_Admin())
  cdp_redirect_to("login.php");

require_once 'helpers/functions_money.php'; // Include LicenseBox external/client api helper file
$api = new License2l6aspi3ekdz14bfnxwtugjycm05hqcdpcoddingproV75(); // Initialize a new LicenseBoxExternalcdpcoddingproV7API object

$userData = $user->cdp_getUserData();

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
  <title><?php echo $lang['tools-config61'] ?> | <?php echo $core->site_name ?></title>
  <?php include 'views/inc/head_scripts.php'; ?>


</head>

<body>
  <!-- ============================================================== -->
  <!-- Preloader - style you can find in spinners.css -->
  <!-- ============================================================== -->
  <?php
  $update_data = $api->check_update();
  if (!is_array($update_data)) {
    $update_data = array('status' => false, 'message' => 'Unable to check for updates.', 'changelog' => '', 'update_id' => '', 'has_sql' => false, 'version' => '');
  }
  ?>

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
      <!-- ============================================================== -->
      <!-- Bread crumb and right sidebar toggle -->
      <!-- ============================================================== -->
      <div class="page-breadcrumb">
        <div class="row">
          <div class="col-12 align-self-center">
            <h4 class="page-title"><?php echo $lang['help-text0'] ?> </h4>

          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- End Bread crumb and right sidebar toggle -->
      <!-- ============================================================== -->
      <!-- ============================================================== -->
      <!-- Container fluid  -->
      <!-- ============================================================== -->
      <div class="container-fluid">
        <!-- ============================================================== -->
        <!-- Start Page Content -->
        <!-- ============================================================== -->
        <div class="row ">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <div class="row justify-content-center">
                  <div class="text-center col-lg-12 col-md-12">
                    <h4 class="card-title"><?php echo $lang['help-text1'] ?> </h4>
                    <h5 class="card-subtitle"><?php echo $lang['help-text2'] ?> </h5>
                    <div><br></div>
                    <h6 class="card-subtitle">
                      <?php echo $lang['help-text3'] ?>
                    </h6>
                    <p><a href="https://codecanyon.net/item/courier-deprixa-pro-integrated-web-system-v32/15216982"><b>
                          <i class="mdi mdi-cart-outline" style="color:#FD571E"></i> <?php echo $lang['help-text4'] ?></b></a></p>
                    <div><br><br></div>
                    <h3 id="item-description__support">
                      <strong><?php echo $lang['help-text8'] ?>
                      </strong>
                    </h3>
                    <p><?php echo $lang['help-text5'] ?><br></p>

                    <a href="https://ticket.deprixapro.site/index.php"><img src="https://deprixapro.site/envato/support.png"></a>
                    <!-- div -->
                    <div><br></div>
                  </div>
                </div>

                <div class="row justify-content-center">
                  <div class="text-center col-lg-12 col-md-12">

                    <div class="section">
                      <div class="columns is-centered">
                        <div class="column is-two-fifths">
                          <center>
                            <h2 class="title"><?php echo $lang['help-text6'] ?></h2><br>
                          </center>
                          <div class="">
                            <?php if ($update_data['status']) { ?>
                              <article class="message is-success">
                                <div class="message-body">
                                  <?php echo $lang['help-text7'] ?>
                                </div>
                              </article>
                            <?php } ?>
                            <p class="subtitle is-5" style="margin-bottom: 5px">
                              <?php
                              echo $update_data['message']; // You can also show update notification/summary here instead.
                              ?>
                            </p>
                            <div class='content'>
                              <?php if ($update_data['status']) { ?>
                                <p><?php echo $update_data['changelog']; ?></p>
                                <?php
                                $update_id = null;
                                $has_sql = null;
                                $version = null;
                                if (!empty($_POST['update_id'])) {
                                  $update_id = strip_tags(trim($_POST["update_id"]));
                                  $has_sql = strip_tags(trim($_POST["has_sql"]));
                                  $version = strip_tags(trim($_POST["version"]));
                                  echo '<progress id="prog" value="0" max="100.0" class="progress is-success" style="margin-bottom: 10px;"></progress>';
                                  // Once we have the update_id we can use LicenseBoxExternalAPI's download_update() function for downloading and installing the update.
                                  $api->download_update(
                                    $_POST['update_id'],
                                    $_POST['has_sql'],
                                    $_POST['version'],
                                    null,
                                    null,
                                    array(
                                      'db_host' => defined('CDP_DB_HOST') ? (string) CDP_DB_HOST : 'localhost',
                                      'db_user' => defined('CDP_DB_USER') ? (string) CDP_DB_USER : '',
                                      'db_pass' => defined('CDP_DB_PASS') ? (string) CDP_DB_PASS : '',
                                      'db_name' => defined('CDP_DB_NAME') ? (string) CDP_DB_NAME : ''
                                    )
                                  );
                                } else { ?>
                                  <form action="verify_update.php" method="POST">
                                    <input type="hidden" class="form-control" value="<?php echo $update_data['update_id']; ?>" name="update_id">
                                    <input type="hidden" class="form-control" value="<?php echo $update_data['has_sql']; ?>" name="has_sql">
                                    <input type="hidden" class="form-control" value="<?php echo $update_data['version']; ?>" name="version">
                                    <center>
                                      <button type="submit" class="btn btn-warning">
                                        <?php echo $lang['help-text9'] ?>
                                      </button>
                                    </center>
                                  </form><?php
                                        }
                                      } ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- ============================================================== -->
        <!-- End PAge Content -->
        <!-- ============================================================== -->
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


</body>

</html>