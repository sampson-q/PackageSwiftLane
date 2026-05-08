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
					<!-- Column -->

					<div class="col-lg-12 col-xl-12 col-md-12">

						<div class="card">
							<div class="card-body">

								<div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['leftfees'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

								<form class="form-horizontal form-material" id="save_config" name="save_config" method="post">
									<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
									<section>
										<div class="row">

											<div class="col-md-6">
												<div class="form-group">
													<label for="lastName1">
														<b style="color:#ff0000"><?php echo $core->currency; ?></b>
														<?php echo $lang['tools-config77'] ?>
													</label>
													<input type="text" class="form-control required" name="min_cost_tax" id="min_cost_tax" value="<?php echo $core->min_cost_tax; ?>" placeholder="<?php echo $lang['tools-config77'] ?>">
												</div>
											</div>


											<div class="col-md-6">
												<div class="form-group">
													<label for="firstName1">
														<i class="fas fa-percent" style="color:#ff0000"></i>
														<?php echo $lang['tools-config46'] ?></label>
													<input type="text" class="form-control required" name="tax" name="id" value="<?php echo $core->tax; ?>" placeholder="<?php echo $lang['tools-config46'] ?>">
												</div>
											</div>
										</div>

										<div class="row">

											<div class="col-md-6">
												<div class="form-group">
													<label for="lastName1">
														<b style="color:#ff0000"><?php echo $core->currency; ?></b>
														<?php echo $lang['tools-config76'] ?>
													</label>
													<input type="text" class="form-control required" name="min_cost_declared_tax" id="min_cost_declared_tax" value="<?php echo $core->min_cost_declared_tax; ?>" placeholder="<?php echo $lang['tools-config76'] ?>">
												</div>
											</div>


											<div class="col-md-6">
												<div class="form-group">
													<label for="firstName1">
														<i class="fas fa-percent" style="color:#ff0000"></i>
														<?php echo $lang['tools-config78'] ?>
													</label>
													<input type="text" class="form-control required" name="declared_tax" id="declared_tax" value="<?php echo $core->declared_tax; ?>" placeholder="<?php echo $lang['tools-config78'] ?>">
												</div>
											</div>
										</div>

										<div class="row">


											<div class="col-md-6">
												<div class="form-group">
													<label for="lastName1"><i class="fas fa-percent" style="color:#ff0000"></i> <?php echo $lang['tools-config47'] ?></label>
													<input type="text" class="form-control required" name="insurance" id="insurance" value="<?php echo $core->insurance; ?>" placeholder="<?php echo $lang['tools-config48'] ?>">
												</div>
											</div>


											<div class="col-md-6">
												<div class="form-group">
													<label for="firstName1"><i class="fas fa-percent" style="color:#ff0000"></i> <?php echo $lang['langs_081'] ?></label>
													<input type="text" class="form-control required" name="c_tariffs" id="c_tariffs" value="<?php echo $core->c_tariffs; ?>" placeholder="<?php echo $lang['langs_081'] ?>">
												</div>
											</div>

										</div>

										<div class="row">
											<div class="col-md-3">
												<div class="form-group">
													<label for="firstName1"><i class="ti-package" style="color:#ff0000"></i> <?php echo $lang['tools-config50'] ?> <b> / <?php echo $core->meter; ?></b></label>
													<input type="text" class="form-control required" name="meter" id="meter" value="<?php echo $core->meter; ?>" placeholder="<?php echo $lang['tools-config50'] ?> / <?php echo $core->meter; ?>">
												</div>
											</div>

											<div class="col-md-3">
												<div class="form-group">
													<label for="lastName1"><?php echo $lang['tools-config75'] ?></label>
													<select class="custom-select form-control" name="units" id="units">
														<optgroup label="<?php echo $lang['tools-config85'] ?>">
															<option value="cm" <?php if ($core->units == "cm") echo " selected=\"selected\""; ?>> <?php echo $lang['tools-config81'] ?></option>
															<option value="m" <?php if ($core->units == "m") echo " selected=\"selected\""; ?>> <?php echo $lang['tools-config82'] ?></option>
															<option value="Pie" <?php if ($core->units == "Pie") echo " selected=\"selected\""; ?>> <?php echo $lang['tools-config83'] ?></option>
															<option value="in" <?php if ($core->units == "in") echo " selected=\"selected\""; ?>> <?php echo $lang['tools-config84'] ?></option>
														</optgroup>
													</select>
												</div>
											</div>


											<div class="col-md-3">
												<div class="form-group">
													<label for="lastName1"><b style="color:#ff0000"><?php echo $core->currency; ?></b> <?php echo $lang['tools-config58'] ?></label>
													<input type="text" class="form-control required" name="value_weight" id="value_weight" value="<?php echo $core->value_weight; ?>" placeholder="<?php echo $lang['tools-config58'] ?>">
												</div>
											</div>

											<div class="col-md-3">
												<div class="form-group">
													<label for="lastName1">
														<?php echo $lang['tools-config79'] ?>
													</label>
													<select class="custom-select form-control required" name="weight_p" id="weight_p">
														<optgroup label="<?php echo $lang['tools-config80'] ?>">
															<option value="kg" <?php if ($core->weight_p == "kg") echo " selected=\"selected\""; ?>><?php echo $lang['tools-config86'] ?></option>
															<option value="lb" <?php if ($core->weight_p == "lb") echo " selected=\"selected\""; ?>><?php echo $lang['tools-config87'] ?></option>
														</optgroup>
													</select>
												</div>
											</div>
										</div>


									</section>
									<div class="form-group">
										<div class="col-sm-12">
											<button type="submit" class="btn btn-danger btn-confirmation" name="dosubmit"><?php echo $lang['tools-config56'] ?> <span><i class="icon-ok"></i></span></button>
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

	<script src="dataJs/config_taxes.js"></script>
</body>

</html>