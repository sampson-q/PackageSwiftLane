<?php
require_once __DIR__ . '/../helpers/Response.php';

class TrackingController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    public function history(string $tracking) {
        $tracking = trim($tracking);
        if ($tracking === '') send_error('tracking is required', 400);

        $this->db->cdp_query('SELECT a.order_track, a.comments, a.t_date, a.status_courier, a.office_id, a.user_id, b.mod_style, b.color FROM cdb_courier_track as a LEFT JOIN cdb_styles as b ON a.status_courier = b.id WHERE a.order_track = :track ORDER BY a.t_date DESC');
        $this->db->bind(':track', $tracking);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros() ?: [];

        if (!$rows) {
            send_error('Tracking not found', 404);
        }

        send_success($rows, 'Tracking history fetched', 200);
    }
}

