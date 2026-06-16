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
$uname = trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''));
if ($uname === '') $uname = $userData->username ?? ('User ' . $uid);

$action = isset($_REQUEST['action']) ? cdp_sanitize($_REQUEST['action']) : 'list';

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

    echo json_encode([
        'ok'          => true,
        'total_order' => $totals ? $totals['total_order'] : null,
        'sub_total'   => $totals ? $totals['sub_total'] : null,
    ]);
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
                <th style="width:130px;">Custom Price</th>
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
                    <input type="text" class="form-control form-control-sm fs-custom"
                           value="<?php echo $useCustom ? ($custom ?: '') : ''; ?>"
                           placeholder="<?php echo $useCustom ? 'USD' : '—'; ?>"
                           <?php echo (!$useCustom || !$editable) ? 'disabled' : ''; ?>
                           onkeypress="return fsIsNumber(event)">
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
    exit;
}

// ----------------------------------------------------------------------------
// PACKAGES for a consolidation (HTML)
// ----------------------------------------------------------------------------
if ($action === 'packages') {
    $consolidate_id = (int) ($_REQUEST['consolidate_id'] ?? 0);

    // cdb_consolidate_detail links to packages by tracking (prefix + no), not by
    // the numeric order_id — its order_id column actually holds the tracking string.
    $db->cdp_query("SELECT a.order_id AS oid, a.order_prefix, a.order_no, a.total_weight
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order a ON a.order_prefix = d.order_prefix AND a.order_no = d.order_no
                    WHERE d.consolidate_id = :cid
                    ORDER BY a.order_id ASC");
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
        <div class="card mb-1 border">
            <div class="card-header p-2" style="background:#fbfcfd;cursor:pointer;"
                 onclick="fsTogglePackage(this, <?php echo $oid; ?>)">
                <i class="mdi mdi-package-variant-closed"></i>
                <b>Package <?php echo $pkgNo; ?></b>
                <span class="text-muted ml-2">Total weight: <?php echo (float) $p->total_weight; ?></span>
                <i class="mdi mdi-chevron-down float-right fs-pkg-caret"></i>
            </div>
            <div class="collapse fs-pkg-body" data-oid="<?php echo $oid; ?>">
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
    $sqlWhere = " WHERE CONCAT(COALESCE(c_prefix,''), COALESCE(c_no,'')) LIKE :q ";
}
$sql = "SELECT consolidate_id, c_prefix, c_no, c_date, total_weight, total_order, status_courier
        FROM cdb_consolidate $sqlWhere ORDER BY consolidate_id DESC LIMIT 200";
$db->cdp_query($sql);
if ($search !== '') $db->bind(':q', '%' . $search . '%');
$db->cdp_execute();
$consolidations = $db->cdp_registros();

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
    <div class="card mb-2">
        <div class="card-header fs-consol-header" onclick="fsToggleConsolidation(this, <?php echo $cid; ?>)">
            <i class="fas fa-boxes"></i>
            <b>Consolidation <?php echo $cNo; ?></b>
            <span class="ml-3 fs-dim"><?php echo htmlspecialchars((string) $c->c_date); ?></span>
            <span class="ml-3 fs-dim">Total weight: <?php echo (float) $c->total_weight; ?></span>
            <span class="float-right">
                <button type="button" class="btn btn-sm btn-light"
                        onclick="event.stopPropagation(); fsExportConsolidation(<?php echo $cid; ?>);"
                        title="Export this consolidation to PDF">
                    <i class="fa fa-file-pdf text-danger"></i> PDF
                </button>
                <i class="mdi mdi-chevron-down fs-consol-caret ml-2"></i>
            </span>
        </div>
        <div class="collapse fs-consol-body" data-cid="<?php echo $cid; ?>">
            <div class="card-body p-2 fs-packages" data-loaded="0">
                <div class="text-muted small">Loading packages…</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
