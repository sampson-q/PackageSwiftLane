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
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_client_list');


$db = new Conexion;
$user = new User;
$ctx = cdp_getAgencyContext();

$search = isset($_REQUEST['search']) ? cdp_sanitize($_REQUEST['search']) : null;

$tables = "cdb_users";
$fields = "*,CONCAT(fname,' ', lname) as name,
                DATE_FORMAT(created, '%d. %b. %Y %H:%i') as cdate,
                DATE_FORMAT(lastlogin, '%d. %b. %Y %H:%i') as adate";

$sWhere = "userlevel=1";
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
	$sWhere .= " AND agency_id = " . (int)$ctx['agency_id'];
} elseif ($ctx['is_restricted']) {
	$sWhere .= " AND 1=0";
}

if ($search != null) {

	$sWhere .= " and (username LIKE '%" . $search . "%' or fname LIKE '%" . $search . "%' or lname LIKE '%" . $search . "%' or locker LIKE '%" . $search . "%' or email LIKE '%" . $search . "%' or phone LIKE '%" . $search . "%')";
}


$filterby = intval($_REQUEST['filterby']);

if ($filterby > 0) {

	if ($filterby == 1) {
		$is_pickup_filter = 1;
	} else {
		$is_pickup_filter = 0;
	}

	$sWhere .= " and  active = '" . $is_pickup_filter . "'";
}



// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 10; //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT $fields FROM  $tables where $sWhere";
$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
<div class="table-responsive">	
	<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
		<thead>
			<tr>
                <th class="text-center"><b>Avatar</b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien38'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien39'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['user-account21000'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien40'] ?></b></th>
				<th class="text-center"><b><?php echo 'Approval Status' ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien42'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['edit-clien43'] ?></b></th>
				<th class="text-center"><b><?php echo 'Admin Actions' ?></b></th>
			</tr>
		</thead>

		<?php if (!$data) { ?>
			<tr>
				<td colspan="6">
					<?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
				</td>
			</tr>
		<?php } else { ?>
			<?php foreach ($data as $user) { ?>
			
				<tr>
                    <td class="text-center">
                        <img src="assets/<?php echo ($user->avatar) ? $user->avatar : "/uploads/blank.png"; ?>"  alt="" class="rounded-circle" width="40" height="40" style="display: block; margin: auto;" />
                    </td>
					<td class="text-center"><?php echo $user->fname; ?> <?php echo $user->lname; ?></td>
					<td class="text-center"><?php echo $user->email; ?></td>
					<td class="text-center"><?php echo $user->locker; ?></td>
					<td class="text-center"><?php echo cdp_userStatus($user->active, $user->id, $lang); ?></td>
					<td class="text-center"><?php echo $user->approve ? '✔ Approved' : 'Unapproved'; ?></td>
					<td class="text-center"><?php echo ($user->adate) ? $user->adate : "-/-"; ?></td>
					
                    <td class="text-center">
                        <div class="action-buttons">
                            <a href="customers_edit.php?user=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
                                <i class="ti-pencil"></i>
                            </a>
                            <a href="newsletter.php?email=<?php echo $user->email; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien45'] ?>">
                                <i style="color:#F5590D" class="ti-email"></i>
                            </a>
                            <?php if ($user->id == 1) : ?>
                                <a data-rel="<?php echo $user->username; ?>">
                                    <button type="button" data-toggle="tooltip" data-original-title="Master Admin">
                                        <i class="ti-lock" aria-hidden="true"></i>
                                    </button>
                                </a>
                            <?php else : ?>
                                <?php if ($userData->userlevel == 9) { ?>
                                    <a onclick="cdp_eliminar('<?php echo $user->id; ?>')" id="item_<?php echo $user->id; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
                                        <i class="fi fi-rr-trash"></i>
                                    </a>
                                <?php } ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <form method="POST" id="changeUserStatus">
                            <?php if ($user->approve) { ?>
                                <!-- If the user is approved -->
                                <?php if ($user->active) { ?>
                                    <!-- Deactivate button -->
                                    <a id="deactivateUserBtn" type="button" class="btn btn-warning" data-id="<?php echo $user->id; ?>" title="Deactivate">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                <?php } else { ?>
                                    <!-- Activate button -->
                                    <a id="activateUserBtn" type="button" class="btn btn-success" data-id="<?php echo $user->id; ?>" title="Activate">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <!-- If the user is unapproved -->
                                <a type="button" class="btn btn-primary approveUserBtn" data-id="<?php echo $user->id; ?>" title="Approve">
                                    <img src="assets/uploads/user-check-solid.svg" alt="Approve Icon" width="20" height="20">
                                </a>
                            <?php } ?>
                        </form>
                    </td>
				</tr>
			
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'customers_list');	?>
	</div>
	</div>
<?php } ?>