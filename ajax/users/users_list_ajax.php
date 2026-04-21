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


$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';

$tables = "cdb_users u LEFT JOIN cdb_user_roles r ON u.userlevel = r.role_id";
$fields = "u.*, r.role_name, CONCAT(u.fname,' ', u.lname) as name,
                DATE_FORMAT(u.created, '%d. %b. %Y %H:%i') as cdate,
                DATE_FORMAT(u.lastlogin, '%d. %b. %Y %H:%i') as adate";

$sWhere = "(u.userlevel=2 or u.userlevel=9 or u.userlevel=3 or u.userlevel=4 or u.userlevel=6)";

if ($search != '') {

        $sWhere .= " and (u.username LIKE :search or u.fname LIKE :search or u.lname LIKE :search or u.locker LIKE :search or u.email LIKE :search or u.phone LIKE :search)";
}

// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 10; //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$sql = "SELECT $fields FROM  $tables where $sWhere";
$query_count = $db->cdp_query($sql);
if ($search != '') { $db->bind(':search', '%' . $search . '%'); }
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql . " limit $offset, $per_page");
if ($search != '') { $db->bind(':search', '%' . $search . '%'); }
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);
$current_userlevel = isset($user) && $user instanceof User ? $user->userlevel : 0;

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
				<th class="text-center"><b><?php echo $lang['edit-clien61'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien60'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['user_manage43'] ?></b></th>
				
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
			<?php foreach ($data as $user) { ?>
				<tr>


					<td><?php echo $user->username; ?></td>
                                        <td><?php echo $user->name_off; ?></td>
                                        <td><?php echo $user->name; ?></td>
                                        <td class="text-center"><?php echo $user->role_name; ?></td>
                                        <td class="text-center"><?php echo cdp_userStatus($user->active, $user->id, $lang); ?></td>
					<td class="text-center"><?php echo cdp_isAdmin($user->userlevel, $lang); ?></td>
					<td class="text-center"><?php echo ($user->adate) ? $user->adate : "-/-"; ?></td>
					<?php if (in_array((int)$user->userlevel, [2, 4, 6, 9])) : ?>
					<td class="text-center"><?php echo $user->enrollment ?? '-'; ?></td>
					<td class="text-center"><?php echo $user->vehiclecode ?? '-'; ?></td>
					<?php elseif ($user->userlevel == 3) : ?>
					<td class="text-center"><i class="icon-prepend icon-truck"></i> <?php echo $user->enrollment; ?></td>
					<td class="text-center"><i class="icon-prepend icon-tag"></i> <?php echo $user->vehiclecode; ?></td>
					<?php endif; ?>
					<td align='center'>
					<?php
					$can_edit = in_array((int)$user->userlevel, [2, 4, 6, 9]) && ($user->userlevel != 9 || $current_userlevel == 9);
					if ($can_edit && in_array((int)$user->userlevel, [2, 4, 6, 9])) : ?>
					    <a href="users_edit.php?user=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
					        <i class="ti-pencil" aria-hidden="true"></i>
					    </a>
					<?php elseif ($user->userlevel == 3) : ?>
					    <a href="drivers_edit.php?user=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
					        <i class="ti-pencil" aria-hidden="true"></i>
					    </a>
					<?php endif; ?>


						<a href="newsletter.php?email=<?php echo $user->email; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien45'] ?>">
							<i style="color:#F5590D" class="ti-email"></i></a>

						<?php if ($user->id == 1 || $user->userlevel == 9) : ?>
							<a data-rel="<?php echo $user->username; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $user->userlevel == 9 ? (isset($lang['role_9']) ? $lang['role_9'] : 'Super Admin') : 'Master Admin'; ?>"><i style="color:#343a40" class="ti-lock"></i></a>
						<?php else : ?>
							<?php if (in_array((int)$user->userlevel, [2, 4, 6, 9])) : ?>
								<a onclick="cdp_eliminar('<?php echo $user->id; ?>')" id="item_<?php echo $user->id; ?>" data-rel="<?php echo $user->username; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
									<div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
								</a>
							<?php elseif ($user->userlevel == 3) : ?>
								<a onclick="cdp_eliminar_driver('<?php echo $user->id; ?>')" id="itemdriver_<?php echo $user->id; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
									<div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
								</a>

							<?php endif; ?>	
						<?php endif; ?>
					</td>
				</tr>
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang);	?>
	</div>
</div>
<?php } ?>