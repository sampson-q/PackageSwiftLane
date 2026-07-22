<?php
/**
 * Builders that turn a consolidation / customer-package row into the normalized
 * $INV array consumed by inv_ship_body.php, so those receipts render exactly
 * like the shipment invoice (print_inv_ship.php). Monetary totals stay hidden
 * (packing-slip style) to match the reference.
 *
 * Guarded with function_exists so the file is safe to include inside loops.
 */

if (!function_exists('cdp_invModelFromOrder')) {
    /**
     * Shipment (cdb_add_order) -> $INV, incl. the FS PAID stamp + financial S/N,
     * so the shipment invoice and its "track" copy share one builder.
     *
     * @param Conexion $db
     * @param object   $row  cdb_add_order row
     * @return array   normalized $INV
     */
    function cdp_invModelFromOrder($db, $row)
    {
        if (is_file('helpers/fs_payments.php')) {
            require_once('helpers/fs_payments.php');
        }
        $orderId = (int) $row->order_id;

        $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $orderId . "'");
        $order_items = $db->cdp_registros();

        $db->cdp_query("SELECT * FROM cdb_category WHERE id='" . intval($row->order_item_category) . "'");
        $category = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
        $courier_com = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
        $sender_data = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
        $address_order = $db->cdp_registro();

        $package_tracking = cdp_getPackageTrackingLegacyAware($orderId);

        $financial_serial = cdp_getOrderFinancialSerial(
            $row->order_prefix,
            $row->order_no,
            (int) ($_REQUEST['consolidate_id'] ?? 0)
        );

        $fs_payment = function_exists('cdp_fsPaymentForOrder') ? cdp_fsPaymentForOrder($orderId) : null;
        $fs_paid_mode = '';
        if ($fs_payment) {
            $modes = ['cash' => 'Cash', 'paystack' => 'Mobile Money (Paystack)',
                      'hubtel' => 'Mobile Money (Hubtel)', 'paypal' => 'PayPal'];
            $fs_paid_mode = $modes[strtolower((string) $fs_payment->mode)] ?? ucfirst((string) $fs_payment->mode);
        }

        $items = [];
        $total_weight = 0.0;
        $total_qty = 0;
        if ($order_items) {
            foreach ($order_items as $it) {
                $total_weight += (float) $it->order_item_weight;
                $total_qty    += (int) $it->order_item_quantity;
                $items[] = ['qty' => (int) $it->order_item_quantity, 'desc' => $it->order_item_description];
            }
        }

        return [
            'sys_tracking'     => $row->order_prefix . $row->order_no,
            'sender_name'      => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
            'sender_address'   => $address_order ? ($address_order->sender_address ?? 'N/A') : 'N/A',
            'sender_location'  => $address_order ? trim(($address_order->sender_city ?? '') . ', ' . ($address_order->sender_country ?? ''), ', ') : 'N/A',
            'sender_phone'     => $sender_data->phone ?? '',
            'carrier_tracking' => $package_tracking->tracking_number ?? '',
            'courier_name'     => $courier_com ? $courier_com->name_com : 'N/A',
            'category_name'    => $category ? $category->name_item : 'N/A',
            'financial_serial' => $financial_serial,
            'items'            => $items,
            'total_weight'     => (string) ($row->total_weight ?? $total_weight),
            'total_qty'        => $total_qty,
            'fs_payment'       => $fs_payment,
            'fs_paid_mode'     => $fs_paid_mode,
        ];
    }
}

if (!function_exists('cdp_invModelFromConsolidate')) {
    /**
     * @param Conexion $db
     * @param object   $row           cdb_consolidate / cdb_consolidate_packages row
     * @param string   $detail_table  member-package detail table
     * @return array   normalized $INV
     */
    function cdp_invModelFromConsolidate($db, $row, $detail_table)
    {
        $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
        $courier_com = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_category WHERE id='" . intval($row->order_item_category) . "'");
        $category = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
        $sender_data = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->c_prefix . $row->c_no . "'");
        $address_order = $db->cdp_registro();

        // Members of the consolidation become the line items (one row per package).
        $db->cdp_query("SELECT * FROM " . $detail_table . " WHERE consolidate_id='" . intval($row->consolidate_id) . "'");
        $detail = $db->cdp_registros();

        $items = [];
        $total_qty = 0;
        if (is_array($detail)) {
            foreach ($detail as $d) {
                $track = ($d->order_prefix ?? '') . ($d->order_no ?? '');
                $wt = isset($d->weight) ? number_format((float) $d->weight, 2) : '';
                $items[] = ['qty' => 1, 'desc' => trim($track . ($wt !== '' ? '  (' . $wt . ')' : ''))];
                $total_qty++;
            }
        }

        return [
            'sys_tracking'     => $row->c_prefix . $row->c_no,
            'sender_name'      => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
            'sender_address'   => $address_order ? ($address_order->sender_address ?? 'N/A') : 'N/A',
            'sender_location'  => $address_order ? trim(($address_order->sender_city ?? '') . ', ' . ($address_order->sender_country ?? ''), ', ') : 'N/A',
            'sender_phone'     => $sender_data->phone ?? '',
            'carrier_tracking' => '',
            'courier_name'     => $courier_com ? $courier_com->name_com : 'N/A',
            'category_name'    => $category ? $category->name_item : 'N/A',
            'financial_serial' => null,
            'items'            => $items,
            'total_weight'     => (string) ($row->total_weight ?? '—'),
            'total_qty'        => $total_qty,
            'fs_payment'       => null,
            'fs_paid_mode'     => '',
        ];
    }
}

if (!function_exists('cdp_invModelFromCustomerPackage')) {
    /**
     * @param Conexion $db
     * @param object   $row  cdb_customers_packages row
     * @return array   normalized $INV
     */
    function cdp_invModelFromCustomerPackage($db, $row)
    {
        $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id='" . intval($row->order_courier) . "'");
        $courier_com = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_category WHERE id='" . intval($row->order_item_category) . "'");
        $category = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . intval($row->sender_id) . "'");
        $sender_data = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
        $address_order = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_customers_packages_detail WHERE order_id='" . intval($row->order_id) . "'");
        $detail = $db->cdp_registros();

        $items = [];
        $total_qty = 0;
        if (is_array($detail)) {
            foreach ($detail as $d) {
                $qty = (int) ($d->order_item_quantity ?? 0);
                $items[] = ['qty' => $qty, 'desc' => $d->order_item_description ?? ''];
                $total_qty += $qty;
            }
        }

        $purchase_track = trim((string) ($row->tracking_purchase ?? ''));

        return [
            'sys_tracking'     => $row->order_prefix . $row->order_no,
            'sender_name'      => trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')),
            'sender_address'   => $address_order ? ($address_order->sender_address ?? 'N/A') : 'N/A',
            'sender_location'  => $address_order ? trim(($address_order->sender_city ?? '') . ', ' . ($address_order->sender_country ?? ''), ', ') : 'N/A',
            'sender_phone'     => $sender_data->phone ?? '',
            'carrier_tracking' => $purchase_track,
            'courier_name'     => $courier_com ? $courier_com->name_com : 'N/A',
            'category_name'    => $category ? $category->name_item : 'N/A',
            'financial_serial' => null,
            'items'            => $items,
            'total_weight'     => (string) ($row->total_weight ?? '—'),
            'total_qty'        => $total_qty,
            'fs_payment'       => null,
            'fs_paid_mode'     => '',
        ];
    }
}
