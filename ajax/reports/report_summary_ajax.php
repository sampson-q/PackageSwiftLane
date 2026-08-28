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
require_once(__DIR__ . '/../../helpers/fs_reports.php');
require_login();
require_permission('view_general_reports');

$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$range = cdp_sanitize($_REQUEST['range']);
$agency_courier = intval($_REQUEST['agency_courier']);
$pay_mode = intval($_REQUEST['pay_mode']);
$customer_id = intval($_REQUEST['customer_id']);

$sWhere = "";

if (isset($userData->userlevel)) {
	if ($userData->userlevel == 3) {
		$sWhere .= " and driver_id = '" . (int)$_SESSION['userid'] . "'";
	} elseif ($userData->userlevel == 1) {
		$sWhere .= " and sender_id = '" . (int)$_SESSION['userid'] . "'";
	} elseif ($userData->userlevel == 6) {
		$aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
		$sWhere .= " and agency = '" . $aid . "'";
	}
}
if ($agency_courier > 0 && (!isset($userData->userlevel) || (int)$userData->userlevel !== 6)) {

	$sWhere .= " and agency = '" . $agency_courier . "'";
}


if ($customer_id > 0) {

	$sWhere .= " and sender_id = '" . $customer_id . "'";
}

if ($pay_mode > 0) {

	$sWhere .= " and order_payment_method = '" . $pay_mode . "'";
}
if (!empty($range)) {

	$fecha =  explode(" - ", $range);
	$fecha = str_replace('/', '-', $fecha);

	$fecha_inicio = date('Y-m-d', strtotime($fecha[0]));
	$fecha_fin = date('Y-m-d', strtotime($fecha[1]));


	$sWhere .= " and  order_date between '" . $fecha_inicio . "'  and '" . $fecha_fin . "'";
}

// Throttled (was a full-scan UPDATE on every page load):
// (was: cdp_markOverdueInvoices — this report no longer reads status_invoice,
//  so it has no reason to scan-and-write cdb_add_order on every load.)


// Financial Sheet ledger — one row per BILL (a consolidation billed to a
// customer). Previously listed cdb_add_order rows whose billing TERMS were
// postpaid, priced against cdb_charges_order — a ledger retired in 2022, so the
// paid/pending columns were meaningless.
$data = cdp_fsBillingSummary([
	'customer_id' => $customer_id,
	'range'       => $range,
]);
$numrows = count($data);



if ($numrows > 0) { ?>
	<div class="table-responsive">


		<table id="zero_config" class="table border  table-bordered display text-nowrap custom-table-checkbox">
			<thead>
				<tr>

					<th><b>Consolidation</b></th>
					<th class="text-center"><b><?php echo $lang['report-text37'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
					<th class="text-center"><b>Discount</b></th>
					<th class="text-center"><b><?php echo $lang['lstatusinvoice'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['modal-text20'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder110'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['modal-text16'] ?></b></th>

					<th class="text-center"></th>


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
					$sumador_pendiente = 0;
					$sumador_total = 0;
					$sumador_pagado = 0;

					foreach ($data as $row) {

						list($text_status, $label_class) = cdp_fsPayStatusLabel($row->pay_status);

						$sumador_pendiente += $row->balance_ghs;
						$sumador_total     += $row->amount_ghs;
						$sumador_pagado    += $row->paid_ghs;

					?>
						<tr class="card-hovera">

							<td><b><a href="financial_sheet_consolidation.php?id=<?php echo (int) $row->consolidate_id; ?>"><?php echo htmlspecialchars($row->consol_no); ?></a></b></td>

							<td class="text-center">
								<?php echo htmlspecialchars($row->customer);
									echo $row->locker ? ' <span class="text-muted">(' . htmlspecialchars($row->locker) . ')</span>' : ''; ?>
							</td>

							<td class="text-center">
								<?php echo htmlspecialchars(date('Y-m-d', strtotime($row->billed_at))); ?>
							</td>


							<td class="text-center">
								<?php echo $row->discount_ghs > 0 ? '₵' . number_format($row->discount_ghs, 2) : '—'; ?>
							</td>

							<td class="text-center">
								<span class="label label-large <?php echo $label_class; ?>"><?php echo htmlspecialchars($text_status); ?></span>

							</td>

							<td class="text-center">
								<?php echo '₵' . number_format($row->amount_ghs, 2); ?>
							</td>

							<td class="text-center">
								<?php echo '₵' . number_format($row->paid_ghs, 2); ?>
							</td>

							<td class="text-center">
								<b><?php echo '₵' . number_format($row->balance_ghs, 2); ?></b>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>
			<tfoot>
				<tr class="card-hover">
					<td class="text-left"><b><?php echo $lang['report-text53'] ?></b></td>

					<td colspan="4"></td>
					<td class="text-center  ">
						<b><?php echo '₵' . number_format($sumador_total, 2); ?> </b>
					</td>

					<td class="text-center  ">
						<b><?php echo '₵' . number_format($sumador_pagado, 2); ?> </b>
					</td>

					<td class="text-center  ">
						<b><?php echo '₵' . number_format($sumador_pendiente, 2); ?> </b>
					</td>

				</tr>

			</tfoot>

		</table>

	</div>
<?php } ?>