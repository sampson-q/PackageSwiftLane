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

$id   = cdp_sanitize($_REQUEST['id']);
$type = $_REQUEST['type'] ?? 'recipient';

$data = [];

if ($type === "user") {
    $sql = "SELECT * FROM cdb_senders_addresses WHERE user_id='$id'";

} else {
    $sql = "SELECT * FROM cdb_recipients_addresses WHERE recipient_id='$id'";
}

$db->cdp_query($sql);
$db->cdp_execute();
$datas = $db->cdp_registros();

foreach ($datas as $key) {

    $db->cdp_query("SELECT name FROM cdb_countries WHERE id='$key->country'");
    $country = $db->cdp_registro();

    $db->cdp_query("SELECT name FROM cdb_states WHERE id='$key->state'");
    $state = $db->cdp_registro();

    $db->cdp_query("SELECT name FROM cdb_cities WHERE id='$key->city'");
    $city = $db->cdp_registro();

    $data[] = [
        'id' => $key->id_addresses,
        'text' => $key->address,
        'country' => $country->name ?? '',
        'state' => $state->name ?? '',
        'city' => $city->name ?? '',
        'zip_code' => $key->zip_code
    ];
}

echo json_encode($data);