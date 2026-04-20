<?php
/**
 * ReferencesHandler – read-only catalog / lookup endpoints (auth required).
 *
 * GET /api/v1/references/countries
 * GET /api/v1/references/states?country_id=
 * GET /api/v1/references/cities?state_id=
 * GET /api/v1/references/couriers
 * GET /api/v1/references/statuses
 * GET /api/v1/references/packaging
 * GET /api/v1/references/shipping-modes
 * GET /api/v1/references/delivery-times
 * GET /api/v1/references/categories
 * GET /api/v1/references/offices
 * GET /api/v1/references/branches
 * GET /api/v1/references/payment-methods
 */
class ReferencesHandler
{
    public function countries(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name, code FROM cdb_countries ORDER BY name ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function states(): void
    {
        ApiAuth::requireAuth();

        $countryId = (int)($_GET['country_id'] ?? 0);
        $db = new Conexion();

        if ($countryId > 0) {
            $db->cdp_query('SELECT id, name, code FROM cdb_states WHERE country_id = :cid ORDER BY name ASC');
            $db->bind(':cid', $countryId);
        } else {
            $db->cdp_query('SELECT id, name, code FROM cdb_states ORDER BY name ASC');
        }

        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function cities(): void
    {
        ApiAuth::requireAuth();

        $stateId = (int)($_GET['state_id'] ?? 0);
        $db = new Conexion();

        if ($stateId > 0) {
            $db->cdp_query('SELECT id, name FROM cdb_cities WHERE state_id = :sid ORDER BY name ASC');
            $db->bind(':sid', $stateId);
        } else {
            $db->cdp_query('SELECT id, name FROM cdb_cities ORDER BY name ASC');
        }

        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function couriers(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_com AS name FROM cdb_courier_com ORDER BY name_com ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function statuses(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, mod_style AS label, color FROM cdb_styles ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function packaging(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, packaging_name AS name FROM cdb_packaging ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function shippingModes(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_item AS name FROM cdb_shipping_mode ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function deliveryTimes(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, deli_time AS name FROM cdb_delivery_time ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function categories(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_item AS name FROM cdb_category ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function offices(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_off AS name FROM cdb_offices ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function branches(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_branch AS name FROM cdb_branchoffices ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    public function paymentMethods(): void
    {
        ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT id, name_method AS name, days, is_active FROM cdb_payment_methods WHERE is_active = 1 ORDER BY id ASC');
        $rows = $db->cdp_registros();
        ApiResponse::success(self::toArray($rows));
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private static function toArray($rows): array
    {
        if (!$rows) return [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = (array)$r;
        }
        return $out;
    }
}
