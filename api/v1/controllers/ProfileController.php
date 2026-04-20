<?php
require_once __DIR__ . '/../helpers/Response.php';

class ProfileController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    public function show() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query("SELECT id, username, email, fname, lname, phone, userlevel, active, lastlogin, document_type, document_number FROM cdb_users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $uid);
        $this->db->cdp_execute();
        $user = $this->db->cdp_registro();
        if (!$user) send_error('User not found', 404);

        $this->db->cdp_query("SELECT id_addresses, country, state, city, zip_code, address FROM cdb_senders_addresses WHERE user_id = :id ORDER BY id_addresses DESC");
        $this->db->bind(':id', $uid);
        $this->db->cdp_execute();
        $addresses = $this->db->cdp_registros() ?: [];

        $response = [
            'id' => (int)$user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')),
            'fname' => $user->fname,
            'lname' => $user->lname,
            'phone' => $user->phone,
            'userlevel' => (int)$user->userlevel,
            'active' => (int)$user->active,
            'lastlogin' => $user->lastlogin,
            'document_type' => $user->document_type,
            'document_number' => $user->document_number,
            'addresses' => $addresses,
        ];

        send_success(['user' => $response], 'Profile fetched', 200);
    }

    public function update() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $data = api_get_request_data();

        $fields = [];
        $params = [':id' => $uid];
        $allowed = [
            'email' => 'email',
            'fname' => 'fname',
            'lname' => 'lname',
            'phone' => 'phone',
            'document_type' => 'document_type',
            'document_number' => 'document_number',
        ];

        foreach ($allowed as $key => $column) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$column = :$key";
                $params[":$key"] = $data[$key];
            }
        }

        if ($fields) {
            $this->db->cdp_query('UPDATE cdb_users SET ' . implode(', ', $fields) . ' WHERE id = :id');
            foreach ($params as $param => $value) {
                $this->db->bind($param, $value);
            }
            $this->db->cdp_execute();
        }

        if (!empty($data['address'])) {
            $addr = [
                'user_id' => $uid,
                'address' => $data['address'],
                'country' => $data['country'] ?? null,
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'postal' => $data['postal'] ?? null,
            ];
            if (function_exists('cdp_insertAddressCustomer')) {
                cdp_insertAddressCustomer($addr);
            }
        }

        send_success(null, 'Profile updated', 200);
    }
}

