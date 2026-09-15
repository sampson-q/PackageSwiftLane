<?php
// Consolidation status inheritance (cdp_effectiveStatusSql) lives in
// helpers/querys.php; the guard covers callers that have not loaded it.
if (!function_exists('cdp_effectiveStatusSql')) {
    require_once __DIR__ . '/querys.php';
}
// ============================================================================
// Shared control-panel data + presentation helpers.
//
// Every dashboard pulls its figures through here so the numbers can never
// drift between panels:
//   - MONEY comes only from the Financial Sheet ledger
//     (cdb_consolidate_customer_billing + cdb_fs_payments net of refunds),
//     the same queries the Financial Sheet / Transactions / Receivables
//     pages run — so every panel tallies with them.
//   - COUNT series come from single GROUP BY queries (not 12 per-month
//     round trips like the legacy graphics endpoints).
//   - Status breakdowns use the cdb_styles vocabulary (label + colour), so
//     a new status appears on the charts without touching any panel.
//
// Presentation helpers render the shared KPI-tile / chart-card markup that
// swiftlane-ds.css styles, and cdp_dashChartsRender() hands chart
// configs to dataJs/dashboard_charts.js (ApexCharts).
// ============================================================================

require_once(__DIR__ . '/fs_status.php');

if (!function_exists('cdp_dashMonthLabels')) {
    function cdp_dashMonthLabels()
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }
}

if (!function_exists('cdp_dashMonthlySeries')) {
    /**
     * 12-month series (current year) for COUNT(*) or an aggregate expression.
     * $table/$dateCol/$expr/$where are internal literals, never user input.
     *
     * @return float[] index 0 = January
     */
    function cdp_dashMonthlySeries($table, $dateCol, $expr = 'COUNT(*)', $where = '')
    {
        $out = array_fill(0, 12, 0.0);
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT MONTH($dateCol) m, $expr t FROM $table
                            WHERE YEAR($dateCol) = YEAR(CURDATE()) $where
                            GROUP BY MONTH($dateCol)");
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $r) {
                $idx = (int) $r->m - 1;
                if ($idx >= 0 && $idx < 12) {
                    $out[$idx] = round((float) $r->t, 2);
                }
            }
        } catch (Throwable $e) { /* table absent — flat series */ }
        return $out;
    }
}

if (!function_exists('cdp_dashFsMonthly')) {
    /**
     * Financial Sheet money by month (current year, USD):
     *   billed   — USD snapshot on the billing ledger
     *   received — payments net of refunds, converted via each row's own rate
     *
     * @return array{billed: float[], received: float[]}
     */
    function cdp_dashFsMonthly($senderId = null)
    {
        $own = $senderId !== null ? (' AND sender_id = ' . (int) $senderId) : '';
        return [
            'billed'   => cdp_dashMonthlySeries('cdb_consolidate_customer_billing', 'billed_at', 'COALESCE(SUM(amount_usd),0)', $own),
            'received' => cdp_dashMonthlySeries(
                'cdb_fs_payments',
                'recorded_at',
                'COALESCE(SUM(' . cdp_fsMoneyExpr() . '/NULLIF(exchange_rate,0)),0)',
                ' AND ' . cdp_fsMoneySqlFilter() . $own
            ),
        ];
    }
}

if (!function_exists('cdp_dashFsTotals')) {
    /**
     * Headline Financial Sheet figures (USD) — the EXACT queries the
     * Transactions Control Panel and Financial Overview run:
     *   billed_month / received_month — current calendar month
     *   outstanding                   — all-time balance still owed
     *
     * @return array{billed_month: float, received_month: float, outstanding: float}
     */
    function cdp_dashFsTotals($senderId = null)
    {
        $t = ['billed_month' => 0.0, 'received_month' => 0.0, 'outstanding' => 0.0];
        $own = $senderId !== null ? (' AND sender_id = ' . (int) $senderId) : '';
        try {
            $db = new Conexion;
            $ini = date('Y-m-01 00:00:00');
            $fin = date('Y-m-t 23:59:59');

            $db->cdp_query("SELECT COALESCE(SUM(amount_usd),0) t FROM cdb_consolidate_customer_billing
                            WHERE billed_at BETWEEN :i AND :f" . $own);
            $db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
            $t['billed_month'] = (float) ($db->cdp_registro()->t ?? 0);

            $db->cdp_query("SELECT COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) t
                            FROM cdb_fs_payments
                            WHERE recorded_at BETWEEN :i AND :f AND " . cdp_fsMoneySqlFilter() . $own);
            $db->bind(':i', $ini); $db->bind(':f', $fin); $db->cdp_execute();
            $t['received_month'] = (float) ($db->cdp_registro()->t ?? 0);

            $db->cdp_query("SELECT COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0) t
                            FROM cdb_consolidate_customer_billing WHERE 1=1" . $own);
            $db->cdp_execute();
            $t['outstanding'] = (float) ($db->cdp_registro()->t ?? 0);
        } catch (Throwable $e) { /* FS migration not run — zeros */ }
        return $t;
    }
}

if (!function_exists('cdp_dashCount')) {
    /** One guarded COUNT(*). $where is an internal literal. */
    function cdp_dashCount($table, $where = '')
    {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) t FROM $table WHERE 1=1 $where");
            $db->cdp_execute();
            return (int) ($db->cdp_registro()->t ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('cdp_dashStatusBreakdown')) {
    /**
     * Rows grouped by status_courier joined to the cdb_styles vocabulary.
     * Statuses missing from the vocabulary are folded into "Other".
     *
     * Shipments and packages are grouped by their EFFECTIVE status — a row
     * inside a consolidation counts under the consolidation's status, which is
     * what every screen shows for it. Consolidations themselves have no parent,
     * so they group by their own.
     *
     * @return array{labels: string[], colors: string[], totals: int[]}
     */
    function cdp_dashStatusBreakdown($table, $where = '', $limit = 8)
    {
        $inherits = array(
            'cdb_add_order'          => false,
            'cdb_customers_packages' => true,
        );
        $statusExpr = 'o.status_courier';
        if (isset($inherits[$table]) && function_exists('cdp_effectiveStatusSql')) {
            $statusExpr = cdp_effectiveStatusSql('o', $inherits[$table]);
        }

        $labels = $colors = $totals = [];
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT $statusExpr sc, COALESCE(s.mod_style, 'Other') lbl,
                                   COALESCE(s.color, '#94a3b8') col, COUNT(*) t
                            FROM $table o LEFT JOIN cdb_styles s ON s.id = $statusExpr
                            WHERE 1=1 $where
                            GROUP BY sc, lbl, col
                            ORDER BY t DESC");
            $db->cdp_execute();
            // Merge by display label (several unknown status ids all fold into
            // one "Other" slice), then cap at $limit + an overflow bucket.
            $byLabel = [];
            foreach ((array) $db->cdp_registros() as $r) {
                $lbl = ucwords(str_replace('_', ' ', (string) $r->lbl));
                if (!isset($byLabel[$lbl])) {
                    $byLabel[$lbl] = ['col' => (string) $r->col, 't' => 0];
                }
                $byLabel[$lbl]['t'] += (int) $r->t;
            }
            uasort($byLabel, function ($a, $b) { return $b['t'] <=> $a['t']; });
            $other = 0;
            foreach ($byLabel as $lbl => $d) {
                if ($lbl === 'Other' || count($labels) >= $limit) {
                    $other += $d['t'];
                    continue;
                }
                $labels[] = $lbl;
                $colors[] = $d['col'];
                $totals[] = $d['t'];
            }
            if ($other > 0) {
                $labels[] = 'Other';
                $colors[] = '#94a3b8';
                $totals[] = $other;
            }
        } catch (Throwable $e) { /* leave empty */ }
        return ['labels' => $labels, 'colors' => $colors, 'totals' => $totals];
    }
}

// ---------------------------------------------------------------------------
// Presentation helpers
// ---------------------------------------------------------------------------

if (!function_exists('cdp_dashKpi')) {
    /**
     * One KPI tile. $opts:
     *   icon   Iconify name            label  short Title Case caption
     *   value  pre-formatted string    href   optional link (tile is clickable)
     *   accent hex colour              sub    optional small note under label
     *   col    grid classes (default 'col-6 col-md-4 col-xl-3')
     */
    function cdp_dashKpi(array $opts)
    {
        // Design-system MetricCard (see assets/css_main_swiftlane/css/swiftlane-ds.css
        // §11): label + Archivo Black figure on the left, a ring icon circle on the
        // right, an optional caption row underneath. `tone => 'inverse'` renders the
        // ink card the design uses for one highlighted figure per row. The old
        // `accent` option is accepted and ignored: icons are ink in this system.
        $icon   = $opts['icon']   ?? 'solar:box-minimalistic-linear';
        $label  = $opts['label']  ?? '';
        $value  = $opts['value']  ?? '0';
        $href   = $opts['href']   ?? '';
        $sub    = $opts['sub']    ?? '';
        $tone   = ($opts['tone'] ?? 'light') === 'inverse' ? 'inverse' : 'light';
        $col    = $opts['col']    ?? 'col-6 col-md-4 col-xl-3';
        $delta  = $opts['delta']  ?? '';            // e.g. '12%'
        $dir    = ($opts['direction'] ?? 'up') === 'down' ? 'down' : 'up';

        $tag  = $href !== '' ? 'a' : 'div';
        $attr = $href !== '' ? ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' : '';
        $foot = '';
        if ($delta !== '' || $sub !== '') {
            $foot = '<span class="swl-metric__foot">'
                  . ($delta !== '' ? '<span class="swl-delta swl-delta--' . $dir . '">' . ($dir === 'up' ? '&#9650;' : '&#9660;') . ' ' . htmlspecialchars((string) $delta, ENT_QUOTES, 'UTF-8') . '</span>' : '')
                  . ($sub !== '' ? '<span>' . htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') . '</span>' : '')
                  . '</span>';
        }
        echo '<div class="' . $col . ' mb-3">'
           . '<' . $tag . $attr . ' class="swl-metric' . ($tone === 'inverse' ? ' swl-metric--inverse' : '') . '">'
           . '<div class="swl-metric__head">'
           . '<div class="swl-metric__text">'
           . '<span class="swl-metric__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
           . '<span class="swl-metric__value">' . $value . '</span>'
           . '</div>'
           . '<span class="swl-metric__icon"><iconify-icon icon="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></iconify-icon></span>'
           . '</div>'
           . $foot
           . '</' . $tag . '></div>';
    }
}

if (!function_exists('cdp_dashSectionTitle')) {
    function cdp_dashSectionTitle($icon, $text, $note = '')
    {
        // Section heading between panel rows (design: ds-title-md, 16/700).
        echo '<div class="col-12 swl-section">'
           . '<iconify-icon icon="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></iconify-icon>'
           . '<span>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>'
           . ($note !== '' ? '<small>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</small>' : '')
           . '</div>';
    }
}

if (!function_exists('cdp_dashChartCard')) {
    /** Opens/closes a chart card. Call with 'open' then 'close'. */
    function cdp_dashChartCard($mode, $id = '', $title = '', $note = '', $col = 'col-12 col-lg-6')
    {
        // Design-system Panel + PanelHeader wrapping an ApexCharts mount point.
        if ($mode === 'open') {
            echo '<div class="' . $col . ' mb-4"><div class="card sw-chart-card h-100 mb-0"><div class="card-body">'
               . '<div class="swl-panel__head"><div class="swl-panel__text">'
               . '<span class="swl-panel__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>'
               . ($note !== '' ? '<span class="swl-panel__note">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</span>' : '')
               . '</div></div>'
               . '<div id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="sw-chart"></div>';
        } else {
            echo '</div></div></div>';
        }
    }
}

if (!function_exists('cdp_dashChartsRender')) {
    /**
     * Emit the chart configs + the shared renderer scripts. Call once, at the
     * bottom of the page, with every chart the page shows:
     *   ['el'=>'#id','type'=>'area|bar|donut|line','series'=>...,'labels'=>[],
     *    'colors'=>[], 'money'=>bool, 'height'=>int]
     */
    function cdp_dashChartsRender(array $charts, $currency = '$')
    {
        echo '<script>window.cdpDashCharts = ' . json_encode($charts) . ';'
           . 'window.cdpDashCurrency = ' . json_encode((string) $currency) . ';</script>' . "\n";
        echo '<script src="' . cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') . '"></script>' . "\n";
        echo '<script src="' . cdp_asset('dataJs/dashboard_charts.js') . '"></script>' . "\n";
    }
}

// ============================================================================
// Control-panel components (Swift Lane Ops design). Data helpers first, then
// the renderers. Every data helper is guarded: a missing table or column
// returns an empty result and the panel simply renders nothing for it.
// ============================================================================

if (!function_exists('cdp_dashRows')) {
    /** Guarded SELECT returning an array of row objects. $sql is an internal literal. */
    function cdp_dashRows($sql)
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            $db->cdp_execute();
            return (array) $db->cdp_registros();
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('cdp_dashSum')) {
    /** One guarded SUM(expr). */
    function cdp_dashSum($table, $expr, $where = '')
    {
        $r = cdp_dashRows("SELECT COALESCE(SUM($expr),0) t FROM $table WHERE 1=1 $where");
        return (float) ($r[0]->t ?? 0);
    }
}

if (!function_exists('cdp_dashStatusCounts')) {
    /** status_courier => count for a set of status ids (missing ids come back as 0). */
    function cdp_dashStatusCounts($table, array $ids, $where = '')
    {
        $out = array_fill_keys($ids, 0);
        if (!$ids) { return $out; }
        $in = implode(',', array_map('intval', $ids));
        foreach (cdp_dashRows("SELECT status_courier sc, COUNT(*) t FROM $table WHERE status_courier IN ($in) $where GROUP BY sc") as $r) {
            $out[(int) $r->sc] = (int) $r->t;
        }
        return $out;
    }
}

if (!function_exists('cdp_dashHourMatrix')) {
    /**
     * Rows registered per weekday x hour over the last $days days.
     * @return int[][] [weekday 0=Mon .. 6=Sun][hour 0..23]
     */
    function cdp_dashHourMatrix($table, $dtCol, $where = '', $days = 90)
    {
        $m = array_fill(0, 7, array_fill(0, 24, 0));
        $days = max(1, (int) $days);
        foreach (cdp_dashRows("SELECT WEEKDAY($dtCol) d, HOUR($dtCol) h, COUNT(*) t FROM $table
                               WHERE $dtCol >= NOW() - INTERVAL $days DAY $where GROUP BY d, h") as $r) {
            $d = (int) $r->d; $h = (int) $r->h;
            if ($d >= 0 && $d < 7 && $h >= 0 && $h < 24) { $m[$d][$h] = (int) $r->t; }
        }
        return $m;
    }
}

if (!function_exists('cdp_dashAgeBuckets')) {
    /**
     * Age buckets for rows still open: how long since $dtCol, in hours.
     * $edges = [24, 48] gives three buckets: <24h, 24-48h, 48h+.
     * @return int[] one count per bucket
     */
    function cdp_dashAgeBuckets($table, $dtCol, $where = '', array $edges = [24, 48])
    {
        $age  = "TIMESTAMPDIFF(HOUR, $dtCol, NOW())";
        $sel  = [];
        $prev = null;
        foreach ($edges as $i => $e) {
            $e = (int) $e;
            $sel[] = ($prev === null ? "SUM($age < $e)" : "SUM($age >= $prev AND $age < $e)") . " b$i";
            $prev = $e;
        }
        $sel[] = "SUM($age >= " . (int) $prev . ") b" . count($edges);
        $r = cdp_dashRows("SELECT " . implode(', ', $sel) . " FROM $table WHERE 1=1 $where");
        $out = [];
        for ($i = 0; $i <= count($edges); $i++) { $out[] = (int) ($r[0]->{"b$i"} ?? 0); }
        return $out;
    }
}

if (!function_exists('cdp_dashTopDestinations')) {
    /**
     * Destinations by shipment count, "City, Country" as recorded on the
     * shipment's own address snapshot (cdb_address_shipments). @return array<{lbl,t}>
     */
    function cdp_dashTopDestinations($where = '', $limit = 5)
    {
        return cdp_dashRows("SELECT COALESCE(NULLIF(CONCAT_WS(', ', NULLIF(TRIM(a.recipient_city),''), NULLIF(TRIM(a.recipient_country),'')), ''), 'Unknown') lbl, COUNT(*) t
                             FROM cdb_add_order o
                             LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id
                             WHERE 1=1 $where GROUP BY lbl ORDER BY t DESC LIMIT " . (int) $limit);
    }
}

if (!function_exists('cdp_dashTopSenders')) {
    /** Customers by shipment count. @return array<{sender_id,lbl,t}> */
    function cdp_dashTopSenders($where = '', $limit = 5)
    {
        return cdp_dashRows("SELECT o.sender_id, COALESCE(NULLIF(TRIM(u.company),''), TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')))) lbl, COUNT(*) t
                             FROM cdb_add_order o JOIN cdb_users u ON u.id = o.sender_id
                             WHERE 1=1 $where GROUP BY o.sender_id, lbl ORDER BY t DESC LIMIT " . (int) $limit);
    }
}

if (!function_exists('cdp_dashTopDrivers')) {
    /** Drivers by shipment count. @return array<{driver_id,lbl,t}> */
    function cdp_dashTopDrivers($where = '', $limit = 5)
    {
        return cdp_dashRows("SELECT o.driver_id, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) lbl, COUNT(*) t
                             FROM cdb_add_order o JOIN cdb_users u ON u.id = o.driver_id
                             WHERE o.driver_id > 0 $where GROUP BY o.driver_id, lbl ORDER BY t DESC LIMIT " . (int) $limit);
    }
}

if (!function_exists('cdp_dashByOffice')) {
    /** Shipments by origin office. @return array<{lbl,t}> */
    function cdp_dashByOffice($where = '', $limit = 6)
    {
        return cdp_dashRows("SELECT COALESCE(f.name_off, 'Unassigned') lbl, COUNT(*) t
                             FROM cdb_add_order o LEFT JOIN cdb_offices f ON f.id = o.origin_off
                             WHERE 1=1 $where GROUP BY lbl ORDER BY t DESC LIMIT " . (int) $limit);
    }
}

if (!function_exists('cdp_dashActivityFeed')) {
    /** Latest non-view entries of the audit trail. @return array<{time,text}> */
    function cdp_dashActivityFeed($limit = 6)
    {
        $out = [];
        foreach (cdp_dashRows("SELECT created_at, actor_name, summary, action_label, module FROM cdb_activity_log
                               WHERE verb <> 'view' ORDER BY id DESC LIMIT " . (int) $limit) as $r) {
            $ts   = strtotime((string) $r->created_at);
            $text = trim((string) ($r->summary ?: $r->action_label));
            $out[] = [
                'time' => $ts ? (date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('M j', $ts)) : '',
                'text' => trim((string) $r->actor_name) !== '' ? $r->actor_name . ' - ' . $text : $text,
            ];
        }
        return $out;
    }
}

if (!function_exists('cdp_dashLatestOrders')) {
    /** Most recent shipments with sender, weight, category and status. */
    function cdp_dashLatestOrders($where = '', $limit = 7)
    {
        return cdp_dashRows("SELECT o.order_id, o.order_prefix, o.order_no, o.order_datetime, o.order_date, o.total_weight,
                                    o.order_item_category, o.status_courier,
                                    COALESCE(NULLIF(TRIM(u.company),''), TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')))) sender,
                                    s.mod_style, s.color
                             FROM cdb_add_order o
                             LEFT JOIN cdb_users u ON u.id = o.sender_id
                             LEFT JOIN cdb_styles s ON s.id = o.status_courier
                             WHERE 1=1 $where ORDER BY o.order_id DESC LIMIT " . (int) $limit);
    }
}

if (!function_exists('cdp_dashPct')) {
    /** Percentage of $v against $max, clamped to 0..100 (bars). */
    function cdp_dashPct($v, $max)
    {
        return $max > 0 ? max(0, min(100, round(100 * $v / $max))) : 0;
    }
}

if (!function_exists('cdp_dashModeLabel')) {
    /** order_item_category -> Air / Sea label (26 = air, 27 = sea). */
    function cdp_dashModeLabel($cat)
    {
        $cat = (int) $cat;
        return $cat === 26 ? 'Air' : ($cat === 27 ? 'Sea' : 'Other');
    }
}

// ---------------------------------------------------------------------------
// Renderers (markup styled by swiftlane-ds.css section 13)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_dashEsc')) {
    function cdp_dashEsc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('cdp_dashPanel')) {
    /**
     * Panel shell. cdp_dashPanel('open', [...]) ... cdp_dashPanel('close').
     *   col    grid classes (default col-12 col-lg-4)   tone  'light' | 'inverse'
     *   title  14/700 heading                            value big Archivo figure
     *   note   caption under the title                   aside raw HTML on the right of the head
     *   class  extra classes on the card
     */
    function cdp_dashPanel($mode, array $o = [])
    {
        if ($mode !== 'open') { echo '</div></div></div>'; return; }
        $inverse = ($o['tone'] ?? 'light') === 'inverse';
        echo '<div class="' . ($o['col'] ?? 'col-12 col-lg-4') . ' mb-4">'
           . '<div class="card swl-panel' . ($inverse ? ' swl-panel--inverse' : '') . ' h-100 mb-0 ' . ($o['class'] ?? '') . '">'
           . '<div class="card-body">';
        if (!empty($o['title']) || !empty($o['aside'])) {
            echo '<div class="swl-panel__head"><div class="swl-panel__text">'
               . (!empty($o['title']) ? '<span class="swl-panel__title">' . cdp_dashEsc($o['title']) . '</span>' : '')
               . (isset($o['value']) && $o['value'] !== '' ? '<span class="swl-panel__value">' . $o['value'] . '</span>' : '')
               . (!empty($o['note']) ? '<span class="swl-panel__note">' . cdp_dashEsc($o['note']) . '</span>' : '')
               . '</div>' . ($o['aside'] ?? '') . '</div>';
        }
    }
}

if (!function_exists('cdp_dashBars')) {
    /** Horizontal bars. rows: [label, value(string), pct 0..100, color(css)]. */
    function cdp_dashBars(array $rows, $thick = false)
    {
        if (!$rows) { echo '<div class="swl-empty">No data yet</div>'; return; }
        echo '<div class="swl-bars' . ($thick ? ' swl-bars--thick' : '') . '">';
        foreach ($rows as $r) {
            echo '<div class="swl-bar"><div class="swl-bar__row"><span class="swl-bar__label">' . cdp_dashEsc($r[0]) . '</span>'
               . '<span class="swl-bar__value">' . cdp_dashEsc($r[1]) . '</span></div>'
               . '<div class="swl-bar__track"><div class="swl-bar__fill" style="width:' . (int) $r[2] . '%;background:' . cdp_dashEsc($r[3] ?? 'var(--accent)') . '"></div></div></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashKv')) {
    /** Hairline list rows. rows: [label, detail, value, dot colour|null, href|null]. */
    function cdp_dashKv(array $rows)
    {
        if (!$rows) { echo '<div class="swl-empty">Nothing to show</div>'; return; }
        echo '<div class="swl-kv">';
        foreach ($rows as $r) {
            $href = $r[4] ?? '';
            echo ($href !== '' ? '<a class="swl-kv__row" href="' . cdp_dashEsc($href) . '">' : '<div class="swl-kv__row">')
               . (!empty($r[3]) ? '<span class="swl-kv__dot" style="background:' . cdp_dashEsc($r[3]) . '"></span>' : '')
               . '<div class="swl-kv__text"><span class="swl-kv__label">' . cdp_dashEsc($r[0]) . '</span>'
               . ((isset($r[1]) && $r[1] !== '') ? '<span class="swl-kv__detail">' . cdp_dashEsc($r[1]) . '</span>' : '') . '</div>'
               . '<span class="swl-kv__value">' . $r[2] . '</span>'
               . ($href !== '' ? '</a>' : '</div>');
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashFeed')) {
    /** Time-stamped feed. rows: [time, text, amount|null]. */
    function cdp_dashFeed(array $rows)
    {
        if (!$rows) { echo '<div class="swl-empty">No activity recorded yet</div>'; return; }
        echo '<div class="swl-feed">';
        foreach ($rows as $r) {
            echo '<div class="swl-feed__row"><span class="swl-feed__time">' . cdp_dashEsc($r[0]) . '</span>'
               . '<span class="swl-feed__text">' . cdp_dashEsc($r[1]) . '</span>'
               . (isset($r[2]) && $r[2] !== '' ? '<span class="swl-feed__amount">' . $r[2] . '</span>' : '') . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashStats')) {
    /** Small figure tiles inside a panel. rows: [value, label]. */
    function cdp_dashStats(array $rows, $cols = 3)
    {
        echo '<div class="swl-stats swl-stats--' . (int) $cols . '">';
        foreach ($rows as $r) {
            echo '<div class="swl-stat"><span class="swl-stat__value">' . $r[0] . '</span><span class="swl-stat__label">' . cdp_dashEsc($r[1]) . '</span></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashLegend')) {
    /** Chart legend. items: [label, color, value]. */
    function cdp_dashLegend(array $items)
    {
        echo '<div class="swl-legend">';
        foreach ($items as $i) {
            echo '<div class="swl-legend__item"><span class="swl-legend__swatch" style="background:' . cdp_dashEsc($i[1]) . '"></span>'
               . '<span class="swl-legend__label">' . cdp_dashEsc($i[0]) . '</span>'
               . (isset($i[2]) ? '<span class="swl-legend__value">' . cdp_dashEsc($i[2]) . '</span>' : '') . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashPill')) {
    /** Status pill from a cdb_styles row (label + colour). */
    function cdp_dashPill($label, $color)
    {
        $label = ucwords(str_replace('_', ' ', (string) $label));
        return '<span class="label label-large swl-status" data-swl="1" style="--swl-status:' . cdp_dashEsc($color ?: '#94a3b8') . '">' . cdp_dashEsc($label) . '</span>';
    }
}

if (!function_exists('cdp_dashToggle')) {
    /** Segmented pill toggle. $options key => label; panes carry data-swl-pane="$id" data-swl-key="key". */
    function cdp_dashToggle($id, array $options, $active)
    {
        echo '<div class="swl-toggle" data-swl-toggle="' . cdp_dashEsc($id) . '">';
        foreach ($options as $k => $l) {
            echo '<button type="button" class="swl-toggle__btn' . ($k === $active ? ' is-active' : '') . '" data-swl-key="' . cdp_dashEsc($k) . '">' . cdp_dashEsc($l) . '</button>';
        }
        echo '</div>';
    }
}

if (!function_exists('cdp_dashBanner')) {
    /**
     * Amber full-width banner. $o: tag, title, note, stats [[icon, value, label]], btn [href, label].
     */
    function cdp_dashBanner(array $o)
    {
        echo '<div class="col-12 mb-4"><div class="swl-banner">'
           . '<div class="swl-banner__copy">'
           . (!empty($o['tag']) ? '<span class="swl-tag swl-tag--dark">' . cdp_dashEsc($o['tag']) . '</span>' : '')
           . '<span class="swl-banner__title">' . cdp_dashEsc($o['title']) . '</span>'
           . (!empty($o['note']) ? '<span class="swl-banner__note">' . cdp_dashEsc($o['note']) . '</span>' : '')
           . '</div>';
        if (!empty($o['stats'])) {
            echo '<div class="swl-banner__stats">';
            foreach ($o['stats'] as $s) {
                echo '<div class="swl-banner__stat"><span class="swl-metric__icon"><iconify-icon icon="' . cdp_dashEsc($s[0]) . '"></iconify-icon></span>'
                   . '<div><span class="swl-stat__value">' . $s[1] . '</span><span class="swl-stat__label">' . cdp_dashEsc($s[2]) . '</span></div></div>';
            }
            echo '</div>';
        }
        if (!empty($o['btn'])) {
            echo '<a href="' . cdp_dashEsc($o['btn'][0]) . '" class="btn btn-dark btn-sm">' . cdp_dashEsc($o['btn'][1]) . '</a>';
        }
        echo '</div></div>';
    }
}

if (!function_exists('cdp_dashRingsPanel')) {
    /**
     * Ink panel with concentric status rings + legend, from a
     * cdp_dashStatusBreakdown() result. Registers the chart in $charts.
     *   $o: col, title, value, note, tone (default inverse), height
     */
    function cdp_dashRingsPanel($id, array $bd, array &$charts, array $o = [])
    {
        $tone = $o['tone'] ?? 'inverse';
        cdp_dashPanel('open', ['col' => $o['col'] ?? 'col-12 col-lg-4', 'tone' => $tone, 'title' => $o['title'] ?? 'By Status',
                               'value' => $o['value'] ?? '', 'note' => $o['note'] ?? '']);
        if (!empty($bd['totals'])) {
            $charts[] = ['el' => '#' . $id, 'type' => 'rings', 'tone' => $tone, 'series' => $bd['totals'],
                         'labels' => $bd['labels'], 'colors' => $bd['colors'], 'height' => $o['height'] ?? 230];
            echo '<div id="' . cdp_dashEsc($id) . '" class="sw-chart"></div>';
            $legend = [];
            foreach ($bd['labels'] as $i => $lbl) { $legend[] = [$lbl, $bd['colors'][$i], number_format($bd['totals'][$i])]; }
            cdp_dashLegend($legend);
        } else {
            echo '<div class="swl-empty">No data yet</div>';
        }
        cdp_dashPanel('close');
    }
}
