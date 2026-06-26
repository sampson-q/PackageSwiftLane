<?php
// ============================================================================
// Financial Sheet — data + actions endpoint
// Nested lazy-loaded accordions: consolidation -> packages -> items.
// Items are editable by weight OR custom price, guarded by a hard per-package
// edit lock (auto-expiring). All money is USD here.
// ============================================================================

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
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

/**
 * Return existing columns for a table in the current database.
 */
function cdp_fs_get_table_columns_ajax($db, $table)
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $cache[$table] = [];

    try {
        $db->cdp_query("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $db->bind(':table_name', $table);
        $db->cdp_execute();
        $rows = $db->cdp_registros();

        if ($rows) {
            foreach ($rows as $row) {
                $name = strtolower((string) ($row->COLUMN_NAME ?? $row->column_name ?? ''));
                if ($name !== '') {
                    $cache[$table][$name] = true;
                }
            }
        }
    } catch (Throwable $e) {
        // Ignore schema lookup failure; base search still works.
    }

    return $cache[$table];
}

function cdp_fs_has_column_ajax($columns, $name)
{
    return isset($columns[strtolower($name)]);
}

/**
 * Build a package/tracking search WHERE clause against cdb_add_order.
 */
function cdp_fs_build_package_search_where($db)
{
    $columns = cdp_fs_get_table_columns_ajax($db, 'cdb_add_order');

    $allowed = [
        'order_id',
        'order_prefix',
        'order_no',
        'tracking_no',
        'tracking_number',
        'tracking',
        'tracking_code',
        'order_tracking',
        'order_tracking_no',
        'postal_tracking',
        'postal_tracking_no',
        'postal_tracking_number',
        'custom_tracking',
        'custom_tracking_no',
        'custom_tracking_number',
        'reference_no',
        'reference',
        'waybill',
        'waybill_no',
        'awb',
        'shipment_no',
        'parcel_no',
        'barcode',
    ];

    $parts = [];
    $parts[] = "LOWER(CONCAT(COALESCE(a.order_prefix,''), COALESCE(a.order_no,''))) LIKE :q";
    $parts[] = "LOWER(CAST(a.order_id AS CHAR)) LIKE :q";

    foreach ($allowed as $col) {
        if (cdp_fs_has_column_ajax($columns, $col)) {
            $safeCol = cdp_fs_escape_column_ajax($col);
            $parts[] = "LOWER(COALESCE(CAST(a.$safeCol AS CHAR), '')) LIKE :q";
        }
    }

    return implode(' OR ', $parts);
}

function cdp_fs_escape_column_ajax($column)
{
    return $column;
}

// ----------------------------------------------------------------------------
// LOCK HEARTBEAT / RELEASE / SAVE  (JSON responses)
// ----------------------------------------------------------------------------
if ($action === 'lock' || $action === 'unlock' || $action === 'save_item') {
    header('Content-Type: application/json; charset=UTF-8');
    $order_id = (int) ($_REQUEST['order_id'] ?? 0);

    if ($action === 'unlock') {
        cdp_fsReleaseLock($order_id, $uid);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'lock') {
        $res = cdp_fsAcquireLock($order_id, $uid, $uname);
        echo json_encode($res['ok'] ? ['ok' => true] : ['ok' => false, 'by' => $res['by']]);
        exit;
    }

    // save_item — update one item's weight OR custom price, then recompute totals.
    $order_item_id = (int) ($_REQUEST['order_item_id'] ?? 0);
    $mode  = (($_REQUEST['mode'] ?? 'weight') === 'custom') ? 'custom' : 'weight';
    $value = (float) str_replace(',', '', (string) ($_REQUEST['value'] ?? '0'));
    // The sheet can be edited in GHS (operator view) while storage stays USD.
    // Only the custom price is a monetary value — weight is currency-agnostic.
    $currency = (strtolower((string) ($_REQUEST['currency'] ?? 'usd')) === 'ghs') ? 'ghs' : 'usd';
    if ($mode === 'custom' && $currency === 'ghs' && $value > 0) {
        $value = round(cdp_ghsToUsd($value, (float) $core->exchange_rate), 2);
    }

    // The lock must still be held by this user.
    $lock = cdp_fsAcquireLock($order_id, $uid, $uname);
    if (!$lock['ok']) {
        echo json_encode(['ok' => false, 'error' => 'locked', 'by' => $lock['by']]);
        exit;
    }
    if ($order_item_id <= 0 || $order_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }
    if ($value <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_value', 'message' => 'Enter a value greater than 0.']);
        exit;
    }

    // Enforce weight XOR custom price.
    if ($mode === 'custom') {
        $db->cdp_query("UPDATE cdb_add_order_item SET custom_price = :cp, order_item_weight = 0 WHERE order_item_id = :iid AND order_id = :oid");
        $db->bind(':cp', $value);
    } else {
        $db->cdp_query("UPDATE cdb_add_order_item SET order_item_weight = :w, custom_price = NULL WHERE order_item_id = :iid AND order_id = :oid");
        $db->bind(':w', $value);
    }
    $db->bind(':iid', $order_item_id);
    $db->bind(':oid', $order_id);
    $db->cdp_execute();

    $totals = cdp_recalcCourierShipmentTotals($order_id);

    // ---- Audit: record who changed what on this package (the change log). -----
    $db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $ord   = $db->cdp_registro();
    $track = $ord ? ($ord->order_prefix . $ord->order_no) : '';

    $db->cdp_query("SELECT order_item_description FROM cdb_add_order_item WHERE order_item_id = :iid LIMIT 1");
    $db->bind(':iid', $order_item_id);
    $db->cdp_execute();
    $itRow = $db->cdp_registro();
    $desc  = $itRow ? trim((string) $itRow->order_item_description) : '';
    if ($desc === '') {
        $desc = 'item';
    }

    // $value is already canonical USD here (GHS input was converted above).
    $what = ($mode === 'custom')
        ? 'set "' . $desc . '" custom price to $' . number_format($value, 2) . ' USD'
        : 'set "' . $desc . '" weight to ' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

    $history = null;
    if (function_exists('cdp_insertCourierShipmentUserHistory')) {
        cdp_insertCourierShipmentUserHistory([
            'user_id'     => $uid,
            'order_id'    => $order_id,
            'order_track' => $track,
            'action'      => 'Financial Sheet — ' . $what,
            'date_history' => date('Y-m-d H:i:s'),
        ]);
        $history = ['who' => $uname, 'what' => $what, 'when' => date('Y-m-d H:i')];
    }

    echo json_encode([
        'ok'          => true,
        'total_order'  => $totals ? $totals['total_order'] : null,
        'sub_total'    => $totals ? $totals['sub_total'] : null,
        'history'      => $history,
    ]);
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

    $sql = "
        SELECT
            c.consolidate_id,
            c.c_prefix,
            c.c_no,
            c.c_date,
            c.status_courier,
            c.is_dangerous_good,
            d.detail_id,
            d.weight AS detail_weight,
            a.order_id AS oid,
            a.order_prefix,
            a.order_no,
            a.total_order
        FROM cdb_consolidate_detail d
        INNER JOIN cdb_consolidate c
            ON c.consolidate_id = d.consolidate_id
        INNER JOIN cdb_add_order a
            ON a.order_id = (
                SELECT a2.order_id
                FROM cdb_add_order a2
                WHERE a2.order_prefix = d.order_prefix
                  AND a2.order_no = d.order_no
                ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
                LIMIT 1
            )
        WHERE ($where)
        ORDER BY c.consolidate_id DESC, d.detail_id ASC
        LIMIT 300
    ";

    $db->cdp_query($sql);
    $db->bind(':q', '%' . mb_strtolower($search) . '%');
    $db->cdp_execute();
    $rows = $db->cdp_registros();

    if (!$rows) {
        echo '<div id="report-has-data" data-has="0"></div>';
        echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No matching packages found.</p></div>';
        exit;
    }

    $grouped = [];
    foreach ($rows as $row) {
        $cid = (int) $row->consolidate_id;
        if (!isset($grouped[$cid])) {
            $grouped[$cid] = [
                'meta' => $row,
                'rows' => [],
            ];
        }
        $grouped[$cid]['rows'][] = $row;
    }

    $totalMatches = count($rows);
    $totalConsol   = count($grouped);

    echo '<div id="report-has-data" data-has="1"></div>';
    echo '<div class="alert alert-info mb-3">';
    echo '<b>' . (int) $totalMatches . '</b> matching package row(s) found across <b>' . (int) $totalConsol . '</b> consolidation(s).';
    echo '</div>';

    $dgStyle = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
    $dgColor = ($dgStyle && !empty($dgStyle->color)) ? $dgStyle->color : '#ff6d00';
    ?>
    <div class="accordion" id="fsSearchResults">
        <?php foreach ($grouped as $cid => $group):
            $meta = $group['meta'];
            $items = $group['rows'];
            $consolNo = htmlspecialchars(($meta->c_prefix ?? '') . ($meta->c_no ?? ''));
            $pkgCount = count($items);
            ?>
            <div class="card mb-2 fs-consol-card fs-active">
                <div class="card-header fs-consol-header">
                    <i class="fas fa-boxes"></i>
                    <b><?php echo $consolNo; ?></b>
                    <span class="ml-3 fs-dim"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) ($meta->c_date ?? '')); ?></span>
                    <span class="ml-3 fs-dim" title="Matching package count">
                        <i class="mdi mdi-package-variant-closed"></i> <?php echo (int) $pkgCount; ?> match(es)
                    </span>
                    <?php if ((int) ($meta->is_dangerous_good ?? 0) === 1): ?>
                        <span class="fs-dg-badge" style="background:<?php echo htmlspecialchars($dgColor); ?>;"
                              title="This consolidation contains dangerous goods">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="fs-consol-body" style="display:block;">
                    <div class="card-body p-2 fs-packages" data-loaded="1">
                        <?php foreach ($items as $p):
                            $oid = (int) $p->oid;
                            $pkgNo = htmlspecialchars(($p->order_prefix ?? '') . ($p->order_no ?? ''));
                            ?>
                            <div class="card mb-1 fs-pkg-card">
                                <div class="card-header fs-pkg-header p-2" onclick="fsTogglePackage(this, <?php echo $oid; ?>)">
                                    <span class="fs-level-chip fs-chip-pkg">PACKAGE</span>
                                    <i class="mdi mdi-package-variant-closed"></i>
                                    <b><?php echo $pkgNo; ?></b>
                                    <span class="text-muted ml-2" title="Package weight">
                                        <i class="mdi mdi-weight-kilogram"></i> <?php echo round((float) $p->detail_weight, 2); ?>
                                    </span>
                                    <span class="float-right">
                                        <span class="fs-money fs-pkg-total" data-usd="<?php echo (float) $p->total_order; ?>">$<?php echo number_format((float) $p->total_order, 2); ?></span>
                                        <i class="mdi mdi-chevron-down fs-pkg-caret ml-2"></i>
                                    </span>
                                </div>
                                <div class="fs-pkg-body" data-oid="<?php echo $oid; ?>" style="display:none;">
                                    <div class="card-body p-2 fs-items" data-loaded="0">
                                        <div class="text-muted small">Click to load items…</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    exit;
}

// ----------------------------------------------------------------------------
// ITEMS for a package (HTML) — acquires the lock for editing.
// ----------------------------------------------------------------------------
if ($action === 'items') {
    $order_id = (int) ($_REQUEST['order_id'] ?? 0);

    $lock = cdp_fsAcquireLock($order_id, $uid, $uname);
    $editable = $lock['ok'];

    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $items = $db->cdp_registros();

    if (!$editable) {
        echo '<div class="alert alert-warning mb-2"><i class="fas fa-lock"></i> This package is being edited by <b>'
            . htmlspecialchars($lock['by'] ?? 'another user') . '</b>. It is read-only until they finish.</div>';
    }
    if (!$items) {
        echo '<div class="text-muted">No items in this package.</div>';
        exit;
    }
    ?>
    <table class="table table-sm table-bordered mb-0 fs-items-table" data-oid="<?php echo $order_id; ?>">
        <thead>
            <tr style="background:#f1f3f5;">
                <th style="width:55px;">Qty</th>
                <th>Description</th>
                <th style="width:160px;">Pricing mode</th>
                <th style="width:120px;">Weight</th>
                <th style="width:180px;">Custom Price</th>
                <th style="width:70px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it):
                $iid       = (int) $it->order_item_id;
                $custom    = isset($it->custom_price) ? (float) $it->custom_price : 0.0;
                $useCustom = $custom > 0;
                $dis       = $editable ? '' : 'disabled';
                ?>
                <tr data-iid="<?php echo $iid; ?>">
                    <td><?php echo (int) $it->order_item_quantity; ?></td>
                    <td><?php echo htmlspecialchars($it->order_item_description ?? ''); ?></td>
                    <td>
                        <div class="btn-group btn-group-sm fs-mode" role="group">
                            <button type="button" class="btn <?php echo $useCustom ? 'btn-outline-dark' : 'btn-dark'; ?>"
                                    onclick="fsSetMode(<?php echo $iid; ?>, 'weight')" <?php echo $dis; ?>>Weight</button>
                            <button type="button" class="btn <?php echo $useCustom ? 'btn-success' : 'btn-outline-success'; ?>"
                                    onclick="fsSetMode(<?php echo $iid; ?>, 'custom')" <?php echo $dis; ?>>Custom</button>
                        </div>
                        <input type="hidden" class="fs-mode-val" value="<?php echo $useCustom ? 'custom' : 'weight'; ?>">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm fs-weight"
                               value="<?php echo $useCustom ? '' : ((float) $it->order_item_weight ?: ''); ?>"
                               placeholder="<?php echo $useCustom ? '—' : 'weight'; ?>"
                               <?php echo ($useCustom || !$editable) ? 'disabled' : ''; ?>
                               onkeypress="return fsIsNumber(event)">
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm fs-cur-mini mb-1" role="group" aria-label="Entry currency">
                            <button type="button" class="btn btn-primary active fs-cur-btn" data-cur="usd"
                                    onclick="fsToggleItemCur(<?php echo $iid; ?>, 'usd')" <?php echo $editable ? '' : 'disabled'; ?>>$</button>
                            <button type="button" class="btn btn-outline-secondary fs-cur-btn" data-cur="ghs"
                                    onclick="fsToggleItemCur(<?php echo $iid; ?>, 'ghs')" <?php echo $editable ? '' : 'disabled'; ?>>&#8373;</button>
                        </div>
                        <input type="text" class="form-control form-control-sm fs-custom"
                               data-usd="<?php echo $useCustom ? (float) $custom : ''; ?>"
                               data-cur="usd"
                               value="<?php echo $useCustom ? ($custom ?: '') : ''; ?>"
                               placeholder="<?php echo $useCustom ? 'USD' : '—'; ?>"
                               <?php echo (!$useCustom || !$editable) ? 'disabled' : ''; ?>
                               onkeyup="fsCustomLiveEquiv(this)"
                               onkeypress="return fsIsNumber(event)">
                        <small class="text-muted fs-equiv d-block" style="font-size:11px;line-height:1.2;"></small>
                    </td>
                    <td>
                        <?php if ($editable): ?>
                            <button type="button" class="btn btn-sm btn-success fs-save"
                                    onclick="fsSaveItem(<?php echo $order_id; ?>, <?php echo $iid; ?>, this)">
                                <i class="mdi mdi-check"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    // Change log for this package (financial-sheet edits only). Filter on the
    // indexed order_track first so this stays fast even when there are no matches,
    // then keep order_id for precision (prefix+no can collide across orders).
    $db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', $order_id);
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
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $hist = $db->cdp_registros();
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
    exit;
}

// ----------------------------------------------------------------------------
// PACKAGES for a consolidation (HTML)
// ----------------------------------------------------------------------------
if ($action === 'packages') {
    $consolidate_id = (int) ($_REQUEST['consolidate_id'] ?? 0);

    // Map each detail row to EXACTLY ONE order. (order_prefix, order_no) is not
    // unique in cdb_add_order, so a plain join multiplies rows. Newer detail rows
    // carry the numeric order_id; older ones store the tracking string in order_id
    // and only resolve by prefix+no. The subquery prefers the exact order_id match,
    // else the lowest matching order_id — one package per member, no duplicates.
    $db->cdp_query("SELECT a.order_id AS oid, d.order_prefix, d.order_no,
                           d.weight AS detail_weight, a.total_order
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_id = (
                        SELECT a2.order_id FROM cdb_add_order a2
                        WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
                        ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
                        LIMIT 1)
                    WHERE d.consolidate_id = :cid
                    ORDER BY d.detail_id ASC");
    $db->bind(':cid', $consolidate_id);
    $db->cdp_execute();
    $packages = $db->cdp_registros();

    if (!$packages) {
        echo '<div class="text-muted p-2">No packages in this consolidation.</div>';
        exit;
    }
    foreach ($packages as $p):
        $oid = (int) $p->oid;
        $pkgNo = htmlspecialchars(($p->order_prefix ?? '') . ($p->order_no ?? ''));
        ?>
        <div class="card mb-1 fs-pkg-card">
            <div class="card-header fs-pkg-header p-2"
                 onclick="fsTogglePackage(this, <?php echo $oid; ?>)">
                <span class="fs-level-chip fs-chip-pkg">PACKAGE</span>
                <i class="mdi mdi-package-variant-closed"></i>
                <b><?php echo $pkgNo; ?></b>
                <span class="text-muted ml-2" title="Package weight">
                    <i class="mdi mdi-weight-kilogram"></i> <?php echo round((float) $p->detail_weight, 2); ?>
                </span>
                <span class="float-right">
                    <span class="fs-money fs-pkg-total" data-usd="<?php echo (float) $p->total_order; ?>">$<?php echo number_format((float) $p->total_order, 2); ?></span>
                    <i class="mdi mdi-chevron-down fs-pkg-caret ml-2"></i>
                </span>
            </div>
            <div class="fs-pkg-body" data-oid="<?php echo $oid; ?>" style="display:none;">
                <div class="card-body p-2 fs-items" data-loaded="0">
                    <div class="text-muted small">Loading items…</div>
                </div>
            </div>
        </div>
    <?php endforeach;
    exit;
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

// Consolidation weight is NOT the (always-0) stored cdb_consolidate.total_weight.
// It is the sum of the member packages' weights, read straight from each detail
// row (cdb_consolidate_detail.weight). We deliberately do NOT join to
// cdb_add_order here: (order_prefix, order_no) is NOT unique in cdb_add_order, so
// that join multiplies rows (e.g. consol 29: 88 members -> 434 joined rows) and
// badly inflates the total. The money total is summed from the de-duplicated
// package list when a consolidation is expanded (see the 'packages' action + JS).
$sql = "SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.status_courier,
               c.is_dangerous_good,
               (SELECT COALESCE(SUM(d.weight),0)
                  FROM cdb_consolidate_detail d
                  WHERE d.consolidate_id = c.consolidate_id) AS calc_weight
        FROM cdb_consolidate c $sqlWhere ORDER BY c.consolidate_id DESC LIMIT 200";
$db->cdp_query($sql);
if ($search !== '') {
    $db->bind(':q', '%' . $search . '%');
}
$db->cdp_execute();
$consolidations = $db->cdp_registros();

// Dangerous-goods badge style (cached). Falls back to the marker's standard orange.
$dgStyle = function_exists('cdp_getDangerousGoodsStyle') ? cdp_getDangerousGoodsStyle() : null;
$dgColor = ($dgStyle && !empty($dgStyle->color)) ? $dgStyle->color : '#ff6d00';

if (!$consolidations) {
    echo '<div id="report-has-data" data-has="0"></div>';
    echo '<div class="text-center mt-3"><img src="assets/images/alert/ohh_shipment.png" width="140" alt=""><p class="mt-2">No consolidations found.</p></div>';
    return;
}
?>
<div id="report-has-data" data-has="1"></div>
<div class="accordion" id="fsAccordion">
    <?php foreach ($consolidations as $c):
        $cid = (int) $c->consolidate_id;
        $cNo = htmlspecialchars(($c->c_prefix ?? '') . ($c->c_no ?? ''));
        ?>
        <div class="card mb-2 fs-consol-card">
            <div class="card-header fs-consol-header" onclick="fsToggleConsolidation(this, <?php echo $cid; ?>)">
                <i class="fas fa-boxes"></i>
                <b><?php echo $cNo; ?></b>
                <span class="ml-3 fs-dim"><i class="mdi mdi-calendar-blank"></i> <?php echo htmlspecialchars((string) $c->c_date); ?></span>
                <span class="ml-3 fs-dim" title="Sum of package weights">
                    <i class="mdi mdi-weight-kilogram"></i> <?php echo round((float) $c->calc_weight, 2); ?>
                </span>
                <?php if ((int) $c->is_dangerous_good === 1): ?>
                    <span class="fs-dg-badge" style="background:<?php echo htmlspecialchars($dgColor); ?>;"
                          title="This consolidation contains dangerous goods">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                <?php endif; ?>
                <span class="float-right">
                    <button type="button" class="btn btn-sm btn-light"
                            onclick="event.stopPropagation(); fsExportConsolidation(<?php echo $cid; ?>);"
                            title="Export this consolidation to PDF">
                        <i class="fa fa-file-pdf text-danger"></i> PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-light"
                            onclick="event.stopPropagation(); fsExportConsolidationExcel(<?php echo $cid; ?>);"
                            title="Export this consolidation to Excel">
                        <i class="fa fa-file-excel text-success"></i> Excel
                    </button>
                    <i class="mdi mdi-chevron-down fs-consol-caret ml-2"></i>
                </span>
            </div>
            <div class="fs-consol-body" data-cid="<?php echo $cid; ?>" style="display:none;">
                <div class="card-body p-2 fs-packages" data-loaded="0">
                    <div class="text-muted small">Loading packages…</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>