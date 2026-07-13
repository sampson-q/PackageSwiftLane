<?php
// ============================================================================
// Pickup-aging state machine (Epic C) — AIR courier flow (cdb_add_order).
//
// Lifecycle of an uncollected package:
//   Ready for PickUp(32)  --14d-->  [admin confirms] --> Pending Collection(1) + sender messaged
//   Pending Collection(1) --7d-->   Not Picked Up(16)   (auto, no message)
//   Not Picked Up(16)     --7d-->   Auction             (auto, no message)
// Picked up(15)/Delivered(8) at any point removes the package from the chain.
//
// The ledger table cdb_package_pickup_aging is the single source of truth for
// the timeline; cdp_processPickupAging() is idempotent and is called by both the
// cron (cron/pickup_aging.php) and the admin dashboard scan.
//
// Requires loader.php (Conexion, Core) to be included by the caller.
// ============================================================================

if (!defined('CDP_PA_READY'))        define('CDP_PA_READY', 32);     // Ready for PickUp
if (!defined('CDP_PA_SORTING'))      define('CDP_PA_SORTING', 33);   // Sorting at Accra Office
if (!defined('CDP_PA_PENDING'))      define('CDP_PA_PENDING', 1);    // Pending_Collection
if (!defined('CDP_PA_NOTPICKED'))    define('CDP_PA_NOTPICKED', 16); // Not Picked Up
if (!defined('CDP_PA_PICKED'))       define('CDP_PA_PICKED', 15);    // Picked up
if (!defined('CDP_PA_DELIVERED'))    define('CDP_PA_DELIVERED', 8);  // Delivered
if (!defined('CDP_PA_READY_DAYS'))   define('CDP_PA_READY_DAYS', 14);
if (!defined('CDP_PA_NOTPICK_DAYS')) define('CDP_PA_NOTPICK_DAYS', 7);
if (!defined('CDP_PA_AUCTION_DAYS')) define('CDP_PA_AUCTION_DAYS', 7);

if (!function_exists('cdp_pickupAgingAuctionId')) {
    /** Resolve the "Auction" status id by name (never hard-coded). 0 if missing. */
    function cdp_pickupAgingAuctionId()
    {
        static $id = null;
        if ($id !== null) return $id;
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM cdb_styles WHERE mod_style = 'Auction' ORDER BY id ASC LIMIT 1");
        $db->cdp_execute();
        $r = $db->cdp_registro();
        $id = $r ? (int) $r->id : 0;
        return $id;
    }
}

if (!function_exists('cdp_pickupAgingTemplateIds')) {
    /** Resolve the WhatsApp + email reminder template ids by title/name. */
    function cdp_pickupAgingTemplateIds()
    {
        static $ids = null;
        if ($ids !== null) return $ids;
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM whatsapp_templates WHERE title = 'Pickup Aging - Awaiting Collection' LIMIT 1");
        $db->cdp_execute();
        $w = $db->cdp_registro();
        $db->cdp_query("SELECT id FROM cdb_email_templates WHERE name = 'Pickup Aging - Awaiting Collection' LIMIT 1");
        $db->cdp_execute();
        $e = $db->cdp_registro();
        $ids = ['whatsapp' => $w ? (int) $w->id : 0, 'email' => $e ? (int) $e->id : 0];
        return $ids;
    }
}

if (!function_exists('cdp_pickupAgingSetStatus')) {
    /** Set a package's status_courier and append a package-level track row. */
    function cdp_pickupAgingSetStatus($order_id, $order_track, $status, $comment, $user_id = 0, $office = 0)
    {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_add_order SET status_courier = :s WHERE order_id = :id");
        $db->bind(':s', (int) $status);
        $db->bind(':id', (int) $order_id);
        $db->cdp_execute();
        if (function_exists('cdp_updateShipTrackingMultiple')) {
            cdp_updateShipTrackingMultiple($order_track, (int) $status, $comment, $office, (int) $user_id);
        }
    }
}

if (!function_exists('cdp_propagateConsolidationStatusToPackages')) {
    /**
     * When an AIR consolidation is moved to Sorting at Accra (33) or Ready for
     * PickUp (32), give its member packages that same status. For 32, start the
     * aging clock. Member packages are resolved with the collation-safe deduped
     * join (the Financial Sheet pattern): (order_prefix, order_no) is not unique
     * in cdb_add_order, so map each detail row to exactly one order.
     *
     * @return int number of packages updated.
     */
    function cdp_propagateConsolidationStatusToPackages($consolidate_id, $status, $user_id = 0, $office = 0)
    {
        $status = (int) $status;
        if ($status !== CDP_PA_SORTING && $status !== CDP_PA_READY) return 0;

        $db = new Conexion;
        $db->cdp_query("
            SELECT a.order_id AS oid, d.order_prefix, d.order_no, a.sender_id
            FROM cdb_consolidate_detail d
            INNER JOIN cdb_add_order a ON a.order_id = (
                SELECT a2.order_id FROM cdb_add_order a2
                WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
                ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
                LIMIT 1)
            WHERE d.consolidate_id = :cid");
        $db->bind(':cid', (int) $consolidate_id);
        $db->cdp_execute();
        $pkgs = $db->cdp_registros();

        $n = 0;
        foreach ($pkgs as $p) {
            $track = $p->order_prefix . $p->order_no;
            cdp_pickupAgingSetStatus($p->oid, $track, $status, 'Consolidation status propagated to package', $user_id, $office);

            if ($status === CDP_PA_READY) {
                // Only start the pickup clock for packages already cleared for
                // delivery; the clock starts at clearance time. Uncleared
                // packages join later (via discovery) once Accounts clears them.
                $db->cdp_query("INSERT INTO cdb_package_pickup_aging (order_id, order_track, sender_id, ready_at)
                                SELECT a.order_id, :trk, a.sender_id, a.fs_cleared_at
                                FROM cdb_add_order a
                                WHERE a.order_id = :oid AND a.fs_cleared_for_delivery = 1
                                ON DUPLICATE KEY UPDATE order_track = VALUES(order_track)");
                $db->bind(':oid', (int) $p->oid);
                $db->bind(':trk', $track);
                $db->cdp_execute();
            }
            $n++;
        }
        return $n;
    }
}

if (!function_exists('cdp_pickupAgingPending')) {
    /**
     * Packages that have been at Ready-for-Pickup for >= 14 days and have NOT yet
     * had their senders notified — these drive the admin popup / page.
     */
    function cdp_pickupAgingPending()
    {
        $db = new Conexion;
        $db->cdp_query("
            SELECT p.order_id, p.order_track, p.sender_id, p.ready_at,
                   DATEDIFF(NOW(), p.ready_at) AS days_ready
            FROM cdb_package_pickup_aging p
            JOIN cdb_add_order a ON a.order_id = p.order_id
            WHERE p.notified_at IS NULL
              AND a.status_courier = " . CDP_PA_READY . "
              AND a.fs_cleared_for_delivery = 1
              AND p.ready_at <= (NOW() - INTERVAL " . (int) CDP_PA_READY_DAYS . " DAY)
            ORDER BY p.ready_at ASC");
        $db->cdp_execute();
        return $db->cdp_registros();
    }
}

if (!function_exists('cdp_processPickupAging')) {
    /**
     * Idempotent engine: discover newly-ready packages, run the time-based
     * auto-transitions, and drop packages that left the chain. Safe to run often
     * (cron + dashboard). Returns a counts summary.
     */
    function cdp_processPickupAging()
    {
        $db = new Conexion;
        $auctionId = cdp_pickupAgingAuctionId();
        $summary = ['discovered' => 0, 'not_picked' => 0, 'auction' => 0, 'dropped' => 0, 'pending' => 0];

        // 1) Discover packages that are CLEARED FOR DELIVERY and at Ready-for-
        //    Pickup but not tracked yet. Aging now follows CLEARANCE: only
        //    Accounts-cleared packages age, and the pickup clock starts at the
        //    moment of clearance (fs_cleared_at), NOT at billing time. Uncleared
        //    packages (incl. old pre-clearance ones) never enter the pipeline.
        $db->cdp_query("
            SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.fs_cleared_at
            FROM cdb_add_order a
            LEFT JOIN cdb_package_pickup_aging p ON p.order_id = a.order_id
            WHERE a.status_courier = " . CDP_PA_READY . "
              AND a.fs_cleared_for_delivery = 1
              AND p.order_id IS NULL");
        $db->cdp_execute();
        foreach ($db->cdp_registros() as $row) {
            $track = $row->order_prefix . $row->order_no;
            $readyAt = !empty($row->fs_cleared_at) ? $row->fs_cleared_at : date('Y-m-d H:i:s');

            $t = new Conexion;
            $t->cdp_query("INSERT INTO cdb_package_pickup_aging (order_id, order_track, sender_id, ready_at)
                           VALUES (:oid, :trk, :sid, :ra)
                           ON DUPLICATE KEY UPDATE order_track = VALUES(order_track)");
            $t->bind(':oid', (int) $row->order_id);
            $t->bind(':trk', $track);
            $t->bind(':sid', (int) $row->sender_id);
            $t->bind(':ra', $readyAt);
            $t->cdp_execute();
            $summary['discovered']++;
        }

        // 2) Drop packages that left the pickup chain (picked up / delivered) OR
        //    are no longer cleared for delivery (clearance revoked by Accounts).
        $db->cdp_query("
            DELETE p FROM cdb_package_pickup_aging p
            JOIN cdb_add_order a ON a.order_id = p.order_id
            WHERE a.fs_cleared_for_delivery <> 1
               OR a.status_courier NOT IN (" . CDP_PA_READY . ", " . CDP_PA_PENDING . ", " . CDP_PA_NOTPICKED . ", " . (int) $auctionId . ")");
        $db->cdp_execute();
        $summary['dropped'] = (int) $db->cdp_rowCount();

        // 3) Pending Collection + 7d  ->  Not Picked Up (auto, no message).
        $db->cdp_query("
            SELECT p.order_id, p.order_track
            FROM cdb_package_pickup_aging p
            JOIN cdb_add_order a ON a.order_id = p.order_id
            WHERE p.notified_at IS NOT NULL AND p.not_picked_at IS NULL
              AND a.status_courier = " . CDP_PA_PENDING . "
              AND p.notified_at <= (NOW() - INTERVAL " . (int) CDP_PA_NOTPICK_DAYS . " DAY)");
        $db->cdp_execute();
        foreach ($db->cdp_registros() as $row) {
            cdp_pickupAgingSetStatus($row->order_id, $row->order_track, CDP_PA_NOTPICKED, 'Pickup aging: auto -> Not Picked Up', 0);
            $u = new Conexion;
            $u->cdp_query("UPDATE cdb_package_pickup_aging SET not_picked_at = NOW() WHERE order_id = :id");
            $u->bind(':id', (int) $row->order_id);
            $u->cdp_execute();
            $summary['not_picked']++;
        }

        // 4) Not Picked Up + 7d  ->  Auction (auto, no message).
        if ($auctionId > 0) {
            $db->cdp_query("
                SELECT p.order_id, p.order_track
                FROM cdb_package_pickup_aging p
                JOIN cdb_add_order a ON a.order_id = p.order_id
                WHERE p.not_picked_at IS NOT NULL AND p.auction_at IS NULL
                  AND a.status_courier = " . CDP_PA_NOTPICKED . "
                  AND p.not_picked_at <= (NOW() - INTERVAL " . (int) CDP_PA_AUCTION_DAYS . " DAY)");
            $db->cdp_execute();
            foreach ($db->cdp_registros() as $row) {
                cdp_pickupAgingSetStatus($row->order_id, $row->order_track, $auctionId, 'Pickup aging: auto -> Auction', 0);
                $u = new Conexion;
                $u->cdp_query("UPDATE cdb_package_pickup_aging SET auction_at = NOW() WHERE order_id = :id");
                $u->bind(':id', (int) $row->order_id);
                $u->cdp_execute();
                $summary['auction']++;
            }
        }

        $summary['pending'] = count(cdp_pickupAgingPending());
        return $summary;
    }
}

if (!function_exists('cdp_pickupAgingNotifySenders')) {
    /**
     * The admin "Yes, notify senders" action. For each eligible (pending) package:
     * set status -> Pending Collection, message the sender on WhatsApp + email from
     * the templates (stating the new status), and record the acting admin + the
     * messages in the ledger and the order history.
     *
     * @return array ['updated'=>int,'sent'=>int,'skipped'=>int]
     */
    function cdp_pickupAgingNotifySenders($orderIds, $userId)
    {
        require_once __DIR__ . '/whatsapp.php';
        require_once __DIR__ . '/notify_placeholders.php';
        if (function_exists('cdp_wa_requireV2')) cdp_wa_requireV2();

        $core = new Core;
        $settings = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
        $tpl = cdp_pickupAgingTemplateIds();
        $result = ['updated' => 0, 'sent' => 0, 'skipped' => 0];

        $ids = array_values(array_unique(array_map('intval', (array) $orderIds)));
        if (!$ids) return $result;

        // Only act on rows that are genuinely pending (>=14d, not yet notified, still 32).
        $allow = [];
        foreach (cdp_pickupAgingPending() as $p) $allow[(int) $p->order_id] = $p;

        $statusLabel = 'Pending Collection';
        $company = !empty($settings->site_name) ? $settings->site_name : ($core->site_name ?? 'Our Company');

        foreach ($ids as $oid) {
            if (!isset($allow[$oid])) { $result['skipped']++; continue; }
            $row   = $allow[$oid];
            $track = $row->order_track;

            // 1) Status -> Pending Collection (+ track row, actor recorded).
            cdp_pickupAgingSetStatus($oid, $track, CDP_PA_PENDING, 'Pickup aging: sender notified -> Pending Collection', $userId);
            $result['updated']++;

            // 2) Build sender + details.
            $sender = function_exists('cdp_getSenderCourier') ? cdp_getSenderCourier((int) $row->sender_id) : null;
            $name   = ($sender && function_exists('cdp_nameWithLocker'))
                ? cdp_nameWithLocker($sender)
                : trim((($sender->fname ?? '') . ' ' . ($sender->lname ?? '')));
            $ph     = function_exists('cdp_buildPackageNotifyPlaceholders') ? cdp_buildPackageNotifyPlaceholders($oid, 'air') : [];
            $appUrl = rtrim((string) ($settings->site_url ?? ($core->site_url ?? '')), '/') . '/track.php?order_track=' . rawurlencode($track);
            $logParts = [];

            // 3) WhatsApp.
            if ($sender && !empty($sender->phone) && $tpl['whatsapp'] && function_exists('cdp_renderWhatsAppTemplate')) {
                $body = cdp_renderWhatsAppTemplate($tpl['whatsapp'], [
                    '[CUSTOMER_FULLNAME]' => $name,
                    '[TRACKING_NUMBER]'   => $track,
                    '[STATUS]'            => $statusLabel,
                    '[COMPANY_NAME]'      => $company,
                    '[APP_URL]'           => $appUrl,
                ]);
                if ($body !== null && function_exists('sendNotificationWhatsApp_v2')) {
                    $r = sendNotificationWhatsApp_v2($sender, $body);
                    if (!empty($r['success'])) { $result['sent']++; $logParts[] = 'whatsapp ok'; }
                    else { $result['skipped']++; $logParts[] = 'whatsapp: ' . ($r['message'] ?? 'fail'); }
                }
            }

            // 4) Email.
            if ($sender && !empty($sender->email) && $tpl['email'] && function_exists('cdp_sendTemplateEmail')) {
                $er = cdp_sendTemplateEmail($tpl['email'], $sender->email, [
                    '[CUSTOMER_FULLNAME]' => $name,
                    '[TRACKING_NUMBER]'   => $track,
                    '[STATUS]'            => $statusLabel,
                    '[SHIPMENT_DETAILS]'  => $ph['[SHIPMENT_DETAILS]'] ?? '',
                ]);
                if (!empty($er['ok'])) { $result['sent']++; $logParts[] = 'email ok'; }
                else { $result['skipped']++; $logParts[] = 'email: ' . ($er['error'] ?? 'fail'); }
            }

            // 5) Record actor + time + message log on the ledger, and the history.
            $log = date('Y-m-d H:i') . ' by user ' . (int) $userId . ' — ' . (implode('; ', $logParts) ?: 'no channels');
            $db = new Conexion;
            $db->cdp_query("UPDATE cdb_package_pickup_aging
                            SET notified_by = :u, notified_at = NOW(),
                                messages_log = CONCAT(COALESCE(messages_log, ''), :log, '\n')
                            WHERE order_id = :id");
            $db->bind(':u', (int) $userId);
            $db->bind(':log', $log);
            $db->bind(':id', $oid);
            $db->cdp_execute();

            if (function_exists('cdp_insertCourierShipmentUserHistory')) {
                cdp_insertCourierShipmentUserHistory([
                    'user_id'      => $userId,
                    'order_id'     => $oid,
                    'order_track'  => $track,
                    'action'       => 'Pickup Aging — notified sender; set Pending Collection',
                    'date_history' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return $result;
    }
}
