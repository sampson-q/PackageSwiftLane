<?php
// ============================================================================
// Payment status vocabulary — the single source of truth for what a payment's
// status MEANS.
//
// Three near-identical label helpers used to exist (cdp_txStatusLabel in
// transactions_ajax, cdp_gwStatusLabel in global_payments_gateways_ajax,
// fo_txStatus in financial_overview_ajax). Each knew only success/pending/
// failed, so every other Paystack state rendered as a raw grey string and no
// screen agreed with any other. They now all delegate here.
//
// The critical field is `money`: whether this status means we actually hold the
// customer's cash. Only statuses with money => true may be summed into a
// balance. Getting this wrong overstates revenue.
// ============================================================================

if (!function_exists('cdp_fsKnownStatuses')) {
    /**
     * Every status this system understands.
     *
     * The gateway block is Paystack's complete transaction vocabulary; the rest
     * are verdicts we reach ourselves. Anything outside this list is handled as
     * 'unknown' and never counted as money.
     */
    function cdp_fsKnownStatuses()
    {
        return [
            'manual',                                     // cash, no gateway
            'success', 'failed', 'abandoned', 'pending',  // Paystack
            'ongoing', 'processing', 'queued',
            'reversal-pending', 'reversed',
            'unconfigured', 'wrong_currency', 'amount_mismatch', 'unknown',
        ];
    }
}

if (!function_exists('cdp_fsStatusMeta')) {
    /**
     * Describe a payment status.
     *
     * @param  string $status Normalised status (our vocabulary).
     * @param  string $mode   cash|paystack|hubtel|paypal — cash is never a gateway state.
     * @return array{key:string,label:string,class:string,money:bool,final:bool,hint:string}
     *   money = counts toward "paid"; final = will not change on its own.
     */
    function cdp_fsStatusMeta($status, $mode = '')
    {
        $s = strtolower(trim((string) $status));
        $m = strtolower(trim((string) $mode));

        if ($m === 'cash' || $s === 'manual') {
            return ['key' => 'manual', 'label' => 'Cash / Manual', 'class' => 'label-info',
                    'money' => true, 'final' => true,
                    'hint' => 'Recorded in person by staff. No gateway involved.'];
        }

        $map = [
            // ---- Paystack / Hubtel transaction states -----------------------
            'success'    => ['Confirmed', 'label-success', true,  true,
                             'The gateway confirmed this payment. The money is ours.'],
            'failed'     => ['Failed', 'label-danger', false, true,
                             'The payment did not go through. No money was taken.'],
            'abandoned'  => ['Abandoned', 'label-default', false, true,
                             'The customer opened the checkout but never completed it. No money was taken.'],
            'pending'    => ['Pending', 'label-warning', false, false,
                             'The gateway has not confirmed this yet. It may still complete on its own.'],
            'ongoing'    => ['Awaiting Customer', 'label-warning', false, false,
                             'The customer still has to approve the prompt on their phone.'],
            'processing' => ['Processing', 'label-warning', false, false,
                             'The gateway is still working on this one.'],
            'queued'     => ['Queued', 'label-warning', false, false,
                             'The gateway has queued this payment and has not processed it yet.'],
            // OBSERVED against the live Paystack API (2026-07-16), NOT in their
            // documented status list: issuing a refund puts the transaction into
            // 'reversal-pending' first, and it settles to 'reversed' later. The
            // money is already on its way out, so it stops counting immediately
            // rather than waiting for the settle — we must not release packages
            // against money that is being clawed back.
            'reversal-pending' => ['Reversal Pending', 'label-danger', false, false,
                             'A refund or chargeback is being processed. The money is on its way back to the customer, so it no longer counts as paid.'],
            'reversed'   => ['Reversed', 'label-danger', false, true,
                             'The money was refunded or charged back. It no longer counts as paid.'],

            // ---- Our own verdicts ------------------------------------------
            'unconfigured'    => ['Gateway Not Set Up', 'label-default', false, false,
                                  'The gateway has no keys configured, so this could not be checked.'],
            'wrong_currency'  => ['Wrong Currency', 'label-danger', false, true,
                                  'The gateway settled this in another currency. It was not accepted.'],
            'amount_mismatch' => ['Amount Mismatch', 'label-danger', false, true,
                                  'The amount the gateway confirmed does not match what was billed. It was not accepted.'],
            'unknown'         => ['Unknown', 'label-default', false, false,
                                  'The gateway reported a state we do not recognise. Check the raw response.'],
        ];

        if (isset($map[$s])) {
            [$label, $class, $money, $final, $hint] = $map[$s];
            return ['key' => $s, 'label' => $label, 'class' => $class,
                    'money' => $money, 'final' => $final, 'hint' => $hint];
        }

        // An unrecognised status must NEVER be treated as money. Show it
        // verbatim so staff can see exactly what Paystack said.
        return ['key' => 'unknown', 'label' => ucfirst($s ?: 'Unknown'), 'class' => 'label-default',
                'money' => false, 'final' => false,
                'hint' => 'Unrecognised gateway status "' . $s . '". It is not counted as paid.'];
    }
}

if (!function_exists('cdp_fsStatusLabel')) {
    /** [label, css-class] — the shape the existing screens already render. */
    function cdp_fsStatusLabel($status, $mode = '')
    {
        $meta = cdp_fsStatusMeta($status, $mode);
        return [$meta['label'], $meta['class']];
    }
}

if (!function_exists('cdp_fsStatusIsMoney')) {
    /** Does this status mean we hold the customer's money? */
    function cdp_fsStatusIsMoney($status, $mode = '')
    {
        $meta = cdp_fsStatusMeta($status, $mode);
        return (bool) $meta['money'];
    }
}

if (!function_exists('cdp_fsMoneySqlFilter')) {
    /**
     * The SQL predicate that restricts cdb_fs_payments to rows that are real
     * money. Kept here so the vocabulary and the money maths can never drift
     * apart: add a money status above and every aggregate follows.
     *
     * Today this is a no-op on existing data (every row is manual/success) —
     * it exists so a reversal or a pending row can never inflate a balance.
     */
    function cdp_fsMoneySqlFilter($alias = '')
    {
        $p = $alias !== '' ? $alias . '.' : '';

        // Derived from the vocabulary, never hand-listed: marking a status as
        // money above is all it takes for every aggregate to follow.
        $money = [];
        foreach (cdp_fsKnownStatuses() as $k) {
            if (cdp_fsStatusMeta($k)['money']) {
                $money[] = "'" . $k . "'";
            }
        }
        // NULL = rows written before this column existed; those were all
        // confirmed at insert time.
        return '(' . $p . 'gateway_status IS NULL OR ' . $p . 'gateway_status IN (' . implode(',', $money) . '))';
    }
}
