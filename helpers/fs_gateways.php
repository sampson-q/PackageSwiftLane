<?php
// ============================================================================
// Financial Sheet — payment gateway integration (Paystack + Hubtel).
//
// Keys are managed by the EXISTING "Payment Methods" settings screens and live
// in cdb_met_payment (Paystack = id 4: public_key / secret_key; Hubtel = id 6,
// which reuses the same columns: public_key = Client ID, secret_key = Client
// Secret, paypal_client_id = Merchant Account Number). Until the keys are set
// the gateways report "unconfigured" and the operator records Cash instead.
//
// Two entry points used by the payment dialog:
//   cdp_fsGatewayInit($mode, $amountGhs, $reference, $ctx)  -> start a charge
//        returns ['ok','url','reference','status','message'] — 'url' is the
//        gateway checkout the customer completes.
//   cdp_fsVerifyPayment($mode, $reference, $expectedGhs)    -> confirm a charge
//        returns ['ok','status','payload','amount','message'].
// Cash needs neither (it is an in-person receipt: status 'manual').
// ============================================================================

if (!function_exists('cdp_fsGatewayRow')) {
    /** cdb_met_payment row for a gateway id (Paystack=4, Hubtel=6). Cached. */
    function cdp_fsGatewayRow($metId)
    {
        static $cache = [];
        $metId = (int) $metId;
        if (array_key_exists($metId, $cache)) {
            return $cache[$metId];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id = :id LIMIT 1");
        $db->bind(':id', $metId);
        $db->cdp_execute();
        return $cache[$metId] = $db->cdp_registro();
    }
}

if (!function_exists('cdp_fsKeyOk')) {
    /** A key column is usable when it is non-empty and not the 'NONE' placeholder. */
    function cdp_fsKeyOk($v)
    {
        $v = trim((string) $v);
        return $v !== '' && strcasecmp($v, 'NONE') !== 0 && strcasecmp($v, 'NONES') !== 0;
    }
}

if (!function_exists('cdp_fsGatewayConfigured')) {
    /** Is a gateway ready to use (active + all required keys present)? */
    function cdp_fsGatewayConfigured($mode)
    {
        switch (strtolower((string) $mode)) {
            case 'paystack':
                $r = cdp_fsGatewayRow(4);
                return $r && (int) $r->is_active === 1 && cdp_fsKeyOk($r->secret_key ?? '');
            case 'hubtel':
                $r = cdp_fsGatewayRow(6);
                return $r && (int) $r->is_active === 1
                    && cdp_fsKeyOk($r->public_key ?? '') && cdp_fsKeyOk($r->secret_key ?? '')
                    && cdp_fsKeyOk($r->paypal_client_id ?? '');
            default:
                return false;
        }
    }
}

// ----------------------------------------------------------------------------
// INIT — start a gateway charge for an amount (GHS). Returns a checkout URL the
// customer completes, plus the reference to verify afterwards.
// ----------------------------------------------------------------------------
if (!function_exists('cdp_fsGatewayInit')) {
    function cdp_fsGatewayInit($mode, $amountGhs, $reference = '', array $ctx = [])
    {
        $mode = strtolower(trim((string) $mode));
        $amountGhs = round((float) $amountGhs, 2);
        if ($reference === '') {
            $reference = 'FS-' . strtoupper(bin2hex(random_bytes(6)));
        }
        if ($amountGhs <= 0) {
            return ['ok' => false, 'status' => 'invalid', 'url' => '', 'reference' => $reference,
                    'message' => 'Amount must be greater than 0.'];
        }
        switch ($mode) {
            case 'paystack':
                return cdp_fsInitPaystack($amountGhs, $reference, $ctx);
            case 'hubtel':
                return cdp_fsInitHubtel($amountGhs, $reference, $ctx);
            default:
                return ['ok' => false, 'status' => 'unknown', 'url' => '', 'reference' => $reference,
                        'message' => 'This mode has no online checkout.'];
        }
    }
}

if (!function_exists('cdp_fsInitPaystack')) {
    function cdp_fsInitPaystack($amountGhs, $reference, array $ctx = [])
    {
        $r = cdp_fsGatewayRow(4);
        $secret = $r ? trim((string) $r->secret_key) : '';
        if (!cdp_fsKeyOk($secret)) {
            return ['ok' => false, 'status' => 'unconfigured', 'url' => '', 'reference' => $reference,
                    'message' => 'Paystack is not configured yet. Add its keys in Settings, or record as Cash.'];
        }
        $body = [
            'email'     => $ctx['email'] ?? 'customer@swiftlanehq.com',
            'amount'    => (int) round($amountGhs * 100), // GHS -> pesewas
            'currency'  => 'GHS',
            'reference' => $reference,
            // Business rule: NO CARDS. Card-not-present fraud (stolen card
            // credentials) is chargeback-able months later, by which time the
            // packages are long delivered. Mobile money is push-based and
            // effectively irreversible, so it is the only channel offered.
            // Override with $ctx['channels'] only for a deliberate exception.
            'channels'  => $ctx['channels'] ?? ['mobile_money'],
        ];
        if (!empty($ctx['callback_url'])) {
            $body['callback_url'] = $ctx['callback_url'];
        }
        $res = cdp_fsHttpJson('POST', 'https://api.paystack.co/transaction/initialize',
            ['Authorization: Bearer ' . $secret, 'Content-Type: application/json'], json_encode($body));
        if (!$res['ok']) {
            return ['ok' => false, 'status' => 'failed', 'url' => '', 'reference' => $reference, 'message' => $res['error']];
        }
        $j = json_decode($res['body']);
        if (isset($j->status) && $j->status && isset($j->data->authorization_url)) {
            return ['ok' => true, 'status' => 'initialized', 'url' => (string) $j->data->authorization_url,
                    'reference' => (string) ($j->data->reference ?? $reference), 'message' => ''];
        }
        return ['ok' => false, 'status' => 'failed', 'url' => '', 'reference' => $reference,
                'message' => isset($j->message) ? (string) $j->message : 'Paystack could not start the transaction.'];
    }
}

if (!function_exists('cdp_fsInitHubtel')) {
    function cdp_fsInitHubtel($amountGhs, $reference, array $ctx = [])
    {
        $r = cdp_fsGatewayRow(6);
        $clientId = $r ? trim((string) $r->public_key) : '';
        $secret   = $r ? trim((string) $r->secret_key) : '';
        $merchant = $r ? trim((string) $r->paypal_client_id) : '';
        if (!cdp_fsKeyOk($clientId) || !cdp_fsKeyOk($secret) || !cdp_fsKeyOk($merchant)) {
            return ['ok' => false, 'status' => 'unconfigured', 'url' => '', 'reference' => $reference,
                    'message' => 'Hubtel is not configured yet. Add its credentials in Settings, or record as Cash.'];
        }
        $body = [
            'totalAmount'           => $amountGhs,
            'description'           => $ctx['description'] ?? ('Payment ' . $reference),
            'merchantAccountNumber' => $merchant,
            'clientReference'       => $reference,
        ];
        foreach (['callbackUrl' => 'callback_url', 'returnUrl' => 'return_url', 'cancellationUrl' => 'cancel_url'] as $k => $src) {
            if (!empty($ctx[$src])) {
                $body[$k] = $ctx[$src];
            }
        }
        $res = cdp_fsHttpJson('POST', 'https://payproxyapi.hubtel.com/items/initiate',
            ['Authorization: Basic ' . base64_encode($clientId . ':' . $secret), 'Content-Type: application/json'],
            json_encode($body));
        if (!$res['ok']) {
            return ['ok' => false, 'status' => 'failed', 'url' => '', 'reference' => $reference, 'message' => $res['error']];
        }
        $j = json_decode($res['body']);
        $url = $j->data->checkoutUrl ?? ($j->data->checkoutDirectUrl ?? null);
        if ($url) {
            return ['ok' => true, 'status' => 'initialized', 'url' => (string) $url,
                    'reference' => (string) ($j->data->clientReference ?? $reference), 'message' => ''];
        }
        return ['ok' => false, 'status' => 'failed', 'url' => '', 'reference' => $reference,
                'message' => isset($j->message) ? (string) $j->message : 'Hubtel could not start the checkout.'];
    }
}

// ----------------------------------------------------------------------------
// VERIFY — confirm a charge succeeded before recording it.
// ----------------------------------------------------------------------------
if (!function_exists('cdp_fsVerifyPayment')) {
    function cdp_fsVerifyPayment($mode, $reference, $expectedGhs = null)
    {
        $mode = strtolower(trim((string) $mode));
        $reference = trim((string) $reference);
        switch ($mode) {
            case 'cash':
                return ['ok' => true, 'status' => 'manual', 'payload' => null, 'amount' => null, 'message' => ''];
            case 'paystack':
                return cdp_fsVerifyPaystack($reference, $expectedGhs);
            case 'hubtel':
                return cdp_fsVerifyHubtel($reference, $expectedGhs);
            case 'paypal':
                return ['ok' => false, 'status' => 'unconfigured', 'payload' => null, 'amount' => null,
                        'message' => 'PayPal is not wired yet. Record as Cash for now.'];
            default:
                return ['ok' => false, 'status' => 'unknown', 'payload' => null, 'amount' => null,
                        'message' => 'Unknown payment mode.'];
        }
    }
}

if (!function_exists('cdp_fsVerifyPaystack')) {
    function cdp_fsVerifyPaystack($reference, $expectedGhs = null)
    {
        if ($reference === '') {
            return ['ok' => false, 'status' => 'failed', 'payload' => null, 'amount' => null,
                    'message' => 'Enter the Paystack transaction reference.'];
        }
        $r = cdp_fsGatewayRow(4);
        $secret = $r ? trim((string) $r->secret_key) : '';
        if (!cdp_fsKeyOk($secret)) {
            return ['ok' => false, 'status' => 'unconfigured', 'payload' => null, 'amount' => null,
                    'message' => 'Paystack is not configured yet. Add its keys in Settings, or record as Cash.'];
        }
        $res = cdp_fsHttpJson('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference),
            ['Authorization: Bearer ' . $secret, 'Cache-Control: no-cache'], null);
        if (!$res['ok']) {
            return ['ok' => false, 'status' => 'failed', 'payload' => $res['error'], 'amount' => null,
                    'message' => 'Could not reach Paystack: ' . $res['error']];
        }
        $j = json_decode($res['body']);
        $status = isset($j->data->status) ? (string) $j->data->status : 'failed';
        $amount = isset($j->data->amount) ? ((float) $j->data->amount / 100.0) : null; // pesewas -> GHS
        $currency = isset($j->data->currency) ? (string) $j->data->currency : '';
        $payload = mb_substr((string) $res['body'], 0, 4000);
        if ($status === 'success') {
            // A 'success' status alone is not enough to credit an account: the
            // reference could belong to a different, smaller transaction. Only
            // trust it when the gateway also confirms the currency and the
            // amount we asked for.
            if ($currency !== '' && strcasecmp($currency, 'GHS') !== 0) {
                return ['ok' => false, 'status' => 'wrong_currency', 'payload' => $payload, 'amount' => $amount,
                        'message' => 'This transaction was paid in ' . $currency . ', not GHS.'];
            }
            if ($expectedGhs !== null) {
                // Tolerate half-pesewa float drift only.
                if ($amount === null || abs($amount - (float) $expectedGhs) > 0.005) {
                    return ['ok' => false, 'status' => 'amount_mismatch', 'payload' => $payload, 'amount' => $amount,
                            'message' => 'Paystack confirmed ' . number_format((float) $amount, 2)
                                       . ' but this payment expected ' . number_format((float) $expectedGhs, 2) . '.'];
                }
            }
            return ['ok' => true, 'status' => 'success', 'payload' => $payload, 'amount' => $amount, 'message' => ''];
        }
        return ['ok' => false, 'status' => ($status ?: 'failed'), 'payload' => $payload, 'amount' => $amount,
                'message' => 'Paystack did not confirm this reference as paid (status: ' . $status . ').'];
    }
}

if (!function_exists('cdp_fsVerifyHubtel')) {
    function cdp_fsVerifyHubtel($reference, $expectedGhs = null)
    {
        if ($reference === '') {
            return ['ok' => false, 'status' => 'failed', 'payload' => null, 'amount' => null,
                    'message' => 'Enter the Hubtel transaction / client reference.'];
        }
        $r = cdp_fsGatewayRow(6);
        $clientId = $r ? trim((string) $r->public_key) : '';
        $secret   = $r ? trim((string) $r->secret_key) : '';
        $merchant = $r ? trim((string) $r->paypal_client_id) : '';
        if (!cdp_fsKeyOk($clientId) || !cdp_fsKeyOk($secret) || !cdp_fsKeyOk($merchant)) {
            return ['ok' => false, 'status' => 'unconfigured', 'payload' => null, 'amount' => null,
                    'message' => 'Hubtel is not configured yet. Add its credentials in Settings, or record as Cash.'];
        }
        // Hubtel Transaction Status Check API (by clientReference).
        $url = 'https://api-txnstatus.hubtel.com/transactions/' . rawurlencode($merchant)
             . '/status?clientReference=' . rawurlencode($reference);
        $res = cdp_fsHttpJson('GET', $url,
            ['Authorization: Basic ' . base64_encode($clientId . ':' . $secret), 'Cache-Control: no-cache'], null);
        if (!$res['ok']) {
            return ['ok' => false, 'status' => 'failed', 'payload' => $res['error'], 'amount' => null,
                    'message' => 'Could not reach Hubtel: ' . $res['error']];
        }
        $j = json_decode($res['body']);
        $txn    = isset($j->data) ? $j->data : null;
        $status = $txn && isset($txn->status) ? (string) $txn->status : 'Unknown';
        $amount = $txn && isset($txn->amount) ? (float) $txn->amount : null;
        $payload = mb_substr((string) $res['body'], 0, 4000);
        if (strcasecmp($status, 'Paid') === 0 || strcasecmp($status, 'Success') === 0) {
            // Same rule as Paystack: a confirmed status must also match the
            // amount we asked for before it can credit an account.
            if ($expectedGhs !== null && ($amount === null || abs($amount - (float) $expectedGhs) > 0.005)) {
                return ['ok' => false, 'status' => 'amount_mismatch', 'payload' => $payload, 'amount' => $amount,
                        'message' => 'Hubtel confirmed ' . number_format((float) $amount, 2)
                                   . ' but this payment expected ' . number_format((float) $expectedGhs, 2) . '.'];
            }
            return ['ok' => true, 'status' => 'success', 'payload' => $payload, 'amount' => $amount, 'message' => ''];
        }
        return ['ok' => false, 'status' => ($status ?: 'failed'), 'payload' => $payload, 'amount' => $amount,
                'message' => 'Hubtel did not confirm this reference as paid (status: ' . $status . ').'];
    }
}

if (!function_exists('cdp_fsHttpJson')) {
    /** Small cURL helper. Returns ['ok'=>bool, 'body'=>string, 'error'=>string, 'code'=>int]. */
    function cdp_fsHttpJson($method, $url, array $headers, $body = null)
    {
        $curl = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $opts);
        $resp = curl_exec($curl);
        $err  = curl_error($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($err) {
            return ['ok' => false, 'body' => '', 'error' => $err, 'code' => 0];
        }
        return ['ok' => true, 'body' => (string) $resp, 'error' => '', 'code' => $code];
    }
}
