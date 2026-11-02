<?php
// *************************************************************************
// * AI Assistant — the chat page.                                         *
// *                                                                       *
// * Conversations on the left, the open conversation on the right, the     *
// * composer at the bottom. Everything after the first paint comes from    *
// * ajax/ai_assistant/chat_ajax.php (dataJs/ai_assistant.js).              *
// *************************************************************************

require_once('helpers/querys.php');
require_once('helpers/ai_assistant.php');

$userData = $user->cdp_getUserData();   // the top bar and the sidebar are built from it
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$aiReady   = cdp_aiEnsureTables() && cdp_aiConfigured() && cdp_aiCanUse($user);
$canManage = cdp_aiCanManage($user);
$canInsights = cdp_aiCanSeeInsights($user);
$scope     = cdp_aiScope($user, $userData);
$firstName = trim((string) ($userData->fname ?? '')) ?: 'there';

$starters = [
    'all' => [
        ['solar:chart-2-linear', 'Give me an overview of this week', 'Give me an overview of this week: new shipments, packages, pickups and consolidations by status, and anything that needs attention.'],
        ['solar:danger-triangle-linear', 'What issues need attention?', 'Which records have issues right now: problem statuses, things that have not moved for 7 days, and unpaid ones? Group them and tell me what to do first.'],
        ['solar:box-linear', 'Unpaid packages over 14 days', 'List customer packages that have been unpaid for more than 14 days, oldest first, with who owes what.'],
        ['solar:layers-linear', 'Consolidations in transit', 'Which consolidations are in transit and what is inside each one?'],
        ['solar:history-linear', 'What changed today?', 'What did the team do today? Summarise the activity log by person and by kind of action.'],
        ['solar:lightbulb-linear', 'Which page would help me most?', 'Based on what I can see in the system, which report or page would help me most that does not exist yet?'],
    ],
    'agency' => [
        ['solar:chart-2-linear', 'Overview of my branch this month', 'Give me an overview of my branch this month by status.'],
        ['solar:danger-triangle-linear', 'What is stuck at my branch?', 'Which of my branch records have not moved for 7 days or are in a problem status?'],
        ['solar:wallet-money-linear', 'Unpaid at my branch', 'What is unpaid at my branch, and who owes the most?'],
        ['solar:map-point-linear', 'Where do I find…', 'Which pages can I use, and what is each one for?'],
    ],
    'driver' => [
        ['solar:delivery-linear', 'What is assigned to me?', 'What is assigned to me that is not delivered yet? Oldest first.'],
        ['solar:history-linear', 'My deliveries this week', 'How many deliveries did I complete this week, by day?'],
        ['solar:danger-triangle-linear', 'Any problems on my list?', 'Is anything assigned to me in a problem status or not moving?'],
        ['solar:map-point-linear', 'Where do I find…', 'Which pages can I use, and what is each one for?'],
    ],
    'client' => [
        ['solar:box-linear', 'Where are my packages?', 'Where are my packages and shipments right now? Show what is still on the way.'],
        ['solar:wallet-money-linear', 'What do I owe?', 'What do I owe, and for which shipments or packages?'],
        ['solar:history-linear', 'My shipments this month', 'Show my shipments and packages from this month with their status.'],
        ['solar:question-circle-linear', 'How do I add a pre-alert?', 'How do I tell you a package is on its way to your warehouse?'],
    ],
];
$myStarters = $starters[$scope['type']] ?? $starters['client'];

$actions = '';
if ($canManage || $canInsights) {
    $actions = '<a href="ai_assistant_settings.php" class="btn btn-secondary btn-sm"><iconify-icon icon="solar:settings-linear"></iconify-icon> Assistant Settings</a>';
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>AI Assistant | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <?php cdp_pageHeader('AI Assistant', ['icon' => 'solar:magic-stick-3-linear', 'actions' => $actions]); ?>

            <div class="container-fluid">
                <div id="resultados_ajax"></div>

                <div class="swl-ai" id="ai_app" data-ready="<?php echo $aiReady ? '1' : '0'; ?>">

                    <!-- ── Conversations ─────────────────────────────────── -->
                    <aside class="swl-ai-side" id="ai_side" aria-label="Conversations">
                        <div class="swl-ai-side-head">
                            <button type="button" class="btn btn-primary btn-block swl-ai-new" id="ai_new">
                                <iconify-icon icon="solar:pen-new-square-linear"></iconify-icon> New chat
                            </button>
                            <button type="button" class="swl-ai-icon-btn swl-ai-side-close" id="ai_side_close" aria-label="Close conversations">
                                <iconify-icon icon="solar:close-circle-linear"></iconify-icon>
                            </button>
                        </div>
                        <div class="swl-ai-side-label">Recent</div>
                        <ul class="swl-ai-convs" id="ai_convs">
                            <li class="swl-ai-convs-empty">No conversations yet.</li>
                        </ul>
                    </aside>
                    <div class="swl-ai-backdrop" id="ai_backdrop"></div>

                    <!-- ── The open conversation ─────────────────────────── -->
                    <section class="swl-ai-main" aria-label="Conversation">
                        <div class="swl-ai-bar">
                            <button type="button" class="swl-ai-icon-btn swl-ai-side-open" id="ai_side_open" aria-label="Show conversations">
                                <iconify-icon icon="solar:hamburger-menu-linear"></iconify-icon>
                            </button>
                            <div class="swl-ai-title" id="ai_title">New chat</div>
                            <button type="button" class="swl-ai-icon-btn" id="ai_rename" aria-label="Rename" title="Rename" hidden>
                                <iconify-icon icon="solar:pen-linear"></iconify-icon>
                            </button>
                            <button type="button" class="swl-ai-icon-btn btn-delete" id="ai_delete" aria-label="Delete conversation" title="Delete conversation" hidden>
                                <iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon>
                            </button>
                        </div>

                        <div class="swl-ai-scroll" id="ai_scroll">
                            <div class="swl-ai-thread" id="ai_thread"></div>

                            <div class="swl-ai-welcome" id="ai_welcome">
                                <div class="swl-ai-mark"><iconify-icon icon="solar:magic-stick-3-bold"></iconify-icon></div>
                                <h2 class="swl-ai-hello">How can I help, <?php echo $e($firstName); ?>?</h2>
                                <p class="swl-ai-sub">
                                    Ask about shipments, packages, pickups, consolidations, pre-alerts, issues, tracking history,
                                    payments or where to find something. Answers only use what your role can see.
                                </p>
                                <?php if (!$aiReady) : ?>
                                    <div class="alert alert-warning swl-ai-setup">
                                        <b>The assistant is not set up yet.</b>
                                        <?php if ($canManage) : ?>
                                            Choose the provider (Claude, OpenAI or OpenRouter), paste the API key, pick a model and switch it on in
                                            <a href="ai_assistant_settings.php">Assistant Settings</a>.
                                        <?php else : ?>
                                            Ask a super admin to set it up under Tools &gt; AI Assistant.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="swl-ai-starters">
                                    <?php foreach ($myStarters as $s) : ?>
                                        <button type="button" class="swl-ai-starter" data-prompt="<?php echo $e($s[2]); ?>" <?php echo $aiReady ? '' : 'disabled'; ?>>
                                            <iconify-icon icon="<?php echo $e($s[0]); ?>"></iconify-icon>
                                            <span><?php echo $e($s[1]); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <form class="swl-ai-composer" id="ai_form" autocomplete="off">
                            <div class="swl-ai-input">
                                <textarea id="ai_input" rows="1" maxlength="8000"
                                    placeholder="<?php echo $aiReady ? 'Ask anything about the system…' : 'The assistant is not set up yet'; ?>"
                                    aria-label="Your question" <?php echo $aiReady ? '' : 'disabled'; ?>></textarea>
                                <button type="submit" class="swl-ai-send" id="ai_send" aria-label="Send" <?php echo $aiReady ? '' : 'disabled'; ?>>
                                    <iconify-icon icon="solar:arrow-up-linear"></iconify-icon>
                                </button>
                            </div>
                            <div class="swl-ai-foot">
                                Enter to send, Shift+Enter for a new line. The assistant reads data but never changes it, and can make mistakes: check important figures on the page it links to.
                            </div>
                        </form>
                    </section>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <script>
        window.CDP_AI = {
            url: 'ajax/ai_assistant/chat_ajax.php',
            ready: <?php echo $aiReady ? 'true' : 'false'; ?>
        };
    </script>
    <script src="<?= cdp_asset('dataJs/ai_assistant.js') ?>"></script>
</body>

</html>
