<?php
// ajax/select2_user_orders.php
require_once("../loader.php");

$db = new Conexion();

header('Content-Type: application/json; charset=utf-8');

// expects GET params: sender_id (int), optional exclude (CSV order_ids), optional ship_from, ship_to (YYYY-MM-DD)
$sender_id = isset($_GET['sender_id']) ? intval($_GET['sender_id']) : 0;
$exclude = isset($_GET['exclude']) ? trim($_GET['exclude']) : '';
$ship_from = isset($_GET['ship_from']) ? trim($_GET['ship_from']) : '';
$ship_to   = isset($_GET['ship_to']) ? trim($_GET['ship_to']) : '';

if (!$sender_id) {
    echo json_encode([]);
    exit;
}

// sanitize exclude list into ints
$ex_clause = '';
if ($exclude !== '') {
    $ids = array_filter(array_map('intval', explode(',', $exclude)));
    if (!empty($ids)) {
        // safe because we cast to int above
        $ex_clause = ' AND o.order_id NOT IN (' . implode(',', $ids) . ') ';
    }
}

// build date clause (inclusive). if either date is missing we omit parts.
$date_clause = '';
$params = [':sender_id' => $sender_id];

if ($ship_from !== '' && $ship_to !== '') {
    // validate basic date format (YYYY-MM-DD) to avoid SQL surprises; fall back to no date filter if invalid
    $from_ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_from);
    $to_ok   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_to);
    if ($from_ok && $to_ok) {
        $date_clause = ' AND DATE(o.order_date) BETWEEN :ship_from AND :ship_to ';
        $params[':ship_from'] = $ship_from;
        $params[':ship_to']   = $ship_to;
    }
} elseif ($ship_from !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_from)) {
        $date_clause = ' AND DATE(o.order_date) >= :ship_from ';
        $params[':ship_from'] = $ship_from;
    }
} elseif ($ship_to !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_to)) {
        $date_clause = ' AND DATE(o.order_date) <= :ship_to ';
        $params[':ship_to'] = $ship_to;
    }
}

// Query: fetch orders where the DB's sender_id = selected sender and optional date filters apply
$sql = "SELECT o.order_id, o.order_prefix, o.order_no, COALESCE(pt.tracking_number, '') AS postal_tracking
        FROM cdb_add_order o
        LEFT JOIN cdb_package_tracking_number pt ON pt.order_id = o.order_id
        WHERE o.sender_id = :sender_id
        {$date_clause}
        {$ex_clause}
        ORDER BY o.order_date DESC
        LIMIT 1000";

try {
    $db->cdp_query($sql);
    // bind params
    foreach ($params as $k => $v) {
        $db->bind($k, $v);
    }
    $db->cdp_execute();
    $rows = $db->cdp_registros();

    $results = [];
    foreach ($rows as $r) {
        $tracking = trim($r->order_prefix . $r->order_no);
        $text = $tracking;
        if ($r->postal_tracking) $text .= ' — ' . $r->postal_tracking;
        $results[] = [
            'id' => (int)$r->order_id,
            'text' => $text,
            'tracking' => $tracking,
            'postal_tracking' => $r->postal_tracking
        ];
    }

    echo json_encode($results);
    exit;
} catch (Exception $e) {
    // On error, return empty list (optionally you could log $e->getMessage())
    echo json_encode([]);
    exit;
}
