<?php
/**
 * ============================================================================
 * Activity Log — the system-wide audit trail.
 *
 * Every action anyone takes gets one row in cdb_activity_log, tagged with the
 * actor, their role, the module, a normalised action key, the record touched,
 * and (for status changes) the status they moved it to. The Activity Logs page
 * then filters and aggregates on those columns.
 *
 * There are three ways a row gets written:
 *
 *   1. AUTOMATIC (writes).  helpers/ajax_guard.php::require_login() arms
 *      cdp_activityArmAuto() on every authenticated AJAX endpoint. On shutdown,
 *      if the request mutated something and nothing more specific was logged,
 *      a row is written from the endpoint's own name. This is what makes
 *      coverage total: an endpoint nobody has instrumented still shows up.
 *
 *   2. AUTOMATIC (page views).  views/inc/head_scripts.php calls
 *      cdp_activityPageView() — one `view` row per page a user opens.
 *
 *   3. EXPLICIT.  Handlers call cdp_activityLog() with a real summary, the
 *      before/after diff, the status moved to, etc. An explicit call suppresses
 *      the automatic one for that request, so there is never a double entry.
 *
 * Nothing here may ever break a request. Every path is wrapped: if the table
 * is missing (the SQL has not been applied yet) or the insert fails, the app
 * carries on exactly as before.
 * ============================================================================
 */

if (!defined('CDP_ACTIVITY_LOG_LOADED')) {
    define('CDP_ACTIVITY_LOG_LOADED', true);

    /** Set once an explicit log ran, so the shutdown auto-logger stands down. */
    $GLOBALS['cdp_activity_logged'] = false;
    /** Set when require_login() armed the shutdown auto-logger. */
    $GLOBALS['cdp_activity_armed'] = false;
    /** Extra context an endpoint wants folded into the automatic entry. */
    $GLOBALS['cdp_activity_hint'] = [];
}

// ---------------------------------------------------------------------------
// Vocabulary
// ---------------------------------------------------------------------------

/**
 * Module slug → display label. The slug is what the filters store; the label
 * is what the page shows.
 */
function cdp_activityModules()
{
    return [
        'packages'       => 'Packages',
        'shipments'      => 'Shipments',
        'consolidations' => 'Consolidations',
        'prealerts'      => 'Pre-Alerts',
        'pickups'        => 'Pickups',
        'warehouse'      => 'Warehouse',
        'customers'      => 'Customers',
        'recipients'     => 'Recipients',
        'users'          => 'Users',
        'drivers'        => 'Drivers',
        'access'         => 'Access Control',
        'finance'        => 'Finance',
        'billing'        => 'Billing',
        'reports'        => 'Reports',
        'notifications'  => 'Notifications',
        'settings'       => 'Settings',
        'auth'           => 'Authentication',
        'profile'        => 'Profile',
        'locker'         => 'Locker',
        'system'         => 'System',
    ];
}

/**
 * Verb slug → display label. The verb is the "what kind of action" axis: it is
 * what makes "show me every deletion, by anyone, this month" one query.
 */
function cdp_activityVerbs()
{
    return [
        'create'      => 'Created',
        'update'      => 'Updated',
        'delete'      => 'Deleted',
        'status'      => 'Status Change',
        'assign'      => 'Assigned',
        'deliver'     => 'Delivered',
        'payment'     => 'Payment',
        'notify'      => 'Notification Sent',
        'upload'      => 'File Upload',
        'export'      => 'Export',
        'print'       => 'Print',
        'login'       => 'Login',
        'logout'      => 'Logout',
        'impersonate' => 'View As',
        'view'        => 'Page View',
        'other'       => 'Other',
    ];
}

function cdp_activityModuleLabel($slug)
{
    $m = cdp_activityModules();
    return $m[$slug] ?? ucfirst(str_replace('_', ' ', (string) $slug));
}

function cdp_activityVerbLabel($slug)
{
    $v = cdp_activityVerbs();
    return $v[$slug] ?? ucfirst((string) $slug);
}

// ---------------------------------------------------------------------------
// Writer
// ---------------------------------------------------------------------------

/**
 * Does the trail table exist? Cached per request — a missing table must not
 * cost a SHOW TABLES on every single log call.
 *
 * @return bool
 */
function cdp_activityTableReady()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $db = new Conexion;
        $db->cdp_query("SHOW TABLES LIKE 'cdb_activity_log'");
        $db->cdp_execute();
        $ready = $db->cdp_rowCount() > 0;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Resolve who is acting. Reads the session, falls back to cdb_users for the
 * display name, and notes impersonation ("View as").
 *
 * @return array{user_id:int,name:string,username:string,role_id:int,role_name:string,impersonated_by:int}
 */
function cdp_activityActor()
{
    static $actor = null;
    if ($actor !== null) {
        return $actor;
    }

    $uid  = (int) ($_SESSION['userid'] ?? 0);
    $name = trim((string) ($_SESSION['name'] ?? ''));
    $uname = (string) ($_SESSION['username'] ?? '');
    $role = (int) ($_SESSION['userlevel'] ?? 0);
    $imp  = (int) ($_SESSION['imp_original_userid'] ?? 0);

    $roleName = '';
    if ($role > 0) {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT role_name FROM cdb_user_roles WHERE role_id = :r LIMIT 1");
            $db->bind(':r', $role);
            $db->cdp_execute();
            $row = $db->cdp_registro();
            $roleName = $row ? (string) $row->role_name : '';
        } catch (Throwable $e) {
            $roleName = '';
        }
    }

    if ($name === '' && $uid > 0) {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT fname, lname, username FROM cdb_users WHERE id = :id LIMIT 1");
            $db->bind(':id', $uid);
            $db->cdp_execute();
            $row = $db->cdp_registro();
            if ($row) {
                $name = trim($row->fname . ' ' . $row->lname);
                if ($uname === '') {
                    $uname = (string) $row->username;
                }
            }
        } catch (Throwable $e) {
            // leave the name blank
        }
    }

    $actor = [
        'user_id'         => $uid,
        'name'            => $name !== '' ? $name : ($uname !== '' ? $uname : 'Guest'),
        'username'        => $uname,
        'role_id'         => $role,
        'role_name'       => $roleName !== '' ? $roleName : ($role > 0 ? ('Role ' . $role) : 'Guest'),
        'impersonated_by' => ($imp > 0 && $imp !== $uid) ? $imp : 0,
    ];
    return $actor;
}

/**
 * "Now" in the system's configured timezone.
 *
 * Pages instantiate Core, which calls date_default_timezone_set() — but many
 * AJAX endpoints never do, so a bare date() would stamp some rows in the app's
 * timezone and others in the server's. An audit trail whose clock depends on
 * which endpoint you hit is worthless, so the timezone is applied here, once,
 * for every row.
 *
 * @return string 'Y-m-d H:i:s'
 */
function cdp_activityNow()
{
    static $tz = null;

    if ($tz === null) {
        $tz = '';
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT timezone FROM cdb_settings LIMIT 1");
            $db->cdp_execute();
            $row = $db->cdp_registro();
            $tz = $row && !empty($row->timezone) ? (string) $row->timezone : '';
        } catch (Throwable $e) {
            $tz = '';
        }
    }

    if ($tz !== '') {
        try {
            return (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            // An unknown timezone name falls through to the process default.
        }
    }
    return date('Y-m-d H:i:s');
}

/** The caller's IP, honouring the usual proxy headers. */
function cdp_activityIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return (string) $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** The script path relative to the app root, e.g. "ajax/users/users_edit_ajax.php". */
function cdp_activityEndpoint()
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = ltrim($script, '/');
    // Trim the app's mount folder so local (/PackageSwiftLane/...) and live
    // (/...) produce the same value.
    $root = basename(dirname(__DIR__));
    if ($root !== '' && strpos($script, $root . '/') === 0) {
        $script = substr($script, strlen($root) + 1);
    }
    return $script;
}

/**
 * Write one row. Never throws.
 *
 * Recognised keys:
 *   module        string  slug from cdp_activityModules() — defaults to 'system'
 *   verb          string  slug from cdp_activityVerbs()  — defaults to 'other'
 *   action        string  filter key; defaults to "<module>.<verb>"
 *   label         string  human action name; defaults to "Module · Verb"
 *   summary       string  one sentence, shown in the table
 *   entity_type   string  'package' | 'shipment' | 'user' | ...
 *   entity_id     mixed   the record's id
 *   entity_label  string  tracking number, customer name, ...
 *   status_id     int     cdb_styles.id the record was moved to
 *   status_name   string  its label
 *   outcome       string  'success' | 'failure' | 'denied'
 *   changes       array   from cdp_activityDiff()
 *   meta          array   anything else worth keeping
 *   user_id       int     override the actor (e.g. logging a failed login)
 *   actor_name    string  override the actor's display name
 *   auto          bool    internal: this came from the shutdown auto-logger
 *
 * @param array $o
 * @return void
 */
function cdp_activityLog(array $o)
{
    try {
        if (!cdp_activityTableReady()) {
            return;
        }

        $module = (string) ($o['module'] ?? 'system');
        $verb   = (string) ($o['verb'] ?? 'other');
        $action = (string) ($o['action'] ?? ($module . '.' . $verb));
        $label  = (string) ($o['label'] ?? (cdp_activityModuleLabel($module) . ' · ' . cdp_activityVerbLabel($verb)));

        $actor = cdp_activityActor();
        $uid   = isset($o['user_id']) ? (int) $o['user_id'] : $actor['user_id'];
        $aname = isset($o['actor_name']) ? (string) $o['actor_name'] : $actor['name'];

        $changes = isset($o['changes']) && $o['changes'] !== [] && $o['changes'] !== null
            ? json_encode($o['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $meta = $o['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = ['value' => $meta];
        }
        $meta['endpoint'] = cdp_activityEndpoint();
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_activity_log
            (created_at, user_id, actor_name, actor_username, role_id, role_name, impersonated_by,
             module, action, action_label, verb, outcome, summary,
             entity_type, entity_id, entity_label, status_id, status_name,
             changes, meta, ip, user_agent, method, endpoint)
            VALUES
            (:created_at, :user_id, :actor_name, :actor_username, :role_id, :role_name, :impersonated_by,
             :module, :action, :action_label, :verb, :outcome, :summary,
             :entity_type, :entity_id, :entity_label, :status_id, :status_name,
             :changes, :meta, :ip, :user_agent, :method, :endpoint)");

        $db->bind(':created_at', cdp_activityNow());
        $db->bind(':user_id', $uid);
        $db->bind(':actor_name', cdp_activityClip($aname, 150));
        $db->bind(':actor_username', cdp_activityClip($actor['username'], 100));
        $db->bind(':role_id', $actor['role_id']);
        $db->bind(':role_name', cdp_activityClip($actor['role_name'], 100));
        $db->bind(':impersonated_by', $actor['impersonated_by']);
        $db->bind(':module', cdp_activityClip($module, 60));
        $db->bind(':action', cdp_activityClip($action, 90));
        $db->bind(':action_label', cdp_activityClip($label, 160));
        $db->bind(':verb', cdp_activityClip($verb, 20));
        $db->bind(':outcome', cdp_activityClip((string) ($o['outcome'] ?? 'success'), 12));
        $db->bind(':summary', cdp_activityClip((string) ($o['summary'] ?? $label), 500));
        $db->bind(':entity_type', cdp_activityClip((string) ($o['entity_type'] ?? ''), 60));
        $db->bind(':entity_id', cdp_activityClip((string) ($o['entity_id'] ?? ''), 64));
        $db->bind(':entity_label', cdp_activityClip((string) ($o['entity_label'] ?? ''), 190));
        $db->bind(':status_id', isset($o['status_id']) && $o['status_id'] !== '' && $o['status_id'] !== null ? (int) $o['status_id'] : null);
        $db->bind(':status_name', isset($o['status_name']) && $o['status_name'] !== '' ? cdp_activityClip((string) $o['status_name'], 120) : null);
        $db->bind(':changes', $changes);
        $db->bind(':meta', $metaJson);
        $db->bind(':ip', cdp_activityClip(cdp_activityIp(), 45));
        $db->bind(':user_agent', cdp_activityClip((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255));
        $db->bind(':method', cdp_activityClip((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 10));
        $db->bind(':endpoint', cdp_activityClip(cdp_activityEndpoint(), 190));
        $db->cdp_execute();

        // An explicit entry stands down the shutdown auto-logger.
        if (empty($o['auto'])) {
            $GLOBALS['cdp_activity_logged'] = true;
        }
    } catch (Throwable $e) {
        // Auditing must never take the app down. Leave a breadcrumb only.
        error_log('ACTIVITY_LOG_FAIL ' . $e->getMessage());
    }
}

/** Trim a value to a column's width, multibyte-safe. */
function cdp_activityClip($v, $len)
{
    $v = (string) $v;
    return function_exists('mb_substr') ? mb_substr($v, 0, $len) : substr($v, 0, $len);
}

// ---------------------------------------------------------------------------
// Diffing
// ---------------------------------------------------------------------------

/**
 * Build a {field: {from, to}} map of what actually changed.
 *
 * Only fields whose value really differs are returned, so an "edit" that
 * changed one phone number logs one line, not forty.
 *
 * @param array|object|null $before
 * @param array|object|null $after
 * @param string[]          $fields Restrict to these keys (empty = all of $after)
 * @param string[]          $labels Optional field => human label map
 * @return array
 */
function cdp_activityDiff($before, $after, array $fields = [], array $labels = [])
{
    $b = (array) ($before ?: []);
    $a = (array) ($after ?: []);

    $keys = $fields ?: array_keys($a);
    $out = [];

    foreach ($keys as $k) {
        if (!array_key_exists($k, $a)) {
            continue;
        }
        $from = array_key_exists($k, $b) ? $b[$k] : null;
        $to = $a[$k];

        // Loose compare: form posts are strings, the DB may hand back ints.
        if ((string) $from === (string) $to) {
            continue;
        }
        if (cdp_activityIsSecret($k)) {
            $from = '••••';
            $to = '••••';
        }

        $out[$labels[$k] ?? $k] = [
            'from' => cdp_activityClip(is_scalar($from) || $from === null ? (string) $from : json_encode($from), 300),
            'to'   => cdp_activityClip(is_scalar($to) || $to === null ? (string) $to : json_encode($to), 300),
        ];
    }

    return $out;
}

/** Field names whose values must never reach the log. */
function cdp_activityIsSecret($key)
{
    $k = strtolower((string) $key);
    foreach (['password', 'passwd', 'pass_', 'token', 'csrf', 'secret', 'api_key', 'apikey',
              'private_key', 'card', 'cvv', 'pin', 'otp_code', 'authorization'] as $needle) {
        if (strpos($k, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * A redacted, size-capped snapshot of the request body — what the automatic
 * logger keeps so an un-instrumented endpoint is still auditable.
 *
 * @return array
 */
function cdp_activityRequestSnapshot()
{
    $src = ($_POST ?: []) + ($_GET ?: []);
    $out = [];
    $n = 0;

    foreach ($src as $k => $v) {
        if ($n++ >= 30) {
            $out['…'] = 'truncated';
            break;
        }
        if (cdp_activityIsSecret($k)) {
            $out[$k] = '••••';
            continue;
        }
        if (is_array($v)) {
            $out[$k] = cdp_activityClip(json_encode($v), 200);
            continue;
        }
        $out[$k] = cdp_activityClip((string) $v, 200);
    }

    if (!empty($_FILES)) {
        $names = [];
        foreach ($_FILES as $f) {
            $names[] = is_array($f['name'] ?? null) ? implode(', ', $f['name']) : (string) ($f['name'] ?? '');
        }
        $out['_files'] = cdp_activityClip(implode(' | ', array_filter($names)), 200);
    }

    return $out;
}

// ---------------------------------------------------------------------------
// Endpoint classification (the automatic logger's brain)
// ---------------------------------------------------------------------------

/**
 * Work out the module, verb and a readable label from an endpoint path.
 *
 * @param string $endpoint e.g. "ajax/customers_packages/edit_customers_packages_ajax.php"
 * @return array{module:string,verb:string,label:string,read_only:bool}
 */
function cdp_activityClassify($endpoint)
{
    $endpoint = strtolower(str_replace('\\', '/', (string) $endpoint));
    $parts = array_values(array_filter(explode('/', $endpoint)));
    $file = (string) array_pop($parts);
    $base = preg_replace('/\.php$/', '', $file);
    $base = preg_replace('/_ajax(_v2)?$/', '', $base);

    // Directory (skip a leading "ajax"/"views").
    $dir = '';
    foreach ($parts as $p) {
        if ($p === 'ajax' || $p === 'views') {
            continue;
        }
        $dir = $dir === '' ? $p : $dir . '/' . $p;
    }

    // ── Module ──────────────────────────────────────────────────────────────
    $dirMap = [
        'accounts_receivable'  => 'finance',
        'consolidate'          => 'consolidations',
        'consolidate_packages' => 'consolidations',
        'courier'              => 'shipments',
        'customer'             => 'billing',
        'customers'            => 'customers',
        'customers_packages'   => 'packages',
        'dashboard'            => 'reports',
        'drivers'              => 'drivers',
        'gateway'              => 'finance',
        'locations'            => 'settings',
        'locker'               => 'locker',
        'notify_sms'           => 'notifications',
        'notify_whatsapp'      => 'notifications',
        'pickup'               => 'pickups',
        'pickup_aging'         => 'pickups',
        'pre_alerts'           => 'prealerts',
        'prealert'             => 'prealerts',
        'recipients'           => 'recipients',
        'reports'              => 'reports',
        'tools'                => 'settings',
        'users'                => 'users',
    ];

    $top = '';
    foreach ($parts as $p) {
        if (isset($dirMap[$p])) { $top = $p; break; }
    }
    $module = $top !== '' ? $dirMap[$top] : '';

    if ($module !== '') {
        // The directory placed it; refine on the filename where that carries
        // more meaning than the folder (a payment handler inside ajax/courier
        // is finance, not shipments).
        if (strpos($dir, 'permissions') !== false || strpos($base, 'permission') !== false
            || strpos($base, 'role') !== false || strpos($base, 'department') !== false
            || strpos($base, 'override') !== false) {
            $module = 'access';
        } elseif (strpos($base, 'warehouse') !== false) {
            $module = 'warehouse';
        } elseif (strpos($base, 'payment') !== false || strpos($base, 'charge') !== false
                  || strpos($base, 'invoice') !== false || strpos($base, 'financial') !== false
                  || strpos($base, 'transaction') !== false || strpos($base, 'bill') !== false) {
            $module = ($module === 'settings') ? 'settings' : 'finance';
        } elseif (strpos($base, 'prealert') !== false) {
            $module = 'prealerts';
        } elseif (strpos($base, 'profile') !== false || strpos($base, 'avatar') !== false) {
            $module = 'profile';
        } elseif (strpos($base, 'otp') !== false || strpos($base, 'sign_up') !== false
                  || strpos($base, 'forgot') !== false || strpos($base, 'login') !== false) {
            $module = 'auth';
        } elseif (strpos($base, 'notify') !== false || strpos($base, 'push_notification') !== false
                  || strpos($base, 'send_email') !== false || strpos($base, 'whatsapp') !== false
                  || strpos($base, 'sms') !== false) {
            $module = 'notifications';
        }
    } else {
        // Root-level pages (every UI screen) have no directory at all, so the
        // filename is the only signal. Order matters: the first token that
        // matches wins, so the more specific ones lead — "customer_packages"
        // is Packages, "consolidate_package" is Consolidations, and
        // "report_packages_registered" is a report.
        $fileMap = [
            'prealert' => 'prealerts', 'pre_alert' => 'prealerts',
            'consolidate' => 'consolidations',
            'warehouse' => 'warehouse',
            'pickup' => 'pickups',
            'locker' => 'locker',
            'my_bills' => 'billing',
            'recipient' => 'recipients',
            'driver' => 'drivers',
            'asingrole' => 'access', 'asingpermission' => 'access', 'permission' => 'access',
            'department' => 'access', 'manage_permissions' => 'access',
            'view_as' => 'auth', 'login' => 'auth', 'logout' => 'auth', 'sign-up' => 'auth',
            'forgot' => 'auth', 'auth' => 'auth', 'otp' => 'auth', 'verify' => 'auth',
            'report' => 'reports', 'dashboard' => 'reports', 'logs' => 'reports',
            'financial' => 'finance', 'transaction' => 'finance',
            'accounts_receivable' => 'finance', 'invoice' => 'finance',
            'charge' => 'finance', 'payment' => 'finance',
            'notification' => 'notifications', 'newsletter' => 'notifications',
            'whatsapp' => 'notifications', 'sms' => 'notifications',
            'package' => 'packages',
            'courier' => 'shipments', 'shipment' => 'shipments', 'track' => 'shipments',
            'profile' => 'profile', 'avatar' => 'profile',
            'customer' => 'customers', 'client' => 'customers',
            'user' => 'users', 'role' => 'access',
            'backup' => 'system',
            'tool' => 'settings', 'config' => 'settings', 'template' => 'settings',
            'countries' => 'settings', 'states' => 'settings', 'cities' => 'settings',
            'offices' => 'settings', 'branchoffices' => 'settings', 'shipping' => 'settings',
            'shipline' => 'settings', 'delivery_time' => 'settings', 'incoterms' => 'settings',
            'packaging' => 'settings', 'category' => 'settings', 'status_courier' => 'settings',
            'tariff' => 'settings', 'taxes' => 'settings', 'terms' => 'settings',
        ];
        foreach ($fileMap as $token => $m) {
            if (strpos($base, $token) !== false) { $module = $m; break; }
        }
        if ($module === '') {
            $module = 'system';
        }
    }

    // ── Read-only? ──────────────────────────────────────────────────────────
    // These endpoints answer questions; they change nothing, so logging them
    // would bury the actions in noise.
    $readOnly = false;
    foreach (['_list', 'list_', 'select2', 'select3', 'select_', 'load_', 'report_',
              'graphics', 'check_', 'validate_', 'get_', 'getuser', 'search',
              '_detail', 'modal_', 'view_ajax', 'stats', 'items', 'effective_', 'presence'] as $t) {
        if (strpos($base, $t) !== false) { $readOnly = true; break; }
    }
    // …but a "list" endpoint that also performs an action is not read-only.
    foreach (['delete', 'update', 'save', 'add', 'edit', 'toggle'] as $t) {
        if (strpos($base, $t) !== false) { $readOnly = false; break; }
    }

    // ── Verb ────────────────────────────────────────────────────────────────
    // Order matters: the first match wins, so the most specific tokens lead.
    $verbTokens = [
        'delete'  => ['delete', 'remove', 'discard'],
        'status'  => ['status', 'tracking', 'update_multiple', 'aging'],
        'assign'  => ['driver', 'assign', 'member_toggle'],
        'deliver' => ['deliver'],
        'payment' => ['payment', 'checkout', 'confirm_payment', 'charge', 'webhook', 'callback', 'refund'],
        'notify'  => ['notify', 'push_notification', 'send_email', 'send_', 'sms', 'whatsapp'],
        'upload'  => ['upload', 'import', 'file', 'document', 'avatar', 'backup'],
        'export'  => ['export', 'excel', 'csv'],
        'print'   => ['print'],
        'create'  => ['add', 'create', 'new', 'sign_up', 'accept'],
        'update'  => ['edit', 'update', 'save', 'config', 'setting', 'toggle', 'restore',
                      'cancel', 'refuse', 'reject', 'confirm', 'verify', 'active_inative', 'read'],
    ];

    $verb = 'other';
    foreach ($verbTokens as $v => $tokens) {
        foreach ($tokens as $t) {
            if (strpos($base, $t) !== false) { $verb = $v; break 2; }
        }
    }

    // ── Label ───────────────────────────────────────────────────────────────
    $label = cdp_activityModuleLabel($module) . ' · ' . cdp_activityVerbLabel($verb);

    return ['module' => $module, 'verb' => $verb, 'label' => $label, 'read_only' => $readOnly];
}

// ---------------------------------------------------------------------------
// Automatic loggers
// ---------------------------------------------------------------------------

/**
 * Arm the shutdown auto-logger. Called by require_login(), so every
 * authenticated AJAX endpoint is covered whether or not anyone instrumented it.
 *
 * @return void
 */
function cdp_activityArmAuto()
{
    if (!empty($GLOBALS['cdp_activity_armed'])) {
        return;
    }
    $GLOBALS['cdp_activity_armed'] = true;

    // Armed for every method, not just POST: a fair number of handlers in this
    // codebase mutate over GET (the bulk status updaters, pickup cancel, backup
    // restore). cdp_activityFlushAuto() drops anything the classifier calls
    // read-only, so the list/select2/report endpoints still cost nothing.
    register_shutdown_function('cdp_activityFlushAuto');
}

/**
 * The shutdown handler: write a generic entry if the handler did not write a
 * specific one.
 *
 * @return void
 */
function cdp_activityFlushAuto()
{
    try {
        if (!empty($GLOBALS['cdp_activity_logged'])) {
            return; // an explicit call already described this request, better
        }

        $endpoint = cdp_activityEndpoint();
        $c = cdp_activityClassify($endpoint);
        if ($c['read_only']) {
            return;
        }

        $hint = is_array($GLOBALS['cdp_activity_hint'] ?? null) ? $GLOBALS['cdp_activity_hint'] : [];

        cdp_activityLog(array_merge([
            'auto'    => true,
            'module'  => $c['module'],
            'verb'    => $c['verb'],
            'label'   => $c['label'],
            'summary' => $c['label'] . ' (' . basename($endpoint) . ')',
            'meta'    => ['params' => cdp_activityRequestSnapshot(), 'source' => 'auto'],
        ], $hint));
    } catch (Throwable $e) {
        error_log('ACTIVITY_LOG_AUTO_FAIL ' . $e->getMessage());
    }
}

/**
 * Let a handler enrich the automatic entry without writing its own row —
 * useful where the interesting id is only known mid-request.
 *
 * @param array $hint Any cdp_activityLog() key
 * @return void
 */
function cdp_activityHint(array $hint)
{
    $GLOBALS['cdp_activity_hint'] = array_merge(
        is_array($GLOBALS['cdp_activity_hint'] ?? null) ? $GLOBALS['cdp_activity_hint'] : [],
        $hint
    );
}

/**
 * One `view` row per page a signed-in user opens. Called from
 * views/inc/head_scripts.php, which every authenticated page includes.
 *
 * Page views are the highest-volume rows in the trail, so the Activity Logs
 * page hides them unless you ask for them.
 *
 * @return void
 */
function cdp_activityPageView()
{
    try {
        if (empty($_SESSION['userid'])) {
            return;
        }
        $endpoint = cdp_activityEndpoint();
        if ($endpoint === '' || strpos($endpoint, 'ajax/') === 0) {
            return;
        }
        // A page that POSTs to itself is a write; the shutdown logger has it.
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return;
        }

        $c = cdp_activityClassify($endpoint);
        $page = basename($endpoint, '.php');

        cdp_activityLog([
            'auto'    => true,
            'module'  => $c['module'],
            'verb'    => 'view',
            'action'  => $c['module'] . '.view',
            'label'   => cdp_activityModuleLabel($c['module']) . ' · Page View',
            'summary' => 'Opened ' . cdp_activityHumanPage($page),
            'meta'    => ['page' => $page, 'query' => cdp_activityClip((string) ($_SERVER['QUERY_STRING'] ?? ''), 200), 'source' => 'page'],
        ]);
    } catch (Throwable $e) {
        error_log('ACTIVITY_LOG_VIEW_FAIL ' . $e->getMessage());
    }
}

/**
 * A document leaving the system: a print view or an Excel/CSV export.
 *
 * Called from the print_*.php / report_*_excel.php / report_*_print.php entry
 * points, which never reach views/inc/head_scripts.php and so are invisible to
 * the page-view logger. Who exported what is exactly the sort of thing an audit
 * is asked about, so these get their own rows.
 *
 * @return void
 */
function cdp_activityLogDocument()
{
    try {
        if (empty($_SESSION['userid'])) {
            return;
        }
        $endpoint = cdp_activityEndpoint();
        $c = cdp_activityClassify($endpoint);
        $page = basename($endpoint, '.php');
        $verb = in_array($c['verb'], ['export', 'print'], true)
            ? $c['verb']
            : (strpos($page, 'excel') !== false ? 'export' : 'print');

        cdp_activityLog([
            'auto'        => true,
            'module'      => $c['module'],
            'verb'        => $verb,
            'action'      => $c['module'] . '.' . $verb,
            'label'       => cdp_activityModuleLabel($c['module']) . ' · ' . cdp_activityVerbLabel($verb),
            'summary'     => ($verb === 'export' ? 'Exported ' : 'Printed ') . cdp_activityHumanPage($page),
            'entity_type' => 'document',
            'entity_id'   => (string) ($_GET['id'] ?? $_GET['order_no'] ?? ''),
            'meta'        => ['page' => $page, 'params' => cdp_activityRequestSnapshot(), 'source' => 'document'],
        ]);
    } catch (Throwable $e) {
        error_log('ACTIVITY_LOG_DOC_FAIL ' . $e->getMessage());
    }
}

/** "customer_packages_list" → "Customer Packages List". */
function cdp_activityHumanPage($slug)
{
    return ucwords(trim(str_replace(['_', '-'], ' ', (string) $slug)));
}

// ---------------------------------------------------------------------------
// Convenience wrappers for the flows worth naming
// ---------------------------------------------------------------------------

/**
 * A sign-in attempt.
 *
 * @param bool   $ok
 * @param string $username
 * @param int    $userId
 * @param string $reason  Why it failed, when it did
 * @return void
 */
function cdp_activityLogLogin($ok, $username, $userId = 0, $reason = '')
{
    cdp_activityLog([
        'module'       => 'auth',
        'verb'         => 'login',
        'outcome'      => $ok ? 'success' : 'failure',
        'user_id'      => (int) $userId,
        'actor_name'   => $username !== '' ? $username : 'Unknown',
        'entity_type'  => 'user',
        'entity_id'    => (int) $userId,
        'entity_label' => $username,
        'summary'      => $ok
            ? 'Signed in'
            : ('Failed sign-in attempt' . ($reason !== '' ? ' — ' . $reason : '')),
    ]);
}

/**
 * A status move — the thing operations actually gets audited on.
 *
 * @param string $module      'packages' | 'shipments' | 'consolidations' | 'pickups'
 * @param string $entityType
 * @param mixed  $entityId
 * @param string $entityLabel Tracking number
 * @param int    $statusId    cdb_styles.id moved TO
 * @param string $statusName
 * @param string $fromName    Previous status label, when known
 * @return void
 */
function cdp_activityLogStatus($module, $entityType, $entityId, $entityLabel, $statusId, $statusName, $fromName = '')
{
    cdp_activityLog([
        'module'       => $module,
        'verb'         => 'status',
        'entity_type'  => $entityType,
        'entity_id'    => $entityId,
        'entity_label' => $entityLabel,
        'status_id'    => $statusId,
        'status_name'  => $statusName,
        'changes'      => $fromName !== '' ? ['status' => ['from' => $fromName, 'to' => $statusName]] : [],
        'summary'      => trim(($entityLabel !== '' ? $entityLabel . ' — ' : '') . 'status set to ' . $statusName),
    ]);
}

/** Look up a status label by cdb_styles.id. Returns '' when unknown. */
function cdp_activityStatusName($statusId)
{
    $id = (int) $statusId;
    if ($id <= 0) {
        return '';
    }
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    try {
        $db = new Conexion;
        $db->cdp_query("SELECT mod_style FROM cdb_styles WHERE id = :id LIMIT 1");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $row = $db->cdp_registro();
        $cache[$id] = $row ? (string) $row->mod_style : '';
    } catch (Throwable $e) {
        $cache[$id] = '';
    }
    return $cache[$id];
}
