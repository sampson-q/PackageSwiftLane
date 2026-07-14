<?php
// *************************************************************************
// * System Logs — unified, categorized activity feed.                     *
// *                                                                        *
// * Aggregates the system's several audit/log tables into one normalized  *
// * timeline. Per-category queries are indexed and independent; the "All"  *
// * view merges the most-recent slice of each source in PHP (no heavy      *
// * cross-table SQL UNION). A single category paginates its full history.  *
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

// Category registry: label + a builder returning [sql, countSql, bind[], format(row)].
$like = '%' . $search . '%';

$categories = [
    'package' => [
        'label' => 'Package History',
        'icon'  => 'mdi:package-variant-closed',
        'time'  => 'h.date_history',
        'from'  => "FROM cdb_order_user_history h LEFT JOIN cdb_users u ON u.id = h.user_id",
        'order' => "h.history_id DESC",
        'search'=> "(h.action LIKE :s OR h.order_track LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "h.date_history AS log_when, " . cdp_logActor('u', 'h.user_id') . " AS actor, h.action AS descr, h.order_track AS reference, h.order_id AS order_id",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr]; },
    ],
    'payments' => [
        'label' => 'Payments',
        'icon'  => 'mdi:cash-multiple',
        'time'  => 'p.recorded_at',
        'from'  => "FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.recorded_by",
        'order' => "p.id DESC",
        'search'=> "(p.mode LIKE :s OR p.reference LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "p.recorded_at AS log_when, " . cdp_logActor('u', 'p.recorded_by') . " AS actor, p.amount_ghs AS amount, p.mode AS mode, p.reference AS reference, p.consolidate_id AS consolidate_id",
        'fmt'   => function ($r) use ($sym) {
            $mode = $r->mode ? ucfirst((string) $r->mode) : 'Payment';
            $ref  = $r->reference ? ' · ref ' . $r->reference : '';
            return ['action' => $mode . ' payment of ' . $sym . number_format((float) $r->amount, 2) . $ref, 'reference' => 'Consolidation #' . (int) $r->consolidate_id];
        },
    ],
    'discounts' => [
        'label' => 'Discounts',
        'icon'  => 'mdi:tag-minus',
        'time'  => 'd.applied_at',
        'from'  => "FROM cdb_fs_discounts d LEFT JOIN cdb_users u ON u.id = d.applied_by",
        'order' => "d.id DESC",
        'search'=> "(d.reason LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "d.applied_at AS log_when, " . cdp_logActor('u', 'd.applied_by') . " AS actor, d.amount_ghs AS amount, d.disc_type AS dtype, d.reason AS reason, d.order_id AS order_id",
        'fmt'   => function ($r) use ($sym) {
            $reason = $r->reason ? ' — ' . $r->reason : '';
            return ['action' => 'Discount ' . $sym . number_format((float) $r->amount, 2) . ' (' . ($r->dtype ?: 'amount') . ')' . $reason];
        },
    ],
    'notes' => [
        'label' => 'Billing Notes',
        'icon'  => 'mdi:note-text-outline',
        'time'  => 'n.created_at',
        'from'  => "FROM cdb_consolidate_billing_notes n LEFT JOIN cdb_users u ON u.id = n.created_by",
        'order' => "n.id DESC",
        'search'=> "(n.note LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "n.created_at AS log_when, " . cdp_logActor('u', 'n.created_by') . " AS actor, n.note AS descr, n.consolidate_id AS consolidate_id",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr, 'reference' => 'Consolidation #' . (int) $r->consolidate_id]; },
    ],
    'aging' => [
        'label' => 'Pickup Aging',
        'icon'  => 'mdi:timer-sand',
        'time'  => 'a.ready_at',
        'from'  => "FROM cdb_package_pickup_aging a LEFT JOIN cdb_users u ON u.id = a.sender_id",
        'order' => "a.ready_at DESC",
        'search'=> "(a.order_track LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "a.ready_at AS log_when, " . cdp_logActor('u', 'a.sender_id') . " AS actor, a.order_track AS reference, a.ready_at AS ready_at, a.notified_at AS notified_at, a.not_picked_at AS not_picked_at, a.auction_at AS auction_at, a.order_id AS order_id",
        'fmt'   => function ($r) {
            $state = 'Ready for pickup';
            if (!empty($r->auction_at)) { $state = 'Sent to auction'; }
            elseif (!empty($r->not_picked_at)) { $state = 'Marked not picked up'; }
            elseif (!empty($r->notified_at)) { $state = 'Pending collection (notified)'; }
            return ['action' => 'Pickup — ' . $state];
        },
    ],
    'notifications' => [
        'label' => 'Notifications',
        'icon'  => 'mdi:bell-outline',
        'time'  => 't.notification_date',
        'from'  => "FROM cdb_notifications t LEFT JOIN cdb_users u ON u.id = t.user_id",
        'order' => "t.notification_id DESC",
        'search'=> "(t.notification_description LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "t.notification_date AS log_when, " . cdp_logActor('u', 't.user_id') . " AS actor, t.notification_description AS descr, t.order_id AS order_id",
        'fmt'   => function ($r) { return ['action' => (string) $r->descr]; },
    ],
    'charges' => [
        'label' => 'Legacy Charges',
        'icon'  => 'mdi:receipt-text-outline',
        'time'  => 'c.charge_date',
        'from'  => "FROM cdb_charges_order c LEFT JOIN cdb_users u ON u.id = c.user_id",
        'order' => "c.id_charge DESC",
        'search'=> "(c.number_reference LIKE :s OR c.note LIKE :s OR u.username LIKE :s OR CONCAT(u.fname,' ',u.lname) LIKE :s)",
        'select'=> "c.charge_date AS log_when, " . cdp_logActor('u', 'c.user_id') . " AS actor, c.total AS amount, c.number_reference AS reference, c.note AS note, c.order_id AS order_id",
        'fmt'   => function ($r) use ($sym) {
            $note = $r->note ? ' — ' . $r->note : '';
            $ref  = $r->reference ? ' · ref ' . $r->reference : '';
            return ['action' => 'Charge ' . $sym . number_format((float) $r->amount, 2) . $ref . $note];
        },
    ],
];

// Build a normalized row list for one category (with optional LIMIT/OFFSET).
function cdp_fetchCategory($db, $key, $conf, $search, $like, $limit, $offset)
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
            'actor'     => $r->actor ?: '—',
            'action'    => $fmt['action'] ?? '',
            'reference' => $fmt['reference'] ?? (isset($r->reference) ? (string) $r->reference : ''),
        ];
    }
    return $out;
}

// Count rows for a single category (for accurate pagination).
function cdp_countCategory($db, $conf, $search, $like)
{
    $where = "WHERE 1 ";
    if ($search !== '') { $where .= " AND " . $conf['search']; }
    $db->cdp_query("SELECT COUNT(*) AS c " . $conf['from'] . " " . $where);
    if ($search !== '') { $db->bind(':s', $like); }
    $db->cdp_execute();
    $row = $db->cdp_registro();
    return $row ? (int) $row->c : 0;
}

$rows = [];
$total = 0;
$paged = false;

if ($cat !== 'all' && isset($categories[$cat])) {
    // Single category: full history, real pagination.
    $paged  = true;
    $total  = cdp_countCategory($db, $categories[$cat], $search, $like);
    $offset = ($page - 1) * $per_page;
    $rows   = cdp_fetchCategory($db, $cat, $categories[$cat], $search, $like, $per_page, $offset);
} else {
    // All: merge the most-recent slice of every source, sort, take per_page.
    $cat = 'all';
    $sliceEach = 150;
    foreach ($categories as $key => $conf) {
        try {
            $rows = array_merge($rows, cdp_fetchCategory($db, $key, $conf, $search, $like, $sliceEach, 0));
        } catch (Throwable $e) {
            // A missing/renamed source table shouldn't break the whole feed.
        }
    }
    usort($rows, function ($a, $b) {
        return strcmp((string) $b['when'], (string) $a['when']);
    });
    $rows = array_slice($rows, 0, $per_page);
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
?>
<table class="table table-hover table-striped mb-0" style="font-size:13px;">
    <thead>
        <tr>
            <th style="width:150px;">When</th>
            <th style="width:150px;">Category</th>
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
    <div class="mt-3"><small class="text-muted">Showing the most recent activity across all sources. Pick a category above for full, paginated history.</small></div>
<?php endif; ?>
