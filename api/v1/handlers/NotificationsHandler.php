<?php
/**
 * NotificationsHandler – in-app notification endpoints.
 *
 * GET   /api/v1/notifications            list (paginated)
 * PATCH /api/v1/notifications/{id}/read  mark one as read
 * PATCH /api/v1/notifications/read-all   mark all as read
 */
class NotificationsHandler
{
    // ── GET /api/v1/notifications ─────────────────────────────────────────────

    public function index(): void
    {
        $authUser = ApiAuth::requireAuth();

        $db      = new Conexion();
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;
        $unread  = isset($_GET['unread']) ? (bool)$_GET['unread'] : false;

        $userId = (int)$authUser->id;
        $extra  = $unread ? " AND nu.read_status = 0" : '';

        $db->cdp_query("SELECT COUNT(*) AS total FROM cdb_notifications n
            INNER JOIN cdb_notifications_users nu ON nu.notification_id = n.id
            WHERE nu.user_id = :uid {$extra}");
        $db->bind(':uid', $userId);
        $db->cdp_execute();
        $total = (int)($db->cdp_registro()->total ?? 0);

        $db->cdp_query("
            SELECT n.id, n.notification_description, n.shipping_type,
                   n.notification_date, nu.read_status
            FROM cdb_notifications n
            INNER JOIN cdb_notifications_users nu ON nu.notification_id = n.id
            WHERE nu.user_id = :uid {$extra}
            ORDER BY n.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        $db->bind(':uid', $userId);
        $rows = $db->cdp_registros();

        $items = array_map([self::class, 'formatRow'], $rows ?: []);
        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    // ── PATCH /api/v1/notifications/{id}/read ─────────────────────────────────

    public function markRead(int $id): void
    {
        $authUser = ApiAuth::requireAuth();
        $userId   = (int)$authUser->id;

        $db = new Conexion();
        $db->cdp_query("UPDATE cdb_notifications_users SET read_status = 1
            WHERE notification_id = :id AND user_id = :uid");
        $db->bind(':id',  $id);
        $db->bind(':uid', $userId);
        $db->cdp_execute();

        if ($db->cdp_rowCount() === 0) {
            ApiResponse::notFound("Notification #{$id} not found.");
        }

        ApiResponse::success(['id' => $id, 'read' => true]);
    }

    // ── PATCH /api/v1/notifications/read-all ─────────────────────────────────

    public function markAllRead(): void
    {
        $authUser = ApiAuth::requireAuth();
        $userId   = (int)$authUser->id;

        $db = new Conexion();
        $db->cdp_query("UPDATE cdb_notifications_users SET read_status = 1 WHERE user_id = :uid");
        $db->bind(':uid', $userId);
        $db->cdp_execute();

        ApiResponse::success(['updated' => $db->cdp_rowCount()]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    public static function formatRow($row): array
    {
        if (!$row) return [];
        return [
            'id'          => (int)$row->id,
            'description' => $row->notification_description,
            'type'        => (int)($row->shipping_type ?? 0),
            'date'        => $row->notification_date ?? null,
            'read'        => (bool)(int)($row->read_status ?? 0),
        ];
    }
}
