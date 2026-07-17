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
require_once(__DIR__ . '/../../helpers/fs_reports.php');
require_login();
require_permission('view_general_reports');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$range = cdp_sanitize($_REQUEST['range']);

$customer_id = intval($_REQUEST['customer_id']);


// Financial Sheet ledger. This report used to derive a balance from
// cdb_add_order.total_order minus cdb_charges_order — a ledger retired in 2022
// — and only for orders whose billing TERMS were not prepaid, so it never
// reflected what customers actually owe today.
$fecha_inicio = '';
$fecha_fin = '';
$r = cdp_fsReportRange($range);
if ($r) { $fecha_inicio = substr($r[0], 0, 10); $fecha_fin = substr($r[1], 0, 10); }

$data = cdp_fsCustomerBalances([
	'customer_id' => $customer_id,
	'range'       => $range,
	'owing_only'  => true,
]);
$numrows = count($data);


if ($numrows > 0) { ?>
	<div class="table-responsive">

		<table id="zero_config" class="table border  table-bordered display text-nowrap custom-table-checkbox">
			<thead>
				<tr>
					<th class="text-left"><b><?php echo $lang['report-text82'] ?></b></th>
					<th class="text-left"><b><?php echo $lang['modal-text16'] ?></b></th>
					<th></th>
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
					$sumador_balance = 0;

					foreach ($data as $row) {

						$sumador_balance += $row->balance_ghs;

					?>


						<tr class="card-hover">

							<td class="text-left">
								<?php echo htmlspecialchars($row->customer);
									echo $row->locker ? ' <span class="text-muted">(' . htmlspecialchars($row->locker) . ')</span>' : ''; ?>
								<br><span class="text-muted" style="font-size:.75rem">
									<?php echo (int) $row->bills; ?> bill(s) &middot; billed ₵<?php echo number_format($row->billed_ghs, 2); ?>
									&middot; paid ₵<?php echo number_format($row->paid_ghs, 2); ?>
									<?php echo $row->discount_ghs > 0 ? ' &middot; discount ₵' . number_format($row->discount_ghs, 2) : ''; ?>
								</span>
							</td>

							<td class="text-left">
								<b><?php echo '₵' . number_format($row->balance_ghs, 2); ?></b>
							</td>

							<td class="text-right">
								<a href="report_customers_balance_detail.php?customer=<?php echo (int) $row->sender_id; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>" class="btn btn-info btn-xs"><i class="fa fa-search"></i></a>
							</td>



						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>
			<tfoot>

				<tr class="card-hover">
					<td class="text-left"><b><?php echo $lang['report-text53'] ?></b></td>




					<td class="text-left">
						<b><?php echo '₵' . number_format($sumador_balance, 2); ?> </b>
					</td>

				</tr>
			</tfoot>

		</table>




	</div>
<?php } ?>