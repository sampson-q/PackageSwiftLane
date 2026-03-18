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

$sender_id = (int)($_REQUEST['id'] ?? 0);
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';

$data = [];

$ctx = cdp_getAgencyContext();
$extraWhere = "";

if ($ctx['is_restricted']) {
    if ($ctx['agency_id'] === null) {
        echo json_encode([]);
        exit;
    }
    $extraWhere = " AND agency_id = " . (int)$ctx['agency_id'];
}

/*
------------------------------------
1. DEFAULT RECIPIENT (THE SENDER)
------------------------------------
*/

$sql = "SELECT id,fname,lname FROM cdb_users
        WHERE id = $sender_id
        AND userlevel='1'
        AND (
            fname LIKE '%$search%' OR
            lname LIKE '%$search%' OR
            email LIKE '%$search%' OR
            phone LIKE '%$search%'
        )
        $extraWhere";

$db->cdp_query($sql);
$db->cdp_execute();
$users = $db->cdp_registros();

foreach ($users as $row) {
    $data[] = [
        'id' => $row->id,
        'text' => $row->fname . " " . $row->lname,
        'type' => 'user'
    ];
}


/*
------------------------------------
2. EXTRA RECIPIENTS
------------------------------------
*/

$sql = "SELECT * FROM cdb_recipients
        WHERE sender_id = $sender_id
        AND (
            fname LIKE '%$search%' OR
            lname LIKE '%$search%' OR
            email LIKE '%$search%' OR
            phone LIKE '%$search%'
        )";

if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $sql .= " AND agency_id=" . (int)$ctx['agency_id'];
}

$db->cdp_query($sql);
$db->cdp_execute();
$recipients = $db->cdp_registros();

foreach ($recipients as $row) {
    $data[] = [
        'id' => $row->id,
        'text' => $row->fname . " " . $row->lname,
        'type' => 'recipient'
    ];
}

echo json_encode($data);