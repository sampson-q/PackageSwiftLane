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
require_once(__DIR__ . '/fs_status.php');

if (!function_exists('cdp_fsLogPaymentEvent')) {
    /**
     * Append to the payment audit trail. Append-only and never fatal: an event
     * we cannot write must not take down the payment path that triggered it.
     *
     * This trail is what lets staff answer "what happened to this payment?"
     * without opening the Paystack dashboard.
     */
    function cdp_fsLogPaymentEvent(array $e)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("INSERT INTO cdb_fs_payment_events
                                (payment_id, reference, source, event, old_status, new_status,
                                 amount_ghs, message, payload, actor_id, created_at)
                            VALUES
                                (:pid, :ref, :src, :evt, :old, :new,
                                 :amt, :msg, :pay, :actor, NOW())");
            $db->bind(':pid', isset($e['payment_id']) ? (int) $e['payment_id'] : null);
            $db->bind(':ref', $e['reference'] ?? null);
            $db->bind(':src', (string) ($e['source'] ?? 'unknown'));
            $db->bind(':evt', $e['event'] ?? null);
            $db->bind(':old', $e['old_status'] ?? null);
            $db->bind(':new', $e['new_status'] ?? null);
            $db->bind(':amt', isset($e['amount_ghs']) ? (float) $e['amount_ghs'] : null);
            $db->bind(':msg', isset($e['message']) ? mb_substr((string) $e['message'], 0, 255) : null);
            $db->bind(':pay', isset($e['payload']) ? mb_substr((string) $e['payload'], 0, 4000) : null);
            $db->bind(':actor', isset($e['actor_id']) ? (int) $e['actor_id'] : null);
            $db->cdp_execute();
        } catch (Throwable $ex) {
            // Trail table not migrated yet, or a write failed — never fatal.
        }
    }
}

if (!function_exists('cdp_fsApplyPaymentStatus')) {
    /**
     * Record what the gateway now says about an EXISTING payment, and deal with
     * the consequences if that changes whether we hold the money.
     *
     * The dangerous transition is success -> reversed (refund or chargeback,
     * which can land days later). Policy set by the user:
     *   - the money stops counting immediately, in every case;
     *   - packages that have NOT yet gone out lose their clearance, so nothing
     *     ships against money we no longer have;
     *   - packages already delivered keep clearance (revoking it would just
     *     create a "not cleared but delivered" contradiction) and are flagged
     *     instead — the customer simply owes again.
     *
     * @return array{changed:bool,old:string,new:string,revoked:int,delivered:int}
     */
    function cdp_fsApplyPaymentStatus($paymentId, $newStatus, array $opts = [])
    {
        $paymentId = (int) $paymentId;
        $db = new Conexion;

        $db->cdp_query("SELECT * FROM cdb_fs_payments WHERE id = :id LIMIT 1");
        $db->bind(':id', $paymentId);
        $row = $db->cdp_registro();
        if (!$row) {
            return ['changed' => false, 'old' => '', 'new' => '', 'revoked' => 0, 'delivered' => 0];
        }

        $old = (string) $row->gateway_status;
        $new = strtolower(trim((string) $newStatus));
        $detail = $opts['detail'] ?? [];

        $wasMoney = cdp_fsStatusIsMoney($old, (string) $row->mode);
        $isMoney  = cdp_fsStatusIsMoney($new, (string) $row->mode);

        // Store everything the gateway told us, whether or not the status moved.
        try {
            $db->cdp_query("UPDATE cdb_fs_payments SET
                                gateway_status     = :st,
                                gateway_raw_status = COALESCE(:raw, gateway_raw_status),
                                gateway_response   = COALESCE(:resp, gateway_response),
                                gateway_channel    = COALESCE(:chan, gateway_channel),
                                gateway_currency   = COALESCE(:cur, gateway_currency),
                                gateway_fees_ghs   = COALESCE(:fees, gateway_fees_ghs),
                                gateway_customer   = COALESCE(:cust, gateway_customer),
                                gateway_paid_at    = COALESCE(:paid, gateway_paid_at),
                                gateway_payload    = COALESCE(:pay, gateway_payload),
                                gateway_checked_at = NOW(),
                                reversed_at    = CASE WHEN :st2 = 'reversed' AND reversed_at IS NULL
                                                      THEN NOW() ELSE reversed_at END,
                                reversal_reason = CASE WHEN :st3 = 'reversed'
                                                       THEN COALESCE(:rr, reversal_reason) ELSE reversal_reason END
                            WHERE id = :id");
            $db->bind(':st', $new);
            $db->bind(':st2', $new);
            $db->bind(':st3', $new);
            $db->bind(':raw', $detail['raw_status'] ?? null);
            $db->bind(':resp', !empty($detail['response']) ? $detail['response'] : null);
            $db->bind(':chan', !empty($detail['channel']) ? $detail['channel'] : null);
            $db->bind(':cur', !empty($detail['currency']) ? $detail['currency'] : null);
            $db->bind(':fees', isset($detail['fees']) && $detail['fees'] !== null ? (float) $detail['fees'] : null);
            $db->bind(':cust', !empty($detail['customer']) ? $detail['customer'] : null);
            $db->bind(':paid', !empty($detail['paid_at']) ? date('Y-m-d H:i:s', strtotime($detail['paid_at'])) : null);
            $db->bind(':pay', isset($opts['payload']) ? mb_substr((string) $opts['payload'], 0, 4000) : null);
            $db->bind(':rr', $opts['message'] ?? null);
            $db->bind(':id', $paymentId);
            $db->cdp_execute();
        } catch (Throwable $e) {
            return ['changed' => false, 'old' => $old, 'new' => $old, 'revoked' => 0, 'delivered' => 0];
        }

        $revoked = 0;
        $delivered = 0;

        // Money we had, and now do not: pull clearance from anything still here.
        if ($wasMoney && !$isMoney) {
            $oids = json_decode((string) $row->cleared_orders, true);
            $oids = is_array($oids) ? array_values(array_unique(array_map('intval', $oids))) : [];
            if ($oids) {
                $in = implode(',', $oids);
                // 8 = Delivered, 15 = Picked up — already with the customer.
                $db->cdp_query("SELECT order_id, status_courier FROM cdb_add_order
                                WHERE order_id IN ($in) AND fs_cleared_for_delivery = 1");
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $o) {
                    if (in_array((int) $o->status_courier, [8, 15], true)) {
                        $delivered++;
                        continue;
                    }
                    $db->cdp_query("UPDATE cdb_add_order
                                    SET fs_cleared_for_delivery = 0, fs_cleared_at = NULL, fs_cleared_by = NULL,
                                        status_invoice = 3
                                    WHERE order_id = :oid");
                    $db->bind(':oid', (int) $o->order_id);
                    $db->cdp_execute();
                    $revoked++;
                }
            }
        }

        cdp_fsSyncBillingCache((int) $row->consolidate_id, (int) $row->sender_id);

        cdp_fsLogPaymentEvent([
            'payment_id' => $paymentId,
            'reference'  => (string) $row->reference,
            'source'     => $opts['source'] ?? 'manual_check',
            'event'      => $opts['event'] ?? 'verify',
            'old_status' => $old,
            'new_status' => $new,
            'amount_ghs' => (float) $row->amount_ghs,
            'message'    => $opts['message'] ?? '',
            'payload'    => $opts['payload'] ?? null,
            'actor_id'   => $opts['actor_id'] ?? null,
        ]);

        if ($wasMoney && !$isMoney) {
            cdp_fsLogPaymentEvent([
                'payment_id' => $paymentId,
                'reference'  => (string) $row->reference,
                'source'     => $opts['source'] ?? 'manual_check',
                'event'      => 'money_withdrawn',
                'old_status' => $old,
                'new_status' => $new,
                'amount_ghs' => (float) $row->amount_ghs,
                'message'    => 'No longer counted as paid. Clearance revoked on ' . $revoked
                              . ' package(s); ' . $delivered . ' already delivered and left cleared.',
                'actor_id'   => $opts['actor_id'] ?? null,
            ]);
        }

        return ['changed' => ($old !== $new), 'old' => $old, 'new' => $new,
                'revoked' => $revoked, 'delivered' => $delivered];
    }
}

if (!function_exists('cdp_fsRecheckPayment')) {
    /**
     * Ask the gateway what a payment's status is right now and apply it.
     * Cash has no gateway to ask.
     */
    function cdp_fsRecheckPayment($paymentId, $actorId = null, $source = 'manual_check')
    {
        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_fs_payments WHERE id = :id LIMIT 1");
        $db->bind(':id', (int) $paymentId);
        $row = $db->cdp_registro();
        if (!$row) {
            return ['ok' => false, 'message' => 'Payment not found.'];
        }
        if (strtolower((string) $row->mode) === 'cash') {
            return ['ok' => false, 'message' => 'This was a cash payment — there is no gateway to check.'];
        }
        if (trim((string) $row->reference) === '') {
            return ['ok' => false, 'message' => 'This payment has no gateway reference to check.'];
        }

        // Verify WITHOUT an expected amount: we want the gateway's true current
        // state (including 'reversed'), not a pass/fail against the billed
        // figure. The amount was already checked when the payment was booked.
        $verify = cdp_fsVerifyPayment((string) $row->mode, (string) $row->reference, null);
        $status = (string) ($verify['status'] ?? 'unknown');

        // Could not reach the gateway: report it, change nothing. A network
        // blip must never look like a reversal.
        if (!empty($verify['unreachable'])) {
            return ['ok' => false, 'message' => $verify['message'] ?: 'Could not reach the gateway.'];
        }

        $res = cdp_fsApplyPaymentStatus((int) $row->id, $status, [
            'detail'   => $verify['detail'] ?? [],
            'payload'  => $verify['payload'] ?? null,
            'message'  => $verify['message'] ?? '',
            'source'   => $source,
            'event'    => 'verify',
            'actor_id' => $actorId,
        ]);

        $meta = cdp_fsStatusMeta($status, (string) $row->mode);
        return ['ok' => true, 'status' => $status, 'label' => $meta['label'], 'hint' => $meta['hint'],
                'changed' => $res['changed'], 'old' => $res['old'],
                'revoked' => $res['revoked'], 'delivered' => $res['delivered'],
                'message' => $res['changed']
                    ? ('Status changed from ' . cdp_fsStatusMeta($res['old'], (string) $row->mode)['label']
                       . ' to ' . $meta['label'] . '.')
                    : ('Still ' . $meta['label'] . '.')];
    }
}

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
     * $opts['source'] names the caller (paystack_webhook / customer_return /
     * manual_check) purely so the audit trail records who confirmed it.
     *
     * Returns ['ok','status','message','amount'].
     *   status: success | already | pending | failed | not_found
     */
    function cdp_fsCompleteIntent($reference, array $opts = [])
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
            // Someone else is mid-flight, or it already resolved. Re-read and
            // report what it ACTUALLY is — telling a customer to wait for a
            // payment that already failed would just leave them stuck.
            $fresh = cdp_fsIntentByRef($reference);
            if ($fresh && $fresh->status === 'success') {
                return ['ok' => true, 'status' => 'already', 'amount' => (float) $fresh->amount_ghs,
                        'message' => 'This payment was already confirmed.'];
            }
            if ($fresh && $fresh->status === 'failed') {
                return ['ok' => false, 'status' => 'failed', 'amount' => null,
                        'message' => (string) ($fresh->fail_reason ?: 'This payment was not confirmed.')];
            }
            // Genuinely still in flight (another completer holds the claim).
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
        $gd = $verify['detail'] ?? [];
        try {
            $db->cdp_query("INSERT INTO cdb_fs_payments
                                (consolidate_id, sender_id, amount_ghs, mode, reference,
                                 gateway_status, gateway_raw_status, gateway_response, gateway_channel,
                                 gateway_currency, gateway_fees_ghs, gateway_customer, gateway_paid_at,
                                 gateway_checked_at, gateway_payload,
                                 cleared_for_delivery, cleared_orders,
                                 note, exchange_rate, recorded_by, recorded_at)
                            VALUES
                                (:cid, :sid, :amt, :mode, :ref,
                                 'success', :graw, :gresp, :gchan,
                                 :gcur, :gfees, :gcust, :gpaid,
                                 NOW(), :gp,
                                 1, :co,
                                 :note, :rate, :by, NOW())");
            $db->bind(':cid', (int) $intent->consolidate_id);
            $db->bind(':sid', (int) $intent->sender_id);
            $db->bind(':amt', $paid);
            $db->bind(':mode', (string) $intent->mode);
            $db->bind(':ref', $reference);
            $db->bind(':graw', !empty($gd['raw_status']) ? $gd['raw_status'] : 'success');
            $db->bind(':gresp', !empty($gd['response']) ? $gd['response'] : null);
            $db->bind(':gchan', !empty($gd['channel']) ? $gd['channel'] : null);
            $db->bind(':gcur', !empty($gd['currency']) ? $gd['currency'] : null);
            $db->bind(':gfees', isset($gd['fees']) && $gd['fees'] !== null ? (float) $gd['fees'] : null);
            $db->bind(':gcust', !empty($gd['customer']) ? $gd['customer'] : null);
            $db->bind(':gpaid', !empty($gd['paid_at']) ? date('Y-m-d H:i:s', strtotime($gd['paid_at'])) : null);
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

        cdp_fsLogPaymentEvent([
            'payment_id' => $paymentId,
            'reference'  => $reference,
            'source'     => $opts['source'] ?? 'customer_checkout',
            'event'      => 'charge.success',
            'old_status' => 'pending',
            'new_status' => 'success',
            'amount_ghs' => $paid,
            'message'    => 'Confirmed by ' . ucfirst((string) $intent->mode) . '. Cleared '
                          . count($toClear) . ' package(s).',
            'payload'    => $verify['payload'] ?? null,
            'actor_id'   => (int) $intent->sender_id,
        ]);

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
                                      AND " . cdp_fsMoneySqlFilter('p') . "
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
