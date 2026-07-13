<?php
// ============================================================================
// Accounts Receivable — now driven by the Financial Sheet ledger. One row per
// (consolidation, customer) billing record: billed / paid / discount /
// outstanding, so the numbers match the Financial Overview + Transactions.
// The legacy per-order cdb_charges_order flow is no longer read here; payments
// are recorded on the Financial Sheet. "View" opens the consolidation there.
// ============================================================================

if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_receivable_accounts');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$db   = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$range       = cdp_sanitize($_REQUEST['range'] ?? '');
$search      = trim((string) ($_REQUEST['search'] ?? ''));
$customer_id = (int) ($_REQUEST['customer_id'] ?? 0);

$where = " WHERE 1=1 ";
$bind  = [];

if (isset($userData->userlevel) && (int) $userData->userlevel === 1) {
    $where .= " AND b.sender_id = :own ";
    $bind[':own'] = (int) $_SESSION['userid'];
}
if ($customer_id > 0) {
    $where .= " AND b.sender_id = :cust ";
    $bind[':cust'] = $customer_id;
}
if ($search !== '') {
    $where .= " AND (CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q
                     OR u.locker LIKE :q
                     OR CONCAT(COALESCE(c.c_prefix,''),COALESCE(c.c_no,'')) LIKE :q) ";
    $bind[':q'] = '%' . $search . '%';
}
if ($range !== '') {
    $parts = explode(' - ', $range);
    if (count($parts) === 2) {
        $ini = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $parts[0])));
        $fin = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $parts[1])));
        $where .= " AND b.billed_at BETWEEN :ini AND :fin ";
        $bind[':ini'] = $ini;
        $bind[':fin'] = $fin;
    }
}

$from = " FROM cdb_consolidate_customer_billing b
          LEFT JOIN cdb_users u ON u.id = b.sender_id
          LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id ";

$page = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000
    : (in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int) $_REQUEST['per_page'] : 25);

// Summary over the whole filtered set (GHS).
$db->cdp_query("SELECT COUNT(*) n,
                       COALESCE(SUM(b.amount_ghs),0) billed,
                       COALESCE(SUM(b.paid_ghs),0) paid,
                       COALESCE(SUM(GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))),0) outstanding
                $from $where");
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$sum = $db->cdp_registro();

$numrows = $sum ? (int) $sum->n : 0;
$total_pages = (int) ceil($numrows / $per_page);
$offset = ($page - 1) * $per_page;

$db->cdp_query("SELECT b.consolidate_id, b.sender_id, b.amount_ghs, b.paid_ghs, b.discount_ghs, b.billed_at,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', b.sender_id)) AS customer,
                       u.locker AS locker,
                       CONCAT(COALESCE(c.c_prefix,''),COALESCE(c.c_no,'')) AS cno
                $from $where
                ORDER BY b.billed_at DESC
                LIMIT $offset, $per_page");
foreach ($bind as $k => $v) { $db->bind($k, $v); }
$db->cdp_execute();
$data = $db->cdp_registros();
?>
<div class="row">
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Billed Customers</h6><h4 class="mb-0"><?php echo (int) $numrows; ?></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Billed</h6><h4 class="mb-0">&#8373;<?php echo number_format((float)($sum ? $sum->billed : 0), 2); ?></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Paid</h6><h4 class="mb-0 text-success">&#8373;<?php echo number_format((float)($sum ? $sum->paid : 0), 2); ?></h4>
    </div></div></div>
    <div class="col-md-3 col-6"><div class="card"><div class="card-body py-3">
        <h6 class="text-muted mb-1">Outstanding</h6><h4 class="mb-0 text-danger">&#8373;<?php echo number_format((float)($sum ? $sum->outstanding : 0), 2); ?></h4>
    </div></div></div>
</div>

<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <th><b><?php echo $lang['left498'] ?? 'Customer'; ?></b></th>
                <th class="text-center"><b>Consolidation</b></th>
                <th class="text-center"><b><?php echo $lang['ddate'] ?? 'Billed'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['modal-text20'] ?? 'Billed'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['modal-text13'] ?? 'Paid'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['modal-text16'] ?? 'Outstanding'; ?></b></th>
                <th class="text-center"><b><?php echo $lang['lstatusinvoice'] ?? 'Status'; ?></b></th>
                <th class="text-center"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$data) { ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No billed customers found.</td></tr>
        <?php } else {
            foreach ($data as $row) {
                $billed = (float) $row->amount_ghs;
                $paid   = (float) $row->paid_ghs;
                $disc   = (float) $row->discount_ghs;
                $out    = max(0, $billed - $disc - $paid);
                if ($out <= 0.001)      { $stLabel = $lang['invoice_paid'] ?? 'Paid';       $stClass = 'label-success'; }
                elseif ($paid > 0)      { $stLabel = 'Partial';                              $stClass = 'label-warning'; }
                else                    { $stLabel = $lang['invoice_due'] ?? 'Outstanding';  $stClass = 'label-danger'; }
        ?>
            <tr>
                <td><b><?php echo htmlspecialchars($row->customer); ?></b><?php if ($row->locker) { ?><br><small class="text-muted"><?php echo htmlspecialchars($row->locker); ?></small><?php } ?></td>
                <td class="text-center"><?php echo $row->cno !== '' ? htmlspecialchars($row->cno) : '<span class="text-muted">—</span>'; ?></td>
                <td class="text-center"><small><?php echo $row->billed_at ? date('Y-m-d', strtotime((string) $row->billed_at)) : '—'; ?></small></td>
                <td class="text-center">&#8373;<?php echo number_format((float)($billed), 2); ?><?php if ($disc > 0) { ?><br><small class="text-muted">− &#8373;<?php echo number_format((float)($disc), 2); ?> disc</small><?php } ?></td>
                <td class="text-center text-success">&#8373;<?php echo number_format((float)($paid), 2); ?></td>
                <td class="text-center text-danger"><b>&#8373;<?php echo number_format((float)($out), 2); ?></b></td>
                <td class="text-center"><span class="label label-large <?php echo $stClass; ?>"><?php echo htmlspecialchars($stLabel); ?></span></td>
                <td class="text-center">
                    <a class="btn btn-outline-dark btn-sm" href="financial_sheet_consolidation.php?id=<?php echo (int) $row->consolidate_id; ?>" title="Open in Financial Sheet">
                        <i class="fa fa-search"></i>
                    </a>
                </td>
            </tr>
        <?php } } ?>
        </tbody>
    </table>
    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, 4, $lang, 'accounts_receivable'); ?>
    </div>
</div>
