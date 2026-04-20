<?php
require_once __DIR__ . '/../helpers/Response.php';

class ConsolidationsController {
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

        $params = [];
        if ($this->isAdmin($payload)) {
            $countSql = 'FROM cdb_consolidate';
            $selectSql = 'FROM cdb_consolidate c';
        } else {
            $countSql = 'FROM cdb_consolidate c INNER JOIN cdb_consolidate_detail d ON d.consolidate_id = c.consolidate_id INNER JOIN cdb_add_order o ON d.order_id = o.order_id WHERE o.sender_id = :uid';
            $selectSql = $countSql;
            $params[':uid'] = $uid;
        }

        $this->db->cdp_query("SELECT COUNT(DISTINCT consolidate_id) as total {$countSql}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $this->db->cdp_query("SELECT DISTINCT c.consolidate_id, c.c_prefix, c.c_no, c.status_courier, c.consolidate_date, c.sender_id, c.receiver_id {$selectSql} ORDER BY c.consolidate_id DESC LIMIT {$offset}, {$perPage}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->consolidate_id,
                'tracking' => $row->c_prefix . $row->c_no,
                'status_courier' => (int)$row->status_courier,
                'consolidate_date' => $row->consolidate_date,
                'sender_id' => (int)$row->sender_id,
                'receiver_id' => (int)$row->receiver_id,
            ];
        }

        send_success($items, 'Consolidations fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function show(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query('SELECT * FROM cdb_consolidate WHERE consolidate_id = :id LIMIT 1');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $row = $this->db->cdp_registro();
        if (!$row) send_error('Consolidation not found', 404);

        if (!$this->isAdmin($payload)) {
            $this->db->cdp_query('SELECT COUNT(*) as total FROM cdb_consolidate_detail d INNER JOIN cdb_add_order o ON d.order_id = o.order_id WHERE d.consolidate_id = :id AND o.sender_id = :uid');
            $this->db->bind(':id', $id);
            $this->db->bind(':uid', $uid);
            $this->db->cdp_execute();
            $count = (int)($this->db->cdp_registro()->total ?? 0);
            if ($count === 0) send_error('Forbidden', 403);
        }

        send_success($row, 'Consolidation fetched', 200);
    }

    public function details(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        if (!$this->isAdmin($payload)) {
            $this->db->cdp_query('SELECT COUNT(*) as total FROM cdb_consolidate_detail d INNER JOIN cdb_add_order o ON d.order_id = o.order_id WHERE d.consolidate_id = :id AND o.sender_id = :uid');
            $this->db->bind(':id', $id);
            $this->db->bind(':uid', $uid);
            $this->db->cdp_execute();
            $count = (int)($this->db->cdp_registro()->total ?? 0);
            if ($count === 0) send_error('Forbidden', 403);
        }

        $this->db->cdp_query('SELECT * FROM cdb_consolidate_detail WHERE consolidate_id = :id');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        send_success($rows, 'Consolidation details fetched', 200);
    }

    public function packagesIndex() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        [$page, $perPage, $offset] = api_pagination_params(25, 100);

        $this->db->cdp_query('SELECT COUNT(*) as total FROM cdb_consolidate_packages');
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $this->db->cdp_query("SELECT consolidate_id, c_prefix, c_no, status_courier, consolidate_date, sender_id, receiver_id FROM cdb_consolidate_packages ORDER BY consolidate_id DESC LIMIT {$offset}, {$perPage}");
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->consolidate_id,
                'tracking' => $row->c_prefix . $row->c_no,
                'status_courier' => (int)$row->status_courier,
                'consolidate_date' => $row->consolidate_date,
                'sender_id' => (int)$row->sender_id,
                'receiver_id' => (int)$row->receiver_id,
            ];
        }

        send_success($items, 'Consolidated packages fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function packagesShow(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query('SELECT * FROM cdb_consolidate_packages WHERE consolidate_id = :id LIMIT 1');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $row = $this->db->cdp_registro();
        if (!$row) send_error('Consolidated package not found', 404);

        send_success($row, 'Consolidated package fetched', 200);
    }
}
