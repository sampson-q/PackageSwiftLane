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

$db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $infoship->time_default5 . "'");
$delivery_times = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $infoship->pay_default6 . "'");
$metod_payment = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_payment_methods where id= '" . $infoship->payment_default7 . "'");
$payment_methods = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $infoship->status_default8 . "'");
$styles_status = $db->cdp_registro();

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

		<?php $agencyrow = $core->cdp_getBranchoffices(); ?>
		<?php $courierrow = $core->cdp_getCouriercom(); ?>
		<?php $statusrow = $core->cdp_getStatus(); ?>
		<?php $packrow = $core->cdp_getPack(); ?>
		<?php $payrow = $core->cdp_getPayment(); ?>
		<?php $paymethodrow = $core->cdp_getPaymentMethod(); ?>
		<?php $itemrow = $core->cdp_getItem(); ?>
		<?php $moderow = $core->cdp_getShipmode(); ?>
		<?php $delitimerow = $core->cdp_getDelitime(); ?>
		<?php $categories = $core->cdp_getCategories(); ?>


		<!-- End Left Sidebar - style you can find in sidebar.scss  -->


		<!-- Page wrapper  -->
		<!-- ============================================================== -->
		<div class="page-wrapper">



			<!-- Action part -->

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
                                        <h3 class="card-title"><span><?php echo $lang['leftorder87'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

								<form class="form-horizontal form-material" id="save_config" name="save_config" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

									<div class="row">
										<div class="col-lg-12">

											<div class="card-header bg-secondary">
												<h4 class="card-title text-white"><i class="mdi mdi-book-multiple" style="color:#20c997"></i> <?php echo $lang['leftorder89'] ?></h4>
											</div>
											<div class="card-body">

												<div class="row">

													<div class="form-group col-md-3">

														<label for="inputlname" class="control-label col-form-label"><?php echo $lang['leftorder29'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="logistics_default1" name="logistics_default1" required style="width: 100%;">
																<option value="<?php echo $s_logistics->id; ?>"><?php echo $s_logistics->name_item; ?></option>
																<?php foreach ($categories as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->name_item; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

													<div class="form-group col-md-3">
														<label for="inputlname" class="control-label col-form-label"><?php echo $lang['add-title17'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="packaging_default2" name="packaging_default2" required style="width: 100%;">
																<option value="<?php echo $packaging_box->id; ?>"><?php echo $packaging_box->name_pack; ?></option>
																<?php foreach ($packrow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->name_pack; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

													<div class="form-group col-md-3">
														<label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title18'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="courier_default3" name="courier_default3" required style="width: 100%;">
																<option value="<?php echo $courier_comp->id; ?>"><?php echo $courier_comp->name_com; ?></option>
																<?php foreach ($courierrow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->name_com; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

													<div class="form-group col-md-3">
														<label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title22'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="service_default4" name="service_default4" required style="width: 100%;">
																<option value="<?php echo $ship_modes->id; ?>"><?php echo $ship_modes->ship_mode; ?></option>
																<?php foreach ($moderow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->ship_mode; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>
												</div>

												<div class="row">
													<!--/span-->
													<div class="form-group col-md-3">
														<label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title20'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="time_default5" name="time_default5" required style="width: 100%;">
																<option value="<?php echo $delivery_times->id; ?>"><?php echo $delivery_times->delitime; ?></option>
																<?php foreach ($delitimerow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->delitime; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>
													<!--/span-->


													<div class="form-group col-md-3">
														<label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title23'] ?> <i style="color:#ff0000" class="fas fa-donate"></i></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="pay_default6" name="pay_default6" required="" style="width: 100%;">
																<option value="<?php echo $metod_payment->id; ?>"><?php echo $metod_payment->name_pay; ?></option>
																<?php foreach ($payrow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->name_pay; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

													<div class="form-group col-md-3">
														<label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['payment_methods'] ?></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="payment_default7" name="payment_default7" required style="width: 100%;">
																<option value="<?php echo $payment_methods->id; ?>"><?php echo $payment_methods->label; ?></option>
																<?php foreach ($paymethodrow as $row) : ?>
																	<option value="<?php echo $row->id; ?>"><?php echo $row->label; ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

													<!--/span-->

													<div class="form-group col-md-3">
														<label for="inputcontact" class="control-label col-form-label"><?php echo $lang['add-title19'] ?> <i style="color:#ff0000" class="fas fa-shipping-fast"></i></label>
														<div class="input-group mb-3">
															<select class="select2 form-control custom-select" id="status_default8" name="status_default8" required style="width: 100%;">
																<option value="<?php echo $styles_status->id; ?>"><?php echo $styles_status->mod_style; ?></option>
																<?php foreach ($statusrow as $row) : ?>
																	<?php if ($row->mod_style == 'Delivered') { ?>
																	<?php } elseif ($row->mod_style == 'Consolidate') { ?>
																	<?php } else { ?>
																		<option value="<?php echo $row->id; ?>"><?php echo $row->mod_style; ?></option>
																	<?php } ?>
																<?php endforeach; ?>
															</select>
														</div>
													</div>

												</div>
											</div>
										</div>
									</div>

									<div class="form-group mb-3">
										<div class="col-sm-12">
											<button type="submit" class="btn btn-danger btn-confirmation" name="dosubmit"><?php echo $lang['tools-config56'] ?> <span><i class="icon-ok"></i></span></button>
										</div>
									</div>

								</form>

							</div>
						</div>
					</div>
				</div>
			</div>

			<?php include 'views/inc/footer.php'; ?>
		</div>

	</div>
	<!-- ============================================================== -->
	<!-- End Page wrapper  -->
	<!-- ============================================================== -->

	<?php include('helpers/languages/translate_to_js.php'); ?>

	<script src="dataJs/config_info_ship_default.js"></script>

</body>

</html>