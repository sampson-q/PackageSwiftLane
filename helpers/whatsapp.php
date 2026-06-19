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

if (!function_exists('cdp_wa_requireV2')) {
    /** Lazy-load the v2 sender (circular-safe: it require_once's this file). */
    function cdp_wa_requireV2()
    {
        if (!function_exists('sendNotificationWhatsApp_v2')) {
            require_once dirname(__DIR__) . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';
        }
    }
}

if (!function_exists('cdp_renderWhatsAppTemplate')) {
    /**
     * Load a whatsapp_templates row and fill its placeholders.
     * @return string|null null when the template is missing (caller should skip).
     */
    function cdp_renderWhatsAppTemplate($templateId, array $placeholders)
    {
        $tpl = getTemplateWhatsApp((int) $templateId);
        if (!$tpl || $tpl->body === null || $tpl->body === '') {
            cdp_wa_log("template {$templateId} missing/empty — message skipped");
            return null;
        }
        return str_replace(array_keys($placeholders), array_values($placeholders), $tpl->body);
    }
}

if (!function_exists('cdp_wa_lookupName')) {
    /** Single-column reference lookup with N/A fallback (name_com/ship_mode/delitime/name_off...). */
    function cdp_wa_lookupName($table, $column, $id)
    {
        if ((int) $id <= 0) {
            return 'N/A';
        }
        $db = new Conexion;
        $db->cdp_query("SELECT {$column} AS v FROM {$table} WHERE id = :id LIMIT 1");
        $db->bind(':id', (int) $id);
        $r = $db->cdp_registro();
        return ($r && $r->v !== null && $r->v !== '') ? $r->v : 'N/A';
    }
}

if (!function_exists('cdp_sendShipmentRegisteredWhatsApp')) {
    /**
     * "Your shipment is registered" message (template 4) — shared by the air/sea
     * add+accept flows. Text only by design: the old PDF attachment was the
     * invoice, and money details are excluded from sender alerts.
     *
     * @param object $sender     cdb_users row (the package owner)
     * @param string $tracking   full tracking number (prefix + number)
     * @param array  $ids        ['courier'=>id,'service'=>id,'delitime'=>id,'office'=>id]
     *                           Service/category fall back to cdb_info_ship_default when 0.
     * @param array  $extraLines pre-rendered "• ..." detail lines (pieces, weight,
     *                           carrier tracking, ETA — never money)
     */
    function cdp_sendShipmentRegisteredWhatsApp($sender, $tracking, array $ids = [], array $extraLines = [])
    {
        cdp_wa_requireV2();
        $settings = cdp_getSettingsCourier();

        // Forms don't always submit the service — use the admin default rather
        // than rendering N/A.
        $service_id = (int) ($ids['service'] ?? 0);
        if ($service_id <= 0 && function_exists('cdp_getInfoShipDefault')) {
            $defaults = cdp_getInfoShipDefault();
            $service_id = (int) ($defaults->service_default4 ?? 0);
        }

        $track_url = rtrim((string) ($settings->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($tracking);

        $body = cdp_renderWhatsAppTemplate(4, [
            '[CUSTOMER_FULLNAME]' => ucfirst(trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? ''))),
            '[TRACKING_NUMBER]'   => $tracking,
            '[SERVICE_TYPE]'      => cdp_wa_lookupName('cdb_shipping_mode', 'ship_mode', $service_id),
            '[COURIER_NAME]'      => cdp_wa_lookupName('cdb_courier_com', 'name_com', $ids['courier'] ?? 0),
            '[DELIVERY_TIME]'     => cdp_wa_lookupName('cdb_delivery_time', 'delitime', $ids['delitime'] ?? 0),
            '[ORIGIN_OFFICE]'     => cdp_wa_lookupName('cdb_offices', 'name_off', $ids['office'] ?? 0),
            '[EXTRA_DETAILS]'     => $extraLines ? implode("\n", $extraLines) . "\n" : '',
            '[TRACK_URL]'         => $track_url,
            '[COMPANY_SITE_URL]'  => !empty($settings->site_url) ? $settings->site_url : '',
            '[COMPANY_NAME]'      => !empty($settings->site_name) ? $settings->site_name : 'Our Company',
        ]);
        if ($body === null) {
            return ['success' => false, 'skipped' => true, 'message' => 'WhatsApp template 4 not found.'];
        }
        return sendNotificationWhatsApp_v2($sender, $body);
    }
}

if (!function_exists('cdp_wa_buildShipmentExtraLines')) {
    /**
     * Extra detail lines for the "shipment registered" message, sourced from the
     * submitting form's POST: pieces / total weight / dimensions (packages json),
     * carrier tracking number and ETA. Never money. Returns [] when none apply.
     */
    function cdp_wa_buildShipmentExtraLines()
    {
        $settings = cdp_getSettingsCourier();
        $lines = array();

        // Total Weight is the Original Total Weight ONLY (the staff-entered
        // package_total_weight). Item weights price the items — they do NOT make
        // up the package weight — so they are never summed here. Omitted if unset.
        $weightUnit = trim((string) ($settings->weight_p ?? 'lb'));
        $ptw = (isset($_POST['package_total_weight']) && $_POST['package_total_weight'] !== '')
            ? (float) $_POST['package_total_weight'] : 0.0;
        if ($ptw > 0) {
            $lines[] = '• Total Weight: ' . (0 + $ptw) . ($weightUnit !== '' ? ' ' . $weightUnit : '');
        }

        // Contents = items + quantities (no pieces count, no dimensions).
        if (isset($_POST['packages'])) {
            $pkgs = json_decode($_POST['packages']);
            if (is_array($pkgs) && count($pkgs) > 0) {
                $contents = array();
                foreach ($pkgs as $p) {
                    $qty = max(1, (int) ($p->qty ?? 1));
                    $desc = trim((string) ($p->description ?? ''));
                    if ($desc !== '') {
                        $contents[] = $qty . ' x ' . $desc;
                    }
                }
                if ($contents) {
                    $lines[] = '• Contents: ' . implode('; ', $contents);
                }
            }
        }
        $pt = trim((string) ($_POST['tracking_number'] ?? ''));
        if ($pt !== '' && $pt !== '0') {
            $lines[] = '• Carrier Tracking #: *' . $pt . '*';
        }
        $eta = trim((string) ($_POST['estimated_eta'] ?? ''));
        if ($eta !== '') {
            $lines[] = '• Estimated Arrival: ' . $eta;
        }
        return $lines;
    }
}

if (!function_exists('cdp_sendStatusUpdateWhatsApp')) {
    /**
     * "Your shipment status changed" message (template 11) for a single package.
     *
     * @param object      $sender      cdb_users row
     * @param string      $tracking    full tracking number
     * @param string      $statusLabel current status name (cdb_styles.mod_style)
     * @param string|null $appUrl      tracking link; defaults to site track page
     */
    function cdp_sendStatusUpdateWhatsApp($sender, $tracking, $statusLabel, $appUrl = null)
    {
        cdp_wa_requireV2();
        $settings = cdp_getSettingsCourier();
        if ($appUrl === null) {
            $appUrl = rtrim((string) ($settings->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($tracking);
        }

        $body = cdp_renderWhatsAppTemplate(11, [
            '[CUSTOMER_FULLNAME]' => ucfirst(trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? ''))),
            '[TRACKING_NUMBER]'   => $tracking,
            '[CURR_STATUS]'       => (string) $statusLabel,
            '[APP_URL]'           => $appUrl,
            '[COMPANY_NAME]'      => !empty($settings->site_name) ? $settings->site_name : 'Our Company',
        ]);
        if ($body === null) {
            return ['success' => false, 'skipped' => true, 'message' => 'WhatsApp template 11 not found.'];
        }
        return sendNotificationWhatsApp_v2($sender, $body);
    }
}

if (!function_exists('cdp_notifyConsolidationPackageSenders')) {
    /**
     * Fan-out: tell the sender of every package inside a consolidation that the
     * consolidation's status changed. The consolidation status has display
     * priority over the package's own status, so this IS the package update —
     * except for packages that already left the consolidation.
     *
     * Skip rules (per product decision):
     *   - package pulled out: is_consolidate = 0
     *   - own status says it's out: 8 Delivered, 15 Picked up, 16 Not Picked Up,
     *     21 Cancelled, 27 Returned to Vendor, 32 Ready for PickUp
     * NOTE: the per-order notify_whatsapp_sender flag is intentionally NOT a
     * blocker here — every existing row carries the column default (0), so it
     * has never represented a real opt-out decision.
     *
     * Callers are responsible for the actual-change guard (only call when the
     * consolidation's status really changed) and must never call this for
     * money/invoice-only events.
     *
     * @param string     $module                'consolidate' (air) | 'consolidate_packages' (sea)
     * @param int        $consolidate_id
     * @param string     $consolidationTracking c_prefix . c_no
     * @param string     $statusLabel           e.g. "Consolidated", "In_Transit", "Delivered"
     * @param array|null $onlyOrderIds          restrict to these package order_ids (e.g. newly added)
     * @param bool       $applySkipRules        false when the caller already filtered $onlyOrderIds
     *                                          by PRIOR package state (needed when the same request
     *                                          just flipped packages out, e.g. the status-32 carve-out)
     * @return array ['sent' => int, 'skipped' => int]
     */
    function cdp_notifyConsolidationPackageSenders($module, $consolidate_id, $consolidationTracking, $statusLabel, $onlyOrderIds = null, $applySkipRules = true)
    {
        cdp_wa_requireV2();

        $tables = array(
            'consolidate'          => array('detail' => 'cdb_consolidate_detail',          'orders' => 'cdb_add_order'),
            'consolidate_packages' => array('detail' => 'cdb_consolidate_packages_detail', 'orders' => 'cdb_customers_packages'),
        );
        if (!isset($tables[$module])) {
            cdp_wa_log("fan-out: unknown module '{$module}'");
            return array('sent' => 0, 'skipped' => 0);
        }
        $detailTable = $tables[$module]['detail'];
        $ordersTable = $tables[$module]['orders'];
        $outOfConsolidationStatuses = array(8, 15, 16, 21, 27, 32);

        // Per-package detail enrichment (items / weight / carrier tracking)
        // reads from the air or sea order tables via the shared notify helper.
        $phModule = ($module === 'consolidate_packages') ? 'sea' : 'air';
        require_once __DIR__ . '/notify_placeholders.php';

        $settings = cdp_getSettingsCourier();

        $db = new Conexion;
        $db->cdp_query("SELECT d.order_id, o.order_prefix, o.order_no, o.sender_id, o.status_courier, o.is_consolidate
            FROM {$detailTable} d
            INNER JOIN {$ordersTable} o ON o.order_id = d.order_id
            WHERE d.consolidate_id = :cid");
        $db->bind(':cid', (int) $consolidate_id);
        $packages = $db->cdp_registros();

        $sent = 0;
        $skipped = 0;
        $only = ($onlyOrderIds === null) ? null : array_map('intval', (array) $onlyOrderIds);

        foreach ($packages as $pkg) {
            try {
                if ($only !== null && !in_array((int) $pkg->order_id, $only, true)) {
                    continue;
                }
                if ($applySkipRules
                    && ((int) $pkg->is_consolidate === 0
                        || in_array((int) $pkg->status_courier, $outOfConsolidationStatuses, true))) {
                    $skipped++;
                    continue;
                }
                $sender = cdp_getSenderCourier((int) $pkg->sender_id);
                if (!$sender || empty($sender->phone)) {
                    $skipped++;
                    continue;
                }

                $tracking = $pkg->order_prefix . $pkg->order_no;
                $body = cdp_renderWhatsAppTemplate(17, array(
                    '[CUSTOMER_FULLNAME]'      => ucfirst(trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? ''))),
                    '[TRACKING_NUMBER]'        => $tracking,
                    '[CONSOLIDATION_TRACKING]' => (string) $consolidationTracking,
                    '[CURR_STATUS]'            => (string) $statusLabel,
                    '[APP_URL]'                => rtrim((string) ($settings->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($tracking),
                    '[COMPANY_NAME]'           => !empty($settings->site_name) ? $settings->site_name : 'Our Company',
                ));
                if ($body === null) {
                    // Template missing: nothing will render for any package — stop.
                    return array('sent' => $sent, 'skipped' => $skipped + 1);
                }

                // Append this package's own details (no money): carrier tracking,
                // weight, items. Template 17 has no [EXTRA_DETAILS] slot.
                if (function_exists('cdp_buildPackageNotifyPlaceholders')) {
                    $pkg_ph = cdp_buildPackageNotifyPlaceholders((int) $pkg->order_id, $phModule);
                    $detail_lines = array();
                    if ($pkg_ph['[WEIGHT]'] !== 'N/A') {
                        $detail_lines[] = '• Total Weight: ' . $pkg_ph['[WEIGHT]'];
                    }
                    if ($pkg_ph['[ITEMS]'] !== 'N/A') {
                        $detail_lines[] = '• Items:';
                        foreach (explode("\n", $pkg_ph['[ITEMS]']) as $il) {
                            $detail_lines[] = '   - ' . $il;
                        }
                    }
                    if ($pkg_ph['[POSTAL_TRACKING]'] !== 'N/A') {
                        $detail_lines[] = '• Carrier Tracking #: *' . $pkg_ph['[POSTAL_TRACKING]'] . '*';
                    }
                    if ($detail_lines) {
                        $body .= "\n\n" . implode("\n", $detail_lines);
                    }
                }

                $res = sendNotificationWhatsApp_v2($sender, $body);
                if (!empty($res['success'])) {
                    $sent++;
                } else {
                    $skipped++;
                    cdp_wa_log("fan-out skip/fail for {$tracking}: " . ($res['message'] ?? ''));
                }
            } catch (Exception $e) {
                $skipped++;
                cdp_wa_log('fan-out error for order_id ' . $pkg->order_id . ': ' . $e->getMessage());
            }
        }

        return array('sent' => $sent, 'skipped' => $skipped);
    }
}

if (!function_exists('cdp_personalizeWhatsAppBody')) {
    /** Fill the generic placeholders in an admin-typed broadcast body. */
    function cdp_personalizeWhatsAppBody($body, $sender, $tracking = null)
    {
        $settings = cdp_getSettingsCourier();
        return str_replace(
            ['[CUSTOMER_FULLNAME]', '[COMPANY_NAME]', '[COMPANY_SITE_URL]', '[TRACKING_NUMBER]'],
            [
                ucfirst(trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? ''))),
                (string) ($settings->site_name ?? ''),
                (string) ($settings->site_url ?? ''),
                (string) $tracking,
            ],
            (string) $body
        );
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
