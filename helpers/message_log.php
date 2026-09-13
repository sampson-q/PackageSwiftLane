<?php
/**
 * ============================================================================
 * Message Log — one row for every outbound WhatsApp / e-mail / SMS.
 *
 * "What was sent, to whom, by whom, when, and did it go" — across the whole
 * system, from one table (cdb_message_log, sql/message_log.sql).
 *
 * Capture is CENTRAL, at the three send primitives, so no caller has to
 * remember to log:
 *   - sendNotificationWhatsApp_v2()   ajax/notify_whatsapp/api_whatsapp_service_v2.php
 *   - sendNotificationSMS()           ajax/notify_sms/api_sms_service.php
 *   - PHPMailer                       helpers/phpmailer/class.phpmailer.php
 *                                     (default action_function + failure hook)
 *
 * Callers that know more than the primitive does (which shipment, which
 * template, which broadcast) describe it with cdp_msgSetContext() before
 * sending and cdp_msgClearContext() after. Without a context the row still
 * carries recipient, body, status, actor and an origin inferred from the
 * endpoint.
 *
 * Everything here is wrapped so logging can never break a send, and a missing
 * table simply no-ops.
 * ============================================================================
 */

require_once __DIR__ . '/activity_log.php';

/** Is cdb_message_log present? Cached per request. */
function cdp_msgTableReady()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $db = new Conexion;
        $db->cdp_query("SHOW TABLES LIKE 'cdb_message_log'");
        $db->cdp_execute();
        $ready = $db->cdp_rowCount() > 0;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/** Timestamp in the configured timezone (shares the activity log's clock). */
function cdp_msgNow()
{
    return function_exists('cdp_activityNow') ? cdp_activityNow() : date('Y-m-d H:i:s');
}

/** A short id that groups every message of one broadcast / bulk action. */
function cdp_msgNewBatchId($prefix = 'b')
{
    try {
        return $prefix . '-' . date('ymdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    } catch (Throwable $e) {
        return $prefix . '-' . date('ymdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);
    }
}

// ---------------------------------------------------------------------------
// Context — what the caller knows about the message being sent.
//
// Keys: source, source_label, template_id, entity_type, entity_id,
//       entity_label, batch_id, recipient_user_id, recipient_name, subject
// ---------------------------------------------------------------------------
function &cdp_msgContextStore()
{
    static $ctx = [];
    return $ctx;
}

/** Merge details into the current context (later keys win). */
function cdp_msgSetContext(array $ctx)
{
    $store = &cdp_msgContextStore();
    $store = array_merge($store, $ctx);
}

/** Drop the context, or only the listed keys. */
function cdp_msgClearContext(?array $keys = null)
{
    $store = &cdp_msgContextStore();
    if ($keys === null) {
        $store = [];
        return;
    }
    foreach ($keys as $k) {
        unset($store[$k]);
    }
}

function cdp_msgContext()
{
    return cdp_msgContextStore();
}

// ---------------------------------------------------------------------------
// Origin — where in the application the message came from.
// ---------------------------------------------------------------------------

/**
 * Slug + label for the current endpoint. Ordered: the first matching token
 * wins, so the more specific names come first.
 *
 * @return array{0:string,1:string}
 */
function cdp_msgInferSource()
{
    $endpoint = function_exists('cdp_activityEndpoint') ? cdp_activityEndpoint() : (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $file = strtolower(basename($endpoint, '.php'));
    if ($file === '' && PHP_SAPI === 'cli') {
        $file = strtolower(basename((string) ($_SERVER['argv'][0] ?? 'cli'), '.php'));
    }

    $map = [
        ['push_notifications_consolidation', 'push_notification_consolidation', 'Push Notification (Consolidation)'],
        ['push_notifications_invoice',       'push_notification_invoice',       'Push Notification (Invoice)'],
        ['push_notifications',               'push_notification',               'Push Notification'],
        ['notify_pending_payments',          'pending_payments',                'Pending Payments Reminder'],
        ['notify_general_customers',         'general_notice',                  'General Customer Notice'],
        ['pickup_aging',                     'pickup_aging',                    'Pickup Aging Reminder'],
        ['send_profile_phone_otp',           'otp',                             'One-Time Password'],
        ['auth-otp',                         'otp',                             'One-Time Password'],
        ['forgot',                           'otp',                             'Password Reset'],
        ['login',                            'otp',                             'Login Verification'],
        ['sign-up',                          'signup',                          'Sign Up'],
        ['update_driver',                    'driver_assignment',               'Driver Assignment'],
        ['update_multiple',                  'bulk_status_update',              'Bulk Status Update'],
        ['shipment_tracking',                'tracking_update',                 'Tracking Update'],
        ['package_tracking',                 'tracking_update',                 'Tracking Update'],
        ['add_courier_tracking',             'tracking_update',                 'Tracking Update'],
        ['deliver',                          'delivery',                        'Delivery'],
        ['warehouse_delivery',               'delivery',                        'Delivery'],
        ['from_prealert',                    'shipment_registered',             'Shipment Registered'],
        ['add_courier',                      'shipment_registered',             'Shipment Registered'],
        ['add_customers_packages',           'shipment_registered',             'Shipment Registered'],
        ['courier_add_client',               'shipment_registered',             'Shipment Registered'],
        ['edit_courier',                     'shipment_updated',                'Shipment Updated'],
        ['edit_customers_packages',          'shipment_updated',                'Shipment Updated'],
        ['financial_sheet',                  'financial_sheet',                 'Financial Sheet'],
        ['send_email_pdf',                   'invoice_email',                   'Invoice E-mail'],
        ['prealert',                         'prealert',                        'Pre-Alert'],
        ['newsletter',                       'newsletter',                      'Newsletter'],
        ['users_add',                        'user_account',                    'User Account'],
        ['customers_add',                    'user_account',                    'User Account'],
        ['api',                              'api',                             'REST API'],
    ];
    foreach ($map as $m) {
        if (strpos($file, $m[0]) !== false) {
            return [$m[1], $m[2]];
        }
    }

    $slug = preg_replace('/[^a-z0-9_]+/', '_', $file);
    $slug = trim(preg_replace('/_ajax$/', '', $slug), '_');
    if ($slug === '') {
        $slug = 'system';
    }
    return [$slug, ucwords(str_replace('_', ' ', $slug))];
}

/** Human labels for the known sources (filter dropdown). */
function cdp_msgSources()
{
    return [
        'push_notification'               => 'Push Notification',
        'push_notification_consolidation' => 'Push Notification (Consolidation)',
        'push_notification_invoice'       => 'Push Notification (Invoice)',
        'tracking_update'                 => 'Tracking Update',
        'bulk_status_update'              => 'Bulk Status Update',
        'consolidation_update'            => 'Consolidation Update (Package Owners)',
        'shipment_registered'             => 'Shipment Registered',
        'shipment_updated'                => 'Shipment Updated',
        'driver_assignment'               => 'Driver Assignment',
        'delivery'                        => 'Delivery',
        'pending_payments'                => 'Pending Payments Reminder',
        'general_notice'                  => 'General Customer Notice',
        'pickup_aging'                    => 'Pickup Aging Reminder',
        'financial_sheet'                 => 'Financial Sheet',
        'invoice_email'                   => 'Invoice E-mail',
        'otp'                             => 'One-Time Password',
        'signup'                          => 'Sign Up',
        'prealert'                        => 'Pre-Alert',
        'user_account'                    => 'User Account',
        'newsletter'                      => 'Newsletter',
        'api'                             => 'REST API',
    ];
}

function cdp_msgSourceLabel($slug)
{
    $all = cdp_msgSources();
    return $all[$slug] ?? ucwords(str_replace('_', ' ', (string) $slug));
}

/** Channel labels + colours (pills, charts). */
function cdp_msgChannels()
{
    return [
        'whatsapp' => ['label' => 'WhatsApp', 'color' => '#25d366'],
        'email'    => ['label' => 'E-mail',   'color' => '#336aea'],
        'sms'      => ['label' => 'SMS',      'color' => '#9b6ef3'],
    ];
}

function cdp_msgStatuses()
{
    return [
        'sent'    => ['label' => 'Sent',    'color' => '#0aa699'],
        'failed'  => ['label' => 'Failed',  'color' => '#f62d51'],
        'skipped' => ['label' => 'Skipped', 'color' => '#b4770d'],
    ];
}

// ---------------------------------------------------------------------------
// Redaction — one-time codes must not sit in a log anyone with the page
// permission can read.
// ---------------------------------------------------------------------------
function cdp_msgRedact($body, $source, $templateId = null)
{
    $body = (string) $body;
    $sensitive = in_array((string) $source, ['otp', 'signup'], true)
        || in_array((int) $templateId, [9, 10], true)
        || preg_match('/\b(one[- ]time|otp|verification code|reset code)\b/i', $body);
    if (!$sensitive) {
        return $body;
    }
    return preg_replace('/\b\d{4,8}\b/', '••••', $body);
}

// ---------------------------------------------------------------------------
// Recipient lookups
// ---------------------------------------------------------------------------
function cdp_msgUserByEmail($email)
{
    static $cache = [];
    $email = strtolower(trim((string) $email));
    if ($email === '') {
        return null;
    }
    if (array_key_exists($email, $cache)) {
        return $cache[$email];
    }
    $cache[$email] = null;
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT id, fname, lname FROM cdb_users WHERE LOWER(email) = :e LIMIT 1");
        $db->bind(':e', $email);
        $db->cdp_execute();
        $row = $db->cdp_registro();
        if ($row) {
            $cache[$email] = ['id' => (int) $row->id, 'name' => trim($row->fname . ' ' . $row->lname)];
        }
    } catch (Throwable $e) {
        // unknown recipient — fine
    }
    return $cache[$email];
}

/** Name + id from whatever object the send primitive was handed. */
function cdp_msgRecipientFromEntity($entity)
{
    $out = ['id' => 0, 'name' => '', 'email' => '', 'phone' => ''];
    if (is_array($entity)) {
        $entity = (object) $entity;
    }
    if (!is_object($entity)) {
        return $out;
    }
    $out['id']    = (int) ($entity->id ?? 0);
    $out['name']  = trim((string) ($entity->fname ?? '') . ' ' . (string) ($entity->lname ?? ''));
    $out['email'] = (string) ($entity->email ?? '');
    $out['phone'] = (string) ($entity->phone ?? '');
    if ($out['name'] === '' && !empty($entity->name)) {
        $out['name'] = (string) $entity->name;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// The writer
// ---------------------------------------------------------------------------

/**
 * Write one row. Never throws. Context keys fill any field not given.
 *
 * @param array $o channel, status, status_detail, provider_response, subject,
 *                 body, template_id, recipient_user_id, recipient_name,
 *                 recipient_to, entity_type, entity_id, entity_label,
 *                 batch_id, source, source_label
 * @return int inserted id, or 0
 */
function cdp_msgLog(array $o)
{
    try {
        if (!cdp_msgTableReady()) {
            return 0;
        }

        $ctx = cdp_msgContext();
        $get = function ($k, $default = '') use ($o, $ctx) {
            if (array_key_exists($k, $o) && $o[$k] !== null && $o[$k] !== '') {
                return $o[$k];
            }
            if (array_key_exists($k, $ctx) && $ctx[$k] !== null && $ctx[$k] !== '') {
                return $ctx[$k];
            }
            return $default;
        };

        $source = (string) $get('source');
        $label  = (string) $get('source_label');
        if ($source === '') {
            list($source, $inferred) = cdp_msgInferSource();
            if ($label === '') {
                $label = $inferred;
            }
        } elseif ($label === '') {
            $label = cdp_msgSourceLabel($source);
        }

        $templateId = $get('template_id', null);
        $templateId = ($templateId === null || $templateId === '') ? null : (int) $templateId;

        $body = cdp_msgRedact((string) $get('body'), $source, $templateId);
        if (function_exists('mb_substr')) {
            $body = mb_substr($body, 0, 60000);
        } else {
            $body = substr($body, 0, 60000);
        }

        $actor = function_exists('cdp_activityActor') ? cdp_activityActor() : ['user_id' => 0, 'name' => '', 'role_name' => ''];
        $sentBy     = (int) ($actor['user_id'] ?? 0);
        $sentByName = $sentBy > 0 ? (string) ($actor['name'] ?? '') : (PHP_SAPI === 'cli' ? 'System (cron)' : 'System');
        $sentByRole = $sentBy > 0 ? (string) ($actor['role_name'] ?? '') : '';

        $status = strtolower((string) $get('status', 'sent'));
        if (!in_array($status, ['sent', 'failed', 'skipped'], true)) {
            $status = $status === 'error' ? 'failed' : 'sent';
        }

        $resp = $get('provider_response', null);
        if ($resp !== null && !is_string($resp)) {
            $resp = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_message_log
            (created_at, channel, source, source_label, template_id, subject, body,
             recipient_user_id, recipient_name, recipient_to,
             entity_type, entity_id, entity_label, batch_id,
             status, status_detail, provider_response,
             sent_by, sent_by_name, sent_by_role, ip, endpoint)
            VALUES
            (:created_at, :channel, :source, :source_label, :template_id, :subject, :body,
             :recipient_user_id, :recipient_name, :recipient_to,
             :entity_type, :entity_id, :entity_label, :batch_id,
             :status, :status_detail, :provider_response,
             :sent_by, :sent_by_name, :sent_by_role, :ip, :endpoint)");

        $db->bind(':created_at',        cdp_msgNow());
        $db->bind(':channel',           substr(strtolower((string) $get('channel', 'whatsapp')), 0, 12));
        $db->bind(':source',            substr($source, 0, 80));
        $db->bind(':source_label',      substr($label, 0, 120));
        $db->bind(':template_id',       $templateId);
        $db->bind(':subject',           mb_substr((string) $get('subject'), 0, 255));
        $db->bind(':body',              $body);
        $db->bind(':recipient_user_id', (int) $get('recipient_user_id', 0));
        $db->bind(':recipient_name',    mb_substr((string) $get('recipient_name'), 0, 150));
        $db->bind(':recipient_to',      mb_substr((string) $get('recipient_to'), 0, 190));
        $db->bind(':entity_type',       substr((string) $get('entity_type'), 0, 60));
        $db->bind(':entity_id',         substr((string) $get('entity_id'), 0, 64));
        $db->bind(':entity_label',      mb_substr((string) $get('entity_label'), 0, 190));
        $db->bind(':batch_id',          substr((string) $get('batch_id'), 0, 40));
        $db->bind(':status',            $status);
        $db->bind(':status_detail',     mb_substr((string) $get('status_detail'), 0, 255));
        $db->bind(':provider_response', $resp === null ? null : mb_substr((string) $resp, 0, 20000));
        $db->bind(':sent_by',           $sentBy);
        $db->bind(':sent_by_name',      mb_substr($sentByName, 0, 150));
        $db->bind(':sent_by_role',      mb_substr($sentByRole, 0, 100));
        $db->bind(':ip',                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        $db->bind(':endpoint',          substr(function_exists('cdp_activityEndpoint') ? cdp_activityEndpoint() : '', 0, 190));
        $db->cdp_execute();

        return (int) $db->dbh->lastInsertId();
    } catch (Throwable $e) {
        return 0;
    }
}

// ---------------------------------------------------------------------------
// PHPMailer hooks — installed by class.phpmailer.php when these functions exist.
// ---------------------------------------------------------------------------

/**
 * PHPMailer action_function: called once per recipient after each attempt.
 */
function cdp_msgMailCallback($isSent, $to, $cc, $bcc, $subject, $body, $from)
{
    try {
        $addresses = [];
        foreach ((array) $to as $t) {
            // PHPMailer passes either "addr" or [addr, name]
            $addresses[] = is_array($t) ? (string) ($t[0] ?? '') : (string) $t;
        }
        foreach ($addresses as $addr) {
            if ($addr === '') {
                continue;
            }
            $ctx = cdp_msgContext();
            $u = cdp_msgUserByEmail($addr);
            cdp_msgLog([
                'channel'           => 'email',
                'status'            => $isSent ? 'sent' : 'failed',
                'status_detail'     => $isSent ? 'Accepted by SMTP server' : 'SMTP server did not accept the message',
                'subject'           => (string) $subject,
                'body'              => (string) $body,
                'recipient_to'      => $addr,
                'recipient_user_id' => $u ? $u['id'] : (int) ($ctx['recipient_user_id'] ?? 0),
                'recipient_name'    => $u ? $u['name'] : (string) ($ctx['recipient_name'] ?? ''),
            ]);
        }
    } catch (Throwable $e) {
        // never disturb the send
    }
}

/**
 * Failure hook: PHPMailer::send() threw before any per-recipient callback ran
 * (connection refused, auth failed, no address...).
 */
function cdp_msgMailFailed($mailer, $error = '')
{
    try {
        $tos = [];
        if (is_object($mailer) && method_exists($mailer, 'getToAddresses')) {
            foreach ((array) $mailer->getToAddresses() as $t) {
                $tos[] = is_array($t) ? (string) ($t[0] ?? '') : (string) $t;
            }
        }
        if (!$tos) {
            $tos = [''];
        }
        $error = $error !== '' ? $error : (is_object($mailer) ? (string) ($mailer->ErrorInfo ?? '') : '');
        foreach ($tos as $addr) {
            $ctx = cdp_msgContext();
            $u = $addr !== '' ? cdp_msgUserByEmail($addr) : null;
            cdp_msgLog([
                'channel'           => 'email',
                'status'            => 'failed',
                'status_detail'     => $error !== '' ? $error : 'Send failed before delivery',
                'subject'           => is_object($mailer) ? (string) ($mailer->Subject ?? '') : '',
                'body'              => is_object($mailer) ? (string) ($mailer->Body ?? '') : '',
                'recipient_to'      => $addr,
                'recipient_user_id' => $u ? $u['id'] : (int) ($ctx['recipient_user_id'] ?? 0),
                'recipient_name'    => $u ? $u['name'] : (string) ($ctx['recipient_name'] ?? ''),
            ]);
        }
    } catch (Throwable $e) {
        // never disturb the send
    }
}

/**
 * Log a PHP mail() attempt (the non-SMTP mailer). Call right after mail().
 */
function cdp_msgLogPhpMail($ok, $to, $subject, $body, array $extra = [])
{
    $u = cdp_msgUserByEmail($to);
    return cdp_msgLog(array_merge([
        'channel'           => 'email',
        'status'            => $ok ? 'sent' : 'failed',
        'status_detail'     => $ok ? 'Handed to PHP mail()' : 'PHP mail() returned false',
        'subject'           => (string) $subject,
        'body'              => (string) $body,
        'recipient_to'      => (string) $to,
        'recipient_user_id' => $u ? $u['id'] : 0,
        'recipient_name'    => $u ? $u['name'] : '',
    ], $extra));
}
