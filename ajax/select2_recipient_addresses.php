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

if ($type === "user" && !$datas) {
    $db->cdp_query("SELECT * FROM cdb_users_multiple_addresses WHERE user_id='$id'");
    $db->cdp_execute();
    $legacy = $db->cdp_registros();

    foreach ($legacy as $key) {
        $data[] = [
            'id' => $key->id ?? $key->id_addresses,
            'text' => $key->address ?? '',
            'country' => $key->country ?? '',
            'state' => $key->state ?? '',
            'city' => $key->city ?? '',
            'zip_code' => $key->zip_code ?? ($key->postal ?? '')
        ];
    }
} elseif ($datas) {
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
}

echo json_encode($data);
