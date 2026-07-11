<?php
// ============================================================================
// Warehouse Delivery — tiered (FS-style) delivery workspace.
//
// Mirrors the Financial Sheet's accordion — consolidation -> customer ->
// package -> item — but with DELIVERY actions instead of finance actions.
// Delivery is BY PACKAGE only. "Deliver All" delivers just a customer's
// cleared, not-yet-delivered packages. When every package in a consolidation
// is Delivered, the consolidation's own status_courier flips to Delivered (8);
// undoing a package reopens it (and reopens the consolidation if it was
// closed). Only finance-cleared packages (fs_cleared_for_delivery = 1) can be
// delivered — enforced server-side, never trust the client.
//
// Actions (all require view_warehouse_delivery; writes require their own perm):
//   list            -> consolidation cards (level 1)
//   customers       -> ?consolidate_id            customer cards (level 2)
//   packages        -> ?consolidate_id&sender_id  package + item cards (3/4)
//   deliver_package -> POST order_no              deliver one package
//   deliver_user    -> POST consolidate_id&sender_id  deliver a customer's cleared pkgs
//   undo_package    -> POST order_no              reopen a delivered package
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once("../../helpers/querys.php");
// PHPMailer MUST be loaded before anything that can call cdp_sendTemplateEmail
// (the delivery notification), or the process crashes silently. WhatsApp
// service powers the "your package was delivered" message.
require_once(__DIR__ . '/../../helpers/phpmailer/class.phpmailer.php');
require_once(__DIR__ . '/../../helpers/phpmailer/class.smtp.php');
require_once(__DIR__ . '/../notify_whatsapp/api_whatsapp_service_v2.php');
require_login();
require_permission('view_warehouse_delivery');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!defined('CDP_WD_DELIVERED'))  { define('CDP_WD_DELIVERED', 8); }  // Delivered
if (!defined('CDP_WD_REOPEN'))     { define('CDP_WD_REOPEN', 4); }     // In_Warehouse (undo target)

// Terminal / exited statuses a package can't be "delivered" out of here.
$WD_TERMINAL = [15, 16, 21, 27, 35]; // Picked up, Not Picked Up, Cancelled, Returned, Auction

$db       = new Conexion;
$user     = new User;
$core     = new Core;
$userData = $user->cdp_getUserData();
$uid      = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));

// Per-action capabilities (authoritative; the view mirrors these for the UI).
$canDeliverPkg  = $user->cdp_hasPermission('warehouse_deliver_package');
$canDeliverUser = $user->cdp_hasPermission('warehouse_deliver_user');
$canUndo        = $user->cdp_hasPermission('warehouse_undo_delivery');

// userlevel 1 (customer) is defensively limited to their own packages.
$ownOnly = (isset($userData->userlevel) && (int) $userData->userlevel === 1)
    ? (' AND a.sender_id = ' . (int) $_SESSION['userid']) : '';

$action = $_REQUEST['action'] ?? 'list';

// ---------------------------------------------------------------------------
// COUNT — cleared-for-delivery packages still needing delivery (the WD "ready"
// universe). Powers the real-time sidebar badge.
// ---------------------------------------------------------------------------
if ($action === 'count') {
    header('Content-Type: application/json; charset=UTF-8');
    $db->cdp_query("SELECT COUNT(DISTINCT a.order_id) AS n
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                    WHERE a.fs_cleared_for_delivery = 1
                      AND a.status_courier NOT IN (8, 15, 16, 21, 27, 35) $ownOnly");
    $db->cdp_execute();
    $row = $db->cdp_registro();
    echo json_encode(['ok' => true, 'count' => $row ? (int) $row->n : 0]);
    exit;
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/** All packages of a consolidation (deduped detail -> order), with delivery state. */
function wd_consolidation_packages(Conexion $db, $cid, $ownOnly)
{
    $db->cdp_query("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.status_courier,
                           a.fs_cleared_for_delivery, a.total_weight,
                           s.mod_style AS status_name, s.color AS status_color,
                           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''),
                                    CONCAT('User ', a.sender_id)) AS customer,
                           u.locker AS locker
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                    LEFT JOIN cdb_styles s ON s.id = a.status_courier
                    LEFT JOIN cdb_users u ON u.id = a.sender_id
                    WHERE d.consolidate_id = :cid $ownOnly
                    ORDER BY customer ASC, a.order_id DESC");
    $db->bind(':cid', (int) $cid);
    $db->cdp_execute();
    return $db->cdp_registros() ?: [];
}

/** Delivery state for one package row: 'delivered' | 'ready' | 'awaiting' | 'other'. */
function wd_pkg_state($p, array $terminal)
{
    if ((int) $p->status_courier === CDP_WD_DELIVERED) { return 'delivered'; }
    if (in_array((int) $p->status_courier, $terminal, true)) { return 'other'; }
    return ((int) $p->fs_cleared_for_delivery === 1) ? 'ready' : 'awaiting';
}

/** After a delivery/undo, recompute a consolidation's status_courier.
 *  Delivered (8) only when EVERY package in it is delivered; otherwise, if it
 *  was previously closed as Delivered, reopen it to In_Warehouse. */
function wd_sync_consolidation_status(Conexion $db, $cid)
{
    $db->cdp_query("SELECT a.status_courier
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                    WHERE d.consolidate_id = :cid");
    $db->bind(':cid', (int) $cid);
    $db->cdp_execute();
    $rows = $db->cdp_registros() ?: [];
    if (!$rows) { return; }

    $allDelivered = true;
    foreach ($rows as $r) {
        if ((int) $r->status_courier !== CDP_WD_DELIVERED) { $allDelivered = false; break; }
    }

    $db->cdp_query("SELECT status_courier FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', (int) $cid);
    $db->cdp_execute();
    $cur = $db->cdp_registro();
    $curStatus = $cur ? (int) $cur->status_courier : 0;

    if ($allDelivered && $curStatus !== CDP_WD_DELIVERED) {
        $db->cdp_query("UPDATE cdb_consolidate SET status_courier = :st WHERE consolidate_id = :cid");
        $db->bind(':st', CDP_WD_DELIVERED);
        $db->bind(':cid', (int) $cid);
        $db->cdp_execute();
    } elseif (!$allDelivered && $curStatus === CDP_WD_DELIVERED) {
        $db->cdp_query("UPDATE cdb_consolidate SET status_courier = :st WHERE consolidate_id = :cid");
        $db->bind(':st', CDP_WD_REOPEN);
        $db->bind(':cid', (int) $cid);
        $db->cdp_execute();
    }
}

/** Consolidation delivery summary as ready-to-inject header HTML fragments
 *  (progress badge + right-side awaiting/state chips). Keeps the single-
 *  consolidation page header in sync after a deliver/undo without a reload. */
function wd_consol_summary(Conexion $db, $cid, array $terminal, $ownOnly)
{
    $pkgs = wd_consolidation_packages($db, $cid, $ownOnly);
    $total = count($pkgs);
    $delivered = 0; $ready = 0; $awaiting = 0;
    foreach ($pkgs as $p) {
        switch (wd_pkg_state($p, $terminal)) {
            case 'delivered': $delivered++; break;
            case 'ready':     $ready++;     break;
            case 'awaiting':  $awaiting++;  break;
        }
    }
    // The consolidation only ever shows a "Delivered" chip (once every package
    // is delivered). Progress otherwise is conveyed by the n/n Delivered badge.
    $stateHtml = ($total > 0 && $delivered >= $total)
        ? '<span class="wd-chip-done"><i class="mdi mdi-check-all"></i> Delivered</span>'
        : '';
    $progCls = ($delivered >= $total && $total > 0) ? 'badge-success' : 'badge-light';

    $progHtml = '<span class="badge ' . $progCls . ' ml-2" title="Packages delivered">'
        . $delivered . '/' . $total . ' Delivered</span>';
    $rightHtml = ($awaiting > 0
        ? '<span class="wd-chip-wait mr-1" title="Not yet cleared by Accounts"><i class="mdi mdi-lock"></i> ' . $awaiting . ' Awaiting</span>'
        : '') . $stateHtml;

    return ['prog_html' => $progHtml, 'right_html' => $rightHtml];
}

/** Render the customer cards (level 2) for one consolidation. Shared by the
 *  `customers` action and the search shells. $sidFilter limits to specific
 *  customers (used by search); null = all customers in the consolidation. */
function wd_render_customers($cid, array $pkgs, $canDeliverUser, array $terminal, array $sidFilter = null)
{
    $groups = [];
    foreach ($pkgs as $p) {
        $sid = (int) $p->sender_id;
        if ($sidFilter !== null && !in_array($sid, $sidFilter, true)) { continue; }
        if (!isset($groups[$sid])) {
            $label = trim((string) $p->customer);
            if ($p->locker) { $label .= ' (' . $p->locker . ')'; }
            $groups[$sid] = ['label' => $label, 'pkgs' => []];
        }
        $groups[$sid]['pkgs'][] = $p;
    }

    ob_start();
    foreach ($groups as $sid => $g) {
        $total = count($g['pkgs']);
        $delivered = 0; $ready = 0; $awaiting = 0;
        foreach ($g['pkgs'] as $p) {
            switch (wd_pkg_state($p, $terminal)) {
                case 'delivered': $delivered++; break;
                case 'ready':     $ready++;     break;
                case 'awaiting':  $awaiting++;  break;
            }
        }

        $initials = '';
        foreach (preg_split('/\s+/', trim($g['label'])) as $w) {
            if ($w !== '' && mb_strlen($initials) < 2) { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); }
        }
        if ($initials === '') { $initials = '?'; }

        $badgeCls = ($total > 0 && $delivered >= $total) ? 'badge-success' : 'badge-light';
        ?>
        <div class="card mb-2 wd-cust-card" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo (int) $sid; ?>">
            <div class="card-header wd-cust-header p-2" onclick="wdToggle(this, event, 'cust')">
                <span class="wd-avatar"><?php echo htmlspecialchars($initials); ?></span>
                <b><?php echo htmlspecialchars($g['label']); ?></b>
                <span class="badge <?php echo $badgeCls; ?> ml-2"><?php echo $delivered; ?>/<?php echo $total; ?> Delivered</span>
                <?php if ($awaiting > 0): ?><span class="wd-chip-wait ml-1"><i class="mdi mdi-lock"></i> <?php echo $awaiting; ?> Awaiting</span><?php endif; ?>
                <span class="wd-spacer"></span>
                <?php if ($canDeliverUser && $ready > 0): ?>
                    <button type="button" class="btn btn-sm wd-btn-deliver wd-actions"
                            data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo (int) $sid; ?>"
                            data-name="<?php echo htmlspecialchars($g['label'], ENT_QUOTES); ?>"
                            data-ready="<?php echo $ready; ?>"
                            onclick="event.stopPropagation(); wdDeliverUser(this);">
                        <i class="mdi mdi-truck-check"></i> Deliver All (<?php echo $ready; ?>)
                    </button>
                <?php endif; ?>
                <i class="mdi mdi-chevron-down wd-caret wd-caret-toggle ml-2"></i>
            </div>
            <div class="wd-cust-body" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo (int) $sid; ?>" style="display:none;">
                <div class="card-body p-2 wd-packages" data-cid="<?php echo (int) $cid; ?>" data-sid="<?php echo (int) $sid; ?>" data-loaded="0">
                    <div class="text-muted small">Loading packages…</div>
                </div>
            </div>
        </div>
        <?php
    }
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// LIST — consolidation cards (level 1)
// ---------------------------------------------------------------------------
if ($action === 'list') {
    $search = trim((string) ($_REQUEST['search'] ?? ''));

    $where = " WHERE EXISTS (SELECT 1 FROM cdb_consolidate_detail d
                             INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                             WHERE d.consolidate_id = c.consolidate_id
                               AND a.fs_cleared_for_delivery = 1 $ownOnly) ";
    $bind = [];
    if ($search !== '') {
        $where .= " AND (CONCAT(COALESCE(c.c_prefix,''),COALESCE(c.c_no,'')) LIKE :q
                         OR EXISTS (SELECT 1 FROM cdb_consolidate_detail d2
                                    INNER JOIN cdb_add_order a2 ON a2.order_id = CAST(d2.order_id AS UNSIGNED)
                                    LEFT JOIN cdb_users u2 ON u2.id = a2.sender_id
                                    WHERE d2.consolidate_id = c.consolidate_id
                                      AND (CONCAT(COALESCE(u2.fname,''),' ',COALESCE(u2.lname,'')) LIKE :q
                                           OR u2.locker LIKE :q
                                           OR CONCAT(COALESCE(a2.order_prefix,''),COALESCE(a2.order_no,'')) LIKE :q))) ";
        $bind[':q'] = '%' . $search . '%';
    }

    $db->cdp_query("SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.status_courier
                    FROM cdb_consolidate c
                    $where
                    ORDER BY c.consolidate_id DESC
                    LIMIT 300");
    foreach ($bind as $k => $v) { $db->bind($k, $v); }
    $db->cdp_execute();
    $consols = $db->cdp_registros() ?: [];

    if (!$consols) {
        echo '<div class="text-center text-muted py-5">'
            . '<img src="assets/images/alert/ohh_shipment.png" width="130"><br>'
            . 'No consolidations have packages cleared for delivery right now.</div>';
        exit;
    }

    foreach ($consols as $c) {
        $cid  = (int) $c->consolidate_id;
        $no   = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));
        $pkgs = wd_consolidation_packages($db, $cid, $ownOnly);

        $total = count($pkgs);
        $delivered = 0; $ready = 0; $awaiting = 0; $weight = 0.0;
        $customers = [];
        foreach ($pkgs as $p) {
            $weight += (float) $p->total_weight;
            $customers[(int) $p->sender_id] = true;
            switch (wd_pkg_state($p, $WD_TERMINAL)) {
                case 'delivered': $delivered++; break;
                case 'ready':     $ready++;     break;
                case 'awaiting':  $awaiting++;  break;
            }
        }
        $custCount = count($customers);

        // The consolidation only shows a "Delivered" chip once every package is
        // delivered; the n/n Delivered badge conveys progress otherwise.
        $stateChip = ($total > 0 && $delivered >= $total)
            ? '<span class="wd-chip-done"><i class="mdi mdi-check-all"></i> Delivered</span>'
            : '';
        $progCls = ($delivered >= $total && $total > 0) ? 'badge-success' : 'badge-light';
        ?>
        <div class="card mb-2 wd-consol-card" data-cid="<?php echo $cid; ?>">
            <div class="card-header wd-consol-header wd-link p-2"
                 onclick="window.location.href = 'warehouse_delivery_consolidation.php?id=<?php echo $cid; ?>'"
                 title="Open this consolidation">
                <i class="mdi mdi-package-variant-closed wd-consol-ico"></i>
                <b class="wd-mono"><?php echo $no; ?></b>
                <span class="wd-dim ml-2"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $c->c_date); ?></span>
                <span class="wd-dim ml-2" title="Sum of package weights"><i class="mdi mdi-weight"></i> <?php echo round($weight, 2); ?> lb</span>
                <span class="badge <?php echo $progCls; ?> ml-2" title="Packages delivered"><?php echo $delivered; ?>/<?php echo $total; ?> Delivered</span>
                <span class="wd-dim ml-1"><i class="mdi mdi-account-multiple"></i> <?php echo $custCount; ?></span>
                <span class="wd-spacer"></span>
                <?php if ($awaiting > 0): ?><span class="wd-chip-wait mr-1" title="Not yet cleared by Accounts"><i class="mdi mdi-lock"></i> <?php echo $awaiting; ?> Awaiting</span><?php endif; ?>
                <?php echo $stateChip; ?>
                <i class="mdi mdi-chevron-right wd-caret ml-2"></i>
            </div>
        </div>
        <?php
    }
    exit;
}

// ---------------------------------------------------------------------------
// CUSTOMERS — customer cards for one consolidation (level 2)
// ---------------------------------------------------------------------------
if ($action === 'customers') {
    $cid  = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $pkgs = wd_consolidation_packages($db, $cid, $ownOnly);
    if (!$pkgs) { echo '<div class="text-muted small">No packages.</div>'; exit; }
    echo wd_render_customers($cid, $pkgs, $canDeliverUser, $WD_TERMINAL);
    exit;
}

// ---------------------------------------------------------------------------
// PACKAGES — package cards + items for one customer (levels 3 & 4)
// ---------------------------------------------------------------------------
if ($action === 'packages') {
    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $sid = (int) ($_REQUEST['sender_id'] ?? 0);
    $all = wd_consolidation_packages($db, $cid, $ownOnly);
    $pkgs = array_values(array_filter($all, function ($p) use ($sid) { return (int) $p->sender_id === $sid; }));
    if (!$pkgs) { echo '<div class="text-muted small">No packages.</div>'; exit; }

    foreach ($pkgs as $p) {
        $oid     = (int) $p->order_id;
        $no      = (string) $p->order_no;
        $track   = htmlspecialchars(($p->order_prefix ?? '') . $p->order_no);
        $state   = wd_pkg_state($p, $WD_TERMINAL);
        $stColor = $p->status_color ?: '#6c757d';
        $stName  = htmlspecialchars(str_replace('_', ' ', (string) $p->status_name));

        // Items (level 4).
        $db->cdp_query("SELECT order_item_description, order_item_quantity, order_item_weight, order_item_weight_group
                        FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
        $db->bind(':oid', $oid);
        $db->cdp_execute();
        $items = $db->cdp_registros() ?: [];
        ?>
        <div class="card mb-1 wd-pkg-card wd-state-<?php echo $state; ?>" data-oid="<?php echo $oid; ?>" data-cid="<?php echo $cid; ?>" data-sid="<?php echo $sid; ?>">
            <div class="card-header wd-pkg-header p-2" onclick="wdToggle(this, event, 'pkg')">
                <span class="wd-level-chip wd-chip-pkg">PACKAGE</span>
                <b class="wd-mono ml-1"><?php echo $track; ?></b>
                <?php // Once delivered, the double-tick "Delivered" chip is enough — the
                      // raw status badge would just repeat it, so hide it. ?>
                <?php if ($state !== 'delivered'): ?>
                <span class="badge ml-2" style="background:<?php echo htmlspecialchars($stColor); ?>;color:#fff;"><?php echo $stName; ?></span>
                <?php endif; ?>
                <?php
                if ($state === 'delivered') { echo '<span class="wd-chip-done ml-1"><i class="mdi mdi-check-all"></i> Delivered</span>'; }
                elseif ($state === 'ready')  { echo '<span class="wd-chip-ready ml-1"><i class="mdi mdi-truck-check"></i> Cleared — Ready</span>'; }
                elseif ($state === 'awaiting') { echo '<span class="wd-chip-wait ml-1"><i class="mdi mdi-lock"></i> Awaiting Clearance</span>'; }
                ?>
                <span class="wd-dim ml-2"><i class="mdi mdi-weight"></i> <?php echo round((float) $p->total_weight, 2); ?> lb</span>
                <span class="wd-spacer"></span>
                <?php
                if ($state === 'ready' && $canDeliverPkg) { ?>
                    <button type="button" class="btn btn-sm wd-btn-deliver wd-actions"
                            data-no="<?php echo htmlspecialchars($no, ENT_QUOTES); ?>"
                            data-track="<?php echo $track; ?>"
                            data-cid="<?php echo $cid; ?>" data-sid="<?php echo $sid; ?>"
                            onclick="event.stopPropagation(); wdDeliverOne(this);">
                        <i class="mdi mdi-truck-check"></i> Deliver
                    </button>
                <?php } elseif ($state === 'delivered' && $canUndo) { ?>
                    <button type="button" class="btn btn-sm btn-outline-danger wd-actions"
                            data-no="<?php echo htmlspecialchars($no, ENT_QUOTES); ?>"
                            data-track="<?php echo $track; ?>"
                            data-cid="<?php echo $cid; ?>" data-sid="<?php echo $sid; ?>"
                            onclick="event.stopPropagation(); wdUndoOne(this);">
                        <i class="mdi mdi-undo-variant"></i> Undo
                    </button>
                <?php } elseif ($state === 'awaiting') { ?>
                    <span class="text-muted small" title="Accounts has not cleared this package for delivery yet"><i class="mdi mdi-lock"></i> Awaiting Clearance</span>
                <?php } ?>
                <i class="mdi mdi-chevron-down wd-caret wd-caret-toggle ml-2"></i>
            </div>
            <div class="wd-pkg-body" style="display:none;">
                <div class="card-body p-2">
                    <?php if (!$items): ?>
                        <div class="text-muted small">No item details recorded for this package.</div>
                    <?php else: ?>
                    <table class="table table-sm wd-item-table mb-0">
                        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Weight (lb)</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><span class="wd-level-chip wd-chip-item">ITEM</span> <?php echo htmlspecialchars((string) ($it->order_item_description ?: '—')); ?></td>
                                <td class="text-center"><?php echo (int) $it->order_item_quantity; ?></td>
                                <td class="text-right"><?php echo round((float) $it->order_item_weight, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    exit;
}

// ---------------------------------------------------------------------------
// SEARCH — behaves like the Financial Sheet: separate filters, and a
// package/customer match renders expanded consolidation shells that contain
// only the matching customers' tiers (packages/items stay lazy-loaded).
// ---------------------------------------------------------------------------

/** (cid => [sid,...]) for consolidations that have >=1 cleared-for-delivery
 *  package AND contain one of the given senders. */
function wd_search_pairs(Conexion $db, array $uids, $ownOnly)
{
    if (!$uids) { return []; }
    $in = implode(',', array_map('intval', $uids));
    $db->cdp_query("SELECT DISTINCT d.consolidate_id AS cid, a.sender_id AS sid
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                    WHERE a.sender_id IN ($in) $ownOnly
                      AND EXISTS (SELECT 1 FROM cdb_consolidate_detail d2
                                  INNER JOIN cdb_add_order a2 ON a2.order_id = CAST(d2.order_id AS UNSIGNED)
                                  WHERE d2.consolidate_id = d.consolidate_id AND a2.fs_cleared_for_delivery = 1)
                    ORDER BY cid DESC LIMIT 100");
    $db->cdp_execute();
    $byCid = [];
    foreach ((array) $db->cdp_registros() as $r) { $byCid[(int) $r->cid][] = (int) $r->sid; }
    return $byCid;
}

/** Render consolidation shells (expanded) containing ONLY the matching
 *  customers' tiers — shared by both WD search actions. */
function wd_render_search_consolidations(Conexion $db, array $byCid, $canDeliverUser, array $terminal, $ownOnly)
{
    ob_start();
    foreach ($byCid as $cid => $sids) {
        $db->cdp_query("SELECT c_prefix, c_no, c_date FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
        $db->bind(':cid', (int) $cid);
        $db->cdp_execute();
        $c = $db->cdp_registro();
        if (!$c) { continue; }
        $no   = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));
        $pkgs = wd_consolidation_packages($db, $cid, $ownOnly);
        $custHtml = wd_render_customers($cid, $pkgs, $canDeliverUser, $terminal, array_values(array_unique($sids)));
        if (trim($custHtml) === '') { continue; }
        ?>
        <div class="card mb-2 wd-consol-card" data-cid="<?php echo (int) $cid; ?>">
            <div class="card-header wd-consol-header p-2" style="cursor:default;">
                <i class="mdi mdi-package-variant-closed wd-consol-ico"></i>
                <b class="wd-mono"><?php echo $no; ?></b>
                <span class="wd-dim ml-2"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) ($c->c_date ?? '')); ?></span>
                <a href="warehouse_delivery_consolidation.php?id=<?php echo (int) $cid; ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Open this consolidation"><i class="mdi mdi-arrow-top-right"></i> Open</a>
                <span class="wd-spacer"></span>
                <span class="wd-dim">showing matching customers only</span>
            </div>
            <div class="card-body p-2 wd-customers" data-cid="<?php echo (int) $cid; ?>" data-loaded="1"><?php echo $custHtml; ?></div>
        </div>
        <?php
    }
    return ob_get_clean();
}

if ($action === 'search_customer') {
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    if ($q === '') { echo '<div class="text-muted p-2">Type a customer name or locker.</div>'; exit; }

    $words = preg_split('/\s+/', mb_strtolower($q));
    $nameConds = [];
    foreach ($words as $i => $w) {
        $nameConds[] = "LOWER(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) LIKE :w$i";
    }
    $db->cdp_query("SELECT u.id FROM cdb_users u
                    WHERE (" . implode(' AND ', $nameConds) . ")
                       OR LOWER(COALESCE(u.locker,'')) LIKE :lk
                    LIMIT 60");
    foreach ($words as $i => $w) { $db->bind(":w$i", '%' . $w . '%'); }
    $db->bind(':lk', mb_strtolower($q) . '%');
    $db->cdp_execute();
    $uids = array_map(function ($r) { return (int) $r->id; }, (array) $db->cdp_registros());

    $byCid = wd_search_pairs($db, $uids, $ownOnly);
    if (!$byCid) {
        echo '<div class="text-center text-muted py-4"><img src="assets/images/alert/ohh_shipment.png" width="120"><br>No matching customers with packages cleared for delivery.</div>';
        exit;
    }
    echo '<div class="alert alert-info py-2 mb-3">Customer matches in <b>' . count($byCid) . '</b> consolidation(s).</div>';
    echo wd_render_search_consolidations($db, $byCid, $canDeliverUser, $WD_TERMINAL, $ownOnly);
    exit;
}

if ($action === 'search_package') {
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    if ($q === '') { echo '<div class="text-muted p-2">Type a package number or tracking value.</div>'; exit; }

    $db->cdp_query("SELECT DISTINCT d.consolidate_id AS cid, a.sender_id AS sid
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
                    WHERE CONCAT(COALESCE(a.order_prefix,''),COALESCE(a.order_no,'')) LIKE :q $ownOnly
                      AND EXISTS (SELECT 1 FROM cdb_consolidate_detail d2
                                  INNER JOIN cdb_add_order a2 ON a2.order_id = CAST(d2.order_id AS UNSIGNED)
                                  WHERE d2.consolidate_id = d.consolidate_id AND a2.fs_cleared_for_delivery = 1)
                    ORDER BY cid DESC LIMIT 100");
    $db->bind(':q', '%' . $q . '%');
    $db->cdp_execute();
    $byCid = [];
    foreach ((array) $db->cdp_registros() as $r) { $byCid[(int) $r->cid][] = (int) $r->sid; }

    if (!$byCid) {
        echo '<div class="text-center text-muted py-4"><img src="assets/images/alert/ohh_shipment.png" width="120"><br>No matching packages in consolidations cleared for delivery.</div>';
        exit;
    }
    echo '<div class="alert alert-info py-2 mb-3">Package matches in <b>' . count($byCid) . '</b> consolidation(s).</div>';
    echo wd_render_search_consolidations($db, $byCid, $canDeliverUser, $WD_TERMINAL, $ownOnly);
    exit;
}

// ---------------------------------------------------------------------------
// Write actions
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=UTF-8');

/** Deliver a single order (by order_no), enforcing clearance. Returns bool. */
function wd_do_deliver(Conexion $db, $no, $uid, array $terminal)
{
    $row = cdp_getCourierMultiple($no);
    if (!$row) { return ['ok' => false, 'reason' => 'not_found']; }
    if ((int) $row->status_courier === CDP_WD_DELIVERED) { return ['ok' => false, 'reason' => 'already']; }
    if ((int) ($row->fs_cleared_for_delivery ?? 0) !== 1) { return ['ok' => false, 'reason' => 'uncleared']; }
    if (in_array((int) $row->status_courier, $terminal, true)) { return ['ok' => false, 'reason' => 'terminal']; }

    cdp_updateStatusCourierMultiple($no, CDP_WD_DELIVERED);
    cdp_freeConsolidatedItem((int) $row->order_id);
    $tracking = ($row->order_prefix ?? '') . $no;
    if (function_exists('cdp_updateShipTrackingMultiple')) {
        cdp_updateShipTrackingMultiple($tracking, CDP_WD_DELIVERED, 'Delivered via Warehouse Delivery', $row->origin_off ?? 0, $uid);
    }
    wd_log($uid, (int) $row->order_id, $tracking, 'package delivered');
    return ['ok' => true, 'order_id' => (int) $row->order_id,
            'tracking' => $tracking, 'sender_id' => (int) $row->sender_id];
}

/** Audit log for a Warehouse Delivery action — who did what, when (mirrors the
 *  Financial Sheet's change log; entries are prefixed "Warehouse Delivery —"). */
function wd_log($uid, $order_id, $track, $what)
{
    if (!function_exists('cdp_insertCourierShipmentUserHistory')) { return; }
    cdp_insertCourierShipmentUserHistory([
        'user_id'      => (int) $uid,
        'order_id'     => (int) $order_id,
        'order_track'  => (string) $track,
        'action'       => 'Warehouse Delivery — ' . $what,
        'date_history' => date('Y-m-d H:i:s'),
    ]);
}

/** Best-effort "your package was delivered" message to the customer (WhatsApp +
 *  email), thanking them by the business name ($core->site_name). Never throws. */
function wd_notifyDelivered(Conexion $db, $core, $senderId, array $trackings)
{
    $trackings = array_values(array_filter(array_map('strval', $trackings), function ($v) { return $v !== ''; }));
    if (!$senderId || !$trackings) { return; }

    $db->cdp_query("SELECT id, fname, lname, phone, email, locker FROM cdb_users WHERE id = :id LIMIT 1");
    $db->bind(':id', (int) $senderId);
    $db->cdp_execute();
    $sender = $db->cdp_registro();
    if (!$sender) { return; }

    $custName = trim((string) ($sender->fname ?? '')) ?: 'there';
    $plural   = count($trackings) > 1;
    $siteName = (string) ($core->site_name ?? '');
    $subject  = 'Your Package' . ($plural ? 's Have' : ' Has') . ' Been Delivered';

    $intro = 'Good news! Your package' . ($plural ? 's have' : ' has')
        . ' been delivered. Thank you for shipping with ' . $siteName . '.';
    $outro = 'We appreciate your business and look forward to serving you again!';

    $msgWa = $intro . "\n\n*Delivered*\n• " . implode("\n• ", $trackings) . "\n\n" . $outro;
    $msgEmail = '<p>' . htmlspecialchars($intro) . '</p>'
        . '<p><b>Delivered</b></p><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $trackings)) . '</li></ul>'
        . '<p>' . htmlspecialchars($outro) . '</p>';

    // WhatsApp (best-effort; wrapped in template 12 for site branding).
    if (trim((string) ($sender->phone ?? '')) !== '' && function_exists('sendNotificationWhatsApp_v2')) {
        try {
            $waBody = $msgWa;
            if (function_exists('getTemplateWhatsApp')) {
                $tpl = getTemplateWhatsApp(12);
                if ($tpl) {
                    $waBody = str_replace(
                        ['[USERNAME]', '[SUBJECT]', '[SITE_NAME]', '[MESSAGE]', '[URL]'],
                        [ucfirst($custName), $subject, $siteName, $msgWa, (string) ($core->site_url ?? '')],
                        $tpl->body
                    );
                }
            }
            sendNotificationWhatsApp_v2($sender, $waBody, 'GH');
        } catch (Throwable $e) { /* best-effort */ }
    }

    // Email (best-effort).
    if (trim((string) ($sender->email ?? '')) !== '' && function_exists('cdp_sendTemplateEmail')) {
        try {
            cdp_sendTemplateEmail(29, trim((string) $sender->email), [
                '[USERNAME]' => $custName,
                '[SUBJECT]'  => $subject,
                '[MESSAGE]'  => $msgEmail,
            ], $subject . ' — ' . $siteName);
        } catch (Throwable $e) { /* best-effort */ }
    }
}

if ($action === 'deliver_package') {
    if (!$canDeliverPkg) { echo json_encode(['ok' => false, 'message' => 'You do not have permission to deliver packages.']); exit; }
    $no = trim((string) ($_POST['order_no'] ?? ''));
    if ($no === '') { echo json_encode(['ok' => false, 'message' => 'No package specified.']); exit; }

    $res = wd_do_deliver($db, $no, $uid, $WD_TERMINAL);
    if (!$res['ok']) {
        $msg = ['already' => 'That package is already delivered.',
                'uncleared' => 'That package is not cleared for delivery.',
                'terminal' => 'That package can no longer be delivered.',
                'not_found' => 'Package not found.'][$res['reason']] ?? 'Could not deliver.';
        echo json_encode(['ok' => false, 'message' => $msg]);
        exit;
    }
    $cid = (int) ($_POST['consolidate_id'] ?? 0);
    if ($cid) { wd_sync_consolidation_status($db, $cid); }
    wd_notifyDelivered($db, $core, (int) ($res['sender_id'] ?? 0), [(string) ($res['tracking'] ?? $no)]);
    echo json_encode(['ok' => true, 'delivered' => 1,
                      'summary' => $cid ? wd_consol_summary($db, $cid, $WD_TERMINAL, $ownOnly) : null]);
    exit;
}

if ($action === 'deliver_user') {
    if (!$canDeliverUser) { echo json_encode(['ok' => false, 'message' => 'You do not have permission to deliver all packages for a customer.']); exit; }
    $cid = (int) ($_POST['consolidate_id'] ?? 0);
    $sid = (int) ($_POST['sender_id'] ?? 0);
    if (!$cid || !$sid) { echo json_encode(['ok' => false, 'message' => 'Missing consolidation or customer.']); exit; }

    $all  = wd_consolidation_packages($db, $cid, $ownOnly);
    $delivered = 0; $skipped = 0; $notifyTracks = [];
    foreach ($all as $p) {
        if ((int) $p->sender_id !== $sid) { continue; }
        if (wd_pkg_state($p, $WD_TERMINAL) !== 'ready') { continue; } // only cleared, undelivered
        $r = wd_do_deliver($db, (string) $p->order_no, $uid, $WD_TERMINAL);
        if ($r['ok']) { $delivered++; $notifyTracks[] = (string) ($r['tracking'] ?? ''); } else { $skipped++; }
    }
    wd_sync_consolidation_status($db, $cid);
    if ($notifyTracks) { wd_notifyDelivered($db, $core, $sid, $notifyTracks); }
    echo json_encode(['ok' => true, 'delivered' => $delivered, 'skipped' => $skipped,
                      'summary' => wd_consol_summary($db, $cid, $WD_TERMINAL, $ownOnly)]);
    exit;
}

if ($action === 'undo_package') {
    if (!$canUndo) { echo json_encode(['ok' => false, 'message' => 'You do not have permission to undo deliveries.']); exit; }
    $no = trim((string) ($_POST['order_no'] ?? ''));
    if ($no === '') { echo json_encode(['ok' => false, 'message' => 'No package specified.']); exit; }

    $row = cdp_getCourierMultiple($no);
    if (!$row) { echo json_encode(['ok' => false, 'message' => 'Package not found.']); exit; }
    if ((int) $row->status_courier !== CDP_WD_DELIVERED) {
        echo json_encode(['ok' => false, 'message' => 'That package is not marked delivered.']);
        exit;
    }

    cdp_updateStatusCourierMultiple($no, CDP_WD_REOPEN);
    $tracking = ($row->order_prefix ?? '') . $no;
    if (function_exists('cdp_updateShipTrackingMultiple')) {
        cdp_updateShipTrackingMultiple($tracking, CDP_WD_REOPEN, 'Delivery reversed via Warehouse Delivery', $row->origin_off ?? 0, $uid);
    }
    wd_log($uid, (int) $row->order_id, $tracking, 'delivery reversed (reopened)');
    $cid = (int) ($_POST['consolidate_id'] ?? 0);
    if ($cid) { wd_sync_consolidation_status($db, $cid); }
    echo json_encode(['ok' => true, 'reopened' => 1,
                      'summary' => $cid ? wd_consol_summary($db, $cid, $WD_TERMINAL, $ownOnly) : null]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
