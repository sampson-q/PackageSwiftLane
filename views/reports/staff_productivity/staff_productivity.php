<?php
// ============================================================================
// Staff Productivity — active hours and output per staff member.
//
// Reads the unified event stream in helpers/staff_activity.php; records
// nothing itself. Figures come from ajax/reports/staff_productivity_ajax.php
// and the CSV from staff_productivity_export_ajax.php, both built from the
// same cdp_spSummary() call, so the tiles, the table and the export always
// agree.
// ============================================================================

require_once('helpers/staff_activity.php');

$userData = $user->cdp_getUserData();
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$staffUsers = cdp_spStaffUsers();
$cutover    = cdp_spCutover();

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
    <title>Staff Productivity | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .sp-filters { background:#fff; border:1px solid #e9edf3; border-radius:10px; padding:18px 20px; margin-bottom:18px; }
        .sp-filters label { font-size:.7rem; text-transform:uppercase; letter-spacing:.07em; font-weight:700; color:#8a94a6; margin-bottom:4px; }
        .sp-filters .form-control, .sp-filters .custom-select { font-size:.86rem; }
        .sp-quick { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
        .sp-quick button {
            border:1px solid #e0e5ee; background:#fff; border-radius:6px;
            padding:5px 12px; font-size:.78rem; font-weight:600; color:#6b7788; cursor:pointer;
        }
        .sp-quick button.is-on { background:#1f2a37; border-color:#1f2a37; color:#fff; }

        .sp-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:14px; margin-bottom:18px; }
        .sp-kpi { background:#fff; border:1px solid #e9edf3; border-radius:10px; padding:16px 18px; position:relative; overflow:hidden; }
        .sp-kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--c,#d7dce5); }
        .sp-k { font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:#8a94a6; }
        .sp-kpi__v { font-size:1.7rem; font-weight:700; letter-spacing:-.02em; color:#1f2a37; line-height:1.15; margin-top:5px; }
        .sp-kpi__s { font-size:.74rem; color:#99a2b1; margin-top:2px; }

        .sp-card { background:#fff; border:1px solid #e9edf3; border-radius:10px; margin-bottom:18px; }
        .sp-card__h {
            padding:14px 18px; border-bottom:1px solid #eef1f6;
            font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:#8a94a6;
            display:flex; align-items:center; justify-content:space-between; gap:10px;
        }
        .sp-card__b { padding:16px 18px; }

        .sp-table { font-size:.85rem; }
        .sp-table thead th {
            font-size:.68rem; text-transform:uppercase; letter-spacing:.07em;
            font-weight:700; color:#99a2b1; border-top:0; white-space:nowrap;
        }
        .sp-row { cursor:pointer; }
        .sp-row:hover { background:#f7f9fc; }
        .sp-name { font-weight:600; color:#1f2a37; }
        .sp-hours { font-weight:700; color:#1f2a37; }
        .sp-bar { height:5px; background:#eef1f6; border-radius:3px; overflow:hidden; margin-top:4px; min-width:70px; }
        .sp-bar span { display:block; height:100%; background:#336aea; border-radius:3px; }
        .sp-pill { display:inline-block; padding:.1rem .45rem; border-radius:1rem; font-size:.66rem; font-weight:700; margin-left:4px; }
        .sp-pill--off { background:#fdeaec; color:#f62d51; }
        .sp-pill--thin { background:#fdf3e0; color:#b4770d; cursor:help; }

        .sp-block {
            display:inline-block; background:#eef2ff; color:#4258c9; border-radius:5px;
            padding:2px 7px; font-size:.72rem; font-weight:600; margin:2px 4px 2px 0; white-space:nowrap;
        }
        .sp-block small { color:#7a86b8; font-weight:500; margin-left:3px; }

        .sp-detail__who { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; padding-bottom:14px; border-bottom:1px solid #eef1f6; margin-bottom:14px; }
        .sp-detail__name { font-size:1.1rem; font-weight:700; color:#1f2a37; }
        .sp-detail__n { font-size:1.4rem; font-weight:700; color:#1f2a37; }
        .sp-empty { color:#99a2b1; font-size:.87rem; padding:34px 0; text-align:center; }
        .sp-note { font-size:.78rem; color:#8a94a6; }
        .sp-note b { color:#6b7788; }
        /* Durations like "5h 49m" must not wrap onto two lines in a narrow column. */
        .sp-table td.text-right, .sp-table .sp-hours { white-space:nowrap; }
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
                        <h4 class="page-title"><iconify-icon icon="solar:chart-square-linear"></iconify-icon> Staff Productivity</h4>
                        <span class="text-muted">Time worked in the system and packages registered, per staff member.</span>
                        <br>
                    </div>
                </div>
            </div>

            <div class="container-fluid pb-4">

                <!-- What the numbers mean. Stated on the page, not buried in a tooltip. -->
                <div class="alert alert-light border" style="font-size:.84rem;">
                    <b>Active hours are reconstructed, not measured.</b>
                    Nothing in the system reports a heartbeat, so this counts runs of recorded
                    activity: a gap longer than the idle setting below ends a block, and the
                    blocks are added up. It is time spent working in the system — not time
                    signed in with a tab open.
                    <?php if ($cutover) : ?>
                        Logins and page activity are recorded from
                        <b><?php echo $e(date('j M Y', strtotime($cutover))); ?></b>;
                        before that only package, consolidation and pickup actions are known,
                        so earlier active hours read low.
                    <?php else : ?>
                        The activity trail is not deployed on this database yet, so only
                        package, consolidation and pickup actions are known and active hours
                        read low. Applying <code>sql/activity_log.sql</code> fixes that going forward.
                    <?php endif; ?>
                </div>

                <!-- ── Filters ────────────────────────────────────────────── -->
                <div class="sp-filters">
                    <div class="sp-quick" id="sp_quick">
                        <button type="button" data-range="today">Today</button>
                        <button type="button" data-range="7">Last 7 Days</button>
                        <button type="button" data-range="30" class="is-on">Last 30 Days</button>
                        <button type="button" data-range="month">This Month</button>
                        <button type="button" data-range="lastmonth">Last Month</button>
                        <button type="button" data-range="all">All Time</button>
                    </div>

                    <div class="form-row align-items-end">
                        <div class="col-md-2 mb-2">
                            <label for="sp_from">From</label>
                            <input type="date" class="form-control" id="sp_from" value="<?php echo $e($defaultFrom); ?>">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="sp_to">To</label>
                            <input type="date" class="form-control" id="sp_to" value="<?php echo $e($defaultTo); ?>">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="sp_user">Staff Member</label>
                            <select class="form-control custom-select" id="sp_user">
                                <option value="">All staff (<?php echo count($staffUsers); ?>)</option>
                                <?php foreach ($staffUsers as $u) : ?>
                                    <option value="<?php echo (int) $u->id; ?>">
                                        <?php echo $e($u->display_name); ?> — <?php echo $e($u->role_name); ?><?php echo (int) $u->active !== 1 ? ' (inactive)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="sp_gap">Idle Gap</label>
                            <select class="form-control custom-select" id="sp_gap">
                                <option value="5">5 minutes</option>
                                <option value="10">10 minutes</option>
                                <option value="15" selected>15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="60">60 minutes</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 text-right">
                            <?php if ($user->cdp_hasPermission('export_staff_productivity')) : ?>
                            <button type="button" class="btn btn-outline-dark btn-sm" onclick="cdpSpExport()">
                                <i class="fa fa-download"></i> Export CSV
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="cdpSpLoad()">
                                <i class="fa fa-search"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Headline ───────────────────────────────────────────── -->
                <div class="sp-kpis" id="sp_kpis"></div>

                <!-- ── Charts ─────────────────────────────────────────────── -->
                <div class="row">
                    <div class="col-lg-7">
                        <div class="sp-card">
                            <div class="sp-card__h">Hours Worked And Packages Added, By Day</div>
                            <div class="sp-card__b"><div id="sp_chart_day" style="min-height:270px;"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="sp-card">
                            <div class="sp-card__h">When The Work Happens <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">by hour of day</span></div>
                            <div class="sp-card__b"><div id="sp_chart_hour" style="min-height:270px;"></div></div>
                        </div>
                    </div>
                </div>

                <!-- ── Per staff ──────────────────────────────────────────── -->
                <div class="sp-card">
                    <div class="sp-card__h">
                        By Staff Member
                        <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">click a row for the day-by-day breakdown</span>
                    </div>
                    <div id="sp_loader" style="display:none;" class="text-center my-4">
                        <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover sp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th class="text-right">Active Hours</th>
                                    <th class="text-right">Days</th>
                                    <th class="text-right">Avg/Day</th>
                                    <th class="text-right">Packages Added</th>
                                    <th class="text-right">Per Hour</th>
                                    <th class="text-right">Edits</th>
                                    <th class="text-right">Logins</th>
                                    <th>First Activity</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody id="sp_rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- ── Day-by-day drill-down ──────────────────────────────────────────── -->
    <div class="modal fade" id="spDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Day-By-Day Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="sp_detail_body">
                    <div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                </div>
            </div>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/staff_productivity.js') ?>"></script>
</body>

</html>
