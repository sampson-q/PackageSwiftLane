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
$office = $core->cdp_getOffices();

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
	<link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
	<?php include 'views/inc/head_scripts.php'; ?>
	<link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">

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


			<div class="container-fluid">

				<div class="row">
					<!-- Column -->

					<div class="col-lg-12 col-xl-12 col-md-12">

						<div class="card">
							<div class="card-body">
								<div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['user_manage37'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>

								<form class="form-horizontal form-material" id="save_user" name="save_user" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

									<section>
										<div class="row">
											<div class="col-md-4">
												<div class="form-group">
													<label for="firstName1"><?php echo $lang['user_manage54'] ?></label>
													<select class="custom-select col-12" id="branch_office" name="branch_office" placeholder="<?php echo $lang['tools-office1'] ?>">
														<?php foreach ($office as $row) : ?>
															<option value="<?php echo $row->name_off; ?>"><?php echo $row->name_off; ?></option>
														<?php endforeach; ?>
													</select>
												</div>
											</div>
											<div class="col-md-4">
												<div class="form-group">
													<label for="firstName1"><?php echo $lang['user_manage3'] ?></label>
													<input type="text" class="form-control" name="username" id="username" placeholder="<?php echo $lang['user_manage3'] ?>">
												</div>
											</div>
											<div class="col-md-4">
												<div class="form-group">
													<label for="lastName1"><?php echo $lang['user_manage32'] ?></label>
													<input type="text" class="form-control" name="password" id="password" placeholder="<?php echo $lang['user_manage32'] ?>">
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="emailAddress1"><?php echo $lang['user_manage6'] ?></label>
													<input type="text" class="form-control" name="fname" id="fname" placeholder="<?php echo $lang['user_manage6'] ?>">
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label for="phoneNumber1"><?php echo $lang['user_manage7'] ?></label>
													<input type="text" class="form-control" id="lname" name="lname" placeholder="<?php echo $lang['user_manage7'] ?>">
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="emailAddress1"><?php echo $lang['user_manage5'] ?></label>
													<input type="text" class="form-control" id="email" name="email" placeholder="<?php echo $lang['user_manage5'] ?>">
												</div>
											</div>

											<div class="col-md-6">
												<div class="form-group">
													<label for="phoneNumber1"><?php echo $lang['user_manage9'] ?></label>
													<input type="tel" class="form-control" name="phone_custom" id="phone_custom" placeholder="<?php echo $lang['user_manage9'] ?>">

													<span id="valid-msg" class="hide"></span>
													<div id="error-msg" class="hide text-danger"></div>
												</div>
											</div>
										</div>

										<div class="row">

											<div class="col-md-6">
												<div class="form-group">
													<label for="phoneNumber1"><?php echo $lang['user_manage11'] ?></label>
													<select class="custom-select form-control" name="gender" id="gender" placeholder="<?php echo $lang['user_manage11'] ?>">
														<option value=""><?php echo $lang['leftorder177'] ?></option>
														<option value="Male"><?php echo $lang['leftorder179'] ?></option>
														<option value="Female"><?php echo $lang['leftorder178'] ?></option>
														<option value="Other"><?php echo $lang['leftorder180'] ?></option>
													</select>
												</div>
											</div>
											<div class="col-md-6">
											    <div class="form-group">
											        <label for="emailAddress1"><?php echo $lang['user_manage15'] ?></label>
                                                    <select class="custom-select form-control" id="userlevel" name="role" onchange="toggleDriverFields()" >
                                                        <option value="" selected disabled><?php echo $lang['left393']; ?></option>
                                                        <?php foreach ($roles as $role) : ?>
                                                            <option value="<?php echo $role->role_id; ?>"><?php echo isset($lang['role_'.$role->role_id]) ? $lang['role_'.$role->role_id] : $role->role_name; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
											    </div>
											</div>

										</div> 

                                        <div class="row" id="driverFields" style="display: none;">
										    <div class="col-md-6">
										        <div class="form-group">
										            <label for="emailAddress1"><?php echo $lang['edit-clien59'] ?></label>
										            <input type="text" class="form-control" id="enrollment" name="enrollment"  placeholder="<?php echo $lang['edit-clien59'] ?>">
										        </div>
										    </div>
										    <div class="col-md-6">
										        <div class="form-group">
										            <label for="phoneNumber1"><?php echo $lang['edit-clien60'] ?></label>
										            <input type="text" class="form-control" id="vehiclecode" name="vehiclecode" placeholder="<?php echo $lang['edit-clien60'] ?>" >
										        </div>
										    </div>
										</div>

                                        

										<input type="hidden" name="phone" id="phone" />


										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="phoneNumber1"><?php echo $lang['user_manage20'] ?></label>
													<div class="btn-group" data-toggle="buttons">
														<label class="btn">
															<div class="custom-control custom-radio">
																<input type="radio" id="customRadio2" class="custom-control-input" name="active" value="1" checked="checked">
																<label class="custom-control-label" for="customRadio2"> <?php echo $lang['user_manage16'] ?></label>
															</div>
														</label>
														<label class="btn">
															<div class="custom-control custom-radio">
																<input type="radio" id="customRadio1" class="custom-control-input" name="active" value="0">
																<label class="custom-control-label" for="customRadio1"> <?php echo $lang['user_manage17'] ?></label>
															</div>
														</label>

													</div>
												</div>
											</div>
											<div class="col-md-3">
												<div class="form-group">
													<label for="phoneNumber1"><?php echo $lang['user_manage23'] ?></label>
													<div class="btn-group" data-toggle="buttons">
														<label class="btn">
															<div class="custom-control custom-radio">
																<input type="radio" id="customRadio4" name="newsletter" value="1" checked="checked" class="custom-control-input">
																<label class="custom-control-label" for="customRadio4"> <?php echo $lang['user_manage21'] ?></label>
															</div>
														</label>
														<label class="btn">
															<div class="custom-control custom-radio">
																<input type="radio" id="customRadio5" name="newsletter" value="0" class="custom-control-input">
																<label class="custom-control-label" for="customRadio5"> <?php echo $lang['user_manage22'] ?></label>
															</div>
														</label>
													</div>
												</div>
											</div>
										</div>

										<div class="row">

											<div class="col-md-6">
												<div class="form-group">
													<label class="custom-control custom-checkbox">
														<input type="checkbox" class="custom-control-input" name="notify" id="notify" value="1">
														<i></i><?php echo $lang['edit-clien34'] ?>
														<span class="custom-control-indicator"></span><br><br>
														<label><span><i class="ti-email" style="color:#6610f2"></i>&nbsp; <?php echo $lang['edit-clien35'] ?></span></label>
													</label>
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="emailAddress1"><?php echo $lang['user_manage36'] ?></label>
													<textarea class="form-control" id="notes" name="notes" rows="6" name="notes" placeholder="<?php echo $lang['user_manage36'] ?>"></textarea>
												</div>
											</div>
										</div>

									</section>
									<div class="form-group">
										<div class="col-sm-12">
											<button class="btn btn-outline-danger btn-confirmation" id="save_data" name="save_data" type="submit"><?php echo $lang['user_manage37'] ?><span><i class="icon-ok"></i></span></button>
											<a href="users_list.php" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['user_manage30'] ?></a>
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

    
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

	<script src="dataJs/users_add.js"></script>


</body>

</html>