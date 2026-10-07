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
										$cdpE         = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
										$cdpHub       = cdp_hubtelSmsConfig();
										$cdpMno       = cdp_mnotifySmsConfig();
										$cdpProviders = cdp_smsProviders();
										$cdpDefault   = cdp_smsDefaultProvider();
										$cdpBadge     = function ($p) {
											if (!cdp_smsProviderConfigured($p)) {
												return '<span class="badge badge-secondary" data-sms-badge="' . $p . '">Not Configured</span>';
											}
											return cdp_smsProviderEnabled($p)
												? '<span class="badge badge-success" data-sms-badge="' . $p . '">On</span>'
												: '<span class="badge badge-warning" data-sms-badge="' . $p . '">Off</span>';
										};
										?>
										<form class="form-horizontal form-material" id="save_sms_settings" name="save_sms_settings" method="post" autocomplete="off">
											<input type="hidden" name="_csrf_token" value="<?php echo $cdpE(cdp_csrf_token()); ?>">
											<input type="hidden" name="action" value="save">

											<header><b>General</b></header>
											<br>
											<section>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="sms_default_provider">Default Provider</label>
															<select class="form-control" name="sms_default_provider" id="sms_default_provider">
																<?php foreach ($cdpProviders as $p => $label) { ?>
																	<option value="<?php echo $p; ?>" <?php echo $p === $cdpDefault ? 'selected' : ''; ?>><?php echo $cdpE($label); ?></option>
																<?php } ?>
															</select>
															<small class="text-muted">Every SMS goes out through this provider. If it is switched off, the other one sends.</small>
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="form-group mb-2">
															<label class="custom-control custom-checkbox">
																Send SMS Notifications
																<input type="checkbox" class="custom-control-input" name="active_sms" id="active_sms" value="1" <?php if ((int) $core->active_sms === 1) { echo 'checked'; } ?>>
																<span class="custom-control-indicator"></span>
															</label>
															<small class="text-muted d-block">Sign-in, reset and pickup codes are always sent while a provider is on.</small>
														</div>
														<div class="form-group">
															<label class="custom-control custom-checkbox">
																Fall Back To The Other Provider When A Send Fails
																<input type="checkbox" class="custom-control-input" name="sms_fallback" id="sms_fallback" value="1" <?php if (cdp_smsFallbackOn()) { echo 'checked'; } ?>>
																<span class="custom-control-indicator"></span>
															</label>
														</div>
													</div>
												</div>
											</section>

											<hr class="my-4">

											<header class="d-flex align-items-center justify-content-between">
												<b>Hubtel</b>
												<?php echo $cdpBadge('hubtel'); ?>
											</header>
											<br>
											<section>
												<div class="form-group">
													<label class="custom-control custom-checkbox">
														Hubtel On
														<input type="checkbox" class="custom-control-input" name="hubtel_enabled" id="hubtel_enabled" value="1" <?php if (cdp_smsProviderEnabled('hubtel')) { echo 'checked'; } ?>>
														<span class="custom-control-indicator"></span>
													</label>
												</div>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_client_id">Client ID</label>
															<input type="text" class="form-control" name="hubtel_client_id" id="hubtel_client_id" maxlength="128" autocomplete="off" value="<?php echo $cdpE($cdpHub['client_id']); ?>">
														</div>
													</div>
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_client_secret">Client Secret</label>
															<input type="password" class="form-control" name="hubtel_client_secret" id="hubtel_client_secret" maxlength="128" autocomplete="new-password" placeholder="<?php echo $cdpHub['client_secret'] !== '' ? '••••••••••••' : ''; ?>" value="">
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="hubtel_sender_id">Sender ID</label>
															<input type="text" class="form-control" name="hubtel_sender_id" id="hubtel_sender_id" maxlength="11" autocomplete="off" value="<?php echo $cdpE($cdpHub['sender']); ?>">
														</div>
													</div>
												</div>
											</section>

											<hr class="my-4">

											<header class="d-flex align-items-center justify-content-between">
												<b>mNotify</b>
												<?php echo $cdpBadge('mnotify'); ?>
											</header>
											<br>
											<section>
												<div class="form-group">
													<label class="custom-control custom-checkbox">
														mNotify On
														<input type="checkbox" class="custom-control-input" name="mnotify_enabled" id="mnotify_enabled" value="1" <?php if (cdp_smsProviderEnabled('mnotify')) { echo 'checked'; } ?>>
														<span class="custom-control-indicator"></span>
													</label>
												</div>
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<label for="mnotify_api_key">API Key</label>
															<input type="password" class="form-control" name="mnotify_api_key" id="mnotify_api_key" maxlength="128" autocomplete="new-password" placeholder="<?php echo $cdpMno['api_key'] !== '' ? '••••••••••••' : ''; ?>" value="">
														</div>
													</div>
													<div class="col-md-6">
														<div class="form-group">
															<label for="mnotify_sender_id">Sender ID</label>
															<input type="text" class="form-control" name="mnotify_sender_id" id="mnotify_sender_id" maxlength="11" autocomplete="off" value="<?php echo $cdpE($cdpMno['sender']); ?>">
														</div>
													</div>
												</div>
												<div class="form-group">
													<button class="btn btn-outline-secondary btn-sm" id="mnotify_balance_btn" type="button">Check mNotify Balance</button>
													<small class="text-muted ml-2">Tests the API key without sending an SMS.</small>
												</div>
											</section>

											<div class="form-group mt-4">
												<button class="btn btn-danger" id="sms_save_btn" type="submit">Save Settings</button>
											</div>
										</form>

										<hr class="my-4">

										<form class="form-horizontal form-material" id="sms_test_form" name="sms_test_form" method="post" autocomplete="off">
											<input type="hidden" name="_csrf_token" value="<?php echo $cdpE(cdp_csrf_token()); ?>">
											<input type="hidden" name="action" value="test">
											<header><b>Send Test SMS</b></header>
											<br>
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<label for="sms_test_provider">Provider</label>
														<select class="form-control" name="sms_test_provider" id="sms_test_provider">
															<?php foreach ($cdpProviders as $p => $label) { ?>
																<option value="<?php echo $p; ?>" <?php echo $p === $cdpDefault ? 'selected' : ''; ?>><?php echo $cdpE($label); ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
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