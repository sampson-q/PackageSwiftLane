<?php
// *************************************************************************
// * AI Assistant settings (Tools > AI Assistant).                         *
// *                                                                       *
// * Super admins: provider (Claude / OpenAI / OpenRouter), API key, model,  *
// * who may use it, and house rules.                                      *
// * Administrators: what the assistant knows, what people ask, and the     *
// * page ideas drawn from those questions.                                 *
// * Data: ajax/ai_assistant/settings_ajax.php (dataJs/ai_assistant_settings.js) *
// *************************************************************************

require_once __DIR__ . '/../../helpers/ai_assistant.php';

if (!cdp_aiCanManage($user) && !cdp_aiCanSeeInsights($user)) {
    cdp_redirect_to("error403.php");
}

$userData    = $user->cdp_getUserData();
$e           = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$canManage   = cdp_aiCanManage($user);
$canInsights = cdp_aiCanSeeInsights($user);
$providers   = cdp_aiProviders();
$tablesReady = cdp_aiEnsureTables();

$actions = '<a href="ai_assistant.php" class="btn btn-primary btn-sm"><iconify-icon icon="solar:magic-stick-3-linear"></iconify-icon> Open Assistant</a>';
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>AI Assistant Settings | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('AI Assistant', ['sub' => 'Settings', 'icon' => 'solar:magic-stick-3-linear', 'actions' => $actions]); ?>

            <div class="email-app">
                <?php include 'views/inc/left_part_menu.php'; ?>

                <div class="right-part mail-list bg-white mt-3">
                    <div class="card-body">
                        <div id="resultados_ajax"></div>

                        <?php if (!$tablesReady) : ?>
                            <div class="alert alert-warning">
                                <b>The assistant's tables could not be created.</b> The database user needs the CREATE privilege once,
                                so <code>cdb_ai_settings</code>, <code>cdb_ai_conversations</code>, <code>cdb_ai_messages</code>,
                                <code>cdb_ai_questions</code>, <code>cdb_ai_knowledge</code> and <code>cdb_ai_page_ideas</code> can be added.
                            </div>
                        <?php endif; ?>

                        <?php if ($canManage) : ?>
                        <!-- ── Connection ─────────────────────────────────────── -->
                        <form id="ai_settings_form" autocomplete="off">
                            <h4 class="card-title"><iconify-icon icon="solar:plug-circle-linear"></iconify-icon> Connection</h4>
                            <div class="swl-ai-status" id="ai_status"></div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="enabled" id="ai_enabled" value="1">
                                    <label class="custom-control-label" for="ai_enabled">Switch the assistant on</label>
                                </div>
                                <small class="text-muted d-block">While it is off, only super admins can open the assistant page.</small>
                            </div>

                            <div class="form-group">
                                <label>Provider</label>
                                <div class="swl-ai-providers">
                                    <?php foreach ($providers as $key => $p) : ?>
                                        <label class="swl-ai-provider">
                                            <input type="radio" name="provider" value="<?php echo $e($key); ?>">
                                            <span class="swl-ai-provider-name"><?php echo $e($p['label']); ?></span>
                                            <span class="swl-ai-provider-key" data-key-state="<?php echo $e($key); ?>">No key</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php foreach ($providers as $key => $p) : ?>
                                <div class="form-group swl-ai-keyrow" data-provider="<?php echo $e($key); ?>">
                                    <label for="ai_key_<?php echo $e($key); ?>"><?php echo $e($p['label']); ?> API Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="ai_key_<?php echo $e($key); ?>" name="key_<?php echo $e($key); ?>"
                                            placeholder="<?php echo $e($p['key_hint']); ?>" autocomplete="new-password" spellcheck="false">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-secondary ai-key-clear" data-provider="<?php echo $e($key); ?>">Remove Key</button>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Stored on this server only and never shown again in full.
                                        <a href="<?php echo $e($p['key_url']); ?>" target="_blank" rel="noopener noreferrer">Get a key</a>.
                                    </small>
                                </div>
                            <?php endforeach; ?>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="ai_model">Model</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="ai_model" name="model" list="ai_model_list" spellcheck="false" placeholder="claude-opus-5-5">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-secondary" id="ai_models_btn">Load Models</button>
                                            </div>
                                        </div>
                                        <datalist id="ai_model_list"></datalist>
                                        <small class="text-muted" id="ai_model_hint">Pick a suggestion, load the provider's current list, or type any model id.</small>
                                    </div>
                                </div>
                                <div class="col-md-4 swl-ai-claude-only">
                                    <div class="form-group">
                                        <label for="ai_effort">Thinking Effort</label>
                                        <select class="form-control custom-select" id="ai_effort" name="effort">
                                            <option value="low">Low (fastest)</option>
                                            <option value="medium">Medium (default)</option>
                                            <option value="high">High (deeper answers)</option>
                                            <option value="xhigh">Extra high</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ai_audience">Who Can Use It</label>
                                        <select class="form-control custom-select" id="ai_audience" name="audience">
                                            <option value="all">Everyone who signs in (each sees only their own data)</option>
                                            <option value="staff">Staff and administrators only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ai_rounds">Lookups Per Question</label>
                                        <input type="number" class="form-control" id="ai_rounds" name="max_rounds" min="2" max="15">
                                        <small class="text-muted">How many rounds of lookups the assistant may make before it must answer.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="suggest_pages" id="ai_suggest" value="1">
                                    <label class="custom-control-label" for="ai_suggest">Let the assistant suggest new pages when a question has no good page</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="ai_instructions">House Rules</label>
                                <textarea class="form-control" id="ai_instructions" name="instructions" rows="3" maxlength="4000"
                                    placeholder="e.g. Amounts are in GHS. Call consolidations 'containers'. Keep answers short for customers."></textarea>
                                <small class="text-muted">Added to every conversation.</small>
                            </div>

                            <div class="swl-ai-form-actions">
                                <button type="button" class="btn btn-secondary" id="ai_test_btn"><iconify-icon icon="solar:plug-circle-linear"></iconify-icon> Test Connection</button>
                                <button type="submit" class="btn btn-primary" id="ai_save_btn">Save Settings</button>
                            </div>
                        </form>
                        <hr>
                        <?php endif; ?>

                        <?php if ($canInsights) : ?>
                        <!-- ── What the assistant knows ───────────────────────── -->
                        <h4 class="card-title"><iconify-icon icon="solar:notebook-linear"></iconify-icon> What The Assistant Knows</h4>
                        <p class="text-muted swl-ai-help">
                            Facts about your business that every conversation should know: rules, definitions, how you work.
                            Administrators can also tell the assistant in a chat to remember something.
                        </p>
                        <ul class="swl-ai-notes" id="ai_notes"></ul>
                        <form id="ai_note_form" class="swl-ai-note-form">
                            <textarea class="form-control" id="ai_note" rows="2" maxlength="2000"
                                placeholder="e.g. Sea shipments to Tema take 6 to 8 weeks. 'Not Shipped' means the customer still has to pay customs."></textarea>
                            <button type="submit" class="btn btn-secondary">Add Fact</button>
                        </form>
                        <hr>

                        <!-- ── What people ask ────────────────────────────────── -->
                        <h4 class="card-title"><iconify-icon icon="solar:chat-square-like-linear"></iconify-icon> What People Ask <small>last 30 days</small></h4>
                        <div class="swl-ai-stats" id="ai_stats"></div>
                        <div class="row">
                            <div class="col-lg-6">
                                <h5 class="swl-ai-h">By Area</h5>
                                <div id="ai_by_area"></div>
                            </div>
                            <div class="col-lg-6">
                                <h5 class="swl-ai-h">By Role</h5>
                                <div id="ai_by_role"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <h5 class="swl-ai-h">Recent Questions</h5>
                                <ul class="swl-ai-qlist" id="ai_recent"></ul>
                            </div>
                            <div class="col-lg-6">
                                <h5 class="swl-ai-h">Not Answered</h5>
                                <ul class="swl-ai-qlist" id="ai_unanswered"></ul>
                            </div>
                        </div>
                        <hr>

                        <!-- ── Page ideas ─────────────────────────────────────── -->
                        <div class="swl-ai-ideas-head">
                            <h4 class="card-title"><iconify-icon icon="solar:lightbulb-bolt-linear"></iconify-icon> Suggested Pages</h4>
                            <button type="button" class="btn btn-primary btn-sm" id="ai_analyse_btn">
                                <iconify-icon icon="solar:magic-stick-3-linear"></iconify-icon> Analyse Questions
                            </button>
                        </div>
                        <p class="text-muted swl-ai-help">
                            Pages and reports that would answer what people keep asking. The assistant files one when a question has no good page;
                            <b>Analyse Questions</b> reads the recent questions and proposes more. Votes count how often the same idea came up.
                        </p>
                        <div id="ai_themes"></div>
                        <div id="ai_ideas"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script>
        window.CDP_AI_SETTINGS = {
            url: 'ajax/ai_assistant/settings_ajax.php',
            providers: <?php echo json_encode(array_map(function ($p) {
                return ['label' => $p['label'], 'default' => $p['default'], 'models' => $p['models']];
            }, $providers), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>
        };
    </script>
    <script src="<?= cdp_asset('dataJs/ai_assistant_settings.js') ?>"></script>
</body>

</html>
