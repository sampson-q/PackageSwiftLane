<?php
/**
 * Uniqueness guarantees for the human-facing identifiers the system generates:
 * shipment numbers (cdb_add_order.order_no), package numbers
 * (cdb_customers_packages.order_no), consolidation numbers (c_no on both
 * consolidation tables) and customer locker numbers (cdb_users.locker).
 *
 * Why this exists
 * ---------------
 * The legacy generators did `SELECT MAX(col)` on a VARCHAR column and trusted
 * whatever number the browser posted back. Two things broke that:
 *   1. Junk rows ("FFFFF9", "5.7E+264") made MAX() return a non-numeric string,
 *      so the "next" number collapsed to 000001 for every new shipment.
 *   2. The number was suggested at page-load and posted back later, so two
 *      people opening the same form got the same suggestion and both saved it.
 * The virtual-locker sequence table was empty, so every signup was offered the
 * same locker and the random mode never checked for collisions.
 *
 * How it works
 * ------------
 * cdb_unique_ids (scope, value) with a PRIMARY KEY is the arbiter. Claiming a
 * number = inserting that row; the database rejects the second claimer, so two
 * concurrent requests can never leave with the same number. The real table is
 * still consulted for numbers that pre-date the registry.
 *
 * Call cdp_uidClaim() immediately before the INSERT that stores the number and
 * use the value it returns — never the raw posted value.
 */

if (!function_exists('cdp_uidScopes')) {

    function cdp_uidScopes()
    {
        return [
            // Air shipments / pickups
            'order_no' => [
                'tables'  => ['cdb_add_order' => 'order_no'],
                'digits'  => 'track_digit',
            ],
            // Sea packages ("customer packages")
            'package_no' => [
                'tables'  => ['cdb_customers_packages' => 'order_no'],
                'digits'  => 'track_digit',
            ],
            // Consolidations share one sequence across air + sea tables (the
            // legacy generator always read cdb_consolidate for both).
            'consolidate_no' => [
                'tables'  => ['cdb_consolidate' => 'c_no', 'cdb_consolidate_packages' => 'c_no'],
                'digits'  => 'track_digit',
            ],
            // Customer locker digits. The column may hold "PREFIX 123456" or a
            // bare "123456"; uniqueness is on the digits.
            'locker' => [
                'tables'  => ['cdb_users' => 'locker'],
                'digits'  => 'digit_random_locker',
                'locker'  => true,
            ],
        ];
    }

    function cdp_uidDb()
    {
        static $db = null;
        if ($db === null) {
            $db = new Conexion;
        }
        return $db;
    }

    function cdp_uidEnsureTable()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $db = cdp_uidDb();
        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_unique_ids (
            scope      VARCHAR(40) NOT NULL,
            value      VARCHAR(64) NOT NULL,
            created_at DATETIME    NOT NULL,
            PRIMARY KEY (scope, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->cdp_execute();
    }

    /** Number of digits configured for a scope (falls back to 6). */
    function cdp_uidDigits($scope)
    {
        static $settings = null;
        $scopes = cdp_uidScopes();
        if (!isset($scopes[$scope])) {
            return 6;
        }
        if ($settings === null) {
            $db = cdp_uidDb();
            $db->cdp_query("SELECT track_digit, digit_random_locker FROM cdb_settings LIMIT 1");
            $settings = $db->cdp_registro();
        }
        $key = $scopes[$scope]['digits'];
        $n = ($settings && isset($settings->$key)) ? (int) $settings->$key : 0;
        return ($n >= 1 && $n <= 12) ? $n : 6;
    }

    /**
     * Canonical form of a value for a scope: digits are zero-padded to the
     * configured width; anything else is trimmed as-is (custom alphanumeric
     * numbers are allowed but still checked for uniqueness).
     * For lockers a leading prefix ("SLL 123456") is stripped.
     */
    function cdp_uidNormalize($scope, $value)
    {
        $value = trim((string) $value);
        $scopes = cdp_uidScopes();
        if (!empty($scopes[$scope]['locker'])) {
            // keep only the trailing digit run
            if (preg_match('/(\d+)\s*$/', $value, $m)) {
                $value = $m[1];
            }
        }
        if ($value !== '' && ctype_digit($value)) {
            $width = cdp_uidDigits($scope);
            if (strlen($value) < $width) {
                $value = str_pad($value, $width, '0', STR_PAD_LEFT);
            }
        }
        return $value;
    }

    /** True when the value is already used in the real table(s) or reserved. */
    function cdp_uidExists($scope, $value)
    {
        $scopes = cdp_uidScopes();
        if (!isset($scopes[$scope])) {
            return false;
        }
        $value = cdp_uidNormalize($scope, $value);
        if ($value === '') {
            return false;
        }
        cdp_uidEnsureTable();
        $db = cdp_uidDb();

        $db->cdp_query("SELECT 1 FROM cdb_unique_ids WHERE scope = :s AND value = :v LIMIT 1");
        $db->bind(':s', $scope);
        $db->bind(':v', $value);
        if ($db->cdp_registro()) {
            return true;
        }

        foreach ($scopes[$scope]['tables'] as $table => $col) {
            if (!empty($scopes[$scope]['locker'])) {
                // "123456", "SLL 123456", "SLL123456" and un-padded "12345"
                $db->cdp_query("SELECT 1 FROM $table
                    WHERE $col = :v
                       OR $col LIKE :suffix
                       OR ($col REGEXP '^[0-9]+$' AND CAST($col AS UNSIGNED) = :n)
                    LIMIT 1");
                $db->bind(':v', $value);
                $db->bind(':suffix', '%' . $value);
                $db->bind(':n', (int) $value);
            } else {
                $db->cdp_query("SELECT 1 FROM $table WHERE $col = :v LIMIT 1");
                $db->bind(':v', $value);
            }
            if ($db->cdp_registro()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Next sequential number for a scope (zero-padded). Only genuinely numeric
     * values that fit the configured width are considered, so junk rows can no
     * longer poison the sequence. Reserved-but-unused values count too.
     * This is a PEEK for pre-filling forms; it reserves nothing.
     */
    function cdp_uidNext($scope)
    {
        $scopes = cdp_uidScopes();
        if (!isset($scopes[$scope])) {
            return '';
        }
        cdp_uidEnsureTable();
        $db    = cdp_uidDb();
        $width = cdp_uidDigits($scope);
        $max   = 0;

        foreach ($scopes[$scope]['tables'] as $table => $col) {
            if (!empty($scopes[$scope]['locker'])) {
                // digits at the end of the stored value, with or without prefix
                $db->cdp_query("SELECT MAX(CAST(REGEXP_SUBSTR($col, '[0-9]+$') AS UNSIGNED)) AS m
                    FROM $table
                    WHERE $col REGEXP '[0-9]+$'
                      AND LENGTH(REGEXP_SUBSTR($col, '[0-9]+$')) <= :w");
            } else {
                $db->cdp_query("SELECT MAX(CAST($col AS UNSIGNED)) AS m
                    FROM $table
                    WHERE $col REGEXP '^[0-9]+$' AND LENGTH($col) <= :w");
            }
            $db->bind(':w', $width);
            $row = $db->cdp_registro();
            if ($row && (int) $row->m > $max) {
                $max = (int) $row->m;
            }
        }

        $db->cdp_query("SELECT MAX(CAST(value AS UNSIGNED)) AS m FROM cdb_unique_ids
            WHERE scope = :s AND value REGEXP '^[0-9]+$' AND LENGTH(value) <= :w");
        $db->bind(':s', $scope);
        $db->bind(':w', $width);
        $row = $db->cdp_registro();
        if ($row && (int) $row->m > $max) {
            $max = (int) $row->m;
        }

        return str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT);
    }

    /** Random candidate of the configured width (never all zeros). */
    function cdp_uidRandom($scope)
    {
        $width = cdp_uidDigits($scope);
        $n = random_int(1, (int) str_repeat('9', $width));
        return str_pad((string) $n, $width, '0', STR_PAD_LEFT);
    }

    /** Try to reserve one value. True only if this call won the row. */
    function cdp_uidReserve($scope, $value)
    {
        cdp_uidEnsureTable();
        $db = cdp_uidDb();
        // INSERT IGNORE + rowCount: a duplicate key affects 0 rows, no exception
        // under the wrapper's silent PDO mode.
        $db->cdp_query("INSERT IGNORE INTO cdb_unique_ids (scope, value, created_at) VALUES (:s, :v, NOW())");
        $db->bind(':s', $scope);
        $db->bind(':v', $value);
        $db->cdp_execute();
        return $db->cdp_rowCount() === 1;
    }

    /**
     * Does the system generate this kind of number randomly (true) or as a
     * running sequence (false)? Mirrors the add forms: shipments/packages/
     * consolidations are sequential only when cdb_settings.code_number = 1;
     * lockers are random when code_number_locker = 2.
     */
    function cdp_uidRandomMode($scope)
    {
        static $s = null;
        if ($s === null) {
            $db = cdp_uidDb();
            $db->cdp_query("SELECT code_number, code_number_locker FROM cdb_settings LIMIT 1");
            $s = $db->cdp_registro() ?: (object) ['code_number' => 0, 'code_number_locker' => 1];
        }
        $scopes = cdp_uidScopes();
        if (!empty($scopes[$scope]['locker'])) {
            return (int) $s->code_number_locker === 2;
        }
        return (int) $s->code_number !== 1;
    }

    /**
     * Claim a unique value for $scope and return it.
     *
     * $requested is what the caller would like (usually the number the form was
     * pre-filled with, possibly edited by the user). It is used when still free;
     * otherwise the next free value is allocated — randomly or sequentially
     * following the system's numbering mode ($random overrides when not null).
     *
     * The returned value is the one that MUST be stored.
     */
    function cdp_uidClaim($scope, $requested = '', $random = null)
    {
        $scopes = cdp_uidScopes();
        if (!isset($scopes[$scope])) {
            return cdp_uidNormalize($scope, $requested);
        }
        if ($random === null) {
            $random = cdp_uidRandomMode($scope);
        }

        $candidate = cdp_uidNormalize($scope, $requested);
        for ($i = 0; $i < 500; $i++) {
            if ($candidate !== '') {
                if (cdp_uidReserve($scope, $candidate)) {
                    // Won the registry row; make sure a legacy row (pre-registry)
                    // does not already carry this value.
                    $tableHit = false;
                    foreach ($scopes[$scope]['tables'] as $table => $col) {
                        $db = cdp_uidDb();
                        if (!empty($scopes[$scope]['locker'])) {
                            $db->cdp_query("SELECT 1 FROM $table
                                WHERE $col = :v OR $col LIKE :suffix
                                   OR ($col REGEXP '^[0-9]+$' AND CAST($col AS UNSIGNED) = :n)
                                LIMIT 1");
                            $db->bind(':v', $candidate);
                            $db->bind(':suffix', '%' . $candidate);
                            $db->bind(':n', (int) $candidate);
                        } else {
                            $db->cdp_query("SELECT 1 FROM $table WHERE $col = :v LIMIT 1");
                            $db->bind(':v', $candidate);
                        }
                        if ($db->cdp_registro()) {
                            $tableHit = true;
                            break;
                        }
                    }
                    if (!$tableHit) {
                        return $candidate;
                    }
                    // keep the registry row: the value IS taken
                }
            }
            $candidate = $random ? cdp_uidRandom($scope) : cdp_uidNext($scope);
        }

        // Astronomically unlikely; fall back to a timestamp-based value so the
        // caller never stores an empty number.
        $fallback = substr((string) time(), -cdp_uidDigits($scope));
        cdp_uidReserve($scope, $fallback);
        return $fallback;
    }

    /** Give a reserved value back (e.g. the INSERT that needed it failed). */
    function cdp_uidRelease($scope, $value)
    {
        cdp_uidEnsureTable();
        $db = cdp_uidDb();
        $db->cdp_query("DELETE FROM cdb_unique_ids WHERE scope = :s AND value = :v");
        $db->bind(':s', $scope);
        $db->bind(':v', cdp_uidNormalize($scope, $value));
        $db->cdp_execute();
    }

    /**
     * Locker helper: returns the unique DIGITS to store (caller prepends the
     * configured prefix). Honors the "random code" locker mode from settings
     * when nothing usable was requested.
     */
    function cdp_claimLockerDigits($requested = '')
    {
        return cdp_uidClaim('locker', $requested, cdp_uidRandomMode('locker'));
    }
}
