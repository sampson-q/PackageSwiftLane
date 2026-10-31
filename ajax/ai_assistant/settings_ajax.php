<?php
// *************************************************************************
// * AI Assistant — settings, knowledge and insights.                      *
// *                                                                       *
// * GET                          everything the settings page shows        *
// * POST action=save             provider, model, keys, options  (SA)      *
// * POST action=test             one short call with the given settings(SA)*
// * POST action=models           the provider's live model list    (SA)    *
// * POST action=knowledge_add    note                              (Admin) *
// * POST action=knowledge_delete id                                (Admin) *
// * POST action=idea_status      id, status                        (Admin) *
// * POST action=idea_delete      id                                (Admin) *
// * POST action=analyse          turn recent questions into page ideas     *
// *                                                                (Admin) *
// * SA = super admin (API keys live here). API keys never leave the        *
// * server: the page only ever receives a masked form.                     *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/ai_assistant_llm.php');
require_login();

header('Content-Type: application/json; charset=utf-8');

$reply = function (array $body, $code = 200) {
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$canManage   = cdp_aiCanManage($user);
$canInsights = cdp_aiCanSeeInsights($user);
if (!$canManage && !$canInsights) {
    $reply(['ok' => false, 'error' => 'No permission.'], 403);
}
if (!cdp_aiEnsureTables()) {
    $reply(['ok' => false, 'error' => 'The assistant tables could not be created. The database user needs the CREATE privilege once.']);
}

$uid = (int) ($_SESSION['userid'] ?? 0);

$log = function ($summary, $changes = []) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog([
            'module'  => 'settings',
            'verb'    => 'update',
            'action'  => 'settings.ai_assistant',
            'label'   => 'Settings · AI Assistant',
            'summary' => $summary,
            'changes' => $changes,
        ]);
    }
};

$payload = function () use ($canManage, $canInsights) {
    $out = ['ok' => true, 'can_manage' => $canManage, 'can_insights' => $canInsights];
    if ($canManage) {
        $s = cdp_aiSettings(true);
        $keys = [];
        foreach (array_keys(cdp_aiProviders()) as $p) {
            $keys[$p] = cdp_aiMaskKey($s['key_' . $p] ?? '');
        }
        $out['settings'] = [
            'enabled' => $s['enabled'] === '1', 'provider' => $s['provider'], 'model' => $s['model'],
            'audience' => $s['audience'], 'effort' => $s['effort'], 'max_rounds' => (int) $s['max_rounds'],
            'instructions' => $s['instructions'], 'suggest_pages' => $s['suggest_pages'] === '1', 'keys' => $keys,
        ];
        $out['configured'] = cdp_aiConfigured();
    }
    if ($canInsights) {
        $out['knowledge'] = cdp_aiKnowledge(false);
        $out['ideas'] = cdp_aiIdeas();
        $out['stats'] = cdp_aiQuestionStats(30);
        $la = json_decode((string) cdp_aiSetting('last_analysis', ''), true);
        $out['last_analysis'] = is_array($la) ? $la : null;
    }
    return $out;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    $reply($payload());
}

$action = (string) ($_POST['action'] ?? '');
$needManage = in_array($action, ['save', 'test', 'models'], true);
if ($needManage && !$canManage) {
    $reply(['ok' => false, 'error' => 'Only a super admin can change the AI provider and keys.'], 403);
}
if (!$needManage && !$canInsights) {
    $reply(['ok' => false, 'error' => 'No permission.'], 403);
}

$providers = cdp_aiProviders();

// A key typed in the form, or the stored one when the field was left as is.
$postedKey = function ($provider) {
    $k = trim((string) ($_POST['key_' . $provider] ?? ''));
    if ($k === '' || strpos($k, '…') !== false || strpos($k, '•') !== false) {
        return cdp_aiApiKey($provider);
    }
    return $k;
};

switch ($action) {
    case 'save':
        $before = cdp_aiSettings(true);
        $provider = (string) ($_POST['provider'] ?? 'claude');
        if (!isset($providers[$provider])) {
            $reply(['ok' => false, 'error' => 'Unknown provider.']);
        }
        $model = trim((string) ($_POST['model'] ?? ''));
        if ($model === '' || !preg_match('#^[A-Za-z0-9._:/@-]{2,120}$#', $model)) {
            $reply(['ok' => false, 'error' => 'Enter a valid model name.']);
        }
        $new = [
            'enabled'       => !empty($_POST['enabled']) && $_POST['enabled'] !== '0' ? '1' : '0',
            'provider'      => $provider,
            'model'         => $model,
            'audience'      => ($_POST['audience'] ?? 'all') === 'staff' ? 'staff' : 'all',
            'effort'        => in_array($_POST['effort'] ?? '', ['low', 'medium', 'high', 'xhigh', 'max'], true) ? $_POST['effort'] : 'medium',
            'max_rounds'    => (string) max(2, min(15, (int) ($_POST['max_rounds'] ?? 8))),
            'instructions'  => mb_substr(trim((string) ($_POST['instructions'] ?? '')), 0, 4000),
            'suggest_pages' => !empty($_POST['suggest_pages']) && $_POST['suggest_pages'] !== '0' ? '1' : '0',
        ];
        foreach (array_keys($providers) as $p) {
            if (!empty($_POST['clear_key_' . $p])) {
                $new['key_' . $p] = '';
                continue;
            }
            $k = trim((string) ($_POST['key_' . $p] ?? ''));
            if ($k !== '' && strpos($k, '…') === false && strpos($k, '•') === false) {
                $new['key_' . $p] = $k;
            }
        }
        if ($new['enabled'] === '1' && ($new['key_' . $provider] ?? cdp_aiApiKey($provider)) === '') {
            $reply(['ok' => false, 'error' => 'Add the ' . $providers[$provider]['label'] . ' API key before switching the assistant on.']);
        }
        $changes = [];
        foreach ($new as $k => $v) {
            if ((string) ($before[$k] ?? '') !== (string) $v) {
                cdp_aiSettingSet($k, $v, $uid);
                // Never write a key into the audit trail.
                $changes[$k] = strpos($k, 'key_') === 0 ? ['from' => $before[$k] !== '' ? 'set' : 'empty', 'to' => $v !== '' ? 'set' : 'empty']
                                                       : ['from' => $before[$k] ?? null, 'to' => $v];
            }
        }
        if ($changes) {
            $log('Changed the AI Assistant settings: ' . implode(', ', array_keys($changes)), $changes);
        }
        $reply($payload() + ['saved' => true]);

    case 'test':
        $provider = (string) ($_POST['provider'] ?? cdp_aiSetting('provider'));
        if (!isset($providers[$provider])) {
            $reply(['ok' => false, 'error' => 'Unknown provider.']);
        }
        $model = trim((string) ($_POST['model'] ?? '')) ?: cdp_aiSetting('model');
        $t0 = microtime(true);
        $r = cdp_aiComplete('You are a connection test.', 'Reply with the single word: OK', $provider, $model, $postedKey($provider), 1024);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $reply($r['ok']
            ? ['ok' => true, 'message' => 'Connected to ' . $providers[$provider]['label'] . ' (' . ($r['model'] ?? $model) . ') in ' . number_format($ms / 1000, 1) . ' s.']
            : ['ok' => false, 'error' => $r['error']]);

    case 'models':
        $provider = (string) ($_POST['provider'] ?? cdp_aiSetting('provider'));
        if (!isset($providers[$provider])) {
            $reply(['ok' => false, 'error' => 'Unknown provider.']);
        }
        $reply(cdp_aiListModels($provider, $postedKey($provider)));

    case 'knowledge_add':
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            $reply(['ok' => false, 'error' => 'Write the fact first.']);
        }
        cdp_aiKnowledgeAdd($note, $uid, 'admin');
        $log('Taught the AI Assistant: ' . mb_substr($note, 0, 200));
        $reply($payload());

    case 'knowledge_delete':
        cdp_aiKnowledgeDelete((int) ($_POST['id'] ?? 0));
        $log('Removed a fact from the AI Assistant');
        $reply($payload());

    case 'idea_status':
        if (!cdp_aiIdeaSetStatus((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''))) {
            $reply(['ok' => false, 'error' => 'Could not change the status.']);
        }
        $reply($payload());

    case 'idea_delete':
        cdp_aiIdeaDelete((int) ($_POST['id'] ?? 0));
        $reply($payload());

    case 'analyse':
        if (!cdp_aiConfigured()) {
            $reply(['ok' => false, 'error' => 'Set up the provider and switch the assistant on first.']);
        }
        session_write_close();
        $r = cdp_aiAnalyseQuestions($uid);
        if (!$r['ok']) {
            $reply($r);
        }
        $reply($payload() + ['added' => $r['added']]);
}

$reply(['ok' => false, 'error' => 'Unknown action.'], 400);
