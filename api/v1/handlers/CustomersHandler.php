<?php
/**
 * CustomersHandler – CRUD for customer users (cdb_users, userlevel=1).
 *
 * GET  /api/v1/customers              list
 * POST /api/v1/customers              create
 * GET  /api/v1/customers/{id}         show
 * PUT  /api/v1/customers/{id}         update
 * GET  /api/v1/customers/{id}/addresses  list addresses
 */
class CustomersHandler
{
    // ── GET /api/v1/customers ─────────────────────────────────────────────────

    public function index(): void
    {
        $authUser = ApiAuth::requirePermission('view_client_list');

        $db      = new Conexion();
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 15)));
        $offset  = ($page - 1) * $perPage;
        $search  = cdp_sanitize($_GET['search'] ?? '');
        $active  = isset($_GET['active']) ? (int)$_GET['active'] : -1;
        $sortDir = strtoupper($_GET['direction'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $params = [];
        $where  = "WHERE userlevel = '1'";

        // Agency scoping (integer ID – safe to inline after cast)
        if ((int)$authUser->userlevel === 6) {
            $ctx = cdp_getAgencyContext();
            if ($ctx['agency_id']) {
                $where .= ' AND agency_id = ' . (int)$ctx['agency_id'];
            }
        }

        // String search – parameterized
        if ($search !== '') {
            $where .= ' AND (fname LIKE :search OR lname LIKE :search OR email LIKE :search OR locker LIKE :search OR phone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        // active filter – integer safe to inline
        if ($active !== -1) {
            $where .= ' AND active = ' . $active;
        }

        $db->cdp_query("SELECT COUNT(*) AS total FROM cdb_users {$where}");
        foreach ($params as $key => $val) { $db->bind($key, $val); }
        $db->cdp_execute();
        $total = (int)($db->cdp_registro()->total ?? 0);

        $db->cdp_query("SELECT * FROM cdb_users {$where} ORDER BY id {$sortDir} LIMIT {$offset}, {$perPage}");
        foreach ($params as $key => $val) { $db->bind($key, $val); }
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatRow'], $rows ?: []);
        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    // ── POST /api/v1/customers ────────────────────────────────────────────────

    public function create(): void
    {
        ApiAuth::requirePermission('add_client');
        $data = ApiResponse::getRequestData();

        $errors = ApiValidator::validate($data, [
            'email'     => 'required|email|max:100',
            'first_name'=> 'required|string|min:2|max:80',
            'last_name' => 'required|string|min:2|max:80',
            'username'  => 'required|string|min:4|max:50',
            'password'  => 'required|string|min:8|max:100',
            'phone'     => 'nullable|string|max:30',
            'gender'    => 'nullable|in:M,F,O',
            'active'    => 'nullable|integer|in:0,1',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user = new User();
        if ($user->cdp_emailExists(cdp_sanitize($data['email']))) {
            ApiResponse::conflict('An account with this email already exists.');
        }
        if ($user->cdp_usernameExists(cdp_sanitize($data['username']))) {
            ApiResponse::conflict('Username is already taken.');
        }

        $core    = new Core();
        $locker  = $core->cdp_order_track();  // re-used as unique locker seed
        $prefix  = $core->prefix_locker ?? 'L';
        $lockerCode = $prefix . str_pad((string)rand(100, 9999), 4, '0', STR_PAD_LEFT);

        $customerData = [
            'username'        => cdp_sanitize($data['username']),
            'password'        => password_hash($data['password'], PASSWORD_DEFAULT),
            'locker'          => $lockerCode,
            'userlevel'       => 1,
            'email'           => cdp_sanitize($data['email']),
            'fname'           => cdp_sanitize($data['first_name']),
            'lname'           => cdp_sanitize($data['last_name']),
            'phone'           => cdp_sanitize($data['phone'] ?? ''),
            'gender'          => cdp_sanitize($data['gender'] ?? 'O'),
            'notes'           => cdp_sanitize($data['notes'] ?? ''),
            'newsletter'      => 0,
            'active'          => isset($data['active']) ? (int)$data['active'] : 1,
            'document_type'   => cdp_sanitize($data['document_type'] ?? ''),
            'document_number' => cdp_sanitize($data['document_number'] ?? ''),
            'created'         => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['agency_id'])) {
            $customerData['agency_id'] = (int)$data['agency_id'];
        }

        $newId = cdp_insertCustomer($customerData);
        if (!$newId) {
            ApiResponse::serverError('Failed to create customer.');
        }

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_users WHERE id = :id LIMIT 1');
        $db->bind(':id', (int)$newId);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        ApiResponse::created(self::formatRow($row), 'Customer created successfully.');
    }

    // ── GET /api/v1/customers/{id} ────────────────────────────────────────────

    public function show(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_client_list');

        // Customers may only view their own profile
        if ((int)$authUser->userlevel === 1 && (int)$authUser->id !== $id) {
            ApiResponse::forbidden();
        }

        $db = new Conexion();
        $db->cdp_query("SELECT * FROM cdb_users WHERE id = :id AND userlevel = '1' LIMIT 1");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) {
            ApiResponse::notFound("Customer #{$id} not found.");
        }

        ApiResponse::success(self::formatRow($row));
    }

    // ── PUT /api/v1/customers/{id} ────────────────────────────────────────────

    public function update(int $id): void
    {
        $authUser = ApiAuth::requirePermission(['edit_client', 'view_client_list']);
        $data     = ApiResponse::getRequestData();

        // Customers may only edit themselves
        if ((int)$authUser->userlevel === 1 && (int)$authUser->id !== $id) {
            ApiResponse::forbidden();
        }

        $db = new Conexion();
        $db->cdp_query("SELECT * FROM cdb_users WHERE id = :id AND userlevel = '1' LIMIT 1");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $existing = $db->cdp_registro();
        if (!$existing) {
            ApiResponse::notFound("Customer #{$id} not found.");
        }

        $errors = ApiValidator::validate($data, [
            'email'      => 'nullable|email|max:100',
            'first_name' => 'nullable|string|min:2|max:80',
            'last_name'  => 'nullable|string|min:2|max:80',
            'phone'      => 'nullable|string|max:30',
            'gender'     => 'nullable|in:M,F,O',
            'active'     => 'nullable|integer|in:0,1',
        ]);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user = new User();
        $newEmail = cdp_sanitize($data['email'] ?? $existing->email);
        if ($newEmail !== $existing->email && $user->cdp_emailExists($newEmail, $id)) {
            ApiResponse::conflict('Email already in use by another account.');
        }

        $newPw = !empty($data['password'])
            ? password_hash($data['password'], PASSWORD_DEFAULT)
            : $existing->password;

        $updateData = [
            'id'              => $id,
            'email'           => $newEmail,
            'fname'           => cdp_sanitize($data['first_name'] ?? $existing->fname),
            'lname'           => cdp_sanitize($data['last_name']  ?? $existing->lname),
            'phone'           => cdp_sanitize($data['phone']      ?? $existing->phone),
            'gender'          => cdp_sanitize($data['gender']     ?? $existing->gender),
            'notes'           => cdp_sanitize($data['notes']      ?? $existing->notes),
            'newsletter'      => (int)($data['newsletter']        ?? $existing->newsletter),
            'active'          => isset($data['active'])  ? (int)$data['active']  : (int)$existing->active,
            'document_type'   => cdp_sanitize($data['document_type']   ?? $existing->document_type),
            'document_number' => cdp_sanitize($data['document_number'] ?? $existing->document_number),
            'password'        => $newPw,
        ];

        cdp_updateCustomers($updateData);

        $db->cdp_query('SELECT * FROM cdb_users WHERE id = :id LIMIT 1');
        $db->bind(':id', $id);
        $db->cdp_execute();
        ApiResponse::success(self::formatRow($db->cdp_registro()));
    }

    // ── GET /api/v1/customers/{id}/addresses ─────────────────────────────────

    public function addresses(int $id): void
    {
        $authUser = ApiAuth::requirePermission('view_client_list');

        if ((int)$authUser->userlevel === 1 && (int)$authUser->id !== $id) {
            ApiResponse::forbidden();
        }

        $db = new Conexion();
        $db->cdp_query("SELECT * FROM cdb_users WHERE id = :id AND userlevel = '1' LIMIT 1");
        $db->bind(':id', $id);
        $db->cdp_execute();
        if (!$db->cdp_registro()) {
            ApiResponse::notFound("Customer #{$id} not found.");
        }

        $db->cdp_query("
            SELECT sa.*, co.name AS country_name, st.name AS state_name, ci.name AS city_name
            FROM cdb_senders_addresses sa
            LEFT JOIN cdb_countries co ON sa.country = co.id
            LEFT JOIN cdb_states    st ON sa.state   = st.id
            LEFT JOIN cdb_cities    ci ON sa.city    = ci.id
            WHERE sa.user_id = :id
            ORDER BY sa.id_addresses ASC
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
            'id'              => (int)$row->id,
            'username'        => $row->username,
            'email'           => $row->email,
            'first_name'      => $row->fname,
            'last_name'       => $row->lname,
            'phone'           => $row->phone ?? null,
            'gender'          => $row->gender ?? null,
            'notes'           => $row->notes ?? null,
            'newsletter'      => (bool)(int)($row->newsletter ?? 0),
            'active'          => (bool)(int)($row->active ?? 0),
            'locker'          => $row->locker ?? null,
            'document_type'   => $row->document_type ?? null,
            'document_number' => $row->document_number ?? null,
            'agency_id'       => isset($row->agency_id) ? (int)$row->agency_id : null,
            'name_off'        => $row->name_off ?? null,
            'created'         => $row->created ?? null,
        ];
    }

    public static function formatAddress($row): array
    {
        if (!$row) return [];
        return [
            'id'           => (int)$row->id_addresses,
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
