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

$customer_id = intval($_REQUEST['customer_id']);
$pay_mode = intval($_REQUEST['pay_mode']);
$range = cdp_sanitize($_REQUEST['range']);


// Sourced from the Financial Sheet ledger. This report used to read
// cdb_charges_order — a ledger retired in favour of the FS and not written to
// since 2022, so it was showing four-year-old demo figures as current takings.
//
// The method filter still submits a cdb_met_payment id (that is what the
// dropdown is built from), so translate it to the FS mode.
$data = cdp_fsPaymentsReceived([
	'customer_id' => $customer_id,
	'mode'        => cdp_fsModeFromMetPayment($pay_mode),
	'range'       => $range,
]);
$numrows = count($data);


if ($numrows > 0) { ?>
	<div class="table-responsive">

		<table id="zero_config" class="table border  table-bordered display text-nowrap custom-table-checkbox">
			<thead>
				<tr>
					<th class="text-center"><b><?php echo $lang['leftorder98'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['ddate'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['report-text37'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['leftorder287'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['ltracking'] ?></b></th>
					<th class="text-center"><b><?php echo $lang['payment5'] ?></b></th>
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
					$sumador_total = 0;

					foreach ($data as $row) {

						$sumador_total += $row->amount_ghs;
						list($stLbl, $stCls) = cdp_fsStatusLabel($row->status, $row->mode);

					?>


						<tr class="card-hover">

							<td class="text-center">
								<?php echo $row->id; ?>
							</td>

							<td class="text-center">
								<?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row->paid_at))); ?>
							</td>


							<td class="text-center">
								<?php echo htmlspecialchars($row->customer);
									echo $row->locker !== '' ? ' <span class="text-muted">(' . htmlspecialchars($row->locker) . ')</span>' : ''; ?>
							</td>
							<td class="text-center">
								<?php echo htmlspecialchars(cdp_fsModeLabel($row->mode)); ?>
								<span class="label <?php echo $stCls; ?>"><?php echo htmlspecialchars($stLbl); ?></span>
								<?php if ($row->reference !== '') { ?>
									<br><span class="text-muted" style="font-size:.75rem">ref <?php echo htmlspecialchars($row->reference); ?></span>
								<?php } ?>
							</td>

							<td class="text-center">
								<?php echo htmlspecialchars($row->tracking !== '' ? $row->tracking : '—'); ?>
							</td>

							<td class="text-center">
								<?php echo '₵' . number_format($row->amount_ghs, 2); ?>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>
			<tfoot>

				<tr class="card-hover">
					<td class="text-center"><b><?php echo $lang['report-text53'] ?></b></td>
					<td colspan="4"></td>
					<td class="text-center">
						<b><?php echo '₵' . number_format($sumador_total, 2); ?> </b>
					</td>

				</tr>
			</tfoot>

		</table>
	</div>
<?php } ?>