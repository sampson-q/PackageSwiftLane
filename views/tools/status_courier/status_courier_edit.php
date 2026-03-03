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


require_once('helpers/querys.php');

if (isset($_GET['id'])) {
	$data = cdp_getStatusCourierEdit($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
	cdp_redirect_to("status_courier_list.php");
}

$row_off = $data['data'];

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
	<link rel="stylesheet" type="text/css" href="assets/template/assets/libs/claviska/jquery-minicolors/jquery.minicolors.css">

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
                                        <h3 class="card-title"><span><?php echo $lang['tools-statuscourier2'] ?> <i class="icon-double-angle-right"></i> <?php echo $row_off->mod_style; ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

								<div id="msgholder"></div>
								<form class="form-horizontal form-material" id="update_data" name="update_data" method="post">

									<section>
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="firstName1"><?php echo $lang['tools-statuscourier1'] ?></label>
													<input type="text" class="form-control required" name="mod_style" id="mod_style" value="<?php echo $row_off->mod_style; ?>" placeholder="<?php echo $lang['tools-statuscourier1'] ?>">
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label for="lastName1"><?php echo $lang['tools-statuscourier3'] ?></label>
													<input type="text" class="form-control required" name="detail" id="detail" value="<?php echo $row_off->detail; ?>" placeholder="<?php echo $lang['tools-statuscourier3'] ?>">
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="lastName1"><?php echo $lang['tools-statuscourier4'] ?></label>
													<input type="text" id="position-top-right" class="form-control demo" data-position="top right" name="color" id="color" value="<?php echo $row_off->color; ?>" placeholder="<?php echo $lang['tools-statuscourier4'] ?>">
												</div>
											</div>
										</div>
									</section>
									<br><br>
									<div class="form-group">
										<div class="col-sm-12">
											<button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['tools-statuscourier5'] ?> <span><i class="icon-ok"></i></span></button>
											<a href="status_courier_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['tools-statuscourier6'] ?></a>
										</div>
									</div>
									<input name="id" id="id" type="hidden" value="<?php echo $_GET['id']; ?>" />
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


	<script src="assets/template/assets/libs/jquery-asColor/dist/jquery-asColor.min.js"></script>
	<script src="assets/template/assets/libs/jquery-asGradient/dist/jquery-asGradient.js"></script>
	<script src="assets/template/assets/libs/jquery-asColorPicker/dist/jquery-asColorPicker.min.js"></script>
	<script src="assets/template/assets/libs/claviska/jquery-minicolors/jquery.minicolors.min.js"></script>

	<script>
		$('.demo').each(function() {

			$(this).minicolors({
				control: $(this).attr('data-control') || 'hue',
				defaultValue: $(this).attr('data-defaultValue') || '',
				format: $(this).attr('data-format') || 'hex',
				keywords: $(this).attr('data-keywords') || '',
				inline: $(this).attr('data-inline') === 'true',
				letterCase: $(this).attr('data-letterCase') || 'lowercase',
				opacity: $(this).attr('data-opacity'),
				position: $(this).attr('data-position') || 'bottom left',
				swatches: $(this).attr('data-swatches') ? $(this).attr('data-swatches').split('|') : [],
				change: function(value, opacity) {
					if (!value) return;
					if (opacity) value += ', ' + opacity;
				},
				theme: 'bootstrap'
			});

		});
	</script>

	<script src="dataJs/status_courier.js"></script>

</body>

</html>