<?php
// *************************************************************************
// * Message Logs — the filtered table, and one message's full detail.      *
// *                                                                       *
// * ?action=rows   (default) the paginated table                          *
// * ?action=detail&id=N      one message: body, provider response, context *
// *                                                                       *
// * Filters come from helpers/message_log_query.php — shared with the      *
// * statistics and the CSV export.                                         *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/message_log_query.php');
require_login();
require_permission('view_message_logs');

$db = new Conexion;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$pill = function ($text, $color) use ($e) {
    return '<span class="ml-pill" style="--c:' . $e($color) . '">' . $e($text) . '</span>';
};

// ---------------------------------------------------------------------------
// One message
// ---------------------------------------------------------------------------
if (($_REQUEST['action'] ?? '') === 'detail') {

    $db->cdp_query("SELECT * FROM cdb_message_log WHERE id = :id LIMIT 1");
    $db->bind(':id', (int) ($_REQUEST['id'] ?? 0));
    $db->cdp_execute();
    $row = $db->cdp_registro();

    if (!$row) {
        echo '<div class="alert alert-warning mb-0">That message no longer exists in the log.</div>';
        exit;
    }

    $isHtml = $row->channel === 'email' && preg_match('/<[a-z][\s\S]*>/i', (string) $row->body);
    ?>
    <div class="ml-detail">
        <div class="ml-detail__grid">
            <div><span class="ml-k">When</span><span class="ml-v"><?php echo $e(cdp_mlWhen($row->created_at)); ?></span></div>
            <div><span class="ml-k">Channel</span><span class="ml-v"><?php echo $pill(cdp_mlChannelLabel($row->channel), cdp_mlChannelColor($row->channel)); ?></span></div>
            <div><span class="ml-k">Status</span><span class="ml-v"><?php echo $pill(cdp_mlStatusLabel($row->status), cdp_mlStatusColor($row->status)); ?></span></div>
            <div><span class="ml-k">Recipient</span><span class="ml-v"><?php echo $e($row->recipient_name ?: '—'); ?><?php echo $row->recipient_user_id ? ' <small class="text-muted">(user #' . (int) $row->recipient_user_id . ')</small>' : ''; ?></span></div>
            <div><span class="ml-k">Sent To</span><span class="ml-v"><?php echo $e($row->recipient_to ?: '—'); ?></span></div>
            <div><span class="ml-k">Sent By</span><span class="ml-v"><?php echo $e($row->sent_by_name ?: 'System'); ?><?php echo $row->sent_by_role ? ' <small class="text-muted">(' . $e($row->sent_by_role) . ')</small>' : ''; ?></span></div>
            <div><span class="ml-k">Origin</span><span class="ml-v"><?php echo $e($row->source_label ?: cdp_msgSourceLabel($row->source)); ?></span></div>
            <div><span class="ml-k">Template</span><span class="ml-v"><?php echo $row->template_id ? '#' . (int) $row->template_id : '—'; ?></span></div>
            <div><span class="ml-k">Record</span><span class="ml-v"><?php echo $e($row->entity_label ?: '—'); ?><?php echo $row->entity_type ? ' <small class="text-muted">' . $e($row->entity_type) . ($row->entity_id !== '' ? ' #' . $e($row->entity_id) : '') . '</small>' : ''; ?></span></div>
            <?php if ($row->batch_id) : ?>
            <div><span class="ml-k">Batch</span><span class="ml-v"><a href="javascript:void(0)" onclick="cdpMlFilterBatch('<?php echo $e($row->batch_id); ?>')"><code><?php echo $e($row->batch_id); ?></code></a> <small class="text-muted">click to see the whole batch</small></span></div>
            <?php endif; ?>
            <div><span class="ml-k">IP Address</span><span class="ml-v"><?php echo $e($row->ip ?: '—'); ?></span></div>
            <div><span class="ml-k">Endpoint</span><span class="ml-v"><code><?php echo $e($row->endpoint ?: '—'); ?></code></span></div>
            <div class="ml-detail__wide"><span class="ml-k">Result</span><span class="ml-v"><?php echo $e($row->status_detail ?: '—'); ?></span></div>
            <?php if ($row->subject !== '') : ?>
            <div class="ml-detail__wide"><span class="ml-k">Subject</span><span class="ml-v"><?php echo $e($row->subject); ?></span></div>
            <?php endif; ?>
        </div>

        <h6 class="ml-detail__h">Message</h6>
        <?php if ($isHtml) : ?>
            <iframe class="ml-body-frame" sandbox="" srcdoc="<?php echo $e($row->body); ?>"></iframe>
        <?php else : ?>
            <pre class="ml-body"><?php echo $e($row->body); ?></pre>
        <?php endif; ?>

        <?php if ($row->provider_response) : ?>
            <h6 class="ml-detail__h">Provider Response</h6>
            <pre class="ml-body ml-body--mono"><?php echo $e($row->provider_response); ?></pre>
        <?php endif; ?>
    </div>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// The table
// ---------------------------------------------------------------------------
$f = cdp_mlFilters();
list($where, $binds) = cdp_mlWhere($f);

$page     = (isset($_REQUEST['page']) && (int) $_REQUEST['page'] > 0) ? (int) $_REQUEST['page'] : 1;
$per_page = in_array((int) ($_REQUEST['per_page'] ?? 0), [25, 50, 100, 200], true) ? (int) $_REQUEST['per_page'] : 50;
$offset   = ($page - 1) * $per_page;

$db->cdp_query("SELECT COUNT(*) AS c FROM cdb_message_log l WHERE $where");
cdp_mlBind($db, $binds);
$db->cdp_execute();
$cnt = $db->cdp_registro();
$total = $cnt ? (int) $cnt->c : 0;
$total_pages = (int) ceil($total / $per_page);

$db->cdp_query("SELECT l.id, l.created_at, l.channel, l.source, l.source_label, l.template_id, l.subject,
                       l.recipient_user_id, l.recipient_name, l.recipient_to, l.entity_type, l.entity_id, l.entity_label,
                       l.batch_id, l.status, l.status_detail, l.sent_by, l.sent_by_name, l.sent_by_role,
                       LEFT(l.body, 160) AS body_preview
                FROM cdb_message_log l WHERE $where
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT " . (int) $per_page . " OFFSET " . (int) $offset);
cdp_mlBind($db, $binds);
$db->cdp_execute();
$rows = $db->cdp_registros();
?>
<div class="table-responsive">
    <table class="table table-hover ml-table mb-0">
        <thead>
            <tr>
                <th style="width:140px;">When</th>
                <th style="width:90px;">Channel</th>
                <th style="width:200px;">Recipient</th>
                <th>Message</th>
                <th style="width:170px;">Origin</th>
                <th style="width:150px;">Sent By</th>
                <th style="width:110px;">Status</th>
                <th style="width:30px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No messages match these filters.</td></tr>
            <?php else : foreach ($rows as $r) :
                $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r->body_preview)));
                if (function_exists('mb_strlen') ? mb_strlen($preview) > 110 : strlen($preview) > 110) {
                    $preview = (function_exists('mb_substr') ? mb_substr($preview, 0, 110) : substr($preview, 0, 110)) . '…';
                } ?>
                <tr class="ml-row ml-row--<?php echo $e($r->status); ?>" onclick="cdpMlDetail(<?php echo (int) $r->id; ?>)">
                    <td class="text-nowrap">
                        <div><?php echo $e(cdp_mlWhen($r->created_at, 'Y-m-d')); ?></div>
                        <small class="text-muted"><?php echo $e(cdp_mlWhen($r->created_at, 'H:i:s')); ?></small>
                    </td>
                    <td><?php echo $pill(cdp_mlChannelLabel($r->channel), cdp_mlChannelColor($r->channel)); ?></td>
                    <td>
                        <div class="ml-name"><?php echo $e($r->recipient_name ?: '—'); ?></div>
                        <small class="text-muted"><?php echo $e($r->recipient_to); ?></small>
                    </td>
                    <td>
                        <?php if ($r->subject !== '') : ?><div class="ml-subject"><?php echo $e($r->subject); ?></div><?php endif; ?>
                        <div class="text-muted ml-preview"><?php echo $e($preview); ?></div>
                        <?php if ($r->entity_label) : ?><small class="ml-entity"><i class="mdi mdi-package-variant"></i> <?php echo $e($r->entity_label); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <div><?php echo $e($r->source_label ?: cdp_msgSourceLabel($r->source)); ?></div>
                        <?php if ($r->template_id) : ?><small class="text-muted">template #<?php echo (int) $r->template_id; ?></small><?php endif; ?>
                    </td>
                    <td>
                        <div class="ml-name"><?php echo $e($r->sent_by_name ?: 'System'); ?></div>
                        <small class="text-muted"><?php echo $e($r->sent_by_role); ?></small>
                    </td>
                    <td>
                        <?php echo $pill(cdp_mlStatusLabel($r->status), cdp_mlStatusColor($r->status)); ?>
                        <?php if ($r->status !== 'sent' && $r->status_detail) : ?>
                            <div><small class="text-muted" title="<?php echo $e($r->status_detail); ?>"><?php echo $e(mb_substr($r->status_detail, 0, 42)) . (mb_strlen($r->status_detail) > 42 ? '…' : ''); ?></small></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><iconify-icon icon="solar:alt-arrow-right-linear" class="text-muted"></iconify-icon></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
    <small class="text-muted"><?php echo number_format($total); ?> messages · page <?php echo (int) $page; ?> of <?php echo max(1, $total_pages); ?></small>
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-secondary" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="cdpMlGo(1)">&laquo; First</button>
        <button class="btn btn-outline-secondary" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="cdpMlGo(<?php echo $page - 1; ?>)">Prev</button>
        <button class="btn btn-outline-secondary" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="cdpMlGo(<?php echo $page + 1; ?>)">Next</button>
        <button class="btn btn-outline-secondary" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="cdpMlGo(<?php echo max(1, $total_pages); ?>)">Last &raquo;</button>
    </div>
</div>
