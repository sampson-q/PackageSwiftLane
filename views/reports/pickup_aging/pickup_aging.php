<?php
// *************************************************************************
// * Pickup Aging — dedicated review page                                 *
// * Lists every package in the pickup-aging chain with its current stage. *
// *************************************************************************

require_once(__DIR__ . '/../../../helpers/pickup_aging.php');

// left_sidebar.php gates its nav blocks on $userData->userlevel — without this
// the whole sidebar renders empty on this page.
$userData = $user->cdp_getUserData();

// Refresh the state machine on load (same scan the cron runs).
cdp_processPickupAging();

$db = new Conexion;
$db->cdp_query("
    SELECT p.order_id, p.order_track, p.ready_at, p.notified_at, p.not_picked_at, p.auction_at,
           p.notified_by, DATEDIFF(NOW(), p.ready_at) AS days_ready,
           a.status_courier, s.mod_style, s.color,
           u.fname, u.lname, u.locker
    FROM cdb_package_pickup_aging p
    JOIN cdb_add_order a ON a.order_id = p.order_id
    LEFT JOIN cdb_styles s ON s.id = a.status_courier
    LEFT JOIN cdb_users  u ON u.id = p.sender_id
    ORDER BY p.ready_at ASC");
$db->cdp_execute();
$rows = $db->cdp_registros();

$readyDays = (int) CDP_PA_READY_DAYS;
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Pickup Aging | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style type="text/css">
        .pa-badge { color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
        .pa-stage { font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; }
        .pa-stage-pending { background:#fff3cd; color:#7a5b00; }
        .pa-stage-notified { background:#cfe2ff; color:#084298; }
        .pa-stage-notpicked { background:#f8d7da; color:#842029; }
        .pa-stage-auction { background:#7a1f1f; color:#fff; }
        .pa-stage-waiting { background:#e2e3e5; color:#41464b; }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline" style="border-top:3px solid #7a1f1f;">
                            <h4 class="card-title ml-4 mt-3"><i class="mdi mdi-clock-alert-outline"></i> Pickup Aging</h4>
                            <div class="card-body">
                                <p class="text-muted">
                                    Packages awaiting collection. A package at <b>Ready for PickUp</b> for
                                    <b><?php echo $readyDays; ?>+ days</b> can have its sender notified (status &rarr;
                                    Pending Collection); a week later it auto-moves to <b>Not Picked Up</b>, then to
                                    <b>Auction</b>.
                                </p>

                                <div class="mb-3">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="cdpPaNotifySelected()">
                                        <i class="mdi mdi-bell-ring"></i> Notify senders for selected
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr style="background:#f1f3f5;">
                                                <th style="width:30px;"></th>
                                                <th>Shipment</th>
                                                <th>Sender</th>
                                                <th>Current Status</th>
                                                <th>Ready Since</th>
                                                <th>Stage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$rows): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-3">No packages in the pickup-aging chain.</td></tr>
                                        <?php else: foreach ($rows as $r):
                                            $pending = ($r->notified_at === null && (int) $r->status_courier === CDP_PA_READY && (int) $r->days_ready >= $readyDays);
                                            if ($r->auction_at !== null)          { $stage = '<span class="pa-stage pa-stage-auction">Auction</span>'; }
                                            elseif ($r->not_picked_at !== null)   { $stage = '<span class="pa-stage pa-stage-notpicked">Not Picked Up</span>'; }
                                            elseif ($r->notified_at !== null)     { $stage = '<span class="pa-stage pa-stage-notified">Pending Collection</span>'; }
                                            elseif ($pending)                     { $stage = '<span class="pa-stage pa-stage-pending">Awaiting Notification</span>'; }
                                            else                                  { $stage = '<span class="pa-stage pa-stage-waiting">Ready (' . (int) $r->days_ready . 'd)</span>'; }
                                            $sender = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
                                            if ($r->locker) $sender .= ' (' . $r->locker . ')';
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if ($pending): ?>
                                                        <input type="checkbox" class="pa-check" value="<?php echo (int) $r->order_id; ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td><b><?php echo htmlspecialchars($r->order_track); ?></b></td>
                                                <td><?php echo htmlspecialchars($sender ?: 'N/A'); ?></td>
                                                <td><span class="pa-badge" style="background:<?php echo htmlspecialchars($r->color ?: '#888'); ?>;"><?php echo htmlspecialchars($r->mod_style ?: '—'); ?></span></td>
                                                <td><?php echo htmlspecialchars((string) $r->ready_at); ?> <span class="text-muted">(<?php echo (int) $r->days_ready; ?>d)</span></td>
                                                <td><?php echo $stage; ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/pickup_aging.js') ?>"></script>
</body>

</html>
