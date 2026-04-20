<?php
require_once __DIR__ . '/../helpers/Response.php';

class NotificationsController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    public function index() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        [$page, $perPage, $offset] = api_pagination_params(20, 100);

        $baseSql = "FROM cdb_notifications_users as a INNER JOIN cdb_notifications as b ON a.notification_id = b.notification_id WHERE a.user_id = :uid";

        $this->db->cdp_query("SELECT COUNT(*) as total {$baseSql}");
        $this->db->bind(':uid', $uid);
        $this->db->cdp_execute();
        $total = (int)($this->db->cdp_registro()->total ?? 0);

        $sql = "SELECT b.user_id, b.shipping_type, a.id_notifi_user, b.notification_description, b.notification_date, b.order_id, a.notification_status, a.notification_read, b.notification_id {$baseSql} ORDER BY b.notification_id DESC LIMIT {$offset}, {$perPage}";
        $this->db->cdp_query($sql);
        $this->db->bind(':uid', $uid);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->id_notifi_user,
                'notification_id' => (int)$row->notification_id,
                'sender_id' => (int)$row->user_id,
                'order_id' => (int)$row->order_id,
                'shipping_type' => (int)$row->shipping_type,
                'description' => $row->notification_description,
                'date' => $row->notification_date,
                'status' => (int)$row->notification_status,
                'read' => (int)$row->notification_read,
            ];
        }

        send_success($items, 'Notifications fetched', 200, api_pagination_meta($page, $perPage, $total));
    }

    public function markRead(int $id) {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);
        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query('UPDATE cdb_notifications_users SET notification_read = 1 WHERE id_notifi_user = :id AND user_id = :uid');
        $this->db->bind(':id', $id);
        $this->db->bind(':uid', $uid);
        $this->db->cdp_execute();

        send_success(null, 'Notification marked as read', 200);
    }
}

