<?php
// *************************************************************************
// * AI Assistant — the chat.                                              *
// *                                                                       *
// * GET  action=list            the user's conversations                   *
// * GET  action=load  id=N      one conversation's messages                *
// * POST action=send            message, conversation_id (0 = new chat)    *
// * POST action=rename id title                                            *
// * POST action=delete id                                                  *
// *                                                                       *
// * A user only ever reaches their own conversations. The answer itself    *
// * comes from helpers/ai_assistant_llm.php, whose tools apply the user's  *
// * permissions and data scope.                                            *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
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

$user->cdp_getUserPermissions();
if (!cdp_aiCanUse($user)) {
    $reply(['ok' => false, 'error' => 'The AI Assistant is not available for your account.'], 403);
}
if (!cdp_aiEnsureTables()) {
    $reply(['ok' => false, 'error' => 'The assistant tables could not be created. The database user needs the CREATE privilege once.']);
}

$uid    = (int) ($_SESSION['userid'] ?? 0);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_REQUEST['action'] ?? 'list');

if ($method !== 'POST') {
    if ($action === 'load') {
        $conv = cdp_aiConversation((int) ($_GET['id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'That conversation no longer exists.'], 404);
        }
        $reply(['ok' => true, 'conversation' => ['id' => (int) $conv->id, 'title' => (string) $conv->title], 'messages' => cdp_aiMessages($conv->id)]);
    }
    $reply(['ok' => true, 'conversations' => cdp_aiConversations($uid)]);
}

if ($action === 'delete') {
    cdp_aiConversationDelete((int) ($_POST['id'] ?? 0), $uid);
    $reply(['ok' => true, 'conversations' => cdp_aiConversations($uid)]);
}

if ($action === 'rename') {
    cdp_aiConversationRename((int) ($_POST['id'] ?? 0), $uid, (string) ($_POST['title'] ?? ''));
    $reply(['ok' => true, 'conversations' => cdp_aiConversations($uid)]);
}

// ── send ────────────────────────────────────────────────────────────────────
if (!cdp_aiConfigured()) {
    $reply(['ok' => false, 'error' => 'The assistant is not set up yet. A super admin chooses the provider, API key and model on Tools > AI Assistant.']);
}

$question = trim((string) ($_POST['message'] ?? ''));
if ($question === '') {
    $reply(['ok' => false, 'error' => 'Type a question first.']);
}
if (mb_strlen($question) > 8000) {
    $reply(['ok' => false, 'error' => 'That message is too long (8,000 characters at most).']);
}

$convId = (int) ($_POST['conversation_id'] ?? 0);
$isNew = false;
if ($convId > 0) {
    if (!cdp_aiConversation($convId, $uid)) {
        $reply(['ok' => false, 'error' => 'That conversation no longer exists.'], 404);
    }
} else {
    $convId = cdp_aiConversationCreate($uid, $question);
    $isNew = true;
}

// Earlier turns as plain text: the last 20, each capped.
$history = [];
foreach (array_slice(cdp_aiMessages($convId), -20) as $m) {
    if (($m['role'] === 'user' || $m['role'] === 'assistant') && trim($m['content']) !== '') {
        $history[] = ['role' => $m['role'], 'content' => mb_substr($m['content'], 0, 6000)];
    }
}
// Roles must alternate and start with the user.
while ($history && $history[0]['role'] !== 'user') {
    array_shift($history);
}
$clean = [];
foreach ($history as $h) {
    if ($clean && end($clean)['role'] === $h['role']) {
        $clean[count($clean) - 1]['content'] .= "\n\n" . $h['content'];
    } else {
        $clean[] = $h;
    }
}
if ($clean && end($clean)['role'] === 'user') {
    array_pop($clean); // an earlier question that never got an answer
}

cdp_aiMessageAdd($convId, 'user', $question);

session_write_close(); // a long answer must not lock the user's other tabs

$userData = $user->cdp_getUserData();
$ctx = cdp_aiContext($user, $userData);
$res = cdp_aiAsk($ctx, $clean, $question);

$toolNames = array_map(function ($t) { return $t['name']; }, $res['tools'] ?? []);
$denied = array_filter($res['tools'] ?? [], function ($t) { return empty($t['ok']); });

if (empty($res['ok'])) {
    cdp_aiLogQuestion($user, $question, $toolNames, 'error');
    $reply(['ok' => false, 'error' => $res['error'] ?? 'The assistant could not answer.', 'conversation_id' => $convId, 'is_new' => $isNew]);
}

$text = (string) ($res['text'] ?? '');
if ($text === '') {
    $text = 'I could not find an answer to that. Try rephrasing, or name the tracking number, period or area you mean.';
}
cdp_aiMessageAdd($convId, 'assistant', $text, [
    'tools'      => $res['tools'] ?? [],
    'provider'   => cdp_aiSetting('provider'),
    'model'      => $res['model'] ?? cdp_aiSetting('model'),
    'tokens_in'  => $res['tokens_in'] ?? null,
    'tokens_out' => $res['tokens_out'] ?? null,
]);
cdp_aiConversationTouch($convId);
cdp_aiLogQuestion($user, $question, $toolNames, $denied && count($denied) === count($toolNames) ? 'no_access' : 'answered');

$reply([
    'ok'              => true,
    'conversation_id' => $convId,
    'is_new'          => $isNew,
    'answer'          => $text,
    'tools'           => $res['tools'] ?? [],
    'conversations'   => cdp_aiConversations($uid),
]);
