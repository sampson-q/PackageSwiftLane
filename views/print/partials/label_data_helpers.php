<?php
/**
 * Builders that turn a consolidation / customer-package row into the normalized
 * $L array consumed by label_ship_body.php, so consolidation and package labels
 * render exactly like the shipment label (print_label_ship.php).
 *
 * Guarded with function_exists so the file is safe to include inside loops.
 */

if (!function_exists('cdp_labelSenderLine')) {
    function cdp_labelSenderLine()
    {
        global $core;
        return trim(h($core->c_country ?? '') . ', ' . h($core->c_city ?? '') . ', ' . h($core->c_postal ?? ''), ', ');
    }
}

if (!function_exists('cdp_labelModelFromConsolidate')) {
    /**
     * @param Conexion $db
     * @param object   $row           a cdb_consolidate / cdb_consolidate_packages row
     * @param string   $detail_table  detail table to count packages from
     * @return array   normalized $L
     */
    function cdp_labelModelFromConsolidate($db, $row, $detail_table)
    {
        $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
        $courier_com = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
        $sender_data = $db->cdp_registro();

        if (($row->recipient_type ?? 'recipient') === 'user') {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
        } else {
            $db->cdp_query("SELECT * FROM cdb_recipients WHERE id='" . intval($row->receiver_id) . "'");
        }
        $receiver_data = $db->cdp_registro();
        if (!$receiver_data) {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
            $receiver_data = $db->cdp_registro();
        }

        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->c_prefix . $row->c_no . "'");
        $address_order = $db->cdp_registro();

        $db->cdp_query("SELECT COUNT(*) AS n FROM " . $detail_table . " WHERE consolidate_id='" . intval($row->consolidate_id) . "'");
        $cnt = $db->cdp_registro();
        $item_count = $cnt ? (int) $cnt->n : 0;

        return [
            'sys_tracking'  => $row->c_prefix . $row->c_no,
            'courier_track' => null,
            'courier_name'  => $courier_com->name_com ?? 'N/A',
            'item_count'    => $item_count,
            'weight'        => number_format((float) $row->total_weight, 2),
            'is_dangerous'  => !empty($row->is_dangerous_good ?? null),
            'sender_name'   => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
            'sender_line'   => cdp_labelSenderLine(),
            'sender_locker' => $sender_data->locker ?? '',
            'recip_name'    => trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? '')),
            'recip_line'    => $address_order->recipient_address ?? '',
            'recip_phone'   => $receiver_data->phone ?? '',
        ];
    }
}

if (!function_exists('cdp_labelModelFromCustomerPackage')) {
    /**
     * @param Conexion $db
     * @param object   $row  a cdb_customers_packages row
     * @return array   normalized $L
     */
    function cdp_labelModelFromCustomerPackage($db, $row)
    {
        $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
        $courier_com = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
        $sender_data = $db->cdp_registro();

        if (($row->recipient_type ?? 'recipient') === 'user') {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
        } else {
            $db->cdp_query("SELECT * FROM cdb_recipients WHERE id='" . intval($row->receiver_id) . "'");
        }
        $receiver_data = $db->cdp_registro();
        if (!$receiver_data) {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->receiver_id) . "'");
            $receiver_data = $db->cdp_registro();
        }

        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
        $address_order = $db->cdp_registro();

        $db->cdp_query("SELECT COUNT(*) AS n FROM cdb_customers_packages_detail WHERE order_id='" . intval($row->order_id) . "'");
        $cnt = $db->cdp_registro();
        $item_count = $cnt ? (int) $cnt->n : 0;

        // The online-shop / postal tracking a customer bought under doubles as the
        // carrier/postal barcode — handy on the small gadget label.
        $purchase_track = trim((string) ($row->tracking_purchase ?? ''));

        return [
            'sys_tracking'  => $row->order_prefix . $row->order_no,
            'courier_track' => $purchase_track !== '' ? $purchase_track : null,
            'courier_name'  => $courier_com->name_com ?? 'N/A',
            'item_count'    => $item_count,
            'weight'        => number_format((float) $row->total_weight, 2),
            'is_dangerous'  => !empty($row->is_dangerous_good ?? null),
            'sender_name'   => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
            'sender_line'   => trim(($address_order->sender_country ?? '') . ', ' . ($address_order->sender_city ?? ''), ', '),
            'sender_locker' => $sender_data->locker ?? '',
            'recip_name'    => trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? '')),
            'recip_line'    => $address_order->recipient_address ?? '',
            'recip_phone'   => $receiver_data->phone ?? '',
        ];
    }
}
