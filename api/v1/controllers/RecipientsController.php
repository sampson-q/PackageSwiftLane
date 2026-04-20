<?php
require_once __DIR__ . '/../helpers/Response.php';

class RecipientsController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    protected function isAdmin(array $payload): bool {
        $level = (int)($payload['userlevel'] ?? 0);
        return $level === 9 || $level === 2;
    }

    public function index() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        [$page, $perPage, $offset] = api_pagination_params(25, 100);

        $where = '';
        $params = [];
        if (!$this->isAdmin($payload)) {
            $where = 'WHERE sender_id = :uid';
            $params[':uid'] = $uid;
        }

        $this->db->cdp_query("SELECT COUNT(*) as total FROM cdb_recipients {$where}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $this->db->cdp_query("SELECT id, email, fname, lname, phone, sender_id FROM cdb_recipients {$where} ORDER BY id DESC LIMIT {$offset}, {$perPage}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->id,
                'email' => $row->email,
                'fname' => $row->fname,
                'lname' => $row->lname,
                'phone' => $row->phone,
                'sender_id' => (int)$row->sender_id,
            ];
        }

        send_success($items, 'Recipients fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function show(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query('SELECT id, email, fname, lname, phone, sender_id FROM cdb_recipients WHERE id = :id LIMIT 1');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $recipient = $this->db->cdp_registro();
        if (!$recipient) send_error('Recipient not found', 404);

        if (!$this->isAdmin($payload) && (int)$recipient->sender_id !== $uid) {
            send_error('Forbidden', 403);
        }

        $this->db->cdp_query('SELECT id_addresses, country, state, city, zip_code, address FROM cdb_recipients_addresses WHERE recipient_id = :id ORDER BY id_addresses DESC');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $addresses = $this->db->cdp_registros() ?: [];

        $data = [
            'id' => (int)$recipient->id,
            'email' => $recipient->email,
            'fname' => $recipient->fname,
            'lname' => $recipient->lname,
            'phone' => $recipient->phone,
            'sender_id' => (int)$recipient->sender_id,
            'addresses' => $addresses,
        ];

        send_success($data, 'Recipient fetched', 200);
    }

    public function create() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $data = api_get_request_data();
        $required = ['email', 'fname', 'lname', 'phone'];
        $errors = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = 'Required';
            }
        }
        if ($errors) send_error('validation_failed', 422, $errors);

        $recipientId = null;
        if (function_exists('cdp_insertRecipient')) {
            $recipientId = cdp_insertRecipient([
                'email' => $data['email'],
                'fname' => $data['fname'],
                'lname' => $data['lname'],
                'phone' => $data['phone'],
                'sender_id' => $uid,
            ]);
        }

        if (!$recipientId) {
            send_error('Failed to create recipient', 500);
        }

        if (!empty($data['address'])) {
            if (function_exists('cdp_insertAddressRecipient')) {
                cdp_insertAddressRecipient([
                    'country' => $data['country'] ?? null,
                    'state' => $data['state'] ?? null,
                    'city' => $data['city'] ?? null,
                    'zip_code' => $data['postal'] ?? null,
                    'address' => $data['address'],
                    'recipient_id' => $recipientId,
                ]);
            }
        }

        send_success(['id' => (int)$recipientId], 'Recipient created', 201);
    }
}

