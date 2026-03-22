<?php
require_once("../loader.php");
require_once("../helpers/querys.php");

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$userData = $user->cdp_getUserData();

$db = new Conexion;

$db->cdp_query("SELECT * FROM cdb_user_details_update_check WHERE user_id = :user_id LIMIT 1");
$db->bind(':user_id', (int)$userData->id);
$row = $db->cdp_registro();

if (!$row) {
    echo json_encode([
        "status" => "no_update",
        "update_address" => 0,
        "update_phone" => 0,
        "update_document" => 0
    ]);
    exit;
}

echo json_encode([
    "status" => "ok",
    "update_address" => isset($row->update_address) ? (int)$row->update_address : 0,
    "update_phone" => isset($row->update_phone) ? (int)$row->update_phone : 0,
    "update_document" => isset($row->update_document) ? (int)$row->update_document : 0
]);