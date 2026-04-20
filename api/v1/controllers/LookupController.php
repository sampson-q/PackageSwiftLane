<?php
require_once __DIR__ . '/../helpers/Response.php';

class LookupController {
    protected $db;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
    }

    protected function fetchAll(string $sql, array $params = []): array {
        $this->db->cdp_query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->cdp_execute();
        return $this->db->cdp_registros() ?: [];
    }

    public function countries() {
        $rows = $this->fetchAll("SELECT id, name, iso2, iso3, phonecode, is_active FROM cdb_countries WHERE is_active=1 ORDER BY name");
        send_success($rows, 'Countries fetched', 200);
    }

    public function states() {
        $countryId = (int)($_GET['country_id'] ?? 0);
        if ($countryId <= 0) {
            send_error('country_id is required', 400);
        }
        $rows = $this->fetchAll("SELECT id, name, country_id FROM cdb_states WHERE country_id = :cid ORDER BY name", [':cid' => $countryId]);
        send_success($rows, 'States fetched', 200);
    }

    public function cities() {
        $stateId = (int)($_GET['state_id'] ?? 0);
        if ($stateId <= 0) {
            send_error('state_id is required', 400);
        }
        $rows = $this->fetchAll("SELECT id, name, state_id FROM cdb_cities WHERE state_id = :sid ORDER BY name", [':sid' => $stateId]);
        send_success($rows, 'Cities fetched', 200);
    }

    public function shippingModes() {
        $rows = $this->fetchAll("SELECT id, ship_mode, price, status, detail FROM cdb_shipping_mode WHERE status = 1 ORDER BY ship_mode");
        send_success($rows, 'Shipping modes fetched', 200);
    }

    public function deliveryTimes() {
        $rows = $this->fetchAll("SELECT id, delitime, status, detail FROM cdb_delivery_time WHERE status = 1 ORDER BY delitime");
        send_success($rows, 'Delivery times fetched', 200);
    }

    public function packaging() {
        $rows = $this->fetchAll("SELECT id, name_pack, detail, price, status FROM cdb_packaging WHERE status = 1 ORDER BY name_pack");
        send_success($rows, 'Packaging fetched', 200);
    }

    public function paymentMethods() {
        $rows = $this->fetchAll("SELECT id, name_pay, detail, status FROM cdb_met_payment WHERE status = 1 ORDER BY name_pay");
        send_success($rows, 'Payment methods fetched', 200);
    }

    public function courierCompanies() {
        $rows = $this->fetchAll("SELECT id, name_com, status, detail FROM cdb_courier_com WHERE status = 1 ORDER BY name_com");
        send_success($rows, 'Courier companies fetched', 200);
    }

    public function incoterms() {
        $rows = $this->fetchAll("SELECT id, inco_name, description, status FROM cdb_incoterm WHERE status = 1 ORDER BY inco_name");
        send_success($rows, 'Incoterms fetched', 200);
    }

    public function offices() {
        $rows = $this->fetchAll("SELECT id, name_off, code_off, address, phone, status FROM cdb_offices WHERE status = 1 ORDER BY name_off");
        send_success($rows, 'Offices fetched', 200);
    }

    public function branches() {
        $rows = $this->fetchAll("SELECT id, name_branch, address, phone, status FROM cdb_branchoffices WHERE status = 1 ORDER BY name_branch");
        send_success($rows, 'Branch offices fetched', 200);
    }

    public function statuses() {
        $rows = $this->fetchAll("SELECT id, mod_style, color, style_icon, active FROM cdb_styles WHERE active = 1 ORDER BY id");
        send_success($rows, 'Statuses fetched', 200);
    }
}

