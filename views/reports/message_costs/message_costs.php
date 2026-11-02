<?php
// *************************************************************************
// * Message Costs — SMS, WhatsApp and e-mail sent, and what they cost.     *
// *                                                                       *
// * Filters (date range, one month or one day) + cost tiles per channel +  *
// * cost over time + the per-period breakdown + cost by origin. Unit       *
// * prices are entered in the Unit Costs modal and kept with a history, so *
// * a price change only affects messages from its date on.                 *
// * Data: cdb_message_log; prices: helpers/message_cost.php.               *
// *************************************************************************

require_once('helpers/message_log_query.php');
require_once('helpers/message_cost.php');

$userData = $user->cdp_getUserData();   // the top bar and the sidebar are built from it
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$tableReady = cdp_msgTableReady();
$costReady  = cdp_mcEnsureTables();
$canEdit    = cdp_mcCanEdit($user);
$canExport  = $user->cdp_hasPermission('export_message_logs');
$mcSettings = cdp_mcSettings();

$sourceOptions = cdp_msgSources();
if ($tableReady) {
    $db = new Conexion;
    $db->cdp_query("SELECT source, MAX(source_label) AS label FROM cdb_message_log GROUP BY source LIMIT 100");
    $db->cdp_execute();
    foreach ((array) $db->cdp_registros() as $s) {
        if (!isset($sourceOptions[$s->source])) {
            $sourceOptions[$s->source] = $s->label ?: ucwords(str_replace('_', ' ', $s->source));
        }
    }
}
asort($sourceOptions);

$today      = substr(cdp_msgNow(), 0, 10);
$monthStart = substr($today, 0, 7) . '-01';

$actions = '<a href="message_logs.php" class="btn btn-secondary btn-sm"><i class="fa fa-list"></i> Message Logs</a> '
         . '<button type="button" class="btn btn-primary btn-sm" onclick="cdpMcOpenRates()"><i class="fa fa-tag"></i> Unit Costs</button>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Message Costs | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .mc-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: var(--slate-400); margin-bottom: 4px; }
        .mc-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .mc-quick button { border: 1px solid var(--fill-100); background: var(--white); border-radius: 6px; padding: 5px 12px; font-size: .78rem; font-weight: 600; color: var(--ink-700); cursor: pointer; }
        .mc-quick button.is-on { background: var(--ink-900); border-color: var(--ink-900); color: var(--white); }
        .mc-mode { display: none; }
        .mc-mode.is-on { display: flex; }
        .mc-rates-line { font-size: .8rem; color: var(--slate-400); margin-top: 6px; }
        .mc-rates-line b { color: var(--ink-800); }

        .mc-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .mc-head .card-title { margin-bottom: 0; }
        .mc-note { font-size: .78rem; color: var(--slate-400); }
        .mc-toggle .btn.is-on { background: var(--ink-900); border-color: var(--ink-900); color: var(--white); }

        .mc-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: var(--slate-400); border-top: 0; white-space: nowrap; }
        .mc-table td { white-space: nowrap; }
        .mc-table tfoot td { font-weight: 700; color: var(--ink-900); border-top: 2px solid var(--line-200); }
        .mc-cost { font-weight: 600; color: var(--ink-900); }
        .mc-sub { display: block; font-size: .72rem; color: var(--slate-400); }
        .mc-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; vertical-align: middle; background: var(--c, var(--slate-400)); }
        .mc-empty { color: var(--slate-400); font-size: .87rem; padding: 26px 0; text-align: center; }
        .mc-row { cursor: pointer; }
        .mc-row:hover { background: var(--page-050); }

        .mc-rate-cur { font-size: .74rem; color: var(--slate-400); margin-top: 3px; }
        .mc-history td, .mc-history th { font-size: .8rem; }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Message Costs', ['icon' => 'solar:wallet-money-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>

                <?php if (!$tableReady) : ?>
                    <div class="alert alert-warning">
                        <b>The message log is not set up yet.</b>
                        Apply <code>sql/message_log.sql</code> to this database to create <code>cdb_message_log</code>.
                        Messages are counted from that log, so there is nothing to count until then.
                    </div>
                <?php endif; ?>
                <?php if (!$costReady) : ?>
                    <div class="alert alert-warning">
                        <b>The price tables could not be created.</b>
                        The database user needs the CREATE privilege once, so <code>cdb_message_cost_rates</code> and
                        <code>cdb_message_cost_settings</code> can be added. Counts still show; costs read as zero.
                    </div>
                <?php endif; ?>
                <div id="mc_unpriced" class="alert alert-info" style="display:none;"></div>

                <!-- ── Filters ────────────────────────────────────────────── -->
                <div class="card">
                    <div class="card-body">
                        <div class="mc-quick" id="mc_quick">
                            <button type="button" data-range="today">Today</button>
                            <button type="button" data-range="yesterday">Yesterday</button>
                            <button type="button" data-range="7">Last 7 Days</button>
                            <button type="button" data-range="month" class="is-on">This Month</button>
                            <button type="button" data-range="lastmonth">Last Month</button>
                            <button type="button" data-range="year">This Year</button>
                            <button type="button" data-range="all">All Time</button>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="col-md-6 col-lg-2 mb-2">
                                <label class="mc-label" for="mc_mode">Show</label>
                                <select class="form-control custom-select" id="mc_mode">
                                    <option value="range">A Date Range</option>
                                    <option value="month">One Month</option>
                                    <option value="day">One Day</option>
                                </select>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-2 mc-mode is-on" data-mode="range">
                                <div class="form-row w-100">
                                    <div class="col-md-6 swl-keep">
                                        <label class="mc-label" for="mc_from">From</label>
                                        <input type="date" class="form-control" id="mc_from" value="<?php echo $e($monthStart); ?>">
                                    </div>
                                    <div class="col-md-6 swl-keep">
                                        <label class="mc-label" for="mc_to">To</label>
                                        <input type="date" class="form-control" id="mc_to" value="<?php echo $e($today); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-2 mc-mode" data-mode="month">
                                <div class="w-100">
                                    <label class="mc-label" for="mc_month">Month</label>
                                    <input type="month" class="form-control" id="mc_month" value="<?php echo $e(substr($today, 0, 7)); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-2 mc-mode" data-mode="day">
                                <div class="w-100">
                                    <label class="mc-label" for="mc_day">Day</label>
                                    <input type="date" class="form-control" id="mc_day" value="<?php echo $e($today); ?>">
                                </div>
                            </div>

                            <div class="col-md-4 col-lg-2 mb-2">
                                <label class="mc-label" for="mc_channel">Channel</label>
                                <select class="form-control custom-select" id="mc_channel">
                                    <option value="">All channels</option>
                                    <?php foreach (cdp_mcChannels() as $ch) : ?>
                                        <option value="<?php echo $e($ch); ?>"><?php echo $e(cdp_mlChannelLabel($ch)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2 mb-2">
                                <label class="mc-label" for="mc_source">Origin</label>
                                <select class="form-control custom-select" id="mc_source">
                                    <option value="">All origins</option>
                                    <?php foreach ($sourceOptions as $k => $label) : ?>
                                        <option value="<?php echo $e($k); ?>"><?php echo $e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2 mb-2">
                                <label class="mc-label" for="mc_group">Totals Per</label>
                                <select class="form-control custom-select" id="mc_group">
                                    <option value="auto">Automatic</option>
                                    <option value="day">Day</option>
                                    <option value="month">Month</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap:10px;">
                            <div class="mc-rates-line" id="mc_rates_line"></div>
                            <div>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="cdpMcReset()"><i class="fa fa-undo"></i> Reset</button>
                                <?php if ($canExport) : ?>
                                <button type="button" class="btn btn-outline-dark btn-sm" onclick="cdpMcExport()"><i class="fa fa-download"></i> Export CSV</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="cdpMcLoad()"><i class="fa fa-search"></i> Apply</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Headline ───────────────────────────────────────────── -->
                <div class="row" id="mc_kpis"></div>

                <!-- ── Over time ──────────────────────────────────────────── -->
                <div class="card">
                    <div class="card-body">
                        <div class="mc-head">
                            <h4 class="card-title">Over Time <span class="mc-note" id="mc_range_note"></span></h4>
                            <div class="btn-group btn-group-sm mc-toggle" role="group" id="mc_chart_mode">
                                <button type="button" class="btn btn-secondary is-on" data-mode="cost">Cost</button>
                                <button type="button" class="btn btn-secondary" data-mode="sent">Messages</button>
                            </div>
                        </div>
                        <div id="mc_chart" style="min-height:280px;"></div>
                    </div>
                </div>

                <!-- ── Breakdown ──────────────────────────────────────────── -->
                <div class="card">
                    <div class="card-body">
                        <div class="mc-head">
                            <h4 class="card-title" id="mc_breakdown_title">By Day</h4>
                            <span class="mc-note">Only messages the provider accepted are charged. Failed and skipped messages are counted but cost nothing.</span>
                        </div>
                        <div id="mc_loader" style="display:none;" class="text-center my-3"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                        <div class="table-responsive">
                            <table class="table table-hover mc-table mb-0">
                                <thead id="mc_breakdown_head"></thead>
                                <tbody id="mc_breakdown"></tbody>
                                <tfoot id="mc_breakdown_foot"></tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ── By origin ──────────────────────────────────────────── -->
                <div class="card">
                    <div class="card-body">
                        <div class="mc-head">
                            <h4 class="card-title">By Origin</h4>
                            <span class="mc-note">What in the system sent the messages. Click a row to filter the page to it.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mc-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Origin</th>
                                        <th class="text-right">SMS</th>
                                        <th class="text-right">WhatsApp</th>
                                        <th class="text-right">E-mail</th>
                                        <th class="text-right">Sent</th>
                                        <th class="text-right">Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="mc_sources"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- ── Unit costs ─────────────────────────────────────────────────────── -->
    <div class="modal fade" id="mcRatesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unit Costs</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="mcr_error" style="display:none;"></div>
                    <?php if (!$canEdit) : ?>
                        <div class="alert alert-light border">Only an Administrator can change prices. These are the prices in use.</div>
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="mc-label" for="mcr_sms">Cost Per SMS</label>
                            <input type="number" class="form-control" id="mcr_sms" min="0" step="any" placeholder="0.00" <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <div class="mc-rate-cur" id="mcr_sms_cur"></div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="mc-label" for="mcr_whatsapp">Cost Per WhatsApp Message</label>
                            <input type="number" class="form-control" id="mcr_whatsapp" min="0" step="any" placeholder="0.00" <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <div class="mc-rate-cur" id="mcr_whatsapp_cur"></div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="mc-label" for="mcr_email">Cost Per E-mail</label>
                            <input type="number" class="form-control" id="mcr_email" min="0" step="any" placeholder="0.00" <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <div class="mc-rate-cur" id="mcr_email_cur"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="mc-label" for="mcr_from">New Prices Apply From</label>
                            <input type="date" class="form-control" id="mcr_from" value="<?php echo $e($today); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <small class="text-muted">Messages sent before this day keep the price they had. The first price you enter for a channel also covers everything sent before it.</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="mc-label" for="mcr_currency">Currency</label>
                            <input type="text" class="form-control" id="mcr_currency" maxlength="12" value="<?php echo $e($mcSettings['currency']); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <small class="text-muted">Shown beside every amount, e.g. GHS or USD.</small>
                        </div>
                        <div class="form-group col-md-4">
                            <div class="custom-control custom-switch mt-md-4">
                                <input type="checkbox" class="custom-control-input" id="mcr_parts" <?php echo (int) $mcSettings['sms_parts'] === 1 ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>>
                                <label class="custom-control-label" for="mcr_parts"><b>Charge Long SMS Per Part</b></label>
                            </div>
                            <small class="text-muted">On: a text over 160 characters counts as several SMS (153 characters each), the way most SMS providers bill.</small>
                        </div>
                    </div>

                    <h6 class="mc-label mt-2">Price History</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mc-history mb-0">
                            <thead>
                                <tr>
                                    <th>Channel</th>
                                    <th>From</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th>Entered</th>
                                    <?php if ($canEdit) : ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="mcr_history"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?php echo $canEdit ? 'Cancel' : 'Close'; ?></button>
                    <?php if ($canEdit) : ?>
                    <button type="button" class="btn btn-primary btn-sm" id="mcr_save" onclick="cdpMcSaveRates()">Save Costs</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        var MC_CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
        var MC_TODAY = <?php echo json_encode($today); ?>;
        var MC_CHANNELS = <?php echo json_encode(array_map(function ($ch) {
            return ['key' => $ch, 'label' => cdp_mlChannelLabel($ch), 'color' => cdp_mlChannelColor($ch)];
        }, cdp_mcChannels())); ?>;
    </script>
    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/message_costs.js') ?>"></script>
</body>

</html>
