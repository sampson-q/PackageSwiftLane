<?php
// ============================================================================
// Activity Logs — who did what, to which record, when, and what changed.
//
// The page is three co-ordinated surfaces over ONE filter set:
//   · headline tiles + charts   (ajax/reports/activity_logs_stats_ajax.php)
//   · the entry table           (ajax/reports/activity_logs_ajax.php)
//   · a CSV of the same rows    (ajax/reports/activity_logs_export_ajax.php)
// All three build their WHERE clause with helpers/activity_log_query.php, so a
// number in a tile always describes exactly the rows listed underneath it.
// ============================================================================

require_once('helpers/activity_log_query.php');

$userData = $user->cdp_getUserData();
$db = new Conexion;

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// The trail table is created by sql/activity_log.sql. Until that is applied the
// page must explain itself rather than error.
$tableReady = cdp_activityTableReady();

// ── Filter option lists ─────────────────────────────────────────────────────
$actorOptions = [];
$actionOptions = [];
if ($tableReady) {
    $db->cdp_query("SELECT user_id, MAX(actor_name) AS actor_name, MAX(role_name) AS role_name, COUNT(*) AS c
                    FROM cdb_activity_log WHERE user_id > 0
                    GROUP BY user_id ORDER BY actor_name ASC LIMIT 500");
    $db->cdp_execute();
    $actorOptions = (array) $db->cdp_registros();

    $db->cdp_query("SELECT action, MAX(action_label) AS action_label, COUNT(*) AS c
                    FROM cdb_activity_log
                    GROUP BY action ORDER BY action_label ASC LIMIT 200");
    $db->cdp_execute();
    $actionOptions = (array) $db->cdp_registros();
}

$db->cdp_query("SELECT role_id, role_name FROM cdb_user_roles WHERE rol_active = 1 ORDER BY role_name");
$db->cdp_execute();
$roleOptions = (array) $db->cdp_registros();

$db->cdp_query("SELECT id, mod_style FROM cdb_styles ORDER BY mod_style");
$db->cdp_execute();
$statusOptions = (array) $db->cdp_registros();

$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$defaultTo   = date('Y-m-d');
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Activity Logs | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        /* ── Filter panel ────────────────────────────────────────────────── */
        .al-filters { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; padding: 18px 20px; margin-bottom: 18px; }
        .al-filters label { font-size: .7rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: #8a94a6; margin-bottom: 4px; }
        .al-filters .form-control, .al-filters .custom-select { font-size: .86rem; }
        .al-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .al-quick button {
            border: 1px solid #e0e5ee; background: #fff; border-radius: 6px;
            padding: 5px 12px; font-size: .78rem; font-weight: 600; color: #6b7788; cursor: pointer;
        }
        .al-quick button.is-on { background: #1f2a37; border-color: #1f2a37; color: #fff; }

        /* ── Stat tiles ──────────────────────────────────────────────────── */
        .al-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .al-kpi { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; padding: 16px 18px; position: relative; overflow: hidden; }
        .al-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--c, #d7dce5); }
        .al-kpi__k { font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #8a94a6; }
        .al-kpi__v { font-size: 1.7rem; font-weight: 700; letter-spacing: -.02em; color: #1f2a37; line-height: 1.15; margin-top: 5px; }
        .al-kpi__s { font-size: .74rem; color: #99a2b1; margin-top: 2px; }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .al-card { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; margin-bottom: 18px; }
        .al-card__h {
            padding: 14px 18px; border-bottom: 1px solid #eef1f6;
            font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #8a94a6;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
        .al-card__b { padding: 16px 18px; }

        /* ── Breakdown bars ──────────────────────────────────────────────── */
        .al-bars { display: flex; flex-direction: column; gap: 10px; }
        .al-bar__top { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: 3px; }
        .al-bar__name { font-weight: 600; color: #3b4655; }
        .al-bar__n { color: #8a94a6; font-weight: 600; }
        .al-bar__track { height: 6px; background: #eef1f6; border-radius: 4px; overflow: hidden; }
        .al-bar__fill { height: 100%; border-radius: 4px; background: var(--c, #336aea); }
        .al-empty { color: #99a2b1; font-size: .85rem; padding: 18px 0; text-align: center; }

        /* ── Table ───────────────────────────────────────────────────────── */
        .al-table { font-size: .84rem; }
        .al-table thead th {
            font-size: .68rem; text-transform: uppercase; letter-spacing: .07em;
            font-weight: 700; color: #99a2b1; border-top: 0; white-space: nowrap;
        }
        .al-row { cursor: pointer; }
        .al-row:hover { background: #f7f9fc; }
        .al-row--denied { background: #fff7f8; }
        .al-row--failure { background: #fffaf2; }
        .al-actor { font-weight: 600; color: #1f2a37; }
        .al-verb {
            display: inline-block; padding: .15rem .5rem; border-radius: 5px;
            font-size: .7rem; font-weight: 700; color: #fff; background: var(--c, #6b7788);
        }
        .al-status {
            display: inline-block; padding: .13rem .5rem; border-radius: 1rem;
            font-size: .7rem; font-weight: 700; background: #eef2ff; color: #4258c9;
        }
        .al-pill { display: inline-block; padding: .1rem .45rem; border-radius: 1rem; font-size: .66rem; font-weight: 700; margin-left: 4px; }
        .al-pill--success { background: #e8f8f5; color: #0aa699; }
        .al-pill--denied  { background: #fdeaec; color: #f62d51; }
        .al-pill--failure { background: #fdf3e0; color: #b4770d; }
        .al-pill--imp     { background: #f3ecff; color: #7d4bd1; }

        /* ── Detail drawer ───────────────────────────────────────────────── */
        .al-detail__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px 20px; margin-bottom: 18px; }
        .al-detail__wide { grid-column: 1 / -1; }
        .al-detail__grid > div { display: flex; flex-direction: column; }
        .al-k { font-size: .66rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #99a2b1; }
        .al-v { font-size: .88rem; color: #1f2a37; word-break: break-word; }
        .al-detail__h { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #8a94a6; font-weight: 700; margin: 18px 0 8px; }
        .al-changes { font-size: .84rem; }
        .al-changes__f { font-weight: 600; color: #3b4655; }
        .al-changes__from { color: #b4491f; text-decoration: line-through; }
        .al-changes__to { color: #0aa699; font-weight: 600; }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><iconify-icon icon="solar:clipboard-list-linear"></iconify-icon> Activity Logs</h4>
                        <span class="text-muted">Every action taken in the system — by whom, on what, and when.</span>
                        <br>
                    </div>
                </div>
            </div>

            <div class="container-fluid pb-4">

                <?php if (!$tableReady) : ?>
                    <div class="alert alert-warning">
                        <b>The activity trail is not set up yet.</b>
                        Apply <code>sql/activity_log.sql</code> to this database to create
                        <code>cdb_activity_log</code> and register the permissions. Until then
                        the system keeps working normally, but nothing is being recorded.
                    </div>
                <?php endif; ?>

                <!-- ── Filters ────────────────────────────────────────────── -->
                <div class="al-filters">
                    <div class="al-quick" id="al_quick">
                        <button type="button" data-range="today">Today</button>
                        <button type="button" data-range="7">Last 7 Days</button>
                        <button type="button" data-range="30" class="is-on">Last 30 Days</button>
                        <button type="button" data-range="month">This Month</button>
                        <button type="button" data-range="all">All Time</button>
                    </div>

                    <div class="form-row">
                        <div class="col-md-2 mb-2">
                            <label for="al_from">From</label>
                            <input type="date" class="form-control" id="al_from" value="<?php echo $e($defaultFrom); ?>">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="al_to">To</label>
                            <input type="date" class="form-control" id="al_to" value="<?php echo $e($defaultTo); ?>">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="al_user">User</label>
                            <select class="form-control custom-select" id="al_user">
                                <option value="0">All users</option>
                                <?php foreach ($actorOptions as $a) : ?>
                                    <option value="<?php echo (int) $a->user_id; ?>">
                                        <?php echo $e($a->actor_name ?: ('User #' . $a->user_id)); ?>
                                        <?php echo $a->role_name ? '(' . $e($a->role_name) . ')' : ''; ?>
                                        — <?php echo number_format((int) $a->c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="al_role">Role</label>
                            <select class="form-control custom-select" id="al_role">
                                <option value="0">All roles</option>
                                <?php foreach ($roleOptions as $r) : ?>
                                    <option value="<?php echo (int) $r->role_id; ?>"><?php echo $e($r->role_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="al_search">Search</label>
                            <input type="text" class="form-control" id="al_search" placeholder="Name, tracking number, IP…">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-2 mb-2">
                            <label for="al_module">Module</label>
                            <select class="form-control custom-select" id="al_module">
                                <option value="">All modules</option>
                                <?php foreach (cdp_activityModules() as $k => $label) : ?>
                                    <option value="<?php echo $e($k); ?>"><?php echo $e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="al_verb">Action Type</label>
                            <select class="form-control custom-select" id="al_verb">
                                <option value="">All action types</option>
                                <?php foreach (cdp_activityVerbs() as $k => $label) : ?>
                                    <option value="<?php echo $e($k); ?>"><?php echo $e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="al_action">Specific Action</label>
                            <select class="form-control custom-select" id="al_action">
                                <option value="">All actions</option>
                                <?php foreach ($actionOptions as $a) : ?>
                                    <option value="<?php echo $e($a->action); ?>">
                                        <?php echo $e($a->action_label ?: $a->action); ?> — <?php echo number_format((int) $a->c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="al_status">Status Set</label>
                            <select class="form-control custom-select" id="al_status">
                                <option value="0">Any status</option>
                                <?php foreach ($statusOptions as $s) : ?>
                                    <option value="<?php echo (int) $s->id; ?>"><?php echo $e($s->mod_style); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="al_outcome">Outcome</label>
                            <select class="form-control custom-select" id="al_outcome">
                                <option value="">All outcomes</option>
                                <option value="success">Success</option>
                                <option value="denied">Denied (No Permission)</option>
                                <option value="failure">Failure</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap:10px;">
                        <div class="d-flex align-items-center" style="gap:16px;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="al_views">
                                <label class="custom-control-label" for="al_views" style="text-transform:none;letter-spacing:0;font-size:.84rem;color:#3b4655;">
                                    Include page views
                                </label>
                            </div>
                            <select class="form-control custom-select" id="al_per_page" style="width:auto;">
                                <option value="25">25 rows</option>
                                <option value="50" selected>50 rows</option>
                                <option value="100">100 rows</option>
                                <option value="200">200 rows</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="cdpAlReset()">
                                <i class="fa fa-undo"></i> Reset
                            </button>
                            <?php if ($user->cdp_hasPermission('export_activity_logs')) : ?>
                            <button type="button" class="btn btn-outline-dark btn-sm" onclick="cdpAlExport()">
                                <i class="fa fa-download"></i> Export CSV
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="cdpAlGo(1)">
                                <i class="fa fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Headline ───────────────────────────────────────────── -->
                <div class="al-kpis" id="al_kpis"></div>

                <!-- ── Charts ─────────────────────────────────────────────── -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="al-card">
                            <div class="al-card__h">Activity Over Time <span id="al_range_note" class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;"></span></div>
                            <div class="al-card__b"><div id="al_chart_time" style="min-height:250px;"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="al-card">
                            <div class="al-card__h">By Action Type</div>
                            <div class="al-card__b"><div id="al_chart_verb" style="min-height:250px;"></div></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="al-card">
                            <div class="al-card__h">By Module</div>
                            <div class="al-card__b"><div class="al-bars" id="al_modules"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="al-card">
                            <div class="al-card__h">Statuses Applied</div>
                            <div class="al-card__b"><div class="al-bars" id="al_statuses"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="al-card">
                            <div class="al-card__h">By Role</div>
                            <div class="al-card__b"><div class="al-bars" id="al_roles"></div></div>
                        </div>
                    </div>
                </div>

                <!-- ── Who did the most ───────────────────────────────────── -->
                <div class="al-card">
                    <div class="al-card__h">Most Active Users <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">click a row to filter the log to that person</span></div>
                    <div class="table-responsive">
                        <table class="table table-hover al-table mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th class="text-right">Created</th>
                                    <th class="text-right">Updated</th>
                                    <th class="text-right">Deleted</th>
                                    <th class="text-right">Status Changes</th>
                                    <th class="text-right">Total</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody id="al_actors"></tbody>
                        </table>
                    </div>
                </div>

                <!-- ── The trail ──────────────────────────────────────────── -->
                <div class="al-card">
                    <div class="al-card__h">Activity Trail</div>
                    <div class="al-card__b">
                        <div id="al_loader" style="display:none;" class="text-center my-3">
                            <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                        </div>
                        <div id="al_rows"></div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- ── Entry detail ───────────────────────────────────────────────────── -->
    <div class="modal fade" id="alDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Entry</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="al_detail_body">
                    <div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                </div>
            </div>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/activity_logs.js') ?>"></script>
</body>

</html>
