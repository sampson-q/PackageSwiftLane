<?php
require_once __DIR__ . '/../helpers/Response.php';

class ShipmentsController {
    protected $db;
    protected $isPickup;

    public function __construct(bool $isPickup = false) {
        $this->db = $GLOBALS['db'] ?? new Conexion();
        $this->isPickup = $isPickup;
    }

    protected function applyAccessFilter(array $payload, array &$params): string {
        $userlevel = (int)($payload['userlevel'] ?? 0);
        $uid = (int)($payload['uid'] ?? 0);
        if ($userlevel === 1) {
            $params[':uid'] = $uid;
            return ' AND sender_id = :uid';
        }
        if ($userlevel === 3) {
            $params[':uid'] = $uid;
            return ' AND driver_id = :uid';
        }
        return '';
    }

    public function index() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        [$page, $perPage, $offset] = api_pagination_params(25, 100);
        $search = trim($_GET['search'] ?? '');
        $status = (int)($_GET['status_courier'] ?? 0);

        $params = [':pickup' => $this->isPickup ? 1 : 0];
        $where = 'WHERE is_pickup = :pickup';
        $where .= $this->applyAccessFilter($payload, $params);

        if ($search !== '') {
            $where .= ' AND CONCAT(order_prefix, order_no) LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }
        if ($status > 0) {
            $where .= ' AND status_courier = :status';
            $params[':status'] = $status;
        }

        $this->db->cdp_query("SELECT COUNT(*) as total FROM cdb_add_order {$where}");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $sql = "SELECT order_id, order_prefix, order_no, order_date, sender_id, receiver_id, status_courier, status_invoice, total_order, order_pay_mode, order_courier, order_service_options, driver_id, is_pickup FROM cdb_add_order {$where} ORDER BY order_id DESC LIMIT {$offset}, {$perPage}";
        $this->db->cdp_query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->order_id,
                'tracking' => $row->order_prefix . $row->order_no,
                'order_date' => $row->order_date,
                'sender_id' => (int)$row->sender_id,
                'receiver_id' => (int)$row->receiver_id,
                'status_courier' => (int)$row->status_courier,
                'status_invoice' => (int)$row->status_invoice,
                'total' => $row->total_order,
                'pay_mode' => (int)$row->order_pay_mode,
                'courier' => (int)$row->order_courier,
                'service' => (int)$row->order_service_options,
                'driver_id' => (int)$row->driver_id,
                'is_pickup' => (int)$row->is_pickup,
            ];
        }

        send_success($items, $this->isPickup ? 'Pickups fetched' : 'Shipments fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function show(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $params = [':id' => $id, ':pickup' => $this->isPickup ? 1 : 0];
        $where = 'WHERE order_id = :id AND is_pickup = :pickup';
        $where .= $this->applyAccessFilter($payload, $params);

        $this->db->cdp_query("SELECT * FROM cdb_add_order {$where} LIMIT 1");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $order = $this->db->cdp_registro();
        if (!$order) send_error('Shipment not found', 404);

        $orderTrack = $order->order_prefix . $order->order_no;

        $this->db->cdp_query('SELECT * FROM cdb_add_order_item WHERE order_id = :id');
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        $items = $this->db->cdp_registros() ?: [];

        $this->db->cdp_query('SELECT * FROM cdb_address_shipments WHERE order_track = :track LIMIT 1');
        $this->db->bind(':track', $orderTrack);
        $this->db->cdp_execute();
        $address = $this->db->cdp_registro();

        $data = [
            'order' => $order,
            'items' => $items,
            'address' => $address,
        ];

        send_success($data, $this->isPickup ? 'Pickup fetched' : 'Shipment fetched', 200);
    }

    public function tracking(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $params = [':id' => $id, ':pickup' => $this->isPickup ? 1 : 0];
        $where = 'WHERE order_id = :id AND is_pickup = :pickup';
        $where .= $this->applyAccessFilter($payload, $params);

        $this->db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order {$where} LIMIT 1");
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        $order = $this->db->cdp_registro();
        if (!$order) send_error('Shipment not found', 404);

        $track = $order->order_prefix . $order->order_no;
        $this->db->cdp_query("SELECT a.order_track, a.comments, a.t_date, a.status_courier, b.mod_style, b.color FROM cdb_courier_track as a LEFT JOIN cdb_styles as b ON a.status_courier = b.id WHERE a.order_track = :track ORDER BY a.t_date DESC");
        $this->db->bind(':track', $track);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        send_success($rows, 'Tracking history fetched', 200);
    }
}

