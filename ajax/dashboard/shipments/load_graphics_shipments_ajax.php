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

 

	require_once ("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../../helpers/querys.php');
require_login();
require_permission('view_dashboard');
require_csrf();


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

    	$sql="SELECT IFNULL(SUM(total_order), 0) as total FROM cdb_add_order WHERE status_courier!=21 and  is_pickup=0 and month(order_date)='$month' AND year(order_date)='$year' $sWhere"; 
	       
        $db->cdp_query($sql); 
        $total_data= $db->cdp_registro();

		$data[] = number_format($total_data->total, 2,'.','');
        
    }
	echo json_encode($data);
