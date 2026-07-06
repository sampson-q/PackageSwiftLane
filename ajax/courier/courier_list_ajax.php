<?php
if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }
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
require_permission('view_shipment_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();
$permissions = $user->cdp_getUserPermissions();

$search = cdp_sanitize($_REQUEST['search']);
$status_courier = intval($_REQUEST['status_courier']);

$sWhere = "";

if ($userData->userlevel == 3) {

	$sWhere .= " and  a.driver_id = '" . $_SESSION['userid'] . "'";
} else if ($userData->userlevel == 1) {

	$sWhere .= " and  a.sender_id = '" . $_SESSION['userid'] . "'";
} else if ($userData->userlevel == 6) {

	$agency_branch_id = cdp_getAgencyBranchIdForUser($userData->name_off);
	$sWhere .= " and  a.agency = '" . (int)$agency_branch_id . "'";
} else {
	$sWhere .= "";
}
if ($search != null) {

	$sWhere .= " and  " . cdp_trackingSearchSql($search, 'a');
}
if ($status_courier > 0) {

	$sWhere .= " and  a.status_courier = '" . $status_courier . "'";
}



$filterby = intval($_REQUEST['filterby']);

if ($filterby > 0) {

	if ($filterby == 1) {
		$is_pickup_filter = 1;
	} else {
		$is_pickup_filter = 0;
	}

	$sWhere .= " and  a.is_pickup = '" . $is_pickup_filter . "'";
}

if ($filterby == 3) {

	$sWhere .= " and  a.is_consolidate = '1'";
}


// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

// Throttled (was a full-scan UPDATE on every page load):
if (!function_exists('cdp_markOverdueInvoices')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/overdue_invoices.php')) { $d = dirname($d); } if (is_file($d . '/helpers/overdue_invoices.php')) require_once $d . '/helpers/overdue_invoices.php'; }
cdp_markOverdueInvoices($db);


$sql = "SELECT a.order_incomplete, a.recipient_type, a.status_invoice, a.is_consolidate, a.is_dangerous_good, a.is_pickup, a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM
			 cdb_add_order as a
			 INNER JOIN cdb_styles as b ON a.status_courier = b.id
			 $sWhere
			  and a.status_courier!=14

			 order by order_id desc 
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
					<?php if ($user->cdp_hasPermission('select_multiple_courier')) { ?>

						<th>
							<div class="custom-control custom-checkbox">
								<input type="checkbox" class="custom-control-input sl-all" id="cstall">
								<label class="custom-control-label" for="cstall"></label>
							</div>
						</th>
					<?php
					}
					?>
					<th><b><?php echo 'Swift Tracking' ?></b></th>
					<th><b><?php echo 'Tracking' ?></b></th>
					<th><b><?php echo $lang['ddate'] ?></b></th>
					<?php
					if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
						<th><b><?php echo $lang['left498'] ?></b></th>
					<?php } ?>
					<th><b><?php echo $lang['left499'] ?></b></th>

					<th><b><?php echo $lang['ldestination'] ?></b></th>
					<th><b><?php echo $lang['lpayment'] ?></b></th>
					<th><b><?php echo $lang['lstatusshipment'] ?></b></th>
					<th class=""><b><?php echo $lang['ship-all5'] ?></b></th>
					<th></th>
					<th><b><?php echo $lang['global-3'] ?></b></th>
					<th><b></b></th>
				</tr>
			</thead>
			<tbody id="projects-tbl">
				<?php if (!$data) { ?>
					<tr>
						<td colspan="6">
							<?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
						</td>
					</tr>
				<?php } else { ?>

					<?php

					$count = 0;

					// Status styles are constant for every row Ã¢â‚¬â€ fetch once, not per iteration.
					$db->cdp_query("SELECT * FROM cdb_styles where id= '14'");
					$status_style_pickup = $db->cdp_registro();
					$db->cdp_query("SELECT * FROM cdb_styles where id= '13'");
					$status_style_consolidate = $db->cdp_registro();
					// --- De-N+1: bulk-prefetch per-row lookups once, then read from maps in the loop.
					$fs_senderIds = $fs_payIds = $fs_recvUserIds = $fs_recvRecipIds = $fs_tracks = [];
					foreach ($data as $row) {
					    if ((int) $row->sender_id > 0)      $fs_senderIds[(int) $row->sender_id] = 1;
					    if ((int) $row->order_pay_mode > 0) $fs_payIds[(int) $row->order_pay_mode] = 1;
					    $rtype = isset($row->recipient_type) ? $row->recipient_type : 'recipient';
					    if ((int) $row->receiver_id > 0) {
					        if ($rtype === 'user') $fs_recvUserIds[(int) $row->receiver_id] = 1;
					        else                   $fs_recvRecipIds[(int) $row->receiver_id] = 1;
					    }
					    $fs_tracks[$row->order_prefix . $row->order_no] = 1;
					}
					$sender_map = $recv_user_map = $recv_recip_map = $paymode_map = $address_map = [];
					if ($fs_senderIds) {
					    $db->cdp_query("SELECT * FROM cdb_users WHERE id IN (" . implode(',', array_keys($fs_senderIds)) . ")");
					    foreach ($db->cdp_registros() as $r) $sender_map[(int) $r->id] = $r;
					}
					if ($fs_payIds) {
					    $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id IN (" . implode(',', array_keys($fs_payIds)) . ")");
					    foreach ($db->cdp_registros() as $r) $paymode_map[(int) $r->id] = $r;
					}
					if ($fs_recvUserIds) {
					    $db->cdp_query("SELECT id, fname, lname FROM cdb_users WHERE id IN (" . implode(',', array_keys($fs_recvUserIds)) . ")");
					    foreach ($db->cdp_registros() as $r) $recv_user_map[(int) $r->id] = $r;
					}
					if ($fs_recvRecipIds) {
					    $db->cdp_query("SELECT id, fname, lname FROM cdb_recipients WHERE id IN (" . implode(',', array_keys($fs_recvRecipIds)) . ")");
					    foreach ($db->cdp_registros() as $r) $recv_recip_map[(int) $r->id] = $r;
					}
					if ($fs_tracks) {
					    $fs_trk = implode(',', array_map(function ($t) { return "'" . str_replace("'", "''", $t) . "'"; }, array_keys($fs_tracks)));
					    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track IN ($fs_trk)");
					    foreach ($db->cdp_registros() as $r) { if (!isset($address_map[$r->order_track])) $address_map[$r->order_track] = $r; }
					}

					foreach ($data as $row) {

						$sender_data = $sender_map[(int) $row->sender_id] ?? null;

						$recipient_type = isset($row->recipient_type) ? $row->recipient_type : 'recipient';

						$receiver_data = ($recipient_type === 'user')
							? ($recv_user_map[(int) $row->receiver_id] ?? null)
							: ($recv_recip_map[(int) $row->receiver_id] ?? null);



						$met_payment = $paymode_map[(int) $row->order_pay_mode] ?? null;




                        $postal_tracking = cdp_getPackageTrackingLegacyAware((int) $row->order_id);

						if ($row->status_invoice == 1) {

							$text_status = $lang['invoice_paid'];
							$label_class = "label-success";
						} else if ($row->status_invoice == 2) {

							$text_status = $lang['invoice_pending'];
							$label_class = "label-warning";
						} else if ($row->status_invoice == 3) {
							$text_status = $lang['verify_payment'];
							$label_class = "label-info";
						}



						$address_order = $address_map[$row->order_prefix . $row->order_no] ?? null;

                        $db->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail where order_no='" . $row->order_no . "'");
						$consolidate_id = $db->cdp_registro() -> consolidate_id;
						
                        $db->cdp_query("SELECT status_courier FROM cdb_consolidate where consolidate_id='" . $consolidate_id . "'");
						$consolidate_status_courier = $db->cdp_registro() -> status_courier;
                        
                        $db->cdp_query("SELECT * FROM cdb_styles where id='" . $consolidate_status_courier . "'");
						$consolidate_style = $db->cdp_registro();
                        


					?>


						<tr class="card-hovera">
							<?php if ($user->cdp_hasPermission('select_multiple_courier')) { ?>
								<td class="chb">
									<?php if ($row->status_courier != 8) { ?>
										<?php if ($row->status_courier != 21) { ?>

											<div class="custom-control custom-checkbox">
												<input type="checkbox" class="custom-control-input" value="<?php echo $row->order_no; ?>" name="checkbox[]" id="cst_<?php echo $count; ?>">
												<label class="custom-control-label" for="cst_<?php echo $count; ?>">&nbsp;</label>
											</div>

										<?php } ?>
									<?php } ?>

								</td>
							<?php } ?>
							<td><b><a href="courier_view.php?id=<?php echo $row->order_id; ?>"><?php echo $row->order_prefix . $row->order_no; ?></a></b></td>
							<td><?php echo $postal_tracking->tracking_number ? $postal_tracking->tracking_number : 'N/A'; ?></td>
							<td>
								<?php echo $row->order_date; ?>
							</td>
							<?php
							if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
								<td>
									<?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?>
								</td>
							<?php } ?>
							<td>
								<?php echo $receiver_data->fname; ?> <?php echo $receiver_data->lname; ?>
							</td>

							<td><?php echo $recipient_type == 'user' ? $address_order->sender_country : $address_order->recipient_country; ?>-<?php echo $recipient_type == 'user' ? $address_order->sender_city : $address_order->recipient_city; ?></td>

							<td>
							    <?php echo isset($met_payment->met_payment) ? $met_payment->met_payment : 'N/A'; ?>
							</td>


							<td class="">

								<!-- <span style="background: <?php echo $row->color; ?>;" class="label label-large"><?php echo $row->mod_style; ?></span> -->
                                 <span style="background: <?php echo $row->is_consolidate ? $consolidate_style->color : $row->color; ?>;" class="label label-large"><?php echo $row->is_consolidate ? $consolidate_style->mod_style : $row->mod_style; ?></span>
								<br>

								<?php if ((int)$row->is_dangerous_good === 1 && ($dg_style = cdp_getDangerousGoodsStyle())) { ?>
									<span style="background: <?php echo htmlspecialchars($dg_style->color, ENT_QUOTES, 'UTF-8'); ?>;" class="label label-large"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(str_replace('_', ' ', $dg_style->mod_style), ENT_QUOTES, 'UTF-8'); ?></span>
									<br>
								<?php } ?>

								<?php
								if ($row->is_pickup == true) { ?>

									<span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large"><?php echo $status_style_pickup->mod_style; ?></span>
								<?php
								}
								?>

								<?php
								if ($row->is_consolidate == true) { ?>

									<span style="background: <?php echo $status_style_consolidate->color; ?>;" class="label label-large"><?php echo $status_style_consolidate->mod_style; ?></span>
								<?php
								}
								?>
								<br>
								<?php if ($row->order_incomplete == 0) { ?>
									<?php if ($row->is_pickup == 0) { ?>
										<?php if ($userData->userlevel != 1) { ?>
											<span style="background: #5BE472;" class="label label-large">
												<?php echo $lang['leftorder34'] ?>
											</span>
										<?php } ?>
									<?php } ?>
								<?php } ?>

								<?php if ($row->order_incomplete == 0) { ?>
									<?php if ($row->is_pickup == 0) { ?>
										<?php if ($userData->userlevel != 9) { ?>
											<?php if ($userData->userlevel == 1) { ?>

												<span style="background: #FC5239;" class="label label-large">
													<?php echo $lang['left1018'] ?>
												</span>

											<?php } ?>
										<?php } ?>
									<?php } ?>
								<?php } ?>
							</td>

							<td>
								<b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
							</td>

							<td>
								<?php if ($row->status_invoice == 2) { ?>
									<?php if ($userData->userlevel == 1) { ?>

										<a style="background: #34e89e;" class="label label" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
											<i style="color:#343a40" class="fas fa-dollar-sign"></i>
											&nbsp;<?php echo $lang['leftorder35'] ?>
										</a>
									<?php } ?>
								<?php } ?>
							</td>

							<td>
								<span class="label label-large <?php echo $label_class; ?>"><?php echo $text_status; ?></span>
							</td>
							<td align='center'>
							    <div class="btn-group">
							        <button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							            <i class="fas fa-ellipsis-v"></i> <!-- Utiliza el icono de puntos suspensivos -->
							        </button>
							        <div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">
							            <!-- VER DETALLES DE ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('view_shipment_details')) { ?>
							                <a class="dropdown-item" href="courier_view.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooledit'] ?>">
							                    <i style="color:#343a40" class="fa fa-search"></i>
							                    &nbsp;<?php echo $lang['leftorder266'] ?>
							                </a>
							            <?php } ?>

							            <!-- VERIFICAR PAGOS DE ENVÃƒÂOS PERMISO -->
							            <?php if ($row->status_invoice == 2 && $user->cdp_hasPermission('verify_payments')) { ?>
							                <?php if ($userData->userlevel == 1) { ?>
							                    <a class="dropdown-item" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
							                        <i style="color:#343a40" class="fas fa-dollar-sign"></i>
							                        &nbsp;<?php echo $lang['leftorder32'] ?>
							                    </a>
							                <?php } ?>
							            <?php } ?>

							            <!-- VERIFICAR PAGOS DE ENVÃƒÂOS (Status = 3) PERMISO -->
							            <?php if ($row->status_invoice == 3 && $user->cdp_hasPermission('verify_payments')) { ?>
							                <?php if ($userData->userlevel != 1) { ?>
							                    <a class="dropdown-item" data-toggle="modal" data-target="#detail_payment_packages" data-id="<?php echo $row->order_id; ?>" data-customer="<?php echo $row->sender_id; ?>">
							                        <i style="color:#343a40" class="fas fa-dollar-sign"></i>
							                        &nbsp;<?php echo $lang['leftorder33'] ?>
							                    </a>
							                <?php } ?>
							            <?php } ?>

							            <!-- ACEPTAR ENVÃƒÂO PERMISO -->
							            <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $user->cdp_hasPermission('complete_client_shipment') && $userData->userlevel != 1) { ?>
							                <a class="dropdown-item" href="courier_accept.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooledit'] ?>">
							                    <i style="color:#343a40" class="ti-pencil"></i>
							                    &nbsp;<?php echo $lang['leftorder34'] ?>
							                </a>
							            <?php } ?>

							            <!-- IMPRIMIR ETIQUETA DE ENVÃƒÂO PERMISO -->
							            <?php if ($row->order_incomplete == 0 && $user->cdp_hasPermission('print_label')) { ?>
							                <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank">
							                    <i style="color:#343a40" class="ti-printer"></i>
							                    &nbsp;<?php echo $lang['toollabel'] ?>
							                </a>
							            <?php } ?>

							            <!-- EDITAR ENVÃƒÂO PERMISO -->
							            <?php if ($row->order_incomplete == 1 && $user->cdp_hasPermission('edit_shipment')) { ?>
							                <?php //if ($row->is_consolidate == 0 ) { ?>
							                    <?php if ($row->status_courier != 8) { ?>
							                        <a class="dropdown-item" href="courier_edit.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooledit'] ?>">
							                            <i style="color:#343a40" class="ti-pencil"></i>
							                            &nbsp;<?php echo $lang['tooledit'] ?>
							                        </a>
							                    <?php // } ?>
							                <?php } ?>
							            <?php } ?>

							            <!-- ANULAR ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('cancel_shipment')) { ?>
						                    <?php if ($row->status_courier != 21 && $row->status_courier != 12) { ?>
						                        <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalCancel">
						                            <i style="color:#f62d51" class="fas fa-times-circle"></i>
						                            &nbsp;<?php echo $lang['leftorder34444']; ?>
						                        </a>
						                    <?php } ?>
							            <?php } ?>

							            <!-- ELIMINAR ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('delete_shipment')) { ?>
						                    <?php if ($row->is_consolidate == 0 && $row->status_courier != 8) { ?>
						                        <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalDeletes">
						                            <i style="color:#f62d51" class="ti-trash"></i>
						                            &nbsp;<?php echo $lang['leftorder34445']; ?>
						                        </a>
						                    <?php } ?>
							            <?php } ?>

							            <!-- ASIGNAR CONDUCTOR A ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('assign_drivers')) { ?>
							                <?php if ($row->status_courier != 21 && $row->status_courier != 12 && $row->status_courier != 8) { ?>
							                    <a class="dropdown-item" data-toggle="modal" data-target="#modalDriver" data-id_shipment="<?php echo $row->order_id; ?>">
							                        <i style="color:#ff0000" class="fas fa-car"></i>
							                        &nbsp;<?php echo $lang['left208']; ?>
							                    </a>
							                <?php } ?>
							            <?php } ?>

							            <!-- SEGUIMIENTO DE ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('track_shipment')) { ?>
							                <?php if ($row->status_courier != 21 && $row->status_courier != 12) { ?>
							                    <a class="dropdown-item" href="courier_shipment_tracking.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['toolupdate'] ?>">
							                        <i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['toolupdate']; ?>
							                    </a>
							                <?php } ?>
							            <?php } ?>

                                        <?php if ($user->cdp_hasPermission('print_label')) { ?>
							                <a class="dropdown-item" target="blank" href="print_label_ship.php?id=<?php echo $row->order_id; ?>">
							                    <i style="color:#343a40" class="ti-printer"></i>
							                    &nbsp;<?php echo 'Print Label'; ?>
							                </a>
							            <?php } ?>

							            <!-- IMPRIMIR ENVÃƒÂO PERMISO -->
							            <?php if ($user->cdp_hasPermission('print_shipment')) { ?>
							                <a class="dropdown-item" target="blank" href="print_inv_ship.php?id=<?php echo $row->order_id; ?>">
							                    <i style="color:#343a40" class="ti-printer"></i>
							                    &nbsp;<?php echo $lang['toolprint']; ?>
							                </a>
							            <?php } ?>

							            <!-- ENVIAR CORREO PERMISO -->
							            <?php if ($user->cdp_hasPermission('send_email_attachment')) { ?>
							                <a class="dropdown-item" href="#" data-toggle="modal" data-id="<?php echo $row->order_id; ?>" data-email="<?php echo $sender_data->email; ?>" data-order="<?php echo $row->order_prefix . $row->order_no; ?>" data-target="#myModal">
							                    <i class="fas fa-envelope"></i>
							                    &nbsp;<?php echo $lang['leftorder36']; ?>
							                </a>
							            <?php } ?>

                                        <?php if ($user->cdp_hasPermission('deliver_shipment')) { ?>
							                <a class="dropdown-item" target="blank" href="courier_deliver_shipment.php?id=<?php echo $row->order_id; ?>">
							                    &nbsp;<i style="color:#343a40" class="fa fa-box"></i>&nbsp;<?php echo 'Deliver Package' ?>
							                </a>
							            <?php } ?>
							        </div>
							    </div>
							</td>

						</tr>
					<?php $count++; } ?>

				<?php } ?>
			</tbody>
		</table> 


		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'courier_list');	?>
		</div>
		<script src="<?= cdp_asset('dataJs/courier_ajax.js') ?>"></script>
	</div>
<?php } ?>