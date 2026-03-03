<?php
require_once("../loader.php");
$db = new Conexion;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT id, name_item
          FROM cdb_category
         WHERE name_item <> ''";
if ($q !== '') {
  $safe = cdp_sanitize($q);
  $sql .= " AND name_item LIKE '%$safe%'";
}
$sql .= " ORDER BY name_item ASC LIMIT 50";

$db->cdp_query($sql);
$rows = $db->cdp_registros();

$out = [];
if ($rows) {
  foreach ($rows as $r) {
    $out[] = ['id' => (int)$r->id, 'text' => $r->name_item];
  }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
