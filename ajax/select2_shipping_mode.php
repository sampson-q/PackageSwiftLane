<?php
require_once("../loader.php");

$db = new Conexion;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$safe = $q !== '' ? cdp_sanitize($q) : '';

// Trae solo categorías que estén usadas en tarifas (opcional pero útil)
$sql = "SELECT DISTINCT c.id, c.name_item
          FROM cdb_category c
          INNER JOIN cdb_shipping_fees f ON f.order_service_options = c.id
         WHERE c.name_item <> ''";

if ($safe !== '') {
  $sql .= " AND c.name_item LIKE '%$safe%'";
}
$sql .= " ORDER BY c.name_item ASC LIMIT 50";

$db->cdp_query($sql);
$db->cdp_execute();
$rows = $db->cdp_registros();

$out = [];
if ($rows) {
  foreach ($rows as $r) {
    $out[] = ['id' => (int)$r->id, 'text' => $r->name_item];
  }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
