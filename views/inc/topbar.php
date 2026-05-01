	<header class="topbar <?php echo isset($show_dashboard_new_header) && $show_dashboard_new_header ? 'topbar-new-variant' : ''; ?>">
		<nav class="navbar top-navbar navbar-expand-md <?php echo isset($show_dashboard_new_header) && $show_dashboard_new_header ? 'navbar-light' : 'navbar-dark'; ?>">
				<!-- This is for the sidebar toggle which is visible on mobile only -->
			<div class="navbar-header">
				<a class="nav-toggler waves-effect waves-light d-block d-md-none" href="javascript:void(0)"><iconify-icon icon="solar:hamburger-menu-linear"></iconify-icon></a>
				<a class="navbar-brand d-flex align-items-center" href="index.php">
					<?php if (isset($show_dashboard_new_header) && $show_dashboard_new_header) { ?>
					<span class="logo-v-new me-2">V</span>
						<!-- dark Logo text -->
					<?php } ?>
					<span class="logo-text">
						<?php echo ($core->logo) ? '<img src="assets/' . $core->logo . '" alt="' . $core->site_name . '" width="' . $core->thumb_w . '" height="' . $core->thumb_h . '"/>' : $core->site_name; ?>
					</span>
				</a>
				<?php if (isset($show_dashboard_new_header) && $show_dashboard_new_header) { ?>
				<div class="topbar-search-new d-none d-md-flex align-items-center">
					<iconify-icon icon="solar:magnifer-linear" class="text-muted me-2"></iconify-icon>
					<input type="text" class="form-control border-0 bg-transparent p-0" placeholder="Search [CTRL + K]" readonly aria-label="Search">
			<!-- ============================================================== -->
			<!-- End Logo -->
				<!-- ============================================================== -->
				<!-- toggle and nav items -->
				<!-- ============================================================== -->
			<!-- ============================================================== -->
				</div>
				<?php } ?>
				<a class="topbartoggler d-block d-md-none waves-effect waves-light" href="javascript:void(0)" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><iconify-icon icon="solar:menu-dots-bold"></iconify-icon></a>
			</div>
			<div class="navbar-collapse collapse" id="navbarSupportedContent">
				<ul class="navbar-nav float-left mr-auto">
					<?php if (!isset($show_dashboard_new_header) || !$show_dashboard_new_header) { ?>
					<li class="nav-item d-none d-md-block"><a class="nav-link sidebartoggler waves-effect waves-light" href="javascript:void(0)" data-sidebartype="mini-sidebar"><iconify-icon icon="solar:hamburger-menu-linear" class="font-24"></iconify-icon></a></li>
					<?php } else { ?>
				<!-- ============================================================== -->
				<!-- Right side toggle and nav items -->
				<!-- ============================================================== -->
					<li class="nav-item d-none d-md-block"><a class="nav-link sidebartoggler waves-effect waves-dark" href="javascript:void(0)" data-sidebartype="mini-sidebar"><iconify-icon icon="solar:hamburger-menu-linear" class="font-24"></iconify-icon></a></li>
					<?php } ?>
				</ul>
				<ul class="navbar-nav float-right">
					<?php if (isset($show_dashboard_new_header) && $show_dashboard_new_header) { ?>
					<li class="nav-item d-none d-md-block"><a class="nav-link waves-effect waves-dark text-body" href="javascript:void(0)"><iconify-icon icon="solar:sun-2-linear" class="font-22"></iconify-icon></a></li>
					<li class="nav-item d-none d-md-block"><a class="nav-link waves-effect waves-dark text-body" href="javascript:void(0)"><iconify-icon icon="solar:widget-4-linear" class="font-22"></iconify-icon></a></li>
					<?php } ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown2" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<?php if ($core->language == "en") { ?>
								<img src="assets/template/assets/icon-flag/us.png" width="34" />
							<?php } else if ($core->language == "es") { ?>
								<img src="assets/template/assets/icon-flag/es.png" width="34" />
							<?php } else if ($core->language == "ar") { ?>
								<img src="assets/template/assets/icon-flag/ar.png" width="34" />
							<?php } else if ($core->language == "he") { ?>
								<img src="assets/template/assets/icon-flag/he.png" width="34" />
							<?php } else if ($core->language == "fr") { ?>
								<img src="assets/template/assets/icon-flag/fr.png" width="34" />
							<?php } ?>
						</a>
					</li>
					<!-- ============================================================== -->
					<!-- Comment -->
					<!-- ============================================================== -->

					<li class="nav-item dropdown">
						<a id="clickme" class="nav-link dropdown-toggle waves-effect waves-dark" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<img src="assets/images/alert/bell.png" width="26" />
							<span class="badge badge-notify badge-sm up badge-light pull-top-xs" id="countNotifications">0</span>
						</a>

						<div class="dropdown-menu dropdown-menu-right mailbox animated bounceInDown">
							<div id="ajax_response"></div>
						</div>

					</li>
					<?php if (isset($show_dashboard_new_header) && $show_dashboard_new_header) { ?>
					<li class="nav-item d-none d-md-block"><a class="nav-link waves-effect waves-dark text-body" href="javascript:void(0)"><iconify-icon icon="solar:settings-outline" class="font-22"></iconify-icon></a></li>
					<?php } ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle text-muted waves-effect waves-dark pro-pic" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="<?php echo ($userData->avatar) ? $userData->avatar : "uploads/blank.png"; ?>" class="rounded-circle" width="36" height="36" />&nbsp;<?php if (empty($show_dashboard_new_header)) { ?><iconify-icon icon="solar:alt-arrow-down-outline"></iconify-icon><?php } ?></a>
						<div class="dropdown-menu dropdown-menu-right user-dd animated flipInY">
							<span class="with-arrow"><span class="bg-primary"></span></span>
							<div class="d-flex no-block align-items-center p-15 bg-primary text-white m-b-10">
								<div class="">
									<img src="<?php echo ($userData->avatar) ? $userData->avatar : "uploads/blank.png"; ?>" class="rounded-circle" width="80" />
								</div>
								<div class="m-l-10">
									<h4 class="m-b-0"><?php echo $userData->username; ?></h4>
									<p class=" m-b-0"><?php echo $userData->email; ?></p>
								</div>
							</div>

							<?php
							if ($userData->userlevel == 9 || $userData->userlevel == 2) {
							?>
								<a class="dropdown-item" href="users_edit.php?user=<?php echo $userData->id; ?>">
									<iconify-icon icon="solar:user-linear" class="m-r-5 m-l-5"></iconify-icon> <?php echo $lang['miprofile'] ?></a>
							<?php
							} else	if ($userData->userlevel == 1) {

							?>
								<a class="dropdown-item" href="customers_profile_edit.php?user=<?php echo $userData->id; ?>">
									<iconify-icon icon="solar:user-linear" class="m-r-5 m-l-5"></iconify-icon> <?php echo $lang['miprofile'] ?></a>
							<?php

							} else	if ($userData->userlevel == 3) {

							?>
								<a class="dropdown-item" href="drivers_edit.php?user=<?php echo $userData->id; ?>">
									<iconify-icon icon="solar:user-linear" class="m-r-5 m-l-5"></iconify-icon> <?php echo $lang['miprofile'] ?></a>
							<?php
							} else {
							?>
								<a class="dropdown-item" href="users_edit.php?user=<?php echo $userData->id; ?>">
									<iconify-icon icon="solar:user-linear" class="m-r-5 m-l-5"></iconify-icon> <?php echo $lang['miprofile'] ?></a>
							<?php
							}
							?>


							<div class="dropdown-divider"></div>
							<?php
							if ($user->cdp_hasPermission('view_user_list')) {
							?>
								<a class="dropdown-item" href="users_list.php">
									<iconify-icon icon="solar:settings-outline" class="m-r-5 m-l-5"></iconify-icon> <?php echo $lang['accountset'] ?></a>
								<div class="dropdown-divider"></div>
							<?php
							}
							?>

							<a class="dropdown-item" href="logout.php"><iconify-icon icon="solar:login-2-outline" class="m-r-5 m-l-5"></iconify-icon>
								<?php echo $lang['logoouts'] ?></a>
						</div>
					</li>
					<!-- ============================================================== -->
					<!-- User profile and search -->
					<!-- ============================================================== -->
				</ul>
			</div>
		</nav>
	</header>

	<audio id="chatAudio">
		<source src="assets/notify.mp3" type="audio/mpeg">
	</audio>


	<!-- <script src="dataJs/load_notifications_all.js"> </script> -->