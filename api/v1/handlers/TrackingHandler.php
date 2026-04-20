<?php
/**
 * TrackingHandler – public shipment / package tracking (no auth required).
 *
 * GET /api/v1/tracking/{order_no}
 *
 * Accepts full tracking numbers like "ABC-00001", "PKG-00005", or bare numbers.
 * Searches cdb_add_order, then cdb_customers_packages, then cdb_consolidate.
 */
class TrackingHandler
{
    public function show(string $orderNo): void
    {
        $orderNo = strtoupper(cdp_sanitize($orderNo));

        if (empty($orderNo)) {
            ApiResponse::badRequest('Tracking number is required.');
        }

        // ── 1. Standard shipment ───────────────────────────────────────────────
        $result = $this->findShipment($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        // ── 2. Customer package ───────────────────────────────────────────────
        $result = $this->findPackage($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        // ── 3. Consolidation ──────────────────────────────────────────────────
        $result = $this->findConsolidation($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        ApiResponse::notFound("No shipment found for tracking number: {$orderNo}");
    }

    private function findShipment(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT a.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_add_order a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            WHERE CONCAT(a.order_prefix, a.order_no) = :on
              AND a.status_courier != 14
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        // Tracking events
        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        return [
            'type'            => 'shipment',
            'tracking_number' => $orderNo,
            'status'          => (int)$row->status_courier,
            'status_label'    => $row->status_label,
            'status_color'    => $row->status_color,
            'order_date'      => $row->order_date ?? null,
            'due_date'        => $row->due_date ?? null,
            'total_order'     => (float)($row->total_order ?? 0),
            'is_pickup'       => (bool)(int)($row->is_pickup ?? 0),
            'events'          => $events ?: [],
        ];
    }

    private function findPackage(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT a.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_customers_packages a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            WHERE CONCAT(a.order_prefix, a.order_no) = :on
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        return [
            'type'            => 'package',
            'tracking_number' => $orderNo,
            'status'          => (int)$row->status_courier,
            'status_label'    => $row->status_label,
            'status_color'    => $row->status_color,
            'order_date'      => $row->order_date ?? null,
            'total_order'     => (float)($row->total_order ?? 0),
            'events'          => $events ?: [],
        ];
    }

    private function findConsolidation(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT c.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_consolidate c
            INNER JOIN cdb_styles s ON c.status_courier = s.id
            WHERE CONCAT(c.c_prefix, c.c_no) = :on
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        return [
            'type'            => 'consolidation',
            'tracking_number' => $orderNo,
            'status'          => (int)$row->status_courier,
            'status_label'    => $row->status_label,
            'status_color'    => $row->status_color,
            'order_date'      => $row->c_date ?? null,
            'total_order'     => (float)($row->total_order ?? 0),
            'events'          => $events ?: [],
        ];
    }
}
