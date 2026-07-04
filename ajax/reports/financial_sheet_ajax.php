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
require_once(__DIR__ . '/../notify_whatsapp/api_whatsapp_service_v2.php');
require_login();

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

    ob_start();
    ?>
    <div class="card mb-2 fs-cust-card" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>">
        <div class="card-header fs-cust-header p-2" onclick="fsToggleCustomer(this, event)">
            <span class="fs-avatar"><?php echo htmlspecialchars($initials); ?><?php if ($hasPaid): ?><span class="fs-avatar-billed" title="Payment received — expand for details."><i class="mdi mdi-currency-usd"></i></span><?php endif; ?></span>
            <b><?php echo htmlspecialchars($g['label']); ?></b>
            <span class="ml-2"><?php echo $pricedBadge; ?></span>
            <?php if ($hasPaid): ?>
                <span class="fs-chip-paid" title="Amount paid by the customer">Paid &#8373;<?php echo number_format((float) $billedRow->paid_ghs, 2); ?></span>
                <?php if ((float) $billedRow->discount_ghs > 0): ?>
                    <span class="fs-chip-discount" title="Auto-calculated: bill minus amount paid">Discount &#8373;<?php echo number_format((float) $billedRow->discount_ghs, 2); ?></span>
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
                        aria-haspopup="true" aria-expanded="false">Actions</button>
                <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item fs-pay-btn" href="javascript:void(0)"
                           data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                           data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                           data-bill="<?php echo $billGhs; ?>"
                           data-paid="<?php echo $hasPaid ? (float) $billedRow->paid_ghs : ''; ?>"
                           onclick="fsRecordPayment(this);">
                            <i class="mdi mdi-cash-multiple"></i> <?php echo $hasPaid ? 'Update Payment' : 'Record Payment'; ?>
                        </a>
                        <a class="dropdown-item fs-bill-btn" href="javascript:void(0)"
                           data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo $sid; ?>"
                           data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                           data-pkgs="<?php echo $pkgCount; ?>" data-rebill="1"
                           onclick="fsBillCustomer(this);">
                            <i class="mdi mdi-cash-refund"></i> Re-bill
                        </a>
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

/** One package card (accordion level 3). */
function fs_render_package($p, $stat = null)
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
        <div class="card-header fs-pkg-header p-2" onclick="fsTogglePackage(this, <?php echo $oid; ?>)">
            <span class="fs-level-chip fs-chip-pkg">PACKAGE</span>
            <i class="mdi mdi-package-variant-closed"></i>
            <b><?php echo $pkgNo; ?></b>
            <span class="fs-dim ml-2" title="Package weight">
                <i class="mdi mdi-weight"></i> <?php echo round($w, 2); ?> lb
            </span>
            <?php echo $itemsBadge; ?>
            <span class="fs-spacer"></span>
            <span class="fs-money fs-pkg-total" data-usd="<?php echo (float) $p->total_order; ?>">$<?php echo number_format((float) $p->total_order, 2); ?></span>
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
                        (consolidate_id, sender_id, amount_usd, amount_ghs, handling_ghs, billed_by, billed_at)
                    VALUES (:cid, :sid, :amt, :ghs, :fee, :by, NOW())
                    ON DUPLICATE KEY UPDATE
                        amount_usd = VALUES(amount_usd), amount_ghs = VALUES(amount_ghs),
                        handling_ghs = VALUES(handling_ghs), billed_by = VALUES(billed_by),
                        billed_at = VALUES(billed_at)");
    $db->bind(':cid', $cid);
    $db->bind(':sid', $sid);
    $db->bind(':amt', round($total, 2));
    $db->bind(':ghs', $billGhs);
    $db->bind(':fee', $feeGhs);
    $db->bind(':by', $uid);
    $db->cdp_execute();

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
// RECORD PAYMENT (JSON) — stage 2: the customer pays on pickup. The discount
// (bill − paid, never negative) is auto-calculated. Re-recording is allowed;
// every action is logged and the customer gets a receipt message best-effort.
// ----------------------------------------------------------------------------
if ($action === 'record_payment') {
    header('Content-Type: application/json; charset=UTF-8');

    $cid  = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid  = (int) ($_REQUEST['sender_id'] ?? 0);
    $raw  = trim((string) ($_REQUEST['paid'] ?? ''));
    $paid = (float) str_replace(',', '', $raw);

    if ($cid <= 0 || $sid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }
    if ($raw === '' || !is_numeric(str_replace(',', '', $raw)) || $paid < 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter an amount of 0 or more.']);
        exit;
    }

    $billedRow = fs_customer_billed($cid, $sid);
    if (!$billedRow) {
        echo json_encode(['ok' => false, 'error' => 'not_billed', 'message' => 'Bill this customer first, then record their payment.']);
        exit;
    }

    $billGhs  = ($billedRow->amount_ghs !== null)
        ? (float) $billedRow->amount_ghs
        : (float) cdp_customerPayableGhs((float) $billedRow->amount_usd, true, (float) $core->exchange_rate)['total'];
    $paid     = round($paid, 2);
    $discount = round(max(0, $billGhs - $paid), 2);

    $db->cdp_query("UPDATE cdb_consolidate_customer_billing
                    SET paid_ghs = :p, discount_ghs = :d, paid_by = :by, paid_at = NOW()
                    WHERE consolidate_id = :cid AND sender_id = :sid");
    $db->bind(':p', $paid);
    $db->bind(':d', $discount);
    $db->bind(':by', $uid);
    $db->bind(':cid', $cid);
    $db->bind(':sid', $sid);
    $db->cdp_execute();

    // Internal-only note (never sent to the customer) — who wrote what, when.
    $note = trim((string) ($_REQUEST['note'] ?? ''));
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
            // Notes table missing (migration §4 not run) — payment still records.
        }
    }

    // Log against every package of this customer in the consolidation.
    $groups = fs_customer_groups($cid);
    $oids = isset($groups[$sid]) ? $groups[$sid]['oids'] : [];
    foreach (array_unique($oids) as $oid) {
        fs_log_history($uid, $oid, 'recorded a payment of ₵' . number_format($paid, 2)
            . ($discount > 0 ? ' (discount ₵' . number_format($discount, 2) . ')' : ''));
    }

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
          . ($discount > 0 ? "\nDiscount Applied: GH₵" . number_format($discount, 2) : '')
        : '';

    $intro  = 'We have received your payment for your package(s) in consolidation ' . $consolNo . ' — thank you!';
    $outro  = 'We appreciate your business and look forward to serving you again.';

    $msgWa = $intro . "\n\n"
        . ($amountBlock !== '' ? "*Payment Receipt*\n" . $amountBlock . "\n\n" : '')
        . $outro;

    $msgEmail = '<p>' . htmlspecialchars($intro) . '</p>'
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
        'ok'            => true,
        'paid_ghs'      => $paid,
        'discount_ghs'  => $discount,
        'bill_ghs'      => round($billGhs, 2),
        'consol'        => fs_consol_summary($cid),
        'sent_whatsapp' => $sentWa,
        'sent_email'    => $sentEmail,
        'warnings'      => $warnings,
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
                <i class="mdi mdi-link-variant"></i> <b>Priced together</b>
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
    foreach ($groups[$sid]['rows'] as $p) {
        echo fs_render_package($p, $stats[(int) $p->oid] ?? null);
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

        $entries = [];

        $entries[] = [
            'ts'   => strtotime((string) $billedRow->billed_at),
            'html' => '<b>' . htmlspecialchars($billedRow->biller) . '</b> billed this customer ₵' . number_format($billGhs, 2)
                . ' ($' . number_format((float) $billedRow->amount_usd, 2)
                . ($feeGhs > 0 ? ' + ₵' . number_format($feeGhs, 2) . ' handling fee' : '') . ')'
                . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $billedRow->billed_at))) . '</span>',
        ];

        if ($billedRow->paid_ghs !== null) {
            $entries[] = [
                'ts'   => strtotime((string) $billedRow->paid_at),
                'html' => '<b>' . htmlspecialchars($billedRow->payer) . '</b> recorded a payment of ₵' . number_format((float) $billedRow->paid_ghs, 2)
                    . ((float) $billedRow->discount_ghs > 0 ? ' (discount ₵' . number_format((float) $billedRow->discount_ghs, 2) . ')' : '')
                    . ' <span class="text-muted">— ' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $billedRow->paid_at))) . '</span>',
            ];
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
// LIST consolidations (default, HTML) — top-level accordions.
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

// Per (consolidation, customer): money + "fully priced" flag, deduped: each
// detail row maps to EXACTLY ONE order ((order_prefix, order_no) is NOT unique
// in cdb_add_order — a plain join multiplies rows and inflates totals). A
// customer is priced when NONE of their packages has an item without priced_at.
$cids = array_map(function ($c) { return (int) $c->consolidate_id; }, $consolidations);
$totMap = [];
if ($cids) {
    $in = implode(',', $cids);
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
    foreach ((array) $db->cdp_registros() as $t) {
        $tcid = (int) $t->cid;
        if (!isset($totMap[$tcid])) {
            $totMap[$tcid] = ['money' => 0.0, 'fees_ghs' => 0.0, 'weight' => 0.0, 'custs' => 0, 'custs_priced' => 0, 'paid' => 0.0];
        }
        $totMap[$tcid]['money']        += (float) $t->money;
        // One handling fee per customer, from their TOTAL payable.
        $totMap[$tcid]['fees_ghs']     += cdp_handlingFeeGhs(cdp_usdToGhs((float) $t->money, $rateNow));
        $totMap[$tcid]['weight']       += (float) $t->wsum;
        $totMap[$tcid]['custs']        += 1;
        $totMap[$tcid]['custs_priced'] += (int) $t->all_priced;
    }

    // Amount paid so far per consolidation (stage-2 payments, GHS).
    $db->cdp_query("SELECT consolidate_id AS cid, COALESCE(SUM(paid_ghs),0) AS paid
                    FROM cdb_consolidate_customer_billing
                    WHERE consolidate_id IN ($in) AND paid_ghs IS NOT NULL
                    GROUP BY consolidate_id");
    $db->cdp_execute();
    foreach ((array) $db->cdp_registros() as $t) {
        $tcid = (int) $t->cid;
        if (isset($totMap[$tcid])) {
            $totMap[$tcid]['paid'] = (float) $t->paid;
        }
    }
}

$dgStyle = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
$dgColor = ($dgStyle && !empty($dgStyle->color)) ? $dgStyle->color : '#ff6d00';
?>
<div id="report-has-data" data-has="1"></div>
<div class="accordion" id="fsAccordion">
    <?php foreach ($consolidations as $c):
        $cid = (int) $c->consolidate_id;
        $cNo = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));
        $t   = $totMap[$cid] ?? null;
        $money = $t ? $t['money'] : 0.0;
        $feeUsd = $t ? cdp_ghsToUsd($t['fees_ghs'], (float) $core->exchange_rate) : 0.0;
        $dueUsd = $money + $feeUsd;
        $weight = $t ? $t['weight'] : 0.0;
        $custs = $t ? $t['custs'] : 0;
        $custsPriced = $t ? $t['custs_priced'] : 0;
        $paid  = $t ? $t['paid'] : 0.0;
        $custBadgeCls = ($custs > 0 && $custsPriced >= $custs) ? 'badge-success' : 'badge-warning';
        ?>
        <div class="card mb-2 fs-consol-card">
            <div class="card-header fs-consol-header"
                 onclick="window.open('financial_sheet_consolidation.php?id=<?php echo $cid; ?>', '_blank')"
                 title="Open this consolidation in a new tab">
                <i class="fas fa-boxes"></i>
                <b><?php echo $cNo; ?></b>
                <span class="fs-dim ml-3"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $c->c_date); ?></span>
                <span class="fs-dim ml-3" title="Sum of package weights">
                    <i class="mdi mdi-weight"></i> <?php echo round($weight, 2); ?> lb
                </span>
                <span class="badge <?php echo $custBadgeCls; ?> ml-3 fs-consol-custpriced" data-cid="<?php echo $cid; ?>"
                      title="Customers whose packages are fully priced">
                    <i class="mdi mdi-account-check"></i> <?php echo $custsPriced; ?>/<?php echo $custs; ?> customers priced
                </span>
                <?php if ((int) $c->is_dangerous_good === 1): ?>
                    <span class="fs-dg-badge" style="background:<?php echo htmlspecialchars($dgColor); ?>;"
                          title="This consolidation contains dangerous goods">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                <?php endif; ?>
                <span class="fs-spacer"></span>
                <span class="fs-dim fs-consol-fees" data-cid="<?php echo $cid; ?>"
                      title="Packages + handling fees">$<?php echo number_format($money, 2); ?> + $<?php echo number_format($feeUsd, 2); ?> fees</span>
                <span class="fs-money fs-consol-total" data-cid="<?php echo $cid; ?>" data-usd="<?php echo $dueUsd; ?>"
                      title="Amount due incl. handling fees">Due $<?php echo number_format($dueUsd, 2); ?></span>
                <?php if ($paid > 0): ?>
                    <span class="fs-chip-paid fs-consol-paid" data-cid="<?php echo $cid; ?>"
                          title="Amount received from customers so far">Received $<?php echo number_format(cdp_ghsToUsd($paid, (float) $core->exchange_rate), 2); ?></span>
                <?php endif; ?>
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
                <i class="mdi mdi-open-in-new fs-caret ml-2"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>
