<?php
// ============================================================================
// Staff Productivity — check-ins, active / idle time and output per staff
// member.
//
// Reads the engine in helpers/staff_activity.php. Figures come from
// ajax/reports/staff_productivity_ajax.php and the CSV from
// staff_productivity_export_ajax.php, both built from the same
// cdp_spSummary() / cdp_spBuildDays() calls, so the tiles, the table, the
// drill-downs and the export always agree. Thresholds are edited in the
// settings modal (staff_productivity_settings_ajax.php).
// ============================================================================

require_once('helpers/staff_activity.php');

$userData = $user->cdp_getUserData();
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$staffUsers = cdp_spStaffUsers();
$cutover    = cdp_spCutover();
$spSettings = cdp_spSettings();
$presenceOk = cdp_spPresenceTableReady();
$settingsOk = cdp_spSettingsTableReady();

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
        .sp-settings-line { font-size:.78rem; color:#8a94a6; margin-top:6px; }
        .sp-settings-line b { color:#4b5563; }

        .sp-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:14px; margin-bottom:18px; }
        .sp-kpis--tight { grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:14px; }
        .sp-kpi { background:#fff; border:1px solid #e9edf3; border-radius:10px; padding:16px 18px; position:relative; overflow:hidden; }
        .sp-kpis--tight .sp-kpi { padding:12px 14px; }
        .sp-kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--c,#d7dce5); }
        .sp-k { font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:#8a94a6; }
        .sp-kpi__v { font-size:1.7rem; font-weight:700; letter-spacing:-.02em; color:#1f2a37; line-height:1.15; margin-top:5px; }
        .sp-kpis--tight .sp-kpi__v { font-size:1.3rem; }
        .sp-kpi__s { font-size:.74rem; color:#99a2b1; margin-top:2px; }

        .sp-card { background:#fff; border:1px solid #e9edf3; border-radius:10px; margin-bottom:18px; }
        .sp-card__h {
            padding:14px 18px; border-bottom:1px solid #eef1f6;
            font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:#8a94a6;
            display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
        }
        .sp-card__b { padding:16px 18px; }
        .sp-heat-note { font-size:.74rem; color:#99a2b1; text-transform:none; letter-spacing:0; font-weight:400; }

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
        .sp-pill { display:inline-block; padding:.1rem .45rem; border-radius:1rem; font-size:.66rem; font-weight:700; margin-left:4px; white-space:nowrap; }
        .sp-pill--off  { background:#fdeaec; color:#f62d51; }
        .sp-pill--thin { background:#fdf3e0; color:#b4770d; cursor:help; }
        .sp-pill--mid  { background:#eef2ff; color:#4258c9; cursor:help; }
        .sp-pill--ok   { background:#e3f7f3; color:#0a8a7d; cursor:help; }
        .sp-pill--pkg  { background:#e3f7f3; color:#0a8a7d; font-family:SFMono-Regular,Menlo,Consolas,monospace; font-size:.68rem; cursor:help; }
        .sp-pill--act  { background:#f1f3f7; color:#6b7788; cursor:help; }
        .sp-btn-xs { padding:1px 8px; font-size:.7rem; line-height:1.5; }

        .sp-ref {
            display:inline-block; background:#f1f3f7; color:#374151; border-radius:5px;
            padding:1px 7px; font-size:.72rem; font-weight:600; margin:2px 3px 2px 0;
            font-family:SFMono-Regular,Menlo,Consolas,monospace; white-space:nowrap;
        }
        .sp-ref--create { background:#e3f7f3; color:#0a8a7d; }
        .sp-ref--edit   { background:#f0e9ff; color:#6d3fd1; }

        .sp-detail__who { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; padding-bottom:14px; border-bottom:1px solid #eef1f6; margin-bottom:14px; }
        .sp-detail__name { font-size:1.1rem; font-weight:700; color:#1f2a37; }
        .sp-detail__n { font-size:1.4rem; font-weight:700; color:#1f2a37; }
        .sp-empty { color:#99a2b1; font-size:.87rem; padding:34px 0; text-align:center; }
        .sp-note { font-size:.78rem; color:#8a94a6; }
        .sp-note b { color:#6b7788; }
        .sp-table td.text-right, .sp-table .sp-hours { white-space:nowrap; }

        /* ── Day timeline ─────────────────────────────────────────────── */
        .sp-strip-wrap { margin:6px 0 18px; }
        .sp-strip { position:relative; height:34px; background:#f1f3f7; border-radius:7px; overflow:visible; }
        .sp-strip__seg { position:absolute; top:6px; height:22px; border-radius:4px; }
        .sp-strip__seg--active { background:#336aea; }
        .sp-strip__seg--idle   { background:#f0c674; }
        .sp-strip__dot {
            position:absolute; top:-5px; width:9px; height:9px; border-radius:50%;
            transform:translateX(-50%); border:2px solid #fff; box-shadow:0 0 0 1px rgba(0,0,0,.08); cursor:help;
        }
        .sp-strip__dot--create { background:#0aa699; }
        .sp-strip__dot--edit   { background:#9b6ef3; top:auto; bottom:-5px; }
        .sp-strip__axis { position:relative; height:16px; margin-top:8px; font-size:.68rem; color:#8a94a6; }
        .sp-strip__axis span { position:absolute; transform:translateX(-50%); white-space:nowrap; }
        .sp-strip__axis span:first-child { transform:none; font-weight:700; color:#1f2a37; }
        .sp-strip__axis-end { transform:translateX(-100%) !important; font-weight:700; color:#1f2a37; }
        .sp-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:8px; font-size:.72rem; color:#6b7788; }
        .sp-legend__sw  { display:inline-block; width:14px; height:9px; border-radius:2px; margin-right:5px; vertical-align:middle; }
        .sp-legend__dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:5px; vertical-align:middle; }

        .sp-segments { display:flex; flex-direction:column; gap:8px; }
        .sp-seg { border:1px solid #e9edf3; border-left-width:4px; border-radius:8px; padding:10px 14px; background:#fff; }
        .sp-seg--active { border-left-color:#336aea; }
        .sp-seg--idle   { border-left-color:#f0c674; background:#fffdf7; }
        .sp-seg__head { display:flex; flex-wrap:wrap; align-items:baseline; gap:10px; font-size:.84rem; }
        .sp-seg__type { font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; font-weight:800; min-width:48px; }
        .sp-seg--active .sp-seg__type { color:#336aea; }
        .sp-seg--idle   .sp-seg__type { color:#b4770d; }
        .sp-seg__time { font-weight:700; color:#1f2a37; }
        .sp-seg__dur  { font-weight:600; color:#4b5563; }
        .sp-seg__meta { font-size:.78rem; }
        .sp-seg__refs { margin-top:6px; font-size:.8rem; display:flex; flex-direction:column; gap:3px; }
        .sp-seg__refs .sp-k { margin-right:6px; }
        .sp-seg__actions { margin-top:8px; }
        .sp-seg__actions summary { font-size:.76rem; color:#4258c9; cursor:pointer; font-weight:600; }
        .sp-seg__actions table { margin-top:6px; font-size:.78rem; }
        .sp-day__totals { margin-top:16px; padding-top:14px; border-top:1px solid #eef1f6; font-size:.84rem; }
        .sp-back { font-size:.74rem; }
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
                        <span class="text-muted">Check-ins, working time, idle time and packages registered, per staff member.</span>
                        <br>
                    </div>
                </div>
            </div>

            <div class="container-fluid pb-4">

                <!-- What the numbers mean. Stated on the page, not buried in a tooltip. -->
                <div class="alert alert-light border" style="font-size:.84rem;">
                    <b>Check-in</b> is the first package a staff member creates or edits that day — not the login.
                    <b>Check-out</b> is the end of their last recorded activity. Between the two, every minute with
                    keyboard, mouse or screen input in this app, or a recorded action, is <b>active</b>; a pause
                    longer than the idle setting is <b>idle</b>. Work done outside this app is not visible here and
                    reads as idle. A day with activity but no package action still counts, with its first activity
                    standing in as the check-in.
                    <?php if (!$presenceOk || !$settingsOk) : ?>
                        <br><b>Presence tracking is not deployed on this database yet</b> — apply
                        <code>sql/staff_productivity_v2.sql</code>. Until then idle is estimated from the gaps
                        between recorded actions.
                    <?php elseif ((int) $spSettings['beacon_enabled'] !== 1) : ?>
                        <br><b>Presence tracking is switched off</b>, so idle is estimated from the gaps between
                        recorded actions. Turn it on in the settings.
                    <?php endif; ?>
                    <?php if ($cutover) : ?>
                        Page activity is recorded from <b><?php echo $e(date('j M Y', strtotime($cutover))); ?></b>;
                        before that only package, consolidation and pickup actions are known, so earlier days show
                        no idle figure.
                    <?php else : ?>
                        The activity trail is not deployed on this database yet, so only package, consolidation and
                        pickup actions are known. Applying <code>sql/activity_log.sql</code> fixes that going forward.
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
                        <div class="col-md-4 mb-2">
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
                        <div class="col-md-4 mb-2 text-right">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cdpSpOpenSettings()" title="Idle threshold, check-in basis, presence tracking">
                                <i class="fa fa-sliders"></i> Settings
                            </button>
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
                    <div class="sp-settings-line" id="sp_settings_line"></div>
                </div>

                <!-- ── Headline ───────────────────────────────────────────── -->
                <div class="sp-kpis" id="sp_kpis"></div>

                <!-- ── Charts ─────────────────────────────────────────────── -->
                <div class="row">
                    <div class="col-lg-7">
                        <div class="sp-card">
                            <div class="sp-card__h">Active, Idle And Packages Created, By Day</div>
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

                <!-- ── Heatmap ────────────────────────────────────────────── -->
                <div class="sp-card">
                    <div class="sp-card__h">
                        Activity Heatmap
                        <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">active minutes in each hour, all staff shown · darker = busier</span>
                        <span class="sp-heat-note"></span>
                    </div>
                    <div class="sp-card__b" style="padding:8px 14px;"><div id="sp_chart_heat" style="min-height:160px;"></div></div>
                </div>

                <!-- ── Per staff ──────────────────────────────────────────── -->
                <div class="sp-card">
                    <div class="sp-card__h">
                        By Staff Member
                        <span class="text-muted" style="text-transform:none;letter-spacing:0;font-weight:400;">click a row for the day-by-day breakdown, then a day for its timeline</span>
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
                                    <th class="text-right">Active</th>
                                    <th class="text-right">Idle</th>
                                    <th class="text-right">Used</th>
                                    <th class="text-right">Days</th>
                                    <th class="text-right">Check-Ins</th>
                                    <th class="text-right">Avg/Day</th>
                                    <th class="text-right">Created</th>
                                    <th class="text-right">Per Hour</th>
                                    <th class="text-right">Edited</th>
                                    <th>First Check-In</th>
                                    <th>Last Check-Out</th>
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

    <!-- ── Drill-down: days, then one day's timeline ─────────────────────── -->
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

    <!-- ── Settings ──────────────────────────────────────────────────────── -->
    <div class="modal fade" id="spSettingsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Staff Productivity Settings</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="sps_error" style="display:none;font-size:.84rem;"></div>

                    <div class="form-group">
                        <label for="sps_scope" class="sp-k">Check-In Starts With</label>
                        <select class="form-control custom-select" id="sps_scope">
                            <option value="create_or_edit">The first package created or edited that day</option>
                            <option value="create_only">The first package created that day</option>
                        </select>
                        <small class="text-muted">A day without any package action uses its first recorded activity instead and is marked "first activity".</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="sps_idle" class="sp-k">Idle After (Minutes)</label>
                            <input type="number" class="form-control" id="sps_idle" min="1" max="120" step="1">
                            <small class="text-muted">With presence data: a pause longer than this, with no keyboard, mouse or screen input, is idle in full. Shorter pauses count as active.</small>
                        </div>
                        <div class="form-group col-6">
                            <label for="sps_gap" class="sp-k">Legacy Gap (Minutes)</label>
                            <input type="number" class="form-control" id="sps_gap" min="1" max="240" step="1">
                            <small class="text-muted">Days without presence data: a gap between two recorded actions longer than this ends a working stretch.</small>
                        </div>
                    </div>

                    <div class="form-row align-items-end">
                        <div class="form-group col-6">
                            <label for="sps_ping" class="sp-k">Presence Report Interval (Seconds)</label>
                            <input type="number" class="form-control" id="sps_ping" min="15" max="600" step="5">
                            <small class="text-muted">How often a staff member's browser reports which minutes had input. 60 is plenty.</small>
                        </div>
                        <div class="form-group col-6">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="sps_beacon">
                                <label class="custom-control-label" for="sps_beacon"><b>Presence Tracking</b></label>
                            </div>
                            <small class="text-muted">Off: nothing is recorded and idle falls back to the legacy gap. Records only "this minute had input" and the page name — no keystrokes, no content.</small>
                        </div>
                    </div>
                    <p class="sp-note mb-0">Changes apply to the whole report immediately, past days included, since the timeline is rebuilt from the recorded data every time. Every change is written to the Activity Log.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="sps_save" onclick="cdpSpSaveSettings()">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/staff_productivity.js') ?>"></script>
</body>

</html>
