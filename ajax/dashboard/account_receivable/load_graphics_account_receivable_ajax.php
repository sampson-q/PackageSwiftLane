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



	require_once ("../../../loader.php");
	require_once(__DIR__ . '/../../../helpers/querys.php');

	$db = new Conexion;
	$user = new User;
	$core = new Core;
	$userData = $user->cdp_getUserData();
	$ctx = cdp_getAgencyContext();

	$year = date('Y');
	$sWhere = '';
	if ($userData->userlevel == 3) {
		$sWhere = " and driver_id = " . (int)$_SESSION['userid'];
	} else if ($userData->userlevel == 1) {
		$sWhere = " and sender_id = " . (int)$_SESSION['userid'];
	} else if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
		$sWhere = " and agency = " . (int)$ctx['agency_id'];
	} else if ($ctx['is_restricted']) {
		$sWhere = " and 1=0";
	}

	$data = array();


	for ($month = 1; $month <= 12; $month ++){

    	$sql="SELECT IFNULL(SUM(total_order), 0) as total FROM cdb_add_order WHERE status_courier!=21 and order_payment_method >1  and order_date >= '$year-$month-01' AND order_date < DATE('$year-$month-01') + INTERVAL 1 MONTH $sWhere"; 
	       
        $db->cdp_query($sql); 
        $total_data= $db->cdp_registro();

		$data[] = number_format($total_data->total, 2,'.','');
        
    }
	echo json_encode($data);
