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
