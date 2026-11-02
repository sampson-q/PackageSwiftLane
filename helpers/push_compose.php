<?php
/**
 * ============================================================================
 * Push composer: the attachment, the audience size and the cost estimate
 * behind the WhatsApp / SMS composer on Tools > Push Notifications.
 *
 * - An attachment (image, document or video) goes out with the WhatsApp copy
 *   only; SMS and e-mail carry the text. The page uploads the file first
 *   (ajax/tools/push_media_upload_ajax.php), keeps the stored name, and posts
 *   that name with every chunk of the push. The server never takes a URL from
 *   the browser: it rebuilds the public URL from the stored name.
 * - The cost per message comes from the notification cost settings
 *   (helpers/message_cost.php: cdp_notifyCostPerMessage / cdp_notifyCostCurrency)
 *   when that helper is installed; without it the estimate shows counts only.
 * ============================================================================
 */

if (!defined('CDP_PUSH_MEDIA_DIR')) {
    /** Where attachments are stored, relative to the project root (served publicly). */
    define('CDP_PUSH_MEDIA_DIR', 'assets/uploads/push_media');
    /** WhatsApp (UltraMsg) takes media up to 16 MB. */
    define('CDP_PUSH_MEDIA_MAX_BYTES', 16 * 1024 * 1024);
    /** WhatsApp caption limit; a longer message follows the media as its own text. */
    define('CDP_PUSH_WA_CAPTION_MAX', 1024);
}

/**
 * What may be attached, per kind: extension => accepted MIME types.
 *
 * @return array<string, array<string, string[]>>
 */
function cdp_pushMediaTypes()
{
    return [
        'image' => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'gif'  => ['image/gif'],
        ],
        'video' => [
            'mp4' => ['video/mp4'],
            '3gp' => ['video/3gpp', 'video/3gpp2', 'video/mp4'],
            'mov' => ['video/quicktime', 'video/mp4'],
        ],
        'document' => [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            'csv'  => ['text/csv', 'text/plain', 'application/csv'],
            'txt'  => ['text/plain'],
        ],
    ];
}

/** The kind ('image'|'video'|'document') of an extension, or '' when not accepted. */
function cdp_pushMediaKindOfExt($ext)
{
    $ext = strtolower((string) $ext);
    foreach (cdp_pushMediaTypes() as $kind => $exts) {
        if (isset($exts[$ext])) {
            return $kind;
        }
    }
    return '';
}

function cdp_pushMediaDirFs()
{
    return dirname(__DIR__) . '/' . CDP_PUSH_MEDIA_DIR;
}

/** Create the store, with a guard so nothing in it can ever run as a script. */
function cdp_pushMediaEnsureDir()
{
    $dir = cdp_pushMediaDirFs();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes -ExecCGI\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar\n<FilesMatch \"\\.(php|phtml|phar)\">\n    Require all denied\n</FilesMatch>\n");
    }
    $idx = $dir . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }
    return true;
}

/**
 * Store one uploaded file ($_FILES entry).
 *
 * @return array ['success' => bool, 'message' => string, 'file' => stored name,
 *               'kind' => image|video|document, 'name' => original name,
 *               'size' => bytes, 'url' => public URL]
 */
function cdp_pushMediaStore(array $file, $siteUrl)
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['success' => false, 'message' => 'The file is larger than the server accepts.'];
    }
    if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file was received.'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['success' => false, 'message' => 'The file is empty.'];
    }
    if ($size > CDP_PUSH_MEDIA_MAX_BYTES) {
        return ['success' => false, 'message' => 'WhatsApp accepts files up to 16 MB.'];
    }

    $orig = basename((string) ($file['name'] ?? 'file'));
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $kind = cdp_pushMediaKindOfExt($ext);
    if ($kind === '') {
        return ['success' => false, 'message' => 'This file type cannot be sent. Images: JPG, PNG, WEBP, GIF. Videos: MP4, 3GP, MOV. Documents: PDF, Word, Excel, PowerPoint, CSV, TXT.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($file['tmp_name']);
    }
    $okMimes = cdp_pushMediaTypes()[$kind][$ext];
    if ($mime !== '' && !in_array($mime, $okMimes, true)) {
        return ['success' => false, 'message' => 'The file content does not match its .' . $ext . ' extension.'];
    }

    if (!cdp_pushMediaEnsureDir()) {
        return ['success' => false, 'message' => 'The upload folder could not be created.'];
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], cdp_pushMediaDirFs() . '/' . $stored)) {
        return ['success' => false, 'message' => 'The file could not be saved.'];
    }
    @chmod(cdp_pushMediaDirFs() . '/' . $stored, 0644);

    // Keep the original name (for the document's file name in WhatsApp) beside it.
    $safeName = cdp_pushMediaSafeName($orig, $ext);
    @file_put_contents(cdp_pushMediaDirFs() . '/' . $stored . '.name', $safeName);

    return [
        'success' => true,
        'message' => '',
        'file'    => $stored,
        'kind'    => $kind,
        'name'    => $safeName,
        'size'    => $size,
        'url'     => cdp_pushMediaUrl($stored, $siteUrl),
    ];
}

/** A display / download file name that cannot carry markup or a path. */
function cdp_pushMediaSafeName($name, $ext)
{
    $base = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', pathinfo((string) $name, PATHINFO_FILENAME));
    $base = trim(substr((string) $base, 0, 80));
    return ($base !== '' ? $base : 'file') . '.' . strtolower((string) $ext);
}

function cdp_pushMediaUrl($stored, $siteUrl)
{
    return rtrim((string) $siteUrl, '/') . '/' . CDP_PUSH_MEDIA_DIR . '/' . rawurlencode((string) $stored);
}

/**
 * The attachment posted with a push, checked against the store.
 *
 * @param string $stored the name cdp_pushMediaStore() returned
 * @return array|null ['kind', 'url', 'filename', 'file'] or null for none / unknown
 */
function cdp_pushMediaResolve($stored, $siteUrl)
{
    $stored = (string) $stored;
    if (!preg_match('/^[a-f0-9]{32}\.([a-z0-9]{2,5})$/', $stored, $m)) {
        return null;
    }
    $kind = cdp_pushMediaKindOfExt($m[1]);
    $path = cdp_pushMediaDirFs() . '/' . $stored;
    if ($kind === '' || !is_file($path)) {
        return null;
    }
    $name = is_file($path . '.name') ? trim((string) @file_get_contents($path . '.name')) : '';
    return [
        'kind'     => $kind,
        'file'     => $stored,
        'url'      => cdp_pushMediaUrl($stored, $siteUrl),
        'filename' => $name !== '' ? $name : $stored,
    ];
}

// ── Cost ────────────────────────────────────────────────────────────────────

/** Load the notification cost settings helper when it is installed. */
function cdp_pushCostLoad()
{
    static $tried = false;
    if (!$tried) {
        $tried = true;
        $f = __DIR__ . '/message_cost.php';
        if (!function_exists('cdp_notifyCostPerMessage') && is_file($f)) {
            require_once $f;
        }
    }
    return function_exists('cdp_notifyCostPerMessage');
}

/**
 * The price of one message per channel, for the composer's estimate.
 *
 * @return array ['available' => bool, 'currency' => string,
 *               'whatsapp' => float, 'sms' => float, 'email' => float,
 *               'sms_per_part' => bool]
 */
function cdp_pushCostRates()
{
    $out = ['available' => false, 'currency' => '', 'whatsapp' => 0.0, 'sms' => 0.0, 'email' => 0.0, 'sms_per_part' => false];
    if (!cdp_pushCostLoad()) {
        return $out;
    }
    try {
        $out['available'] = true;
        foreach (['whatsapp', 'sms', 'email'] as $ch) {
            $out[$ch] = (float) cdp_notifyCostPerMessage($ch);
        }
        if (function_exists('cdp_notifyCostCurrency')) {
            $out['currency'] = (string) cdp_notifyCostCurrency();
        }
        $out['sms_per_part'] = cdp_pushCostSmsPerPart();
    } catch (Throwable $e) {
        $out['available'] = false;
    }
    return $out;
}

/**
 * Whether long SMS are billed per 153-character part (the cost settings'
 * sms_parts switch, off unless set), so the estimate matches the cost page.
 */
function cdp_pushCostSmsPerPart()
{
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT setting_value FROM cdb_message_cost_settings WHERE setting_key = 'sms_parts' LIMIT 1");
        $db->cdp_execute();
        $row = $db->cdp_registro();
        return $row && (string) $row->setting_value === '1';
    } catch (Throwable $e) {
        return false;
    }
}

// ── Audience ────────────────────────────────────────────────────────────────

/**
 * Who a push would reach, for the composer's estimate (no message is sent).
 *
 * @param int[] $recipientIds
 * @return array ['total' => int, 'with_phone' => int, 'with_email' => int]
 */
function cdp_pushAudienceCounts(array $recipientIds)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $recipientIds), function ($v) { return $v > 0; })));
    $out = ['total' => count($ids), 'with_phone' => 0, 'with_email' => 0];
    if (!$ids) {
        return $out;
    }
    $db = new Conexion;
    foreach (array_chunk($ids, 1000) as $chunk) {
        $db->cdp_query("SELECT
                SUM(CASE WHEN TRIM(COALESCE(phone, '')) <> '' THEN 1 ELSE 0 END) AS p,
                SUM(CASE WHEN TRIM(COALESCE(email, '')) LIKE '%@%' THEN 1 ELSE 0 END) AS e
            FROM cdb_users WHERE id IN (" . implode(',', $chunk) . ")");
        $db->cdp_execute();
        $r = $db->cdp_registro();
        $out['with_phone'] += (int) ($r->p ?? 0);
        $out['with_email'] += (int) ($r->e ?? 0);
    }
    return $out;
}
