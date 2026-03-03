	<style>
		.menutext {
			color: #666;
			overflow: hidden;
		}
		.left-part iconify-icon.tools-menu-icon {
			font-size: 1.25rem;
			margin-right: 10px;
			min-width: 1.25rem;
		}
	</style>

	<aside class="left-part">
		<a class="btn btn-success show-left-part d-block d-md-none" href="javascript:void(0)"><iconify-icon icon="solar:hamburger-menu-linear"></iconify-icon></a>
		<div class="scroll-sidebar">

			<div class="p-15">
				<a id="compose_mail" class="waves-effect waves-light btn btn-danger d-block" href=""><?php echo $lang['apis04'] ?></a>
			</div>

			<div class="divider"></div>
			<?php 

				$perModule = [
				'edit_general_config',
				'edit_company_config',
				'edit_seo_config',
				'edit_logo_config',
				'edit_email_config',
				'edit_whatsapp_config',
				'edit_sms_config',
				];
				if ($user->cdp_hasPermission($perModule)) {

			?>
			<!-- Sidebar navigation-->
			<nav class="sidebar-nav idebar-collapse">
				<ul class="list-group" id="sidebarnav">
					<li>
						<small class="p-15 grey-text text-lighten-1 db"></small>
					</li>
					
					<?php if ($user->cdp_hasPermission('edit_general_config')) { ?>
					<li class="list-group-item sidebar-item">
						<a href="tools.php?list=config_general" class="list-group-item-action"><iconify-icon icon="solar:settings-minimalistic-linear" class="tools-menu-icon"></iconify-icon> <?php echo $lang['left601'] ?></a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_company_config')) { ?>
					<li class="list-group-item sidebar-item">
						<a href="tools.php?list=config" class="list-group-item-action"><iconify-icon icon="solar:building-2-linear" class="tools-menu-icon"></iconify-icon> <?php echo $lang['setcompany'] ?></a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_seo_config')) { ?>
					<li class="list-group-item sidebar-item">
						<a href="tools.php?list=config_seo" class="list-group-item-action"><iconify-icon icon="solar:global-linear" class="tools-menu-icon"></iconify-icon> <?php echo $lang['metaseo1'] ?></a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_logo_config')) { ?>
					<li class="list-group-item sidebar-item">
						<a href="tools.php?list=configlogo" class="list-group-item-action"><iconify-icon icon="solar:gallery-linear" class="tools-menu-icon"></iconify-icon> <?php echo $lang['setcompanylogo'] ?></a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_email_config')) { ?>
					<li class="list-group-item sidebar-item">
						<a href="tools.php?list=config_email" class="list-group-item-action"><iconify-icon icon="solar:letter-linear" class="tools-menu-icon"></iconify-icon> <?php echo $lang['leftemail'] ?></a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_whatsapp_config')) { ?>
					<li class="list-group-item  sidebar-item">
						<a href="config_whatsapp.php" class="list-group-item-action">
							<iconify-icon icon="solar:chat-round-dots-linear" class="tools-menu-icon"></iconify-icon>
							<?php echo $lang['ws-add-text22'] ?>
						</a>
					</li>
					<?php } ?>
					<?php if ($user->cdp_hasPermission('edit_sms_config')) { ?>
					<li class="list-group-item  sidebar-item">
						<a href="config_sms.php" class="list-group-item-action">
							<iconify-icon icon="solar:chat-square-linear" class="tools-menu-icon"></iconify-icon>
							<?php echo $lang['ws-add-text30'] ?>
						</a>
					</li>
					<?php } ?>

					<?php 

						$perModule = [
						'edit_email_templates',
						'manage_whatsapp_templates',
						'edit_sms_templates',
						];
						if ($user->cdp_hasPermission($perModule)) {

					?>
					<li class="list-group-item sidebar-item">
						<a class="list-group-item-action has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:playlist-2-linear" class="tools-menu-icon"></iconify-icon>
							<span class="menutext">
								<?php echo $lang['ws-add-text27'] ?>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">
							<?php if ($user->cdp_hasPermission('edit_email_templates')) { ?>
							<li class="list-group-item sidebar-item">
								<a href="templates_email.php" class="sidebar-link">
									<iconify-icon icon="solar:check-circle-linear" class="tools-menu-icon" style="color:#E0206D"></iconify-icon>
									<span class="menutext">
										<?php echo $lang['ws-add-text28'] ?>
									</span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('manage_whatsapp_templates')) { ?>
							<li class="list-group-item sidebar-item">
								<a href="templates_whatsapp.php" class="sidebar-link">
									<iconify-icon icon="solar:check-circle-linear" class="tools-menu-icon" style="color:#E0206D"></iconify-icon>
									<span class="menutext">
										<?php echo $lang['ws-add-text29'] ?>
									</span>
								</a>
							</li>
							<?php } ?>
							<?php if ($user->cdp_hasPermission('edit_sms_templates')) { ?>
							<li class="list-group-item sidebar-item">
								<a href="templates_sms.php" class="sidebar-link">
									<iconify-icon icon="solar:check-circle-linear" class="tools-menu-icon" style="color:#E0206D"></iconify-icon>
									<span class="menutext">
										<?php echo $lang['left1115'] ?>
									</span>
								</a>
							</li>
							<?php } ?>
						</ul>
					</li>
					<?php } ?>

					<?php if ($user->cdp_hasPermission('edit_default_templates')) { ?>
					<li class="list-group-item sidebar-item">
						<a class="list-group-item-action has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<iconify-icon icon="solar:playlist-2-linear" class="tools-menu-icon"></iconify-icon>
							<span class="menutext">
								<?php echo $lang['ws-add-text26'] ?>
						</a>
						<ul aria-expanded="false" class="collapse  first-level">

							<li class="list-group-item sidebar-item">
								<a href="templates_default.php" class="sidebar-link">
									<iconify-icon icon="solar:check-circle-linear" class="tools-menu-icon" style="color:#E0206D"></iconify-icon>
									<span class="menutext">
										WhatsApp
									</span>
								</a>
							</li>
						</ul>
					</li>
					<?php } ?>

					<li class="list-group-item">
						<hr>
					</li>

				</ul>
			</nav>
			<?php } ?>
		</div>
	</aside>