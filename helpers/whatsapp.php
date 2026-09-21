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
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
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
            '[CUSTOMER_FULLNAME]' => cdp_nameWithLocker($sender),
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
        cdp_msgSetContext(['template_id' => 4, 'entity_type' => 'shipment', 'entity_label' => (string) $tracking, 'subject' => 'Shipment registered ' . $tracking]);
        $res = sendNotificationWhatsApp_v2($sender, $body);
        cdp_msgClearContext(['template_id', 'entity_type', 'entity_label', 'subject']);
        return $res;
    }
}

if (!function_exists('cdp_wa_buildShipmentExtraLines')) {
    /**
     * Extra detail lines for the "shipment registered" message: total weight,
     * contents, carrier tracking number and ETA. Never money.
     *
     * The submitting form's POST wins (it is the freshest value), but only the
     * courier add/edit forms carry an "Original Total Weight" input — the sea,
     * pickup, multiple and accept forms do not. So whenever a field is missing
     * from the POST we fall back to what was just persisted for the order, which
     * is why $order_id matters: without it those flows sent a message with no
     * weight line at all.
     *
     * @param int    $order_id     order id in the module's order table (0 = POST only)
     * @param string $module       'air' (cdb_add_order) | 'sea' (cdb_customers_packages)
     * @param bool   $preferStored ignore the POST and describe the stored order only.
     *                             Required by the "multiple" flows, where one request
     *                             creates one shipment per posted package: the POST
     *                             describes every shipment, the row describes this one.
     * @return array pre-rendered "• ..." lines; [] when none apply
     */
    function cdp_wa_buildShipmentExtraLines($order_id = 0, $module = 'air', $preferStored = false)
    {
        $settings = cdp_getSettingsCourier();
        $lines = array();

        // Persisted fallback for anything the form didn't post.
        $ph = array();
        if ((int) $order_id > 0) {
            require_once __DIR__ . '/notify_placeholders.php';
            if (function_exists('cdp_buildPackageNotifyPlaceholders')) {
                $ph = cdp_buildPackageNotifyPlaceholders((int) $order_id, $module);
            }
        }
        $post = ($preferStored && $ph) ? array() : $_POST;

        // Total Weight is the PACKAGE weight — the staff-entered
        // package_total_weight, stored as the order's total_weight. Item weights
        // price the items — they do NOT make up the package weight — so they are
        // never summed here. Omitted when the package was never weighed.
        $weightUnit = trim((string) ($settings->weight_p ?? 'lb'));
        if ($weightUnit === '') { $weightUnit = 'lb'; }
        $ptw = (isset($post['package_total_weight']) && $post['package_total_weight'] !== '')
            ? (float) $post['package_total_weight'] : 0.0;
        if ($ptw > 0) {
            $lines[] = '• Total Weight: ' . (0 + round($ptw, 2)) . ' ' . $weightUnit;
        } elseif (!empty($ph['[WEIGHT]']) && $ph['[WEIGHT]'] !== 'N/A') {
            $lines[] = '• Total Weight: ' . $ph['[WEIGHT]'];
        }

        // Contents = items + quantities (no pieces count, no dimensions).
        $contents = array();
        if (isset($post['packages'])) {
            $pkgs = json_decode($post['packages']);
            if (is_array($pkgs) && count($pkgs) > 0) {
                foreach ($pkgs as $p) {
                    $qty = max(1, (int) ($p->qty ?? 1));
                    $desc = trim((string) ($p->description ?? ''));
                    if ($desc !== '') {
                        $contents[] = $qty . ' x ' . $desc;
                    }
                }
            }
        }
        if ($contents) {
            $lines[] = '• Contents: ' . implode('; ', $contents);
        } elseif (!empty($ph['[ITEMS]']) && $ph['[ITEMS]'] !== 'N/A') {
            $lines[] = '• Contents: ' . implode('; ', explode("\n", $ph['[ITEMS]']));
        }

        $pt = trim((string) ($post['tracking_number'] ?? ''));
        if ($pt === '' || $pt === '0') {
            $pt = (!empty($ph['[POSTAL_TRACKING]']) && $ph['[POSTAL_TRACKING]'] !== 'N/A') ? $ph['[POSTAL_TRACKING]'] : '';
        }
        if ($pt !== '' && $pt !== '0') {
            $lines[] = '• Carrier Tracking #: *' . $pt . '*';
        }

        $eta = trim((string) ($post['estimated_eta'] ?? ''));
        if ($eta === '') {
            $eta = (!empty($ph['[ETA]']) && $ph['[ETA]'] !== 'N/A') ? $ph['[ETA]'] : '';
        }
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
            '[CUSTOMER_FULLNAME]' => cdp_nameWithLocker($sender),
            '[TRACKING_NUMBER]'   => $tracking,
            '[CURR_STATUS]'       => (string) $statusLabel,
            '[APP_URL]'           => $appUrl,
            '[COMPANY_NAME]'      => !empty($settings->site_name) ? $settings->site_name : 'Our Company',
        ]);
        if ($body === null) {
            return ['success' => false, 'skipped' => true, 'message' => 'WhatsApp template 11 not found.'];
        }
        cdp_msgSetContext(['template_id' => 11, 'entity_type' => 'shipment', 'entity_label' => (string) $tracking, 'subject' => 'Status update: ' . $statusLabel]);
        $res = sendNotificationWhatsApp_v2($sender, $body);
        cdp_msgClearContext(['template_id', 'entity_type', 'entity_label', 'subject']);
        return $res;
    }
}

if (!function_exists('cdp_notifyConsolidationPackageSenders')) {
    /**
     * Fan-out: every new status of a consolidation is sent to the owner of every
     * package inside it, by WhatsApp AND e-mail. The consolidation status has
     * display priority over the package's own status, so this IS the package
     * update — except for packages that already left the consolidation.
     *
     * The customer is told about THEIR PACKAGE only. Nothing that identifies the
     * consolidation goes out: no consolidation number, no air waybill, no flight,
     * no other customer's package. ($consolidationTracking is used for the
     * message log, which staff read, and nowhere else.) No money either.
     *
     * One message per owner per channel: an owner with several packages in the
     * consolidation gets them listed together instead of one message each.
     *
     * Who is left out:
     *   - a package whose newest consolidation is a different one (the detail
     *     table keeps old rows; is_consolidate is not trusted, it drifts);
     *   - a package that was handed over on its own: 8 Delivered, 15 Picked up,
     *     16 Not Picked Up, 21 Cancelled, 27 Returned to Vendor, 32 Ready for
     *     PickUp, 35 Auction (cdp_statusLeavesConsolidation) — UNLESS that own
     *     status is the very status being announced. Sorting / Ready for PickUp
     *     are copied onto the packages before this runs, and treating that copy
     *     as "already left" meant nobody was ever told their package was ready.
     * NOTE: the per-order notify_whatsapp_sender flag is intentionally NOT a
     * blocker here — every existing row carries the column default (0), so it
     * has never represented a real opt-out decision.
     *
     * Callers are responsible for the actual-change guard (only call when the
     * consolidation's status really changed) and must never call this for
     * money/invoice-only events. As a backstop against the same update arriving
     * twice (double-bound handlers, double clicks), a package that already got
     * this status announced in the last 10 minutes is left out.
     *
     * The sends run after the response (helpers/after_response.php): a
     * consolidation holds well over a hundred packages and the staff member who
     * changed the status must not wait for that many round trips. The outcome
     * of each send is in the message log (source "consolidation_update").
     *
     * @param string     $module                'consolidate' (air) | 'consolidate_packages' (sea)
     * @param int        $consolidate_id
     * @param string     $consolidationTracking c_prefix . c_no — message log only
     * @param string     $statusLabel           e.g. "Consolidated", "In_Transit", "Delivered"
     * @param array|null $onlyOrderIds          restrict to these package order_ids (e.g. newly added)
     * @param bool       $applySkipRules        false when the caller already filtered $onlyOrderIds
     *                                          by PRIOR package state
     * @return array ['sent' => int owners queued, 'skipped' => int packages left out]
     */
    function cdp_notifyConsolidationPackageSenders($module, $consolidate_id, $consolidationTracking, $statusLabel, $onlyOrderIds = null, $applySkipRules = true)
    {
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
        $isPackage   = ($module === 'consolidate_packages');
        $statusLabel = (string) $statusLabel;
        $statusKey   = strtolower(trim($statusLabel));

        $db = new Conexion;
        $db->cdp_query("SELECT d.order_id, o.order_prefix, o.order_no, o.sender_id, o.status_courier, s.mod_style AS own_status
            FROM {$detailTable} d
            INNER JOIN {$ordersTable} o ON o.order_id = d.order_id
            LEFT JOIN cdb_styles s ON s.id = o.status_courier
            WHERE d.consolidate_id = :cid
            ORDER BY o.order_id");
        $db->bind(':cid', (int) $consolidate_id);
        $db->cdp_execute();
        $packages = (array) $db->cdp_registros();

        if ($applySkipRules && function_exists('cdp_prefetchConsolidations')) {
            $nos = array();
            foreach ($packages as $pkg) {
                $nos[] = $pkg->order_no;
            }
            cdp_prefetchConsolidations($nos, $isPackage);   // one query, not one per package
        }

        $only    = ($onlyOrderIds === null) ? null : array_map('intval', (array) $onlyOrderIds);
        $skipped = 0;
        $byOwner = array();

        foreach ($packages as $pkg) {
            if ($only !== null && !in_array((int) $pkg->order_id, $only, true)) {
                continue;
            }
            if ((int) $pkg->sender_id <= 0) {
                $skipped++;
                continue;
            }
            if ($applySkipRules) {
                $current = function_exists('cdp_getConsolidationOf') ? cdp_getConsolidationOf($pkg->order_no, $isPackage) : null;
                if ($current && isset($current->consolidate_id) && (int) $current->consolidate_id !== (int) $consolidate_id) {
                    $skipped++;   // lives in a newer consolidation now
                    continue;
                }
                // Only Sorting (33) / Ready for PickUp (32) are copied onto the
                // packages (cdp_propagateConsolidationStatusToPackages). A package
                // delivered on its own earlier is not told "Delivered" again.
                $announcedIsOwn = in_array((int) $pkg->status_courier, array(32, 33), true)
                    && strtolower(trim((string) $pkg->own_status)) === $statusKey;
                $left = function_exists('cdp_statusLeavesConsolidation')
                    ? cdp_statusLeavesConsolidation($pkg->status_courier, $pkg->order_no, $isPackage)
                    : in_array((int) $pkg->status_courier, array(8, 15, 16, 21, 27, 32, 35), true);
                if ($left && !$announcedIsOwn) {
                    $skipped++;
                    continue;
                }
            }
            if (cdp_consolidationUpdateAlreadySent((int) $pkg->order_id, $statusLabel)) {
                $skipped++;
                continue;
            }
            $byOwner[(int) $pkg->sender_id][] = $pkg;
        }

        if (!$byOwner) {
            return array('sent' => 0, 'skipped' => $skipped);
        }

        $phModule = $isPackage ? 'sea' : 'air';
        $msgBatch = function_exists('cdp_msgNewBatchId') ? cdp_msgNewBatchId('con') : '';
        $logLabel = (string) $consolidationTracking;

        $deliver = function () use ($byOwner, $statusLabel, $phModule, $msgBatch, $logLabel, $consolidate_id) {
            @set_time_limit(0);
            foreach ($byOwner as $ownerId => $pkgs) {
                try {
                    cdp_notifyOwnerOfConsolidationUpdate((int) $ownerId, $pkgs, $statusLabel, $phModule, $msgBatch, (int) $consolidate_id, $logLabel);
                } catch (\Throwable $e) {
                    cdp_wa_log('fan-out error for owner ' . $ownerId . ': ' . $e->getMessage());
                }
            }
        };

        require_once __DIR__ . '/after_response.php';
        cdp_afterResponse($deliver);

        return array('sent' => count($byOwner), 'skipped' => $skipped);
    }
}

if (!function_exists('cdp_consolidationUpdateAlreadySent')) {
    /**
     * Was this status already announced for this package in the last 10 minutes?
     * Reads the message log; without the log table there is no backstop.
     */
    function cdp_consolidationUpdateAlreadySent($order_id, $statusLabel)
    {
        if (!function_exists('cdp_msgTableReady') || !cdp_msgTableReady()) {
            return false;
        }
        $db = new Conexion;
        $db->cdp_query("SELECT 1 AS x FROM cdb_message_log
            WHERE source = 'consolidation_update' AND entity_type = 'shipment'
              AND FIND_IN_SET(:oid, entity_id) > 0 AND subject = :subj
              AND created_at >= :since LIMIT 1");
        $db->bind(':oid', (string) (int) $order_id);
        $db->bind(':subj', cdp_consolidationUpdateSubject($statusLabel));
        $db->bind(':since', date('Y-m-d H:i:s', strtotime(cdp_msgNow()) - 600));
        $db->cdp_execute();
        return (bool) $db->cdp_registro();
    }

    /** Subject of the e-mail and of both message-log rows. Carries no consolidation info. */
    function cdp_consolidationUpdateSubject($statusLabel)
    {
        return 'Shipment Update: ' . str_replace('_', ' ', (string) $statusLabel);
    }
}

if (!function_exists('cdp_notifyOwnerOfConsolidationUpdate')) {
    /**
     * One owner, all of their packages in the consolidation: WhatsApp
     * (template 11 "Package Status") and e-mail (template 36 "Shipment Update").
     * Both templates speak about the shipment only.
     *
     * @param object[] $pkgs rows with order_id, order_prefix, order_no
     */
    function cdp_notifyOwnerOfConsolidationUpdate($ownerId, array $pkgs, $statusLabel, $phModule, $msgBatch, $consolidate_id, $logLabel)
    {
        $owner = cdp_getSenderCourier((int) $ownerId);
        if (!$owner) {
            return;
        }
        require_once __DIR__ . '/notify_placeholders.php';
        $settings   = cdp_getSettingsCourier();
        $siteUrl    = rtrim((string) ($settings->site_url ?? ''), '/');
        $siteName   = !empty($settings->site_name) ? (string) $settings->site_name : 'Our Company';
        $statusText = str_replace('_', ' ', (string) $statusLabel);
        $subject    = cdp_consolidationUpdateSubject($statusLabel);

        $trackings = array();
        $orderIds  = array();
        $waBlocks  = array();
        $htmlBlocks = '';
        foreach ($pkgs as $pkg) {
            $tracking    = $pkg->order_prefix . $pkg->order_no;
            $trackings[] = $tracking;
            $orderIds[]  = (int) $pkg->order_id;
            $ph = cdp_buildPackageNotifyPlaceholders((int) $pkg->order_id, $phModule);

            // This package's own details (no money): weight, items, carrier tracking, ETA.
            $lines = array();
            if (count($pkgs) > 1) {
                $lines[] = '*' . $tracking . '*';
            }
            if ($ph['[WEIGHT]'] !== 'N/A') {
                $lines[] = '• Total Weight: ' . $ph['[WEIGHT]'];
            }
            if ($ph['[ITEMS]'] !== 'N/A') {
                $lines[] = '• Items:';
                foreach (explode("\n", $ph['[ITEMS]']) as $il) {
                    $lines[] = '   - ' . $il;
                }
            }
            if ($ph['[POSTAL_TRACKING]'] !== 'N/A') {
                $lines[] = '• Carrier Tracking #: *' . $ph['[POSTAL_TRACKING]'] . '*';
            }
            if ($ph['[ETA]'] !== 'N/A') {
                $lines[] = '• Estimated Arrival: ' . $ph['[ETA]'];
            }
            if (count($pkgs) > 1) {
                $lines[] = '• Track: ' . $siteUrl . '/track.php?order_track=' . rawurlencode($tracking);
            }
            if ($lines) {
                $waBlocks[] = implode("\n", $lines);
            }

            if (count($pkgs) > 1) {
                $htmlBlocks .= '<p style="margin:16px 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;font-family:Roboto,Arial,Helvetica,sans-serif;">'
                    . htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $htmlBlocks .= $ph['[SHIPMENT_DETAILS]'];
        }
        $trackingList = implode(', ', $trackings);
        $trackUrl     = $siteUrl . '/track.php?order_track=' . rawurlencode($trackings[0]);

        $ctx = array(
            'source'            => 'consolidation_update',
            // Staff-only: lets Message Logs show which consolidation caused the send.
            'source_label'      => 'Consolidation Update (Package Owners) · ' . $logLabel,
            'batch_id'          => $msgBatch,
            'entity_type'       => 'shipment',
            'entity_id'         => implode(',', $orderIds),
            'entity_label'      => $trackingList,
            'subject'           => $subject,
            'recipient_user_id' => (int) $ownerId,
            'recipient_name'    => trim((string) ($owner->fname ?? '') . ' ' . (string) ($owner->lname ?? '')),
        );
        $ctxKeys = array_merge(array_keys($ctx), array('template_id'));

        // ── WhatsApp ────────────────────────────────────────────────────────
        cdp_msgSetContext($ctx + array('template_id' => 11));
        try {
            cdp_wa_requireV2();
            $body = cdp_renderWhatsAppTemplate(11, array(
                '[CUSTOMER_FULLNAME]' => cdp_nameWithLocker($owner),
                '[TRACKING_NUMBER]'   => $trackingList,
                '[CURR_STATUS]'       => $statusText,
                '[APP_URL]'           => $trackUrl,
                '[COMPANY_NAME]'      => $siteName,
            ));
            if ($body === null) {
                cdp_msgLog(array('channel' => 'whatsapp', 'status' => 'failed', 'status_detail' => 'WhatsApp template 11 not found.',
                    'recipient_to' => (string) ($owner->phone ?? ''), 'body' => ''));
            } else {
                if ($waBlocks) {
                    $body .= "\n\n" . implode("\n\n", $waBlocks);
                }
                $res = sendNotificationWhatsApp_v2($owner, $body);   // logs sent / failed / skipped itself
                if (empty($res['success'])) {
                    cdp_wa_log("fan-out skip/fail for {$trackingList}: " . ($res['message'] ?? ''));
                }
            }
        } catch (\Throwable $e) {
            cdp_wa_log('fan-out WhatsApp error for ' . $trackingList . ': ' . $e->getMessage());
        }

        // ── E-mail ──────────────────────────────────────────────────────────
        cdp_msgSetContext(array('template_id' => 36));
        $to = trim((string) ($owner->email ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            cdp_msgLog(array('channel' => 'email', 'status' => 'skipped', 'status_detail' => 'No valid e-mail address.',
                'recipient_to' => $to, 'body' => ''));
        } else {
            try {
                require_once __DIR__ . '/phpmailer/class.phpmailer.php';
                require_once __DIR__ . '/phpmailer/class.smtp.php';
                $statusRow = '<tr><td width="35%" style="font-size:13px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;">Current Status</td>'
                    . '<td style="font-size:13px;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;"><strong style="color:#1a1a1a;">'
                    . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</strong></td></tr>';
                $res = cdp_sendTemplateEmail(36, $to, array(
                    '[NAME]'             => htmlspecialchars($ctx['recipient_name'], ENT_QUOTES, 'UTF-8'),
                    '[TRACKING]'         => htmlspecialchars($trackingList, ENT_QUOTES, 'UTF-8'),
                    '[CHANGED_FIELDS]'   => $statusRow,
                    '[PACKAGES_SECTION]' => $htmlBlocks,
                    '[TOTAL_AMOUNT]'     => '',   // no monetary values in customer notifications
                    '[URL_SHIP]'         => $trackUrl,
                ), $subject);
                if (empty($res['ok'])) {
                    cdp_wa_log("fan-out e-mail failed for {$trackingList}: " . ($res['error'] ?? ''));
                    if (strpos((string) ($res['error'] ?? ''), 'SMTP send error') !== 0) {
                        // PHPMailer logs its own failures; PHP mail() and template errors do not.
                        cdp_msgLog(array('channel' => 'email', 'status' => 'failed', 'status_detail' => (string) ($res['error'] ?? 'Send failed'),
                            'recipient_to' => $to, 'body' => ''));
                    }
                } elseif (($settings->mailer ?? '') === 'PHP') {
                    cdp_msgLog(array('channel' => 'email', 'status' => 'sent', 'status_detail' => 'Accepted by PHP mail()',
                        'recipient_to' => $to, 'body' => ''));
                }
            } catch (\Throwable $e) {
                cdp_wa_log('fan-out e-mail error for ' . $trackingList . ': ' . $e->getMessage());
            }
        }

        cdp_msgClearContext($ctxKeys);
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
                cdp_nameWithLocker($sender),
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
