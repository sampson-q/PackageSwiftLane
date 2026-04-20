<?php
/**
 * ShipmentsHandler – CRUD for courier shipments (cdb_add_order).
 *
 * GET    /api/v1/shipments             list (paginated, filtered, sorted)
 * POST   /api/v1/shipments             create
 * GET    /api/v1/shipments/{id}        show
 * PATCH  /api/v1/shipments/{id}/status update status
 * DELETE /api/v1/shipments/{id}        delete
 *
 * Query params for GET /shipments:
 *   search, status, page, per_page, sort (order_id|order_date|total_order),
 *   direction (asc|desc), date_from, date_to, agency_id, sender_id
 */
class ShipmentsHandler
{
    // ── GET /api/v1/shipments ─────────────────────────────────────────────────

    public function index(): void
    {
        $authUser = ApiAuth::requirePermission('view_shipment_list');

        $db       = new Conexion();
        $page     = max(1, (int)($_GET['page']     ?? 1));
        $perPage  = min(100, max(1, (int)($_GET['per_page'] ?? 15)));
        $offset   = ($page - 1) * $perPage;

        $search    = cdp_sanitize($_GET['search']    ?? '');
        $status    = (int)($_GET['status']            ?? 0);
        $agencyId  = (int)($_GET['agency_id']         ?? 0);
        $senderId  = (int)($_GET['sender_id']         ?? 0);
        $dateFrom  = cdp_sanitize($_GET['date_from']  ?? '');
        $dateTo    = cdp_sanitize($_GET['date_to']    ?? '');
        $sortCol   = in_array($_GET['sort'] ?? '', ['order_id', 'order_date', 'total_order'])
                     ? $_GET['sort'] : 'order_id';
        $sortDir   = strtoupper($_GET['direction'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $where = "WHERE a.status_courier != 14";   // exclude cancelled/deleted

        // Role-based scoping
        $ulevel = (int)$authUser->userlevel;
        if ($ulevel === 1) {
            // Customer: only own shipments
            $where .= " AND a.sender_id = " . (int)$authUser->id;
        } elseif ($ulevel === 3) {
            // Driver: only assigned
            $where .= " AND a.driver_id = " . (int)$authUser->id;
        } elseif ($ulevel === 6) {
            // Agency
            $agBranch = cdp_getAgencyBranchIdForUser($authUser->name_off ?? '');
            if ($agBranch) {
                $where .= " AND a.agency = {$agBranch}";
            }
        } else {
            // Admin/employee – optional agency filter
            if ($agencyId > 0) {
                $where .= " AND a.agency = {$agencyId}";
            }
        }

        if ($status > 0)  $where .= " AND a.status_courier = {$status}";
        if ($senderId > 0) $where .= " AND a.sender_id = {$senderId}";
        if ($search !== '') {
            $search = str_replace("'", "''", $search);
            $where .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE '%{$search}%')";
        }
        if ($dateFrom !== '') {
            $df = date('Y-m-d', strtotime($dateFrom));
            $where .= " AND a.order_date >= '{$df} 00:00:00'";
        }
        if ($dateTo !== '') {
            $dt = date('Y-m-d', strtotime($dateTo));
            $where .= " AND a.order_date <= '{$dt} 23:59:59'";
        }

        $baseSql = "
            SELECT a.order_id, a.order_prefix, a.order_no, a.order_date,
                   a.sender_id, a.receiver_id, a.agency, a.origin_off,
                   a.order_courier, a.order_service_options, a.order_deli_time,
                   a.order_payment_method, a.order_pay_mode, a.driver_id,
                   a.status_courier, a.status_invoice, a.is_pickup, a.is_consolidate,
                   a.total_weight, a.sub_total, a.total_tax, a.total_tax_insurance,
                   a.total_tax_discount, a.total_tax_custom_tariffis, a.total_reexp,
                   a.total_fixed_value, a.total_declared_value, a.total_order,
                   a.due_date, a.order_incomplete,
                   s.mod_style AS status_label, s.color AS status_color,
                   CONCAT(u.fname,' ',u.lname) AS sender_name,
                   CONCAT(r.fname,' ',r.lname) AS receiver_name
            FROM cdb_add_order a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            LEFT  JOIN cdb_users  u ON a.sender_id   = u.id
            LEFT  JOIN cdb_users  r ON a.receiver_id = r.id
            {$where}
        ";

        // Count
        $db->cdp_query("SELECT COUNT(*) AS total FROM cdb_add_order a {$where}");
        $db->cdp_execute();
        $countRow = $db->cdp_registro();
        $total    = (int)($countRow->total ?? 0);

        // Page
        $db->cdp_query("{$baseSql} ORDER BY a.{$sortCol} {$sortDir} LIMIT {$offset}, {$perPage}");
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatRow'], $rows ?: []);
        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    // ── POST /api/v1/shipments ────────────────────────────────────────────────

    public function create(): void
    {
        $authUser = ApiAuth::requirePermission('add_shipment');
        $data     = ApiResponse::getRequestData();

        $errors = ApiValidator::validate($data, [
            'sender_id'             => 'required|integer',
            'sender_address_id'     => 'required|integer',
            'recipient_id'          => 'required|integer',
            'recipient_address_id'  => 'required|integer',
            'agency'                => 'required|integer',
            'origin_off'            => 'required|integer',
            'order_item_category'   => 'required|integer',
            'order_package'         => 'required|integer',
            'order_courier'         => 'required|integer',
            'order_service_options' => 'required|integer',
            'order_deli_time'       => 'required|integer',
            'order_payment_method'  => 'required|integer',
            'status_courier'        => 'required|integer',
            'order_date'            => 'required|date',
        ]);

        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $core       = new Core();
        $orderTrack = $core->cdp_order_track();

        $payMethods = new Conexion();
        $payMethods->cdp_query('SELECT * FROM cdb_payment_methods WHERE id = :id LIMIT 1');
        $payMethods->bind(':id', (int)$data['order_payment_method']);
        $payMethods->cdp_execute();
        $pm = $payMethods->cdp_registro();

        $days          = $pm ? (int)$pm->days : 0;
        $saleDate      = date('Y-m-d H:i:s');
        $dueDate       = cdp_sumardias($saleDate, $days);
        $statusInvoice = ($days === 0) ? 1 : 2;

        $codePrefix = cdp_sanitize($data['code_prefix'] ?? $core->prefix);
        $date       = date('Y-m-d', strtotime(cdp_sanitize($data['order_date']))) . ' ' . date('H:i:s');

        $dataShipment = [
            'user_id'               => (int)$authUser->id,
            'order_prefix'          => $codePrefix,
            'is_pickup'             => 0,
            'order_incomplete'      => 1,
            'order_no'              => cdp_sanitize($data['order_no'] ?? $orderTrack),
            'order_datetime'        => $date,
            'sender_id'             => (int)$data['sender_id'],
            'recipient_id'          => (int)$data['recipient_id'],
            'sender_address_id'     => (int)$data['sender_address_id'],
            'recipient_address_id'  => (int)$data['recipient_address_id'],
            'order_date'            => $saleDate,
            'agency'                => (int)$data['agency'],
            'origin_off'            => (int)$data['origin_off'],
            'order_package'         => (int)$data['order_package'],
            'order_item_category'   => (int)$data['order_item_category'],
            'order_courier'         => (int)$data['order_courier'],
            'order_service_options' => (int)$data['order_service_options'],
            'order_deli_time'       => (int)$data['order_deli_time'],
            'order_payment_method'  => (int)$data['order_payment_method'],
            'status_courier'        => (int)$data['status_courier'],
            'driver_id'             => (int)($data['driver_id'] ?? 0),
            'due_date'              => $dueDate,
            'status_invoice'        => $statusInvoice,
            'volumetric_percentage' => (float)($data['volumetric_percentage'] ?? $core->meter),
            'manual_tariff'         => 1,
            'tracking_number'       => (int)($data['tracking_number'] ?? 0),
            'estimated_eta'         => cdp_sanitize($data['estimated_eta'] ?? ''),
        ];

        $shipmentId = cdp_insertCourierShipment($dataShipment);

        if ($shipmentId === null) {
            ApiResponse::serverError('Failed to create shipment.');
        }

        // Insert package lines if provided
        if (!empty($data['packages']) && is_array($data['packages'])) {
            foreach ($data['packages'] as $pkg) {
                $lineData = [
                    'order_id'      => $shipmentId,
                    'qty'           => max(1, (int)($pkg['qty'] ?? 1)),
                    'description'   => cdp_sanitize($pkg['description'] ?? ''),
                    'length'        => (float)($pkg['length'] ?? 0),
                    'width'         => (float)($pkg['width'] ?? 0),
                    'height'        => (float)($pkg['height'] ?? 0),
                    'weight'        => (float)($pkg['weight'] ?? 0),
                    'declared_value'=> (float)($pkg['declared_value'] ?? 0),
                    'fixed_value'   => (float)($pkg['fixed_value'] ?? 0),
                ];
                cdp_insertCourierShipmentPackages($lineData);
            }
        }

        // Fetch the created record
        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_add_order WHERE order_id = :id LIMIT 1');
        $db->bind(':id', $shipmentId);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        ApiResponse::created(self::formatRow($row), 'Shipment created successfully.');
    }

    // ── GET /api/v1/shipments/{id} ────────────────────────────────────────────

    public function show(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_shipment_list');

        $db = new Conexion();
        $db->cdp_query("
            SELECT a.*, s.mod_style AS status_label, s.color AS status_color,
                   CONCAT(u.fname,' ',u.lname) AS sender_name,
                   CONCAT(r.fname,' ',r.lname) AS receiver_name
            FROM cdb_add_order a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            LEFT  JOIN cdb_users  u ON a.sender_id   = u.id
            LEFT  JOIN cdb_users  r ON a.receiver_id = r.id
            WHERE a.order_id = :id AND a.status_courier != 14
            LIMIT 1
        ");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Shipment #{$id} not found.");
        }

        // Access control: customers only see own
        if ((int)$authUser->userlevel === 1 && (int)$row->sender_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        // Package lines
        $db->cdp_query('SELECT * FROM cdb_add_order_item WHERE order_id = :id ORDER BY id ASC');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $lines = $db->cdp_registros();

        // Tracking history
        $db->cdp_query("
            SELECT ct.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :track
            ORDER BY ct.id ASC
        ");
        $db->bind(':track', $row->order_prefix . $row->order_no);
        $db->cdp_execute();
        $tracking = $db->cdp_registros();

        $result               = self::formatRow($row);
        $result['packages']   = $lines ?: [];
        $result['tracking']   = $tracking ?: [];

        ApiResponse::success($result);
    }

    // ── PATCH /api/v1/shipments/{id}/status ──────────────────────────────────

    public function updateStatus(int $id): void
    {
        $authUser = ApiAuth::requirePermission(['edit_shipment', 'edit_shipment_status']);
        $data     = ApiResponse::getRequestData();

        $errors = ApiValidator::validate($data, [
            'status'   => 'required|integer',
            'comments' => 'nullable|string|max:500',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $db = new Conexion();
        $db->cdp_query('SELECT order_id, order_prefix, order_no FROM cdb_add_order WHERE order_id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();
        if (!$row) {
            ApiResponse::notFound("Shipment #{$id} not found.");
        }

        // Update status_courier directly by order_id
        $db->cdp_query('UPDATE cdb_add_order SET status_courier = :status WHERE order_id = :id');
        $db->bind(':status', (int)$data['status']);
        $db->bind(':id',     $id);
        $db->cdp_execute();

        // Insert tracking event
        $comment = cdp_sanitize($data['comments'] ?? '');
        $date    = date('Y-m-d H:i:s');
        $db->cdp_query("
            INSERT INTO cdb_courier_track (order_track, comments, t_date, status_courier, user_id)
            VALUES (:track, :comments, :date, :status, :uid)
        ");
        $db->bind(':track',    $row->order_prefix . $row->order_no);
        $db->bind(':comments', $comment);
        $db->bind(':date',     $date);
        $db->bind(':status',   (int)$data['status']);
        $db->bind(':uid',      (int)$authUser->id);
        $db->cdp_execute();

        ApiResponse::success(['id' => $id, 'status' => (int)$data['status']]);
    }

    // ── DELETE /api/v1/shipments/{id} ─────────────────────────────────────────

    public function delete(int $id): void
    {
        ApiAuth::requirePermission('delete_shipment');

        $db = new Conexion();
        $db->cdp_query('SELECT order_id FROM cdb_add_order WHERE order_id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();
        if (!$row) {
            ApiResponse::notFound("Shipment #{$id} not found.");
        }

        // Soft-delete: set status to 14 (deleted/cancelled)
        $db->cdp_query('UPDATE cdb_add_order SET status_courier = 14 WHERE order_id = :id');
        $db->bind(':id', $id);
        $db->cdp_execute();

        ApiResponse::success(['id' => $id, 'deleted' => true]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    public static function formatRow($row): array
    {
        if (!$row) {
            return [];
        }
        return [
            'id'                   => (int)$row->order_id,
            'order_prefix'         => $row->order_prefix,
            'order_no'             => $row->order_no,
            'tracking_number'      => ($row->order_prefix ?? '') . ($row->order_no ?? ''),
            'order_date'           => $row->order_date ?? null,
            'sender_id'            => (int)$row->sender_id,
            'sender_name'          => $row->sender_name ?? null,
            'receiver_id'          => (int)$row->receiver_id,
            'receiver_name'        => $row->receiver_name ?? null,
            'agency'               => (int)($row->agency ?? 0),
            'origin_off'           => (int)($row->origin_off ?? 0),
            'order_courier'        => (int)($row->order_courier ?? 0),
            'order_service_options'=> (int)($row->order_service_options ?? 0),
            'order_deli_time'      => (int)($row->order_deli_time ?? 0),
            'order_payment_method' => (int)($row->order_payment_method ?? 0),
            'driver_id'            => (int)($row->driver_id ?? 0),
            'status'               => (int)($row->status_courier ?? 0),
            'status_label'         => $row->status_label ?? null,
            'status_color'         => $row->status_color ?? null,
            'status_invoice'       => (int)($row->status_invoice ?? 0),
            'is_pickup'            => (bool)(int)($row->is_pickup ?? 0),
            'is_consolidate'       => (bool)(int)($row->is_consolidate ?? 0),
            'total_weight'         => (float)($row->total_weight ?? 0),
            'sub_total'            => (float)($row->sub_total ?? 0),
            'total_tax'            => (float)($row->total_tax ?? 0),
            'total_tax_insurance'  => (float)($row->total_tax_insurance ?? 0),
            'total_tax_discount'   => (float)($row->total_tax_discount ?? 0),
            'total_tax_custom_tariffis' => (float)($row->total_tax_custom_tariffis ?? 0),
            'total_reexp'          => (float)($row->total_reexp ?? 0),
            'total_fixed_value'    => (float)($row->total_fixed_value ?? 0),
            'total_declared_value' => (float)($row->total_declared_value ?? 0),
            'total_order'          => (float)($row->total_order ?? 0),
            'due_date'             => $row->due_date ?? null,
            'order_incomplete'     => (bool)(int)($row->order_incomplete ?? 0),
        ];
    }
}
