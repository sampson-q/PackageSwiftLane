#!/usr/bin/env php
<?php
/**
 * cron/pickup_aging.php
 *
 * Advances the pickup-aging state machine independent of any admin session:
 *   - discovers packages newly at Ready for PickUp,
 *   - Pending Collection + 7d -> Not Picked Up,
 *   - Not Picked Up + 7d -> Auction,
 *   - drops packages that were picked up / delivered.
 * The 14-day "notify senders" step is intentionally NOT automated here — it is
 * gated by an admin confirming the dashboard popup.
 *
 * Suggested crontab (hourly):
 *   0 * * * * php /var/www/html/PackageSwiftLane/cron/pickup_aging.php >> /var/log/pickup_aging.log 2>&1
 */

require_once __DIR__ . '/../loader.php';
require_once __DIR__ . '/../helpers/querys.php';
require_once __DIR__ . '/../helpers/pickup_aging.php';

$s = cdp_processPickupAging();

echo '[' . date('Y-m-d H:i:s') . '] pickup-aging: '
    . "discovered={$s['discovered']} not_picked={$s['not_picked']} "
    . "auction={$s['auction']} dropped={$s['dropped']} pending={$s['pending']}\n";
