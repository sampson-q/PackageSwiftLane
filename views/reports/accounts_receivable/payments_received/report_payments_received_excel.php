<?php
require_once(dirname(__DIR__, 4) . '/helpers/fs_reports.php');

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



header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Report-payments_received_" . date('d-m-Y') . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);



$db = new Conexion;

$customer_id = intval($_REQUEST['customer_id']);
$pay_mode = intval($_REQUEST['pay_mode']);
$range = $_REQUEST['range'];


// Financial Sheet ledger — same source as the on-screen report, so print and
// screen can never disagree. (Was cdb_charges_order, retired since 2022.)
$data = cdp_fsPaymentsReceived([
    'customer_id' => $customer_id,
    'mode'        => cdp_fsModeFromMetPayment($pay_mode),
    'range'       => $range,
]);
$numrows = count($data);

$fecha = str_replace('-', '/', $fecha);

$html = '
	<html>
		<body>
		
		<h2>' . $core->site_name . '<br>
		' . $lang['report-text86'] . '<br>

		[' . $fecha[0] . ' - ' . $fecha[1] . ']
		
		</h2>


		<table border=1>
		<tbody>
			<tr style="background-color: #3e5569; color: white">				
				<th><b></b></th>
				<th class="text-center"><b>' . $lang['leftorder98'] . '</b></th>	
				<th><b>' . $lang['ddate'] . '</b></th>
				<th class="text-center"><b>' . $lang['report-text37'] . '</b></th>
				<th class="text-center"><b>' . $lang['leftorder287'] . '</b></th>
				<th><b>' . $lang['ltracking'] . '</b></th>
				<th class="text-center"><b>' . $lang['payment5'] . '</b></th>
			</tr>';

if ($numrows > 0) {

	$count = 0;
	$sumador_total = 0;

	foreach ($data as $row) {
		$sumador_total += $row->amount_ghs;

		$count++;



		$html .= '<tr>';
		$html .= '<td >' . $count . '</td>';
		$html .= '<td >' . $row->id . '</td>';
		$html .= '<td >' . date('Y-m-d H:i', strtotime($row->paid_at)) . '</td>';
		$html .= '<td>' . $row->customer . '</td>';
		$html .= '<td >' . cdp_fsModeLabel($row->mode) . '</td>';
		$html .= '<td >' . ($row->tracking !== "" ? $row->tracking : "—") . '</td>';
		$html .= '<td>' . 'GHS ' . number_format($row->amount_ghs, 2) . '</td>';
		$html .= '</tr>';
	}

	$html .= '<tr>';
	$html .= '<td><b>' . $lang['report-text53'] . '</td> </b>';
	$html .= '<td colspan="5"></td>';
	$html .= '<td><b>' . 'GHS ' . number_format($sumador_total, 2) . ' </b></td>';
	$html .= '</tr>';
}

$html .= '</table></html>';
echo ($html);
