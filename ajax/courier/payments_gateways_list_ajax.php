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
require_login();
require_permission('view_shipment_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = cdp_sanitize($_REQUEST['search']);
$gateway = $_REQUEST['gateway'];


$sWhere = "";

if ($userData->userlevel == 1) {

	$sWhere .= " and  order_track_customer_id = '" . $_SESSION['userid'] . "'";
} else {
	$sWhere .= "";
}

if ($gateway != '0') {

	$sWhere .= " and  gateway = '" . $gateway . "'";
}


// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;



$sql = "SELECT * FROM cdb_payments_gateway  where type_transaccition_courier= 'Shipments' and payment_transaction LIKE '%" . $search . "%' $sWhere  order by id desc 
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
					<th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder41'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder42'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder44'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder43'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['lstatusshipment'] ?></b></th>
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

						if ($row->status === 'COMPLETED' || $row->status === 'succeeded' || $row->status === 'success') {
							$text_status = $lang['left533020024'];
							$label_class = "label-success";
						} else {

							$text_status = $row->status;
							$label_class = "label-warning";
						}


						$db->cdp_query("SELECT * FROM cdb_add_order  where CONCAT(order_prefix, order_no)= '" . $row->order_track . "'");
						$order_ = $db->cdp_registro();


					?>
						<tr class="card-hovera">

							<td><b><a href="courier_view.php?id=<?php echo $order_->order_id; ?>"><?php echo $row->order_track; ?></a></b></td>
							<td class="text-center">
								<?php echo date('Y-m-d h:i A', strtotime($row->date_payment)); ?>
							</td>

							<td class="text-center">
								<?php echo $row->gateway; ?>
							</td>

							<td class="text-center">
								<?php echo $row->payment_transaction; ?>
							</td>


							<td class="text-center">
								<?php echo $row->currency; ?>
							</td>

							<td class="text-center">
								<?php echo cdb_money_format($row->amount); ?>
							</td>

							<td class="text-center">
								<span class="label label-large <?php echo $label_class; ?>"><?php echo $text_status; ?></span>

							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>


		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'payments_gateways_list_courier');	?>
		</div>

	</div>
<?php } ?>