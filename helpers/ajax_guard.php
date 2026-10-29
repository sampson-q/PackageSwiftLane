<?php
/**
 * Guard estándar para endpoints AJAX: sesión y permisos.
 * Uso: después de loader.php incluir este archivo y llamar require_login(); require_permission('nombre_permiso');
 * Respuesta: 401 sin sesión, 403 sin permiso (JSON). Con permiso sigue la ejecución.
 */

if (!defined('SWIFTLANE_AJAX_GUARD_LOADED')) {
    require_once __DIR__ . '/csrf.php';
    if (!class_exists('User')) {
        require_once dirname(__DIR__) . '/loader.php';
    }
    if (!isset($user) || !$user instanceof User) {
        $user = new User();
    }
    define('SWIFTLANE_AJAX_GUARD_LOADED', true);
}

/**
 * Asegura que haya sesión activa. Si no, envía 401 JSON y termina.
 */
function require_login() {
    global $user;
    if (!isset($user) || !($user instanceof User)) {
        $user = new User();
    }
    if (empty($user->logged_in)) {
        _ajax_guard_send(401, ['success' => false, 'error' => 'Unauthorized', 'message' => 'Sesión requerida']);
    }

    _ajax_guard_require_csrf();

    // Audit trail. Every authenticated AJAX endpoint passes through here, so
    // arming the shutdown auto-logger at this one point means a mutating
    // endpoint is recorded whether or not anyone instrumented it by hand.
    // See helpers/activity_log.php.
    require_once __DIR__ . '/activity_log.php';
    cdp_activityArmAuto();
}

/**
 * Opt this request into queued notifications: email and WhatsApp sends made
 * after this call are delivered once the response has been sent, so saving a
 * shipment no longer waits for an SMTP conversation and two API calls. Only
 * for endpoints that do not report the send result back to the user; the
 * outcome is recorded by the message log. See helpers/after_response.php.
 */
function notify_after_response() {
    require_once __DIR__ . '/after_response.php';
    cdp_notifyDeferred(true);
    // Buffer from here so the response length is known and the connection can
    // be closed before the queue runs (needed under Apache mod_php).
    if (!headers_sent() && ob_get_level() === 0) {
        ob_start();
    }
}

/**
 * Asegura que el usuario tenga al menos uno de los permisos. Si no, envía 403 JSON y termina.
 * @param string|string[] $permission Nombre del permiso (o array de nombres, cualquiera)
 */
function require_permission($permission) {
    global $user;
    if (!isset($user) || !($user instanceof User)) {
        $user = new User();
    }
    if (empty($user->logged_in)) {
        _ajax_guard_send(401, ['success' => false, 'error' => 'Unauthorized', 'message' => 'Session Required']);
    }
    _ajax_guard_require_csrf();
    $perms = is_array($permission) ? $permission : [$permission];
    // Always reload permissions fresh from DB — loader.php may have created $user
    // before session was fully initialised, leaving $this->permissions empty or stale.
    // The page controllers do the same: they call cdp_getUserPermissions() explicitly
    // before cdp_hasPermission(). Mirror that here so AJAX and page behave identically.
    // Endpoints that gate on permission alone still need the audit trail armed.
    require_once __DIR__ . '/activity_log.php';
    cdp_activityArmAuto();

    $user->cdp_getUserPermissions();
    if ($user->cdp_hasPermission($perms)) {
        return;
    }

    // Authorization is mandatory for every role; configuration cannot bypass it.

    // A hard denial is a security event: record it, then answer 403.
    $denied = cdp_activityClassify(cdp_activityEndpoint());
    cdp_activityLog([
        'module'  => $denied['module'],
        'verb'    => $denied['verb'],
        'outcome' => 'denied',
        'label'   => $denied['label'],
        'summary' => 'Blocked — no permission for ' . implode(', ', $perms),
        'meta'    => ['required' => $perms, 'source' => 'rbac'],
    ]);

    $body = ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action'];
    if (defined('CDP_DEBUG_RBAC') && CDP_DEBUG_RBAC) {
        $body['required'] = $perms;
        $body['role_id'] = $user->userlevel;
    }
    _ajax_guard_send(403, $body);
}

/**
 * Envía respuesta JSON y termina.
 * @param int $httpCode 401 o 403
 * @param array $body
 */
function _ajax_guard_send($httpCode, array $body) {
    http_response_code($httpCode);
    if ($httpCode === 401) {
        header('HTTP/1.1 401 Unauthorized');
    } elseif ($httpCode === 403) {
        header('HTTP/1.1 403 Forbidden');
    } elseif ($httpCode === 419) {
        header('HTTP/1.1 419 Authentication Timeout');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body);
    exit;
}

function _ajax_guard_require_csrf() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    if (!cdp_csrf_validate_request()) {
        _ajax_guard_send(419, ['success' => false, 'error' => 'Invalid CSRF token', 'message' => 'CSRF token missing or invalid']);
    }
}

function require_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        _ajax_guard_send(405, ['success' => false, 'error' => 'Method not allowed']);
    }
}
