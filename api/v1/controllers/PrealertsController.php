<?php
require_once __DIR__ . '/../helpers/Response.php';

class PrealertsController {
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
            $where = 'WHERE customer_id = :uid';
            $params[':uid'] = $uid;
        }

        $this->db->cdp_query("SELECT COUNT(*) as total FROM cdb_pre_alert {$where}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $this->db->cdp_query("SELECT pre_alert_id, tracking, provider_shop, courier_com, customer_id, purchase_price, package_description, estimated_date, prealert_date, url_invoice FROM cdb_pre_alert {$where} ORDER BY pre_alert_id DESC LIMIT {$offset}, {$perPage}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        send_success($rows, 'Pre-alerts fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function show(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query('SELECT pre_alert_id, tracking, provider_shop, courier_com, customer_id, purchase_price, package_description, estimated_date, prealert_date, url_invoice FROM cdb_pre_alert WHERE pre_alert_id = :id LIMIT 1');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $row = $this->db->cdp_registro();
        if (!$row) send_error('Pre-alert not found', 404);
        if (!$this->isAdmin($payload) && (int)$row->customer_id !== $uid) {
            send_error('Forbidden', 403);
        }

        send_success($row, 'Pre-alert fetched', 200);
    }

    public function create() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $data = api_get_request_data();
        $required = ['tracking', 'provider_shop', 'courier_com', 'purchase_price', 'package_description', 'estimated_date'];
        $errors = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = 'Required';
            }
        }
        if ($errors) send_error('validation_failed', 422, $errors);

        if (!function_exists('cdp_insertPreAlert')) {
            send_error('Pre-alert creation not available', 500);
        }

        $payloadData = [
            'tracking_prealert' => $data['tracking'],
            'provider_prealert' => $data['provider_shop'],
            'courier_prealert' => $data['courier_com'],
            'customer_id' => $uid,
            'price_prealert' => $data['purchase_price'],
            'description_prealert' => $data['package_description'],
            'estimated_date' => $data['estimated_date'],
            'prealert_date' => date('Y-m-d'),
            'file_invoice' => $data['url_invoice'] ?? null,
        ];

        $ok = cdp_insertPreAlert($payloadData);
        if (!$ok) send_error('Failed to create pre-alert', 500);

        send_success(null, 'Pre-alert created', 201);
    }
}

