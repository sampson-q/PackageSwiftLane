<?php
/**
 * RecipientsHandler – CRUD for recipients (cdb_recipients).
 *
 * GET  /api/v1/recipients              list
 * POST /api/v1/recipients              create
 * GET  /api/v1/recipients/{id}         show
 * PUT  /api/v1/recipients/{id}         update
 * GET  /api/v1/recipients/{id}/addresses  list addresses
 */
class RecipientsHandler
{
    // ── GET /api/v1/recipients ────────────────────────────────────────────────

    public function index(): void
    {
        $authUser = ApiAuth::requirePermission('view_recipients');

        $db      = new Conexion();
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 15)));
        $offset  = ($page - 1) * $perPage;
        $search   = cdp_sanitize($_GET['search']    ?? '');
        $senderId = (int)($_GET['sender_id']         ?? 0);
        $sortDir  = strtoupper($_GET['direction'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $params = [];
        $where  = 'WHERE 1=1';

        // Agency / role-based scoping (integer IDs – safe to inline)
        $ulevel = (int)$authUser->userlevel;
        if ($ulevel === 6) {
            $ctx = cdp_getAgencyContext();
            if ($ctx['agency_id']) {
                $where .= ' AND r.agency_id = ' . (int)$ctx['agency_id'];
            }
        } elseif ($ulevel === 1) {
            // Customers only see their own recipients
            $where .= ' AND r.sender_id = ' . (int)$authUser->id;
        }

        // Optional sender_id filter (admin/staff only; customers are already scoped above)
        if ($senderId > 0 && $ulevel !== 1) {
            $where .= ' AND r.sender_id = ' . $senderId;
        }

        // String search – parameterized
        if ($search !== '') {
            $where .= ' AND (r.fname LIKE :search OR r.lname LIKE :search OR r.email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $db->cdp_query("SELECT COUNT(*) AS total FROM cdb_recipients r {$where}");
        foreach ($params as $key => $val) { $db->bind($key, $val); }
        $db->cdp_execute();
        $total = (int)($db->cdp_registro()->total ?? 0);

        $db->cdp_query("SELECT r.* FROM cdb_recipients r {$where} ORDER BY r.id {$sortDir} LIMIT {$offset}, {$perPage}");
        foreach ($params as $key => $val) { $db->bind($key, $val); }
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatRow'], $rows ?: []);
        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    // ── POST /api/v1/recipients ───────────────────────────────────────────────

    public function create(): void
    {
        $authUser = ApiAuth::requirePermission('view_recipients');
        $data     = ApiResponse::getRequestData();

        $errors = ApiValidator::validate($data, [
            'first_name' => 'required|string|min:2|max:80',
            'last_name'  => 'required|string|min:2|max:80',
            'email'      => 'required|email|max:100',
            'phone'      => 'nullable|string|max:30',
            'sender_id'  => 'required|integer',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user = new User();
        if ($user->cdp_emailExistsRecipients(cdp_sanitize($data['email']))) {
            ApiResponse::conflict('A recipient with this email already exists.');
        }

        $ctx = cdp_getAgencyContext();

        $recipientData = [
            'fname'     => cdp_sanitize($data['first_name']),
            'lname'     => cdp_sanitize($data['last_name']),
            'email'     => cdp_sanitize($data['email']),
            'phone'     => cdp_sanitize($data['phone'] ?? ''),
            'sender_id' => (int)$data['sender_id'],
        ];
        if ($ctx['agency_id']) {
            $recipientData['agency_id'] = (int)$ctx['agency_id'];
        }

        $newId = cdp_insertRecipient($recipientData);
        if (!$newId) {
            ApiResponse::serverError('Failed to create recipient.');
        }

        // Optional address
        if (!empty($data['address'])) {
            $addrData = [
                'recipient_id' => (int)$newId,
                'address'      => cdp_sanitize($data['address']),
                'country'      => (int)($data['country_id'] ?? 0),
                'state'        => (int)($data['state_id']   ?? 0),
                'city'         => (int)($data['city_id']    ?? 0),
                'postal'       => cdp_sanitize($data['zip_code'] ?? ''),
            ];
            cdp_insertAddressRecipient($addrData);
        }

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_recipients WHERE id = :id LIMIT 1');
        $db->bind(':id', (int)$newId);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        ApiResponse::created(self::formatRow($row), 'Recipient created successfully.');
    }

    // ── GET /api/v1/recipients/{id} ───────────────────────────────────────────

    public function show(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_recipients');

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_recipients WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Recipient #{$id} not found.");
        }

        // Customers only see their own recipients
        if ((int)$authUser->userlevel === 1 && (int)$row->sender_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        ApiResponse::success(self::formatRow($row));
    }

    // ── PUT /api/v1/recipients/{id} ───────────────────────────────────────────

    public function update(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_recipients');
        $data     = ApiResponse::getRequestData();

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_recipients WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $existing = $db->cdp_registro();

        if (!$existing) {
            ApiResponse::notFound("Recipient #{$id} not found.");
        }
        if ((int)$authUser->userlevel === 1 && (int)$existing->sender_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        $errors = ApiValidator::validate($data, [
            'email'      => 'nullable|email|max:100',
            'first_name' => 'nullable|string|min:2|max:80',
            'last_name'  => 'nullable|string|min:2|max:80',
            'phone'      => 'nullable|string|max:30',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $updateData = [
            'id_recipient' => $id,
            'email'        => cdp_sanitize($data['email']       ?? $existing->email),
            'fname'        => cdp_sanitize($data['first_name']  ?? $existing->fname),
            'lname'        => cdp_sanitize($data['last_name']   ?? $existing->lname),
            'phone'        => cdp_sanitize($data['phone']       ?? $existing->phone),
        ];

        cdp_updateRecipient($updateData);

        $db->cdp_query('SELECT * FROM cdb_recipients WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        ApiResponse::success(self::formatRow($db->cdp_registro()));
    }

    // ── GET /api/v1/recipients/{id}/addresses ─────────────────────────────────

    public function addresses(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_recipients');

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_recipients WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Recipient #{$id} not found.");
        }
        if ((int)$authUser->userlevel === 1 && (int)$row->sender_id !== (int)$authUser->id) {
            ApiResponse::forbidden();
        }

        $db->cdp_query("
            SELECT ra.*, co.name AS country_name, st.name AS state_name, ci.name AS city_name
            FROM cdb_recipients_addresses ra
            LEFT JOIN cdb_countries co ON ra.country = co.id
            LEFT JOIN cdb_states    st ON ra.state   = st.id
            LEFT JOIN cdb_cities    ci ON ra.city    = ci.id
            WHERE ra.recipient_id = :id
            ORDER BY ra.id ASC
        ");
        $db->bind(':id', $id);
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatAddress'], $rows ?: []);
        ApiResponse::success($items);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    public static function formatRow($row): array
    {
        if (!$row) return [];
        return [
            'id'        => (int)$row->id,
            'first_name'=> $row->fname,
            'last_name' => $row->lname,
            'email'     => $row->email,
            'phone'     => $row->phone ?? null,
            'sender_id' => (int)($row->sender_id ?? 0),
            'agency_id' => isset($row->agency_id) ? (int)$row->agency_id : null,
        ];
    }

    public static function formatAddress($row): array
    {
        if (!$row) return [];
        return [
            'id'           => (int)$row->id,
            'address'      => $row->address,
            'zip_code'     => $row->zip_code ?? null,
            'country_id'   => (int)($row->country ?? 0),
            'country_name' => $row->country_name ?? null,
            'state_id'     => (int)($row->state ?? 0),
            'state_name'   => $row->state_name ?? null,
            'city_id'      => (int)($row->city ?? 0),
            'city_name'    => $row->city_name ?? null,
        ];
    }
}
