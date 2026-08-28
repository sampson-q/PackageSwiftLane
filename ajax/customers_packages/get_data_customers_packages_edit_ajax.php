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




require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');


$db = new Conexion;

$order_id = cdp_sanitize($_GET['id']);
$list = array();

$sql = "SELECT * FROM cdb_customers_packages_detail  WHERE  order_id='" . $order_id . "'";

$db->cdp_query($sql);
$db->cdp_execute();

$data = $db->cdp_registros();

foreach ($data as $key) {
    $list[] = array(
        'id' => $key->order_item_id,
        'qty' => $key->order_item_quantity,
        'description' => $key->order_item_description,
        'length' => $key->order_item_length,
        'width' => $key->order_item_width,
        'height' => $key->order_item_height,
        'weight' => $key->order_item_weight,
        'declared_value' => $key->order_item_declared_value,
        'fixed_value' => $key->order_item_fixed_value,
    );
}

echo json_encode($list);
