<?php
/**
 * WhatsApp delivery helpers.
 *
 * Two jobs:
 *   1. cdp_normalizePhone()    – turn a stored phone into a clean international
 *      (digits-only, no "+") number so the UltraMsg API actually accepts it.
 *      Badly-formatted numbers are a major cause of silently-failed sends.
 *   2. cdp_isWhatsAppNumber()  – ask UltraMsg whether a number is actually on
 *      WhatsApp before we message it, so we stop hammering invalid numbers
 *      (which is what gets the WhatsApp/UltraMsg account banned).
 *
 * Verification policy = FAIL-OPEN (per product decision): we only skip a send
 * when the API *explicitly* reports the number is NOT on WhatsApp. If the check
 * is unavailable / times out / rate-limited, we still send.
 *
 * Results are cached in a self-provisioning table (cdb_whatsapp_number_cache)
 * so we don't re-check the same number on every message.
 *
 * Requires lib/Conexion.php and helpers/querys.php (cdp_getSettingsCourier) to
 * be loaded, which they always are wherever WhatsApp is sent.
 */

if (!function_exists('cdp_wa_log')) {
    function cdp_wa_log($msg)
    {
        error_log('[whatsapp] ' . $msg);
    }
}

if (!function_exists('cdp_wa_tableExists')) {
    function cdp_wa_tableExists($table)
    {
        static $cache = array();
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT COUNT(*) AS c FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t");
        $db->bind(':t', $table);
        $row = $db->cdp_registro();
        return $cache[$table] = ($row && (int) $row->c > 0);
    }
}

if (!function_exists('cdp_wa_ensureCacheTable')) {
    /** Lazily create the number-check cache table (once per request). */
    function cdp_wa_ensureCacheTable()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (cdp_wa_tableExists('cdb_whatsapp_number_cache')) {
            return $ready = true;
        }
        $db = new Conexion;
        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_whatsapp_number_cache (
            phone VARCHAR(32) NOT NULL PRIMARY KEY,
            is_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
            checked_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = (bool) $db->cdp_execute();
        return $ready;
    }
}

if (!function_exists('cdp_resolveDialingCode')) {
    /**
     * Resolve a dialing code (digits, no "+") from a hint that may be a
     * cdb_countries id, a phone code, an ISO3/ISO2 code, or a country name.
     */
    function cdp_resolveDialingCode($hint)
    {
        if ($hint === null) {
            return '';
        }
        $hint = trim((string) $hint);
        if ($hint === '') {
            return '';
        }
        $db = new Conexion;

        if (ctype_digit($hint)) {
            // Prefer a country id match; otherwise treat the digits as the code.
            $db->cdp_query("SELECT phone_code FROM cdb_countries WHERE id = :id LIMIT 1");
            $db->bind(':id', (int) $hint);
            $row = $db->cdp_registro();
            if ($row && $row->phone_code !== null && $row->phone_code !== '') {
                return preg_replace('/\D+/', '', $row->phone_code);
            }
            return preg_replace('/\D+/', '', $hint);
        }

        $upper = strtoupper($hint);
        $db->cdp_query("SELECT phone_code FROM cdb_countries
            WHERE iso3 = :iso OR name = :name OR iso3 LIKE :iso2 LIMIT 1");
        $db->bind(':iso', $upper);
        $db->bind(':name', $hint);
        $db->bind(':iso2', (strlen($upper) === 2 ? $upper . '%' : $upper));
        $row = $db->cdp_registro();
        if ($row && $row->phone_code) {
            return preg_replace('/\D+/', '', $row->phone_code);
        }
        return '';
    }
}

if (!function_exists('cdp_getDefaultDialingCode')) {
    /** Company default dialing code, derived from settings (cached). */
    function cdp_getDefaultDialingCode()
    {
        static $code = null;
        if ($code !== null) {
            return $code;
        }
        $settings = cdp_getSettingsCourier();
        $hint = ($settings && isset($settings->c_country)) ? $settings->c_country : '';
        return $code = cdp_resolveDialingCode($hint);
    }
}

if (!function_exists('cdp_normalizePhone')) {
    /**
     * Normalise a phone to international digits (no "+", no spaces).
     * Never corrupts an already-international number; only prepends a dialing
     * code for clearly-national numbers when one is resolvable.
     *
     * @param string      $phone
     * @param mixed|null  $countryHint  per-recipient country (id/iso/name/code)
     * @return string  digits only, or '' if nothing usable
     */
    function cdp_normalizePhone($phone, $countryHint = null)
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return '';
        }

        $hadPlus = (strpos($raw, '+') === 0);
        $digits  = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return '';
        }

        // 00 = international access prefix -> strip it, the rest is international.
        if (strpos($digits, '00') === 0) {
            $rest = substr($digits, 2);
            return (ltrim($rest, '0') === '') ? '' : $rest;
        }
        if ($hadPlus) {
            return $digits; // already international
        }

        $code = cdp_resolveDialingCode($countryHint);
        if ($code === '') {
            $code = cdp_getDefaultDialingCode();
        }

        // National number with trunk zero, e.g. 0552453008.
        if ($digits[0] === '0') {
            $national = ltrim($digits, '0');
            return ($code !== '') ? $code . $national : $national;
        }
        // Already prefixed with the country code.
        if ($code !== '' && strpos($digits, $code) === 0) {
            return $digits;
        }
        // Looks like a bare national number -> prepend the code.
        if ($code !== '' && strlen($digits) <= 10) {
            return $code . $digits;
        }
        return $digits;
    }
}

if (!function_exists('cdp_wa_parseCheck')) {
    /** Parse an UltraMsg /contacts/check response -> true|false|null(unknown). */
    function cdp_wa_parseCheck($resp)
    {
        $data = json_decode($resp, true);
        if (is_array($data)) {
            if (isset($data['status'])) {
                $s = strtolower((string) $data['status']);
                if (strpos($s, 'invalid') !== false) {
                    return false;
                }
                if (strpos($s, 'valid') !== false) {
                    return true;
                }
            }
            if (isset($data['valid'])) {
                return (bool) $data['valid'];
            }
            if (isset($data['exists'])) {
                return (bool) $data['exists'];
            }
        }
        return null; // unrecognised -> unknown -> fail-open
    }
}

if (!function_exists('cdp_isWhatsAppNumber')) {
    /**
     * Is this number registered on WhatsApp? Cached, fail-open.
     *
     * @param string $normalizedPhone  digits only (from cdp_normalizePhone)
     * @param int    $ttlDays          how long a cached result stays fresh
     * @return bool|null  true=on WhatsApp, false=NOT on WhatsApp, null=unknown
     */
    function cdp_isWhatsAppNumber($normalizedPhone, $ttlDays = 30)
    {
        $phone = preg_replace('/\D+/', '', (string) $normalizedPhone);
        if ($phone === '') {
            return false;
        }

        $settings = cdp_getSettingsCourier();
        $base  = ($settings && isset($settings->api_ws_url)) ? rtrim($settings->api_ws_url, '/') : '';
        $token = ($settings && isset($settings->api_ws_token)) ? $settings->api_ws_token : '';
        if ($base === '' || $token === '') {
            return null; // not configured -> can't verify -> fail-open
        }

        // 1) cache
        if (cdp_wa_ensureCacheTable()) {
            $db = new Conexion;
            $db->cdp_query("SELECT is_whatsapp, checked_at FROM cdb_whatsapp_number_cache WHERE phone = :p LIMIT 1");
            $db->bind(':p', $phone);
            $hit = $db->cdp_registro();
            if ($hit && strtotime($hit->checked_at) > (time() - $ttlDays * 86400)) {
                return ((int) $hit->is_whatsapp === 1);
            }
        }

        // 2) live check
        $url = $base . '/contacts/check?token=' . urlencode($token) . '&chatId=' . urlencode($phone . '@c.us');
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $resp === false) {
            cdp_wa_log("contacts/check failed for {$phone}: {$err}");
            return null; // fail-open
        }

        $result = cdp_wa_parseCheck($resp);

        // 3) cache definitive results
        if ($result !== null && cdp_wa_ensureCacheTable()) {
            $db = new Conexion;
            $db->cdp_query("INSERT INTO cdb_whatsapp_number_cache (phone, is_whatsapp, checked_at)
                VALUES (:p, :w, :t)
                ON DUPLICATE KEY UPDATE is_whatsapp = VALUES(is_whatsapp), checked_at = VALUES(checked_at)");
            $db->bind(':p', $phone);
            $db->bind(':w', $result ? 1 : 0, PDO::PARAM_INT);
            $db->bind(':t', date('Y-m-d H:i:s'));
            $db->cdp_execute();
        }

        return $result;
    }
}

if (!function_exists('cdp_wa_resolveSendTarget')) {
    /**
     * Shared gate used by every WhatsApp send path. Returns the number to send
     * to, or '' to skip. Centralises normalise -> verify -> fail-open.
     *
     * @param object|array $entity      record holding ->phone (and maybe country)
     * @param mixed|null   $countryHint optional explicit country
     * @return array ['phone' => string, 'skip' => bool, 'reason' => string]
     */
    function cdp_wa_resolveSendTarget($entity, $countryHint = null)
    {
        $rawPhone = '';
        if (is_object($entity) && isset($entity->phone)) {
            $rawPhone = $entity->phone;
        } elseif (is_array($entity) && isset($entity['phone'])) {
            $rawPhone = $entity['phone'];
        }

        if ($countryHint === null && is_object($entity)) {
            foreach (array('country', 'c_country', 'phone_country', 'country_id') as $f) {
                if (isset($entity->$f) && $entity->$f !== '' && $entity->$f !== null) {
                    $countryHint = $entity->$f;
                    break;
                }
            }
        }

        $phone = cdp_normalizePhone($rawPhone, $countryHint);
        if ($phone === '') {
            return array('phone' => '', 'skip' => true, 'reason' => 'No valid phone number.');
        }

        $isWa = cdp_isWhatsAppNumber($phone);
        if ($isWa === false) {
            cdp_wa_log("skip non-WhatsApp number {$phone}");
            return array('phone' => $phone, 'skip' => true, 'reason' => 'Recipient number is not on WhatsApp.');
        }

        // true or null (unknown) -> send (fail-open)
        return array('phone' => $phone, 'skip' => false, 'reason' => '');
    }
}
