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

$search = cdp_sanitize($_REQUEST['q']);

$data = [];

$sql = "SELECT id, fname, lname FROM cdb_users WHERE userlevel='1' AND (fname LIKE '%" . $search . "%' or lname LIKE '%" . $search . "%' or email LIKE '%" . $search . "%' or username LIKE '%" . $search . "%')";

$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $row) {
    $data[] = array('id' => $row->id, 'text' => $row->fname . ' ' . $row->lname);
}

echo json_encode($data);