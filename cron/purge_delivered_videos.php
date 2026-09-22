#!/usr/bin/env php
<?php
/**
 * cron/purge_delivered_videos.php
 *
 * Deletes the condition videos of every package that has been delivered
 * (helpers/video_files.php). The delivery endpoints already do this when they
 * run; the cron catches every other way a package reaches Delivered — bulk
 * status updates, edits, a consolidation delivered as a whole.
 *
 * Suggested cron schedule (hourly):
 *   15 * * * * php /var/www/html/PackageSwiftLane/cron/purge_delivered_videos.php
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
chdir(dirname(__DIR__));
require_once __DIR__ . '/../loader.php';
require_once __DIR__ . '/../helpers/video_files.php';

$n = cdp_purgeDeliveredVideos();
echo date('Y-m-d H:i:s'), " purged videos — shipments: {$n['shipments']}, packages: {$n['packages']}\n";
