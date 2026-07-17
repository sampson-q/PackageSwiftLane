<?php
// ============================================================================
// My Bills — customer self-service payments (userlevel 1).
//
// The customer sees only their OWN bills and pays only for their OWN packages.
// The identity used for every query is $_SESSION['userid'] — the request may
// name a consolidation, never a sender. A sender_id taken from the request
// would let any logged-in customer read and settle someone else's account.
//
// The amount charged is always computed here from stored prices. The browser
// only ever says WHICH packages; it never says how much.
// ============================================================================

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/autoload_lang.php');
require_once(__DIR__ . '/../../helpers/fs_payments.php');
require_login();

$db   = new Conexion;
$user = new User;
$core = new Core;

$userData = $user->cdp_getUserData();
$sid = (int) ($_SESSION['userid'] ?? 0);

// This endpoint is the customer's own-account view. Staff have the Financial
// Sheet; letting a staff session in here would just read their own (empty)
// customer account, so gate it on the customer permission and nothing else.
$action = isset($_REQUEST['action']) ? cdp_sanitize($_REQUEST['action']) : 'list';
$permMap = [
    'checkout' => 'pay_my_bills',
];
require_permission($permMap[$action] ?? 'view_my_bills');

if ($sid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'no_identity']);
    exit;
}

header('Content-Type: application/json');

/**
 * Every bill for this customer, with derived money.
 * net = billed - discounts; balance = max(0, net - paid).
 */
function mb_bills($sid)
{
    $db = new Conexion;
    $db->cdp_query("SELECT b.consolidate_id, b.amount_ghs, b.amount_usd, b.exchange_rate, b.billed_at,
                           c.c_prefix, c.c_no,
                           COALESCE((SELECT SUM(" . cdp_fsMoneyExpr('p') . ") FROM cdb_fs_payments p
                                     WHERE p.consolidate_id = b.consolidate_id AND p.sender_id = b.sender_id
                                       AND " . cdp_fsMoneySqlFilter('p') . "), 0) AS paid_ghs,
                           COALESCE((SELECT SUM(d.amount_ghs) FROM cdb_fs_discounts d
                                     WHERE d.consolidate_id = b.consolidate_id AND d.sender_id = b.sender_id), 0) AS discount_ghs
                    FROM cdb_consolidate_customer_billing b
                    LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id
                    WHERE b.sender_id = :sid
                    ORDER BY b.billed_at DESC");
    $db->bind(':sid', (int) $sid);
    $db->cdp_execute();

    $out = [];
    foreach ((array) $db->cdp_registros() as $r) {
        $billed   = round((float) $r->amount_ghs, 2);
        $discount = round((float) $r->discount_ghs, 2);
        $paid     = round((float) $r->paid_ghs, 2);
        $net      = round(max(0, $billed - $discount), 2);
        $balance  = round(max(0, $net - $paid), 2);
        $out[] = [
            'cid'        => (int) $r->consolidate_id,
            'consol_no'  => trim((string) ($r->c_prefix ?? '') . (string) ($r->c_no ?? '')),
            'billed'     => $billed,
            'discount'   => $discount,
            'paid'       => $paid,
            'net'        => $net,
            'balance'    => $balance,
            'settled'    => $balance <= 0.005,
            'rate'       => $r->exchange_rate !== null ? (float) $r->exchange_rate : null,
            'billed_at'  => (string) ($r->billed_at ?? ''),
        ];
    }
    return $out;
}

/**
 * The customer's packages in one consolidation, each with its GHS share of the
 * NET bill. A package's raw price does not equal what is owed for it: the bill
 * folds in the handling fee and any discount. Sharing the net out in proportion
 * to price is the same rule the staff payment dialog uses, so both surfaces
 * agree on what a package costs.
 */
function mb_bill_packages($cid, $sid, $net)
{
    // Membership is cdb_consolidate_detail (matched on prefix+number), NOT an
    // consolidate_id column on the order — so reuse the same helper the
    // Financial Sheet bills from. Rolling our own query here would let the two
    // surfaces disagree about which packages a bill covers.
    $rows = [];
    foreach (cdp_getConsolidationFinancialRows($cid) as $r) {
        if ((int) ($r->sender_id ?? 0) === (int) $sid) {
            $rows[] = $r;
        }
    }

    $db = new Conexion;

    // The bill's own rate — not today's — so the figures never drift.
    $db->cdp_query("SELECT exchange_rate FROM cdb_consolidate_customer_billing
                    WHERE consolidate_id = :cid AND sender_id = :sid LIMIT 1");
    $db->bind(':cid', (int) $cid);
    $db->bind(':sid', (int) $sid);
    $bill = $db->cdp_registro();
    $rate = ($bill && $bill->exchange_rate) ? (float) $bill->exchange_rate : (float) (new Core)->exchange_rate;

    // Clearance state lives on the order.
    $cleared = [];
    $oids = array_map(function ($r) { return (int) $r->oid; }, $rows);
    if ($oids) {
        $in = implode(',', $oids);
        $db->cdp_query("SELECT order_id, fs_cleared_for_delivery FROM cdb_add_order WHERE order_id IN ($in)");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $cleared[(int) $r->order_id] = ((int) $r->fs_cleared_for_delivery === 1);
        }
    }

    $gross = [];
    $grossAll = 0.0;
    foreach ($rows as $r) {
        $g = round(cdp_usdToGhs((float) $r->total_order, $rate), 2);
        $gross[(int) $r->oid] = $g;
        $grossAll += $g;
    }
    $factor = ($grossAll > 0) ? ($net / $grossAll) : 0.0;

    $out = [];
    foreach ($rows as $r) {
        $oid = (int) $r->oid;
        $out[] = [
            'oid'      => $oid,
            'tracking' => (string) $r->order_prefix . (string) $r->order_no,
            'gross'    => $gross[$oid],
            'share'    => round($gross[$oid] * $factor, 2),
            'cleared'  => !empty($cleared[$oid]),
        ];
    }
    return ['rate' => $rate, 'packages' => $out];
}

// ---------------------------------------------------------------------------
switch ($action) {

    // -- Bills list ---------------------------------------------------------
    case 'list': {
        $bills = mb_bills($sid);
        $owed = 0.0;
        foreach ($bills as $b) {
            $owed += $b['balance'];
        }
        echo json_encode([
            'ok'           => true,
            'bills'        => $bills,
            'total_owed'   => round($owed, 2),
            'can_pay'      => $user->cdp_hasPermission('pay_my_bills'),
            'gateway_up'   => cdp_fsGatewayConfigured('paystack'),
        ]);
        exit;
    }

    // -- One bill's package checklist ---------------------------------------
    case 'bill': {
        $cid = (int) ($_REQUEST['cid'] ?? 0);
        $bills = mb_bills($sid);
        $bill = null;
        foreach ($bills as $b) {
            if ($b['cid'] === $cid) {
                $bill = $b;
                break;
            }
        }
        if (!$bill) {
            echo json_encode(['ok' => false, 'error' => 'not_found',
                              'message' => 'That bill does not belong to your account.']);
            exit;
        }
        $pk = mb_bill_packages($cid, $sid, $bill['net']);
        echo json_encode(['ok' => true, 'bill' => $bill, 'rate' => $pk['rate'], 'packages' => $pk['packages']]);
        exit;
    }

    // -- Start a MoMo checkout ---------------------------------------------
    case 'checkout': {
        $cid = (int) ($_REQUEST['cid'] ?? 0);

        $bills = mb_bills($sid);
        $bill = null;
        foreach ($bills as $b) {
            if ($b['cid'] === $cid) {
                $bill = $b;
                break;
            }
        }
        if (!$bill) {
            echo json_encode(['ok' => false, 'message' => 'That bill does not belong to your account.']);
            exit;
        }
        if ($bill['settled']) {
            echo json_encode(['ok' => false, 'message' => 'This bill is already settled.']);
            exit;
        }

        $raw = $_REQUEST['orders'] ?? '';
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $wanted = is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
        if (!$wanted) {
            echo json_encode(['ok' => false, 'message' => 'Select at least one package to pay for.']);
            exit;
        }

        // Intersect the request against what this customer actually owes for.
        // Anything already cleared, or not theirs, is silently dropped.
        $pk = mb_bill_packages($cid, $sid, $bill['net']);
        $payable = [];
        $amount = 0.0;
        foreach ($pk['packages'] as $p) {
            if (!$p['cleared'] && in_array($p['oid'], $wanted, true)) {
                $payable[] = $p['oid'];
                $amount += $p['share'];
            }
        }
        if (!$payable) {
            echo json_encode(['ok' => false, 'message' => 'Those packages are already paid for.']);
            exit;
        }

        // If they are settling everything that is left, charge the exact
        // remaining balance rather than the sum of the shares — proportional
        // rounding would otherwise leave a few pesewas behind and the bill
        // would never read as settled.
        $uncleared = 0;
        foreach ($pk['packages'] as $p) {
            if (!$p['cleared']) {
                $uncleared++;
            }
        }
        $amount = (count($payable) === $uncleared)
            ? $bill['balance']
            : round($amount, 2);

        if ($amount > $bill['balance'] + 0.005) {
            $amount = $bill['balance'];
        }
        if ($amount <= 0) {
            echo json_encode(['ok' => false, 'message' => 'There is nothing left to pay on this bill.']);
            exit;
        }

        $email = trim((string) ($userData->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Paystack requires an email; it only ever receives the receipt.
            $email = 'customer' . $sid . '@swiftlanehq.com';
        }

        // The app's PUBLIC address. cdb_settings.site_url is what the rest of
        // the codebase already sends to customers (tracking links in WhatsApp /
        // email), so it is the one that is known-correct on live. CDP_APP_URL
        // lives in a gitignored per-environment config.php and is 'localhost'
        // in dev — trusting it would redirect paying customers to localhost.
        $base = rtrim((string) ($core->site_url ?? ''), '/');
        if ($base === '' || strpos($base, 'http') !== 0) {
            $base = rtrim((string) (defined('CDP_APP_URL') ? CDP_APP_URL : ''), '/');
        }
        $res = cdp_fsCreateIntent($cid, $sid, 'paystack', $amount, $payable, [
            'email'          => $email,
            'callback_url'   => $base . '/payment_return.php',
            'description'    => 'SwiftLane bill ' . ($bill['consol_no'] ?: $cid),
            'exchange_rate'  => $bill['rate'] ?? (float) $core->exchange_rate,
        ], $sid);

        echo json_encode([
            'ok'        => !empty($res['ok']),
            'url'       => $res['url'] ?? '',
            'reference' => $res['reference'] ?? '',
            'amount'    => $amount,
            'message'   => $res['message'] ?? '',
        ]);
        exit;
    }

    // -- Poll a checkout the customer started (browser may have lost the
    //    callback; the webhook may have finished it already). ---------------
    case 'status': {
        $ref = trim((string) ($_REQUEST['reference'] ?? ''));
        $intent = cdp_fsIntentByRef($ref);
        if (!$intent || (int) $intent->sender_id !== $sid) {
            echo json_encode(['ok' => false, 'status' => 'not_found']);
            exit;
        }
        if ($intent->status === 'pending') {
            $res = cdp_fsCompleteIntent($ref);
            echo json_encode(['ok' => !empty($res['ok']), 'status' => $res['status'], 'message' => $res['message']]);
            exit;
        }
        echo json_encode(['ok' => $intent->status === 'success', 'status' => (string) $intent->status,
                          'message' => (string) ($intent->fail_reason ?? '')]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'unknown_action']);
