<?php
require_once(dirname(__DIR__, 4) . '/helpers/fs_reports.php');

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



header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Report-accounts_receivable_summary_" . date('d-m-Y') . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);



$db = new Conexion;

$range = $_REQUEST['range'];
$agency_courier = intval($_REQUEST['agency_courier']);
$pay_mode = intval($_REQUEST['pay_mode']);
$customer_id = intval($_REQUEST['customer_id']);

$sWhere = "";


if ($agency_courier > 0) {

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


// Financial Sheet ledger — same source as the on-screen report.
$data = cdp_fsBillingSummary([
    'customer_id' => $customer_id,
    'range'       => $range,
]);
$numrows = count($data);

$fecha = str_replace('-', '/', $fecha);

$html = '
	<html>
		<body>
		
		<h2>' . $core->site_name . '<br>
		' . $lang['report-text85'] . ' <br>

		[' . $fecha[0] . ' - ' . $fecha[1] . ']
		
		</h2>


		<table border=1>
		<tbody>
			<tr style="background-color: #3e5569; color: white">	

				<th class="text-center"></th>               
				<th><b>' . $lang['ltracking'] . '</b></th>
                <th><b>' . $lang['report-text37'] . '</b></th>
				<th><b>' . $lang['ddate'] . '</b></th>
                <th class="text-center"><b>' . $lang['leftorder109'] . '</b></th>
                <th class="text-center"><b>' . $lang['lstatusinvoice'] . '</b></th>                
                <th class="text-center"><b>' . $lang['modal-text20'] . '</b></th>                
                <th class="text-center"><b>' . $lang['leftorder110'] . '</b></th>                
                <th class="text-center"><b>' . $lang['modal-text16'] . '</b></th>                
			</tr>';

if ($numrows > 0) {

    $count = 1;
    $sumador_pendiente = 0;
    $sumador_total = 0;
    $sumador_pagado = 0;

    foreach ($data as $row) {

                                                list($text_status, $label_class) = cdp_fsPayStatusLabel($row->pay_status);
        $sumador_pendiente += $row->balance_ghs;
        $sumador_total += $row->amount_ghs;
        $sumador_pagado += $row->paid_ghs;

        $count++;



        $html .= '<tr>';
        $html .= '<td >' . $count . '</td>';
        $html .= '<td >' . $row->consol_no . '</td>';
        $html .= '<td>' . $row->customer . '</td>';
        $html .= '<td >' . date('Y-m-d', strtotime($row->billed_at)) . '</td>';
        $html .= '<td >' . ($row->discount_ghs > 0 ? "GHS " . number_format($row->discount_ghs, 2) : "-") . '</td>';
        $html .= '<td >' . $text_status . '</td>';
        $html .= '<td>' . 'GHS ' . number_format($row->amount_ghs, 2) . '</td>';
        $html .= '<td>' . 'GHS ' . number_format($row->paid_ghs, 2) . '</td>';
        $html .= '<td>' . 'GHS ' . number_format($row->balance_ghs, 2) . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<td><b>' . $lang['report-text53'] . '</td> </b>';
    $html .= '<td colspan="5"></td>';
    $html .= '<td><b>' . 'GHS ' . number_format($sumador_total, 2) . ' </b></td>';
    $html .= '<td><b>' . 'GHS ' . number_format($sumador_pagado, 2) . ' </b></td>';
    $html .= '<td><b>' . 'GHS ' . number_format($sumador_pendiente, 2) . ' </b></td>';
    $html .= '</tr>';
}

$html .= '</table></html>';
echo ($html);
