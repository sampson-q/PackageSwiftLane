<?php
// ============================================================================
// Warehouse Delivery — lists ONLY packages finance has cleared for delivery
// (cdb_add_order.fs_cleared_for_delivery = 1) that are here in Ghana and not
// yet delivered/picked-up. Grouped by customer (per-user deliveries). Packages
// that aren't cleared never appear here, so the warehouse can't deliver them.
//   action=list -> customer-grouped cards (HTML)
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once("../../helpers/querys.php");
require_login();
require_permission('deliver_shipment');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$user = new User;
$userData = $user->cdp_getUserData();

$search = trim((string) ($_REQUEST['search'] ?? ''));
$status = (int) ($_REQUEST['status_courier'] ?? 0);

// Cleared for delivery AND physically here in the Ghana warehouse:
//   1 = Pending_Collection, 4 = In_Warehouse, 32 = Ready for PickUp,
//   33 = Sorting at Accra Office. (Excludes Delivered/Picked-up and any
//   pre-arrival statuses so only genuinely deliverable packages show.)
$where = " WHERE a.fs_cleared_for_delivery = 1 AND a.status_courier IN (1, 4, 32, 33) ";
$bind  = [];

if (isset($userData->userlevel) && (int) $userData->userlevel === 1) {
    $where .= " AND a.sender_id = :own ";
    $bind[':own'] = (int) $_SESSION['userid'];
}
if ($status > 0) {
    $where .= " AND a.status_courier = :st ";
    $bind[':st'] = $status;
}
if ($search !== '') {
    $where .= " AND (CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q
                     OR u.locker LIKE :q
                     OR CONCAT(COALESCE(a.order_prefix,''),COALESCE(a.order_no,'')) LIKE :q) ";
    $bind[':q'] = '%' . $search . '%';
}

$db->cdp_query("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.status_courier,
                       a.fs_cleared_at,
                       b.mod_style AS status_name, b.color AS status_color,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', a.sender_id)) AS customer,
                       u.locker AS locker
                FROM cdb_add_order a
                LEFT JOIN cdb_styles b ON b.id = a.status_courier
                LEFT JOIN cdb_users u ON u.id = a.sender_id
                $where
                ORDER BY customer ASC, a.order_id DESC
                LIMIT 500");
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$rows = $db->cdp_registros() ?: [];

// Group by customer.
$groups = [];
foreach ($rows as $r) {
    $sid = (int) $r->sender_id;
    if (!isset($groups[$sid])) {
        $label = trim((string) $r->customer);
        if ($r->locker) { $label .= ' (' . $r->locker . ')'; }
        $groups[$sid] = ['label' => $label, 'pkgs' => []];
    }
    $groups[$sid]['pkgs'][] = $r;
}

$totalPkgs = count($rows);
$totalCust = count($groups);
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="text-muted"><b><?php echo $totalPkgs; ?></b> package(s) cleared for delivery &middot; <b><?php echo $totalCust; ?></b> customer(s)</div>
    <button type="button" class="btn btn-success btn-sm" onclick="cdpWhDeliverSelected();">
        <i class="mdi mdi-truck-check"></i> Deliver Selected
    </button>
</div>

<?php if (!$rows) { ?>
    <div class="text-center text-muted py-5">
        <img src="assets/images/alert/ohh_shipment.png" width="130"><br>
        No packages are cleared for delivery right now.
    </div>
<?php } else {
    foreach ($groups as $sid => $g) {
        $nos = array_map(function ($p) { return (string) $p->order_no; }, $g['pkgs']);
        $nosJson = htmlspecialchars(json_encode(array_values($nos)), ENT_QUOTES);
    ?>
    <div class="card mb-2 wh-cust-card">
        <div class="card-header d-flex justify-content-between align-items-center p-2">
            <div>
                <input type="checkbox" class="wh-cust-all" onclick="cdpWhToggleCustomer(this);">
                <b class="ml-1"><?php echo htmlspecialchars($g['label']); ?></b>
                <span class="badge badge-light ml-1"><?php echo count($g['pkgs']); ?> pkg(s)</span>
            </div>
            <button type="button" class="btn btn-outline-success btn-sm" data-nos='<?php echo $nosJson; ?>' onclick="cdpWhDeliverCustomer(this);">
                <i class="mdi mdi-truck-check"></i> Deliver All
            </button>
        </div>
        <div class="card-body p-2">
            <table class="table table-sm mb-0">
                <tbody>
                <?php foreach ($g['pkgs'] as $p):
                    $tracking = ($p->order_prefix ?? '') . $p->order_no;
                    $color = $p->status_color ?: '#6c757d';
                ?>
                    <tr>
                        <td style="width:28px;"><input type="checkbox" class="wh-pkg" value="<?php echo htmlspecialchars((string) $p->order_no); ?>"></td>
                        <td><b style="font-family:SFMono-Regular,Consolas,monospace;"><?php echo htmlspecialchars($tracking); ?></b></td>
                        <td><span class="badge" style="background:<?php echo htmlspecialchars($color); ?>;color:#fff;"><?php echo htmlspecialchars(str_replace('_', ' ', (string) $p->status_name)); ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-outline-success btn-xs" data-no="<?php echo htmlspecialchars((string) $p->order_no); ?>" onclick="cdpWhDeliverOne(this);">Deliver</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php }
} ?>
