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
require_once(__DIR__ . '/../helpers/querys.php');

$db = new Conexion;
$ctx = cdp_getAgencyContext();

$sender_id = (int)(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';

$list = array();
$data = [];

if ($ctx['is_restricted']) {
    if ($ctx['agency_id'] === null) {
        echo json_encode([]);
        exit;
    }
    $db->cdp_query('SELECT agency_id FROM cdb_users WHERE id = :id AND userlevel = 1 LIMIT 1');
    $db->bind(':id', $sender_id);
    $db->cdp_execute();
    $sender_row = $db->cdp_registro();
    if (!$sender_row || (int)$sender_row->agency_id !== (int)$ctx['agency_id']) {
        echo json_encode([]);
        exit;
    }
}

$sql = "SELECT * FROM cdb_recipients
 WHERE 
  (fname LIKE '%" . $search . "%'
  or lname LIKE '%" . $search . "%'
  or email LIKE '%" . $search . "%'
  or phone LIKE '%" . $search . "%'
)
  and sender_id = " . $sender_id;
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $sql .= " AND agency_id = " . (int)$ctx['agency_id'];
}
$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

    $data[] = array('id' => $key->id, 'text' => $key->fname . " " . $key->lname);
}

echo json_encode($data);
