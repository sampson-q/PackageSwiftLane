<?php
// *************************************************************************
// * Message Logs — every WhatsApp / e-mail / SMS the system sent.          *
// *                                                                       *
// * Filters + stat tiles + per-channel timeline + breakdowns by origin and *
// * by sender + the message table. Row click opens the full message with   *
// * the provider's response. Data: cdb_message_log (sql/message_log.sql).  *
// *************************************************************************

require_once('helpers/message_log_query.php');

$db = new Conexion;
$e  = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$tableReady = cdp_msgTableReady();

$senderOptions = [];
$sourceOptions = [];
$templateOptions = [];
if ($tableReady) {
    $db->cdp_query("SELECT sent_by, MAX(sent_by_name) AS n, MAX(sent_by_role) AS r, COUNT(*) AS c
                    FROM cdb_message_log GROUP BY sent_by ORDER BY n ASC LIMIT 300");
    $db->cdp_execute();
    $senderOptions = (array) $db->cdp_registros();

    $db->cdp_query("SELECT source, MAX(source_label) AS label, COUNT(*) AS c
                    FROM cdb_message_log GROUP BY source ORDER BY label ASC LIMIT 100");
    $db->cdp_execute();
    $sourceOptions = (array) $db->cdp_registros();
}
$knownSources = cdp_msgSources();
foreach ($sourceOptions as $s) {
    if (!isset($knownSources[$s->source])) {
        $knownSources[$s->source] = $s->label ?: ucwords(str_replace('_', ' ', $s->source));
    }
}
asort($knownSources);

$db->cdp_query("SELECT id, title FROM whatsapp_templates ORDER BY id");
$db->cdp_execute();
$templateOptions = (array) $db->cdp_registros();

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
    <title>Message Logs | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .ml-filters { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; padding: 18px 20px; margin-bottom: 18px; }
        .ml-filters label { font-size: .7rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: #8a94a6; margin-bottom: 4px; }
        .ml-filters .form-control, .ml-filters .custom-select { font-size: .86rem; }
        .ml-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .ml-quick button { border: 1px solid #e0e5ee; background: #fff; border-radius: 6px; padding: 5px 12px; font-size: .78rem; font-weight: 600; color: #6b7788; cursor: pointer; }
        .ml-quick button.is-on { background: #1f2a37; border-color: #1f2a37; color: #fff; }

        .ml-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .ml-kpi { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; padding: 16px 18px; position: relative; overflow: hidden; cursor: default; }
        .ml-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--c, #d7dce5); }
        .ml-kpi__k { font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #8a94a6; }
        .ml-kpi__v { font-size: 1.7rem; font-weight: 700; letter-spacing: -.02em; color: #1f2a37; line-height: 1.15; margin-top: 5px; }
        .ml-kpi__s { font-size: .74rem; color: #99a2b1; margin-top: 2px; }
        .ml-kpi[data-filter] { cursor: pointer; }
        .ml-kpi[data-filter]:hover { border-color: #c9d2e0; }

        .ml-card { background: #fff; border: 1px solid #e9edf3; border-radius: 10px; margin-bottom: 18px; }
        .ml-card__h { padding: 14px 18px; border-bottom: 1px solid #eef1f6; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #8a94a6; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .ml-card__h .note { text-transform: none; letter-spacing: 0; font-weight: 400; color: #99a2b1; }
        .ml-card__b { padding: 16px 18px; }

        .ml-bars { display: flex; flex-direction: column; gap: 10px; }
        .ml-bar { cursor: pointer; }
        .ml-bar__top { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: 3px; }
        .ml-bar__name { font-weight: 600; color: #3b4655; }
        .ml-bar__n { color: #8a94a6; font-weight: 600; }
        .ml-bar__track { height: 6px; background: #eef1f6; border-radius: 4px; overflow: hidden; display: flex; }
        .ml-bar__fill { height: 100%; background: var(--c, #336aea); }
        .ml-empty { color: #99a2b1; font-size: .85rem; padding: 18px 0; text-align: center; }

        .ml-table { font-size: .84rem; }
        .ml-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: #99a2b1; border-top: 0; white-space: nowrap; }
        .ml-row { cursor: pointer; }
        .ml-row:hover { background: #f7f9fc; }
        .ml-row--failed { background: #fff7f8; }
        .ml-row--skipped { background: #fffaf2; }
        .ml-name { font-weight: 600; color: #1f2a37; }
        .ml-subject { font-weight: 600; color: #1f2a37; }
        .ml-preview { font-size: .8rem; }
        .ml-entity { color: #4258c9; font-weight: 600; }
        .ml-pill { display: inline-block; padding: .15rem .55rem; border-radius: 1rem; font-size: .68rem; font-weight: 700; color: var(--c, #6b7788); background: color-mix(in srgb, var(--c, #6b7788) 14%, #fff); border: 1px solid color-mix(in srgb, var(--c, #6b7788) 35%, #fff); white-space: nowrap; }

        .ml-detail__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 20px; margin-bottom: 18px; }
        .ml-detail__wide { grid-column: 1 / -1; }
        .ml-detail__grid > div { display: flex; flex-direction: column; }
        .ml-k { font-size: .66rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #99a2b1; }
        .ml-v { font-size: .88rem; color: #1f2a37; word-break: break-word; }
        .ml-detail__h { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #8a94a6; font-weight: 700; margin: 18px 0 8px; }
        .ml-body { white-space: pre-wrap; word-break: break-word; background: #f7f9fc; border: 1px solid #eef1f6; border-radius: 8px; padding: 14px; font-family: inherit; font-size: .88rem; color: #1f2a37; max-height: 420px; overflow: auto; margin: 0; }
        .ml-body--mono { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: .78rem; }
        .ml-body-frame { width: 100%; height: 380px; border: 1px solid #eef1f6; border-radius: 8px; background: #fff; }
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
                        <h4 class="page-title"><iconify-icon icon="solar:chat-round-dots-linear"></iconify-icon> Message Logs</h4>
                        <span class="text-muted">Every WhatsApp, e-mail and SMS the system sent — to whom, by whom, when, and whether it was delivered to the provider.</span>
                        <br>
                    </div>
                </div>
            </div>

            <div class="container-fluid pb-4">

                <?php if (!$tableReady) : ?>
                    <div class="alert alert-warning">
                        <b>The message log is not set up yet.</b>
                        Apply <code>sql/message_log.sql</code> to this database to create
                        <code>cdb_message_log</code>. Messages keep going out normally, but nothing is being recorded until then.
                    </div>
                <?php endif; ?>

                <!-- ── Filters ────────────────────────────────────────────── -->
                <div class="ml-filters">
                    <div class="ml-quick" id="ml_quick">
                        <button type="button" data-range="today">Today</button>
                        <button type="button" data-range="7">Last 7 Days</button>
                        <button type="button" data-range="30" class="is-on">Last 30 Days</button>
                        <button type="button" data-range="month">This Month</button>
                        <button type="button" data-range="all">All Time</button>
                    </div>

                    <div class="form-row">
                        <div class="col-md-2 mb-2">
                            <label for="ml_from">From</label>
                            <input type="date" class="form-control" id="ml_from" value="<?php echo $e($defaultFrom); ?>">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="ml_to">To</label>
                            <input type="date" class="form-control" id="ml_to" value="<?php echo $e($defaultTo); ?>">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="ml_channel">Channel</label>
                            <select class="form-control custom-select" id="ml_channel">
                                <option value="">All channels</option>
                                <?php foreach (cdp_msgChannels() as $k => $c) : ?>
                                    <option value="<?php echo $e($k); ?>"><?php echo $e($c['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="ml_status">Status</label>
                            <select class="form-control custom-select" id="ml_status">
                                <option value="">All statuses</option>
                                <?php foreach (cdp_msgStatuses() as $k => $s) : ?>
                                    <option value="<?php echo $e($k); ?>"><?php echo $e($s['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="ml_search">Search</label>
                            <input type="text" class="form-control" id="ml_search" placeholder="Recipient, phone, e-mail, tracking number, subject, text…">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-3 mb-2">
                            <label for="ml_source">Origin</label>
                            <select class="form-control custom-select" id="ml_source">
                                <option value="">All origins</option>
                                <?php foreach ($knownSources as $k => $label) : ?>
                                    <option value="<?php echo $e($k); ?>"><?php echo $e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="ml_sent_by">Sent By</label>
                            <select class="form-control custom-select" id="ml_sent_by">
                                <option value="0">Anyone</option>
                                <option value="-1">System (automatic)</option>
                                <?php foreach ($senderOptions as $s) : if ((int) $s->sent_by <= 0) continue; ?>
                                    <option value="<?php echo (int) $s->sent_by; ?>">
                                        <?php echo $e($s->n ?: ('User #' . $s->sent_by)); ?><?php echo $s->r ? ' (' . $e($s->r) . ')' : ''; ?> — <?php echo number_format((int) $s->c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="ml_template">WhatsApp Template</label>
                            <select class="form-control custom-select" id="ml_template">
                                <option value="0">Any template</option>
                                <?php foreach ($templateOptions as $t) : ?>
                                    <option value="<?php echo (int) $t->id; ?>">#<?php echo (int) $t->id; ?> — <?php echo $e($t->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="ml_batch">Batch</label>
                            <input type="text" class="form-control" id="ml_batch" placeholder="Batch id (from a message's detail)">
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap:10px;">
                        <select class="form-control custom-select" id="ml_per_page" style="width:auto;">
                            <option value="25">25 rows</option>
                            <option value="50" selected>50 rows</option>
                            <option value="100">100 rows</option>
                            <option value="200">200 rows</option>
                        </select>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="cdpMlReset()"><i class="fa fa-undo"></i> Reset</button>
                            <?php if ($user->cdp_hasPermission('export_message_logs')) : ?>
                            <button type="button" class="btn btn-outline-dark btn-sm" onclick="cdpMlExport()"><i class="fa fa-download"></i> Export CSV</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="cdpMlGo(1)"><i class="fa fa-search"></i> Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- ── Headline ───────────────────────────────────────────── -->
                <div class="ml-kpis" id="ml_kpis"></div>

                <!-- ── Charts ─────────────────────────────────────────────── -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="ml-card">
                            <div class="ml-card__h">Messages Over Time <span id="ml_range_note" class="note"></span></div>
                            <div class="ml-card__b"><div id="ml_chart_time" style="min-height:250px;"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="ml-card">
                            <div class="ml-card__h">By Channel <span class="note">sent · failed · skipped</span></div>
                            <div class="ml-card__b"><div class="ml-bars" id="ml_channels"></div></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="ml-card">
                            <div class="ml-card__h">By Origin <span class="note">click to filter</span></div>
                            <div class="ml-card__b"><div class="ml-bars" id="ml_sources"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ml-card">
                            <div class="ml-card__h">Why Messages Did Not Go Out <span class="note">top reasons</span></div>
                            <div class="ml-card__b"><div class="ml-bars" id="ml_failures"></div></div>
                        </div>
                    </div>
                </div>

                <!-- ── Who sent what ─────────────────────────────────────── -->
                <div class="ml-card">
                    <div class="ml-card__h">Who Sent Messages <span class="note">click a row to filter the log to that person</span></div>
                    <div class="table-responsive">
                        <table class="table table-hover ml-table mb-0">
                            <thead>
                                <tr>
                                    <th>Sent By</th>
                                    <th>Role</th>
                                    <th class="text-right">WhatsApp</th>
                                    <th class="text-right">E-mail</th>
                                    <th class="text-right">SMS</th>
                                    <th class="text-right">Sent</th>
                                    <th class="text-right">Failed</th>
                                    <th class="text-right">Skipped</th>
                                    <th class="text-right">Total</th>
                                    <th>Last Message</th>
                                </tr>
                            </thead>
                            <tbody id="ml_senders"></tbody>
                        </table>
                    </div>
                </div>

                <!-- ── The messages ──────────────────────────────────────── -->
                <div class="ml-card">
                    <div class="ml-card__h">Messages <span class="note">click a row for the full message and the provider's response</span></div>
                    <div class="ml-card__b">
                        <div id="ml_loader" style="display:none;" class="text-center my-3"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                        <div id="ml_rows"></div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- ── Message detail ─────────────────────────────────────────────────── -->
    <div class="modal fade" id="mlDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="ml_detail_body">
                    <div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                </div>
            </div>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/apexcharts.min.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/message_logs.js') ?>"></script>
</body>

</html>
