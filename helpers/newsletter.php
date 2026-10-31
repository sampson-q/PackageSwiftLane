<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * ============================================================================
 * E-mail newsletter: saved designs, audiences, personalisation, delivery.
 *
 * The page (views/tools/newsletter.php + dataJs/newsletter*.js) is a
 * drag-and-drop builder that produces a complete HTML e-mail in the browser.
 * This helper is the server side of it, used by ajax/tools/newsletter_ajax.php:
 *
 *   - designs      cdb_newsletter_designs (name, subject, preheader, the
 *                  builder's JSON and the HTML it produced)
 *   - audiences    who a campaign goes to (customers, subscribers, staff,
 *                  every active user, a typed list of addresses)
 *   - delivery     one personalised message per recipient, sent a few
 *                  recipients per request (like the push notifications, see
 *                  helpers/push_notify.php) so a large list never outlives
 *                  the web server's request limit. Every attempt lands in
 *                  cdb_message_log under source "newsletter" and the
 *                  campaign's batch id.
 *   - unsubscribe  a signed link per recipient ([UNSUBSCRIBE_URL]) that
 *                  newsletter_unsubscribe.php honours: the address is taken
 *                  off the subscriber list and left out of later bulk sends.
 * ============================================================================
 */

require_once __DIR__ . '/message_log.php';
require_once __DIR__ . '/phpmailer/class.phpmailer.php';
require_once __DIR__ . '/phpmailer/class.smtp.php';

/** Recipients delivered per HTTP request (one SMTP conversation each). */
const CDP_NL_CHUNK = 10;

/** Largest design the page may save or send (bytes of HTML). */
const CDP_NL_MAX_HTML = 400000;

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

/** Create the newsletter tables on first use. Cached per request. */
function cdp_nlTablesReady()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $db = new Conexion;
        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_newsletter_designs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            subject VARCHAR(255) NOT NULL DEFAULT '',
            preheader VARCHAR(255) NOT NULL DEFAULT '',
            design_json MEDIUMTEXT NOT NULL,
            html MEDIUMTEXT NOT NULL,
            created_by INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_sent_at DATETIME NULL,
            KEY idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->cdp_execute();

        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_newsletter_unsubscribes (
            email VARCHAR(190) NOT NULL PRIMARY KEY,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->cdp_execute();

        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_newsletter_meta (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->cdp_execute();
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function cdp_nlNow()
{
    return function_exists('cdp_msgNow') ? cdp_msgNow() : date('Y-m-d H:i:s');
}

// ---------------------------------------------------------------------------
// Saved designs
// ---------------------------------------------------------------------------

/** @return object[] id, name, subject, updated_at, last_sent_at (newest first) */
function cdp_nlListDesigns()
{
    if (!cdp_nlTablesReady()) {
        return [];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT id, name, subject, updated_at, last_sent_at FROM cdb_newsletter_designs ORDER BY updated_at DESC LIMIT 200");
    $db->cdp_execute();
    return (array) $db->cdp_registros();
}

/** @return object|null */
function cdp_nlGetDesign($id)
{
    if (!cdp_nlTablesReady()) {
        return null;
    }
    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_newsletter_designs WHERE id = :id LIMIT 1");
    $db->bind(':id', (int) $id);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    return $row ?: null;
}

/**
 * Insert ($id = 0) or update a design.
 *
 * @return int the design id (0 on failure)
 */
function cdp_nlSaveDesign($id, $name, $subject, $preheader, $json, $html, $userId)
{
    if (!cdp_nlTablesReady()) {
        return 0;
    }
    $now = cdp_nlNow();
    $db  = new Conexion;
    $id  = (int) $id;
    if ($id > 0 && cdp_nlGetDesign($id)) {
        $db->cdp_query("UPDATE cdb_newsletter_designs
            SET name = :name, subject = :subject, preheader = :pre, design_json = :json, html = :html, updated_at = :now
            WHERE id = :id");
        $db->bind(':id', $id);
    } else {
        $db->cdp_query("INSERT INTO cdb_newsletter_designs
            (name, subject, preheader, design_json, html, created_by, created_at, updated_at)
            VALUES (:name, :subject, :pre, :json, :html, :uid, :now, :now2)");
        $db->bind(':uid', (int) $userId);
        $db->bind(':now2', $now);
        $id = 0;
    }
    $db->bind(':name', mb_substr($name, 0, 150));
    $db->bind(':subject', mb_substr($subject, 0, 255));
    $db->bind(':pre', mb_substr($preheader, 0, 255));
    $db->bind(':json', $json);
    $db->bind(':html', $html);
    $db->bind(':now', $now);
    if (!$db->cdp_execute()) {
        return 0;
    }
    return $id > 0 ? $id : (int) $db->dbh->lastInsertId();
}

function cdp_nlDeleteDesign($id)
{
    if (!cdp_nlTablesReady()) {
        return false;
    }
    $db = new Conexion;
    $db->cdp_query("DELETE FROM cdb_newsletter_designs WHERE id = :id");
    $db->bind(':id', (int) $id);
    return (bool) $db->cdp_execute();
}

function cdp_nlMarkDesignSent($id)
{
    if ((int) $id <= 0 || !cdp_nlTablesReady()) {
        return;
    }
    $db = new Conexion;
    $db->cdp_query("UPDATE cdb_newsletter_designs SET last_sent_at = :now WHERE id = :id");
    $db->bind(':now', cdp_nlNow());
    $db->bind(':id', (int) $id);
    $db->cdp_execute();
}

// ---------------------------------------------------------------------------
// Audiences
// ---------------------------------------------------------------------------

/** The audiences the page offers: key => label. */
function cdp_nlAudiences()
{
    return [
        'subscribers' => 'Newsletter subscribers',
        'customers'   => 'All active customers',
        'staff'       => 'All staff (employees, drivers, agencies)',
        'all'         => 'Every active user',
        'emails'      => 'Specific e-mail addresses',
    ];
}

/**
 * Split a typed list of addresses (commas, semicolons, spaces, new lines)
 * into valid, lower-cased, unique addresses.
 *
 * @return array{valid:string[],invalid:string[]}
 */
function cdp_nlParseEmails($raw)
{
    $valid = [];
    $invalid = [];
    foreach (preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        $part = trim($part, " \t\n\r\0\x0B<>\"'");
        if ($part === '') {
            continue;
        }
        if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
            $valid[strtolower($part)] = true;
        } else {
            $invalid[] = $part;
        }
    }
    return ['valid' => array_keys($valid), 'invalid' => $invalid];
}

/** Addresses that used the unsubscribe link. @return array<string,bool> */
function cdp_nlUnsubscribedSet()
{
    if (!cdp_nlTablesReady()) {
        return [];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT email FROM cdb_newsletter_unsubscribes");
    $db->cdp_execute();
    $out = [];
    foreach ((array) $db->cdp_registros() as $r) {
        $out[strtolower((string) $r->email)] = true;
    }
    return $out;
}

/**
 * Everyone a campaign goes to, in a stable order (the chunks walk it by
 * position), one entry per address.
 *
 * Bulk audiences leave out addresses that unsubscribed. A typed list is sent
 * as typed: the sender named those people on purpose.
 *
 * @return array<int,array{email:string,user_id:int,fname:string,lname:string}>
 */
function cdp_nlRecipients($audience, $emailsRaw = '')
{
    $where = [
        'subscribers' => "active = 1 AND newsletter = 1",
        'customers'   => "active = 1 AND userlevel = 1",
        'staff'       => "active = 1 AND userlevel <> 1",
        'all'         => "active = 1",
    ];

    $list = [];
    $seen = [];

    if ($audience === 'emails') {
        $parsed = cdp_nlParseEmails($emailsRaw);
        $known  = [];
        if ($parsed['valid']) {
            $db = new Conexion;
            $in = [];
            foreach ($parsed['valid'] as $i => $addr) {
                $in[] = ':e' . $i;
            }
            $db->cdp_query("SELECT id, email, fname, lname FROM cdb_users WHERE LOWER(email) IN (" . implode(',', $in) . ")");
            foreach ($parsed['valid'] as $i => $addr) {
                $db->bind(':e' . $i, $addr);
            }
            $db->cdp_execute();
            foreach ((array) $db->cdp_registros() as $u) {
                $known[strtolower((string) $u->email)] = $u;
            }
        }
        foreach ($parsed['valid'] as $addr) {
            $u = $known[$addr] ?? null;
            $list[] = [
                'email'   => $addr,
                'user_id' => $u ? (int) $u->id : 0,
                'fname'   => $u ? (string) $u->fname : '',
                'lname'   => $u ? (string) $u->lname : '',
            ];
        }
        return $list;
    }

    if (!isset($where[$audience])) {
        return [];
    }

    $db = new Conexion;
    $db->cdp_query("SELECT id, email, fname, lname FROM cdb_users WHERE {$where[$audience]} AND email IS NOT NULL AND email <> '' ORDER BY id ASC");
    $db->cdp_execute();
    $unsub = cdp_nlUnsubscribedSet();
    foreach ((array) $db->cdp_registros() as $u) {
        $addr = strtolower(trim((string) $u->email));
        if ($addr === '' || isset($seen[$addr]) || !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if ($audience !== 'subscribers' && isset($unsub[$addr])) {
            continue;
        }
        $seen[$addr] = true;
        $list[] = ['email' => $addr, 'user_id' => (int) $u->id, 'fname' => (string) $u->fname, 'lname' => (string) $u->lname];
    }
    return $list;
}

// ---------------------------------------------------------------------------
// Unsubscribe links
// ---------------------------------------------------------------------------

/** The key unsubscribe links are signed with (made once, kept in the DB). */
function cdp_nlSecret()
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $secret = '';
    if (!cdp_nlTablesReady()) {
        return $secret;
    }
    $db = new Conexion;
    $db->cdp_query("SELECT v FROM cdb_newsletter_meta WHERE k = 'unsubscribe_secret' LIMIT 1");
    $db->cdp_execute();
    $row = $db->cdp_registro();
    if ($row && strlen((string) $row->v) >= 32) {
        return $secret = (string) $row->v;
    }
    $new = bin2hex(random_bytes(32));
    $db->cdp_query("INSERT IGNORE INTO cdb_newsletter_meta (k, v) VALUES ('unsubscribe_secret', :v)");
    $db->bind(':v', $new);
    $db->cdp_execute();
    // Another request may have won the race: read back what is stored.
    $db->cdp_query("SELECT v FROM cdb_newsletter_meta WHERE k = 'unsubscribe_secret' LIMIT 1");
    $db->cdp_execute();
    $row = $db->cdp_registro();
    return $secret = $row ? (string) $row->v : $new;
}

function cdp_nlUnsubscribeToken($email)
{
    $secret = cdp_nlSecret();
    if ($secret === '') {
        return '';
    }
    return substr(hash_hmac('sha256', 'unsub|' . strtolower(trim((string) $email)), $secret), 0, 32);
}

function cdp_nlUnsubscribeUrl($email, $siteUrl)
{
    $base = rtrim((string) $siteUrl, '/') . '/newsletter_unsubscribe.php';
    return $base . '?e=' . rawurlencode(strtolower(trim((string) $email))) . '&t=' . cdp_nlUnsubscribeToken($email);
}

function cdp_nlUnsubscribeValid($email, $token)
{
    $expected = cdp_nlUnsubscribeToken($email);
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

/** Record the opt-out and take the address off the subscriber list. */
function cdp_nlUnsubscribe($email)
{
    $email = strtolower(trim((string) $email));
    if ($email === '' || !cdp_nlTablesReady()) {
        return false;
    }
    $db = new Conexion;
    $db->cdp_query("INSERT IGNORE INTO cdb_newsletter_unsubscribes (email, created_at) VALUES (:e, :now)");
    $db->bind(':e', $email);
    $db->bind(':now', cdp_nlNow());
    $db->cdp_execute();

    $db->cdp_query("UPDATE cdb_users SET newsletter = 0 WHERE LOWER(email) = :e");
    $db->bind(':e', $email);
    $db->cdp_execute();
    return true;
}

// ---------------------------------------------------------------------------
// HTML: clean-up and personalisation
// ---------------------------------------------------------------------------

/**
 * Make the builder's HTML safe to send and to show again in the page:
 * no scripts, frames, forms or event handlers, no javascript: links, and
 * every relative image / link made absolute against the site URL (a mail
 * client has no page to resolve them against).
 */
function cdp_nlCleanHtml($html, $siteUrl)
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    $base = rtrim((string) $siteUrl, '/') . '/';

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xp = new DOMXPath($doc);
    foreach (['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'base', 'frame', 'frameset', 'applet'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) {
            $n->parentNode->removeChild($n);
        }
    }
    // A <meta http-equiv="refresh"> could send the reader elsewhere.
    foreach (iterator_to_array($xp->query('//meta[@http-equiv]')) as $n) {
        if (strtolower($n->getAttribute('http-equiv')) === 'refresh') {
            $n->parentNode->removeChild($n);
        }
    }
    foreach (iterator_to_array($xp->query('//@*')) as $attr) {
        $name = strtolower($attr->nodeName);
        $val  = trim((string) $attr->nodeValue);
        $el   = $attr->ownerElement;
        if (strpos($name, 'on') === 0 || strpos($name, 'data-nlb') === 0 || $name === 'contenteditable' || $name === 'draggable') {
            $el->removeAttribute($attr->nodeName);
            continue;
        }
        if (in_array($name, ['href', 'src', 'background', 'action', 'formaction', 'xlink:href'], true)) {
            $plain = strtolower(preg_replace('/[\x00-\x20]+/', '', $val));
            if (preg_match('/^(javascript|vbscript|data):/', $plain) && !preg_match('#^data:image/(png|jpe?g|gif|webp);#', $plain)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }
            // Relative paths only; personalisation tags like [URL] stay as typed.
            if ($val !== '' && !preg_match('#^([a-z][a-z0-9+.-]*:|//|\#|\[)#i', $val)) {
                $el->setAttribute($attr->nodeName, $base . ltrim($val, './'));
            }
        }
        if ($name === 'style' && preg_match('/expression\s*\(|javascript:|behaviou?r\s*:/i', $val)) {
            $el->removeAttribute($attr->nodeName);
        }
    }

    // drop the encoding hint we added
    foreach (iterator_to_array($doc->childNodes) as $n) {
        if ($n->nodeType === XML_PI_NODE) {
            $doc->removeChild($n);
        }
    }
    $out = (string) $doc->saveHTML();
    // DOMDocument writes the doctype it found; keep the HTML5 one at the top.
    if (stripos($out, '<!DOCTYPE') === false) {
        $out = "<!DOCTYPE html>\n" . $out;
    }
    // The tags are written as [TAG] by the builder; DOMDocument url-encodes
    // them inside href/src. Put them back.
    $out = preg_replace_callback('/%5B([A-Z_]{2,30})%5D/', function ($m) { return '[' . $m[1] . ']'; }, $out);
    return $out;
}

/** The personalisation tags the builder offers: tag => description. */
function cdp_nlTags()
{
    return [
        '[NAME]'            => 'Full name',
        '[FIRST_NAME]'      => 'First name',
        '[LAST_NAME]'       => 'Last name',
        '[EMAIL]'           => 'E-mail address',
        '[SITE_NAME]'       => 'Company name',
        '[URL]'             => 'Website address',
        '[TRACK_URL]'       => 'Tracking page',
        '[YEAR]'            => 'Current year',
        '[UNSUBSCRIBE_URL]' => 'Unsubscribe link',
    ];
}

/**
 * Fill in the tags for one recipient. Names are HTML-escaped (they are
 * written into HTML); a missing name reads "Customer" / "there".
 *
 * @param array $r recipient: email, fname, lname
 */
function cdp_nlPersonalise($text, array $r, $settings, $isHtml = true)
{
    $esc   = $isHtml ? function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } : function ($v) { return (string) $v; };
    $first = trim((string) ($r['fname'] ?? ''));
    $last  = trim((string) ($r['lname'] ?? ''));
    $full  = trim($first . ' ' . $last);
    $site  = rtrim((string) ($settings->site_url ?? ''), '/');

    return str_replace(
        array_keys(cdp_nlTags()),
        [
            $esc($full !== '' ? $full : 'Customer'),
            $esc($first !== '' ? $first : 'there'),
            $esc($last),
            $esc((string) ($r['email'] ?? '')),
            $esc((string) ($settings->site_name ?? '')),
            $esc($site . '/'),
            $esc($site . '/tracking.php'),
            date('Y'),
            $esc(cdp_nlUnsubscribeUrl((string) ($r['email'] ?? ''), $site)),
        ],
        (string) $text
    );
}

/** A plain-text version of the HTML for the multipart alternative. */
function cdp_nlPlainText($html)
{
    $t = preg_replace('#<(style|head|title)[^>]*>.*?</\1>#is', '', (string) $html);
    $t = preg_replace_callback('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', function ($m) {
        $label = trim(strip_tags($m[2]));
        return $label === '' ? '' : $label . ' (' . $m[1] . ')';
    }, $t);
    $t = preg_replace('#<(br|/p|/h[1-6]|/tr|/li|/div)[^>]*>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $t);
    return trim($t);
}

// ---------------------------------------------------------------------------
// Delivery
// ---------------------------------------------------------------------------

/**
 * Send one personalised newsletter.
 *
 * @param array  $r        recipient: email, user_id, fname, lname
 * @param string $html     the cleaned design (tags not yet filled in)
 * @return array{status:string,detail:string}  status sent|failed
 */
function cdp_nlSendOne(array $r, $subject, $html, $settings, array $ctx = [])
{
    $to = (string) ($r['email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        cdp_msgLog(array_merge($ctx, ['channel' => 'email', 'status' => 'skipped', 'status_detail' => 'No valid e-mail address.',
            'recipient_to' => $to, 'subject' => $subject, 'body' => '']));
        return ['status' => 'skipped', 'detail' => 'No valid e-mail address.'];
    }

    $name    = trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? ''));
    $body    = cdp_nlPersonalise($html, $r, $settings, true);
    $subj    = cdp_nlPersonalise($subject, $r, $settings, false);
    $unsub   = cdp_nlUnsubscribeUrl($to, (string) ($settings->site_url ?? ''));
    $from    = (string) ($settings->email_address ?? '');
    $fromNm  = (string) (($settings->smtp_names ?? '') !== '' ? $settings->smtp_names : ($settings->site_name ?? ''));

    $logCtx = array_merge([
        'source'            => 'newsletter',
        'subject'           => $subj,
        'recipient_user_id' => (int) ($r['user_id'] ?? 0),
        'recipient_name'    => $name,
    ], $ctx);
    cdp_msgSetContext($logCtx);

    try {
        if (($settings->mailer ?? '') === 'PHP') {
            $encSubj = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subj, 'UTF-8', 'B') : $subj;
            $header  = "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html; charset=UTF-8\r\n";
            $header .= "From: " . ($fromNm !== '' ? '"' . addcslashes($fromNm, '"\\') . '" ' : '') . "<{$from}>\r\n";
            $header .= "List-Unsubscribe: <{$unsub}>\r\n";
            $ok = @mail($to, $encSubj, $body, $header);
            cdp_msgLogPhpMail($ok, $to, $subj, $body, ['source' => 'newsletter']);
            $res = $ok ? ['status' => 'sent', 'detail' => ''] : ['status' => 'failed', 'detail' => 'PHP mail() returned false'];
        } else {
            // PHPMailer writes its own message-log row (helpers/message_log.php hooks).
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $settings->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings->smtp_user;
            $mail->Password   = $settings->smtp_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->Timeout    = 15;
            $mail->setFrom($from, $fromNm);
            $mail->addAddress($to, $name);
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsub . '>');
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subj;
            $mail->Body    = $body;
            $mail->AltBody = cdp_nlPlainText($body);
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->send();
            $res = ['status' => 'sent', 'detail' => ''];
        }
    } catch (Throwable $e) {
        $res = ['status' => 'failed', 'detail' => $e->getMessage()];
    }

    cdp_msgClearContext(array_keys($logCtx));
    return $res;
}

/** Addresses that already have a message-log row in this batch. @return array<string,bool> */
function cdp_nlAlreadyAttempted($batchId, array $emails)
{
    $emails = array_values(array_filter(array_map('strval', $emails)));
    if ((string) $batchId === '' || !$emails || !cdp_msgTableReady()) {
        return [];
    }
    $db = new Conexion;
    $in = [];
    foreach ($emails as $i => $e) {
        $in[] = ':e' . $i;
    }
    $db->cdp_query("SELECT DISTINCT LOWER(recipient_to) AS e FROM cdb_message_log
        WHERE batch_id = :b AND channel = 'email' AND LOWER(recipient_to) IN (" . implode(',', $in) . ")");
    $db->bind(':b', (string) $batchId);
    foreach ($emails as $i => $e) {
        $db->bind(':e' . $i, strtolower($e));
    }
    $db->cdp_execute();
    $out = [];
    foreach ((array) $db->cdp_registros() as $row) {
        $out[(string) $row->e] = true;
    }
    return $out;
}

/**
 * Send the next chunk of a campaign: recipients from position $offset on.
 *
 * @return array{total:int,recipients:int,sent:int,failed:int,skipped:int,lines:string[],next_offset:int,done:bool,batch_id:string}
 */
function cdp_nlSendChunk(array $recipients, $offset, $subject, $html, $settings, array $ctx)
{
    $offset = max(0, (int) $offset);
    $slice  = array_slice($recipients, $offset, CDP_NL_CHUNK);
    $sum = [
        'total'       => count($recipients),
        'recipients'  => 0,
        'sent'        => 0,
        'failed'      => 0,
        'skipped'     => 0,
        'lines'       => [],
        'next_offset' => $offset + count($slice),
        'done'        => $offset + count($slice) >= count($recipients),
        'batch_id'    => (string) ($ctx['batch_id'] ?? ''),
    ];

    // A chunk the page repeats (its answer was lost) skips whoever the lost
    // request already reached, so nobody gets the newsletter twice.
    $done = cdp_nlAlreadyAttempted($sum['batch_id'], array_column($slice, 'email'));

    foreach ($slice as $r) {
        $sum['recipients']++;
        $label = trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? ''));
        $label = ($label !== '' ? $label . ' <' . $r['email'] . '>' : $r['email']);
        if (isset($done[strtolower($r['email'])])) {
            $sum['skipped']++;
            $sum['lines'][] = $label . ' — already handled by an earlier attempt of this campaign, not sent again.';
            continue;
        }
        $res = cdp_nlSendOne($r, $subject, $html, $settings, $ctx);
        $sum[$res['status']]++;
        $sum['lines'][] = $label . ' — ' . $res['status'] . ($res['detail'] !== '' ? ' (' . $res['detail'] . ')' : '');
    }
    return $sum;
}

/** Campaigns sent from the page, newest first, from the message log. @return object[] */
function cdp_nlHistory($limit = 15)
{
    if (!cdp_msgTableReady()) {
        return [];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT batch_id, MAX(subject) AS subject, MIN(created_at) AS started_at, MAX(sent_by_name) AS sent_by,
            MAX(entity_label) AS audience, COUNT(*) AS attempts,
            SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed, SUM(status = 'skipped') AS skipped
        FROM cdb_message_log
        WHERE source = 'newsletter' AND batch_id <> '' AND entity_type = 'newsletter'
        GROUP BY batch_id
        ORDER BY started_at DESC
        LIMIT " . max(1, min(100, (int) $limit)));
    $db->cdp_execute();
    return (array) $db->cdp_registros();
}
