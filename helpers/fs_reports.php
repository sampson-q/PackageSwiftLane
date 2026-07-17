<?php
// ============================================================================
// Accounts-Receivable reports, sourced from the Financial Sheet ledger.
//
// These three reports (Payments Received / Customers Balance / Summary) used to
// read the LEGACY per-order ledger: cdb_charges_order + cdb_add_order
// .status_invoice + order_payment_method. That ledger was retired when the
// Financial Sheet became the single source of truth, and it has not been
// written to since 2022 — so the reports were quietly showing four-year-old
// demo figures as if they were current. Worse than empty: staff trusted them.
//
// Each report's list / print / excel variant used to carry its OWN copy of the
// SQL (nine near-identical queries). They all call in here now, so the three
// views of a report can never disagree again.
//
// Money rule: only statuses that mean we actually hold the cash are summed —
// see cdp_fsMoneySqlFilter() in fs_status.php. A reversed payment must not be
// reported as revenue.
// ============================================================================

require_once(__DIR__ . '/fs_status.php');

if (!function_exists('cdp_fsReportRange')) {
    /** "Y/m/d - Y/m/d" (or with dashes) -> [start 00:00:00, end 23:59:59] or null. */
    function cdp_fsReportRange($range) {
        $range = trim((string) $range);
        if ($range === '') {
            return null;
        }
        $parts = explode(' - ', $range);
        if (count($parts) !== 2) {
            return null;
        }
        $a = strtotime(str_replace('/', '-', trim($parts[0])));
        $b = strtotime(str_replace('/', '-', trim($parts[1])));
        if (!$a || !$b) {
            return null;
        }
        return [date('Y-m-d 00:00:00', $a), date('Y-m-d 23:59:59', $b)];
    }
}

if (!function_exists('cdp_fsPaymentsReceived')) {
    /**
     * Every payment actually received (cash + gateway), newest first.
     *
     * $f: ['customer_id' => int, 'mode' => 'cash|paystack|hubtel', 'range' => str]
     * Rows carry: id, paid_at, customer, locker, mode, status, reference,
     *             amount_ghs, amount_usd, exchange_rate, tracking (string).
     */
    function cdp_fsPaymentsReceived(array $f = []) {
        $db = new Conexion;
        $where = ' WHERE ' . cdp_fsMoneySqlFilter('p');
        $bind = [];

        if (!empty($f['customer_id'])) {
            $where .= ' AND p.sender_id = :sid';
            $bind[':sid'] = (int) $f['customer_id'];
        }
        if (!empty($f['mode'])) {
            $where .= ' AND p.mode = :mode';
            $bind[':mode'] = (string) $f['mode'];
        }
        $r = cdp_fsReportRange($f['range'] ?? '');
        if ($r) {
            $where .= ' AND p.recorded_at BETWEEN :i AND :f';
            $bind[':i'] = $r[0];
            $bind[':f'] = $r[1];
        }

        $db->cdp_query("SELECT p.id, p.recorded_at, p.amount_ghs, p.refunded_ghs, p.exchange_rate, p.mode,
                               p.reference, p.gateway_status, p.cleared_orders, p.sender_id,
                               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                        u.username, CONCAT('User ', p.sender_id)) AS customer,
                               u.locker
                        FROM cdb_fs_payments p
                        LEFT JOIN cdb_users u ON u.id = p.sender_id
                        $where
                        ORDER BY p.recorded_at DESC, p.id DESC");
        foreach ($bind as $k => $v) {
            $db->bind($k, $v);
        }
        $db->cdp_execute();
        $rows = (array) $db->cdp_registros();
        if (!$rows) {
            return [];
        }

        // Resolve the packages each payment paid for, in ONE query rather than
        // one per row.
        $all = [];
        $byRow = [];
        foreach ($rows as $r2) {
            $ids = json_decode((string) $r2->cleared_orders, true);
            $ids = is_array($ids) ? array_map('intval', $ids) : [];
            $byRow[(int) $r2->id] = $ids;
            foreach ($ids as $o) {
                $all[$o] = true;
            }
        }
        $trackByOid = [];
        if ($all) {
            $in = implode(',', array_map('intval', array_keys($all)));
            $db->cdp_query("SELECT order_id, order_prefix, order_no FROM cdb_add_order WHERE order_id IN ($in)");
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $o) {
                $trackByOid[(int) $o->order_id] = (string) $o->order_prefix . (string) $o->order_no;
            }
        }

        $out = [];
        foreach ($rows as $r2) {
            $rate = (float) $r2->exchange_rate;
            $tr = [];
            foreach ($byRow[(int) $r2->id] as $o) {
                if (isset($trackByOid[$o])) {
                    $tr[] = $trackByOid[$o];
                }
            }
            $out[] = (object) [
                'id'            => (int) $r2->id,
                'paid_at'       => (string) $r2->recorded_at,
                'customer'      => (string) $r2->customer,
                'locker'        => (string) ($r2->locker ?? ''),
                'mode'          => (string) $r2->mode,
                'status'        => (string) $r2->gateway_status,
                'reference'     => (string) ($r2->reference ?? ''),
                'amount_ghs'    => round((float) $r2->amount_ghs - (float) $r2->refunded_ghs, 2),
                'gross_ghs'     => (float) $r2->amount_ghs,
                'refunded_ghs'  => (float) $r2->refunded_ghs,
                'amount_usd'    => $rate > 0 ? round(((float) $r2->amount_ghs - (float) $r2->refunded_ghs) / $rate, 2) : 0.0,
                'exchange_rate' => $rate,
                'tracking'      => implode(', ', $tr),
            ];
        }
        return $out;
    }
}

if (!function_exists('cdp_fsCustomerBalances')) {
    /**
     * What each customer owes, across every consolidation they were billed for.
     *   net = billed - discounts;  balance = max(0, net - paid)
     *
     * $f: ['customer_id' => int, 'range' => str (on billed_at), 'owing_only' => bool]
     */
    function cdp_fsCustomerBalances(array $f = []) {
        $db = new Conexion;
        $where = ' WHERE 1=1 ';
        $bind = [];

        if (!empty($f['customer_id'])) {
            $where .= ' AND b.sender_id = :sid';
            $bind[':sid'] = (int) $f['customer_id'];
        }
        $r = cdp_fsReportRange($f['range'] ?? '');
        if ($r) {
            $where .= ' AND b.billed_at BETWEEN :i AND :f';
            $bind[':i'] = $r[0];
            $bind[':f'] = $r[1];
        }

        // Derive paid/discount from the LEDGERS, not from b.paid_ghs /
        // b.discount_ghs. Those caches are wrong twice over:
        //   1. they drift — found ₵7,150 of "paid" on live-shaped data with no
        //      matching payment row at all, which reads as a settled bill;
        //   2. they are FLOAT columns, so they lose pesewas (₵22,312.84 comes
        //      back as 22312.8398), leaving permanent 1-4 pesewa balances that
        //      make a settled customer look like they still owe.
        // cdb_fs_payments.amount_ghs is decimal(12,2) and exact, so summing the
        // ledger is both correct and self-healing.
        $money = cdp_fsMoneySqlFilter('p');
        $db->cdp_query("SELECT b.sender_id,
                               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                        u.username, CONCAT('User ', b.sender_id)) AS customer,
                               u.locker, u.email,
                               COUNT(*) AS bills,
                               ROUND(COALESCE(SUM(b.amount_ghs),0),2) AS billed_ghs,
                               ROUND(COALESCE(SUM(
                                   (SELECT COALESCE(SUM(d.amount_ghs),0) FROM cdb_fs_discounts d
                                     WHERE d.consolidate_id=b.consolidate_id AND d.sender_id=b.sender_id)
                               ),0),2) AS discount_ghs,
                               ROUND(COALESCE(SUM(
                                   (SELECT COALESCE(SUM(" . cdp_fsMoneyExpr('p') . "),0) FROM cdb_fs_payments p
                                     WHERE p.consolidate_id=b.consolidate_id AND p.sender_id=b.sender_id
                                       AND $money)
                               ),0),2) AS paid_ghs,
                               ROUND(COALESCE(SUM(GREATEST(0, ROUND(COALESCE(b.amount_ghs,0),2)
                                   - (SELECT COALESCE(SUM(d.amount_ghs),0) FROM cdb_fs_discounts d
                                       WHERE d.consolidate_id=b.consolidate_id AND d.sender_id=b.sender_id)
                                   - (SELECT COALESCE(SUM(" . cdp_fsMoneyExpr('p') . "),0) FROM cdb_fs_payments p
                                       WHERE p.consolidate_id=b.consolidate_id AND p.sender_id=b.sender_id
                                         AND $money))),0),2) AS balance_ghs,
                               ROUND(COALESCE(SUM(GREATEST(0, ROUND(COALESCE(b.amount_ghs,0),2)
                                   - (SELECT COALESCE(SUM(d.amount_ghs),0) FROM cdb_fs_discounts d
                                       WHERE d.consolidate_id=b.consolidate_id AND d.sender_id=b.sender_id)
                                   - (SELECT COALESCE(SUM(" . cdp_fsMoneyExpr('p') . "),0) FROM cdb_fs_payments p
                                       WHERE p.consolidate_id=b.consolidate_id AND p.sender_id=b.sender_id
                                         AND $money)) / NULLIF(b.exchange_rate,0)),0),2) AS balance_usd,
                               MAX(b.billed_at) AS last_billed
                        FROM cdb_consolidate_customer_billing b
                        LEFT JOIN cdb_users u ON u.id = b.sender_id
                        $where
                        GROUP BY b.sender_id
                        " . (!empty($f['owing_only']) ? ' HAVING balance_ghs > 0 ' : '') . "
                        ORDER BY balance_ghs DESC");
        foreach ($bind as $k => $v) {
            $db->bind($k, $v);
        }
        $db->cdp_execute();
        return (array) $db->cdp_registros();
    }
}

if (!function_exists('cdp_fsBillingSummary')) {
    /**
     * One row per bill (consolidation + customer): what was billed, discounted,
     * paid and what is still owed.
     *
     * $f: ['customer_id' => int, 'range' => str, 'status' => 'paid|partial|outstanding']
     */
    function cdp_fsBillingSummary(array $f = []) {
        $db = new Conexion;
        $where = ' WHERE 1=1 ';
        $bind = [];

        if (!empty($f['customer_id'])) {
            $where .= ' AND b.sender_id = :sid';
            $bind[':sid'] = (int) $f['customer_id'];
        }
        $r = cdp_fsReportRange($f['range'] ?? '');
        if ($r) {
            $where .= ' AND b.billed_at BETWEEN :i AND :f';
            $bind[':i'] = $r[0];
            $bind[':f'] = $r[1];
        }

        // Same rule as cdp_fsCustomerBalances(): paid/discount come from the
        // ledgers, never from the float caches on the billing row.
        $money = cdp_fsMoneySqlFilter('p');
        $db->cdp_query("SELECT b.consolidate_id, b.sender_id,
                               ROUND(b.amount_ghs,2) AS amount_ghs, b.amount_usd,
                               ROUND(COALESCE((SELECT SUM(d.amount_ghs) FROM cdb_fs_discounts d
                                     WHERE d.consolidate_id=b.consolidate_id AND d.sender_id=b.sender_id),0),2)
                                   AS discount_ghs,
                               ROUND(COALESCE((SELECT SUM(" . cdp_fsMoneyExpr('p') . ") FROM cdb_fs_payments p
                                     WHERE p.consolidate_id=b.consolidate_id AND p.sender_id=b.sender_id
                                       AND $money),0),2) AS paid_ghs,
                               b.exchange_rate, b.billed_at,
                               COALESCE(NULLIF(CONCAT(c.c_prefix, c.c_no),''),
                                        CONCAT('#', b.consolidate_id)) AS consol_no,
                               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                        u.username, CONCAT('User ', b.sender_id)) AS customer,
                               u.locker
                        FROM cdb_consolidate_customer_billing b
                        LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id
                        LEFT JOIN cdb_users u ON u.id = b.sender_id
                        $where
                        ORDER BY b.billed_at DESC");
        foreach ($bind as $k => $v) {
            $db->bind($k, $v);
        }
        $db->cdp_execute();
        $rows = (array) $db->cdp_registros();

        $want = strtolower(trim((string) ($f['status'] ?? '')));
        $out = [];
        foreach ($rows as $b) {
            $net = round(max(0, (float) $b->amount_ghs - (float) $b->discount_ghs), 2);
            $paid = round((float) $b->paid_ghs, 2);
            $bal = round(max(0, $net - $paid), 2);
            $st = ($bal <= 0.005) ? 'paid' : (($paid > 0) ? 'partial' : 'outstanding');
            if ($want !== '' && $want !== $st) {
                continue;
            }
            $b->net_ghs = $net;
            $b->balance_ghs = $bal;
            $b->pay_status = $st;
            $out[] = $b;
        }
        return $out;
    }
}

if (!function_exists('cdp_fsModeFromMetPayment')) {
    /**
     * The report filters are built from cdb_met_payment, so they submit a row
     * id; the FS ledger stores a mode string. Translate one to the other.
     * 0 / anything unmapped means "no filter".
     */
    function cdp_fsModeFromMetPayment($metId) {
        $map = [1 => 'cash', 2 => 'paypal', 4 => 'paystack', 6 => 'hubtel'];
        return $map[(int) $metId] ?? '';
    }
}

if (!function_exists('cdp_fsModeLabel')) {
    /** Human name for an FS payment mode. */
    function cdp_fsModeLabel($mode) {
        $map = ['cash' => 'Cash', 'paystack' => 'Paystack (Mobile Money)',
                'hubtel' => 'Hubtel (Mobile Money)', 'paypal' => 'PayPal'];
        return $map[strtolower((string) $mode)] ?? ucfirst((string) $mode);
    }
}

if (!function_exists('cdp_fsPayStatusLabel')) {
    /** Title Case label + badge class for a bill's payment state. */
    function cdp_fsPayStatusLabel($st) {
        switch ($st) {
            case 'paid':    return ['Paid', 'label-success'];
            case 'partial': return ['Part Paid', 'label-warning'];
            default:        return ['Outstanding', 'label-danger'];
        }
    }
}
