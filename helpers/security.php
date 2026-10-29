<?php
/** Shared security primitives. No database or application bootstrap side effects. */
function cdp_securityStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        // Secure by default, including HTTPS deployments behind a TLS terminator.
        // A local HTTP development instance may explicitly opt out in its private config.
        'secure' => defined('CDP_SESSION_COOKIE_SECURE') ? (bool) CDP_SESSION_COOKIE_SECURE : true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Validate the server-detected MIME and every suffix, including Apache multi-extension handlers. */
function cdp_securityUploadAllowed(string $source, string $destination, int $maxBytes = 10485760): bool
{
    $name = basename($destination);
    if ($name === '' || $name === '.' || $name === '..') return false;
    if (preg_match('/[\x00-\x1f\x7f]/', $name) || $name[0] === '.'
        || preg_match('/\.(?:php\d*|phtml|pht|phar|cgi|pl|py|sh|shtml|html?|js|svg|htaccess)(?:\.|$)/i', $name)) {
        return false;
    }
    $types = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
        'webm' => ['video/webm'], 'mp4' => ['video/mp4', 'application/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'], 'mov' => ['video/quicktime'],
        'ogg' => ['video/ogg', 'application/ogg'], 'ogv' => ['video/ogg'],
        '3gp' => ['video/3gpp'], '3gpp' => ['video/3gpp'],
        'mkv' => ['video/x-matroska'], 'avi' => ['video/x-msvideo', 'video/avi'],
        'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $size = is_file($source) ? filesize($source) : false;
    return isset($types[$extension]) && $size !== false && $size > 0 && $size <= $maxBytes
        && in_array((new finfo(FILEINFO_MIME_TYPE))->file($source), $types[$extension], true);
}

/** Same contract as move_uploaded_file, with a single enforced upload policy. */
function cdp_moveUploadedFile(string $source, string $destination): bool
{
    $parent = realpath(dirname($destination));
    $root = dirname(__DIR__);
    $confined = false;
    foreach (['assets', 'order_files', 'pre_alert_files', 'driver_files', 'doc_signs', 'files'] as $folder) {
        $base = $root . DIRECTORY_SEPARATOR . $folder;
        if ($parent !== false && ($parent === $base || strpos($parent, $base . DIRECTORY_SEPARATOR) === 0)) {
            $confined = true;
        }
    }
    if (!$confined || file_exists($destination) || is_link($destination)
        || !is_uploaded_file($source) || !cdp_securityUploadAllowed($source, $destination)) {
        return false;
    }
    return move_uploaded_file($source, $destination);
}

/** Resolve a single existing file beneath a fixed directory, excluding traversal and symlink escapes. */
function cdp_securityConfinedFile(string $directory, $name): ?string
{
    if (!is_string($name) || $name === '' || basename($name) !== $name || preg_match('/[\x00-\x1f\x7f\\\\]/', $name)) return null;
    $base = realpath($directory);
    $path = $base !== false ? realpath($base . DIRECTORY_SEPARATOR . $name) : false;
    return $path !== false && dirname($path) === $base && is_file($path) ? $path : null;
}

/** File-backed atomic limiter, outside the document root. Never stores credentials. */
function cdp_securityRateLimit(string $bucket, int $limit, int $window): bool
{
    $dir = defined('CDP_SECURITY_STATE_DIR') ? CDP_SECURITY_STATE_DIR : sys_get_temp_dir() . '/swiftlane-security-' . hash('sha256', __DIR__);
    if ((!is_dir($dir) && !@mkdir($dir, 0700, true)) || is_link($dir) || ((fileperms($dir) & 0077) !== 0)) {
        throw new RuntimeException('Security state directory unavailable.');
    }
    $path = $dir . '/' . hash('sha256', $bucket) . '.json';
    if (is_link($path)) {
        throw new RuntimeException('Invalid security state file.');
    }
    $file = @fopen($path, 'c+');
    if (!$file || !flock($file, LOCK_EX)) {
        throw new RuntimeException('Security state lock unavailable.');
    }
    try {
        $serialized = stream_get_contents($file);
        $state = json_decode($serialized, true);
        if ($serialized !== '' && (!is_array($state) || !is_int($state['until'] ?? null) || !is_int($state['count'] ?? null))) {
            throw new RuntimeException('Invalid security state.');
        }
        $now = time();
        if (!is_array($state) || ($state['until'] ?? 0) <= $now) {
            $state = ['until' => $now + $window, 'count' => 0];
        }
        $allowed = $state['count'] < $limit;
        $state['count'] = min($limit, $state['count'] + 1);
        rewind($file);
        $encoded = json_encode($state);
        if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
            throw new RuntimeException('Security state write failed.');
        }
        return $allowed;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
