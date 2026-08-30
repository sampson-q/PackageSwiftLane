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

    // Denied. Behaviour depends on CDP_RBAC_MODE (set in config/config.php):
    //   'off'     -> allow silently (legacy behaviour while the check was commented out)
    //   'audit'   -> allow, but log RBAC_DENY so perm names can be reconciled without lockouts
    //   'enforce' -> GRADUATED: 403 for staff-and-up (staff/admin/superadmin), but only
    //                audit-log for customer/driver/agency. Rationale: the whole granular
    //                RBAC is about STAFF capabilities, and staff endpoints are fully
    //                reconciled. Many customer/driver/agency-facing handlers are still
    //                gated with staff permission names (historical), so hard-enforcing
    //                them would false-403 real customer flows (4800+ users viewing their
    //                own packages). They stay audit-only until reconciled via the logs.
    // Default is 'audit': config.php is gitignored, so an env without the constant
    // must never fail closed.
    $mode = defined('CDP_RBAC_MODE') ? strtolower((string)CDP_RBAC_MODE) : 'audit';
    if ($mode === 'off') {
        return;
    }

    // Rank: staff-and-up = enforce; below = audit. cdp_roleRankById lives in
    // helpers/rbac.php (rank 2+ = staff/admin/superadmin).
    if (!function_exists('cdp_roleRankById')) {
        require_once __DIR__ . '/rbac.php';
    }
    $isStaffUp = cdp_roleRankById((int)$user->userlevel) >= 2;
    $shouldEnforce = ($mode === 'enforce') && $isStaffUp;

    if (!$shouldEnforce) {
        error_log(sprintf(
            'RBAC_DENY mode=%s enforced=0 uid=%s role=%s need=[%s] script=%s uri=%s',
            $mode,
            $user->uid ?? '?',
            $user->userlevel ?? '?',
            implode(',', $perms),
            basename($_SERVER['SCRIPT_NAME'] ?? ''),
            $_SERVER['REQUEST_URI'] ?? ''
        ));
        return;
    }

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
