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



require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');


$db = new Conexion;


$search = cdp_sanitize($_REQUEST['search']);

$tables = "cdb_user_module_permissions";
$fields = "*";

$sWhere = "";


$sWhere .= " module_name LIKE '%" . $search . "%'";


$sWhere .= " order by module_name desc";

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
				<th><b><?php echo $lang['asingmodule9'] ?></b></th>
                <th><b><?php echo $lang['asingmodule10'] ?></b></th>
                <th><b><?php echo $lang['asingmodule11'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['asingmodule12'] ?></b></th>
			</tr>
		</thead>


		<?php if (!$data) { ?>
			<tr>
				<td colspan="4">
					<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
				</td>
			</tr>
		<?php } else { ?>
			<?php foreach ($data as $row) { ?>
				<tr>
					<td><?php echo $row->module_name; ?></td>
                    <td><?php echo $row->description; ?></td>
                    <td><?php echo $row->created_at; ?></td>
					<td class="text-center">
					    <a href="asingrole_edit.php?id=<?php echo $row->id; ?>" data-toggle="tooltip" data-original-title="<?php echo $lang['asingmodule9'] ?>">
					        <div class="icon-holder"><i class="ti-pencil" aria-hidden="true"></i></div>
					    </a>
					</td>

				</tr>
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'asingrole_list');	?>
	</div>
	</div>
<?php } ?>