<?php
/**
 * API Bootstrap
 *
 * Loads the application foundation, sets JSON response headers and CORS.
 * In production the ionCube-protected loader.php defines all DB constants
 * and includes lib/*.php. In test mode (CDP_API_TEST_MODE=1) the test
 * bootstrap has already done that, so we skip the file-level includes.
 */

// ── Error handling ──────────────────────────────────────────────────────────
// Show nothing on stdout; errors are caught and returned as JSON 500.
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ── Load application core (skip when already bootstrapped by test harness) ──
if (!defined('CDP_DB_HOST')) {
    // Suppress any accidental HTML output from loader
    ob_start();
    require_once __DIR__ . '/../../loader.php';
    ob_end_clean();
}

// ── Load helpers ─────────────────────────────────────────────────────────────
if (!function_exists('cdp_getSettingsCourier')) {
    require_once __DIR__ . '/../../helpers/querys.php';
}

// ── Polyfill functions that come from the ionCube loader ─────────────────────
if (!function_exists('cdp_sanitize')) {
    function cdp_sanitize($data)
    {
        if (is_array($data)) {
            return array_map('cdp_sanitize', $data);
        }
        $data = trim((string)$data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

if (!function_exists('cdp_sumardias')) {
    function cdp_sumardias($date, $days)
    {
        return date('Y-m-d H:i:s', strtotime($date . ' +' . intval($days) . ' days'));
    }
}

// ── HTTP response headers ─────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── CORS ──────────────────────────────────────────────────────────────────────
$allowedOrigins = defined('CDP_API_ALLOWED_ORIGINS')
    ? explode(',', CDP_API_ALLOWED_ORIGINS)
    : ['*'];

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigins === ['*'] || in_array($requestOrigin, $allowedOrigins, true)) {
    $originHeader = ($allowedOrigins === ['*']) ? '*' : $requestOrigin;
    header("Access-Control-Allow-Origin: {$originHeader}");
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');

// Handle pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
