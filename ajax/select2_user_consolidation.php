<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// *************************************************************************

require_once("../loader.php");

$db = new Conexion;

// request params
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';
$cid    = isset($_REQUEST['consolidation_id']) ? intval($_REQUEST['consolidation_id']) : 0;

$data = [];

// require consolidation scope
if ($cid <= 0) {
    echo json_encode([]);
    exit;
}

// 1) get all order_ids from cdb_consolidate_detail for this consolidation
$db->cdp_query("SELECT order_id, order_no FROM cdb_consolidate_detail WHERE consolidate_id = :cid");
$db->bind(':cid', $cid);
$db->cdp_execute();
$detail_rows = $db->cdp_registros();

if (empty($detail_rows)) {
    echo json_encode([]);
    exit;
}

// collect unique order_ids (and order_nos if needed)
$order_ids = [];
foreach ($detail_rows as $d) {
    $oid = intval($d->order_id);
    if ($oid > 0) $order_ids[$oid] = $oid;
}
$order_ids = array_values($order_ids);

if (empty($order_ids)) {
    echo json_encode([]);
    exit;
}

// safe IN list for subsequent queries
$in_orders = implode(',', array_map('intval', $order_ids));

// 2) retrieve sender_id values for those orders in a single query
$db->cdp_query("SELECT DISTINCT IFNULL(sender_id, 0) AS sender_id FROM cdb_add_order WHERE order_id IN ({$in_orders})");
$db->cdp_execute();
$order_senders = $db->cdp_registros();

$sender_ids = [];
if (!empty($order_senders)) {
    foreach ($order_senders as $s) {
        $sid = intval($s->sender_id);
        if ($sid > 0) $sender_ids[$sid] = $sid;
    }
}
$sender_ids = array_values($sender_ids);

if (empty($sender_ids)) {
    echo json_encode([]);
    exit;
}

// 3) query users (senders) filtered by search and active = 1
$in_users = implode(',', array_map('intval', $sender_ids));

// prepare base SQL; if $search empty, we still bind '%'
$sql = "
    SELECT DISTINCT u.id, u.fname, u.lname, u.email, u.phone, u.locker
    FROM cdb_consolidate_detail d
    INNER JOIN cdb_add_order o ON d.order_id = o.order_id
    INNER JOIN cdb_users u ON o.sender_id = u.id
    WHERE d.consolidate_id = :cid
      AND u.active = 1
      AND (
           u.fname LIKE :s
        OR u.lname LIKE :s
        OR u.email LIKE :s
        OR u.phone LIKE :s
        OR u.locker LIKE :s
      )
    ORDER BY u.fname, u.lname
    LIMIT 50
";

$db->cdp_query($sql);
$db->bind(':cid', $cid);
$db->bind(':s', '%' . $search . '%');
$db->cdp_execute();
$users = $db->cdp_registros();

// format for Select2
foreach ($users as $u) {
    $text = trim($u->fname . ' ' . $u->lname);
    if ($text === '') $text = $u->email;
    $data[] = array('id' => $u->id, 'text' => $text);
}

echo json_encode($data);
