<?php
// ============================================================================
// Financial Sheet — data + actions endpoint
// Hierarchy: consolidation -> customer (sender) -> packages -> items.
// Items are priced by weight OR custom price (0 is valid), individually or in
// "priced together" groups. A customer is billed once per consolidation: the
// bill is recorded first (ledger + packages exit the consolidation + pricing
// locks), then WhatsApp/email notifications are sent best-effort.
// All storage is canonical USD.
// ============================================================================

// PHPMailer MUST be loaded before anything that can call cdp_sendTemplateEmail:
// the app's autoloader kills the whole process (no catchable error) when asked
// to resolve an unknown class.
require_once(__DIR__ . '/../../helpers/phpmailer/class.phpmailer.php');
require_once(__DIR__ . '/../../helpers/phpmailer/class.smtp.php');
require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/autoload_lang.php');
require_once(__DIR__ . '/../../helpers/pickup_aging.php');
require_once(__DIR__ . '/../../helpers/fs_gateways.php');
require_once(__DIR__ . '/../notify_whatsapp/api_whatsapp_service_v2.php');
require_login();

// Release the PHP session lock immediately: nothing below writes to the
// session, and a long report query would otherwise block EVERY other request
// from the same browser (pages appear frozen, even logout hangs).
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$user = new User;
$core = new Core;

$userData = $user->cdp_getUserData();
$uid   = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));
$uname = trim((string) ($userData->fname ?? '') . ' ' . (string) ($userData->lname ?? ''));
if ($uname === '') {
    $uname = $userData->username ?? ('User ' . $uid);
}

$action = isset($_REQUEST['action']) ? cdp_sanitize($_REQUEST['action']) : 'list';

// Per-action RBAC: viewing needs 'financial_sheet'; each mutation needs its own
// permission so the Accounts department can delegate who may price / bill /
// take payment / discount. Superadmin (userlevel 9) passes via the wildcard.
$fsPermMap = [
    'save_item'      => 'fs_price_items',
    'save_group'     => 'fs_price_items',
    'clear_group'    => 'fs_price_items',
    'bill_customer'  => 'fs_bill_customer',
    'bill_preview'   => 'fs_bill_customer',
    'payment_form'   => 'fs_record_payment',
    'gateway_init'   => 'fs_record_payment',
    'record_payment' => 'fs_record_payment',
    'clear_debt'     => 'fs_record_payment',
    'apply_discount' => 'fs_apply_discount',
    'remove_discount' => 'fs_apply_discount',
    'clear_package'  => 'fs_clear_delivery',
    'set_weight_rate' => 'fs_price_items',
    'payment_history' => 'financial_sheet',
];
require_permission($fsPermMap[$action] ?? 'financial_sheet');

// Amount lines in customer notifications (bill + payment receipt): pending a
// final decision on whether customers may be sent money values. Flip to false
// to omit all amounts from BOTH WhatsApp and email (nothing else changes).
if (!defined('FS_BILL_MSG_INCLUDE_AMOUNT')) {
    define('FS_BILL_MSG_INCLUDE_AMOUNT', true);
}

// ----------------------------------------------------------------------------
// Shared data helpers
// ----------------------------------------------------------------------------

/** An item counts as priced ONLY when it was priced from the Financial Sheet
 *  (priced_at set). Weights/values captured at order entry do not count. */
function fs_priced_case()
{
    return "(i.priced_at IS NOT NULL)";
}

/** Per-order item stats: order_id => ['items' => n, 'priced' => n]. */
function fs_item_stats(array $oids)
{
    $out = [];
    $oids = array_values(array_unique(array_map('intval', $oids)));
    if (!$oids) {
        return $out;
    }
    $db = new Conexion;
    $in = implode(',', $oids);
    $db->cdp_query("SELECT i.order_id,
                           COUNT(*) AS items,
                           SUM(CASE WHEN " . fs_priced_case() . " THEN 1 ELSE 0 END) AS priced
                    FROM cdb_add_order_item i
                    WHERE i.order_id IN ($in)
                    GROUP BY i.order_id");
    $db->cdp_execute();
    foreach ((array) $db->cdp_registros() as $r) {
        $out[(int) $r->order_id] = ['items' => (int) $r->items, 'priced' => (int) $r->priced];
    }
    return $out;
}

/** Billing ledger for a consolidation: sender_id => row (amount, when, biller name). */
function fs_billing_map($cid)
{
    static $cache = [];
    $cid = (int) $cid;
    if (isset($cache[$cid])) {
        return $cache[$cid];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT b.sender_id, b.amount_usd, b.amount_ghs, b.handling_ghs,
                           b.paid_ghs, b.discount_ghs, b.billed_at, b.paid_at,
                           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                    u.username, CONCAT('User ', b.billed_by)) AS biller,
                           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(pu.fname,''),' ',COALESCE(pu.lname,''))),''),
                                    pu.username, CONCAT('User ', b.paid_by)) AS payer
                    FROM cdb_consolidate_customer_billing b
                    LEFT JOIN cdb_users u ON u.id = b.billed_by
                    LEFT JOIN cdb_users pu ON pu.id = b.paid_by
                    WHERE b.consolidate_id = :cid");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $map = [];
    foreach ((array) $db->cdp_registros() as $r) {
        $map[(int) $r->sender_id] = $r;
    }
    $cache[$cid] = $map;
    return $map;
}

/**
 * Group the consolidation's financial rows by customer (sender).
 * Returns sender_id => ['sender_id','label','total','weight','rows','oids','orders'].
 */
function fs_customer_groups($cid)
{
    $rows = cdp_getConsolidationFinancialRows($cid);
    $groups = [];
    foreach ($rows as $r) {
        $sid = (int) ($r->sender_id ?? 0);
        if (!isset($groups[$sid])) {
            $label = trim((string) ($r->fname ?? '') . ' ' . (string) ($r->lname ?? ''));
            if ($label === '') {
                $label = 'Unknown customer';
            }
            $locker = trim((string) ($r->locker ?? ''));
            $groups[$sid] = [
                'sender_id' => $sid,
                'name'      => $label,
                'locker'    => $locker,
                'label'     => $locker !== '' ? $label . ' (' . $locker . ')' : $label,
                'total'     => 0.0,
                'weight'    => 0.0,
                'rows'      => [],
                'oids'      => [],
                'orders'    => [],
            ];
        }
        $groups[$sid]['total']  += (float) $r->total_order;
        $groups[$sid]['weight'] += (float) $r->detail_weight;
        $groups[$sid]['rows'][]  = $r;
        $groups[$sid]['oids'][]  = (int) $r->oid;
        $groups[$sid]['orders'][] = ['id' => (int) $r->oid, 'no' => (string) ($r->order_prefix . $r->order_no)];
    }
    return $groups;
}

/** Fresh money/pricing aggregates after a save — bubbled to the UI. */
function fs_aggregates($cid, $sid)
{
    $cid = (int) $cid;
    $sid = (int) $sid;
    if ($cid <= 0) {
        return null;
    }
    $rows = cdp_getConsolidationFinancialRows($cid);
    $consolTotal = 0.0;
    $custTotal   = 0.0;
    $oids        = [];
    foreach ($rows as $r) {
        $consolTotal += (float) $r->total_order;
        if ((int) ($r->sender_id ?? 0) === $sid) {
            $custTotal += (float) $r->total_order;
            $oids[] = (int) $r->oid;
        }
    }
    $stats = fs_item_stats($oids);
    $pkgs = count($oids);
    $pkgsPriced = 0;
    foreach ($oids as $oid) {
        $s = isset($stats[$oid]) ? $stats[$oid] : null;
        if (!$s || $s['priced'] >= $s['items']) {
            $pkgsPriced++;
        }
    }
    $billing = fs_billing_map($cid);
    return [
        'consol_total'   => round($consolTotal, 2),
        'customer_total' => round($custTotal, 2),
        'pkgs'           => $pkgs,
        'pkgs_priced'    => $pkgsPriced,
        'billed'         => isset($billing[$sid]),
        'billable'       => !isset($billing[$sid]) && $pkgs > 0 && $pkgsPriced >= $pkgs,
        'consol'         => fs_consol_summary($cid),
    ];
}

/**
 * Bill breakdown for one customer: per-package prices, plus ONE tiered
 * handling fee determined by the customer's TOTAL payable across all their
 * packages (cdp_handlingFeeGhs on the summed GHS value).
 */
function fs_bill_breakdown($cid, $sid)
{
    $core = new Core;
    $rate = (float) $core->exchange_rate;
    $groups = fs_customer_groups($cid);
    if (!isset($groups[$sid]) || !$groups[$sid]['rows']) {
        return null;
    }
    $packages = [];
    $usd = 0.0;
    $subGhs = 0.0;
    foreach ($groups[$sid]['rows'] as $p) {
        $pUsd = (float) $p->total_order;
        $pGhs = round(cdp_usdToGhs($pUsd, $rate), 2);
        $packages[] = [
            'oid' => (int) $p->oid,
            'no'  => (string) ($p->order_prefix . $p->order_no),
            'usd' => round($pUsd, 2),
            'ghs' => $pGhs,
        ];
        $usd    += $pUsd;
        $subGhs += $pGhs;
    }
    $feeGhs = round(cdp_handlingFeeGhs($subGhs), 2);
    return [
        'packages'  => $packages,
        'usd'       => round($usd, 2),
        'sub_ghs'   => round($subGhs, 2),
        'fee_ghs'   => $feeGhs,
        'total_ghs' => round($subGhs + $feeGhs, 2),
        'group'     => $groups[$sid],
    ];
}

/** Consolidation-level live summary (due incl. fees, customers priced, received). */
function fs_consol_summary($cid)
{
    $cid = (int) $cid;
    $core = new Core;
    $rate = (float) $core->exchange_rate;
    $groups = fs_customer_groups($cid);
    $base = 0.0;      // sum of package totals (USD, before fees)
    $feesGhs = 0.0;   // one handling fee per customer, from their TOTAL payable
    $allOids = [];
    foreach ($groups as $g) {
        $base += $g['total'];
        $feesGhs += cdp_handlingFeeGhs(cdp_usdToGhs((float) $g['total'], $rate));
        foreach ($g['oids'] as $o) {
            $allOids[] = $o;
        }
    }
    $feeUsd = cdp_ghsToUsd($feesGhs, $rate);
    $stats = fs_item_stats($allOids);
    $custs = 0;
    $priced = 0;
    foreach ($groups as $g) {
        $custs++;
        $ok = true;
        foreach ($g['oids'] as $oid) {
            $s = isset($stats[$oid]) ? $stats[$oid] : null;
            if ($s && $s['priced'] < $s['items']) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $priced++;
        }
    }
    $db = new Conexion;
    $db->cdp_query("SELECT COALESCE(SUM(paid_ghs),0) AS p FROM cdb_consolidate_customer_billing
                    WHERE consolidate_id = :cid AND paid_ghs IS NOT NULL");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $pRow = $db->cdp_registro();
    $paidGhs = $pRow ? round((float) $pRow->p, 2) : 0.0;
    return [
        'base_usd'     => round($base, 2),
        'fee_usd'      => round($feeUsd, 2),
        'due_usd'      => round($base + $feeUsd, 2),
        'custs'        => $custs,
        'custs_priced' => $priced,
        'paid_ghs'     => $paidGhs,
        // Consolidation-level "Received" is displayed in USD.
        'paid_usd'     => round(cdp_ghsToUsd($paidGhs, $rate), 2),
    ];
}

/** Pricing is locked for a (consolidation, customer) pair once it is in the
 *  billing ledger. is_consolidate is NOT used here — packages can leave a
 *  consolidation for other reasons without ever being billed. */
function fs_customer_billed($cid, $sid)
{
    $map = fs_billing_map($cid);
    return isset($map[(int) $sid]) ? $map[(int) $sid] : null;
}

/** Sum of a customer's recorded payments (GHS) for a consolidation. Resilient
 *  if the ledger table isn't migrated yet (returns 0). */
function fs_paid_total($cid, $sid)
{
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT COALESCE(SUM(amount_ghs),0) AS t FROM cdb_fs_payments
                        WHERE consolidate_id = :cid AND sender_id = :sid");
        $db->bind(':cid', (int) $cid);
        $db->bind(':sid', (int) $sid);
        $db->cdp_execute();
        $r = $db->cdp_registro();
        return $r ? round((float) $r->t, 2) : 0.0;
    } catch (Throwable $e) {
        return 0.0;
    }
}

/** Sum of a customer's applied discounts (GHS) for a consolidation. */
function fs_discount_total($cid, $sid)
{
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT COALESCE(SUM(amount_ghs),0) AS t FROM cdb_fs_discounts
                        WHERE consolidate_id = :cid AND sender_id = :sid");
        $db->bind(':cid', (int) $cid);
        $db->bind(':sid', (int) $sid);
        $db->cdp_execute();
        $r = $db->cdp_registro();
        return $r ? round((float) $r->t, 2) : 0.0;
    } catch (Throwable $e) {
        return 0.0;
    }
}

/** Accumulative outstanding balance for a customer across ALL of their billed
 *  consolidations (GHS): Σ max(0, bill − discount − paid). This is the debt
 *  signal finance sees on the customer accordion / Customer Actions. */
function fs_customer_outstanding($sid)
{
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT COALESCE(SUM(GREATEST(0,
                                COALESCE(amount_ghs,0) - COALESCE(discount_ghs,0) - COALESCE(paid_ghs,0))),0) AS owed
                        FROM cdb_consolidate_customer_billing WHERE sender_id = :sid");
        $db->bind(':sid', (int) $sid);
        $db->cdp_execute();
        $r = $db->cdp_registro();
        return $r ? round((float) $r->owed, 2) : 0.0;
    } catch (Throwable $e) {
        return 0.0;
    }
}

/** Recompute the denormalized paid/discount caches on the billing row from the
 *  two ledgers (called after any payment/discount mutation). Keeps the fast
 *  list/summary queries reading a single column instead of re-SUMming. */
function fs_sync_billing_cache($cid, $sid, $uid = null)
{
    $paid = fs_paid_total($cid, $sid);
    $disc = fs_discount_total($cid, $sid);
    $db = new Conexion;
    // paid_by/paid_at reflect the LAST payment; only touch them when there is
    // at least one payment, so a discount-only change doesn't fake a payment.
    if ($paid > 0 && $uid !== null) {
        $db->cdp_query("UPDATE cdb_consolidate_customer_billing
                        SET paid_ghs = :p, discount_ghs = :d, paid_by = :by, paid_at = NOW()
                        WHERE consolidate_id = :cid AND sender_id = :sid");
        $db->bind(':by', (int) $uid);
    } else {
        $db->cdp_query("UPDATE cdb_consolidate_customer_billing
                        SET paid_ghs = :p, discount_ghs = :d
                        WHERE consolidate_id = :cid AND sender_id = :sid");
    }
    $db->bind(':p', $paid > 0 ? $paid : null);
    $db->bind(':d', $disc);
    $db->bind(':cid', (int) $cid);
    $db->bind(':sid', (int) $sid);
    $db->cdp_execute();
    return ['paid' => $paid, 'discount' => $disc];
}

// ----------------------------------------------------------------------------
// Shared HTML renderers
// ----------------------------------------------------------------------------

/** The single weight/custom pricing control (used by item rows and group cards). */
function fs_render_price_ctl($kind, $key, $mode, $value, $editable)
{
    $isCustom = ($mode === 'custom');
    $dis      = $editable ? '' : 'disabled';
    $valAttr  = ($value === '' || $value === null) ? '' : htmlspecialchars((string) $value);
    ob_start();
    ?>
    <div class="fs-price-ctl" data-kind="<?php echo htmlspecialchars($kind); ?>" data-key="<?php echo htmlspecialchars((string) $key); ?>">
        <div class="btn-group btn-group-sm fs-mode" role="group">
            <button type="button" class="btn <?php echo $isCustom ? 'btn-outline-secondary' : 'btn-secondary'; ?>"
                    onclick="fsSetMode(this, 'weight')" <?php echo $dis; ?>>Weight</button>
            <button type="button" class="btn <?php echo $isCustom ? 'btn-success' : 'btn-outline-success'; ?>"
                    onclick="fsSetMode(this, 'custom')" <?php echo $dis; ?>>Custom</button>
        </div>
        <input type="hidden" class="fs-mode-val" value="<?php echo $isCustom ? 'custom' : 'weight'; ?>">
        <!-- Currency toggle mirrors the courier_add/edit discount-type toggle. -->
        <span class="btn-group btn-group-sm fs-curs" role="group" aria-label="Entry currency"
              <?php echo $isCustom ? '' : 'style="display:none;"'; ?>>
            <button type="button" class="btn btn-dark py-0 px-2 fs-cur-btn active" data-cur="usd"
                    onclick="fsToggleCur(this, 'usd')" <?php echo $dis; ?>>$</button>
            <button type="button" class="btn btn-outline-dark py-0 px-2 fs-cur-btn" data-cur="ghs"
                    onclick="fsToggleCur(this, 'ghs')" <?php echo $dis; ?>>&#8373;</button>
        </span>
        <div class="input-group input-group-sm fs-valgrp">
            <input type="text" class="form-control fs-value" data-cur="usd"
                   value="<?php echo $valAttr; ?>"
                   placeholder="<?php echo $isCustom ? 'USD' : 'weight'; ?>"
                   onkeypress="return fsIsNumber(event)" onkeyup="fsLiveEquiv(this)" <?php echo $dis; ?>>
            <div class="input-group-append fs-unit" <?php echo $isCustom ? 'style="display:none;"' : ''; ?>>
                <span class="input-group-text">lb</span>
            </div>
        </div>
        <small class="fs-equiv text-muted d-block"></small>
    </div>
    <?php
    return ob_get_clean();
}

/** Live USD→GHS rate (cached per request) — used only for USD-view estimates of
 *  values that have no stored per-transaction rate (package/consolidation
 *  totals). Billed amounts use their own stored rate for audit stability. */
function fs_live_rate()
{
    static $r = null;
    if ($r === null) {
        $c = new Core;
        $r = (float) $c->exchange_rate;
    }
    return $r > 0 ? $r : 1.0;
}

/** One customer tier (accordion level 2). $bodyHtml pre-fills the body (search). */
function fs_render_customer($cid, array $g, array $stats, $billing, $bodyHtml = null)
{
    $sid = (int) $g['sender_id'];
    // Package-level progress: a package counts as priced once ALL its items
    // are priced (a package with no items doesn't block).
    $pkgCount  = count($g['oids']);
    $pkgPriced = 0;
    foreach ($g['oids'] as $oid) {
        $s = isset($stats[$oid]) ? $stats[$oid] : null;
        if (!$s || $s['priced'] >= $s['items']) {
            $pkgPriced++;
        }
    }
    $billedRow = ($billing && isset($billing[$sid])) ? $billing[$sid] : null;
    $ordersJson = htmlspecialchars(json_encode($g['orders']), ENT_QUOTES);

    $badgeCls = ($pkgCount > 0 && $pkgPriced >= $pkgCount) ? 'badge-success' : 'badge-warning';
    $pricedBadge = '<span class="badge ' . $badgeCls . ' fs-cust-priced">'
        . $pkgPriced . '/' . $pkgCount . ' pkgs priced</span>';

    // Avatar initials (first letter of the first two name words).
    $initials = '';
    foreach (preg_split('/\s+/', trim($g['name'])) as $w) {
        if ($w !== '' && mb_strlen($initials) < 2) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
        }
    }
    if ($initials === '') {
        $initials = '?';
    }

    $hasPaid = $billedRow && ($billedRow->paid_ghs !== null);
    $billGhs = 0.0;
    if ($billedRow) {
        $billGhs = ($billedRow->amount_ghs !== null)
            ? (float) $billedRow->amount_ghs
            : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true)['total'];
    }
    $canBill = $pkgCount > 0 && $pkgPriced >= $pkgCount;

    // Money state for a billed customer.
    //   $billGhs  gross bill — packages + handling fee (fs_bill_breakdown's
    //             total_ghs = sub_ghs + fee_ghs, so the fee is already inside it)
    //   $discGhs  discounts applied from Package Actions
    //   $netGhs   what the customer actually has to pay, after discounts
    //   $paidGhs  what they have paid so far (sum of split payments)
    //   $owingGhs the difference when they have not paid the full net — i.e. the
    //             debt. This is the single money figure Customer Actions shows.
    $discGhs  = $billedRow ? (float) $billedRow->discount_ghs : 0.0;
    $paidGhs  = ($billedRow && $billedRow->paid_ghs !== null) ? (float) $billedRow->paid_ghs : 0.0;
    $netGhs   = round(max(0, $billGhs - $discGhs), 2);
    $owingGhs = round(max(0, $netGhs - $paidGhs), 2);
    $balGhs   = $owingGhs; // legacy alias — other blocks below still read $balGhs

    // Accumulative debt across ALL of this customer's consolidations. Distinct
    // from $owingGhs (this consolidation only) and used solely by the "Owes ₵X
    // total" chip on the customer row, not by Customer Actions.
    $owedGhs = fs_customer_outstanding($sid);

    // Per-package clearance / delivery state — drives which Customer Actions apply.
    $pkgTotal = count($g['oids']);
    $pkgClearedCt = 0;
    $pkgDeliveredCt = 0;
    if ($g['oids']) {
        $inOids = implode(',', array_map('intval', $g['oids']));
        $sdb = new Conexion;
        $sdb->cdp_query("SELECT COALESCE(SUM(fs_cleared_for_delivery = 1), 0) c,
                                COALESCE(SUM(status_courier = 8), 0) d
                         FROM cdb_add_order WHERE order_id IN ($inOids)");
        $sdb->cdp_execute();
        $sr = $sdb->cdp_registro();
        $pkgClearedCt   = (int) ($sr->c ?? 0);
        $pkgDeliveredCt = (int) ($sr->d ?? 0);
    }
    $allCleared   = ($pkgTotal > 0 && $pkgClearedCt >= $pkgTotal);
    $allDelivered = ($pkgTotal > 0 && $pkgDeliveredCt >= $pkgTotal);

    ob_start();
    ?>
    <div class="card mb-2 fs-cust-card" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>">
        <div class="card-header fs-cust-header p-2" onclick="fsToggleCustomer(this, event)">
            <span class="fs-avatar"><?php echo htmlspecialchars($initials); ?><?php if ($hasPaid): ?><span class="fs-avatar-billed" title="Payment received — expand for details."><i class="mdi mdi-currency-usd"></i></span><?php endif; ?></span>
            <b><?php echo htmlspecialchars($g['label']); ?></b>
            <span class="ml-2"><?php echo $pricedBadge; ?></span>
            <?php
            // USD equivalents use this customer's OWN billing rate (audit-stable),
            // falling back to the live rate only if a bill predates rate capture.
            $custRate = ($billedRow && (float) $billedRow->exchange_rate > 0) ? (float) $billedRow->exchange_rate : fs_live_rate();
            $ghsUsd = function ($ghs) use ($custRate) { return $custRate > 0 ? (float) $ghs / $custRate : 0.0; };
            ?>
            <?php if ($billedRow): ?>
                <?php if ($discGhs > 0): ?>
                    <span class="fs-chip-discount" title="Discount applied (Customer Actions)">Discount <span class="fs-cur-chip" data-ghs="<?php echo $discGhs; ?>" data-usd="<?php echo round($ghsUsd($discGhs), 2); ?>">&#8373;<?php echo number_format($discGhs, 2); ?></span></span>
                <?php endif; ?>
                <?php // Paid / Balance / Settled only appear once a payment exists. ?>
                <?php if ($hasPaid): ?>
                    <span class="fs-chip-paid" title="Amount paid by the customer (all payments)">Paid <span class="fs-cur-chip" data-ghs="<?php echo $paidGhs; ?>" data-usd="<?php echo round($ghsUsd($paidGhs), 2); ?>">&#8373;<?php echo number_format($paidGhs, 2); ?></span></span>
                    <?php if ($balGhs > 0): ?>
                        <span class="fs-chip-balance" title="Outstanding balance for this consolidation">Balance <span class="fs-cur-chip" data-ghs="<?php echo $balGhs; ?>" data-usd="<?php echo round($ghsUsd($balGhs), 2); ?>">&#8373;<?php echo number_format($balGhs, 2); ?></span></span>
                    <?php else: ?>
                        <span class="fs-chip-settled" title="Fully paid"><i class="mdi mdi-check"></i> Settled</span>
                    <?php endif; ?>
                    <?php if ($owedGhs > 0): ?>
                        <span class="fs-chip-owed" title="Total outstanding across ALL of this customer's consolidations"><i class="mdi mdi-alert-circle-outline"></i> Owes <span class="fs-cur-chip" data-ghs="<?php echo $owedGhs; ?>" data-usd="<?php echo round($ghsUsd($owedGhs), 2); ?>">&#8373;<?php echo number_format($owedGhs, 2); ?></span> total</span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <span class="fs-spacer"></span>
            <span class="fs-money fs-cust-total" data-usd="<?php echo (float) $g['total']; ?>">$<?php echo number_format((float) $g['total'], 2); ?></span>
            <?php if (!$billedRow): ?>
                <button type="button" class="btn btn-sm btn-primary fs-bill-btn ml-1 fs-actions"
                        data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                        data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                        data-pkgs="<?php echo $pkgCount; ?>"
                        <?php echo $canBill ? '' : 'disabled title="All packages must be fully priced before billing"'; ?>
                        onclick="event.stopPropagation(); fsBillCustomer(this);">
                    <i class="mdi mdi-cash-check"></i> Bill
                </button>
            <?php else: ?>
            <div class="btn-group btn-group-sm fs-actions ml-1">
                <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">Customer Actions</button>
                <div class="dropdown-menu dropdown-menu-right">
                        <?php // One money figure only: what the customer OWES.
                              // owing = everything they must pay (packages + handling fee,
                              // already both inside amount_ghs) minus discounts, minus what
                              // they have actually paid. $balGhs is exactly that. The old
                              // header showed two competing numbers ("Balance (this
                              // consolidation)" and "Total owed") and only when they had
                              // paid something, so a billed customer who had paid nothing
                              // was shown no figure at all. ?>
                        <?php if ($owingGhs > 0): ?>
                        <h6 class="dropdown-header text-danger">
                            Owing: &#8373;<?php echo number_format($owingGhs, 2); ?>
                        </h6>
                        <div class="dropdown-divider"></div>
                        <?php endif; ?>

                        <?php // Per-package payment flow, while packages are still uncleared. ?>
                        <?php if (!$allDelivered && !$allCleared): ?>
                            <a class="dropdown-item fs-pay-btn" href="javascript:void(0)"
                               data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                               data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                               data-bill="<?php echo $netGhs; ?>"
                               data-paid="<?php echo $paidGhs; ?>"
                               data-balance="<?php echo $owingGhs; ?>"
                               onclick="fsRecordPayment(this);">
                                <i class="mdi mdi-cash-multiple"></i> <?php echo $hasPaid ? 'Update Payment' : 'Record Payment'; ?>
                            </a>
                        <?php endif; ?>

                        <?php // Clear Debt: the ONLY condition is that the customer is owing.
                              // Not gated on clearance or delivery — if they owe, it shows. ?>
                        <?php if ($owingGhs > 0): ?>
                            <a class="dropdown-item fs-debt-btn text-danger" href="javascript:void(0)"
                               data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                               data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                               data-balance="<?php echo $owingGhs; ?>"
                               onclick="fsClearDebt(this);">
                                <i class="mdi mdi-cash-refund"></i> Clear Debt (&#8373;<?php echo number_format($owingGhs, 2); ?>)
                            </a>
                        <?php endif; ?>

                        <a class="dropdown-item fs-disc-apply" href="javascript:void(0)"
                           data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                           data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                           data-bill="<?php echo $billGhs; ?>"
                           data-disc="<?php echo $discGhs; ?>"
                           onclick="fsApplyDiscount(this);">
                            <i class="mdi mdi-sale"></i> <?php echo $discGhs > 0 ? 'Edit Discount' : 'Apply Discount'; ?>
                        </a>
                        <?php if ($discGhs > 0): ?>
                        <a class="dropdown-item fs-disc-remove text-danger" href="javascript:void(0)"
                           data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                           onclick="fsRemoveDiscount(this);">
                            <i class="mdi mdi-close-circle-outline"></i> Remove Discount
                        </a>
                        <?php endif; ?>
                        <a class="dropdown-item fs-history-btn" href="javascript:void(0)"
                           data-sid="<?php echo $sid; ?>"
                           data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                           onclick="fsPaymentHistory(this);">
                            <i class="mdi mdi-history"></i> Payment History
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php if (!$allDelivered): ?>
                        <a class="dropdown-item fs-bill-btn" href="javascript:void(0)"
                           data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                           data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                           data-pkgs="<?php echo $pkgCount; ?>" data-rebill="1"
                           onclick="fsBillCustomer(this);">
                            <i class="mdi mdi-refresh"></i> Re-bill
                        </a>
                        <?php endif; ?>
                        <a class="dropdown-item" href="javascript:void(0)"
                           data-orders='<?php echo $ordersJson; ?>'
                           onclick="fsPrintShipments(this);">
                            <i class="mdi mdi-printer"></i> Print Shipment
                        </a>
                </div>
            </div>
            <?php endif; ?>
            <i class="mdi mdi-chevron-down fs-caret ml-2"></i>
        </div>
        <div class="fs-cust-body" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>" style="display:none;">
            <div class="card-body p-2 fs-packages" data-loaded="<?php echo $bodyHtml === null ? '0' : '1'; ?>">
                <?php echo $bodyHtml === null ? '<div class="text-muted small">Loading packages…</div>' : $bodyHtml; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Whether the current user may manually clear packages for delivery (cached). */
function fs_user_can_clear()
{
    static $can = null;
    if ($can === null) {
        $u = new User();
        $can = (bool) $u->cdp_hasPermission('fs_clear_delivery');
    }
    return $can;
}

/** One package card (accordion level 3). $cleared = this package has been paid
 *  for and cleared for delivery (per-package payment status). */
function fs_render_package($p, $stat = null, $cleared = false)
{
    $oid   = (int) $p->oid;
    $pkgNo = htmlspecialchars(($p->order_prefix ?? '') . ($p->order_no ?? ''));
    $itemsBadge = '';
    if ($stat) {
        $cls = ($stat['items'] > 0 && $stat['priced'] >= $stat['items']) ? 'badge-success' : 'badge-light';
        $itemsBadge = '<span class="badge ' . $cls . ' ml-2 fs-pkg-priced">' . (int) $stat['priced'] . '/' . (int) $stat['items'] . ' priced</span>';
    }
    // Live weight from the order (courier_edit changes show immediately);
    // the consolidation-time snapshot is only a fallback.
    $w = (float) ($p->pkg_weight ?? 0);
    if ($w <= 0) {
        $w = (float) $p->detail_weight;
    }
    ob_start();
    ?>
    <div class="card mb-1 fs-pkg-card">
        <div class="card-header fs-pkg-header p-2" onclick="fsTogglePackage(this, <?php echo $oid; ?>, event)">
            <span class="fs-level-chip fs-chip-pkg">PACKAGE</span>
            <i class="mdi mdi-package-variant-closed"></i>
            <b title="Swift (system) tracking"><?php echo $pkgNo; ?></b>
            <?php
            // Carrier / postal tracking alongside the Swift (system) number.
            $__pt = cdp_getPackageTrackingLegacyAware($oid);
            $__carrier = ($__pt && !empty($__pt->tracking_number)) ? (string) $__pt->tracking_number : '';
            ?>
            <?php if ($__carrier !== ''): ?>
                <span class="fs-dim ml-2" title="Carrier / postal tracking"><i class="mdi mdi-barcode"></i> <?php echo htmlspecialchars($__carrier); ?></span>
            <?php endif; ?>
            <span class="fs-dim ml-2" title="Package weight">
                <i class="mdi mdi-weight"></i> <?php echo round($w, 2); ?> lb
            </span>
            <?php echo $itemsBadge; ?>
            <?php
            // Delivered = out of our custody (status 8): no more package actions.
            $__st = new Conexion;
            $__st->cdp_query("SELECT status_courier FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
            $__st->bind(':oid', $oid);
            $__st->cdp_execute();
            $__strow = $__st->cdp_registro();
            $isDelivered = $__strow && (int) $__strow->status_courier === 8;
            ?>
            <?php if ($isDelivered): ?>
                <span class="fs-pkg-paid fs-chip-settled ml-2" title="Delivered — no longer in our custody"><i class="mdi mdi-truck-check-outline"></i> Delivered</span>
            <?php elseif ($cleared): ?>
                <span class="fs-pkg-paid fs-chip-settled ml-2" title="Paid for &amp; cleared for delivery"><i class="mdi mdi-check-decagram"></i> Paid</span>
            <?php else: ?>
                <span class="fs-pkg-unpaid fs-chip-balance ml-2" title="Not yet paid / cleared for delivery">Unpaid</span>
            <?php endif; ?>
            <span class="fs-spacer"></span>
            <span class="fs-money fs-pkg-total" data-usd="<?php echo (float) $p->total_order; ?>">$<?php echo number_format((float) $p->total_order, 2); ?></span>
            <?php if (fs_user_can_clear() && !$isDelivered): ?>
            <div class="btn-group btn-group-sm fs-pkg-actions ml-2">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Package Actions</button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php if (!$cleared): ?>
                    <a class="dropdown-item" href="javascript:void(0)" data-oid="<?php echo $oid; ?>" data-track="<?php echo $pkgNo; ?>" onclick="fsClearPackage(this);">
                        <i class="mdi mdi-truck-check"></i> Clear for Delivery
                    </a>
                    <?php else: ?>
                    <span class="dropdown-item text-muted"><i class="mdi mdi-check-decagram"></i> Cleared for Delivery</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <i class="mdi mdi-chevron-down fs-caret ml-2"></i>
        </div>
        <div class="fs-pkg-body" data-oid="<?php echo $oid; ?>" style="display:none;">
            <div class="card-body p-2 fs-items" data-loaded="0">
                <div class="text-muted small">Loading items…</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Financial-sheet change log for one package. */
function fs_render_change_log($order_id)
{
    $db = new Conexion;
    $db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', (int) $order_id);
    $db->cdp_execute();
    $ordRow   = $db->cdp_registro();
    $ordTrack = $ordRow ? ($ordRow->order_prefix . $ordRow->order_no) : '';

    $db->cdp_query("SELECT h.action, h.date_history,
                           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', h.user_id)) AS uname
                    FROM cdb_order_user_history h
                    LEFT JOIN cdb_users u ON u.id = h.user_id
                    WHERE h.order_track = :track AND h.order_id = :oid AND h.action LIKE 'Financial Sheet%'
                    ORDER BY h.history_id DESC LIMIT 12");
    $db->bind(':track', $ordTrack);
    $db->bind(':oid', (int) $order_id);
    $db->cdp_execute();
    $hist = $db->cdp_registros();

    ob_start();
    ?>
    <div class="fs-history">
        <div class="fs-history-title"><i class="mdi mdi-history"></i> Change log</div>
        <div class="fs-history-list">
            <?php if ($hist): foreach ($hist as $hh):
                $act = preg_replace('/^Financial Sheet\s*[—-]\s*/u', '', (string) $hh->action);
                ?>
                <div class="fs-hist-item"><b><?php echo htmlspecialchars($hh->uname); ?></b>
                    <?php echo htmlspecialchars($act); ?>
                    <span class="text-muted">— <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $hh->date_history))); ?></span>
                </div>
            <?php endforeach; else: ?>
                <div class="fs-hist-item fs-history-empty text-muted">No changes recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Log a financial-sheet action against a package (who did what, when). */
function fs_log_history($uid, $order_id, $what)
{
    if (!function_exists('cdp_insertCourierShipmentUserHistory')) {
        return null;
    }
    $db = new Conexion;
    $db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', (int) $order_id);
    $db->cdp_execute();
    $ord   = $db->cdp_registro();
    $track = $ord ? ($ord->order_prefix . $ord->order_no) : '';

    cdp_insertCourierShipmentUserHistory([
        'user_id'      => (int) $uid,
        'order_id'     => (int) $order_id,
        'order_track'  => $track,
        'action'       => 'Financial Sheet — ' . $what,
        'date_history' => date('Y-m-d H:i:s'),
    ]);
    return $track;
}

// ----------------------------------------------------------------------------
// LOCK HEARTBEAT / RELEASE  (JSON)
// ----------------------------------------------------------------------------
if ($action === 'lock' || $action === 'unlock') {
    header('Content-Type: application/json; charset=UTF-8');
    $order_id = (int) ($_REQUEST['order_id'] ?? 0);

    if ($action === 'unlock') {
        cdp_fsReleaseLock($order_id, $uid);
        echo json_encode(['ok' => true]);
        exit;
    }

    $res = cdp_fsAcquireLock($order_id, $uid, $uname);
    echo json_encode($res['ok'] ? ['ok' => true] : ['ok' => false, 'by' => $res['by']]);
    exit;
}

// ----------------------------------------------------------------------------
// CLEAR PACKAGE FOR DELIVERY (JSON) — manual per-package clearance from the
// Package Actions menu (fs_cleared_for_delivery = 1, mirrors the payment-time
// clearance). Gated by fs_clear_delivery.
// ----------------------------------------------------------------------------
if ($action === 'clear_package') {
    header('Content-Type: application/json; charset=UTF-8');
    $order_id = (int) ($_POST['order_id'] ?? 0);
    if ($order_id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'No package specified.']);
        exit;
    }

    $db->cdp_query("SELECT fs_cleared_for_delivery FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    if (!$row) {
        echo json_encode(['ok' => false, 'message' => 'Package not found.']);
        exit;
    }
    if ((int) $row->fs_cleared_for_delivery === 1) {
        echo json_encode(['ok' => true, 'already' => true]);
        exit;
    }

    $db->cdp_query("UPDATE cdb_add_order
                    SET fs_cleared_for_delivery = 1, fs_cleared_at = NOW(), fs_cleared_by = :by, status_invoice = 1
                    WHERE order_id = :oid");
    $db->bind(':by', $uid);
    $db->bind(':oid', $order_id);
    $db->cdp_execute();

    fs_log_history($uid, $order_id, 'package cleared for delivery (manual)');
    echo json_encode(['ok' => true]);
    exit;
}

// ----------------------------------------------------------------------------
// SAVE ITEM / SAVE GROUP / CLEAR GROUP  (JSON)
// ----------------------------------------------------------------------------
if ($action === 'save_item' || $action === 'save_group' || $action === 'clear_group') {
    header('Content-Type: application/json; charset=UTF-8');

    $order_id = (int) ($_REQUEST['order_id'] ?? 0);
    $cid      = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid      = (int) ($_REQUEST['sender_id'] ?? 0);

    if ($order_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    // NOTE: pricing stays editable even after billing — re-pricing is allowed
    // (each change is logged); the operator re-bills to refresh the amount.
    $lock = cdp_fsAcquireLock($order_id, $uid, $uname);
    if (!$lock['ok']) {
        echo json_encode(['ok' => false, 'error' => 'locked', 'by' => $lock['by']]);
        exit;
    }

    // ---- clear_group: dissolve a group; members return to UNPRICED so the
    // operator re-prices each explicitly (no silent total changes). -----------
    if ($action === 'clear_group') {
        $token = trim((string) ($_REQUEST['group'] ?? ''));
        if ($token === '') {
            echo json_encode(['ok' => false, 'error' => 'bad_request']);
            exit;
        }
        $db->cdp_query("SELECT COUNT(*) AS n FROM cdb_add_order_item WHERE order_id = :oid AND order_item_weight_group = :g");
        $db->bind(':oid', $order_id);
        $db->bind(':g', $token);
        $db->cdp_execute();
        $nRow = $db->cdp_registro();
        $n = $nRow ? (int) $nRow->n : 0;

        $db->cdp_query("UPDATE cdb_add_order_item
                        SET order_item_weight_group = NULL, order_item_weight = 0,
                            custom_price = NULL, priced_at = NULL
                        WHERE order_id = :oid AND order_item_weight_group = :g");
        $db->bind(':oid', $order_id);
        $db->bind(':g', $token);
        $db->cdp_execute();

        $totals = cdp_recalcCourierShipmentTotals($order_id);
        fs_log_history($uid, $order_id, 'ungrouped ' . $n . ' item(s) — pricing reset');

        $pstat = fs_item_stats([$order_id]);
        echo json_encode([
            'ok'            => true,
            'package_total' => $totals ? $totals['total_order'] : null,
            'pkg_stat'      => $pstat[$order_id] ?? null,
            'aggregates'    => fs_aggregates($cid, $sid),
        ]);
        exit;
    }

    // ---- save_item / save_group share value parsing. -------------------------
    $mode  = (($_REQUEST['mode'] ?? 'weight') === 'custom') ? 'custom' : 'weight';
    $value = (float) str_replace(',', '', (string) ($_REQUEST['value'] ?? ''));
    $rawIn = trim((string) ($_REQUEST['value'] ?? ''));
    $currency = (strtolower((string) ($_REQUEST['currency'] ?? 'usd')) === 'ghs') ? 'ghs' : 'usd';

    if ($rawIn === '' || !is_numeric(str_replace(',', '', $rawIn)) || $value < 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter a value of 0 or more.']);
        exit;
    }
    if ($mode === 'custom' && $currency === 'ghs' && $value > 0) {
        $value = round(cdp_ghsToUsd($value, (float) $core->exchange_rate), 2);
    }

    if ($action === 'save_item') {
        $order_item_id = (int) ($_REQUEST['order_item_id'] ?? 0);
        if ($order_item_id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'bad_request']);
            exit;
        }

        // Grouped items are priced through their group, never individually.
        $db->cdp_query("SELECT order_item_weight_group, order_item_description FROM cdb_add_order_item
                        WHERE order_item_id = :iid AND order_id = :oid LIMIT 1");
        $db->bind(':iid', $order_item_id);
        $db->bind(':oid', $order_id);
        $db->cdp_execute();
        $itRow = $db->cdp_registro();
        if (!$itRow) {
            echo json_encode(['ok' => false, 'error' => 'bad_request']);
            exit;
        }
        if (trim((string) ($itRow->order_item_weight_group ?? '')) !== '') {
            echo json_encode(['ok' => false, 'error' => 'grouped', 'message' => 'This item is priced through its group.']);
            exit;
        }

        if ($mode === 'custom') {
            $db->cdp_query("UPDATE cdb_add_order_item SET custom_price = :cp, order_item_weight = 0, priced_at = NOW()
                            WHERE order_item_id = :iid AND order_id = :oid");
            $db->bind(':cp', $value);
        } else {
            $db->cdp_query("UPDATE cdb_add_order_item SET order_item_weight = :w, custom_price = NULL, priced_at = NOW()
                            WHERE order_item_id = :iid AND order_id = :oid");
            $db->bind(':w', $value);
        }
        $db->bind(':iid', $order_item_id);
        $db->bind(':oid', $order_id);
        $db->cdp_execute();

        $totals = cdp_recalcCourierShipmentTotals($order_id);

        $desc = trim((string) ($itRow->order_item_description ?? ''));
        if ($desc === '') {
            $desc = 'item';
        }
        $what = ($mode === 'custom')
            ? 'set "' . $desc . '" custom price to $' . number_format($value, 2) . ' USD'
            : 'set "' . $desc . '" weight to ' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' lb';
        fs_log_history($uid, $order_id, $what);

        $pstat = fs_item_stats([$order_id]);
        echo json_encode([
            'ok'            => true,
            'usd_value'     => $value,
            'package_total' => $totals ? $totals['total_order'] : null,
            'pkg_stat'      => $pstat[$order_id] ?? null,
            'aggregates'    => fs_aggregates($cid, $sid),
            'history'       => ['who' => $uname, 'what' => $what, 'when' => date('Y-m-d H:i')],
        ]);
        exit;
    }

    // ---- save_group: create a new group from item ids, or re-price an
    // existing one (token). The shared value covers the whole batch. ----------
    $token = trim((string) ($_REQUEST['group'] ?? ''));
    if ($token === '') {
        $ids = $_REQUEST['item_ids'] ?? '';
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) < 2) {
            echo json_encode(['ok' => false, 'error' => 'bad_request', 'message' => 'Select at least two items to group.']);
            exit;
        }
        $in = implode(',', $ids);
        $db->cdp_query("SELECT COUNT(*) AS n FROM cdb_add_order_item
                        WHERE order_id = :oid AND order_item_id IN ($in)
                          AND (order_item_weight_group IS NULL OR order_item_weight_group = '')");
        $db->bind(':oid', $order_id);
        $db->cdp_execute();
        $vRow = $db->cdp_registro();
        if (($vRow ? (int) $vRow->n : 0) !== count($ids)) {
            echo json_encode(['ok' => false, 'error' => 'bad_request', 'message' => 'One or more items are already grouped.']);
            exit;
        }
        $token = 'grp_' . substr(md5(uniqid('', true)), 0, 10);
        $memberWhere = "order_item_id IN ($in)"; // $in is sanitized ints
        $isExisting  = false;
        $count = count($ids);
    } else {
        $db->cdp_query("SELECT COUNT(*) AS n FROM cdb_add_order_item WHERE order_id = :oid AND order_item_weight_group = :g");
        $db->bind(':oid', $order_id);
        $db->bind(':g', $token);
        $db->cdp_execute();
        $cRow  = $db->cdp_registro();
        $count = $cRow ? (int) $cRow->n : 0;
        if ($count < 1) {
            echo json_encode(['ok' => false, 'error' => 'bad_request', 'message' => 'Group not found.']);
            exit;
        }
        $memberWhere = "order_item_weight_group = :gsel";
        $isExisting  = true;
    }

    $setValue = ($mode === 'custom')
        ? "custom_price = :v, order_item_weight = 0"
        : "order_item_weight = :v, custom_price = NULL";
    $db->cdp_query("UPDATE cdb_add_order_item
                    SET order_item_weight_group = :g, $setValue, priced_at = NOW()
                    WHERE order_id = :oid AND $memberWhere");
    $db->bind(':g', $token);
    $db->bind(':v', $value);
    $db->bind(':oid', $order_id);
    if ($isExisting) {
        $db->bind(':gsel', $token);
    }
    $db->cdp_execute();

    $totals = cdp_recalcCourierShipmentTotals($order_id);

    $what = 'priced a group of ' . $count . ' item(s) together at '
        . ($mode === 'custom' ? '$' . number_format($value, 2) . ' USD' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' lb');
    fs_log_history($uid, $order_id, $what);

    $pstat = fs_item_stats([$order_id]);
    echo json_encode([
        'ok'            => true,
        'group'         => $token,
        'package_total' => $totals ? $totals['total_order'] : null,
        'pkg_stat'      => $pstat[$order_id] ?? null,
        'aggregates'    => fs_aggregates($cid, $sid),
        'history'       => ['who' => $uname, 'what' => $what, 'when' => date('Y-m-d H:i')],
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// BILL CUSTOMER  (JSON) — record the bill FIRST (authoritative), then notify
// best-effort. Billing is one-time-only per (consolidation, customer).
// ----------------------------------------------------------------------------
if ($action === 'bill_customer') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);
    if ($cid <= 0 || $sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    // Re-billing is allowed (e.g. after re-pricing): the ledger row is updated,
    // the action is logged and the customer is messaged again.
    $billing  = fs_billing_map($cid);
    $isRebill = isset($billing[$sid]);
    $prevGhs  = $isRebill && $billing[$sid]->amount_ghs !== null ? (float) $billing[$sid]->amount_ghs : null;

    $breakdown = fs_bill_breakdown($cid, $sid);
    if (!$breakdown) {
        echo json_encode(['ok' => false, 'error' => 'no_packages', 'message' => 'No packages for this customer in this consolidation.']);
        exit;
    }
    $pkgs  = $breakdown['group']['rows'];
    $total = $breakdown['usd'];

    // Server-side gate: every item must be priced (never trust the button).
    $oids = array_map(function ($p) { return (int) $p->oid; }, $pkgs);
    $stats = fs_item_stats($oids);
    $items = 0;
    $priced = 0;
    foreach ($stats as $s) {
        $items  += $s['items'];
        $priced += $s['priced'];
    }
    if ($priced < $items) {
        echo json_encode(['ok' => false, 'error' => 'unpriced',
            'message' => ($items - $priced) . ' item(s) are not priced yet. Price all items before billing.']);
        exit;
    }

    // ---- 1) Record the bill (ledger) — logs WHO billed. The GHS amount is a
    // snapshot at billing time; EACH package carries its own tiered handling
    // fee (see fs_bill_breakdown). -----------------------------------------------
    $billGhs = $breakdown['total_ghs'];
    $feeGhs  = $breakdown['fee_ghs'];

    $db->cdp_query("INSERT INTO cdb_consolidate_customer_billing
                        (consolidate_id, sender_id, amount_usd, amount_ghs, handling_ghs, exchange_rate, billed_by, billed_at)
                    VALUES (:cid, :sid, :amt, :ghs, :fee, :rate, :by, NOW())
                    ON DUPLICATE KEY UPDATE
                        amount_usd = VALUES(amount_usd), amount_ghs = VALUES(amount_ghs),
                        handling_ghs = VALUES(handling_ghs), exchange_rate = VALUES(exchange_rate),
                        billed_by = VALUES(billed_by), billed_at = VALUES(billed_at)");
    $db->bind(':cid', $cid);
    $db->bind(':sid', $sid);
    $db->bind(':amt', round($total, 2));
    $db->bind(':ghs', $billGhs);
    $db->bind(':fee', $feeGhs);
    $db->bind(':rate', (float) $core->exchange_rate);
    $db->bind(':by', $uid);
    $db->cdp_execute();

    // Refresh paid/discount caches from the ledgers (pre-existing package
    // discounts must reduce the net immediately after billing).
    fs_sync_billing_cache($cid, $sid, null);

    // ---- 2) First bill only: packages exit the consolidation and become
    // Ready for PickUp. A RE-bill must NOT repeat these side effects — it only
    // refreshes the amount, logs, and re-notifies. --------------------------------
    $trackList = [];
    foreach ($pkgs as $p) {
        $oid   = (int) $p->oid;
        $track = $p->order_prefix . $p->order_no;
        $trackList[] = $track;

        if (!$isRebill) {
            $db->cdp_query("UPDATE cdb_add_order SET is_consolidate = 0 WHERE order_id = :oid");
            $db->bind(':oid', $oid);
            $db->cdp_execute();

            cdp_pickupAgingSetStatus($oid, $track, CDP_PA_READY, 'Billed on Financial Sheet — Ready for PickUp', $uid);

            // Start the pickup-aging clock (mirrors consolidation status propagation).
            $db->cdp_query("INSERT INTO cdb_package_pickup_aging (order_id, order_track, sender_id, ready_at)
                            VALUES (:oid, :trk, :sid, NOW())
                            ON DUPLICATE KEY UPDATE order_track = VALUES(order_track)");
            $db->bind(':oid', $oid);
            $db->bind(':trk', $track);
            $db->bind(':sid', $sid);
            $db->cdp_execute();

            fs_log_history($uid, $oid, 'billed customer (₵' . number_format($billGhs, 2)
                . ' incl. ₵' . number_format($feeGhs, 2) . ' handling fee) — package moved to Ready for PickUp');
        } else {
            fs_log_history($uid, $oid, 're-billed customer — updated total ₵' . number_format($billGhs, 2)
                . ($prevGhs !== null ? ' (was ₵' . number_format($prevGhs, 2) . ')' : ''));
        }
    }

    // ---- 3) Notify best-effort (never blocks / undoes the bill). -------------
    $warnings   = [];
    $sentWa     = false;
    $sentEmail  = false;

    $db->cdp_query("SELECT id, fname, lname, locker, email, phone FROM cdb_users WHERE id = :sid LIMIT 1");
    $db->bind(':sid', $sid);
    $db->cdp_execute();
    $sender = $db->cdp_registro();

    $db->cdp_query("SELECT c_prefix, c_no FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $cRow = $db->cdp_registro();
    $consolNo = $cRow ? ($cRow->c_prefix . $cRow->c_no) : ('#' . $cid);

    $custName  = $sender ? trim((string) $sender->fname . ' ' . (string) $sender->lname) : 'Customer';
    $locker    = $sender ? trim((string) $sender->locker) : '';
    $pkgCount  = count($pkgs);
    $plural    = $pkgCount > 1;
    $subject   = $isRebill
        ? 'Your Updated Bill — Package' . ($plural ? 's' : '') . ' Ready for Pickup'
        : 'Your Package' . ($plural ? 's Are' : ' Is') . ' Ready for Pickup 🎉';

    // Two renderings of the same message: plain text with *bold* markers for
    // WhatsApp, and an HTML equivalent for the email body. Every amount is
    // gated by FS_BILL_MSG_INCLUDE_AMOUNT (per-package lines fall back to
    // tracking numbers only).
    $pkgLines = [];
    foreach ($breakdown['packages'] as $bp) {
        $pkgLines[] = FS_BILL_MSG_INCLUDE_AMOUNT
            ? $bp['no'] . ' — GH₵' . number_format($bp['ghs'], 2)
            : $bp['no'];
    }

    $amountLine = FS_BILL_MSG_INCLUDE_AMOUNT
        ? 'Subtotal: GH₵' . number_format($breakdown['sub_ghs'], 2) . "\n"
          . 'Handling Fee: GH₵' . number_format($feeGhs, 2) . "\n"
          . 'Total Amount Due: GH₵' . number_format($billGhs, 2)
          . ($isRebill && $prevGhs !== null && FS_BILL_MSG_INCLUDE_AMOUNT
              ? "\nPrevious Total: GH₵" . number_format($prevGhs, 2)
                . ' → Updated Total: GH₵' . number_format($billGhs, 2)
              : '')
        : '';

    $intro = $isRebill
        ? 'Please note: the bill for your package' . ($plural ? 's' : '') . ' in consolidation ' . $consolNo
          . ' has been updated. Here are the latest details.'
        : 'Great news! Your ' . ($plural ? $pkgCount . ' packages have' : 'package has')
          . ' arrived, been sorted, and ' . ($plural ? 'are' : 'is') . ' now ready for pickup at our office. 🎉';
    $outro1 = 'Please stop by during working hours to collect ' . ($plural ? 'them' : 'it')
        . ($locker !== '' ? ' — kindly have your locker ID (' . $locker . ') ready.' : '.');
    $outro2 = 'Thank you for shipping with us. We look forward to seeing you soon!';

    $msgWa = $intro . "\n\n"
        . "*Pickup Details*\n"
        . 'Consolidation: ' . $consolNo . "\n"
        . 'Package' . ($plural ? 's' : '') . ' (' . $pkgCount . "):\n"
        . '• ' . implode("\n• ", $pkgLines) . "\n"
        . ($amountLine !== '' ? "\n" . $amountLine . "\n" : '')
        . "\n" . $outro1 . "\n\n" . $outro2;

    $msgEmail = '<p>' . htmlspecialchars($intro) . '</p>'
        . '<p><b>Pickup Details</b><br>'
        . 'Consolidation: ' . htmlspecialchars($consolNo) . '<br>'
        . 'Package' . ($plural ? 's' : '') . ' (' . $pkgCount . '):</p>'
        . '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $pkgLines)) . '</li></ul>'
        . ($amountLine !== '' ? '<p><b>' . nl2br(htmlspecialchars($amountLine)) . '</b></p>' : '')
        . '<p>' . htmlspecialchars($outro1) . '</p>'
        . '<p>' . htmlspecialchars($outro2) . '</p>';

    if ($sender && trim((string) $sender->phone) !== '') {
        try {
            $waBody = $msgWa;
            if (function_exists('getTemplateWhatsApp')) {
                $tpl = getTemplateWhatsApp(12);
                if ($tpl) {
                    $waBody = str_replace(
                        ['[USERNAME]', '[SUBJECT]', '[SITE_NAME]', '[MESSAGE]', '[URL]'],
                        [ucfirst($custName), $subject, $core->site_name, $msgWa, $core->site_url],
                        $tpl->body
                    );
                }
            }
            // 'GH' hint: most stored phones are national 0-prefix Ghanaian numbers.
            $wa = sendNotificationWhatsApp_v2($sender, $waBody, 'GH');
            if (!empty($wa['success'])) {
                $sentWa = true;
            } else {
                $warnings[] = 'WhatsApp: ' . ($wa['message'] ?? 'not sent');
            }
        } catch (Throwable $e) {
            $warnings[] = 'WhatsApp: ' . $e->getMessage();
        }
    } else {
        $warnings[] = 'WhatsApp: no phone number on file.';
    }

    if ($sender && trim((string) $sender->email) !== '') {
        try {
            $res = cdp_sendTemplateEmail(29, trim((string) $sender->email), [
                '[USERNAME]' => $custName,
                '[SUBJECT]'  => $subject,
                '[MESSAGE]'  => $msgEmail,
            ], $subject . ' — ' . $core->site_name);
            if (!empty($res['ok'])) {
                $sentEmail = true;
            } else {
                $warnings[] = 'Email: ' . ($res['error'] ?? 'not sent');
            }
        } catch (Throwable $e) {
            $warnings[] = 'Email: ' . $e->getMessage();
        }
    } else {
        $warnings[] = 'Email: no email address on file.';
    }

    echo json_encode([
        'ok'            => true,
        'rebill'        => $isRebill,
        'prev_ghs'      => $prevGhs,
        'billed_by'     => $uname,
        'billed_at'     => date('Y-m-d H:i'),
        'amount_usd'    => round($total, 2),
        'amount_ghs'    => $billGhs,
        'sub_ghs'       => $breakdown['sub_ghs'],
        'handling_ghs'  => $feeGhs,
        'packages'      => $breakdown['packages'],
        'consol'        => fs_consol_summary($cid),
        'sent_whatsapp' => $sentWa,
        'sent_email'    => $sentEmail,
        'warnings'      => $warnings,
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// BILL PREVIEW (JSON) — per-package breakdown (price + handling fee) shown in
// the confirmation dialog before billing/re-billing.
// ----------------------------------------------------------------------------
if ($action === 'bill_preview') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);

    $breakdown = fs_bill_breakdown($cid, $sid);
    if (!$breakdown) {
        echo json_encode(['ok' => false, 'error' => 'no_packages', 'message' => 'No packages for this customer in this consolidation.']);
        exit;
    }
    $billing = fs_billing_map($cid);
    $prev = isset($billing[$sid]) && $billing[$sid]->amount_ghs !== null ? (float) $billing[$sid]->amount_ghs : null;

    echo json_encode([
        'ok'        => true,
        'rebill'    => isset($billing[$sid]),
        'prev_ghs'  => $prev,
        'packages'  => $breakdown['packages'],
        'usd'       => $breakdown['usd'],
        'sub_ghs'   => $breakdown['sub_ghs'],
        'fee_ghs'   => $breakdown['fee_ghs'],
        'total_ghs' => $breakdown['total_ghs'],
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// PAYMENT FORM (JSON) — data for the per-package payment checklist: the
// customer's packages (with GHS price + current cleared state), the one-time
// handling fee (if unpaid), and the running bill / paid / balance.
// ----------------------------------------------------------------------------
if ($action === 'payment_form') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);

    $billedRow = fs_customer_billed($cid, $sid);
    if (!$billedRow) {
        echo json_encode(['ok' => false, 'error' => 'not_billed', 'message' => 'Bill this customer first, then record their payment.']);
        exit;
    }

    $rate   = (float) $core->exchange_rate;
    $groups = fs_customer_groups($cid);
    if (!isset($groups[$sid])) {
        echo json_encode(['ok' => false, 'error' => 'no_packages', 'message' => 'No packages for this customer.']);
        exit;
    }

    $oids = array_map('intval', $groups[$sid]['oids']);
    $clearedNow = [];
    if ($oids) {
        $in = implode(',', $oids);
        $db->cdp_query("SELECT order_id, fs_cleared_for_delivery FROM cdb_add_order WHERE order_id IN ($in)");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $clearedNow[(int) $r->order_id] = (int) $r->fs_cleared_for_delivery;
        }
    }

    $packages = [];
    foreach ($groups[$sid]['rows'] as $p) {
        $oid = (int) $p->oid;
        $packages[] = [
            'oid'     => $oid,
            'no'      => (string) ($p->order_prefix . $p->order_no),
            'ghs'     => round(cdp_usdToGhs((float) $p->total_order, $rate), 2),
            'cleared' => !empty($clearedNow[$oid]),
        ];
    }

    $billGhs = ($billedRow->amount_ghs !== null)
        ? (float) $billedRow->amount_ghs
        : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true, $rate)['total'];
    $disc    = (float) $billedRow->discount_ghs;
    $paid    = ($billedRow->paid_ghs !== null) ? (float) $billedRow->paid_ghs : 0.0;
    $net     = round(max(0, $billGhs - $disc), 2);
    $feeGhs  = ($billedRow->handling_ghs !== null) ? (float) $billedRow->handling_ghs : 0.0;

    echo json_encode([
        'ok'          => true,
        'packages'    => $packages,
        'bill_ghs'    => round($billGhs, 2),
        'discount_ghs' => round($disc, 2),
        'net_ghs'     => $net,
        'paid_ghs'    => round($paid, 2),
        'balance_ghs' => round(max(0, $net - $paid), 2),
        'fee_ghs'     => $feeGhs,
        'fee_paid'    => ((int) ($billedRow->fee_paid ?? 0) === 1),
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// GATEWAY INIT (JSON) — start a Paystack/Hubtel checkout for an amount. Returns
// the checkout URL the customer completes + the reference to verify with. Falls
// back gracefully to "unconfigured" until the API keys are set in Settings.
// ----------------------------------------------------------------------------
if ($action === 'gateway_init') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid    = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid    = (int) ($_REQUEST['sender_id'] ?? 0);
    $mode   = strtolower(trim((string) ($_REQUEST['mode'] ?? '')));
    $amount = round((float) str_replace(',', '', (string) ($_REQUEST['amount'] ?? '0')), 2);

    if (!in_array($mode, ['paystack', 'hubtel'], true) || $amount <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Pick an online mode and enter an amount first.']);
        exit;
    }

    // Context for the gateway (customer email + return/callback URLs).
    $email = '';
    if ($sid > 0) {
        $db->cdp_query("SELECT email FROM cdb_users WHERE id = :sid LIMIT 1");
        $db->bind(':sid', $sid);
        $db->cdp_execute();
        $u = $db->cdp_registro();
        $email = $u ? trim((string) $u->email) : '';
    }
    $base = rtrim((string) $core->site_url, '/');
    $ctx = [
        'email'        => $email !== '' ? $email : null,
        'description'  => 'PackageSwiftLane payment',
        'callback_url' => $base . '/financial_sheet.php',
        'return_url'   => $base . '/financial_sheet.php',
        'cancel_url'   => $base . '/financial_sheet.php',
    ];
    echo json_encode(cdp_fsGatewayInit($mode, $amount, '', $ctx));
    exit;
}

// ----------------------------------------------------------------------------
// RECORD PAYMENT (JSON) — payment is now PER PACKAGE. The operator ticks which
// of the customer's packages this payment covers (all checked by default); the
// amount equals the SUM of the checked packages' GHS prices (+ the one-time
// handling fee on the first payment). Each checked package is marked paid and
// CLEARED FOR DELIVERY (fs_cleared_for_delivery + status_invoice=1) — you can't
// pick a package you didn't pay for. Split payments accumulate. Online modes
// are verified against the gateway. Best-effort receipt to the customer.
// ----------------------------------------------------------------------------
if ($action === 'record_payment') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);

    $mode = strtolower(trim((string) ($_REQUEST['mode'] ?? 'cash')));
    if (!in_array($mode, ['cash', 'paystack', 'hubtel', 'paypal'], true)) {
        $mode = 'cash';
    }
    $reference = trim((string) ($_REQUEST['reference'] ?? ''));
    $modeLbl   = ucfirst($mode);

    // Packages the customer is paying for now (checkbox list, all on by default).
    $rawOrders   = $_REQUEST['orders'] ?? '';
    $decoded     = is_array($rawOrders) ? $rawOrders : json_decode((string) $rawOrders, true);
    $checkedOids = is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];

    if ($cid <= 0 || $sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    $billedRow = fs_customer_billed($cid, $sid);
    if (!$billedRow) {
        echo json_encode(['ok' => false, 'error' => 'not_billed', 'message' => 'Bill this customer first, then record their payment.']);
        exit;
    }

    $billGhs = ($billedRow->amount_ghs !== null)
        ? (float) $billedRow->amount_ghs
        : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true, (float) $core->exchange_rate)['total'];

    // This customer's packages + their GHS prices.
    $rate   = (float) $core->exchange_rate;
    $groups = fs_customer_groups($cid);
    if (!isset($groups[$sid])) {
        echo json_encode(['ok' => false, 'error' => 'no_packages', 'message' => 'No packages for this customer.']);
        exit;
    }
    $custOids = array_map('intval', $groups[$sid]['oids']);
    $ghsByOid = [];
    foreach ($groups[$sid]['rows'] as $p) {
        $ghsByOid[(int) $p->oid] = round(cdp_usdToGhs((float) $p->total_order, $rate), 2);
    }

    // Current clearance state — never re-charge an already-cleared package.
    $clearedNow = [];
    if ($custOids) {
        $in = implode(',', $custOids);
        $db->cdp_query("SELECT order_id, fs_cleared_for_delivery FROM cdb_add_order WHERE order_id IN ($in)");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $clearedNow[(int) $r->order_id] = (int) $r->fs_cleared_for_delivery;
        }
    }
    $toClear = [];
    foreach ($checkedOids as $o) {
        if (in_array($o, $custOids, true) && empty($clearedNow[$o])) {
            $toClear[] = $o;
        }
    }
    // ---- Verify first (cash is a manual in-person receipt; online modes are
    // confirmed against the gateway). --------------------------------------------
    $verify = cdp_fsVerifyPayment($mode, $reference, null);
    if (empty($verify['ok'])) {
        echo json_encode(['ok' => false, 'error' => 'verify_failed',
                          'message' => $verify['message'] ?: 'Payment could not be verified.']);
        exit;
    }

    // Amount: CASH is entered by the operator (only way to record physical cash).
    // Electronic modes take the GATEWAY-CONFIRMED amount — never re-typed.
    if ($mode === 'cash') {
        $raw  = trim((string) ($_REQUEST['paid'] ?? ''));
        $paid = round((float) str_replace(',', '', $raw), 2);
        if ($raw === '' || !is_numeric(str_replace(',', '', $raw)) || $paid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter the amount received (greater than 0).']);
            exit;
        }
    } else {
        $paid = round((float) ($verify['amount'] ?? 0), 2);
        if ($paid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'gateway_no_amount',
                              'message' => 'The gateway did not report a paid amount for this reference.']);
            exit;
        }
    }

    // ---- Insert the payment row (records which packages it cleared). ----------
    $note = trim((string) ($_REQUEST['note'] ?? ''));
    try {
        $db->cdp_query("INSERT INTO cdb_fs_payments
                            (consolidate_id, sender_id, amount_ghs, mode, reference,
                             gateway_status, gateway_payload, cleared_for_delivery, cleared_orders, note,
                             exchange_rate, recorded_by, recorded_at)
                        VALUES
                            (:cid, :sid, :amt, :mode, :ref,
                             :gs, :gp, 1, :co, :note, :rate, :by, NOW())");
        $db->bind(':cid', $cid);
        $db->bind(':sid', $sid);
        $db->bind(':amt', $paid);
        $db->bind(':mode', $mode);
        $db->bind(':ref', $reference !== '' ? $reference : null);
        $db->bind(':gs', $verify['status'] ?? null);
        $db->bind(':gp', $verify['payload'] ?? null);
        $db->bind(':co', json_encode($toClear));
        $db->bind(':note', $note !== '' ? $note : null);
        $db->bind(':rate', (float) $core->exchange_rate);
        $db->bind(':by', $uid);
        $db->cdp_execute();
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'ledger_missing',
                          'message' => 'Payment ledger not found — run sql/fs_transactions.sql, then try again.']);
        exit;
    }

    // ---- Mark each paid package cleared for delivery + Paid (status_invoice=1)
    // so the existing invoice / package-list labels reflect it immediately. ------
    foreach ($toClear as $o) {
        $db->cdp_query("UPDATE cdb_add_order
                        SET fs_cleared_for_delivery = 1, fs_cleared_at = NOW(), fs_cleared_by = :by,
                            status_invoice = 1
                        WHERE order_id = :oid");
        $db->bind(':by', $uid);
        $db->bind(':oid', $o);
        $db->cdp_execute();
        fs_log_history($uid, $o, 'marked paid & cleared for delivery via ' . $modeLbl . ' payment'
            . ($reference !== '' ? ' (ref ' . $reference . ')' : ''));
    }

    // Internal-only note (never sent to the customer).
    if ($note !== '') {
        try {
            $db->cdp_query("INSERT INTO cdb_consolidate_billing_notes
                                (consolidate_id, sender_id, note, created_by, created_at)
                            VALUES (:cid, :sid, :note, :by, NOW())");
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->bind(':note', $note);
            $db->bind(':by', $uid);
            $db->cdp_execute();
        } catch (Throwable $e) {
            // Notes table missing — payment still records.
        }
    }

    // Recompute the billing-row caches (paid_ghs / discount_ghs) from ledgers.
    $agg     = fs_sync_billing_cache($cid, $sid, $uid);
    $paidTot = $agg['paid'];
    $discTot = $agg['discount'];
    $netGhs  = round(max(0, $billGhs - $discTot), 2);
    $balance = round(max(0, $netGhs - $paidTot), 2);

    // ---- Receipt message, best-effort. ---------------------------------------
    $warnings = [];
    $sentWa = false;
    $sentEmail = false;

    $db->cdp_query("SELECT id, fname, lname, locker, email, phone FROM cdb_users WHERE id = :sid LIMIT 1");
    $db->bind(':sid', $sid);
    $db->cdp_execute();
    $sender = $db->cdp_registro();

    $db->cdp_query("SELECT c_prefix, c_no FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $cRow = $db->cdp_registro();
    $consolNo = $cRow ? ($cRow->c_prefix . $cRow->c_no) : ('#' . $cid);

    $custName = $sender ? trim((string) $sender->fname . ' ' . (string) $sender->lname) : 'Customer';
    $subject  = 'Payment Received — Thank You!';

    $amountBlock = FS_BILL_MSG_INCLUDE_AMOUNT
        ? 'Amount Paid: GH₵' . number_format($paid, 2)
          . ($balance > 0 ? "\nBalance Remaining: GH₵" . number_format($balance, 2) : "\nYour account is now fully settled.")
        : '';

    // List the packages this payment covers — "system tracking - price" — rather
    // than the internal consolidation number.
    $trackByOid = [];
    foreach ($groups[$sid]['rows'] as $rp) {
        $trackByOid[(int) $rp->oid] = (string) (($rp->order_prefix ?? '') . ($rp->order_no ?? ''));
    }
    $pkgLinesTxt = [];
    $pkgLinesHtml = [];
    $idx = 1;
    foreach ($checkedOids as $o) {
        if (!isset($trackByOid[$o])) { continue; }
        $priceStr = FS_BILL_MSG_INCLUDE_AMOUNT ? (' - GH₵' . number_format($ghsByOid[$o] ?? 0, 2)) : '';
        $pkgLinesTxt[]  = $idx . '. ' . $trackByOid[$o] . $priceStr;
        $pkgLinesHtml[] = htmlspecialchars($idx . '. ' . $trackByOid[$o] . $priceStr);
        $idx++;
    }
    $pkgListTxt  = implode("\n", $pkgLinesTxt);
    $pkgListHtml = implode('<br>', $pkgLinesHtml);

    $intro  = 'We have received payment for your package(s) — thank you!';
    $outro  = 'We appreciate your business and look forward to serving you again.';

    $msgWa = $intro . "\n\n"
        . ($pkgListTxt !== '' ? $pkgListTxt . "\n\n" : '')
        . ($amountBlock !== '' ? "*Payment Receipt*\n" . $amountBlock . "\n\n" : '')
        . $outro;

    $msgEmail = '<p>' . htmlspecialchars($intro) . '</p>'
        . ($pkgListHtml !== '' ? '<p>' . $pkgListHtml . '</p>' : '')
        . ($amountBlock !== '' ? '<p><b>Payment Receipt</b><br>' . nl2br(htmlspecialchars($amountBlock)) . '</p>' : '')
        . '<p>' . htmlspecialchars($outro) . '</p>';

    if ($sender && trim((string) $sender->phone) !== '') {
        try {
            $waBody = $msgWa;
            if (function_exists('getTemplateWhatsApp')) {
                $tpl = getTemplateWhatsApp(12);
                if ($tpl) {
                    $waBody = str_replace(
                        ['[USERNAME]', '[SUBJECT]', '[SITE_NAME]', '[MESSAGE]', '[URL]'],
                        [ucfirst($custName), $subject, $core->site_name, $msgWa, $core->site_url],
                        $tpl->body
                    );
                }
            }
            $wa = sendNotificationWhatsApp_v2($sender, $waBody, 'GH');
            if (!empty($wa['success'])) {
                $sentWa = true;
            } else {
                $warnings[] = 'WhatsApp: ' . ($wa['message'] ?? 'not sent');
            }
        } catch (Throwable $e) {
            $warnings[] = 'WhatsApp: ' . $e->getMessage();
        }
    } else {
        $warnings[] = 'WhatsApp: no phone number on file.';
    }

    if ($sender && trim((string) $sender->email) !== '') {
        try {
            $res = cdp_sendTemplateEmail(29, trim((string) $sender->email), [
                '[USERNAME]' => $custName,
                '[SUBJECT]'  => $subject,
                '[MESSAGE]'  => $msgEmail,
            ], $subject . ' — ' . $core->site_name);
            if (!empty($res['ok'])) {
                $sentEmail = true;
            } else {
                $warnings[] = 'Email: ' . ($res['error'] ?? 'not sent');
            }
        } catch (Throwable $e) {
            $warnings[] = 'Email: ' . $e->getMessage();
        }
    } else {
        $warnings[] = 'Email: no email address on file.';
    }

    echo json_encode([
        'ok'             => true,
        'this_payment'   => $paid,
        'mode'           => $mode,
        'paid_ghs'       => $paidTot,
        'discount_ghs'   => $discTot,
        'bill_ghs'       => round($billGhs, 2),
        'net_ghs'        => $netGhs,
        'balance_ghs'    => $balance,
        'cleared_orders' => $toClear,
        'consol'         => fs_consol_summary($cid),
        'sent_whatsapp'  => $sentWa,
        'sent_email'     => $sentEmail,
        'warnings'       => $warnings,
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// CLEAR DEBT (JSON) — light payment against an outstanding balance once every
// package is already cleared for delivery. Unlike record_payment it does NOT
// touch package clearance/status or send a "ready for pickup" receipt — it just
// records the money against the ledger so the debt drops.
// ----------------------------------------------------------------------------
if ($action === 'clear_debt') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);
    if ($cid <= 0 || $sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    $billedRow = fs_customer_billed($cid, $sid);
    if (!$billedRow) {
        echo json_encode(['ok' => false, 'error' => 'not_billed', 'message' => 'This customer has no bill on this consolidation.']);
        exit;
    }

    $billGhs = ($billedRow->amount_ghs !== null)
        ? (float) $billedRow->amount_ghs
        : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true, (float) $core->exchange_rate)['total'];
    $discGhs = (float) $billedRow->discount_ghs;
    $paidGhs = ($billedRow->paid_ghs !== null) ? (float) $billedRow->paid_ghs : 0.0;
    $balance = round(max(0, ($billGhs - $discGhs) - $paidGhs), 2);
    if ($balance <= 0) {
        echo json_encode(['ok' => false, 'error' => 'no_debt', 'message' => 'There is no outstanding balance to clear.']);
        exit;
    }

    $mode = strtolower(trim((string) ($_REQUEST['mode'] ?? 'cash')));
    if (!in_array($mode, ['cash', 'paystack', 'hubtel', 'paypal'], true)) {
        $mode = 'cash';
    }
    $reference = trim((string) ($_REQUEST['reference'] ?? ''));

    $verify = cdp_fsVerifyPayment($mode, $reference, null);
    if (empty($verify['ok'])) {
        echo json_encode(['ok' => false, 'error' => 'verify_failed',
                          'message' => $verify['message'] ?: 'Payment could not be verified.']);
        exit;
    }

    if ($mode === 'cash') {
        $raw  = trim((string) ($_REQUEST['paid'] ?? ''));
        $paid = round((float) str_replace(',', '', $raw), 2);
        if ($raw === '' || !is_numeric(str_replace(',', '', $raw)) || $paid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter the amount received (greater than 0).']);
            exit;
        }
    } else {
        $paid = round((float) ($verify['amount'] ?? 0), 2);
        if ($paid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'gateway_no_amount',
                              'message' => 'The gateway did not report a paid amount for this reference.']);
            exit;
        }
    }
    if ($paid > $balance) { $paid = $balance; } // never over-clear the debt

    $note = trim((string) ($_REQUEST['note'] ?? ''));
    try {
        $db->cdp_query("INSERT INTO cdb_fs_payments
                            (consolidate_id, sender_id, amount_ghs, mode, reference,
                             gateway_status, gateway_payload, cleared_for_delivery, cleared_orders, note,
                             exchange_rate, recorded_by, recorded_at)
                        VALUES
                            (:cid, :sid, :amt, :mode, :ref,
                             :gs, :gp, 1, '[]', :note, :rate, :by, NOW())");
        $db->bind(':cid', $cid);
        $db->bind(':sid', $sid);
        $db->bind(':amt', $paid);
        $db->bind(':mode', $mode);
        $db->bind(':ref', $reference !== '' ? $reference : null);
        $db->bind(':gs', $verify['status'] ?? null);
        $db->bind(':gp', $verify['payload'] ?? null);
        $db->bind(':note', $note !== '' ? ('Debt clearing — ' . $note) : 'Debt clearing');
        $db->bind(':rate', (float) $core->exchange_rate);
        $db->bind(':by', $uid);
        $db->cdp_execute();
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'ledger_missing',
                          'message' => 'Payment ledger not found — run sql/fs_transactions.sql, then try again.']);
        exit;
    }

    $agg = fs_sync_billing_cache($cid, $sid, $uid);
    $newBalance = round(max(0, ($billGhs - $discGhs) - (float) $agg['paid']), 2);

    // Log the debt payment against each of the customer's packages.
    $__grp = fs_customer_groups($cid);
    if (isset($__grp[$sid])) {
        foreach (array_unique(array_map('intval', $__grp[$sid]['oids'])) as $__o) {
            fs_log_history($uid, $__o, 'recorded a ₵' . number_format($paid, 2) . ' debt payment (' . ucfirst($mode) . ')');
        }
    }

    echo json_encode([
        'ok'          => true,
        'this_payment' => $paid,
        'mode'        => $mode,
        'paid_ghs'    => $agg['paid'],
        'balance_ghs' => $newBalance,
        'consol'      => fs_consol_summary($cid),
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// APPLY / REMOVE DISCOUNT (JSON) — PER CUSTOMER (per consolidation), from
// "Customer Actions". One discount row per (consolidation, customer); Apply
// replaces any existing. A percentage is taken of the customer's gross bill.
// ----------------------------------------------------------------------------
if ($action === 'apply_discount' || $action === 'remove_discount') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);
    if ($cid <= 0 || $sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    $billedRow = fs_customer_billed($cid, $sid);
    if (!$billedRow) {
        echo json_encode(['ok' => false, 'error' => 'not_billed', 'message' => 'Bill this customer first, then apply a discount.']);
        exit;
    }
    $billGhs = ($billedRow->amount_ghs !== null)
        ? (float) $billedRow->amount_ghs
        : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true, (float) $core->exchange_rate)['total'];

    // Package list for logging (discount is customer-level; log on each package).
    $groups = fs_customer_groups($cid);
    $oids   = isset($groups[$sid]) ? array_unique(array_map('intval', $groups[$sid]['oids'])) : [];

    try {
        // Replace any existing customer discount (Apply overwrites; Remove clears).
        $db->cdp_query("DELETE FROM cdb_fs_discounts WHERE consolidate_id = :cid AND sender_id = :sid");
        $db->bind(':cid', $cid);
        $db->bind(':sid', $sid);
        $db->cdp_execute();

        $amountGhs = 0.0;
        if ($action === 'apply_discount') {
            $reqType = (string) ($_REQUEST['disc_type'] ?? 'amount');
            $type = in_array($reqType, ['percent', 'amount', 'weight_rate'], true) ? $reqType : 'amount';
            $rawV = trim((string) ($_REQUEST['value'] ?? ''));
            $val  = (float) str_replace(',', '', $rawV);
            if ($rawV === '' || !is_numeric(str_replace(',', '', $rawV)) || $val <= 0) {
                echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter a value greater than 0.']);
                exit;
            }

            // Extra descriptor appended to the auto-reason (e.g. the rate change).
            $logExtra = '';
            if ($type === 'percent') {
                if ($val > 100) { $val = 100; }
                $amountGhs = round($billGhs * $val / 100, 2);
                $logExtra = ' (' . rtrim(rtrim(number_format($val, 2), '0'), '.') . '%)';
            } elseif ($type === 'weight_rate') {
                // Charge THIS customer a custom per-weight rate. FS otherwise uses
                // the general rate; the difference on their weight-priced items
                // (custom_price items are untouched) becomes the discount.
                $genRate = (float) ($core->value_weight ?? 0);
                $newRate = $val;
                if ($genRate <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'no_rate', 'message' => 'No general weight rate is configured.']);
                    exit;
                }
                if ($newRate >= $genRate) {
                    echo json_encode(['ok' => false, 'error' => 'rate_too_high',
                        'message' => 'Enter a rate below the general ' . ($core->for_symbol ?: '$') . number_format($genRate, 2) . '/' . ($core->weight_p ?: 'lb') . ' to give a discount.']);
                    exit;
                }
                // Total chargeable weight of the customer's WEIGHT-PRICED items.
                $chargeableWeight = 0.0;
                if ($oids) {
                    $inOids = implode(',', array_map('intval', $oids));
                    $db->cdp_query("SELECT COALESCE(SUM(order_item_weight * COALESCE(NULLIF(order_item_quantity,0),1)),0) w
                                    FROM cdb_add_order_item
                                    WHERE order_id IN ($inOids)
                                      AND (custom_price IS NULL OR custom_price = 0)");
                    $db->cdp_execute();
                    $chargeableWeight = (float) ($db->cdp_registro()->w ?? 0);
                }
                if ($chargeableWeight <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'no_weight',
                        'message' => 'This customer has no weight-priced items, so a rate change gives no discount.']);
                    exit;
                }
                $discountUsd = $chargeableWeight * ($genRate - $newRate);
                $amountGhs   = round($discountUsd * (float) $core->exchange_rate, 2);
                $sym = $core->for_symbol ?: '$';
                $unit = $core->weight_p ?: 'lb';
                $logExtra = ' (rate ' . $sym . number_format($newRate, 2) . '/' . $unit
                          . ', was ' . $sym . number_format($genRate, 2) . '/' . $unit
                          . ' on ' . rtrim(rtrim(number_format($chargeableWeight, 2), '0'), '.') . ' ' . $unit . ')';
            } else {
                $amountGhs = round($val, 2);
            }
            if ($amountGhs > $billGhs) { $amountGhs = $billGhs; } // never exceed the bill
            if ($amountGhs <= 0) {
                echo json_encode(['ok' => false, 'error' => 'no_discount', 'message' => 'That produces no discount.']);
                exit;
            }
            $reason = trim((string) ($_REQUEST['reason'] ?? ''));

            $db->cdp_query("INSERT INTO cdb_fs_discounts
                                (consolidate_id, sender_id, order_id, amount_ghs, disc_type, reason, exchange_rate, applied_by, applied_at)
                            VALUES (:cid, :sid, NULL, :amt, :type, :reason, :rate, :by, NOW())");
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->bind(':amt', $amountGhs);
            // 'weight_rate' exceeds the disc_type VARCHAR(10) — store the short 'rate'.
            $db->bind(':type', $type === 'weight_rate' ? 'rate' : $type);
            $db->bind(':reason', $reason !== '' ? $reason : null);
            $db->bind(':rate', (float) $core->exchange_rate);
            $db->bind(':by', $uid);
            $db->cdp_execute();

            foreach ($oids as $o) {
                fs_log_history($uid, $o, 'applied a ₵' . number_format($amountGhs, 2) . ' customer discount'
                    . $logExtra
                    . ($reason !== '' ? ' — ' . $reason : ''));
            }
        } else {
            foreach ($oids as $o) {
                fs_log_history($uid, $o, 'removed the customer discount');
            }
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'ledger_missing',
                          'message' => 'Discount ledger not found — run sql/fs_transactions.sql, then try again.']);
        exit;
    }

    // Refresh the billing caches (discount + net) from the ledger.
    $agg = fs_sync_billing_cache($cid, $sid, null);

    echo json_encode([
        'ok'           => true,
        'discount_ghs' => $agg['discount'],
        'paid_ghs'     => $agg['paid'],
        'bill_ghs'     => round($billGhs, 2),
        'aggregates'   => fs_aggregates($cid, $sid),
        'consol'       => fs_consol_summary($cid),
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// SET THE GLOBAL PER-WEIGHT RATE (value_weight in cdb_settings).
// The rate used for weight pricing is a system value: it is display-only at
// courier add/edit, and managed here (gated by fs_price_items). Historical
// bills are unaffected — each already stores its own captured rate.
// ----------------------------------------------------------------------------
if ($action === 'set_weight_rate') {
    header('Content-Type: application/json; charset=UTF-8');

    $raw = trim((string) ($_REQUEST['value'] ?? ''));
    $clean = str_replace(',', '', $raw);
    $val = round((float) $clean, 4);
    if ($raw === '' || !is_numeric($clean) || $val <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Enter a rate greater than 0.']);
        exit;
    }

    try {
        $db->cdp_query("UPDATE cdb_settings SET value_weight = :v");
        $db->bind(':v', $val);
        $db->cdp_execute();
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Could not update the rate.']);
        exit;
    }

    echo json_encode(['ok' => true, 'value' => $val, 'unit' => (string) ($core->weight_p ?? 'lb')]);
    exit;
}

// ----------------------------------------------------------------------------
// PAYMENT HISTORY for a customer (all consolidations) — a statement of every
// payment and discount recorded against them, shown from Customer Actions.
// ----------------------------------------------------------------------------
if ($action === 'payment_history') {
    header('Content-Type: application/json; charset=UTF-8');

    $sid = (int) ($_REQUEST['sender_id'] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }

    $nameExpr = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', %s))";

    // Customer label + locker.
    $db->cdp_query("SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(fname,''),' ',COALESCE(lname,''))),''), username, CONCAT('User ', id)) AS label, locker
                    FROM cdb_users WHERE id = :sid LIMIT 1");
    $db->bind(':sid', $sid);
    $db->cdp_execute();
    $cust = $db->cdp_registro();
    $custLabel = $cust ? (string) $cust->label : ('User ' . $sid);

    $payments = [];
    $discounts = [];
    try {
        $db->cdp_query("SELECT p.consolidate_id, p.amount_ghs, p.mode, p.reference, p.gateway_status,
                               p.exchange_rate, p.recorded_at,
                               " . sprintf($nameExpr, 'p.recorded_by') . " AS by_name
                        FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.recorded_by
                        WHERE p.sender_id = :sid ORDER BY p.recorded_at DESC, p.id DESC");
        $db->bind(':sid', $sid);
        $db->cdp_execute();
        $payments = (array) $db->cdp_registros();

        $db->cdp_query("SELECT d.consolidate_id, d.amount_ghs, d.disc_type, d.reason,
                               d.exchange_rate, d.applied_at,
                               " . sprintf($nameExpr, 'd.applied_by') . " AS by_name
                        FROM cdb_fs_discounts d LEFT JOIN cdb_users u ON u.id = d.applied_by
                        WHERE d.sender_id = :sid ORDER BY d.applied_at DESC, d.id DESC");
        $db->bind(':sid', $sid);
        $db->cdp_execute();
        $discounts = (array) $db->cdp_registros();
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Payment ledger not found — run sql/fs_transactions.sql, then try again.']);
        exit;
    }

    $totalPaid = 0.0;
    foreach ($payments as $p) { $totalPaid += (float) $p->amount_ghs; }
    $totalDisc = 0.0;
    foreach ($discounts as $d) { $totalDisc += (float) $d->amount_ghs; }
    $owed = fs_customer_outstanding($sid);

    $fmtGhs = function ($v) { return '&#8373;' . number_format((float) $v, 2); };

    ob_start();
    ?>
    <div class="fs-history">
        <div class="d-flex flex-wrap mb-2" style="gap:.5rem;">
            <span class="badge badge-success p-2">Total Paid: <?php echo $fmtGhs($totalPaid); ?></span>
            <?php if ($totalDisc > 0): ?><span class="badge badge-info p-2">Total Discount: <?php echo $fmtGhs($totalDisc); ?></span><?php endif; ?>
            <?php if ($owed > 0): ?><span class="badge badge-danger p-2">Outstanding: <?php echo $fmtGhs($owed); ?></span>
            <?php else: ?><span class="badge badge-secondary p-2">No outstanding balance</span><?php endif; ?>
        </div>

        <h6 class="mt-2 mb-1 text-left"><i class="mdi mdi-cash-multiple"></i> Payments</h6>
        <?php if (empty($payments)): ?>
            <div class="text-muted small text-left mb-2">No payments recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-2" style="font-size:12px;">
                <thead><tr class="text-left">
                    <th>Date</th><th>Consol #</th><th>Mode</th><th>Reference</th>
                    <th class="text-right">Amount</th><th>By</th>
                </tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr class="text-left">
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $p->recorded_at))); ?></td>
                        <td><?php echo (int) $p->consolidate_id; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst((string) $p->mode)); ?></td>
                        <td><?php echo htmlspecialchars((string) ($p->reference ?? '')); ?></td>
                        <td class="text-right"><?php echo $fmtGhs($p->amount_ghs); ?></td>
                        <td><?php echo htmlspecialchars((string) $p->by_name); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($discounts)): ?>
        <h6 class="mt-2 mb-1 text-left"><i class="mdi mdi-sale"></i> Discounts</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0" style="font-size:12px;">
                <thead><tr class="text-left">
                    <th>Date</th><th>Consol #</th><th>Type</th><th>Reason</th>
                    <th class="text-right">Amount</th><th>By</th>
                </tr></thead>
                <tbody>
                <?php foreach ($discounts as $d): ?>
                    <tr class="text-left">
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $d->applied_at))); ?></td>
                        <td><?php echo (int) $d->consolidate_id; ?></td>
                        <td><?php echo htmlspecialchars((string) $d->disc_type); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d->reason ?? '')); ?></td>
                        <td class="text-right"><?php echo $fmtGhs($d->amount_ghs); ?></td>
                        <td><?php echo htmlspecialchars((string) $d->by_name); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
    echo json_encode([
        'ok'    => true,
        'name'  => $custLabel,
        'html'  => ob_get_clean(),
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// ITEMS for a package (HTML) — editable with grouping, or read-only if billed.
// ----------------------------------------------------------------------------
if ($action === 'items') {
    $order_id = (int) ($_REQUEST['order_id'] ?? 0);

    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $items = $db->cdp_registros();

    if (!$items) {
        echo '<div class="text-muted">No items in this package.</div>';
        echo fs_render_change_log($order_id);
        exit;
    }

    // Split items into groups and ungrouped singles.
    $groups = [];
    $singles = [];
    foreach ($items as $it) {
        $g = trim((string) ($it->order_item_weight_group ?? ''));
        if ($g !== '') {
            $groups[$g][] = $it;
        } else {
            $singles[] = $it;
        }
    }

    // -------------------- Editable (subject to the edit lock). ----------------
    $lock = cdp_fsAcquireLock($order_id, $uid, $uname);
    $editable = $lock['ok'];

    if (!$editable) {
        echo '<div class="alert alert-warning py-2 mb-2"><i class="fas fa-lock"></i> This package is being edited by <b>'
            . htmlspecialchars($lock['by'] ?? 'another user') . '</b>. It is read-only until they finish.</div>';
    }

    $canGroup = $editable && count($singles) >= 2;

    // Existing groups first — each has one shared pricing control.
    foreach ($groups as $token => $members):
        $first = $members[0];
        $gMode = ($first->custom_price !== null) ? 'custom' : 'weight';
        $gVal  = ($first->custom_price !== null) ? (float) $first->custom_price : (float) $first->order_item_weight;
        ?>
        <div class="fs-group-card mb-2" data-group="<?php echo htmlspecialchars($token); ?>">
            <div class="fs-group-head">
                <i class="mdi mdi-link-variant"></i> <b>Priced Together</b>
                <span class="badge badge-info ml-1"><?php echo count($members); ?> items</span>
                <span class="fs-spacer"></span>
                <?php if ($editable): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="fsUngroup(this, <?php echo $order_id; ?>, '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>')">
                        <i class="mdi mdi-link-variant-off"></i> Ungroup
                    </button>
                <?php endif; ?>
            </div>
            <ul class="fs-group-members mb-1">
                <?php foreach ($members as $m): ?>
                    <li><?php echo (int) $m->order_item_quantity; ?> × <?php echo htmlspecialchars((string) $m->order_item_description); ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="fs-group-ctl">
                <?php echo fs_render_price_ctl('group', $token, $gMode, $gVal, $editable); ?>
                <?php if ($editable): ?>
                    <button type="button" class="btn btn-sm btn-success fs-save ml-2"
                            onclick="fsSaveGroup(this, <?php echo $order_id; ?>, '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>')">
                        <i class="mdi mdi-check"></i> Save group
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($canGroup): ?>
        <div class="fs-group-bar" style="display:none;">
            <button type="button" class="btn btn-sm btn-info"
                    onclick="fsGroupSelected(this, <?php echo $order_id; ?>)">
                <i class="mdi mdi-link-variant"></i> Group &amp; price selected (<span class="fs-sel-count">0</span>)
            </button>
            <small class="text-muted ml-2">Selected items will be priced as one (single weight or price for the whole batch).</small>
        </div>
    <?php endif; ?>

    <?php if ($singles): ?>
    <table class="table table-sm table-bordered mb-0 fs-items-table" data-oid="<?php echo $order_id; ?>">
        <thead>
            <tr>
                <?php if ($canGroup): ?><th style="width:34px;" title="Select items to group"><i class="mdi mdi-link-variant"></i></th><?php endif; ?>
                <th style="width:55px;">Qty</th>
                <th>Description</th>
                <th style="width:290px;">Pricing</th>
                <th style="width:64px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($singles as $it):
                $iid    = (int) $it->order_item_id;
                $mode   = ($it->custom_price !== null) ? 'custom' : 'weight';
                // Only sheet pricing counts as "priced"; order-entry weights are
                // still prefilled below as a convenience, but stay unticked.
                $priced = !empty($it->priced_at);
                if ($it->custom_price !== null) {
                    $val = (float) $it->custom_price;
                } else {
                    $w = (float) $it->order_item_weight;
                    $val = ($w > 0 || !empty($it->priced_at)) ? $w : '';
                }
                ?>
                <tr data-iid="<?php echo $iid; ?>">
                    <?php if ($canGroup): ?>
                        <td class="text-center"><input type="checkbox" class="fs-item-check" value="<?php echo $iid; ?>"></td>
                    <?php endif; ?>
                    <td><?php echo (int) $it->order_item_quantity; ?></td>
                    <td>
                        <?php echo htmlspecialchars((string) $it->order_item_description); ?>
                        <?php if ($priced): ?><i class="mdi mdi-check-circle text-success ml-1" title="Priced"></i><?php endif; ?>
                    </td>
                    <td><?php echo fs_render_price_ctl('item', $iid, $mode, $val, $editable); ?></td>
                    <td class="text-center">
                        <?php if ($editable): ?>
                            <button type="button" class="btn btn-sm btn-success fs-save"
                                    onclick="fsSaveItem(this, <?php echo $order_id; ?>, <?php echo $iid; ?>)">
                                <i class="mdi mdi-check"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif;

    echo fs_render_change_log($order_id);
    exit;
}

// ----------------------------------------------------------------------------
// PACKAGES for one customer in a consolidation (HTML)
// ----------------------------------------------------------------------------
if ($action === 'packages') {
    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);

    $groups = fs_customer_groups($cid);
    if (!isset($groups[$sid]) || !$groups[$sid]['rows']) {
        echo '<div class="text-muted p-2">No packages for this customer.</div>';
        exit;
    }

    $stats = fs_item_stats($groups[$sid]['oids']);
    // Per-package paid / cleared-for-delivery state (badge on each card).
    $clearedMap = [];
    $oidsC = array_map('intval', $groups[$sid]['oids']);
    if ($oidsC) {
        $inC = implode(',', $oidsC);
        $db->cdp_query("SELECT order_id, fs_cleared_for_delivery FROM cdb_add_order WHERE order_id IN ($inC)");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $clearedMap[(int) $r->order_id] = ((int) $r->fs_cleared_for_delivery === 1);
        }
    }
    foreach ($groups[$sid]['rows'] as $p) {
        echo fs_render_package($p, $stats[(int) $p->oid] ?? null, $clearedMap[(int) $p->oid] ?? false);
    }

    // Billing log — the customer-level counterpart of the package change log.
    // All entries (bill, payment, internal notes) merge into ONE timeline,
    // newest first.
    $billedRow = fs_customer_billed($cid, $sid);
    if ($billedRow) {
        $billGhs = ($billedRow->amount_ghs !== null)
            ? (float) $billedRow->amount_ghs
            : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true)['total'];
        $feeGhs = ($billedRow->handling_ghs !== null) ? (float) $billedRow->handling_ghs : 0.0;

        // Show the exchange rate a GHS figure was created at (audit anchor): the
        // GHS numbers never move even after the live rate changes.
        $fsRateChip = function ($rate) {
            $rate = (float) $rate;
            if ($rate <= 0) { return ''; }
            $r = rtrim(rtrim(number_format($rate, 4), '0'), '.');
            return ' <span class="fs-rate-chip" title="USD→GHS rate used at the time">@ ' . $r . '</span>';
        };

        $entries = [];

        $entries[] = [
            'ts'   => strtotime((string) $billedRow->billed_at),
            'html' => '<b>' . htmlspecialchars($billedRow->biller) . '</b> billed this customer ₵' . number_format($billGhs, 2)
                . ' ($' . number_format((float) $billedRow->amount_usd, 2)
                . ($feeGhs > 0 ? ' + ₵' . number_format($feeGhs, 2) . ' handling fee' : '') . ')'
                . $fsRateChip($billedRow->exchange_rate ?? 0)
                . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $billedRow->billed_at))) . '</span>',
        ];

        // Each recorded payment is its own timeline line (split payments).
        try {
            $db->cdp_query("SELECT p.amount_ghs, p.mode, p.reference, p.gateway_status,
                                   p.cleared_for_delivery, p.recorded_at, p.exchange_rate,
                                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                            u.username, CONCAT('User ', p.recorded_by)) AS payer
                            FROM cdb_fs_payments p
                            LEFT JOIN cdb_users u ON u.id = p.recorded_by
                            WHERE p.consolidate_id = :cid AND p.sender_id = :sid
                            ORDER BY p.id DESC");
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->cdp_execute();
            $pays = $db->cdp_registros();
        } catch (Throwable $e) {
            $pays = null; // cdb_fs_payments missing — migration not run yet
        }
        if ($pays) {
            foreach ($pays as $pp) {
                $entries[] = [
                    'ts'   => strtotime((string) $pp->recorded_at),
                    'html' => '<i class="mdi mdi-cash"></i> <b>' . htmlspecialchars($pp->payer) . '</b> recorded a '
                        . htmlspecialchars(ucfirst((string) $pp->mode)) . ' payment of ₵' . number_format((float) $pp->amount_ghs, 2)
                        . ((string) $pp->reference !== '' ? ' <span class="text-muted">ref ' . htmlspecialchars((string) $pp->reference) . '</span>' : '')
                        . ((int) $pp->cleared_for_delivery === 1 ? ' <span class="badge badge-success">cleared for delivery</span>' : '')
                        . $fsRateChip($pp->exchange_rate ?? 0)
                        . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $pp->recorded_at))) . '</span>',
                ];
            }
        }

        // Applied discount (customer-level, from Customer Actions).
        try {
            $db->cdp_query("SELECT d.amount_ghs, d.disc_type, d.reason, d.applied_at, d.exchange_rate,
                                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                            u.username, CONCAT('User ', d.applied_by)) AS author
                            FROM cdb_fs_discounts d
                            LEFT JOIN cdb_users u ON u.id = d.applied_by
                            WHERE d.consolidate_id = :cid AND d.sender_id = :sid
                            ORDER BY d.id DESC");
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->cdp_execute();
            $discs = $db->cdp_registros();
        } catch (Throwable $e) {
            $discs = null;
        }
        if ($discs) {
            foreach ($discs as $dd) {
                $entries[] = [
                    'ts'   => strtotime((string) $dd->applied_at),
                    'html' => '<i class="mdi mdi-sale"></i> <b>' . htmlspecialchars($dd->author) . '</b> applied a ₵'
                        . number_format((float) $dd->amount_ghs, 2) . ' discount'
                        . ((string) $dd->reason !== '' ? ' <span class="text-muted">(' . htmlspecialchars((string) $dd->reason) . ')</span>' : '')
                        . $fsRateChip($dd->exchange_rate ?? 0)
                        . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $dd->applied_at))) . '</span>',
                ];
            }
        }

        // Internal notes (never sent to the customer): who wrote what, when.
        try {
            $db->cdp_query("SELECT n.note, n.created_at,
                                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                            u.username, CONCAT('User ', n.created_by)) AS author
                            FROM cdb_consolidate_billing_notes n
                            LEFT JOIN cdb_users u ON u.id = n.created_by
                            WHERE n.consolidate_id = :cid AND n.sender_id = :sid
                            ORDER BY n.id DESC LIMIT 10");
            $db->bind(':cid', $cid);
            $db->bind(':sid', $sid);
            $db->cdp_execute();
            $notes = $db->cdp_registros();
        } catch (Throwable $e) {
            $notes = null; // notes table missing — migration §4 not run yet
        }
        if ($notes) {
            foreach ($notes as $n) {
                $entries[] = [
                    'ts'   => strtotime((string) $n->created_at),
                    'html' => '<i class="mdi mdi-note-text-outline"></i> <b>' . htmlspecialchars($n->author) . '</b>'
                        . ' noted: &ldquo;' . htmlspecialchars((string) $n->note) . '&rdquo;'
                        . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $n->created_at))) . '</span>',
                ];
            }
        }

        usort($entries, function ($a, $b) {
            return $b['ts'] <=> $a['ts'];
        });
        ?>
        <div class="fs-history fs-billing-log">
            <div class="fs-history-title"><i class="mdi mdi-cash-check"></i> Billing log</div>
            <?php foreach ($entries as $e): ?>
                <div class="fs-hist-item"><?php echo $e['html']; ?></div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    exit;
}

// ----------------------------------------------------------------------------
// CUSTOMERS for a consolidation (HTML) — the new second tier.
// ----------------------------------------------------------------------------
if ($action === 'customers') {
    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);

    $groups = fs_customer_groups($cid);
    if (!$groups) {
        echo '<div class="text-muted p-2">No packages in this consolidation.</div>';
        exit;
    }

    $allOids = [];
    foreach ($groups as $g) {
        foreach ($g['oids'] as $o) {
            $allOids[] = $o;
        }
    }
    $stats   = fs_item_stats($allOids);
    $billing = fs_billing_map($cid);

    foreach ($groups as $g) {
        echo fs_render_customer($cid, $g, $stats, $billing);
    }
    exit;
}

// ----------------------------------------------------------------------------
// SEARCH: customers by name (any word order) or locker (HTML)
// ----------------------------------------------------------------------------
if ($action === 'search_customer') {
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    if ($q === '') {
        echo '<div class="text-muted p-2">Type a customer name or locker.</div>';
        exit;
    }

    // Name: every typed word must appear somewhere in "fname lname" (order-
    // agnostic). Locker: prefix match.
    $words = preg_split('/\s+/', mb_strtolower($q));
    $nameConds = [];
    foreach ($words as $i => $w) {
        $nameConds[] = "LOWER(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) LIKE :w$i";
    }
    $sqlU = "SELECT u.id FROM cdb_users u
             WHERE (" . implode(' AND ', $nameConds) . ")
                OR LOWER(COALESCE(u.locker,'')) LIKE :lk
             LIMIT 60";
    $db->cdp_query($sqlU);
    foreach ($words as $i => $w) {
        $db->bind(":w$i", '%' . $w . '%');
    }
    $db->bind(':lk', mb_strtolower($q) . '%');
    $db->cdp_execute();
    $uids = array_map(function ($r) { return (int) $r->id; }, (array) $db->cdp_registros());

    if (!$uids) {
        echo '<div id="report-has-data" data-has="0"></div>';
        echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No matching customers found.</p></div>';
        exit;
    }

    // Which consolidations hold packages of these customers?
    $in = implode(',', $uids);
    $db->cdp_query("
        SELECT d.consolidate_id AS cid, a.sender_id AS sid
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_add_order a ON a.order_id = (
            SELECT a2.order_id FROM cdb_add_order a2
            WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
            ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
            LIMIT 1)
        WHERE a.sender_id IN ($in)
        GROUP BY d.consolidate_id, a.sender_id
        ORDER BY d.consolidate_id DESC
        LIMIT 100");
    $db->cdp_execute();
    $pairs = (array) $db->cdp_registros();

    if (!$pairs) {
        echo '<div id="report-has-data" data-has="0"></div>';
        echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No consolidated packages found for matching customers.</p></div>';
        exit;
    }

    $byCid = [];
    foreach ($pairs as $p) {
        $byCid[(int) $p->cid][] = (int) $p->sid;
    }

    echo '<div id="report-has-data" data-has="1"></div>';
    echo '<div class="alert alert-info py-2 mb-3">Customer matches in <b>' . count($byCid) . '</b> consolidation(s).</div>';

    echo fs_render_search_consolidations($byCid);
    exit;
}

// ----------------------------------------------------------------------------
// SEARCH packages by package number / tracking values (HTML)
// ----------------------------------------------------------------------------
if ($action === 'search_package') {
    $search = trim((string) ($_REQUEST['q'] ?? ''));
    if ($search === '') {
        echo '<div class="text-muted p-2">Type a package number or tracking value.</div>';
        exit;
    }

    $where = cdp_fs_build_package_search_where($db);

    $db->cdp_query("
        SELECT DISTINCT c.consolidate_id AS cid, a.sender_id AS sid
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_consolidate c ON c.consolidate_id = d.consolidate_id
        INNER JOIN cdb_add_order a ON a.order_id = (
            SELECT a2.order_id FROM cdb_add_order a2
            WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
            ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
            LIMIT 1)
        WHERE ($where)
        ORDER BY cid DESC
        LIMIT 100");
    $db->bind(':q', '%' . mb_strtolower($search) . '%');
    $db->cdp_execute();
    $pairs = (array) $db->cdp_registros();

    if (!$pairs) {
        echo '<div id="report-has-data" data-has="0"></div>';
        echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No matching packages found.</p></div>';
        exit;
    }

    $byCid = [];
    foreach ($pairs as $p) {
        $byCid[(int) $p->cid][] = (int) $p->sid;
    }

    echo '<div id="report-has-data" data-has="1"></div>';
    echo '<div class="alert alert-info py-2 mb-3">Package matches in <b>' . count($byCid) . '</b> consolidation(s).</div>';

    echo fs_render_search_consolidations($byCid);
    exit;
}

/**
 * Render consolidation shells (expanded) containing ONLY the given customers'
 * tiers — shared by both search actions. Packages/items stay lazy-loaded.
 */
function fs_render_search_consolidations(array $byCid)
{
    $db = new Conexion;
    $dgStyle = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
    $dgColor = ($dgStyle && !empty($dgStyle->color)) ? $dgStyle->color : '#ff6d00';

    $html = '<div class="accordion" id="fsAccordion">';
    foreach ($byCid as $cid => $sids) {
        $db->cdp_query("SELECT c_prefix, c_no, c_date, is_dangerous_good FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
        $db->bind(':cid', $cid);
        $db->cdp_execute();
        $c = $db->cdp_registro();
        if (!$c) {
            continue;
        }
        $cNo = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));

        $groups  = fs_customer_groups($cid);
        $billing = fs_billing_map($cid);
        $oids = [];
        foreach ($sids as $sid) {
            if (isset($groups[$sid])) {
                foreach ($groups[$sid]['oids'] as $o) {
                    $oids[] = $o;
                }
            }
        }
        $stats = fs_item_stats($oids);

        $tierHtml = '';
        foreach (array_unique($sids) as $sid) {
            if (isset($groups[$sid])) {
                $tierHtml .= fs_render_customer($cid, $groups[$sid], $stats, $billing);
            }
        }
        if ($tierHtml === '') {
            continue;
        }

        $dg = ((int) ($c->is_dangerous_good ?? 0) === 1)
            ? '<span class="fs-dg-badge" style="background:' . htmlspecialchars($dgColor) . ';" title="This consolidation contains dangerous goods"><i class="fas fa-exclamation-triangle"></i></span>'
            : '';

        $html .= '<div class="card mb-2 fs-consol-card fs-active">'
            . '<div class="card-header fs-consol-header">'
            . '<i class="fas fa-boxes"></i> <b>' . $cNo . '</b>'
            . '<span class="fs-dim ml-3"><i class="mdi mdi-calendar-blank"></i> ' . htmlspecialchars((string) ($c->c_date ?? '')) . '</span>'
            . $dg
            . '<span class="fs-spacer"></span>'
            . '<span class="fs-dim">showing matching customers only</span>'
            . '</div>'
            . '<div class="fs-consol-body" data-cid="' . (int) $cid . '" style="display:block;">'
            . '<div class="card-body p-2 fs-customers" data-cid="' . (int) $cid . '" data-loaded="1">' . $tierHtml . '</div>'
            . '</div></div>';
    }
    $html .= '</div>';
    return $html;
}

/** Column whitelist for the package/tracking search (schema-tolerant). */
function cdp_fs_get_table_columns_ajax($db, $table)
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $cache[$table] = [];
    try {
        $db->cdp_query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
        $db->bind(':table_name', $table);
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $row) {
            $name = strtolower((string) ($row->COLUMN_NAME ?? $row->column_name ?? ''));
            if ($name !== '') {
                $cache[$table][$name] = true;
            }
        }
    } catch (Throwable $e) {
        // Base columns still work if the schema lookup fails.
    }
    return $cache[$table];
}

function cdp_fs_build_package_search_where($db)
{
    $columns = cdp_fs_get_table_columns_ajax($db, 'cdb_add_order');
    $allowed = [
        'order_id', 'order_prefix', 'order_no', 'tracking_no', 'tracking_number',
        'tracking', 'tracking_code', 'order_tracking', 'order_tracking_no',
        'postal_tracking', 'postal_tracking_no', 'postal_tracking_number',
        'custom_tracking', 'custom_tracking_no', 'custom_tracking_number',
        'reference_no', 'reference', 'waybill', 'waybill_no', 'awb',
        'shipment_no', 'parcel_no', 'barcode', 'tracking_num',
    ];
    $parts = [];
    $parts[] = "LOWER(CONCAT(COALESCE(a.order_prefix,''), COALESCE(a.order_no,''))) LIKE :q";
    $parts[] = "LOWER(CAST(a.order_id AS CHAR)) LIKE :q";
    foreach ($allowed as $col) {
        if (isset($columns[strtolower($col)])) {
            $parts[] = "LOWER(COALESCE(CAST(a.$col AS CHAR), '')) LIKE :q";
        }
    }
    return implode(' OR ', $parts);
}

// ----------------------------------------------------------------------------
// LIST STATS (JSON) — heavy per-consolidation numbers (due/fees/weight/priced/
// received), fetched AFTER the list renders so the page never blocks on them.
// ----------------------------------------------------------------------------
if ($action === 'list_stats') {
    header('Content-Type: application/json; charset=UTF-8');

    $cids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) ($_REQUEST['cids'] ?? ''))))), 0, 200);
    if (!$cids) {
        echo json_encode(['ok' => true, 'stats' => new stdClass()]);
        exit;
    }
    $in = implode(',', $cids);

    $stats = [];
    foreach ($cids as $c) {
        $stats[$c] = ['base_usd' => 0.0, 'fee_usd' => 0.0, 'due_usd' => 0.0, 'weight' => 0.0,
                      'custs' => 0, 'custs_priced' => 0, 'paid_usd' => 0.0];
    }

    // Per (consolidation, customer): money, weight, fully-priced flag — deduped
    // ((order_prefix, order_no) is NOT unique in cdb_add_order).
    $db->cdp_query("
        SELECT d.consolidate_id AS cid, a.sender_id AS sid,
               SUM(a.total_order) AS money,
               SUM(COALESCE(NULLIF(a.total_weight, 0), d.weight)) AS wsum,
               MIN(CASE WHEN EXISTS (SELECT 1 FROM cdb_add_order_item i
                                     WHERE i.order_id = a.order_id AND i.priced_at IS NULL)
                        THEN 0 ELSE 1 END) AS all_priced
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_add_order a ON a.order_id = (
            SELECT a2.order_id FROM cdb_add_order a2
            WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
            ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
            LIMIT 1)
        WHERE d.consolidate_id IN ($in)
        GROUP BY d.consolidate_id, a.sender_id");
    $db->cdp_execute();
    $rateNow = (float) $core->exchange_rate;
    $feesGhs = [];
    foreach ((array) $db->cdp_registros() as $t) {
        $tcid = (int) $t->cid;
        if (!isset($stats[$tcid])) {
            continue;
        }
        $stats[$tcid]['base_usd']     += (float) $t->money;
        $stats[$tcid]['weight']       += (float) $t->wsum;
        $stats[$tcid]['custs']        += 1;
        $stats[$tcid]['custs_priced'] += (int) $t->all_priced;
        // One handling fee per customer, from their TOTAL payable.
        $feesGhs[$tcid] = ($feesGhs[$tcid] ?? 0.0) + cdp_handlingFeeGhs(cdp_usdToGhs((float) $t->money, $rateNow));
    }

    $db->cdp_query("SELECT consolidate_id AS cid, COALESCE(SUM(paid_ghs),0) AS paid
                    FROM cdb_consolidate_customer_billing
                    WHERE consolidate_id IN ($in) AND paid_ghs IS NOT NULL
                    GROUP BY consolidate_id");
    $db->cdp_execute();
    $paidMap = [];
    foreach ((array) $db->cdp_registros() as $t) {
        $paidMap[(int) $t->cid] = (float) $t->paid;
    }

    foreach ($stats as $c => &$s) {
        $s['base_usd'] = round($s['base_usd'], 2);
        $s['fee_usd']  = round(cdp_ghsToUsd($feesGhs[$c] ?? 0.0, $rateNow), 2);
        $s['due_usd']  = round($s['base_usd'] + $s['fee_usd'], 2);
        $s['weight']   = round($s['weight'], 2);
        $s['paid_usd'] = round(cdp_ghsToUsd($paidMap[$c] ?? 0.0, $rateNow), 2);
    }
    unset($s);

    echo json_encode(['ok' => true, 'stats' => $stats]);
    exit;
}

// ----------------------------------------------------------------------------
// LIST consolidations (default, HTML) — renders instantly from cdb_consolidate
// alone; the heavy numbers arrive via list_stats right after.
// ----------------------------------------------------------------------------
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';

$sqlWhere = '';
if ($search !== '') {
    $sqlWhere = " WHERE CONCAT(COALESCE(c.c_prefix,''), COALESCE(c.c_no,'')) LIKE :q
                  OR CAST(c.consolidate_id AS CHAR) LIKE :q ";
}

// Weights/money/pricing come from the deduped per-customer query below.
$sql = "SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.is_dangerous_good
        FROM cdb_consolidate c $sqlWhere ORDER BY c.consolidate_id DESC LIMIT 200";
$db->cdp_query($sql);
if ($search !== '') {
    $db->bind(':q', '%' . $search . '%');
}
$db->cdp_execute();
$consolidations = $db->cdp_registros();

if (!$consolidations) {
    echo '<div id="report-has-data" data-has="0"></div>';
    echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No consolidations found.</p></div>';
    return;
}

$dgStyle = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
$dgColor = ($dgStyle && !empty($dgStyle->color)) ? $dgStyle->color : '#ff6d00';
?>
<div id="report-has-data" data-has="1"></div>
<div class="accordion" id="fsAccordion">
    <?php foreach ($consolidations as $c):
        $cid = (int) $c->consolidate_id;
        $cNo = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));
        ?>
        <div class="card mb-2 fs-consol-card">
            <div class="card-header fs-consol-header"
                 onclick="window.location.href = 'financial_sheet_consolidation.php?id=<?php echo $cid; ?>'"
                 title="Open this consolidation">
                <i class="fas fa-boxes"></i>
                <b><?php echo $cNo; ?></b>
                <span class="fs-dim ml-3"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $c->c_date); ?></span>
                <span class="fs-dim ml-3 fs-consol-weight" data-cid="<?php echo $cid; ?>" title="Sum of package weights">
                    <i class="mdi mdi-weight"></i> …
                </span>
                <span class="badge badge-light ml-3 fs-consol-custpriced" data-cid="<?php echo $cid; ?>"
                      title="Customers whose packages are fully priced">
                    <i class="mdi mdi-account-check"></i> …
                </span>
                <?php if ((int) $c->is_dangerous_good === 1): ?>
                    <span class="fs-dg-badge" style="background:<?php echo htmlspecialchars($dgColor); ?>;"
                          title="This consolidation contains dangerous goods">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                <?php endif; ?>
                <span class="fs-spacer"></span>
                <span class="fs-dim fs-consol-fees" data-cid="<?php echo $cid; ?>"
                      title="Packages + handling fees">…</span>
                <span class="fs-money fs-consol-total" data-cid="<?php echo $cid; ?>" data-usd="0"
                      title="Amount due incl. handling fees">Due …</span>
                <span class="fs-chip-paid fs-consol-paid" data-cid="<?php echo $cid; ?>" style="display:none;"
                      title="Amount received from customers so far"></span>
                <button type="button" class="btn btn-sm btn-light ml-2"
                        onclick="event.stopPropagation(); fsExportConsolidation(<?php echo $cid; ?>);"
                        title="Export this consolidation to PDF">
                    <i class="fa fa-file-pdf text-danger"></i> PDF
                </button>
                <button type="button" class="btn btn-sm btn-light"
                        onclick="event.stopPropagation(); fsExportConsolidationExcel(<?php echo $cid; ?>);"
                        title="Export this consolidation to Excel">
                    <i class="fa fa-file-excel text-success"></i> Excel
                </button>
                <i class="mdi mdi-chevron-right fs-caret ml-2"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>
