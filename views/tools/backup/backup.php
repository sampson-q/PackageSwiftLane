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
	<title><?php echo $lang['tools-config61'] ?> | <?php echo $core->site_name ?></title>
	<?php include 'views/inc/head_scripts.php'; ?>
	<link href="assets/css/front.css" rel="stylesheet" type="text/css">


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

			<!-- ============================================================== -->
			<!-- Start Page Content -->
			<!-- ============================================================== -->
			<div class="email-app">
				<!-- ============================================================== -->
				<!-- Left Part menu -->
				<!-- ============================================================== -->

				<?php include 'views/inc/left_part_menu.php'; ?>

				<!-- ============================================================== -->
				<!-- Right Part contents-->
				<!-- ============================================================== -->
				<div class="right-part mail-list bg-white mt-3">
					<div class="p-15 b-b">
						<div class="d-flex align-items-center">
							<div>
								<span><?php echo $lang['tools-config61'] ?> | <?php echo $lang['restorbackup'] ?></span>
							</div>

						</div>
					</div>
					<!-- Action part -->
					<!-- Button group part -->


					<div class="bg-light ">
						<div class="row justify-content-center">
							<div class="col-md-12">
								<div class="row">
									<div class="col-12">
										<?php

										if (isset($_GET['backupok']) && $_GET['backupok'] == "1") {
										?>

											<div class="alert alert-info" id="success-alert">
												<p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
													<?php echo $lang['restorbackuMmessage'] ?>
												</p>
											</div>
										<?php
										}

										if (CDP_APP_MODE_DEMO === false) {

											if (isset($_GET['create']) && $_GET['create'] == "1") {

												doBackup();
											}
										}

										?>
										<!-- <div id="loader" style="display:none"></div> -->
										<div id="resultados_ajax"></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="row justify-content-center">
						<div class="col-lg-12">
							<div class="row">
								<!-- Column -->
								<div class="col-lg-12">
									<div class="card-body">
										<div class="d-flex no-block align-items-center m-b-30">
											<h4 class="card-title"></h4>
											<div class="ml-auto">
												<div class="btn-group">
													<a href="backup.php?do=backup&amp;create=1"><button type="button" class="btn btn-dark">
															<?php echo $lang['tools-database1'] ?>
															<span class="icon-hdd"></span></button></a>
												</div>
											</div>
										</div>
										<p class="bluetip"><i class="icon-lightbulb icon-3x pull-left"></i><?php echo $lang['tools-database2'] ?><br />
											<?php echo $lang['tools-database3'] ?> [<strong>backups</strong>] <?php echo $lang['tools-database4'] ?> <br />
											<?php echo $lang['tools-database5'] ?>
										</p>
										<div class="table-responsive">
											<?php
											$dir = 'backups/';
											if (is_dir($dir)) :
												$getDir = dir($dir);
												while (false !== ($file = $getDir->read())) :
													if ($file != "." && $file != ".." && $file != "user.php") :
														$sql =  ($file == $core->backup) ? " db-latest" : "";
														echo '<div class="db-backup' . $sql . '" id="item_' . $file . '"><i class="icon-hdd pull-left icon-3x icon-white"></i>';
														echo '<span>' . getSize(filesize('backups/' . $file)) . '</span>';

														echo '<a class="delete">
															  <small class="sdelet btn btn-light" data-toggle="tooltip" title="' . $lang['tools-database6'] . '" ' . $file . 'p"><i class="icon-trash icon-white"></i></small></a>';

														echo '<a href="download.php?file=' . $file . '">
															  <small class="sdown btn btn-light" data-toggle="tooltip" title="' . $lang['tools-database7'] . '"><i class="icon-download-alt icon-white"></i></small></a>';

														echo '<a class="restore">
															  <small class="srestore btn btn-light" data-toggle="tooltip" title="' . $lang['tools-database8'] . '"' . $file . '"><i class="icon-refresh icon-white"></i></small></a>';
														echo '<p>' . str_replace(".sql", "", $file) . '</p>';

														echo '</div>';
													endif;
												endwhile;
												$getDir->close();
											endif;
											?>
										</div>
									</div>
								</div>
								<!-- Column -->
							</div>
						</div>
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

		<script src="dataJs/backup.js"></script>

</body>

</html>