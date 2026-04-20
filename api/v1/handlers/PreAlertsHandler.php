<?php
/**
 * PreAlertsHandler – CRUD for pre-alerts (cdb_pre_alert).
 *
 * GET    /api/v1/pre-alerts          list
 * POST   /api/v1/pre-alerts          create (multipart/form-data accepted)
 * GET    /api/v1/pre-alerts/{id}     show
 * DELETE /api/v1/pre-alerts/{id}     delete
 */
class PreAlertsHandler
{
    // ── GET /api/v1/pre-alerts ────────────────────────────────────────────────

    public function index(): void
    {
        $authUser = ApiAuth::requirePermission('prealert_list');

        $db      = new Conexion();
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 15)));
        $offset  = ($page - 1) * $perPage;
        $search  = cdp_sanitize($_GET['search'] ?? '');
        $sortDir = strtoupper($_GET['direction'] ?? '') === 'DESC' ? 'DESC' : 'DESC';

        $where = 'WHERE 1=1';

        $ulevel = (int)$authUser->userlevel;
        if ($ulevel === 1) {
            // Customers only see their own pre-alerts
            $where .= ' AND p.customer_id = ' . (int)$authUser->id;
        }

        if ($search !== '') {
            $s = str_replace("'", "''", $search);
            $where .= " AND (p.tracking LIKE '%{$s}%' OR p.provider_shop LIKE '%{$s}%')";
        }

        $db->cdp_query("SELECT COUNT(*) AS total FROM cdb_pre_alert p {$where}");
        $db->cdp_execute();
        $total = (int)($db->cdp_registro()->total ?? 0);

        $db->cdp_query("
            SELECT p.*, cc.name_com AS courier_name,
                   CONCAT(u.fname,' ',u.lname) AS customer_name
            FROM cdb_pre_alert p
            LEFT JOIN cdb_courier_com cc ON p.courier_com = cc.id
            LEFT JOIN cdb_users       u  ON p.customer_id  = u.id
            {$where}
            ORDER BY p.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatRow'], $rows ?: []);
        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    // ── POST /api/v1/pre-alerts ───────────────────────────────────────────────

    public function create(): void
    {
        $authUser = ApiAuth::requirePermission('prealert_list');
        $data     = ApiResponse::getRequestData();

        $errors = ApiValidator::validate($data, [
            'tracking'    => 'required|string|min:4|max:55',
            'provider'    => 'required|string|min:2|max:100',
            'courier_id'  => 'required|integer',
            'price'       => 'required|numeric|min:0',
            'description' => 'required|string|min:2|max:500',
            'date'        => 'required|date',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        // Duplicate tracking check
        $db = new Conexion();
        $db->cdp_query("SELECT id FROM cdb_pre_alert WHERE tracking = :t AND customer_id = :cid LIMIT 1");
        $db->bind(':t',   cdp_sanitize($data['tracking']));
        $db->bind(':cid', (int)$authUser->id);
        $db->cdp_execute();
        if ($db->cdp_registro()) {
            ApiResponse::conflict('A pre-alert with this tracking number already exists for your account.');
        }

        // Handle optional file upload
        $invoiceFile = '';
        if (!empty($_FILES['file_invoice']['name'])) {
            $uploadDir  = __DIR__ . '/../../../../pre_alert_files/';
            $imageName  = time() . '_' . basename($_FILES['file_invoice']['name']);
            $targetFile = $uploadDir . $imageName;

            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $mimeType = mime_content_type($_FILES['file_invoice']['tmp_name']);
            if (!in_array($mimeType, $allowed, true)) {
                ApiResponse::badRequest('Only images (JPG, PNG, GIF) and PDF files are allowed.');
            }
            if ($_FILES['file_invoice']['size'] > 5 * 1024 * 1024) {
                ApiResponse::badRequest('File size must not exceed 5 MB.');
            }
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (!move_uploaded_file($_FILES['file_invoice']['tmp_name'], $targetFile)) {
                ApiResponse::serverError('Failed to upload invoice file.');
            }
            $invoiceFile = 'pre_alert_files/' . $imageName;
        }

        $prealertData = [
            'tracking_prealert'    => cdp_sanitize($data['tracking']),
            'provider_prealert'    => cdp_sanitize($data['provider']),
            'courier_prealert'     => (int)$data['courier_id'],
            'customer_id'          => (int)$authUser->id,
            'price_prealert'       => cdp_sanitize($data['price']),
            'description_prealert' => cdp_sanitize($data['description']),
            'estimated_date'       => date('Y-m-d', strtotime($data['date'])),
            'prealert_date'        => date('Y-m-d H:i:s'),
            'file_invoice'         => $invoiceFile,
        ];

        $inserted = cdp_insertPreAlert($prealertData);
        if (!$inserted) {
            ApiResponse::serverError('Failed to create pre-alert.');
        }

        // Fetch the new ID (cdp_insertPreAlert uses its own Conexion, so we query separately)
        $db->cdp_query("SELECT id FROM cdb_pre_alert WHERE tracking = :t AND customer_id = :cid ORDER BY id DESC LIMIT 1");
        $db->bind(':t',   cdp_sanitize($data['tracking']));
        $db->bind(':cid', (int)$authUser->id);
        $db->cdp_execute();
        $newRow = $db->cdp_registro();
        $newId  = $newRow ? (int)$newRow->id : 0;

        // Notification
        $db->cdp_query("
            INSERT INTO cdb_notifications (user_id, notification_description, shipping_type, notification_date)
            VALUES (:uid, :desc, 3, :nd)
        ");
        $db->bind(':uid',  (int)$authUser->id);
        $db->bind(':desc', 'New pre-alert created by ' . ($authUser->fname ?? '') . ' ' . ($authUser->lname ?? ''));
        $db->bind(':nd',   date('Y-m-d H:i:s'));
        $db->cdp_execute();
        $notifId = (int)$db->dbh->lastInsertId();

        // Notify admins
        $admins = cdp_getUsersAdminEmployees();
        foreach ($admins as $admin) {
            cdp_insertNotificationsUsers($notifId, $admin->id);
        }
        cdp_insertNotificationsUsers($notifId, (int)$authUser->id);

        // Fetch new record
        $db->cdp_query("SELECT p.*, cc.name_com AS courier_name FROM cdb_pre_alert p LEFT JOIN cdb_courier_com cc ON p.courier_com = cc.id WHERE p.id = :id LIMIT 1");
        $db->bind(':id', (int)$newId);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        ApiResponse::created(self::formatRow($row), 'Pre-alert created successfully.');
    }

    // ── GET /api/v1/pre-alerts/{id} ───────────────────────────────────────────

    public function show(int $id): void
    {
        $authUser = ApiAuth::requirePermission('prealert_list');

        $db = new Conexion();
        $db->cdp_query("
            SELECT p.*, cc.name_com AS courier_name,
                   CONCAT(u.fname,' ',u.lname) AS customer_name
            FROM cdb_pre_alert p
            LEFT JOIN cdb_courier_com cc ON p.courier_com = cc.id
            LEFT JOIN cdb_users       u  ON p.customer_id  = u.id
            WHERE p.id = :id LIMIT 1
        ");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Pre-alert #{$id} not found.");
        }
        if ((int)$authUser->userlevel === 1 && (int)$row->customer_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        ApiResponse::success(self::formatRow($row));
    }

    // ── DELETE /api/v1/pre-alerts/{id} ────────────────────────────────────────

    public function delete(int $id): void
    {
        $authUser = ApiAuth::requirePermission('prealert_list');

        $db = new Conexion();
        $db->cdp_query('SELECT id, customer_id FROM cdb_pre_alert WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Pre-alert #{$id} not found.");
        }
        if ((int)$authUser->userlevel === 1 && (int)$row->customer_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        $db->cdp_query('DELETE FROM cdb_pre_alert WHERE id = :id');
        $db->bind(':id', $id);
        $db->cdp_execute();

        ApiResponse::success(['id' => $id, 'deleted' => true]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    public static function formatRow($row): array
    {
        if (!$row) return [];
        return [
            'id'             => (int)$row->id,
            'tracking'       => $row->tracking,
            'provider'       => $row->provider_shop ?? null,
            'courier_id'     => (int)($row->courier_com ?? 0),
            'courier_name'   => $row->courier_name ?? null,
            'customer_id'    => (int)($row->customer_id ?? 0),
            'customer_name'  => $row->customer_name ?? null,
            'price'          => (float)($row->purchase_price ?? 0),
            'description'    => $row->package_description ?? null,
            'estimated_date' => $row->estimated_date ?? null,
            'prealert_date'  => $row->prealert_date ?? null,
            'invoice_url'    => $row->url_invoice ?? null,
        ];
    }
}
