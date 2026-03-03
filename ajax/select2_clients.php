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