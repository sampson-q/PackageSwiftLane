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

$search = cdp_sanitize($_REQUEST['term']);

$sql = "SELECT CONCAT(fname, ' ', lname) as label, id, fname, lname, email, phone, address,country, city, postal FROM cdb_users WHERE fname LIKE '%" . $search . "%'";

$db->cdp_query($sql);
$db->cdp_execute();

$data = $db->cdp_registros();

echo json_encode($data);
