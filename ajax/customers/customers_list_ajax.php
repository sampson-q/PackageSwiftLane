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

 

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_client_list');


require_once(__DIR__ . '/../../helpers/rbac.php');

$db = new Conexion;
$user = new User;
$ctx = cdp_getAgencyContext();

// Captured before the row loop below shadows $user — used to decide whether the
// current operator may "View as" each listed customer (helpers/rbac.php).
$cdpViewerLevel = (int) $user->userlevel;

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
	// Match every word of the search independently so partial names work in any
	// order: "John Doe", "Doe", "John" and "Doe John" all find John Doe. Each
	// word must hit one field (AND across words, OR across fields). CONCAT covers
	// full-name typing; locker/id cover locker-id lookups.
	$terms = preg_split('/\s+/', trim($search));
	foreach ($terms as $term) {
		$term = trim($term);
		if ($term === '') {
			continue;
		}
		$sWhere .= " and (username LIKE '%" . $term . "%'"
			. " or fname LIKE '%" . $term . "%'"
			. " or lname LIKE '%" . $term . "%'"
			. " or CONCAT(fname,' ',lname) LIKE '%" . $term . "%'"
			. " or locker LIKE '%" . $term . "%'"
			. " or id LIKE '%" . $term . "%'"
			. " or email LIKE '%" . $term . "%'"
			. " or phone LIKE '%" . $term . "%')";
	}
}

// 1. Handle Active Status Filter
if (isset($_REQUEST['filterby_active']) && intval($_REQUEST['filterby_active']) > 0) {
    $filterby_active = intval($_REQUEST['filterby_active']);
    
    if ($filterby_active == 1) {
        $sWhere .= " AND active = 1 ";
    } elseif ($filterby_active == 2) {
        $sWhere .= " AND active = 0 ";
    }
}

// 2. Handle Approval Status Filter
if (isset($_REQUEST['filterby_approve']) && intval($_REQUEST['filterby_approve']) > 0) {
    $filterby_approve = intval($_REQUEST['filterby_approve']);
    
    if ($filterby_approve == 3) {
        $sWhere .= " AND approve = 1 ";
    } elseif ($filterby_approve == 4) {
        $sWhere .= " AND approve = 0 ";
    }
}

// 3. Handle "New Users" filter (last 30 days) — overrides active/approve filters
if (isset($_REQUEST['filterby_new']) && intval($_REQUEST['filterby_new']) == 1) {
    $sWhere .= " AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
}



// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT $fields FROM  $tables where $sWhere";
$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;


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
                    <td class="text-center"><b><a href="customers_edit.php?user=<?php echo $user->id; ?>"><?php echo $user->fname; ?> <?php echo $user->lname; ?></a></b></td>
					<td class="text-center"><?php echo $user->email; ?></td>
					<td class="text-center"><?php echo $user->locker; ?></td>
					<td class="text-center"><?php echo cdp_userStatus($user->active, $user->id, $lang); ?></td>
					<td class="text-center"><?php echo $user->approve ? '✔ Approved' : 'Unapproved'; ?></td>
					
                    <td class="text-center">
                        <div class="action-buttons">
                            <!-- <a href="customers_edit.php?user=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien46'] ?>">
                                <i class="ti-pencil"></i>
                            </a> -->
                            <a href="newsletter.php?email=<?php echo $user->email; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien45'] ?>">
                                <i style="color: #15e6a0; font-size: 14px;" class="ti-email"></i>
                            </a>
                            <span class="mx-1">|</span>
                            <a href="customer_view.php?user=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo "View Customer Packages" ?>">
                                <i style="color: #ff0037; font-size: 18px;" class="ti-eye"></i>
                            </a>
                            <?php if (cdp_canViewAs($cdpViewerLevel, (int) $user->userlevel)) : ?>
                                <span class="mx-1">|</span>
                                <a href="view_as.php?id=<?php echo $user->id; ?>" data-toggle="tooltip" data-placement="top" title="View as this customer (support mode)">
                                    <iconify-icon icon="solar:login-3-bold" style="color:#b45309;font-size:18px;vertical-align:-3px;"></iconify-icon>
                                </a>
                            <?php endif; ?>
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