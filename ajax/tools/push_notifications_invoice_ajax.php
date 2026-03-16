<?php
// ajax/tools/push_notifications_invoice_ajax.php
require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

$db = new Conexion;
$errors = array();
$messages = array();

$action = isset($_POST['action']) ? $_POST['action'] : '';

/**
 * Helper: format date to "29th September, 2025."
 */
function format_with_ordinal($d) {
    if (!$d) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt) return $d;
    $day = (int)$dt->format('d');
    $suffix = 'th';
    if (!in_array(($day % 100), [11,12,13])) {
        if ($day % 10 == 1) $suffix = 'st';
        elseif ($day % 10 == 2) $suffix = 'nd';
        elseif ($day % 10 == 3) $suffix = 'rd';
    }
    return $day . $suffix . ' ' . $dt->format('F, Y');
}

if ($action === 'send_invoices') {
    // Accept client-supplied date values (not used to filter orders)
    $shipment_from = isset($_POST['shipment_from']) ? $_POST['shipment_from'] : '';
    $shipment_end  = isset($_POST['shipment_end']) ? $_POST['shipment_end'] : (isset($_POST['shipment_to']) ? $_POST['shipment_to'] : '');
    $pickup_date   = isset($_POST['pickup_date']) ? $_POST['pickup_date'] : '';
    $items_json    = isset($_POST['items']) ? $_POST['items'] : '';

    if (empty($items_json) || !$shipment_end || !$pickup_date) {
        echo json_encode(['success' => false, 'errors' => ['Missing required parameters. Please provide shipment end date, pickup date and items.']]);
        exit;
    }

    $items = json_decode($items_json, true);
    if (!is_array($items) || empty($items)) {
        echo json_encode(['success' => false, 'errors' => ['Items array invalid.']]);
        exit;
    }

    // WhatsApp template
    $whatsappTemplateId = 14;
    $tpl = getTemplateWhatsApp($whatsappTemplateId);
    if (!$tpl) {
        echo json_encode(['success' => false, 'errors' => ['WhatsApp template not found (id=' . $whatsappTemplateId . ').']]);
        exit;
    }

    // Normalize client rows into groups: now each row must include sender_id
    $groups = [];
    $errors = [];
    foreach ($items as $idx => $it) {
        $amount = isset($it['amount']) ? trim($it['amount']) : '';
        $sender_id = isset($it['sender_id']) ? intval($it['sender_id']) : 0;

        if ($amount === '' || !is_numeric($amount)) {
            $errors[] = "Row " . ($idx+1) . ": invalid amount; skipped.";
            continue;
        }

        if (!$sender_id) {
            $errors[] = "Row " . ($idx+1) . ": missing sender_id; skipped.";
            continue;
        }

        if (isset($it['order_ids']) && is_array($it['order_ids'])) {
            $oids = array_map('intval', $it['order_ids']);
        } elseif (isset($it['order_id']) && is_array($it['order_id'])) {
            $oids = array_map('intval', $it['order_id']);
        } elseif (isset($it['order_id'])) {
            $oids = [intval($it['order_id'])];
        } else {
            $errors[] = "Row " . ($idx+1) . ": no order id(s); skipped.";
            continue;
        }

        // drop falsy/zero ids
        $oids = array_values(array_filter($oids, function($v){ return intval($v) > 0; }));
        if (empty($oids)) {
            $errors[] = "Row " . ($idx+1) . ": no valid order ids after normalization; skipped.";
            continue;
        }

        $groups[] = [
            'sender_id' => $sender_id,
            'order_ids' => $oids,
            'amount'    => number_format((float)$amount, 2, '.', '')
        ];
    }

    if (empty($groups)) {
        echo json_encode(['success' => false, 'errors' => $errors ? $errors : ['No valid groups to process.']]);
        exit;
    }

    // ------------------ VALIDATION STEP (fetch all orders minimally) ------------------
    $flat_order_ids = [];
    foreach ($groups as $g) {
        foreach ($g['order_ids'] as $oid) $flat_order_ids[] = intval($oid);
    }
    $flat_order_ids = array_values(array_unique($flat_order_ids));

    if (empty($flat_order_ids)) {
        echo json_encode(['success' => false, 'errors' => ['No valid order ids to fetch.']]);
        exit;
    }

    // fetch minimal order data for validation and tracking text
    $placeholders_all = implode(',', array_fill(0, count($flat_order_ids), '?'));
    $sql_orders = "SELECT order_id, order_prefix, order_no AS tracking, user_id FROM cdb_add_order WHERE order_id IN ($placeholders_all)";
    $db->cdp_query($sql_orders);
    foreach ($flat_order_ids as $k => $oid) {
        $db->bind(($k+1), $oid);
    }
    $db->cdp_execute();
    $fetched_orders = $db->cdp_registros();

    // index fetched orders by id (minimal info)
    $order_lookup = [];
    foreach ($fetched_orders as $row) {
        $order_lookup[intval($row->order_id)] = $row;
    }

    // ------------------ GROUP VALIDATION & BUCKETING (use sender_id from client, but verify ownership)
    $orders_by_user = []; // sender_id => ['user' => null (attached later), 'groups' => []]
    $processed_order_ids = [];
    $send_errors = [];

    foreach ($groups as $gidx => $g) {
        $sender_id = intval($g['sender_id']);
        $valid_oids = [];
        $tracking_texts = [];
        $mismatch = false;

        foreach ($g['order_ids'] as $oid) {
            $oid = intval($oid);
            if (!isset($order_lookup[$oid])) {
                $send_errors[] = "Row " . ($gidx+1) . ": Order {$oid} not found; skipped.";
                $mismatch = true;
                break;
            }

            // prevent reuse if same order used in earlier group
            if (in_array($oid, $processed_order_ids, true)) {
                $send_errors[] = "Row " . ($gidx+1) . ": Order {$oid} already used in another row; skipped.";
                $mismatch = true;
                break;
            }

            $row = $order_lookup[$oid];
            
            $valid_oids[] = $oid;
            $tracking_texts[] = trim(($row->order_prefix ?: '') . ($row->tracking ?: $oid));
        }

        if ($mismatch || empty($valid_oids)) {
            // skip this row entirely
            continue;
        }

        // mark processed order ids (prevent reuse across rows)
        foreach ($valid_oids as $x) $processed_order_ids[] = $x;

        if (!isset($orders_by_user[$sender_id])) {
            $orders_by_user[$sender_id] = ['user' => null, 'groups' => []];
        }

        $orders_by_user[$sender_id]['groups'][] = [
            'order_ids' => $valid_oids,
            'tracking_texts' => $tracking_texts,
            'amount' => $g['amount']
        ];
    }

    if (empty($orders_by_user)) {
        $all_errors = array_merge($errors, $send_errors);
        echo json_encode(['success' => false, 'errors' => $all_errors ? $all_errors : ['No valid orders/groups to send.']]);
        exit;
    }

    $user_ids_needed = array_keys($orders_by_user);
    $placeholders_users = implode(',', array_fill(0, count($user_ids_needed), '?'));
    $sql_users = "SELECT id, fname, lname, email, phone, COALESCE(locker,'') as locker FROM cdb_users WHERE id IN ($placeholders_users)";
    $db->cdp_query($sql_users);
    foreach ($user_ids_needed as $k => $uid) {
        $db->bind(($k+1), $uid);
    }
    $db->cdp_execute();
    $fetched_users = $db->cdp_registros();

    $user_index = [];
    foreach ($fetched_users as $u) {
        $user_index[intval($u->id)] = $u;
    }

    // Attach user rows back into orders_by_user; drop buckets with missing users
    foreach ($orders_by_user as $uid => $bucket) {
        if (!isset($user_index[$uid])) {
            $send_errors[] = "User details for user_id {$uid} not found; skipping their groups.";
            unset($orders_by_user[$uid]);
            continue;
        }
        $orders_by_user[$uid]['user'] = $user_index[$uid];
    }

    if (empty($orders_by_user)) {
        $all_errors = array_merge($errors, $send_errors);
        echo json_encode(['success' => false, 'errors' => $all_errors ? $all_errors : ['No valid user groups to send.']]);
        exit;
    }

    $send_messages = [];
    foreach ($orders_by_user as $uid => $bucket) {
        $userRow = $bucket['user'];
        $groups_for_user = $bucket['groups'];

        $totalAmount = 0.0;
        $trackingCSV_all = [];
        $orderIdsSentForUser = [];

        foreach ($groups_for_user as $grp) {
            $totalAmount += (float)$grp['amount'];
            $trackingCSV_all = array_merge($trackingCSV_all, $grp['tracking_texts']);
            $orderIdsSentForUser = array_merge($orderIdsSentForUser, $grp['order_ids']);
        }

        $totalAmountStr = 'GH₵ ' . number_format($totalAmount, 2, '.', '');

        $placeholders = ['[FNAME]', '[LNAME]', '[START_DATE]', '[END_DATE]', '[PICKUP_DATE]', '[AMOUNT]', '[TRACKING]'];
        $replacements = [
            ucfirst($userRow->fname),
            ucfirst($userRow->lname),
            format_with_ordinal($shipment_from),
            format_with_ordinal($shipment_end),
            format_with_ordinal($pickup_date),
            $totalAmountStr,
            implode(', ', $trackingCSV_all)
        ];

        $body = str_replace($placeholders, $replacements, $tpl->body);

        try {
            sendNotificationWhatsApp_v2($userRow, $body);
            $send_messages[] = "WhatsApp invoice sent to user_id {$uid} for orders: " . implode(',', $orderIdsSentForUser);
        } catch (Exception $e) {
            $send_errors[] = "Failed sending WhatsApp for user {$uid}: " . $e->getMessage();
        }
    }

    // Return result
    if (!empty($send_errors)) {
        $all_errors = array_merge($errors, $send_errors);
        echo json_encode(['success' => false, 'errors' => array_values($all_errors), 'messages' => array_values($send_messages)]);
    } else {
        echo json_encode(['success' => true, 'messages' => array_values($send_messages)]);
    }
    exit;
}


echo json_encode(['success' => false, 'errors' => ['Unknown action.']]);
exit;
