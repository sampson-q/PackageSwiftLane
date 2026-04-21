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
require_permission('prealert_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';

$sWhere = "";

if ($search != '') {

	$sWhere .= " ";
}

if ($userData->userlevel == 1) {

	$sWhere .= " and  customer_id = '" . $_SESSION['userid'] . "'";
} else if ($userData->userlevel == 6) {
	// Agencia: solo prealertas de clientes que tienen órdenes/paquetes en esta agencia
	$aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
	$sWhere .= " and (customer_id IN (SELECT sender_id FROM cdb_add_order WHERE agency = " . $aid . ") OR customer_id IN (SELECT sender_id FROM cdb_customers_packages WHERE agency = " . $aid . ") OR customer_id IN (SELECT id FROM cdb_users WHERE name_off = (SELECT name_branch FROM cdb_branchoffices WHERE id = " . $aid . " LIMIT 1)))";
} else {
	$sWhere .= "";
}



// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 10; //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT * FROM cdb_pre_alert  where tracking LIKE :search $sWhere order by prealert_date desc";


$query_count = $db->cdp_query($sql);
$db->bind(':search', '%' . $search . '%');
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql . " limit $offset, $per_page");
$db->bind(':search', '%' . $search . '%');
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
	<div class="table-responsive">
		<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
			<thead>
				<tr>
					<th class="text-center"><b><?php echo $lang['booking-list3'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left46'] ?></b></th>
					<?php
					if ($userData->userlevel != 1) { ?>

						<th class="text-center"><b><?php echo $lang['ncustomer'] ?></b></th>
					<?php } ?>
					<th class="text-center"><b><?php echo $lang['left47'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left48'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left49'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['add-title15'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['left66'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['deliver-ship9'] ?></b></th>
					<th class="text-center"><b></b></th>

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

						$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->customer_id . "'");
						$sender_data = $db->cdp_registro();


						$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->courier_com . "'");
						$courier_com = $db->cdp_registro();


					?>
						<tr class="card-hovera">

							<td class="text-center">
								<?php echo date('Y-m-d', strtotime($row->prealert_date)); ?>
							</td>
							<td class="text-center"><b><?php echo $row->tracking; ?></b></td>

							<?php
							if ($userData->userlevel != 1) { ?>
								<td class="text-center">
									<?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?>
								</td>
							<?php } ?>

							<td class="text-center"><?php echo $courier_com->name_com; ?></td>

							<td class="text-center">
								<?php echo $row->provider_shop; ?>
							</td>

							<td class="text-center">
								<?php echo $row->package_description; ?>
							</td>

							<td class="text-center">
								<?php echo $row->estimated_date; ?>
							</td>

							<td class="text-center">
								<b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->purchase_price); ?>
							</td>
							<td class="text-center">
								<?php if ($row->is_package == 1) { ?>
									<span style="background: #5BE472;" class="label label-large"><?php echo $lang['customer-packages-text2'] ?></span>
								<?php } ?>
								<?php if ($row->is_package == 0) { ?>
									<span style="background: #FD571E;" class="label label-large"><?php echo $lang['customer-packages-text1'] ?></span>
								<?php } ?>
							</td>

							<td align='center'>
								<div class="btn-group">
										<button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									        <i class="fas fa-ellipsis-v"></i> <!-- Utiliza el icono de puntos suspensivos -->
									    </button>
										<div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">

										<?php if ($user->cdp_hasPermission('see_attached_invoice_pre-alerts')) { ?>
										<a class="dropdown-item" href="<?php echo $row->url_invoice; ?>" target="_blank"><i style="color:#343a40" class="fa fa-search"></i>&nbsp;<?php echo $lang['customer-packages-text3'] ?></a>

										<?php } ?>

										<?php

										if ($user->cdp_hasPermission('invoice_prealert')) {
											if ($row->is_package == 0) {
										?>
												<a class="dropdown-item" href="customer_packages_add_from_prealert.php?id=<?php echo $row->pre_alert_id; ?>"><i style="color:#343a40" class="ti-package"></i>&nbsp;<?php echo $lang['customer-packages-text4'] ?></a>

										<?php }
										}

										?>
									</div>
								</div>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>


		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang);	?>
		</div>



	</div>
<?php } ?>