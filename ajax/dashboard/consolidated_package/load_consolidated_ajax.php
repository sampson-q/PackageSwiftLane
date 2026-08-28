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
require_once(__DIR__ . '/../../../helpers/querys.php');
require_login();
require_permission('view_dashboard');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();
$ctx = cdp_getAgencyContext();
$month = date('m');
$year = date('Y');


// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$swhere = "";

if ($userData->userlevel == 3) {
	$swhere .= " and  a.driver_id = " . (int)$_SESSION['userid'];
} else if ($userData->userlevel == 1) {
	$swhere .= " and  a.sender_id = " . (int)$_SESSION['userid'];
} else if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
	$swhere .= " and  a.agency = " . (int)$ctx['agency_id'];
} else if ($ctx['is_restricted']) {
	$swhere .= " and 1=0";
}

if (isset($_REQUEST['search'])) {

	$search = trim($_REQUEST['search']);

	if ($search != null) {

		$swhere .= " and  CONCAT(a.c_prefix,a.c_no) LIKE '%" . $search . "%'";
	}
}


$sql = "SELECT  a.total_order, a.recipient_type, a.consolidate_id , a.c_prefix, a.c_no, a.c_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM cdb_consolidate_packages as a
			 INNER JOIN cdb_styles as b ON a.status_courier = b.id
			 $swhere
			and c_date >= '$year-$month-01' AND c_date < DATE('$year-$month-01') + INTERVAL 1 MONTH
		 
			  and a.status_courier!=14
			 order by consolidate_id  desc
			 ";


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
					<th><b><?php echo $lang['ltracking'] ?></b></th>
					<th><b><?php echo $lang['ddate'] ?></b></th>
					<th><b><?php echo $lang['left498'] ?></b></th>
					<th><b><?php echo $lang['left499'] ?></b></th>
					<th><b><?php echo $lang['lorigin'] ?></b></th>
					<th><b><?php echo $lang['ldestination'] ?></b></th>
					<!-- <th><b><?php echo $lang['lshipline'] ?></b></th> -->
					<th><b><?php echo $lang['lpayment'] ?></b></th>
					<th><b><?php echo $lang['lstatusshipment'] ?></b></th>
					<th><b><?php echo $lang['left533020007'] ?></b></th>
					<th class=""><b><?php echo $lang['ship-all5'] ?></b></th>
				</tr>
			</thead>
			<tbody id="projects-tbl">


				<?php if (!$data) { ?>
					<tr>
						<td colspan="6">
							<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
						</td>
					</tr>
				<?php } else { ?>

					<?php

					$count = 0;
					foreach ($data as $row) {

						$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
						$sender_data = $db->cdp_registro();

						if ($row->recipient_type == 'user') {
							$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->receiver_id . "'");
						} else {
							$db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row->receiver_id . "'");
						}
						$receiver_data = $db->cdp_registro();

						$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->driver_id . "'");
						$driver_data = $db->cdp_registro();

						$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->order_courier . "'");
						$courier_com = $db->cdp_registro();

						$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $row->order_pay_mode . "'");
						$met_payment = $db->cdp_registro();

						$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row->order_service_options . "'");

						$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row->c_prefix . $row->c_no . "'");
						$address_order = $db->cdp_registro();

						$order_service_options = $db->cdp_registro();

						$db->cdp_query("SELECT * FROM cdb_styles where id= '14'");
						$status_style_pickup = $db->cdp_registro();

					?>
						<tr class="card-hovera">

							<td><b><a href="consolidate_view.php?id=<?php echo $row->consolidate_id; ?>"><?php echo $row->c_prefix . $row->c_no; ?></a></b></td>
							<td>
								<?php echo $row->c_date; ?>
							</td>

							<td>
								<?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?>
							</td>

							<td>
								<?php echo $receiver_data->fname; ?> <?php echo $receiver_data->lname; ?>
							</td>

							<td><?php echo $address_order->sender_country; ?>-<?php echo $address_order->sender_city; ?></td>
							<td><?php echo $address_order->recipient_country; ?>-<?php echo $address_order->recipient_city; ?></td>
							<td><?php echo $met_payment->met_payment; ?></td>

							<td class="">

								<span style="background: <?php echo $row->color; ?>;" class="label label-large"><?php echo $row->mod_style; ?></span>

							</td>
							<td><?php

								if ($driver_data != null) {
									echo $driver_data->fname; ?> <?php echo $driver_data->lname;
																} ?></td>
							<td>
								<b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>


		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'load_consolidated_package');	?>
		</div>



	</div>
<?php } ?>