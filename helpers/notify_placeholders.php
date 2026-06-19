<?php
/**
 * Shared notification enrichment.
 *
 * Builds the package-detail placeholders that every customer-facing email /
 * WhatsApp message about a shipment, package or consolidation should carry:
 * postal (carrier) tracking, estimated ETA, current status, the original total
 * weight, and the full item list with quantities. Monetary values are
 * deliberately excluded.
 *
 * Keyed by order_id so any trigger point (add / edit / consolidate / deliver)
 * produces identical, consistent details.
 *
 * @param int    $order_id  the order id in the module's order table.
 * @param string $module    'air' (cdb_add_order / cdb_add_order_item — the
 *                           default, used by courier shipments & air
 *                           consolidations) or 'sea' (cdb_customers_packages /
 *                           cdb_customers_packages_detail — customer packages &
 *                           sea consolidations). Mirrors the table map used by
 *                           cdp_notifyConsolidationPackageSenders().
 *
 * Returns an associative array of token => value, ready to merge into the
 * existing str_replace() calls at each trigger point:
 *   [POSTAL_TRACKING] [ETA] [STATUS] [WEIGHT] [ITEMS] [ITEMS_HTML]
 *
 * [ITEMS]      — plain text ("2 x Shoes\n1 x Laptop"), for WhatsApp/SMS.
 * [ITEMS_HTML] — an HTML <table>, for email.
 */
if (!function_exists('cdp_buildPackageNotifyPlaceholders')) {

    function cdp_buildPackageNotifyPlaceholders($order_id, $module = 'air')
    {
        $db       = new Conexion;
        $order_id = (int) $order_id;

        // Whitelisted module → table map (never interpolate user input here).
        $map = array(
            'air' => array('orders' => 'cdb_add_order',          'items' => 'cdb_add_order_item'),
            'sea' => array('orders' => 'cdb_customers_packages', 'items' => 'cdb_customers_packages_detail'),
        );
        if (!isset($map[$module])) {
            $module = 'air';
        }
        $ordersTable = $map[$module]['orders'];
        $itemsTable  = $map[$module]['items'];

        // Order: original total weight + current status label
        $db->cdp_query("SELECT a.total_weight, a.status_courier, b.mod_style
                        FROM {$ordersTable} a
                        LEFT JOIN cdb_styles b ON a.status_courier = b.id
                        WHERE a.order_id = :id LIMIT 1");
        $db->bind(':id', $order_id);
        $order = $db->cdp_registro();

        // Items (quantity + description only — never monetary fields)
        $db->cdp_query("SELECT order_item_quantity, order_item_description
                        FROM {$itemsTable} WHERE order_id = :id");
        $db->bind(':id', $order_id);
        $items = $db->cdp_registros();

        // Postal / carrier tracking + ETA. Air falls back to the legacy
        // cdb_add_order.tracking_num column for the ~41k old orders; sea has no
        // such legacy column, so read the new tracking table only.
        $postal = '';
        $eta    = '';
        $pt     = null;
        if ($module === 'sea') {
            if (function_exists('cdp_getPackageTracking')) {
                $pt = cdp_getPackageTracking($order_id);
            }
        } else {
            if (function_exists('cdp_getPackageTrackingLegacyAware')) {
                $pt = cdp_getPackageTrackingLegacyAware($order_id);
            }
        }
        if ($pt) {
            $postal = !empty($pt->tracking_number) ? (string) $pt->tracking_number : '';
            // ETA is ONLY the explicitly-entered estimated_eta. No fallback.
            $eta    = !empty($pt->estimated_eta)   ? (string) $pt->estimated_eta   : '';
        }

        // Build the item list in both plain-text and HTML form
        $lines = [];
        $rows  = '';
        if ($items) {
            foreach ($items as $it) {
                $qty  = (int) $it->order_item_quantity;
                $desc = trim((string) $it->order_item_description);
                $lines[] = $qty . ' x ' . $desc;
                $rows   .= '<tr>'
                    . '<td style="padding:4px 8px;border:1px solid #ddd;text-align:center;">' . $qty . '</td>'
                    . '<td style="padding:4px 8px;border:1px solid #ddd;">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }
        }

        $items_text = $lines ? implode("\n", $lines) : 'N/A';
        $items_html = $rows !== ''
            ? '<table style="border-collapse:collapse;width:100%;font-size:13px;">'
                . '<thead><tr>'
                . '<th style="padding:4px 8px;border:1px solid #ddd;background:#f3f3f3;">Qty</th>'
                . '<th style="padding:4px 8px;border:1px solid #ddd;background:#f3f3f3;">Description</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            : 'N/A';

        $status = ($order && !empty($order->mod_style)) ? $order->mod_style : '';
        // Treat a 0/empty total weight as "not set" so notifications don't show
        // a meaningless "Weight: 0" line.
        $weight = ($order && $order->total_weight !== null && (float) $order->total_weight != 0)
            ? (string) $order->total_weight : '';

        // Pre-built HTML details block for templates that expose a [SHIPMENT_DETAILS]
        // slot. Matches the existing email styling (left-accented light panels).
        $block = function ($label, $value) {
            return '<table border="0" cellpadding="0" cellspacing="0" width="100%" '
                . 'style="margin-bottom:8px;background:#fafafa;border-left:4px solid #f5a800;"><tr>'
                . '<td style="padding:12px 18px;">'
                . '<p style="margin:0 0 3px 0;font-size:10px;color:#aaaaaa;text-transform:uppercase;letter-spacing:1px;font-family:Roboto,Arial,Helvetica,sans-serif;">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div style="margin:0;font-size:15px;font-weight:700;color:#1a1a1a;font-family:Roboto,Arial,Helvetica,sans-serif;">'
                . $value . '</div></td></tr></table>';
        };

        $details = '';
        if ($status !== '') { $details .= $block('Status', htmlspecialchars($status, ENT_QUOTES, 'UTF-8')); }
        if ($weight !== '') { $details .= $block('Original Package Weight', htmlspecialchars($weight, ENT_QUOTES, 'UTF-8')); }
        if ($postal !== '') { $details .= $block('Carrier Tracking #', htmlspecialchars($postal, ENT_QUOTES, 'UTF-8')); }
        if ($eta    !== '') { $details .= $block('Estimated Arrival', htmlspecialchars($eta, ENT_QUOTES, 'UTF-8')); }
        if ($rows   !== '') { $details .= $block('Items', $items_html); }

        return [
            '[POSTAL_TRACKING]'  => $postal !== '' ? $postal : 'N/A',
            '[ETA]'              => $eta !== ''    ? $eta    : 'N/A',
            '[STATUS]'           => $status !== '' ? $status : 'N/A',
            '[WEIGHT]'           => $weight !== '' ? $weight : 'N/A',
            '[ITEMS]'            => $items_text,
            '[ITEMS_HTML]'       => $items_html,
            '[SHIPMENT_DETAILS]' => $details,
        ];
    }
}
