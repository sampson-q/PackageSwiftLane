<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * The e-mail newsletter builder's server side (views/tools/newsletter.php).
 *
 * POST action =
 *   designs              list of saved designs
 *   load      id         one saved design (JSON + subject)
 *   save      id?, name, subject, preheader, design_json, html
 *   delete    id
 *   count     audience, emails       how many people a send would reach
 *   test      emails, subject, html  send to up to 5 addresses (no batch)
 *   send      audience, emails, subject, html, design_id, offset, batch_id
 *             one chunk of a campaign; the page calls again until done
 *   upload    file "image"           store an image for the design
 *   history                          recent campaigns from the message log
 *
 * Every answer is JSON: { success, ...data, errors: [] }.
 */

require_once("../../loader.php");
require_once("../../helpers/ajax_guard.php");
require_login();
require_permission('manage_newsletter');
require_once("../../helpers/querys.php");
require_once("../../helpers/newsletter.php");

header('Content-Type: application/json; charset=utf-8');

function cdp_nlReply(array $data, $ok = true)
{
    echo json_encode(array_merge(['success' => $ok, 'errors' => []], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cdp_nlFail($errors)
{
    cdp_nlReply(['errors' => array_values((array) $errors)], false);
}

$settings = cdp_getSettingsCourier();
$siteUrl  = (string) ($settings->site_url ?? '');
$action   = isset($_POST['action']) ? (string) $_POST['action'] : '';
$userData = $user->cdp_getUserData();
$uid      = (int) ($userData->id ?? 0);

/** The posted design HTML, cleaned. Fails the request when it is unusable. */
function cdp_nlPostedHtml($siteUrl)
{
    $raw = (string) ($_POST['html'] ?? '');
    if (trim($raw) === '') {
        cdp_nlFail(['The design is empty: add some content blocks first.']);
    }
    if (strlen($raw) > CDP_NL_MAX_HTML) {
        cdp_nlFail(['The design is too large to send (' . round(strlen($raw) / 1024) . ' KB). Use image links rather than pasted images.']);
    }
    return cdp_nlCleanHtml($raw, $siteUrl);
}

function cdp_nlPostedSubject()
{
    $subject = trim(preg_replace('/[\r\n]+/', ' ', (string) ($_POST['subject'] ?? '')));
    if ($subject === '') {
        cdp_nlFail(['Enter a subject line.']);
    }
    return mb_substr($subject, 0, 255);
}

switch ($action) {

    // ── Saved designs ──────────────────────────────────────────────────────
    case 'designs':
        if (!cdp_nlTablesReady()) {
            cdp_nlFail(['The newsletter tables could not be created. Check the database user may CREATE TABLE.']);
        }
        cdp_nlReply(['designs' => cdp_nlListDesigns()]);
        break;

    case 'load':
        $row = cdp_nlGetDesign((int) ($_POST['id'] ?? 0));
        if (!$row) {
            cdp_nlFail(['That design no longer exists.']);
        }
        cdp_nlReply(['design' => [
            'id'        => (int) $row->id,
            'name'      => (string) $row->name,
            'subject'   => (string) $row->subject,
            'preheader' => (string) $row->preheader,
            'json'      => (string) $row->design_json,
        ]]);
        break;

    case 'save':
        $name = trim((string) ($_POST['name'] ?? ''));
        $json = (string) ($_POST['design_json'] ?? '');
        if ($name === '') {
            cdp_nlFail(['Give the design a name.']);
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            cdp_nlFail(['The design could not be read. Reload the page and try again.']);
        }
        if (strlen($json) > CDP_NL_MAX_HTML * 2) {
            cdp_nlFail(['The design is too large to save.']);
        }
        $html = cdp_nlPostedHtml($siteUrl);
        $id = cdp_nlSaveDesign(
            (int) ($_POST['id'] ?? 0),
            $name,
            trim((string) ($_POST['subject'] ?? '')),
            trim((string) ($_POST['preheader'] ?? '')),
            $json,
            $html,
            $uid
        );
        if ($id <= 0) {
            cdp_nlFail(['The design could not be saved.']);
        }
        cdp_nlReply(['id' => $id, 'designs' => cdp_nlListDesigns()]);
        break;

    case 'delete':
        if (!cdp_nlDeleteDesign((int) ($_POST['id'] ?? 0))) {
            cdp_nlFail(['The design could not be deleted.']);
        }
        cdp_nlReply(['designs' => cdp_nlListDesigns()]);
        break;

    // ── Audience size ──────────────────────────────────────────────────────
    case 'count':
        $audience = (string) ($_POST['audience'] ?? '');
        if (!isset(cdp_nlAudiences()[$audience])) {
            cdp_nlFail(['Choose who receives the newsletter.']);
        }
        $list   = cdp_nlRecipients($audience, (string) ($_POST['emails'] ?? ''));
        $parsed = $audience === 'emails' ? cdp_nlParseEmails((string) ($_POST['emails'] ?? '')) : ['invalid' => []];
        $sample = array_map(function ($r) {
            $n = trim($r['fname'] . ' ' . $r['lname']);
            return $n !== '' ? $n . ' <' . $r['email'] . '>' : $r['email'];
        }, array_slice($list, 0, 5));
        cdp_nlReply(['total' => count($list), 'sample' => $sample, 'invalid' => $parsed['invalid']]);
        break;

    // ── Test send ──────────────────────────────────────────────────────────
    case 'test':
        $subject = cdp_nlPostedSubject();
        $parsed  = cdp_nlParseEmails((string) ($_POST['emails'] ?? ''));
        if (!$parsed['valid']) {
            cdp_nlFail(['Enter at least one valid e-mail address for the test.']);
        }
        if (count($parsed['valid']) > 5) {
            cdp_nlFail(['A test goes to five addresses at most.']);
        }
        $html  = cdp_nlPostedHtml($siteUrl);
        $ctx   = ['batch_id' => cdp_msgNewBatchId('nltest'), 'entity_type' => 'newsletter_test', 'entity_label' => 'Test send'];
        $lines = [];
        $sent  = 0;
        foreach (cdp_nlRecipients('emails', implode(',', $parsed['valid'])) as $r) {
            $res = cdp_nlSendOne($r, '[TEST] ' . $subject, $html, $settings, $ctx);
            $sent += $res['status'] === 'sent' ? 1 : 0;
            $lines[] = $r['email'] . ' — ' . $res['status'] . ($res['detail'] !== '' ? ' (' . $res['detail'] . ')' : '');
        }
        cdp_nlReply(['sent' => $sent, 'lines' => $lines], $sent > 0);
        break;

    // ── Campaign send (one chunk) ──────────────────────────────────────────
    case 'send':
        $audience = (string) ($_POST['audience'] ?? '');
        if (!isset(cdp_nlAudiences()[$audience])) {
            cdp_nlFail(['Choose who receives the newsletter.']);
        }
        $subject = cdp_nlPostedSubject();
        $html    = cdp_nlPostedHtml($siteUrl);
        $offset  = max(0, (int) ($_POST['offset'] ?? 0));
        $rawBid  = (string) ($_POST['batch_id'] ?? '');
        $batchId = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $rawBid) ? $rawBid : cdp_msgNewBatchId('nl');

        $recipients = cdp_nlRecipients($audience, (string) ($_POST['emails'] ?? ''));
        if (!$recipients) {
            cdp_nlFail(['Nobody to send to: the chosen audience has no valid e-mail addresses.']);
        }

        $audLabel = cdp_nlAudiences()[$audience];
        $ctx = [
            'batch_id'     => $batchId,
            'entity_type'  => 'newsletter',
            'entity_id'    => (string) (int) ($_POST['design_id'] ?? 0),
            'entity_label' => $audLabel,
        ];

        if ($offset === 0 && function_exists('cdp_activityLog')) {
            cdp_activityLog([
                'module'       => 'notifications',
                'verb'         => 'notify',
                'action'       => 'notifications.newsletter',
                'label'        => 'Notifications · Newsletter Sent',
                'summary'      => sprintf('Newsletter "%s" started to %d recipient(s) [%s] — batch %s', $subject, count($recipients), $audLabel, $batchId),
                'entity_type'  => 'newsletter',
                'entity_id'    => $ctx['entity_id'],
                'entity_label' => $subject,
                'meta'         => ['batch_id' => $batchId, 'audience' => $audience, 'recipients' => count($recipients)],
            ]);
            cdp_nlMarkDesignSent((int) ($_POST['design_id'] ?? 0));
        }

        $sum = cdp_nlSendChunk($recipients, $offset, $subject, $html, $settings, $ctx);
        cdp_nlReply(['summary' => $sum]);
        break;

    // ── Image upload ───────────────────────────────────────────────────────
    case 'upload':
        $f = $_FILES['image'] ?? null;
        if (!$f || !is_uploaded_file($f['tmp_name'] ?? '') || (int) ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            cdp_nlFail(['The image did not arrive. Try again with a smaller file.']);
        }
        if ((int) $f['size'] > 5 * 1024 * 1024) {
            cdp_nlFail(['Images must be 5 MB or smaller.']);
        }
        $info  = @getimagesize($f['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) {
            cdp_nlFail(['Only JPG, PNG, GIF or WEBP images can be used.']);
        }
        $dir = dirname(__DIR__, 2) . '/assets/uploads/newsletter';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            cdp_nlFail(['The upload folder assets/uploads/newsletter could not be created.']);
        }
        $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            cdp_nlFail(['The image could not be stored.']);
        }
        $rel = 'assets/uploads/newsletter/' . $name;
        cdp_nlReply([
            'path'   => $rel,
            'url'    => rtrim($siteUrl, '/') . '/' . $rel,
            'width'  => (int) $info[0],
            'height' => (int) $info[1],
        ]);
        break;

    // ── Recent campaigns ───────────────────────────────────────────────────
    case 'history':
        cdp_nlReply(['history' => cdp_nlHistory(15)]);
        break;

    default:
        cdp_nlFail(['Unknown action.']);
}
