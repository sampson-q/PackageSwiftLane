<?php
// *************************************************************************
// * System Logs — unified, categorized, SYSTEM-WIDE activity feed.        *
// *                                                                        *
// * Normalizes every audit/activity source in the DB into one timeline,    *
// * grouped into tabs (Packages, Finance, Pickups, Accounts & Access,      *
// * Authentication, Notifications). Per-source queries are indexed; a      *
// * single-source tab paginates fully, a multi-source tab (and "All")      *
// * merges the recent slice of each source in PHP — no heavy cross-table   *
// * SQL UNION, and a missing source table can't break the feed.            *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_system_logs');

$db   = new Conexion;
$core = new Core;

$cat      = isset($_REQUEST['cat']) ? preg_replace('/[^a-z_]/', '', (string) $_REQUEST['cat']) : 'all';
$search   = isset($_REQUEST['search']) ? trim((string) $_REQUEST['search']) : '';
$page     = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int) $_REQUEST['per_page'] : 50;

// Actor display name from a joined cdb_users alias.
function cdp_logActor($alias, $idCol)
{
    return "COALESCE(NULLIF(TRIM(CONCAT($alias.fname,' ',$alias.lname)),''), $alias.username, CONCAT('User ', $idCol))";
}

$sym = '&#8373;'; // GH cedi
$A   = 'cdp_logActor';

// Tab groups → the label shown and the order they appear.
$groups = [
    'all'           => 'All',
    'packages'      => 'Packages',
    'finance'       => 'Finance',
    'pickups'       => 'Pickups',
    'accounts'      => 'Accounts & Access',
    'auth'          => 'Authentication',
    'notifications' => 'Notifications',
];

$like = '%' . $search . '%';

// Each source: which group it belongs to + how to query and format it.
$sources = [
    // ---------------- Packages ----------------
    'package' => [
        'group' => 'packages', 'label' => 'Package History', 'icon' => 'mdi:package-variant-closed',
        'from'  => "FROM cdb_order_user_history h LEFT JOIN cdb_users u ON u.id = h.user_id",
        'order' => "h.history_id DESC",
        'search'=> "(h.action LIKE :s OR h.order_track LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "h.date_history AS log_when, " . $A('u', 'h.user_id') . " AS actor, h.action AS descr, h.order_track AS reference",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr]; },
    ],
    'prealerts' => [
        'group' => 'packages', 'label' => 'Pre-Alerts', 'icon' => 'mdi:package-up',
        'from'  => "FROM cdb_pre_alert pa LEFT JOIN cdb_users u ON u.id = pa.customer_id",
        'order' => "pa.pre_alert_id DESC",
        'search'=> "(pa.tracking LIKE :s OR pa.provider_shop LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "pa.prealert_date AS log_when, " . $A('u', 'pa.customer_id') . " AS actor, pa.tracking AS reference, pa.provider_shop AS shop",
        'fmt'   => function ($r) { return ['action' => 'Pre-alert raised' . ($r->shop ? ' from ' . $r->shop : '')]; },
    ],
    'files' => [
        'group' => 'packages', 'label' => 'File Uploads', 'icon' => 'mdi:paperclip',
        'from'  => "FROM cdb_order_files f",
        'order' => "f.id DESC",
        'search'=> "(f.name LIKE :s OR f.file_type LIKE :s)",
        'select'=> "f.date_file AS log_when, NULL AS actor, f.name AS fname, f.file_type AS ftype, f.order_id AS order_id, f.is_consolidate AS is_consol",
        'fmt'   => function ($r) {
            $ref = ((int) $r->is_consol === 1 ? 'Consolidation #' : 'Order #') . (int) $r->order_id;
            return ['action' => 'File uploaded: ' . ($r->fname ?: 'file') . ($r->ftype ? ' (' . $r->ftype . ')' : ''), 'reference' => $ref, 'actor' => 'System'];
        },
    ],
    // ---------------- Finance ----------------
    'payments' => [
        'group' => 'finance', 'label' => 'Payments', 'icon' => 'mdi:cash-multiple',
        'from'  => "FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.recorded_by",
        'order' => "p.id DESC",
        'search'=> "(p.mode LIKE :s OR p.reference LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "p.recorded_at AS log_when, " . $A('u', 'p.recorded_by') . " AS actor, p.amount_ghs AS amount, p.mode AS mode, p.reference AS reference, p.consolidate_id AS consolidate_id",
        'fmt'   => function ($r) use ($sym) {
            $mode = $r->mode ? ucfirst((string) $r->mode) : 'Payment';
            $ref  = $r->reference ? ' · ref ' . $r->reference : '';
            return ['action' => $mode . ' payment of ' . $sym . number_format((float) $r->amount, 2) . $ref, 'reference' => 'Consolidation #' . (int) $r->consolidate_id];
        },
    ],
    'discounts' => [
        'group' => 'finance', 'label' => 'Discounts', 'icon' => 'mdi:tag-minus',
        'from'  => "FROM cdb_fs_discounts d LEFT JOIN cdb_users u ON u.id = d.applied_by",
        'order' => "d.id DESC",
        'search'=> "(d.reason LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "d.applied_at AS log_when, " . $A('u', 'd.applied_by') . " AS actor, d.amount_ghs AS amount, d.disc_type AS dtype, d.reason AS reason",
        'fmt'   => function ($r) use ($sym) {
            $reason = $r->reason ? ' — ' . $r->reason : '';
            return ['action' => 'Discount ' . $sym . number_format((float) $r->amount, 2) . ' (' . ($r->dtype ?: 'amount') . ')' . $reason];
        },
    ],
    'notes' => [
        'group' => 'finance', 'label' => 'Billing Notes', 'icon' => 'mdi:note-text-outline',
        'from'  => "FROM cdb_consolidate_billing_notes n LEFT JOIN cdb_users u ON u.id = n.created_by",
        'order' => "n.id DESC",
        'search'=> "(n.note LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "n.created_at AS log_when, " . $A('u', 'n.created_by') . " AS actor, n.note AS descr, n.consolidate_id AS consolidate_id",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr, 'reference' => 'Consolidation #' . (int) $r->consolidate_id]; },
    ],
    'charges' => [
        'group' => 'finance', 'label' => 'Legacy Charges', 'icon' => 'mdi:receipt-text-outline',
        'from'  => "FROM cdb_charges_order c LEFT JOIN cdb_users u ON u.id = c.user_id",
        'order' => "c.id_charge DESC",
        'search'=> "(c.number_reference LIKE :s OR c.note LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "c.charge_date AS log_when, " . $A('u', 'c.user_id') . " AS actor, c.total AS amount, c.number_reference AS reference, c.note AS note",
        'fmt'   => function ($r) use ($sym) {
            $note = $r->note ? ' — ' . $r->note : '';
            $ref  = $r->reference ? ' · ref ' . $r->reference : '';
            return ['action' => 'Charge ' . $sym . number_format((float) $r->amount, 2) . $ref . $note];
        },
    ],
    // ---------------- Pickups ----------------
    'aging' => [
        'group' => 'pickups', 'label' => 'Pickup Aging', 'icon' => 'mdi:timer-sand',
        'from'  => "FROM cdb_package_pickup_aging a LEFT JOIN cdb_users u ON u.id = a.sender_id",
        'order' => "a.ready_at DESC",
        'search'=> "(a.order_track LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "a.ready_at AS log_when, " . $A('u', 'a.sender_id') . " AS actor, a.order_track AS reference, a.notified_at AS notified_at, a.not_picked_at AS not_picked_at, a.auction_at AS auction_at",
        'fmt'   => function ($r) {
            $state = 'Ready for pickup';
            if (!empty($r->auction_at)) { $state = 'Sent to auction'; }
            elseif (!empty($r->not_picked_at)) { $state = 'Marked not picked up'; }
            elseif (!empty($r->notified_at)) { $state = 'Pending collection (notified)'; }
            return ['action' => 'Pickup — ' . $state];
        },
    ],
    // ---------------- Accounts & Access ----------------
    'accounts' => [
        'group' => 'accounts', 'label' => 'User Accounts', 'icon' => 'mdi:account-plus',
        'from'  => "FROM cdb_users u LEFT JOIN cdb_users c ON c.id = u.create_user",
        'order' => "u.id DESC",
        'search'=> "(u.username LIKE :s OR u.email LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s OR u.locker LIKE :s)",
        'select'=> "u.created AS log_when, "
                 . "CASE WHEN u.create_user > 0 THEN " . $A('c', 'u.create_user') . " ELSE 'Self-registered' END AS actor, "
                 . "CONCAT(u.fname,' ',u.lname) AS fullname, u.username AS uname, u.locker AS reference",
        'fmt'   => function ($r) {
            $name = trim((string) $r->fullname) !== '' ? $r->fullname : $r->uname;
            return ['action' => 'Account created: ' . $name . ' (' . $r->uname . ')'];
        },
    ],
    'access' => [
        'group' => 'accounts', 'label' => 'Permission Overrides', 'icon' => 'mdi:shield-key',
        'from'  => "FROM cdb_user_permission_overrides o LEFT JOIN cdb_users u ON u.id = o.user_id LEFT JOIN cdb_user_module_actions ma ON ma.id = o.module_action_id",
        'order' => "o.id DESC",
        'search'=> "(ma.action_name LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "o.created_at AS log_when, " . $A('u', 'o.user_id') . " AS actor, ma.action_name AS act, o.permitted AS permitted",
        'fmt'   => function ($r) {
            $verb = ((int) $r->permitted === 1) ? 'granted' : 'revoked';
            return ['action' => "Permission '" . ($r->act ?: 'action') . "' " . $verb . ' (per-user override)'];
        },
    ],
    'deptmembers' => [
        'group' => 'accounts', 'label' => 'Department Members', 'icon' => 'mdi:account-group',
        'from'  => "FROM cdb_department_members dm LEFT JOIN cdb_users u ON u.id = dm.user_id LEFT JOIN cdb_departments d ON d.id = dm.department_id",
        'order' => "dm.id DESC",
        'search'=> "(d.name LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "dm.created_at AS log_when, " . $A('u', 'dm.user_id') . " AS actor, d.name AS dept",
        'fmt'   => function ($r) { return ['action' => 'Added to department: ' . ($r->dept ?: '—')]; },
    ],
    // ---------------- Authentication ----------------
    'auth' => [
        'group' => 'auth', 'label' => 'Authentication', 'icon' => 'mdi:shield-lock-outline',
        'from'  => "FROM cdb_auth_otp_challenges o LEFT JOIN cdb_users u ON u.id = o.user_id",
        'order' => "o.id DESC",
        'search'=> "(o.purpose LIKE :s OR o.channel LIKE :s OR o.status LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "o.created_at AS log_when, " . $A('u', 'o.user_id') . " AS actor, o.purpose AS purpose, o.channel AS channel, o.status AS status",
        'fmt'   => function ($r) {
            return ['action' => 'OTP ' . ($r->purpose ?: 'challenge') . ' via ' . ($r->channel ?: '—') . ' — ' . ($r->status ?: '')];
        },
    ],
    // ---------------- Notifications ----------------
    'notifications' => [
        'group' => 'notifications', 'label' => 'Notifications', 'icon' => 'mdi:bell-outline',
        'from'  => "FROM cdb_notifications t LEFT JOIN cdb_users u ON u.id = t.user_id",
        'order' => "t.notification_id DESC",
        'search'=> "(t.notification_description LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "t.notification_date AS log_when, " . $A('u', 't.user_id') . " AS actor, t.notification_description AS descr, t.order_id AS order_id",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr]; },
    ],
];

// Which sources feed the requested tab.
if ($cat !== 'all' && isset($groups[$cat])) {
    $active = array_filter($sources, function ($s) use ($cat) { return $s['group'] === $cat; });
} else {
    $cat    = 'all';
    $active = $sources;
}

// Normalized fetch for one source (with LIMIT/OFFSET).
function cdp_fetchSource($db, $conf, $search, $like, $limit, $offset)
{
    $where = "WHERE 1 ";
    if ($search !== '') { $where .= " AND " . $conf['search']; }
    $sql = "SELECT " . $conf['select'] . " " . $conf['from'] . " " . $where
         . " ORDER BY " . $conf['order'] . " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
    $db->cdp_query($sql);
    if ($search !== '') { $db->bind(':s', $like); }
    $db->cdp_execute();
    $rows = $db->cdp_registros() ?: [];
    $out = [];
    foreach ($rows as $r) {
        $fmt = $conf['fmt']($r);
        $out[] = [
            'when'      => $r->log_when,
            'category'  => $conf['label'],
            'icon'      => $conf['icon'],
            'actor'     => $fmt['actor'] ?? ($r->actor ?: '—'),
            'action'    => $fmt['action'] ?? '',
            'reference' => $fmt['reference'] ?? (isset($r->reference) ? (string) $r->reference : ''),
        ];
    }
    return $out;
}

function cdp_countSource($db, $conf, $search, $like)
{
    $where = "WHERE 1 ";
    if ($search !== '') { $where .= " AND " . $conf['search']; }
    $db->cdp_query("SELECT COUNT(*) AS c " . $conf['from'] . " " . $where);
    if ($search !== '') { $db->bind(':s', $like); }
    $db->cdp_execute();
    $row = $db->cdp_registro();
    return $row ? (int) $row->c : 0;
}

$rows  = [];
$total = 0;
$paged = false;

if (count($active) === 1) {
    // Single-source tab: full history with real pagination.
    $conf   = reset($active);
    $paged  = true;
    $total  = cdp_countSource($db, $conf, $search, $like);
    $offset = ($page - 1) * $per_page;
    $rows   = cdp_fetchSource($db, $conf, $search, $like, $per_page, $offset);
} else {
    // Multi-source tab / All: merge the recent slice of each source.
    $sliceEach = 150;
    foreach ($active as $conf) {
        try {
            $rows = array_merge($rows, cdp_fetchSource($db, $conf, $search, $like, $sliceEach, 0));
        } catch (Throwable $e) {
            // A missing/renamed source shouldn't break the whole feed.
        }
    }
    usort($rows, function ($a, $b) { return strcmp((string) $b['when'], (string) $a['when']); });
    $rows = array_slice($rows, 0, $per_page);
}
?>
<table class="table table-hover table-striped mb-0" style="font-size:13px;">
    <thead>
        <tr>
            <th style="width:150px;">When</th>
            <th style="width:160px;">Category</th>
            <th style="width:170px;">Who</th>
            <th>Activity</th>
            <th style="width:170px;">Reference</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No log entries found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td class="text-nowrap text-muted"><?php echo $r['when'] ? htmlspecialchars(date('Y-m-d H:i', strtotime((string) $r['when'])), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                <td class="text-nowrap">
                    <iconify-icon icon="<?php echo htmlspecialchars($r['icon'], ENT_QUOTES, 'UTF-8'); ?>" style="vertical-align:-2px;"></iconify-icon>
                    <?php echo htmlspecialchars($r['category'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td><?php echo htmlspecialchars($r['actor'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($r['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ($paged): ?>
    <?php $total_pages = (int) ceil($total / $per_page); ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted"><?php echo number_format($total); ?> entries · page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?></small>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="cdpLogsGo(<?php echo $page - 1; ?>)">&laquo; Prev</button>
            <button class="btn btn-outline-secondary" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="cdpLogsGo(<?php echo $page + 1; ?>)">Next &raquo;</button>
        </div>
    </div>
<?php else: ?>
    <div class="mt-3"><small class="text-muted">Showing the most recent activity across these sources. Pick a single-source tab, or search, to dig deeper.</small></div>
<?php endif; ?>
