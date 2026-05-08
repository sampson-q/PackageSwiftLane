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
	<!-- This Page CSS -->
	<!-- Custom CSS -->
	<?php include 'views/inc/head_scripts.php'; ?>

	<link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">

    <style>
    /* Estilo para campos requeridos */
    .highlight {
        border: 1px solid #ff0000; /* Borde rojo */
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

			<!-- ============================================================== -->
			<!-- Start Page Content -->
			<!-- ============================================================== -->
			<div class="email-app mt-3">
				<!-- ============================================================== -->
				<!-- Left Part menu -->
				<!-- ============================================================== -->

				<?php include 'views/inc/left_part_menu.php'; ?>

				<!-- ============================================================== -->
				<!-- Right Part contents-->
				<!-- ============================================================== -->
				<div class="right-part mail-list bg-white">
						<!-- Action part -->
						<!-- Button group part -->
						<div class="bg-light">
							<div class="row justify-content-center">
								<div class="col-md-12">
									<div class="row">
										<div class="col-12">
											<div id="loader" style="display:none"></div>
											<div id="resultados_ajax"></div>
										</div>
									</div>
								</div>
							</div>
						</div> 
						<!-- Action part -->

					<div class="row justify-content-center">
						<div class="col-md-12">
							<div class="row">
								<!-- Column -->
								<div class="col-12">
									<div class="card-body">

										<div class="d-md-flex align-items-center">
						                    <div>
						                        <h3 class="card-title"><span><?php echo $lang['ws-add-text22'] ?></span></h3>
						                    </div>
						                </div>
						                <div><hr><br></div>

										<form class="form-horizontal form-material" id="save_data" name="save_data" method="post">
											<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
											<section>
												<div class="row">
													<div class="col-md-12">
														<div class="form-group">
															<label for="firstName1"> <?php echo $lang['ws-add-text23'] ?></label>
															<input type="text" class="form-control required" name="api_ws_url" id="api_ws_url" placeholder="API URL" value="<?php echo $core->api_ws_url; ?>">
														</div>
													</div>

												</div>

												<div class="row">
													<div class="col-md-12">
														<div class="form-group">
															<label for="firstName1"> <?php echo $lang['ws-add-text24'] ?></label>
															<input type="text" class="form-control required" name="api_ws_token" id="api_ws_token" placeholder="API TOKEN" value="<?php echo $core->api_ws_token; ?>">
														</div>
													</div>

												</div>
												<div class="row mt-3 mb-3">
													<div class="col-md-12">
														<div class="form-group">
															<label class="custom-control custom-checkbox">
																<?php echo $lang['ws-add-text25'] ?>
																<input type="checkbox" class="custom-control-input" name="active_whatsapp" id="active_whatsapp" value="1" 
																<?php if ($core->active_whatsapp == 1) {
																		echo 'checked';
																	} ?>>

																<span class="custom-control-indicator"></span>
															</label>
														</div>
													</div>

												</div>
											</section> 

											<div class="form-group">
												<div class="col-sm-12">
													<button class="btn btn-danger btn-confirmation" name="dosubmit" type="submit">
														<?php echo $lang['ws-add-text21'] ?> <span><i class="icon-ok"></i></span></button>

												</div>
											</div>
										</form>
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

		<?php include('helpers/languages/translate_to_js.php'); ?>


		<!-- ============================================================== -->
		<!-- All Jquery -->
		<!-- ============================================================== -->

		<script src="dataJs/whatssap_config.js"></script>

</body>

</html>