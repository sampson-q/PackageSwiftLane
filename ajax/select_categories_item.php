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




require_once("../loader.php");

$db = new Conexion;

$list = array();

$data = [];

$sql = "SELECT *  FROM cdb_category ";

$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

	$data[] = array(

		'id' => $key->id,
		'name_item' => $key->name_item,

	);
}

echo json_encode($data);
