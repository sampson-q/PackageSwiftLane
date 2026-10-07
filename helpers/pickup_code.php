<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Pickup codes — a package is handed over only to the person holding the code
 * its OWNER was sent.
 *
 * Flow:
 *   1. Staff generate a code for one collection: one or more packages of ONE
 *      owner (cdb_add_order / cdb_customers_packages .sender_id). Packages of
 *      different owners are refused, so a code never reaches anyone about a
 *      package that is not theirs.
 *   2. The code goes to the owner by SMS, WhatsApp and e-mail. It lives
 *      CDP_PICKUP_CODE_TTL seconds; while it lives no new one can be sent for
 *      those packages, after it expires staff can send a new one.
 *   3. The owner reads the code out, staff enter it: the code is verified.
 *   4. Every path that sets a package to Delivered (8) or Picked up (15) calls
 *      cdp_pickupCodeGate() first and cdp_pickupCodeConsume() after: each
 *      package needs a verified, unused code from the last
 *      CDP_PICKUP_VERIFIED_TTL seconds. No exceptions.
 *
 * Tables (created on first use, like cdb_sms_settings):
 *   cdb_pickup_codes       one row per code sent
 *   cdb_pickup_code_items  the packages a code covers, with used_at
 */

require_once __DIR__ . '/querys.php';

if (!defined('CDP_PICKUP_CODE_TTL')) {
    define('CDP_PICKUP_CODE_TTL', 180);        // the code is valid for 3 minutes
    define('CDP_PICKUP_VERIFIED_TTL', 1800);   // a verified code allows handover for 30 minutes
    define('CDP_PICKUP_MAX_ATTEMPTS', 5);      // wrong entries before the code is void
}

if (!function_exists('cdp_pickupCodeEnsureTables')) {

    function cdp_pickupCodeEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_pickup_codes (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code        VARCHAR(10)  NOT NULL,
                module      VARCHAR(8)   NOT NULL,
                owner_id    INT          NOT NULL,
                trackings   VARCHAR(2000) NOT NULL DEFAULT '',
                context     VARCHAR(40)  NOT NULL DEFAULT '',
                status      VARCHAR(12)  NOT NULL DEFAULT 'active',
                attempts    INT          NOT NULL DEFAULT 0,
                channels    VARCHAR(255) NOT NULL DEFAULT '',
                created_by  INT          NULL,
                created_at  DATETIME     NOT NULL,
                expires_at  DATETIME     NOT NULL,
                verified_by INT          NULL,
                verified_at DATETIME     NULL,
                PRIMARY KEY (id),
                KEY idx_owner (owner_id),
                KEY idx_status (status, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $ok = ($db->cdp_execute() !== false);
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_pickup_code_items (
                code_id     INT UNSIGNED NOT NULL,
                module      VARCHAR(8)   NOT NULL,
                order_id    INT          NOT NULL,
                used_at     DATETIME     NULL,
                used_by     INT          NULL,
                PRIMARY KEY (code_id, order_id),
                KEY idx_pkg (module, order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $ready = $ok && ($db->cdp_execute() !== false);
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** Package table of a module ('air' shipments / pickups, 'sea' customer packages). */
    function cdp_pickupCodeTable($module)
    {
        return $module === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
    }

    function cdp_pickupCodeModule($module)
    {
        return $module === 'sea' ? 'sea' : 'air';
    }

    /** Statuses that hand a package over and therefore need a verified code. */
    function cdp_pickupCodeGatedStatus($status)
    {
        return in_array((int) $status, [8, 15], true);
    }

    /** Staff who may send, verify and see pickup codes. Never a customer. */
    function cdp_pickupCodeCanUse($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        require_once __DIR__ . '/rbac.php';
        if (function_exists('cdp_roleIsClient') && cdp_roleIsClient((int) $user->userlevel)) {
            return false;
        }
        return $user->cdp_hasPermission([
            'deliver_shipment', 'deliver_package', 'view_shipment_list', 'view_pickup_list',
            'view_client_list', 'view_warehouse_delivery',
        ]);
    }

    /** Mark codes whose time ran out (lazy; called before every read). */
    function cdp_pickupCodeExpireStale()
    {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_pickup_codes SET status = 'expired'
                        WHERE status = 'active' AND expires_at < :now");
        $db->bind(':now', date('Y-m-d H:i:s'));
        $db->cdp_execute();
    }

    /**
     * The packages, each with its owner. Order ids only — never order numbers,
     * which repeat across customers.
     *
     * @return object[] order_id, order_prefix, order_no, sender_id, status_courier
     */
    function cdp_pickupCodePackages($module, array $orderIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$ids) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT order_id, order_prefix, order_no, sender_id, status_courier
                        FROM " . cdp_pickupCodeTable($module) . "
                        WHERE order_id IN (" . implode(',', $ids) . ")
                        ORDER BY order_id");
        return (array) $db->cdp_registros();
    }

    /** Mask a phone / e-mail for the staff screen: +233 24*** **67, jo***@x.com */
    function cdp_pickupCodeMask($value, $isEmail = false)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if ($isEmail) {
            $at = strpos($value, '@');
            return $at === false ? '***' : mb_substr($value, 0, min(2, $at)) . '***' . mb_substr($value, $at);
        }
        $d = preg_replace('/\D+/', '', $value);
        return strlen($d) <= 4 ? '***' : str_repeat('*', strlen($d) - 4) . substr($d, -4);
    }

    /**
     * Send a new code for one collection.
     *
     * @return array{success:bool,message:string,code_id?:int,expires_in?:int,owner?:string,trackings?:string[],channels?:string[]}
     */
    function cdp_pickupCodeGenerate($module, array $orderIds, $context, $staffId)
    {
        $module = cdp_pickupCodeModule($module);
        if (!cdp_pickupCodeEnsureTables()) {
            return ['success' => false, 'message' => 'The pickup code tables could not be created.'];
        }
        cdp_pickupCodeExpireStale();

        $pkgs = cdp_pickupCodePackages($module, $orderIds);
        if (!$pkgs || count($pkgs) !== count(array_unique(array_filter(array_map('intval', $orderIds))))) {
            return ['success' => false, 'message' => 'Package not found.'];
        }
        $owners = array_values(array_unique(array_map(function ($p) { return (int) $p->sender_id; }, $pkgs)));
        if (count($owners) !== 1 || $owners[0] <= 0) {
            return ['success' => false, 'message' => count($owners) > 1
                ? 'These packages belong to different customers. Send one code per customer.'
                : 'This package has no owner on record, so no code can be sent.'];
        }
        $done = array_filter($pkgs, function ($p) { return cdp_pickupCodeGatedStatus($p->status_courier); });
        if ($done) {
            $first = reset($done);
            return ['success' => false, 'message' => 'Package ' . $first->order_prefix . $first->order_no . ' has already been handed over.'];
        }

        $ids = array_map(function ($p) { return (int) $p->order_id; }, $pkgs);
        $db  = new Conexion;

        // One live code per package: a new one only after the last one expired.
        $db->cdp_query("SELECT c.id, c.expires_at FROM cdb_pickup_codes c
                        INNER JOIN cdb_pickup_code_items i ON i.code_id = c.id
                        WHERE c.status = 'active' AND i.module = :m AND i.order_id IN (" . implode(',', $ids) . ")
                        ORDER BY c.expires_at DESC LIMIT 1");
        $db->bind(':m', $module);
        $live = $db->cdp_registro();
        if ($live) {
            $left = max(0, strtotime($live->expires_at) - time());
            return ['success' => false, 'message' => 'A code was already sent for this package. A new one can be sent in ' . $left . ' seconds.',
                    'code_id' => (int) $live->id, 'expires_in' => $left, 'live' => true];
        }

        $owner = cdp_getSenderCourier($owners[0]);
        if (!$owner) {
            return ['success' => false, 'message' => 'The owner of this package was not found.'];
        }
        $phone = trim((string) ($owner->phone ?? ''));
        $email = trim((string) ($owner->email ?? ''));
        if ($phone === '' && ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return ['success' => false, 'message' => 'The owner has no phone number or e-mail on record, so the code cannot be sent.'];
        }

        $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $trackings = array_map(function ($p) { return $p->order_prefix . $p->order_no; }, $pkgs);
        $now       = date('Y-m-d H:i:s');

        $db->cdp_query("INSERT INTO cdb_pickup_codes
                        (code, module, owner_id, trackings, context, status, created_by, created_at, expires_at)
                        VALUES (:code, :m, :o, :t, :ctx, 'active', :by, :now, :exp)");
        $db->bind(':code', $code);
        $db->bind(':m', $module);
        $db->bind(':o', (int) $owner->id);
        $db->bind(':t', mb_substr(implode(', ', $trackings), 0, 2000));
        $db->bind(':ctx', mb_substr((string) $context, 0, 40));
        $db->bind(':by', (int) $staffId);
        $db->bind(':now', $now);
        $db->bind(':exp', date('Y-m-d H:i:s', time() + CDP_PICKUP_CODE_TTL));
        $db->cdp_execute();
        $codeId = (int) $db->dbh->lastInsertId();

        foreach ($ids as $oid) {
            $db->cdp_query("INSERT INTO cdb_pickup_code_items (code_id, module, order_id) VALUES (:c, :m, :o)");
            $db->bind(':c', $codeId);
            $db->bind(':m', $module);
            $db->bind(':o', $oid);
            $db->cdp_execute();
        }

        $channels = [];
        if ($phone !== '') {
            $channels[] = 'SMS ' . cdp_pickupCodeMask($phone);
            $channels[] = 'WhatsApp ' . cdp_pickupCodeMask($phone);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'E-mail ' . cdp_pickupCodeMask($email, true);
        }
        $db->cdp_query("UPDATE cdb_pickup_codes SET channels = :ch WHERE id = :id");
        $db->bind(':ch', mb_substr(implode(', ', $channels), 0, 255));
        $db->bind(':id', $codeId);
        $db->cdp_execute();

        // Sent after the response so the staff screen shows the code's timer at
        // once; the message log records what each channel did.
        $send = function () use ($owner, $code, $trackings, $codeId) {
            cdp_pickupCodeSend($owner, $code, $trackings, $codeId);
        };
        require_once __DIR__ . '/after_response.php';
        cdp_afterResponse($send);

        return [
            'success'    => true,
            'message'    => 'Pickup code sent.',
            'code_id'    => $codeId,
            'expires_in' => CDP_PICKUP_CODE_TTL,
            'owner'      => cdp_nameWithLocker($owner),
            'trackings'  => $trackings,
            'channels'   => $channels,
        ];
    }

    /** SMS + WhatsApp + e-mail to the owner. Only their own package numbers. */
    function cdp_pickupCodeSend($owner, $code, array $trackings, $codeId)
    {
        $core     = new Core;
        $site     = trim((string) ($core->site_name ?? ''));
        $minutes  = (int) round(CDP_PICKUP_CODE_TTL / 60);
        $label    = (count($trackings) > 1 ? 'packages ' : 'package ') . implode(', ', $trackings);
        $text     = ($site !== '' ? $site . ': ' : '') . 'Pickup code for ' . $label . ': ' . $code . '. '
                  . 'This code expires in ' . $minutes . ' minutes. Our staff ask for this code at handover.';
        $subject  = 'Pickup Code: ' . $code;

        $ctx = [
            'source'            => 'pickup_code',
            'source_label'      => 'Pickup Code #' . (int) $codeId,
            'entity_type'       => 'pickup_code',
            'entity_id'         => (string) (int) $codeId,
            'entity_label'      => mb_substr(implode(', ', $trackings), 0, 190),
            'subject'           => $subject,
            'recipient_user_id' => (int) ($owner->id ?? 0),
            'recipient_name'    => trim((string) ($owner->fname ?? '') . ' ' . (string) ($owner->lname ?? '')),
        ];
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext($ctx);
        }

        try {
            // SMS — always, like sign-in codes: the SMS switch and the
            // customer's opt-out do not hold a pickup code back.
            require_once __DIR__ . '/whatsapp.php';
            require_once __DIR__ . '/hubtel_sms.php';
            if (trim((string) ($owner->phone ?? '')) !== '') {
                cdp_smsToRecipient($owner, $text);
            }
        } catch (Throwable $e) {
            error_log('pickup code SMS: ' . $e->getMessage());
        }

        try {
            // WhatsApp — its own SMS copy is off: the SMS above is the one.
            if (trim((string) ($owner->phone ?? '')) !== '') {
                require_once dirname(__DIR__) . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';
                sendNotificationWhatsApp_v2($owner, $text, null, ['allow_sms' => false]);
            }
        } catch (Throwable $e) {
            error_log('pickup code WhatsApp: ' . $e->getMessage());
        }

        try {
            $email = trim((string) ($owner->email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                require_once __DIR__ . '/phpmailer/class.phpmailer.php';
                require_once __DIR__ . '/phpmailer/class.smtp.php';
                $html = '<p style="margin:0 0 12px 0;">Pickup code for ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</p>'
                      . '<p style="margin:0 0 12px 0;font-size:28px;font-weight:700;letter-spacing:6px;color:#1a1a1a;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
                      . '<p style="margin:0;">This code expires in ' . $minutes . ' minutes. Our staff ask for this code at handover.</p>';
                cdp_sendTemplateEmail(29, $email, [
                    '[USERNAME]' => htmlspecialchars(cdp_nameWithLocker($owner), ENT_QUOTES, 'UTF-8'),
                    '[MESSAGE]'  => $html,
                ], $subject . ($site !== '' ? ' — ' . $site : ''));
            }
        } catch (Throwable $e) {
            error_log('pickup code e-mail: ' . $e->getMessage());
        }

        if (function_exists('cdp_msgClearContext')) {
            cdp_msgClearContext(array_keys($ctx));
        }
    }

    /**
     * Staff enter the code the owner read out.
     *
     * @return array{success:bool,message:string}
     */
    function cdp_pickupCodeVerify($codeId, $entered, $staffId)
    {
        if (!cdp_pickupCodeEnsureTables()) {
            return ['success' => false, 'message' => 'The pickup code tables could not be created.'];
        }
        cdp_pickupCodeExpireStale();
        $entered = preg_replace('/\D+/', '', (string) $entered);

        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_pickup_codes WHERE id = :id LIMIT 1");
        $db->bind(':id', (int) $codeId);
        $row = $db->cdp_registro();
        if (!$row) {
            return ['success' => false, 'message' => 'Pickup code not found.'];
        }
        if ($row->status === 'verified') {
            return ['success' => true, 'message' => 'This code is already verified.'];
        }
        if ($row->status !== 'active') {
            return ['success' => false, 'message' => $row->status === 'expired'
                ? 'This code has expired. Send a new one.' : 'This code can no longer be used. Send a new one.'];
        }
        if ($entered === '' || !hash_equals((string) $row->code, $entered)) {
            $attempts = (int) $row->attempts + 1;
            $void     = $attempts >= CDP_PICKUP_MAX_ATTEMPTS;
            $db->cdp_query("UPDATE cdb_pickup_codes SET attempts = :a" . ($void ? ", status = 'void'" : '') . " WHERE id = :id");
            $db->bind(':a', $attempts);
            $db->bind(':id', (int) $row->id);
            $db->cdp_execute();
            return ['success' => false, 'message' => $void
                ? 'Too many wrong entries. This code is void; send a new one.'
                : 'Wrong code. ' . (CDP_PICKUP_MAX_ATTEMPTS - $attempts) . ' attempt(s) left.'];
        }

        $db->cdp_query("UPDATE cdb_pickup_codes SET status = 'verified', verified_by = :by, verified_at = :now WHERE id = :id AND status = 'active'");
        $db->bind(':by', (int) $staffId);
        $db->bind(':now', date('Y-m-d H:i:s'));
        $db->bind(':id', (int) $row->id);
        $db->cdp_execute();
        return ['success' => true, 'message' => 'Code verified. The package can be handed over.'];
    }

    /**
     * The verified, unused code item covering each package, if any.
     *
     * @return array<int,int> order_id => code_id
     */
    function cdp_pickupCodeVerifiedFor($module, array $orderIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$ids || !cdp_pickupCodeEnsureTables()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT i.order_id, c.id AS code_id FROM cdb_pickup_code_items i
                        INNER JOIN cdb_pickup_codes c ON c.id = i.code_id
                        WHERE i.module = :m AND i.order_id IN (" . implode(',', $ids) . ")
                          AND i.used_at IS NULL AND c.status = 'verified' AND c.verified_at >= :since
                        ORDER BY c.verified_at DESC");
        $db->bind(':m', cdp_pickupCodeModule($module));
        $db->bind(':since', date('Y-m-d H:i:s', time() - CDP_PICKUP_VERIFIED_TTL));
        $out = [];
        foreach ((array) $db->cdp_registros() as $r) {
            if (!isset($out[(int) $r->order_id])) {
                $out[(int) $r->order_id] = (int) $r->code_id;
            }
        }
        return $out;
    }

    /**
     * Gate for every handover. Packages already Delivered / Picked up are not
     * re-checked (no change); every other package needs a verified code.
     *
     * @return array{ok:bool,message:string,missing:int[],trackings:string[]}
     */
    function cdp_pickupCodeGate($module, array $orderIds, $newStatus)
    {
        $ok = ['ok' => true, 'message' => '', 'missing' => [], 'trackings' => []];
        if (!cdp_pickupCodeGatedStatus($newStatus)) {
            return $ok;
        }
        $pkgs = array_filter(cdp_pickupCodePackages($module, $orderIds), function ($p) {
            return !cdp_pickupCodeGatedStatus($p->status_courier);
        });
        if (!$pkgs) {
            return $ok;
        }
        $have    = cdp_pickupCodeVerifiedFor($module, array_map(function ($p) { return $p->order_id; }, $pkgs));
        $missing = array_values(array_filter($pkgs, function ($p) use ($have) { return !isset($have[(int) $p->order_id]); }));
        if (!$missing) {
            return $ok;
        }
        $tracks = array_map(function ($p) { return $p->order_prefix . $p->order_no; }, $missing);
        return [
            'ok'        => false,
            'message'   => 'Pickup code not verified for ' . implode(', ', $tracks)
                         . '. Send the owner a pickup code and enter it before handing the package over.',
            'missing'   => array_map(function ($p) { return (int) $p->order_id; }, $missing),
            'trackings' => $tracks,
        ];
    }

    /**
     * Where a collection stands: verified for every package, or a live code
     * waiting to be entered, or nothing yet.
     *
     * @return array{verified:bool,live:?array}
     */
    function cdp_pickupCodeState($module, array $orderIds)
    {
        $module = cdp_pickupCodeModule($module);
        if (!cdp_pickupCodeEnsureTables()) {
            return ['verified' => false, 'live' => null];
        }
        cdp_pickupCodeExpireStale();
        $ids  = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $have = cdp_pickupCodeVerifiedFor($module, $ids);
        if ($ids && count($have) === count($ids)) {
            return ['verified' => true, 'live' => null];
        }
        $live = null;
        if ($ids) {
            $db = new Conexion;
            $db->cdp_query("SELECT c.id, c.expires_at, c.channels FROM cdb_pickup_codes c
                            INNER JOIN cdb_pickup_code_items i ON i.code_id = c.id
                            WHERE c.status = 'active' AND i.module = :m AND i.order_id IN (" . implode(',', $ids) . ")
                            ORDER BY c.expires_at DESC LIMIT 1");
            $db->bind(':m', $module);
            $r = $db->cdp_registro();
            if ($r) {
                $live = ['code_id' => (int) $r->id, 'expires_in' => max(0, strtotime($r->expires_at) - time()),
                         'channels' => $r->channels !== '' ? explode(', ', $r->channels) : []];
            }
        }
        return ['verified' => false, 'live' => $live];
    }

    /** After the handover is saved: the code items are spent. */
    function cdp_pickupCodeConsume($module, array $orderIds, $staffId)
    {
        $have = cdp_pickupCodeVerifiedFor($module, $orderIds);
        if (!$have) {
            return;
        }
        $db  = new Conexion;
        $now = date('Y-m-d H:i:s');
        foreach ($have as $oid => $codeId) {
            $db->cdp_query("UPDATE cdb_pickup_code_items SET used_at = :now, used_by = :by
                            WHERE code_id = :c AND module = :m AND order_id = :o AND used_at IS NULL");
            $db->bind(':now', $now);
            $db->bind(':by', (int) $staffId);
            $db->bind(':c', $codeId);
            $db->bind(':m', cdp_pickupCodeModule($module));
            $db->bind(':o', $oid);
            $db->cdp_execute();
        }
        foreach (array_unique(array_values($have)) as $codeId) {
            $db->cdp_query("UPDATE cdb_pickup_codes SET status = 'used'
                            WHERE id = :c AND NOT EXISTS (SELECT 1 FROM cdb_pickup_code_items WHERE code_id = :c2 AND used_at IS NULL)");
            $db->bind(':c', $codeId);
            $db->bind(':c2', $codeId);
            $db->cdp_execute();
        }
    }

    /**
     * Order ids behind order numbers, for the endpoints that post numbers.
     * A number shared by several packages that are not yet handed over is
     * ambiguous: it is reported, never guessed.
     *
     * @return array{ids:int[],ambiguous:string[]}
     */
    function cdp_pickupCodeResolveNumbers($module, array $orderNos)
    {
        $out = ['ids' => [], 'ambiguous' => []];
        $db  = new Conexion;
        foreach ($orderNos as $no) {
            $no = trim((string) $no);
            if ($no === '') {
                continue;
            }
            $db->cdp_query("SELECT order_id, order_prefix, order_no, status_courier FROM " . cdp_pickupCodeTable($module) . " WHERE order_no = :no");
            $db->bind(':no', $no);
            $rows = array_values(array_filter((array) $db->cdp_registros(), function ($r) {
                return !cdp_pickupCodeGatedStatus($r->status_courier);
            }));
            if (count($rows) > 1) {
                $out['ambiguous'][] = $rows[0]->order_prefix . $rows[0]->order_no;
            } elseif (count($rows) === 1) {
                $out['ids'][] = (int) $rows[0]->order_id;
            }
        }
        return $out;
    }

    /**
     * "Pickup Code" item for a row's actions menu (dataJs/pickup_code.js opens
     * the modal). Empty for customers and for packages already handed over.
     *
     * @param int|int[] $orderIds one package, or one owner's collection
     */
    function cdp_pickupCodeMenuItem($module, $orderIds, $context, $status = 0, $tag = 'a')
    {
        static $can = null;
        if ($can === null) {
            global $user;
            $can = isset($user) && cdp_pickupCodeCanUse($user);
        }
        $ids = array_values(array_filter(array_map('intval', (array) $orderIds)));
        if (!$can || !$ids || cdp_pickupCodeGatedStatus($status)) {
            return '';
        }
        $attrs = ' data-module="' . cdp_pickupCodeModule($module) . '" data-order-ids="' . implode(',', $ids) . '"'
               . ' data-context="' . htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8') . '"';
        if ($tag === 'button') {
            return '<button type="button" class="btn btn-sm btn-outline-dark cdp-pickup-code"' . $attrs . '>'
                 . '<i class="fas fa-key"></i>&nbsp;Pickup Code</button>';
        }
        return '<a class="dropdown-item cdp-pickup-code" href="javascript:void(0)"' . $attrs . '>'
             . '&nbsp;<i style="color:#343a40" class="fas fa-key"></i>&nbsp;Pickup Code</a>';
    }

    /**
     * Codes staff can see while they last: live ones, and verified ones still
     * inside their handover window.
     *
     * @return object[]
     */
    function cdp_pickupCodeActiveList()
    {
        if (!cdp_pickupCodeEnsureTables()) {
            return [];
        }
        cdp_pickupCodeExpireStale();
        $db = new Conexion;
        $db->cdp_query("SELECT c.id, c.code, c.module, c.owner_id, c.trackings, c.status, c.channels,
                               c.created_at, c.expires_at, c.verified_at,
                               u.fname, u.lname, u.locker,
                               s.fname AS staff_fname, s.lname AS staff_lname
                        FROM cdb_pickup_codes c
                        LEFT JOIN cdb_users u ON u.id = c.owner_id
                        LEFT JOIN cdb_users s ON s.id = c.created_by
                        WHERE (c.status = 'active' AND c.expires_at >= :now)
                           OR (c.status = 'verified' AND c.verified_at >= :since)
                        ORDER BY c.created_at DESC
                        LIMIT 200");
        $db->bind(':now', date('Y-m-d H:i:s'));
        $db->bind(':since', date('Y-m-d H:i:s', time() - CDP_PICKUP_VERIFIED_TTL));
        return (array) $db->cdp_registros();
    }
}
