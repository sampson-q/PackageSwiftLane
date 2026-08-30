<?php
// *************************************************************************
// * Activity Logs — the filtered table, and one row's full detail.        *
// *                                                                       *
// * ?action=rows   (default) the paginated table                          *
// * ?action=detail&id=N      one entry's changes / meta / request context  *
// *                                                                       *
// * Every filter is applied through helpers/activity_log_query.php, the    *
// * same builder the statistics and the CSV export use, so the tiles and   *
// * the table can never describe different sets of rows.                   *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/activity_log_query.php');
require_once(__DIR__ . '/../../helpers/pagination.php');
require_login();
require_permission('view_activity_logs');

$db = new Conexion;

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// ---------------------------------------------------------------------------
// One entry's detail
// ---------------------------------------------------------------------------
if (($_REQUEST['action'] ?? '') === 'detail') {

    $db->cdp_query("SELECT * FROM cdb_activity_log WHERE id = :id LIMIT 1");
    $db->bind(':id', (int) ($_REQUEST['id'] ?? 0));
    $db->cdp_execute();
    $row = $db->cdp_registro();

    if (!$row) {
        echo '<div class="alert alert-warning mb-0">That log entry no longer exists.</div>';
        exit;
    }

    $changes = json_decode((string) $row->changes, true);
    $meta    = json_decode((string) $row->meta, true);
    ?>
    <div class="al-detail">
        <div class="al-detail__grid">
            <div><span class="al-k">When</span><span class="al-v"><?php echo $e(cdp_alWhen($row->created_at)); ?></span></div>
            <div><span class="al-k">Who</span><span class="al-v"><?php echo $e($row->actor_name); ?><?php echo $row->actor_username ? ' <small class="text-muted">(' . $e($row->actor_username) . ')</small>' : ''; ?></span></div>
            <div><span class="al-k">Role</span><span class="al-v"><?php echo $e($row->role_name); ?></span></div>
            <div><span class="al-k">Action</span><span class="al-v"><?php echo $e($row->action_label); ?> <code><?php echo $e($row->action); ?></code></span></div>
            <div><span class="al-k">Outcome</span><span class="al-v"><span class="<?php echo cdp_alOutcomeClass($row->outcome); ?>"><?php echo $e(ucfirst($row->outcome)); ?></span></span></div>
            <div><span class="al-k">Record</span><span class="al-v"><?php echo $e($row->entity_label ?: '—'); ?><?php echo $row->entity_type ? ' <small class="text-muted">' . $e($row->entity_type) . ($row->entity_id !== '' ? ' #' . $e($row->entity_id) : '') . '</small>' : ''; ?></span></div>
            <?php if ($row->status_name) : ?>
            <div><span class="al-k">Status Set</span><span class="al-v"><?php echo $e($row->status_name); ?></span></div>
            <?php endif; ?>
            <?php if ((int) $row->impersonated_by > 0) : ?>
            <div><span class="al-k">View As</span><span class="al-v">Performed by operator #<?php echo (int) $row->impersonated_by; ?> acting as this user</span></div>
            <?php endif; ?>
            <div><span class="al-k">IP Address</span><span class="al-v"><?php echo $e($row->ip ?: '—'); ?></span></div>
            <div><span class="al-k">Endpoint</span><span class="al-v"><code><?php echo $e($row->method . ' ' . $row->endpoint); ?></code></span></div>
            <div class="al-detail__wide"><span class="al-k">Summary</span><span class="al-v"><?php echo $e($row->summary); ?></span></div>
            <div class="al-detail__wide"><span class="al-k">Browser</span><span class="al-v"><small class="text-muted"><?php echo $e($row->user_agent ?: '—'); ?></small></span></div>
        </div>

        <?php if (is_array($changes) && $changes) : ?>
            <h6 class="al-detail__h">What Changed</h6>
            <table class="table table-sm al-changes mb-3">
                <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                <tbody>
                <?php foreach ($changes as $field => $c) : ?>
                    <tr>
                        <td class="al-changes__f"><?php echo $e(ucwords(str_replace('_', ' ', (string) $field))); ?></td>
                        <td class="al-changes__from"><?php echo $e(is_array($c) ? ($c['from'] ?? '') : ''); ?></td>
                        <td class="al-changes__to"><?php echo $e(is_array($c) ? ($c['to'] ?? '') : (string) $c); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (is_array($meta) && $meta) : ?>
            <h6 class="al-detail__h">Request Detail</h6>
            <table class="table table-sm al-changes mb-0">
                <tbody>
                <?php foreach ($meta as $k => $v) : ?>
                    <tr>
                        <td class="al-changes__f" style="width:30%;"><?php echo $e(ucwords(str_replace('_', ' ', (string) $k))); ?></td>
                        <td colspan="2"><?php echo $e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_SLASHES)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// The table
// ---------------------------------------------------------------------------
$f = cdp_alFilters();
list($where, $binds) = cdp_alWhere($f);

$page     = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100, 200], true) ? (int) $_REQUEST['per_page'] : 50;
$offset   = ($page - 1) * $per_page;

$db->cdp_query("SELECT COUNT(*) AS c FROM cdb_activity_log l WHERE $where");
cdp_alBind($db, $binds);
$db->cdp_execute();
$cnt = $db->cdp_registro();
$total = $cnt ? (int) $cnt->c : 0;
$total_pages = (int) ceil($total / $per_page);

$db->cdp_query("SELECT l.* FROM cdb_activity_log l WHERE $where
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT " . (int) $per_page . " OFFSET " . (int) $offset);
cdp_alBind($db, $binds);
$db->cdp_execute();
$rows = $db->cdp_registros();
?>
<div class="table-responsive">
    <table class="table table-hover al-table mb-0">
        <thead>
            <tr>
                <th style="width:150px;">When</th>
                <th style="width:190px;">Who</th>
                <th style="width:150px;">Action</th>
                <th>Activity</th>
                <th style="width:160px;">Record</th>
                <th style="width:130px;">Status Set</th>
                <th style="width:110px;">IP</th>
                <th style="width:40px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="8" class="text-center text-muted py-5">
                    No activity matches these filters.
                </td></tr>
            <?php else : foreach ($rows as $r) : ?>
                <tr class="al-row<?php echo $r->outcome !== 'success' ? ' al-row--' . $e($r->outcome) : ''; ?>"
                    onclick="cdpAlDetail(<?php echo (int) $r->id; ?>)">
                    <td class="text-nowrap">
                        <div><?php echo $e(cdp_alWhen($r->created_at, 'Y-m-d')); ?></div>
                        <small class="text-muted"><?php echo $e(cdp_alWhen($r->created_at, 'H:i:s')); ?></small>
                    </td>
                    <td>
                        <div class="al-actor"><?php echo $e($r->actor_name ?: '—'); ?></div>
                        <small class="text-muted"><?php echo $e($r->role_name); ?></small>
                        <?php if ((int) $r->impersonated_by > 0) : ?>
                            <span class="al-pill al-pill--imp" title="Performed through View As">View As</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="al-verb" style="--c:<?php echo $e(cdp_alVerbColor($r->verb)); ?>">
                            <?php echo $e(cdp_activityVerbLabel($r->verb)); ?>
                        </span>
                        <div><small class="text-muted"><?php echo $e(cdp_activityModuleLabel($r->module)); ?></small></div>
                    </td>
                    <td><?php echo $e($r->summary); ?>
                        <?php if ($r->outcome !== 'success') : ?>
                            <span class="<?php echo cdp_alOutcomeClass($r->outcome); ?>"><?php echo $e(ucfirst($r->outcome)); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <?php echo $e($r->entity_label ?: ($r->entity_id !== '' ? '#' . $r->entity_id : '—')); ?>
                    </td>
                    <td>
                        <?php echo $r->status_name ? '<span class="al-status">' . $e($r->status_name) . '</span>' : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-muted"><small><?php echo $e($r->ip ?: '—'); ?></small></td>
                    <td class="text-right"><iconify-icon icon="solar:alt-arrow-right-linear" class="text-muted"></iconify-icon></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
    <small class="text-muted">
        <?php echo number_format($total); ?> entries · page <?php echo (int) $page; ?> of <?php echo max(1, $total_pages); ?>
    </small>
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-secondary" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="cdpAlGo(1)">&laquo; First</button>
        <button class="btn btn-outline-secondary" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="cdpAlGo(<?php echo $page - 1; ?>)">Prev</button>
        <button class="btn btn-outline-secondary" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="cdpAlGo(<?php echo $page + 1; ?>)">Next</button>
        <button class="btn btn-outline-secondary" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="cdpAlGo(<?php echo max(1, $total_pages); ?>)">Last &raquo;</button>
    </div>
</div>
