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
header("Content-Disposition: attachment; filename=Report-customers_balance_" . date('d-m-Y') . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);



$db = new Conexion;

$customer_id = intval($_REQUEST['customer_id']);
$range = cdp_sanitize($_REQUEST['range']);


// Financial Sheet ledger — same source as the on-screen report.
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

$fecha = str_replace('-', '/', $fecha);

$html = '
	<html>
		<body>
		
		<h2>' . $core->site_name . '<br>
		' . $lang['report-text81'] . ' <br>

		[' . $fecha[0] . ' - ' . $fecha[1] . ']
		
		</h2>


		<table border=1>
		<tbody>
			<tr style="background-color: #3e5569; color: white">				
				<th><b></b></th>
                <th><b>' . $lang['report-text82'] . '</b></th>
                <th><b>' . $lang['modal-text16'] . '</b></th>
			</tr>';

if ($numrows > 0) {

    $count = 0;
    $order_pagado = 0;
    $order_total = 0;
    $sumador_balance = 0;

    foreach ($data as $row) {

        $sumador_balance += $row->balance_ghs;



        $count++;

        



        $html .= '<tr>';
        $html .= '<td ><b>' . $count . '</b></td>';
        $html .= '<td>' . $row->customer . '</td>';
        $html .= '<td>' . 'GHS ' . number_format($row->balance_ghs, 2) . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<td><b>' . $lang['report-text53'] . '</td> </b>';
    $html .= '<td></td>';
    $html .= '<td><b>' . 'GHS ' . number_format($sumador_balance, 2) . ' </b></td>';
    $html .= '</tr>';
}

$html .= '</table></html>';
echo ($html);
