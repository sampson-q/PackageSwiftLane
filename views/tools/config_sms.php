<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



require_once __DIR__ . '/../../helpers/hubtel_sms.php';

// API keys live here: super admins only (the root page checks the same).
if (!cdp_smsCanManage($user)) {
	cdp_redirect_to("error403.php");
}

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
	<title>SMS Settings | <?php echo $core->site_name ?></title>

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
								<span>SMS Settings</span>
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
										<!-- <div id="loader" style="display:none"></div> -->
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
										<!-- <div id="loader" style="display:none"></div> -->
										<!-- <div id="msgholder"></div> -->
										<?php
										$cdpSmsCfg     = cdp_hubtelSmsConfig();
										$cdpSmsReady   = cdp_hubtelSmsReady();
										$cdpSecretSet  = $cdpSmsCfg['client_secret'] !== '';
										?>
										<form class="form-horizontal form-material" id="save_sms_settings" name="save_sms_settings" method="post" autocomplete="off">
											<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
											<input type="hidden" name="action" value="save">
											<header class="d-flex align-items-center justify-content-between">
												<b>Hubtel SMS</b>
												<span id="sms_status_badge" class="badge <?php echo $cdpSmsReady ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $cdpSmsReady ? 'Configured' : 'Not Configured'; ?></span>
											</header>
											<br>
											<section>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_client_id">Client ID</label>
															<input type="text" class="form-control" name="hubtel_client_id" id="hubtel_client_id" maxlength="128" autocomplete="off" value="<?php echo htmlspecialchars($cdpSmsCfg['client_id'], ENT_QUOTES, 'UTF-8'); ?>">
														</div>
													</div>
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_client_secret">Client Secret</label>
															<input type="password" class="form-control" name="hubtel_client_secret" id="hubtel_client_secret" maxlength="128" autocomplete="new-password" placeholder="<?php echo $cdpSecretSet ? '••••••••••••' : ''; ?>" value="">
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_sender_id">Sender ID</label>
															<input type="text" class="form-control" name="hubtel_sender_id" id="hubtel_sender_id" maxlength="11" autocomplete="off" value="<?php echo htmlspecialchars($cdpSmsCfg['sender'], ENT_QUOTES, 'UTF-8'); ?>">
														</div>
													</div>
												</div>
												<div class="row mt-2 mb-3">
													<div class="col-md-12">
														<div class="form-group">
															<label class="custom-control custom-checkbox">
																Send SMS Notifications
																<input type="checkbox" class="custom-control-input" name="active_sms" id="active_sms" value="1" <?php if ((int) $core->active_sms === 1) { echo 'checked'; } ?>>
																<span class="custom-control-indicator"></span>
															</label>
														</div>
													</div>
												</div>
											</section>
											<div class="form-group">
												<button class="btn btn-danger" id="sms_save_btn" type="submit">Save Settings</button>
											</div>
										</form>

										<hr class="my-4">

										<form class="form-horizontal form-material" id="sms_test_form" name="sms_test_form" method="post" autocomplete="off">
											<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
											<input type="hidden" name="action" value="test">
											<header><b>Send Test SMS</b></header>
											<br>
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<label for="sms_test_phone">Phone Number</label>
														<input type="tel" class="form-control" name="sms_test_phone" id="sms_test_phone" maxlength="20" autocomplete="off">
													</div>
												</div>
											</div>
											<div class="form-group">
												<button class="btn btn-outline-secondary" id="sms_test_btn" type="submit">Send Test SMS</button>
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


		<script src="<?= cdp_asset('dataJs/config_sms_hubtel.js') ?>"></script>

</body>

</html>