#!/usr/bin/env php
<?php
/**
 * cron/cleanup_tmp_uploads.php
 *
 * Deletes temp upload folders under assets/uploads/tmp/ that are older than 1 hour.
 * These are left behind when a user submits registration but never verifies their email.
 *
 * Suggested cron schedule (runs every hour):
 *   0 * * * * php /var/www/html/cron/cleanup_tmp_uploads.php
 */

$tmpBase  = __DIR__ . '/../assets/uploads/tmp/';
$maxAge   = 3600; // 1 hour in seconds — adjust if your OTP expiry is longer
$now      = time();
$deleted  = 0;

if (!is_dir($tmpBase)) {
    exit(0);
}

foreach (glob($tmpBase . '*', GLOB_ONLYDIR) as $dir) {
    if (($now - filemtime($dir)) >= $maxAge) {
        // Remove all files inside
        foreach (glob($dir . '/*') as $file) {
            unlink($file);
        }
        rmdir($dir);
        $deleted++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$deleted} expired temp upload folder(s).\n";