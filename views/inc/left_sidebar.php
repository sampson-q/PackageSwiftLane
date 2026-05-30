<aside class="left-sidebar">
	<!-- Sidebar scroll-->
	<div class="scroll-sidebar">
		<!-- Sidebar navigation-->
		<nav class="sidebar-nav">

			<?php if ($userData->userlevel == 9) { ?>
				<!-- User Profile SUPER ADMIN-->
				<ul id="sidebarnav">
					<li>
						<!-- User Profile-->
						<div class="user-profile d-flex no-block dropdown m-t-20">
							<div class="user-pic">
								<img src="<?php echo ($userData->avatar) ? $userData->avatar : "blank.png"; ?>" class="rounded-circle" width="50" />
							</div>
							<?php
							date_default_timezone_set("" . $core->timezone . "");
							$t = date("H");

							if ($t < 12) {
								$mensaje = '' . $lang['message1'] . '';
							} else if ($t < 18) {
								$mensaje = '' . $lang['message2'] . '';
							} else {
								$mensaje = '' . $lang['message3'] . '';
							}
							?>

							<div class="user-content hide-menu m-l-10">
								<a href="javascript:void(0)" class="" id="Userdd" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									<h5 class="m-b-0 user-name font-medium"><?php echo $mensaje; ?>,&nbsp;&nbsp;</h5>
									<span class="op-5 user-email"><?php echo $userData->fname; ?></span>
								</a>
							</div>
						</div>
					</li>
					<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
					<li class="p-15 m-t-10">
						<!-- <a href="courier_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center"> -->
						<a href="customer_packages_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center">
							<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu m-l-5"> <?php echo $lang['left-menu-sidebar-1'] ?> </span>
						</a>
					</li>
					<?php } ?>

					<li class="nav-small-cap"> <span class="hide-menu"></span></li>
                    
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="index.php" aria-expanded="false">
							<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2'] ?></span>
						</a>
					</li>
					<li class="sidebar-item">----------------------------------------</li>
                    <li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="locker_search.php" aria-expanded="false">
							<iconify-icon icon="ph:lockers-light" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-00'] . ' Search' ?></span>
						</a>
					</li>
                    <li class="sidebar-item">----------------------------------------</li>
                    <li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="pickup_client.php" aria-expanded="false">
							<iconify-icon icon="f7:tray-arrow-up-fill" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-65']?></span>
						</a>
					</li>

					<?php 

						$perModule = [
						'view_dashboard_pack',
						'add_package',
						'add_multiple_packages',
						'prealert_list',
						'view_package_list',
						'view_payment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>

                    <li class="sidebar-item">----------------------------------------</li>
                    <!-- Module online shopping-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mdi:airplane-takeoff" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-64'] ?></span>
						</a>

						<ul aria-expanded="false" class="collapse first-level">

							<?php if ($user->cdp_hasPermission('view_dashboard_pack')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_packages_customers.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-6'] ?></span>
								</a>
							</li>
							<?php } ?>


							<?php if ($user->cdp_hasPermission('prealert_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="prealert_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-7'] ?> </span>
								</a>
							</li>
							<?php } ?>


							<?php if ($user->cdp_hasPermission('add_package')) { ?>
							<li class="sidebar-item">
								<a href="customer_packages_add.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-8'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_package_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customer_packages_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-11'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_payment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>

						</ul>
					</li>

					<?php } ?>


					<?php 

						$perModule = [
						'view_dashboard_ship',
						'add_shipment',
						'add_multiple_shipments',
						'view_shipment_list',
						'view_payment_shipment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module shipment-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mingcute:ship-fill" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo 'Sea ' . $lang['left-menu-sidebar-13'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard_ship')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_shipments.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-14'] ?></span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
							<li class="sidebar-item">
								<a href="courier_add.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-15'] ?> </span>
								</a>
							</li>
							<?php } ?>

							

							<li class="sidebar-item">
								<a href="import_excel_add_courier.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['asingmoduleexcell1'] ?> </span>
								</a>
							</li>

							<?php if ($user->cdp_hasPermission('view_shipment_list')) { ?>
							<li class="sidebar-item">
								<a href="courier_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-16'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_payment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_courier_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>

						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_dashboard_pick',
						'add_full_pickup',
						'view_pickup_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module pickup-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-18'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard_pick')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_pickup.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-19'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_full_pickup')) { ?>
							<li class="sidebar-item">
								<a href="pickup_add_full.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-20'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_pickup_list')) { ?>
							<li class="sidebar-item">
								<a href="pickup_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-21'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>


					<?php 

						$perModule = [
						'view_dashboard_shipments',
						'view_consolidate_list',
						'add_consolidate_shipment',
						'payments_gateways_consolidate_shipment',
						'view_dashboard_packages',
						'view_consolidate_package',
						'add_consolidate_package',
						'payments_gateways_package_consolidate',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- CONSOLIDATE -->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mdi:consolidate" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-22'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<!-- Module consolidate shipment-->
							<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333310'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_list')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>


							<!-- Module consolidate package-->
							<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333312'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_package_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_package_consolidate')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_package_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

                    <li class="sidebar-item">----------------------------------------</li>
					<?php if ($user->cdp_hasPermission('view_general_reports')) { ?>
					<!-- Module general report-->	
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="reports.php" aria-expanded="false">
							<iconify-icon icon="solar:document-text-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-26'] ?></span>
						</a>
					</li>
					<?php } ?>

                    <li class="sidebar-item">----------------------------------------</li>
                    <?php if ($user->cdp_hasPermission('push_notifications')) { ?>
					<!-- push notifications-->
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="push_notifications.php" aria-expanded="false">
							<iconify-icon icon="mdi:message-text-fast-outline" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-66'] ?></span>
						</a>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_dashboard',
						'view_receivable_accounts',
						'view_global_payments',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module account receivaible-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-27'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_account.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-28'] ?></span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_receivable_accounts')) { ?>
							<li class="sidebar-item">
								<a href="accounts_receivable.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-29'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_global_payments')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="global_payments_gateways.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 
						// Agencia (userlevel 6) SIEMPRE ve Lista de clientes y Destinatarios; otros roles por permiso
						$showCustomerRecipients = ($userData->userlevel == 6) || $user->cdp_hasPermission(['view_client_list', 'view_recipients']);
						if ($showCustomerRecipients) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module customer and recipints list-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:users-group-two-rounded-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-30'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if (($userData->userlevel == 6) || $user->cdp_hasPermission('view_client_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customers_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-31'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if (($userData->userlevel == 6) || $user->cdp_hasPermission('view_recipients')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="recipients_admin_list.php" aria-expanded="false"><iconify-icon icon="solar:users-group-two-rounded-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-62'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_user_list',
						'add_user',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module user list-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:settings-minimalistic-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-33'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_user_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="users_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-34'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_user')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="users_add.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-35'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>


					<?php 

						$perModule = [
						'view_role_assignment',
						'view_module_permissions',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module roles and permissions-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:layers-line-duotone" class="fs-6 m-r-5 m-l-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['asingmodule13'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_role_assignment')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="permissions_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['rolesp9'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_module_permissions')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="asingrole_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['rolesp23'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>
                    <li class="sidebar-item">----------------------------------------</li>
                    <?php if ($user->cdp_hasPermission('view_general_reports')) { ?>
					<!-- Module general report-->	
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="warehouse.php" aria-expanded="false">
							<iconify-icon icon="mdi:warehouse" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo 'Warehouse View' ?></span>
						</a>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'configurations_all',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
                    <li class="sidebar-item">----------------------------------------</li>
					<!-- Module generalconfiguracion  -->
					<li class="nav-small-cap">
						<iconify-icon icon="solar:menu-dots-bold" class="fs-5"></iconify-icon>
						<span class="hide-menu"><?php echo $lang['left-menu-sidebar-37'] ?></span>
					</li>

					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:settings-outline" class="fs-5" style="color:#89D91B"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-38'] ?></span>
						</a>

						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_tools')) { ?>
							<!-- Module generalconfiguracion  -->
                                <li class="sidebar-item">
                                        <a class="sidebar-link waves-effect waves-dark" href="tools.php" aria-expanded="false">
                                                <iconify-icon icon="solar:settings-minimalistic-linear" class="fs-5"></iconify-icon>
                                                <span class="hide-menu"> <?php echo $lang['left-menu-sidebar-39'] ?> </span>
                                        </a>
                                </li>
                                
                                                   
							<?php } ?>


							<?php 

								$perModule = [
								'view_offices',
								'view_branches',
								'view_courier_companies',
								'view_packaging',
								'view_shipping_modes',
								'view_delivery_times',
								'view_statuses',
								'view_categories',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module logistic configuracion  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-42'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_offices')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="offices_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-43'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_branches')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="branchoffices_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-44'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_courier_companies')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="courier_company_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-45'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_packaging')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="packaging_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-54'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_shipping_modes')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="shipping_mode_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-55'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_delivery_times')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="delivery_time_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-56'] ?></span>
										</a>
									
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_statuses')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="status_courier_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-46'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_categories')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="category_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-47'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND  Module logistic configuracion  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_taxes_and_fees',
								'view_shipping_tariffs',
								'track_invoices',
								'manage_default_shipping_info',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module shipping configuracion  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-48'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_taxes_and_fees')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="taxesadnfees.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-49'] ?> </span>
										</a>
									</li> 
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_shipping_tariffs')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark sidebar-link" href="shipping_tariffs_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-53'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('track_invoices')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="track_invoice.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-50'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_default_shipping_info')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="info_ship_default.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-51'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND Module shipping configuracion  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_payment_modes',
								'manage_payment_methods',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module payments  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-40'] ?></span>
								</a>

								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_payment_modes')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark sidebar-link" href="payment_mode_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-40'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_payment_methods')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payment_methods_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-41'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND Module payments  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_countries',
								'manage_states',
								'manage_cities',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- MODULE LOCATIONS  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">

									<iconify-icon icon="solar:map-point-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-57'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_countries')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="countries_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-58'] ?> </span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_states')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="states_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-59'] ?> </span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_cities')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="cities_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-60'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- end MODULE LOCATIONS  -->
							<?php } ?>
						</ul>
					</li>

					<?php } ?>

					<?php 
						$perModule = [
						'manage_api_settings',
						'manage_api_clients',
						'view_api_sessions',
						'view_api_logs',
						];
						if ($user->cdp_hasPermission($perModule)) {
					?>
					<!-- (API Administration module removed) -->
					<?php } ?>

					<!-- <?php if ($user->cdp_hasPermission('verify_updates')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="verify_update.php" aria-expanded="false">
							<iconify-icon icon="solar:info-circle-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-61'] ?></span>
						</a>
					</li>
					<?php } ?> -->
				</ul>


			<?php } else if (in_array($userData->userlevel, [2, 4, 6])) { ?>

				<!-- User Profile ADMINISTRATOR / EMPLOYEE / AGENCY (permisos por rol)-->
				<ul id="sidebarnav">
					<li>
						<!-- User Profile-->
						<div class="user-profile d-flex no-block dropdown m-t-20">
							<div class="user-pic">
								<img src="<?php echo ($userData->avatar) ? $userData->avatar : "uploads/blank.png"; ?>" class="rounded-circle" width="50" />
							</div>
							<?php
							date_default_timezone_set("" . $core->timezone . "");
							$t = date("H");

							if ($t < 12) {
								$mensaje = '' . $lang['message1'] . '';
							} else if ($t < 18) {
								$mensaje = '' . $lang['message2'] . '';
							} else {
								$mensaje = '' . $lang['message3'] . '';
							}
							?>

							<div class="user-content hide-menu m-l-10">
								<a href="javascript:void(0)" class="" id="Userdd" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									<h5 class="m-b-0 user-name font-medium"><?php echo $mensaje; ?>,&nbsp;&nbsp;</h5>
									<span class="op-5 user-email"><?php echo $userData->fname; ?><br></span>
									<span class="op-5 user-email"><?php echo $lang['left-menu-sidebar-0'] ?>: <strong><?php echo $userData->name_off; ?></strong></span>
								</a>
							</div>
						</div>
						<!-- End User Profile-->
					</li>



					<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
					<li class="p-15 m-t-10">
						<!-- <a href="courier_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center"> -->
						<a href="customer_packages_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center">
							<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu m-l-5"> <?php echo $lang['left-menu-sidebar-1'] ?> </span>
						</a>
					</li>
					<?php } ?>

					<li class="nav-small-cap"> <span class="hide-menu"></span></li>

					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="index.php" aria-expanded="false">
							<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2'] ?></span>
						</a>
					</li>

					<?php 

						$perModule = [
						'view_dashboard_pack',
						'add_package',
						'add_multiple_packages',
						'prealert_list',
						'view_package_list',
						'view_payment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>

                    <!-- Module online shopping-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:cart-large-2-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-5'] ?></span>
						</a>

						<ul aria-expanded="false" class="collapse  first-level">

							<?php if ($user->cdp_hasPermission('view_dashboard_pack')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_packages_customers.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-6'] ?></span>
								</a>
							</li>
							<?php } ?>


							<?php if ($user->cdp_hasPermission('prealert_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="prealert_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-7'] ?> </span>
								</a>
							</li>
							<?php } ?>


							<?php if ($user->cdp_hasPermission('add_package')) { ?>
							<li class="sidebar-item">
								<a href="customer_packages_add.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-8'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_package_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customer_packages_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-11'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_payment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>

						</ul>
					</li>

					<?php } ?>


					<?php 

						$perModule = [
						'view_dashboard_ship',
						'add_shipment',
						'add_multiple_shipments',
						'view_shipment_list',
						'view_payment_shipment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module shipment-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-13'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard_ship')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_shipments.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-14'] ?></span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
							<li class="sidebar-item">
								<a href="courier_add.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-15'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_shipment_list')) { ?>
							<li class="sidebar-item">
								<a href="courier_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-16'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_payment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_courier_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>

						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_dashboard_pick',
						'add_full_pickup',
						'view_pickup_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module pickup-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-18'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard_pick')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_pickup.php" class="sidebar-link">
									<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-19'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_full_pickup')) { ?>
							<li class="sidebar-item">
								<a href="pickup_add_full.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-20'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_pickup_list')) { ?>
							<li class="sidebar-item">
								<a href="pickup_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-21'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>


					<?php 

						$perModule = [
						'view_dashboard_shipments',
						'view_consolidate_list',
						'add_consolidate_shipment',
						'payments_gateways_consolidate_shipment',
						'view_dashboard_packages',
						'view_consolidate_package',
						'add_consolidate_package',
						'payments_gateways_package_consolidate',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- CONSOLIDATE -->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mdi:consolidate" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-22'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<!-- Module consolidate shipment-->
							<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333310'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_list')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>


							<!-- Module consolidate package-->
							<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333312'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_package_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_package_consolidate')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_package_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>


					<?php if ($user->cdp_hasPermission('view_general_reports')) { ?>
					<!-- Module general report-->	
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="reports.php" aria-expanded="false">
							<iconify-icon icon="solar:document-text-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-26'] ?></span>
						</a>
					</li>
					<?php } ?>

                    <?php if ($user->cdp_hasPermission('push_notifications')) { ?>
					<!-- push notifications-->
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="push_notifications.php" aria-expanded="false">
							<iconify-icon icon="mdi:message-text-fast-outline" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-66'] ?></span>
						</a>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_dashboard',
						'view_receivable_accounts',
						'view_global_payments',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module account receivaible-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-27'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_dashboard')) { ?>
							<li class="sidebar-item">
								<a href="dashboard_admin_account.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-28'] ?></span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_receivable_accounts')) { ?>
							<li class="sidebar-item">
								<a href="accounts_receivable.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-29'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_global_payments')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="global_payments_gateways.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 
						// Agencia (userlevel 6) SIEMPRE ve Lista de clientes y Destinatarios; otros roles por permiso
						$showCustomerRecipients = ($userData->userlevel == 6) || $user->cdp_hasPermission(['view_client_list', 'view_recipients']);
						if ($showCustomerRecipients) {

					?>
					<!-- Module customer and recipints list-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:users-group-two-rounded-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-30'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if (($userData->userlevel == 6) || $user->cdp_hasPermission('view_client_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customers_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-31'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if (($userData->userlevel == 6) || $user->cdp_hasPermission('view_recipients')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="recipients_admin_list.php" aria-expanded="false"><iconify-icon icon="solar:users-group-two-rounded-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-62'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_user_list',
						'add_user',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module user list-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:settings-minimalistic-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-33'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_user_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="users_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-34'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('add_user')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="users_add.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-35'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'configurations_all',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module generalconfiguracion  -->
					<li class="nav-small-cap">
						<iconify-icon icon="solar:menu-dots-bold" class="fs-5"></iconify-icon>
						<span class="hide-menu"><?php echo $lang['left-menu-sidebar-37'] ?></span>
					</li>

					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:settings-outline" class="fs-5" style="color:#89D91B"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-38'] ?></span>
						</a>

						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('view_tools')) { ?>
							<!-- Module generalconfiguracion  -->
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="tools.php" aria-expanded="false">
									<iconify-icon icon="solar:settings-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-39'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php 

								$perModule = [
								'view_offices',
								'view_branches',
								'view_courier_companies',
								'view_packaging',
								'view_shipping_modes',
								'view_delivery_times',
								'view_statuses',
								'view_categories',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module logistic configuracion  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-42'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_offices')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="offices_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-43'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_branches')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="branchoffices_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-44'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_courier_companies')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="courier_company_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-45'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_packaging')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="packaging_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-54'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_shipping_modes')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="shipping_mode_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-55'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_delivery_times')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="delivery_time_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-56'] ?></span>
										</a>
									
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_statuses')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="status_courier_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-46'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_categories')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="category_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-47'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND  Module logistic configuracion  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_taxes_and_fees',
								'view_shipping_tariffs',
								'track_invoices',
								'manage_default_shipping_info',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module shipping configuracion  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-48'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_taxes_and_fees')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="taxesadnfees.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-49'] ?> </span>
										</a>
									</li> 
									<?php } ?>
									<?php if ($user->cdp_hasPermission('view_shipping_tariffs')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark sidebar-link" href="shipping_tariffs_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-53'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('track_invoices')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="track_invoice.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-50'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_default_shipping_info')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="info_ship_default.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-51'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND Module shipping configuracion  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_payment_modes',
								'manage_payment_methods',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module payments  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:wallet-money-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-40'] ?></span>
								</a>

								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_payment_modes')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark sidebar-link" href="payment_mode_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-40'] ?></span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_payment_methods')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payment_methods_list.php">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-41'] ?></span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- AND Module payments  -->
							<?php } ?>

							<?php 

								$perModule = [
								'manage_countries',
								'manage_states',
								'manage_cities',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- MODULE LOCATIONS  -->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">

									<iconify-icon icon="solar:map-point-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-57'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('manage_countries')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="countries_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-58'] ?> </span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_states')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="states_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-59'] ?> </span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('manage_cities')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="cities_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-60'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<!-- end MODULE LOCATIONS  -->
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 
						$perModule = [
						'manage_api_settings',
						'manage_api_clients',
						'view_api_sessions',
						'view_api_logs',
						];
						if ($user->cdp_hasPermission($perModule)) {
					?>
					<!-- (API Administration module removed) -->
					<?php } ?>

					<!-- <?php if ($user->cdp_hasPermission('verify_updates')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="verify_update.php" aria-expanded="false">
							<iconify-icon icon="solar:info-circle-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-61'] ?></span>
						</a>
					</li>
					<?php } ?> -->

				</ul>


			<?php } else if ($userData->userlevel == 1) { ?>
				<!-- User Profile CUSTOMER-->
				<ul id="sidebarnav">
					<li>
						<!-- User Profile-->
						<div class="user-profile d-flex no-block dropdown m-t-20">
							<div class="user-pic">
								<img src="<?php echo ($userData->avatar) ? $userData->avatar : "uploads/blank.png"; ?>" class="rounded-circle" width="50" />
							</div>
							<?php
							date_default_timezone_set("" . $core->timezone . "");
							$t = date("H");

							if ($t < 12) {
								$mensaje = '' . $lang['message1'] . '';
							} else if ($t < 18) {
								$mensaje = '' . $lang['message2'] . '';
							} else {
								$mensaje = '' . $lang['message3'] . '';
							}
							?>

							<div class="user-content hide-menu m-l-10">
								<a href="javascript:void(0)" class="" id="Userdd" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									<h5 class="m-b-0 user-name font-medium"><?php echo $mensaje; ?>,&nbsp;&nbsp;</h5>
									<span class="op-5 user-email"><?php echo $userData->fname; ?></span>
									<br><?php echo $lang['left-menu-sidebar-00'] ?> <b><?php echo $userData->locker; ?></b>
								</a>
							</div>
						</div>
						<!-- End User Profile-->
					</li>

					<?php if ($user->cdp_hasPermission('pickup_add')) { ?>
					<!-- Module add pickup-->
					<li class="p-15 m-t-10">
						<a href="pickup_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center">
							<iconify-icon icon="solar:delivery-linear" style="font-size: 20px"></iconify-icon>
							<span class="hide-menu"> &nbsp; <?php echo $lang['left-menu-sidebar-20'] ?></span>
						</a>
					</li>
					<?php } ?>

					<!-- Module dashbpard client-->
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="index.php" aria-expanded="false"><iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2'] ?> </span>
						</a>
					</li>

					<?php 

						$perModule = [
						'prealert_add',
						'prealert_list',
						'view_payment_list',
						'view_package_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module pre-alerts-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false"><iconify-icon icon="mdi:airplane-takeoff" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo 'Air Shipping' ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('prealert_add')) { ?>
							<li class="sidebar-item">
								<a href="prealert_add.php" class="sidebar-link"><iconify-icon icon="solar:bell-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-10'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('prealert_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="prealert_list.php" aria-expanded="false"><iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-7'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_package_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customer_packages_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-11'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_payment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'courier_add_client',
						'view_shipment_list',
						'view_payment_shipment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module pre-alerts-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false"><iconify-icon icon="mingcute:ship-fill" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo 'Sea ' . $lang['left-menu-sidebar-13'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('courier_add_client')) { ?>
							<li class="sidebar-item">
								<a href="courier_add_client.php" class="sidebar-link"><iconify-icon icon="solar:box-minimalistic-linear" class="fs-5" style="color:#f62d51"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-15'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_shipment_list')) { ?>
							<li class="sidebar-item">
								<a href="courier_list.php" class="sidebar-link"><iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-16'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_payment_shipment_list')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_courier_list.php" aria-expanded="false"><iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'pickup_add_',
						'view_pickup_list_',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module PICKUP CLIENT-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false"><iconify-icon icon="mdi:courier-fast" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-18'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('pickup_add')) { ?>
							<li class="sidebar-item">
								<a href="pickup_add.php" class="sidebar-link"><iconify-icon icon="solar:delivery-linear" class="fs-5" style="color:#f62d51"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-20'] ?> </span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('view_pickup_list')) { ?>
							<li class="sidebar-item">
								<a href="pickup_list.php" class="sidebar-link"><iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-21'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_consolidate_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- Module all consolidate-->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mdi:consolidate" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-22'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php 

								$perModule = [
								'payments_gateways_consolidate_shipment',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module consolidate shipments-->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333310'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_consolidate_list')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>
									<?php if ($user->cdp_hasPermission('payments_gateways_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>

							<?php 

								$perModule = [
								'view_consolidate_package_list',
								'payments_gateways_package_consolidate',
								];
								if ($user->cdp_hasPermission($perModule)) {

							?>
							<!-- Module consolidate packages-->
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333312'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_consolidate_package_list')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_package_consolidate')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_package_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php if ($user->cdp_hasPermission('view_recipients')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="recipients_list.php" aria-expanded="false"><iconify-icon icon="solar:users-group-two-rounded-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-62'] ?> </span>
						</a>
					</li>
					<?php } ?>

					<?php if ($user->cdp_hasPermission('customers_profile_edit')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="customers_profile_edit.php?user=<?php echo $userData->id; ?>" aria-expanded="false"><iconify-icon icon="solar:user-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-63'] ?> </span>
						</a>
					</li>
					<?php } ?>
					
                    <?php if ($user->cdp_hasPermission('client_virtual_mail_box_addresses')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="client_virtual_mail_box_addresses.php" aria-expanded="false"><iconify-icon icon="ph:mailbox-light"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['virtual_mailbox-7'] ?> </span>
						</a>
					</li>
					<?php } ?>
				</ul>



				<!-- User Profile DRIVER-->
			<?php } else if ($userData->userlevel == 3) { ?>
				<ul id="sidebarnav">
					<!-- User Profile-->
					<li>
						<!-- User Profile-->
						<div class="user-profile d-flex no-block dropdown m-t-20">
							<div class="user-pic">
								<img src="<?php echo ($userData->avatar) ? $userData->avatar : "uploads/blank.png"; ?>" class="rounded-circle" width="50" />
							</div>
							<?php
							date_default_timezone_set("" . $core->timezone . "");
							$t = date("H");

							if ($t < 12) {
								$mensaje = '' . $lang['message1'] . '';
							} else if ($t < 18) {
								$mensaje = '' . $lang['message2'] . '';
							} else {
								$mensaje = '' . $lang['message3'] . '';
							}
							?>

							<div class="user-content hide-menu m-l-10">

								<a href="javascript:void(0)" class="" id="Userdd" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									<h5 class="m-b-0 user-name font-medium"><?php echo $mensaje; ?>,&nbsp;&nbsp;</h5>
									<span class="op-5 user-email"><?php echo $userData->fname; ?></span>
								</a>
							</div>
						</div>
						<!-- End User Profile-->
					</li>

					<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
					<li class="p-15 m-t-10">
						<!-- <a href="courier_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center"> -->
						<a href="customer_packages_add.php" class="btn btn-block create-btn text-white no-block d-flex align-items-center">
							<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon> <span class="hide-menu m-l-5"> <?php echo $lang['left-menu-sidebar-1'] ?> </span> </a>
					</li>
					<?php } ?>

					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="index.php" aria-expanded="false">
							<iconify-icon icon="solar:widget-4-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2'] ?> </span>
						</a>
					</li>

					<?php if ($user->cdp_hasPermission('view_package_list')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:programming-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-5'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<li class="sidebar-item">
								<a class="sidebar-link waves-effect waves-dark" href="customer_packages_list.php" aria-expanded="false">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-11'] ?> </span>
								</a>
							</li>

						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'add_shipment',
						'view_shipment_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-13'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('add_shipment')) { ?>
							<li class="sidebar-item">
								<a href="courier_add.php" class="sidebar-link">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5" style="color:#f62d51"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-15'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_shipment_list')) { ?>
							<li class="sidebar-item">
								<a href="courier_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-16'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>


					<?php 

						$perModule = [
						'add_full_pickup',
						'view_pickup_list',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:delivery-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-18'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<?php if ($user->cdp_hasPermission('add_full_pickup')) { ?>
							<li class="sidebar-item">
								<a href="pickup_add_full.php" class="sidebar-link">
									<iconify-icon icon="solar:delivery-linear" class="fs-5" style="color:#f62d51"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-20'] ?> </span>
								</a>
							</li>
							<?php } ?>

							<?php if ($user->cdp_hasPermission('view_pickup_list')) { ?>
							<li class="sidebar-item">
								<a href="pickup_list.php" class="sidebar-link">
									<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-21'] ?> </span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'view_dashboard_shipments',
						'view_consolidate_list',
						'add_consolidate_shipment',
						'payments_gateways_consolidate_shipment',
						'view_dashboard_packages',
						'view_consolidate_package',
						'add_consolidate_package',
						'payments_gateways_package_consolidate',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<!-- CONSOLIDATE -->
					<li class="sidebar-item">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="mdi:consolidate" class="fs-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['left-menu-sidebar-22'] ?></span>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<!-- Module consolidate shipment-->
							<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333310'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_shipments')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_list')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_consolidate_shipment')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>


							<!-- Module consolidate package-->
							<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
							<li class="sidebar-item">
								<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
									<iconify-icon icon="solar:box-minimalistic-linear" class="fs-5"></iconify-icon>
									<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-2233333312'] ?></span>
								</a>
								<ul aria-expanded="false" class="collapse  first-level">
									<?php if ($user->cdp_hasPermission('view_dashboard_packages')) { ?>
									<li class="sidebar-item">
										<a href="dashboard_admin_package_consolidated.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-23'] ?></span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('view_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_list.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-24'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('add_consolidate_package')) { ?>
									<li class="sidebar-item">
										<a href="consolidate_package_add.php" class="sidebar-link">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-25'] ?> </span>
										</a>
									</li>
									<?php } ?>

									<?php if ($user->cdp_hasPermission('payments_gateways_package_consolidate')) { ?>
									<li class="sidebar-item">
										<a class="sidebar-link waves-effect waves-dark" href="payments_gateways_package_consolidate_list.php" aria-expanded="false">
											<iconify-icon icon="solar:alt-arrow-right-outline" class="fs-5" style="color:#fc3f7"></iconify-icon>
											<span class="hide-menu"><?php echo $lang['left-menu-sidebar-12'] ?> </span>
										</a>
									</li>
									<?php } ?>
								</ul>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php if ($user->cdp_hasPermission('view_general_reports')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="reports.php" aria-expanded="false">
							<iconify-icon icon="solar:document-text-linear" class="fs-5" style="color:#fb8c00"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-26'] ?></span>
						</a>
					</li>
					<?php } ?>

                    <?php if ($user->cdp_hasPermission('push_notifications')) { ?>
					<!-- push notifications-->
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="push_notifications.php" aria-expanded="false">
							<iconify-icon icon="mdi:message-text-fast-outline" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['left-menu-sidebar-66'] ?></span>
						</a>
					</li>
					<?php } ?>

					<?php if ($user->cdp_hasPermission('drivers_edit')) { ?>
					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark" href="drivers_edit.php?user=<?php echo $userData->id; ?>" aria-expanded="false">
							<iconify-icon icon="solar:user-linear" class="fs-5"></iconify-icon>
							<span class="hide-menu"> <?php echo $lang['leftorder195'] ?> </span>
						</a>
					</li>
					<?php } ?>


					<li class="sidebar-item">
						<a class="sidebar-link waves-effect waves-dark sidebar-link" href="logout.php" aria-expanded="false">
							<iconify-icon icon="solar:login-2-outline" class="m-r-5 m-l-5"></iconify-icon>
							<span class="hide-menu"><?php echo $lang['wout'] ?></span>
						</a>
					</li>
				</ul>
			<?php } ?>
		</nav>
		<!-- End Sidebar navigation -->
	</div>
	<!-- End Sidebar scroll-->
</aside>