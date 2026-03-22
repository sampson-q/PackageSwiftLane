<?php
require_once("../loader.php");
require_once("../helpers/querys.php");

header('Content-Type: application/json; charset=UTF-8');

$user = new User();
$userData = $user->cdp_getUserData();
$db = new Conexion;

$country = cdp_sanitize($_POST['country'] ?? '');
$state   = cdp_sanitize($_POST['state'] ?? '');
$city    = cdp_sanitize($_POST['city'] ?? '');
$postal  = cdp_sanitize($_POST['postal'] ?? '');
$address = cdp_sanitize($_POST['address'] ?? '');

if ($country === '' || $state === '' || $city === '' || $postal === '' || $address === '') {
    echo json_encode([
        "status" => "error",
        "message" => "All address fields are required. " . $userData->id
    ]);
    exit;
}

$db->cdp_query("INSERT INTO cdb_senders_addresses (country, state, city, zip_code, address, user_id) VALUES (:country, :state, :city, :zip_code, :address, :user_id)");

$db->bind(':country', $country);
$db->bind(':state', $state);
$db->bind(':city', $city);
$db->bind(':zip_code', $postal);
$db->bind(':address', $address);
$db->bind(':user_id', (int) $userData->id);

if ($db->cdp_execute()) {
    // check if the user already has an entry in the update check table
    $db->cdp_query("SELECT * FROM cdb_user_details_update_check WHERE user_id = :user_id");
    $db->bind(':user_id', $userData->id);
    $update_info = $db->cdp_registro();
    
    if ($update_info) {
        $db->cdp_query("UPDATE cdb_user_details_update_check SET update_address = 1 WHERE user_id = :user_id");
        $db->bind(':user_id', $userData->id);
        $db->cdp_execute();
    } else {
        $db->cdp_query("INSERT INTO cdb_user_details_update_check (user_id, update_address) VALUES (:user_id, 1)");
        $db->bind(':user_id', $userData->id);
        $db->cdp_execute();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Address saved successfully."
    ]);

    exit;
}

echo json_encode([
    "status" => "error",
    "message" => "Could not save address."
]);