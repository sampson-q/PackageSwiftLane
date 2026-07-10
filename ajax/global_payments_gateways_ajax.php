<?php
// ============================================================================
// Global Payments (gateways) — now driven by the Financial Sheet ledger
// (cdb_fs_payments), showing the ONLINE payments (Paystack / Hubtel / PayPal)
// so the numbers match the Financial Overview + Transactions dashboard. The
// legacy cdb_payments_gateway table is no longer read here.
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../loader.php");
require_once(__DIR__ . '/../helpers/ajax_guard.php');
require_once(__DIR__ . '/../helpers/querys.php');
require_login();
require_permission('view_global_payments');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search  = trim((string) ($_REQUEST['search'] ?? ''));
$gateway = strtolower(trim((string) ($_REQUEST['gateway'] ?? '')));

$where = " WHERE p.mode <> 'cash' ";  // online payments only
$bind  = [];

if (isset($userData->userlevel) && (int) $userData->userlevel === 1) {
    $where .= " AND p.sender_id = :own ";
    $bind[':own'] = (int) $_SESSION['userid'];
}
if (in_array($gateway, ['paystack', 'hubtel', 'paypal'], true)) {
    $where .= " AND p.mode = :mode ";
    $bind[':mode'] = $gateway;
}
if ($search !== '') {
    $where .= " AND (p.reference LIKE :q
                     OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q
                     OR u.locker LIKE :q) ";
    $bind[':q'] = '%' . $search . '%';
}

$from = " FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.sender_id ";

$page = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000
    : (in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int) $_REQUEST['per_page'] : 25);

$db->cdp_query("SELECT COUNT(*) AS n $from $where");
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$cnt = $db->cdp_registro();
$numrows = $cnt ? (int) $cnt->n : 0;
$total_pages = (int) ceil($numrows / $per_page);
$offset = ($page - 1) * $per_page;

$db->cdp_query("SELECT p.*,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', p.sender_id)) AS customer,
                       u.locker AS locker,
                       c.c_prefix, c.c_no
                $from
                LEFT JOIN cdb_consolidate c ON c.consolidate_id = p.consolidate_id
                $where
                ORDER BY p.id DESC
                LIMIT $offset, $per_page");
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$data = $db->cdp_registros();

function cdp_gwStatusLabel($status)
{
    $s = strtolower((string) $status);
    if ($s === 'success') return ['Confirmed', 'label-success'];
    if ($s === 'pending') return ['Pending', 'label-warning'];
    if ($s === 'failed') return ['Failed', 'label-danger'];
    if ($s === 'unconfigured') return ['Unconfigured', 'label-default'];
    return [$status ?: '—', 'label-default'];
}
?>
<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <th><b><?php echo $lang['ddate'] ?? 'Date'; ?></b></th>
                <th><b><?php echo $lang['modal-text21'] ?? 'Customer'; ?></b></th>
                <th class="text-center"><b>Consolidation</b></th>
                <th class="text-center"><b><?php echo $lang['leftorder41'] ?? 'Gateway'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['leftorder42'] ?? 'Reference'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['leftorder43'] ?? 'Amount'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['lstatusshipment'] ?? 'Status'; ?></b></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$data) { ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No gateway payments found.</td></tr>
        <?php } else {
            foreach ($data as $row) {
                list($stLabel, $stClass) = cdp_gwStatusLabel($row->gateway_status);
                $cno = trim((string) $row->c_prefix . (string) $row->c_no);
        ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', strtotime((string) $row->recorded_at)); ?></td>
                <td><?php echo htmlspecialchars($row->customer); ?><?php if ($row->locker) { ?><br><small class="text-muted"><?php echo htmlspecialchars($row->locker); ?></small><?php } ?></td>
                <td class="text-center"><small><?php echo $cno !== '' ? htmlspecialchars($cno) : '<span class="text-muted">—</span>'; ?></small></td>
                <td class="text-center"><?php echo htmlspecialchars(ucfirst((string) $row->mode)); ?></td>
                <td class="text-center"><small><?php echo $row->reference ? htmlspecialchars((string) $row->reference) : '<span class="text-muted">—</span>'; ?></small></td>
                <td class="text-center"><b>&#8373;<?php echo cdb_money_format($row->amount_ghs); ?></b></td>
                <td class="text-center"><span class="label label-large <?php echo $stClass; ?>"><?php echo htmlspecialchars($stLabel); ?></span></td>
            </tr>
        <?php } } ?>
        </tbody>
    </table>
    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, 4, $lang, 'global_payments_gateways'); ?>
    </div>
</div>
