<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

/*
 * E-mail newsletter: pick a template, design it by dragging blocks, send it.
 *
 * Builder:   dataJs/newsletter.js (blocks, canvas, inspector, preview, send)
 * Templates: dataJs/newsletter_templates.js
 * Styles:    assets/css_main_swiftlane/css/newsletter-builder.css
 * Server:    ajax/tools/newsletter_ajax.php + helpers/newsletter.php
 */

require_once('helpers/querys.php');
require_once('helpers/newsletter.php');
$userData = $user->cdp_getUserData();

$nlSettings = cdp_getSettingsCourier();
$nlPreset   = isset($_GET['email']) ? cdp_nlParseEmails((string) $_GET['email'])['valid'] : [];
$nlConfig   = [
    'ajax'      => 'ajax/tools/newsletter_ajax.php',
    'siteUrl'   => rtrim((string) ($nlSettings->site_url ?? ''), '/'),
    'siteName'  => (string) $core->site_name,
    'logo'      => $core->logo ? 'assets/' . $core->logo : '',
    'fromEmail' => (string) ($nlSettings->email_address ?? $core->site_email),
    'fromName'  => (string) (($nlSettings->smtp_names ?? '') !== '' ? $nlSettings->smtp_names : $core->site_name),
    'preset'    => $nlPreset,
    'tags'      => cdp_nlTags(),
    'audiences' => cdp_nlAudiences(),
];
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$nlActions = '<div class="nlb-head-actions">'
    . '<button type="button" class="btn btn-secondary" id="nlb_preview_btn"><iconify-icon icon="solar:eye-linear"></iconify-icon> Preview</button> '
    . '<button type="button" class="btn btn-secondary" id="nlb_save_btn"><iconify-icon icon="solar:diskette-linear"></iconify-icon> Save Design</button> '
    . '<button type="button" class="btn btn-primary" id="nlb_next_btn">Next: Send <iconify-icon icon="solar:arrow-right-linear"></iconify-icon></button>'
    . '</div>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Email Newsletter | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/newsletter-builder.css') ?>" rel="stylesheet" type="text/css" />
</head>

<body>

    <?php include 'views/inc/preloader.php'; ?>

    <div id="main-wrapper">

        <?php include 'views/inc/topbar.php'; ?>

        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('Email Newsletter', ['sub' => 'Design and send', 'actions' => $nlActions]); ?>
            <div class="container-fluid">
                <div id="resultados_ajax"></div>

                <!-- Steps -->
                <ul class="nav nav-tabs nlb-steps" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#nlb_step_template" role="tab" data-step="template"><span class="nlb-step-no">1</span> Choose Template</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#nlb_step_design" role="tab" data-step="design"><span class="nlb-step-no">2</span> Design</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#nlb_step_send" role="tab" data-step="send"><span class="nlb-step-no">3</span> Recipients &amp; Send</a></li>
                </ul>

                <div class="tab-content">

                    <!-- ── 1. Templates ─────────────────────────────────────────── -->
                    <div class="tab-pane fade show active" id="nlb_step_template" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Start From a Template</h4>
                                <p class="text-muted">Pick a layout to start from. Every block can be changed, moved or removed in the next step.</p>
                                <div class="nlb-gallery" id="nlb_gallery"></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Saved Designs</h4>
                                <div id="nlb_saved_empty" class="text-muted">No saved designs yet. Use <b>Save Design</b> while you work to keep a design for later.</div>
                                <div class="table-responsive" id="nlb_saved_wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Subject</th>
                                                <th>Last Changed</th>
                                                <th>Last Sent</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="nlb_saved_rows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 2. Designer ──────────────────────────────────────────── -->
                    <div class="tab-pane fade" id="nlb_step_design" role="tabpanel">
                        <div class="nlb-designer">

                            <!-- Blocks palette -->
                            <aside class="nlb-panel nlb-palette">
                                <div class="nlb-panel-title">Content Blocks</div>
                                <p class="nlb-hint">Drag a block into the email, or click it to add it below the selected block.</p>
                                <div class="nlb-tiles" id="nlb_tiles"></div>
                            </aside>

                            <!-- Canvas -->
                            <section class="nlb-stage">
                                <div class="nlb-stage-bar">
                                    <div class="btn-group nlb-seg" role="group" aria-label="Screen size">
                                        <button type="button" class="btn btn-secondary active" data-nlb-device="desktop" title="Desktop"><iconify-icon icon="solar:monitor-linear"></iconify-icon></button>
                                        <button type="button" class="btn btn-secondary" data-nlb-device="mobile" title="Phone"><iconify-icon icon="solar:smartphone-linear"></iconify-icon></button>
                                    </div>
                                    <span class="nlb-design-name" id="nlb_design_label">Untitled design</span>
                                    <div class="btn-group nlb-seg" role="group" aria-label="History">
                                        <button type="button" class="btn btn-secondary" id="nlb_undo" title="Undo (Ctrl+Z)"><iconify-icon icon="solar:undo-left-linear"></iconify-icon></button>
                                        <button type="button" class="btn btn-secondary" id="nlb_redo" title="Redo (Ctrl+Y)"><iconify-icon icon="solar:undo-right-linear"></iconify-icon></button>
                                    </div>
                                </div>
                                <div class="nlb-canvas-wrap" id="nlb_canvas_wrap">
                                    <iframe id="nlb_canvas" class="nlb-canvas" title="Newsletter canvas"></iframe>
                                </div>
                            </section>

                            <!-- Inspector -->
                            <aside class="nlb-panel nlb-inspector">
                                <ul class="nav nav-tabs nlb-insp-tabs" role="tablist">
                                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#nlb_insp_block" role="tab">Block</a></li>
                                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#nlb_insp_style" role="tab">Email Style</a></li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="nlb_insp_block" role="tabpanel">
                                        <div id="nlb_inspector"></div>
                                    </div>
                                    <div class="tab-pane fade" id="nlb_insp_style" role="tabpanel">
                                        <div id="nlb_style"></div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <!-- ── 3. Recipients & send ─────────────────────────────────── -->
                    <div class="tab-pane fade" id="nlb_step_send" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-7">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title">Campaign</h4>

                                        <div class="form-group">
                                            <label>From</label>
                                            <input class="form-control" type="text" readonly value="<?php echo $e($nlConfig['fromName'] . ' <' . $nlConfig['fromEmail'] . '>'); ?>">
                                            <small class="form-text text-muted">Set under Settings &rarr; E-mail (SMTP).</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="nlb_audience">To</label>
                                            <select class="form-control custom-select" id="nlb_audience">
                                                <?php foreach ($nlConfig['audiences'] as $k => $label) { ?>
                                                    <option value="<?php echo $e($k); ?>"<?php echo ($nlPreset ? $k === 'emails' : $k === 'subscribers') ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <div class="form-group" id="nlb_emails_group">
                                            <label for="nlb_emails">E-mail Addresses</label>
                                            <textarea class="form-control" id="nlb_emails" rows="3" placeholder="one@example.com, two@example.com"><?php echo $e(implode(', ', $nlPreset)); ?></textarea>
                                            <small class="form-text text-muted">Separate addresses with commas or new lines.</small>
                                        </div>

                                        <div class="nlb-reach" id="nlb_reach">Counting recipients&hellip;</div>

                                        <div class="form-group">
                                            <label for="nlb_subject">Subject Line</label>
                                            <input class="form-control" type="text" id="nlb_subject" maxlength="255" placeholder="What is this newsletter about?">
                                        </div>

                                        <div class="form-group">
                                            <label for="nlb_preheader">Preview Text</label>
                                            <input class="form-control" type="text" id="nlb_preheader" maxlength="255" placeholder="Shown after the subject in most inboxes">
                                            <small class="form-text text-muted">Personalise with tags such as [FIRST_NAME] in the subject, preview text and content.</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="nlb_test_emails">Send a Test</label>
                                            <div class="nlb-inline">
                                                <input class="form-control" type="text" id="nlb_test_emails" placeholder="you@example.com" value="<?php echo $e($userData->email ?? ''); ?>">
                                                <button type="button" class="btn btn-secondary" id="nlb_test_btn">Send Test</button>
                                            </div>
                                            <small class="form-text text-muted">Up to five addresses. The subject is marked [TEST].</small>
                                        </div>

                                        <h5 class="nlb-sub">Before You Send</h5>
                                        <ul class="nlb-checks" id="nlb_checks"></ul>

                                        <button type="button" class="btn btn-primary btn-lg nlb-send" id="nlb_send_btn" disabled>
                                            <iconify-icon icon="solar:plain-linear"></iconify-icon> Send Newsletter
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title">What They Will See</h4>
                                        <div class="nlb-inbox">
                                            <div class="nlb-inbox-from" id="nlb_inbox_from"><?php echo $e($nlConfig['fromName']); ?></div>
                                            <div class="nlb-inbox-subject" id="nlb_inbox_subject">(no subject)</div>
                                            <div class="nlb-inbox-pre" id="nlb_inbox_pre"></div>
                                        </div>
                                        <div class="nlb-mini">
                                            <iframe id="nlb_mini" sandbox="" title="Newsletter preview"></iframe>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-block" id="nlb_preview_btn2"><iconify-icon icon="solar:eye-linear"></iconify-icon> Full Preview</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Recent Campaigns</h4>
                                <div id="nlb_hist_empty" class="text-muted">No newsletters sent from this page yet.</div>
                                <div class="table-responsive" id="nlb_hist_wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Sent</th>
                                                <th>Subject</th>
                                                <th>Audience</th>
                                                <th>By</th>
                                                <th class="text-right">Delivered</th>
                                                <th class="text-right">Failed</th>
                                                <th>Batch</th>
                                            </tr>
                                        </thead>
                                        <tbody id="nlb_hist_rows"></tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Every message is recorded per recipient in Settings &rarr; Message Logs; search there by batch.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <!-- Preview -->
    <div class="modal fade" id="nlb_preview_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview</h5>
                    <div class="btn-group nlb-seg ml-auto mr-3" role="group">
                        <button type="button" class="btn btn-secondary active" data-nlb-preview="desktop"><iconify-icon icon="solar:monitor-linear"></iconify-icon> Desktop</button>
                        <button type="button" class="btn btn-secondary" data-nlb-preview="mobile"><iconify-icon icon="solar:smartphone-linear"></iconify-icon> Phone</button>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body nlb-preview-body">
                    <div class="nlb-inbox nlb-inbox-wide">
                        <div class="nlb-inbox-from"><?php echo $e($nlConfig['fromName']); ?></div>
                        <div class="nlb-inbox-subject" id="nlb_pv_subject"></div>
                        <div class="nlb-inbox-pre" id="nlb_pv_pre"></div>
                    </div>
                    <div class="nlb-preview-frame" id="nlb_pv_frame_wrap">
                        <iframe id="nlb_pv_frame" sandbox="allow-popups allow-popups-to-escape-sandbox" title="Newsletter preview"></iframe>
                    </div>
                    <small class="text-muted">Tags such as [FIRST_NAME] are filled in for each recipient when the newsletter is sent.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="nlb_pv_send">Continue to Send</button>
                </div>
            </div>
        </div>
    </div>

    <input type="file" id="nlb_file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>

    <script>
        window.NLB_CONFIG = <?php echo json_encode($nlConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="<?= cdp_asset('dataJs/newsletter_templates.js') ?>"></script>
    <script src="<?= cdp_asset('dataJs/newsletter.js') ?>"></script>

</body>

</html>
