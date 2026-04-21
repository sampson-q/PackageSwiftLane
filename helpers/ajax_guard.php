<?php
/**
 * Guard estándar para endpoints AJAX: sesión y permisos.
 * Uso: después de loader.php incluir este archivo y llamar require_login(); require_permission('nombre_permiso');
 * Respuesta: 401 sin sesión, 403 sin permiso (JSON). Con permiso sigue la ejecución.
 */

if (!defined('DEPRIXAPRO_AJAX_GUARD_LOADED')) {
    require_once __DIR__ . '/csrf.php';
    if (!class_exists('User')) {
        require_once dirname(__DIR__) . '/loader.php';
    }
    if (!isset($user) || !$user instanceof User) {
        $user = new User();
    }
    define('DEPRIXAPRO_AJAX_GUARD_LOADED', true);
}

/**
 * Asegura que haya sesión activa. Si no, envía 401 JSON y termina.
 */
function require_login()
{
    global $user;
    if (!isset($user) || !($user instanceof User)) {
        $user = new User();
    }
    if (empty($user->logged_in)) {
        _ajax_guard_send(401, ['success' => false, 'error' => 'Unauthorized', 'message' => 'Sesión requerida']);
    }

    _ajax_guard_require_csrf();
}

/**
 * Asegura que el usuario tenga al menos uno de los permisos. Si no, envía 403 JSON y termina.
 * @param string|string[] $permission Nombre del permiso (o array de nombres, cualquiera)
 */
function require_permission($permission)
{
    global $user;
    if (!isset($user) || !($user instanceof User)) {
        $user = new User();
    }
    if (empty($user->logged_in)) {
        _ajax_guard_send(401, ['success' => false, 'error' => 'Unauthorized', 'message' => 'Sesión requerida']);
    }
    $perms = is_array($permission) ? $permission : [$permission];
    // Agencia (userlevel 6) siempre tiene acceso a view_client_list y view_recipients
    $agencyPerms = ['view_client_list', 'view_recipients'];
    if ((int)$user->userlevel === 6 && count(array_intersect($perms, $agencyPerms)) > 0) {
        return;
    }
    if (!$user->cdp_hasPermission($perms)) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'Sin permiso para esta acción']);
    }
}

/**
 * Envía respuesta JSON y termina.
 * @param int $httpCode 401 o 403
 * @param array $body
 */
function _ajax_guard_send($httpCode, array $body)
{
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

function _ajax_guard_require_csrf()
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    if (!cdp_csrf_validate_request()) {
        _ajax_guard_send(419, ['success' => false, 'error' => 'Invalid CSRF token', 'message' => 'CSRF token missing or invalid']);
    }
}
