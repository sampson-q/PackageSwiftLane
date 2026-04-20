<?php
require_once __DIR__ . '/../helpers/Jwt.php';
$apiConf = $GLOBALS['api_config'];
$secret = $apiConf['jwt_secret'];

/**
 * require_auth - Enforces a valid Bearer token.
 * On success populates $GLOBALS['auth_user'] with JWT payload.
 */
function require_auth() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }

    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    $token = null;
    if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
    } else {
        $token = $_GET['token'] ?? null;
    }

    if (!$token) \send_error('Missing Authorization token', 401);

    $secret = $GLOBALS['api_config']['jwt_secret'] ?? null;
    if (!$secret) \send_error('Server misconfigured', 500);

    $payload = jwt_decode($token, $secret);
    if ($payload === false) \send_error('Invalid or expired token', 401);

    $GLOBALS['auth_user'] = $payload;
}
