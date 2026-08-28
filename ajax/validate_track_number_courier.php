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

$search = cdp_sanitize($_REQUEST['track']);
$search = intval($search);


$sql_digits = "SELECT * FROM cdb_settings";

$db->cdp_query($sql_digits);
$db->cdp_execute();
$trackd = $db->cdp_registro();

$digits = $trackd->track_digit;

$format_track = str_pad($search, "" . $digits . "", "0", STR_PAD_LEFT);


$sql = "SELECT order_no FROM cdb_add_order WHERE order_no = '" . $format_track . "'";

$db->cdp_query($sql);
$db->cdp_execute();

$data = $db->cdp_registro();

if ($data) {

	echo json_encode(true);
} else {

	echo json_encode(false);
}
