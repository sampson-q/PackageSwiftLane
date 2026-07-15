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




require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_user_list');

$db = new Conexion;


$search = trim($_REQUEST['search'] ?? '');

$tables = "cdb_users u LEFT JOIN cdb_user_roles r ON u.userlevel = r.role_id";
$fields = "u.*, r.role_name, CONCAT(u.fname,' ', u.lname) as name,
                DATE_FORMAT(u.created, '%d. %b. %Y %H:%i') as cdate,
                DATE_FORMAT(u.lastlogin, '%d. %b. %Y %H:%i') as adate";

$sWhere = "(u.userlevel=2 or u.userlevel=9 or u.userlevel=3 or u.userlevel=4 or u.userlevel=6)";

if ($search !== '') {

        $sWhere .= " and (u.username LIKE :s1 or u.fname LIKE :s2 or u.lname LIKE :s3 or u.locker LIKE :s4 or u.email LIKE :s5 or u.phone LIKE :s6)";
}

// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$sql = "SELECT $fields FROM  $tables where $sWhere ORDER BY u.userlevel DESC";
$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
if ($search !== '') { foreach (['s1','s2','s3','s4','s5','s6'] as $sp) { $db->bind(':' . $sp, '%' . $search . '%'); } }
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;


$db->cdp_query($sql . " limit $offset, $per_page");
if ($search !== '') { foreach (['s1','s2','s3','s4','s5','s6'] as $sp) { $db->bind(':' . $sp, '%' . $search . '%'); } }
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);
require_once(__DIR__ . '/../../helpers/rbac.php');
$viewer_can_edit_user   = $user->cdp_hasPermission('edit_user');
$viewer_can_edit_driver = $user->cdp_hasPermission('drivers_edit');
$viewer_can_delete      = $user->cdp_hasPermission('delete_user');
$viewer_can_newsletter  = $user->cdp_hasPermission('manage_newsletter');
$viewer_uid = (int)$user->uid;

if ($numrows > 0) { ?> 

<div class="table-responsive">

	<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
		<thead>
			<tr>
				<th><b><?php echo $lang['user_manage3'] ?></b></th>
				<th><b><?php echo $lang['user_manage54'] ?></b></th>
                                <th class="text-center"><b><?php echo $lang['user_manage38'] ?></b></th>
                                <th class="text-center"><b><?php echo $lang['left533020003'] ?></b></th>
                                <th class="text-center"><b><?php echo $lang['user_manage40'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['user_manage41'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['user_manage42'] ?></b></th>
				<!-- <th class="text-center"><b><?php echo $lang['edit-clien61'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien60'] ?></b></th> -->
				<th class=""><b><?php echo $lang['user_manage43'] ?></b></th>
				
			</tr>
		</thead>


		<?php if (!$data) { ?>
			<tr>
				<td colspan="6">
					<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
				</td>
			</tr>
		<?php } else { ?>
			<?php foreach ($data as $row_user) { ?>
				<tr>


					<td><?php echo $row_user->username; ?></td>
                    <td><?php echo $row_user->name_off; ?></td>
                    <td><?php echo $row_user->name; ?></td>
                    <td class="text-center"><?php echo $row_user->role_name; ?></td>
                    <td class="text-center"><?php echo cdp_userStatus($row_user->active, $row_user->id, $lang); ?></td>
					<td class="text-center"><?php echo cdp_isAdmin($row_user->userlevel, $lang); ?></td>
					<td class="text-center"><?php echo ($row_user->adate) ? $row_user->adate : "-/-"; ?></td>
					<?php if (in_array((int)$row_user->userlevel, [2, 4, 6, 9])) : ?>
					<!-- <td class="text-center"><?php echo $row_user->enrollment ?? '-'; ?></td>
					<td class="text-center"><?php echo $row_user->vehiclecode ?? '-'; ?></td> -->
					<?php /*elseif ($row_user->userlevel == 3) :*/ ?>
					<!-- <td class="text-center"><i class="icon-prepend icon-truck"></i> <?php echo $row_user->enrollment; ?></td>
					<td class="text-center"><i class="icon-prepend icon-tag"></i> <?php echo $row_user->vehiclecode; ?></td> -->
					<?php endif; ?>
					<td align='center'>
					<?php
                        $row_level     = (int)$row_user->userlevel;
                        $row_is_driver = cdp_roleHasFlag($row_level, 'is_driver');
                        $row_managed   = cdp_canManageUser($user, $row_level);
					?>
					<div class="action-buttons d-inline-flex align-items-center justify-content-center" style="gap:12px;font-size:16px;line-height:1;">
						<?php if ($row_managed && $row_is_driver && $viewer_can_edit_driver) : ?>
							<a href="drivers_edit.php?user=<?php echo $row_user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
								<i class="ti-pencil" style="color:#2f8be6;" aria-hidden="true"></i></a>
						<?php elseif ($row_managed && !$row_is_driver && $viewer_can_edit_user) : ?>
							<a href="users_edit.php?user=<?php echo $row_user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
								<i class="ti-pencil" style="color:#2f8be6;" aria-hidden="true"></i></a>
						<?php endif; ?>

						<?php if ($row_managed && !$row_is_driver && $viewer_can_edit_user) : ?>
							<a href="user_permissions.php?user=<?php echo $row_user->id; ?>" data-toggle="tooltip" data-placement="top" title="Permissions">
								<i style="color:#336aea;" class="ti-shield"></i></a>
						<?php endif; ?>

						<?php if (cdp_canViewAs((int)$user->userlevel, $row_level) && (int)$row_user->id !== $viewer_uid) : ?>
							<a href="view_as.php?id=<?php echo $row_user->id; ?>" data-toggle="tooltip" data-placement="top" title="View as this user (support mode)">
								<iconify-icon icon="solar:login-3-bold" style="color:#b45309;vertical-align:-2px;"></iconify-icon></a>
						<?php endif; ?>

						<?php if ($viewer_can_newsletter) : ?>
							<a href="newsletter.php?email=<?php echo $row_user->email; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien45'] ?>">
								<i style="color:#F5590D;" class="ti-email"></i></a>
						<?php endif; ?>

						<?php if ((int)$row_user->id === 1 || cdp_roleHasFlag($row_level, 'is_superadmin')) : ?>
							<a data-rel="<?php echo $row_user->username; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo cdp_roleHasFlag($row_level, 'is_superadmin') ? (isset($lang['role_9']) ? $lang['role_9'] : 'Super Admin') : 'Master Admin'; ?>">
								<i style="color:#343a40;" class="ti-lock"></i></a>
						<?php elseif ($row_managed && $viewer_can_delete && (int)$row_user->id !== $viewer_uid) : ?>
							<?php if ($row_is_driver) : ?>
								<a onclick="cdp_eliminar_driver('<?php echo $row_user->id; ?>')" id="itemdriver_<?php echo $row_user->id; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
									<i class="fi fi-rr-trash" style="color:#e4384d;"></i></a>
							<?php else : ?>
								<a onclick="cdp_eliminar('<?php echo $row_user->id; ?>')" id="item_<?php echo $row_user->id; ?>" data-rel="<?php echo $row_user->username; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
									<i class="fi fi-rr-trash" style="color:#e4384d;"></i></a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					</td>
				</tr>
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'users_list');	?>
	</div>
</div>
<?php } ?>