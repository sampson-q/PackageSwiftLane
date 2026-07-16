<?php
// ============================================================================
// Customer self-service payments — intent lifecycle.
//
// An INTENT (cdb_fs_payment_intents) is an attempt to pay; a PAYMENT
// (cdb_fs_payments) is confirmed money. Intents are deliberately kept in their
// own table because every money aggregate in the app sums cdb_fs_payments
// without filtering gateway_status — an unpaid row in there would read as cash
// received across the Financial Sheet, Overview, AR and the dashboards.
//
// Lifecycle:
//   cdp_fsCreateIntent()   pending    — checkout started, amount is SERVER-computed
//   cdp_fsCompleteIntent() processing — claimed by exactly one completer
//                          success    — Paystack verified it; promoted to a payment
//                          failed     — gateway says not paid
//
// Two independent callers race to complete an intent: the customer's browser
// coming back from Paystack, and Paystack's webhook. Both call
// cdp_fsCompleteIntent(); the atomic claim below guarantees only one of them
// ever inserts the payment and clears the packages.
// ============================================================================

require_once(__DIR__ . '/fs_gateways.php');

if (!function_exists('cdp_fsIntentByRef')) {
    /** Load an intent row by its gateway reference. */
    function cdp_fsIntentByRef($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT * FROM cdb_fs_payment_intents WHERE reference = :r LIMIT 1");
            $db->bind(':r', $reference);
            $db->cdp_execute();
            return $db->cdp_registro();
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cdp_fsCreateIntent')) {
    /**
     * Record a pending intent and open the gateway checkout.
     *
     * $amountGhs MUST already be server-computed from stored prices — this
     * function never sees, and must never be given, a client-supplied amount.
     *
     * Returns ['ok','reference','url','message'].
     */
    function cdp_fsCreateIntent($cid, $sid, $mode, $amountGhs, array $orderIds, array $ctx = [], $createdBy = null)
    {
        $cid       = (int) $cid;
        $sid       = (int) $sid;
        $mode      = strtolower(trim((string) $mode));
        $amountGhs = round((float) $amountGhs, 2);

        if ($amountGhs <= 0) {
            return ['ok' => false, 'reference' => '', 'url' => '', 'message' => 'There is nothing to pay.'];
        }
        if (!in_array($mode, ['paystack', 'hubtel'], true)) {
            return ['ok' => false, 'reference' => '', 'url' => '',
                    'message' => 'Choose an online payment method.'];
        }
        if (!cdp_fsGatewayConfigured($mode)) {
            return ['ok' => false, 'reference' => '', 'url' => '',
                    'message' => ucfirst($mode) . ' is not available right now. Please pay at the office.'];
        }

        $reference = 'SL-' . $cid . '-' . $sid . '-' . strtoupper(bin2hex(random_bytes(5)));
        $rate      = isset($ctx['exchange_rate']) ? (float) $ctx['exchange_rate'] : null;

        // Persist BEFORE contacting the gateway. If we crash between the two,
        // a stranded pending row is harmless; a paid transaction with no
        // intent to match it would be money we cannot attribute.
        try {
            $db = new Conexion;
            $db->cdp_query("INSERT INTO cdb_fs_payment_intents
                                (reference, consolidate_id, sender_id, amount_ghs, exchange_rate,
                                 mode, orders, status, created_by, created_at)
                            VALUES
                                (:r, :cid, :sid, :amt, :rate, :mode, :ord, 'pending', :by, NOW())");
            $db->bind(':r', $reference);
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->bind(':amt', $amountGhs);
            $db->bind(':rate', $rate);
            $db->bind(':mode', $mode);
            $db->bind(':ord', json_encode(array_values(array_map('intval', $orderIds))));
            $db->bind(':by', $createdBy !== null ? (int) $createdBy : null);
            $db->cdp_execute();
        } catch (Throwable $e) {
            return ['ok' => false, 'reference' => '', 'url' => '',
                    'message' => 'Payment ledger not found — run sql/fs_customer_payments.sql, then try again.'];
        }

        $init = cdp_fsGatewayInit($mode, $amountGhs, $reference, $ctx);
        if (empty($init['ok'])) {
            cdp_fsMarkIntent($reference, 'failed', $init['message'] ?? 'Checkout could not be started.');
            return ['ok' => false, 'reference' => $reference, 'url' => '',
                    'message' => $init['message'] ?: 'Checkout could not be started.'];
        }

        try {
            $db->cdp_query("UPDATE cdb_fs_payment_intents SET checkout_url = :u WHERE reference = :r");
            $db->bind(':u', $init['url']);
            $db->bind(':r', $reference);
            $db->cdp_execute();
        } catch (Throwable $e) {
            // Cosmetic only — the checkout URL is already on its way to the browser.
        }

        return ['ok' => true, 'reference' => $reference, 'url' => $init['url'], 'message' => ''];
    }
}

if (!function_exists('cdp_fsMarkIntent')) {
    /** Set an intent's terminal status + reason. */
    function cdp_fsMarkIntent($reference, $status, $reason = null, $payload = null)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("UPDATE cdb_fs_payment_intents
                            SET status = :s, fail_reason = :fr,
                                gateway_payload = COALESCE(:gp, gateway_payload),
                                completed_at = NOW()
                            WHERE reference = :r");
            $db->bind(':s', (string) $status);
            $db->bind(':fr', $reason !== null ? mb_substr((string) $reason, 0, 255) : null);
            $db->bind(':gp', $payload !== null ? mb_substr((string) $payload, 0, 4000) : null);
            $db->bind(':r', (string) $reference);
            $db->cdp_execute();
        } catch (Throwable $e) {
            // Best effort.
        }
    }
}

if (!function_exists('cdp_fsCompleteIntent')) {
    /**
     * Verify an intent against the gateway and, if genuinely paid, promote it
     * to a cdb_fs_payments row and clear its packages for delivery.
     *
     * Safe to call repeatedly and concurrently (browser return + webhook):
     * the claim below is atomic, so a second caller gets 'already' and does
     * nothing. This is what stops a customer refreshing the return URL from
     * being credited twice.
     *
     * Returns ['ok','status','message','amount'].
     *   status: success | already | pending | failed | not_found
     */
    function cdp_fsCompleteIntent($reference)
    {
        $reference = trim((string) $reference);
        $intent    = cdp_fsIntentByRef($reference);
        if (!$intent) {
            return ['ok' => false, 'status' => 'not_found', 'amount' => null,
                    'message' => 'We could not find that payment.'];
        }
        if ($intent->status === 'success') {
            return ['ok' => true, 'status' => 'already', 'amount' => (float) $intent->amount_ghs,
                    'message' => 'This payment was already confirmed.'];
        }

        $db = new Conexion;

        // ---- Atomic claim: pending -> processing. Only the caller whose
        // UPDATE actually matches a row may proceed to insert money. -------
        $claimed = false;
        try {
            $db->cdp_query("UPDATE cdb_fs_payment_intents
                            SET status = 'processing'
                            WHERE reference = :r AND status = 'pending'");
            $db->bind(':r', $reference);
            $db->cdp_execute();
            $claimed = ($db->cdp_rowCount() > 0);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'failed', 'amount' => null,
                    'message' => 'Could not lock the payment for confirmation.'];
        }
        if (!$claimed) {
            // Someone else is mid-flight (or it already resolved). Re-read.
            $fresh = cdp_fsIntentByRef($reference);
            if ($fresh && $fresh->status === 'success') {
                return ['ok' => true, 'status' => 'already', 'amount' => (float) $fresh->amount_ghs,
                        'message' => 'This payment was already confirmed.'];
            }
            return ['ok' => false, 'status' => 'pending', 'amount' => null,
                    'message' => 'This payment is still being confirmed. Give it a moment.'];
        }

        // ---- Ask the gateway, enforcing the amount we originally quoted. ---
        $expected = round((float) $intent->amount_ghs, 2);
        $verify   = cdp_fsVerifyPayment($intent->mode, $reference, $expected);

        if (empty($verify['ok'])) {
            // Not paid (yet). 'pending'/'ongoing' means the customer may still
            // be on the MoMo prompt, so release the claim and let the webhook
            // or a later poll finish the job. Anything else is terminal.
            $gwStatus = strtolower((string) ($verify['status'] ?? 'failed'));
            $retryable = in_array($gwStatus, ['pending', 'ongoing', 'processing', 'abandoned'], true);
            cdp_fsMarkIntent($reference, $retryable ? 'pending' : 'failed',
                             $verify['message'] ?? 'Not confirmed.', $verify['payload'] ?? null);
            return ['ok' => false, 'status' => $retryable ? 'pending' : 'failed', 'amount' => null,
                    'message' => $verify['message'] ?: 'This payment has not been confirmed.'];
        }

        // ---- Confirmed. Promote to money. --------------------------------
        // Use the gateway-reported amount, never the intent's own figure.
        $paid   = round((float) ($verify['amount'] ?? $expected), 2);
        $orders = json_decode((string) $intent->orders, true);
        $orders = is_array($orders) ? array_values(array_unique(array_map('intval', $orders))) : [];

        // Re-check clearance at completion time: staff may have cleared these
        // packages by cash while the customer was on the checkout page. The
        // payment is still recorded in full (they did pay) — we just don't
        // re-clear what is already cleared.
        $toClear = [];
        if ($orders) {
            $in = implode(',', $orders);
            $db->cdp_query("SELECT order_id FROM cdb_add_order
                            WHERE order_id IN ($in) AND sender_id = :sid AND fs_cleared_for_delivery <> 1");
            $db->bind(':sid', (int) $intent->sender_id);
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $r) {
                $toClear[] = (int) $r->order_id;
            }
        }

        $paymentId = null;
        try {
            $db->cdp_query("INSERT INTO cdb_fs_payments
                                (consolidate_id, sender_id, amount_ghs, mode, reference,
                                 gateway_status, gateway_payload, cleared_for_delivery, cleared_orders,
                                 note, exchange_rate, recorded_by, recorded_at)
                            VALUES
                                (:cid, :sid, :amt, :mode, :ref,
                                 'success', :gp, 1, :co,
                                 :note, :rate, :by, NOW())");
            $db->bind(':cid', (int) $intent->consolidate_id);
            $db->bind(':sid', (int) $intent->sender_id);
            $db->bind(':amt', $paid);
            $db->bind(':mode', (string) $intent->mode);
            $db->bind(':ref', $reference);
            $db->bind(':gp', $verify['payload'] ?? null);
            $db->bind(':co', json_encode($toClear));
            $db->bind(':note', 'Paid online by the customer.');
            $db->bind(':rate', $intent->exchange_rate !== null ? (float) $intent->exchange_rate : null);
            $db->bind(':by', (int) $intent->sender_id);
            $db->cdp_execute();
            $paymentId = (int) $db->dbh->lastInsertId();
        } catch (Throwable $e) {
            // Money left the customer but we could not book it. Park the intent
            // as processing so it is visibly stuck rather than silently lost —
            // never mark it failed, and never mark it success.
            cdp_fsMarkIntent($reference, 'processing',
                             'PAID BUT NOT BOOKED — reconcile manually: ' . $e->getMessage(),
                             $verify['payload'] ?? null);
            return ['ok' => false, 'status' => 'failed', 'amount' => $paid,
                    'message' => 'Your payment went through but we could not record it. Please contact support with reference ' . $reference . '.'];
        }

        foreach ($toClear as $oid) {
            $db->cdp_query("UPDATE cdb_add_order
                            SET fs_cleared_for_delivery = 1, fs_cleared_at = NOW(), fs_cleared_by = :by,
                                status_invoice = 1
                            WHERE order_id = :oid");
            $db->bind(':by', (int) $intent->sender_id);
            $db->bind(':oid', $oid);
            $db->cdp_execute();
        }

        try {
            $db->cdp_query("UPDATE cdb_fs_payment_intents
                            SET status = 'success', payment_id = :pid, gateway_payload = :gp,
                                fail_reason = NULL, completed_at = NOW()
                            WHERE reference = :r");
            $db->bind(':pid', $paymentId);
            $db->bind(':gp', $verify['payload'] !== null ? mb_substr((string) $verify['payload'], 0, 4000) : null);
            $db->bind(':r', $reference);
            $db->cdp_execute();
        } catch (Throwable $e) {
            // The payment is booked; the intent's own bookkeeping is secondary.
        }

        cdp_fsSyncBillingCache((int) $intent->consolidate_id, (int) $intent->sender_id);

        return ['ok' => true, 'status' => 'success', 'amount' => $paid, 'message' => 'Payment confirmed.'];
    }
}

if (!function_exists('cdp_fsSyncBillingCache')) {
    /**
     * Recompute the denormalized paid/discount caches on the billing row.
     * Mirrors fs_sync_billing_cache() in financial_sheet_ajax.php, which lives
     * behind the staff-only permission gate and so cannot be included here.
     */
    function cdp_fsSyncBillingCache($cid, $sid)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("UPDATE cdb_consolidate_customer_billing b
                            SET b.paid_ghs = (
                                    SELECT COALESCE(SUM(p.amount_ghs), 0) FROM cdb_fs_payments p
                                    WHERE p.consolidate_id = b.consolidate_id AND p.sender_id = b.sender_id
                                ),
                                b.discount_ghs = (
                                    SELECT COALESCE(SUM(d.amount_ghs), 0) FROM cdb_fs_discounts d
                                    WHERE d.consolidate_id = b.consolidate_id AND d.sender_id = b.sender_id
                                )
                            WHERE b.consolidate_id = :cid AND b.sender_id = :sid");
            $db->bind(':cid', (int) $cid);
            $db->bind(':sid', (int) $sid);
            $db->cdp_execute();
        } catch (Throwable $e) {
            // Caches are derived; the ledgers remain authoritative.
        }
    }
}

if (!function_exists('cdp_fsPaymentForOrder')) {
    /**
     * The confirmed payment that cleared a given package, if any — used to
     * stamp PAID (with its method/reference/date) onto the shipping invoice.
     * Returns the newest matching cdb_fs_payments row, or null.
     */
    function cdp_fsPaymentForOrder($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return null;
        }
        try {
            $db = new Conexion;
            // cleared_orders is a JSON array of order_ids. JSON_CONTAINS is the
            // exact test; a LIKE would match 12 inside 123.
            $db->cdp_query("SELECT * FROM cdb_fs_payments
                            WHERE cleared_orders IS NOT NULL
                              AND JSON_VALID(cleared_orders)
                              AND JSON_CONTAINS(cleared_orders, :oid)
                            ORDER BY recorded_at DESC, id DESC
                            LIMIT 1");
            $db->bind(':oid', (string) $orderId);
            $db->cdp_execute();
            return $db->cdp_registro();
        } catch (Throwable $e) {
            return null;
        }
    }
}
