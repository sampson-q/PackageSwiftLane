<?php
// controllers/CustomersController.php
require_once __DIR__ . '/../helpers/Response.php';

class CustomersController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    /**
     * GET /customers
     * Protected: requires bearer token
     * Optional query: ?page=1&per_page=25
     */
    public function index() {
        [$page, $per, $offset] = api_pagination_params(25, 100);
        $sql = "SELECT id, username, email, fname, lname, userlevel, active FROM cdb_users ORDER BY id DESC LIMIT :off, :per";
        // Conexion doesn't support named params for LIMIT easily, so bind ints explicitly
        $this->db->cdp_query("SELECT id, username, email, fname, lname, userlevel, active FROM cdb_users ORDER BY id DESC LIMIT {$offset}, {$per}");
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros();

        // Basic pagination total count
        $this->db->cdp_query("SELECT COUNT(*) as total FROM cdb_users");
        $this->db->cdp_execute();
        $countRow = $this->db->cdp_registro();
        $total = (int)($countRow->total ?? 0);

        $users = [];
        foreach ($rows as $r) {
            $users[] = [
                'id' => (int)$r->id,
                'username' => $r->username,
                'email' => $r->email,
                'name' => trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')),
                'userlevel' => (int)$r->userlevel,
                'active' => (int)$r->active,
            ];
        }

        $meta = api_pagination_meta($page, $per, $total);

        send_success($users, 'Customers fetched', 200, $meta);
    }
}
