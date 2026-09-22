<?php
/**
 * ============================================================================
 * Consolidation reference — how a consolidation is named to people.
 *
 * A consolidation has two numbers: the AIR WAYBILL (cdb_consolidate.awb_no,
 * the airline's document number, what the customer and the airline recognise)
 * and the system code (c_prefix + c_no, e.g. CONSGL000140). Wherever a
 * consolidation is shown, the waybill comes FIRST and the code follows it;
 * with no waybill on record only the code shows.
 *
 *   cdp_consolidationRef($row|$code, $module)      plain text — Excel, e-mail,
 *                                                  WhatsApp, PDF, <title>, JSON
 *   cdp_consolidationRefHtml($row|$code, $module)  markup for pages and lists
 *   cdp_consolidationAwb($row|$code, $module)      the waybill alone, '' if none
 *   cdp_awbNormalize($raw)                         what the forms store
 *
 * Callers with a row hand it over (any object carrying c_prefix/c_no and,
 * when the SELECT included it, awb_no). Callers that only have the code — the
 * tracking pages, the Consolidated badge, notifications — hand the code over
 * and the waybill is looked up from a per-request table of every consolidation
 * that has one (one query, both tables, a few hundred rows).
 *
 * Before sql/consolidation_awb.sql has been run the column is absent; the
 * helper then reports no waybill anywhere and the forms hide the field, so
 * every page keeps working.
 * ============================================================================
 */

require_once __DIR__ . '/querys.php';

if (!function_exists('cdp_awbColumnReady')) {
    /** Has sql/consolidation_awb.sql been applied? Cached per request. */
    function cdp_awbColumnReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = false;
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'awb_no'
                  AND TABLE_NAME IN ('cdb_consolidate', 'cdb_consolidate_packages')");
            $r = $db->cdp_registro();
            $ready = $r && (int) $r->n === 2;
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('cdp_awbNormalize')) {
    /**
     * Clean a typed waybill: trimmed, upper-case, single spaces. An IATA
     * number typed as 11 bare digits is stored in its printed form
     * (prefix-serial, e.g. 006-12345678).
     */
    function cdp_awbNormalize($raw)
    {
        $s = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $raw)));
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) === 11 && preg_match('/^[\d\s-]+$/', $s)) {
            $s = substr($digits, 0, 3) . '-' . substr($digits, 3);
        }
        return mb_substr($s, 0, 40);
    }
}

if (!function_exists('cdp_awbTable')) {
    /**
     * Every consolidation that has a waybill, keyed by code and by id, for the
     * callers that only know one of those. Loaded once per request.
     *
     * @return array{code:array<string,string>, consolidate:array<int,string>, consolidate_packages:array<int,string>}
     */
    function &cdp_awbTable()
    {
        static $table = null;
        if ($table !== null) {
            return $table;
        }
        $table = array('code' => array(), 'consolidate' => array(), 'consolidate_packages' => array());
        if (!cdp_awbColumnReady()) {
            return $table;
        }
        foreach (array('consolidate', 'consolidate_packages') as $module) {
            try {
                $db = new Conexion;
                $db->cdp_query("SELECT consolidate_id, c_prefix, c_no, awb_no FROM cdb_{$module} WHERE awb_no <> ''");
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $r) {
                    $awb = trim((string) $r->awb_no);
                    $table[$module][(int) $r->consolidate_id] = $awb;
                    $table['code'][(string) ($r->c_prefix . $r->c_no)] = $awb;
                }
            } catch (Throwable $e) {
                // no waybills for this module
            }
        }
        return $table;
    }
}

if (!function_exists('cdp_awbForget')) {
    /** Drop the per-request table after a form saved a waybill. */
    function cdp_awbForget()
    {
        $t = &cdp_awbTable();
        $t = null;
    }
}

if (!function_exists('cdp_consolidationCode')) {
    /** The system code of a row (c_prefix + c_no), or the string given. */
    function cdp_consolidationCode($rowOrCode)
    {
        if (is_object($rowOrCode)) {
            return trim((string) (($rowOrCode->c_prefix ?? '') . ($rowOrCode->c_no ?? '')));
        }
        if (is_array($rowOrCode)) {
            return trim((string) (($rowOrCode['c_prefix'] ?? '') . ($rowOrCode['c_no'] ?? '')));
        }
        return trim((string) $rowOrCode);
    }
}

if (!function_exists('cdp_consolidationAwb')) {
    /**
     * The waybill of a consolidation, '' when it has none.
     *
     * @param object|array|string $rowOrCode row (awb_no used when present, else
     *                                       looked up by id/code) or the code
     * @param string              $module    'consolidate' | 'consolidate_packages'
     */
    function cdp_consolidationAwb($rowOrCode, $module = 'consolidate')
    {
        if (is_object($rowOrCode) && isset($rowOrCode->awb_no)) {
            return trim((string) $rowOrCode->awb_no);
        }
        if (is_array($rowOrCode) && array_key_exists('awb_no', $rowOrCode)) {
            return trim((string) $rowOrCode['awb_no']);
        }
        $t = &cdp_awbTable();
        $id = is_object($rowOrCode) ? (int) ($rowOrCode->consolidate_id ?? 0)
            : (is_array($rowOrCode) ? (int) ($rowOrCode['consolidate_id'] ?? 0) : 0);
        if ($id > 0 && isset($t[$module][$id])) {
            return $t[$module][$id];
        }
        $code = cdp_consolidationCode($rowOrCode);
        return ($code !== '' && isset($t['code'][$code])) ? $t['code'][$code] : '';
    }
}

if (!function_exists('cdp_consolidationRef')) {
    /**
     * Plain-text reference: "AWB 006-12345678 · CONSGL000140", or the code
     * alone when there is no waybill.
     */
    function cdp_consolidationRef($rowOrCode, $module = 'consolidate')
    {
        $code = cdp_consolidationCode($rowOrCode);
        $awb  = cdp_consolidationAwb($rowOrCode, $module);
        if ($awb === '') {
            return $code;
        }
        return 'AWB ' . $awb . ($code !== '' ? ' · ' . $code : '');
    }
}

if (!function_exists('cdp_consolidationRefHtml')) {
    /**
     * Markup: the waybill as the headline, the code after it in a muted run.
     * Styled by .swl-awbref in swiftlane-ds.css; $inline keeps both on one
     * line for table cells and titles, $inline = false stacks them (cards).
     */
    function cdp_consolidationRefHtml($rowOrCode, $module = 'consolidate', $inline = true)
    {
        $e    = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $code = cdp_consolidationCode($rowOrCode);
        $awb  = cdp_consolidationAwb($rowOrCode, $module);
        if ($awb === '') {
            return '<span class="swl-awbref swl-awbref--code-only">' . $e($code) . '</span>';
        }
        // Literal spaces and the separator are part of the markup so the text
        // reads "AWB 006-12345678 · CONSGL000140" wherever the stylesheet is not
        // loaded (PDF, e-mail, Excel); the stylesheet only tidies the spacing.
        return '<span class="swl-awbref' . ($inline ? '' : ' swl-awbref--stack') . '">'
            . '<span class="swl-awbref__awb"><span class="swl-awbref__tag">AWB</span> ' . $e($awb) . '</span>'
            . ($code !== '' ? ' <span class="swl-awbref__code"><span class="swl-awbref__sep">· </span>' . $e($code) . '</span>' : '')
            . '</span>';
    }
}
