<?php
/**
 * PHPUnit bootstrap for the PackageSwiftLane API test suite.
 *
 * Defines the DB constants that Conexion requires, includes the three core
 * lib classes, and provides stubs for functions that live inside the
 * ionCube-protected loader.php so that unit tests can run without a web server
 * or ionCube runtime.
 *
 * Integration tests that actually hit a MySQL instance will use env variables:
 *   CDP_DB_HOST, CDP_DB_NAME, CDP_DB_USER, CDP_DB_PASS
 * and will mark themselves as @group integration (skipped by default).
 */

// ── DB constants ─────────────────────────────────────────────────────────────
if (!defined('CDP_DB_HOST')) define('CDP_DB_HOST', getenv('CDP_DB_HOST') ?: '127.0.0.1');
if (!defined('CDP_DB_NAME')) define('CDP_DB_NAME', getenv('CDP_DB_NAME') ?: 'test_db');
if (!defined('CDP_DB_USER')) define('CDP_DB_USER', getenv('CDP_DB_USER') ?: 'root');
if (!defined('CDP_DB_PASS')) define('CDP_DB_PASS', getenv('CDP_DB_PASS') ?: '');

// Signal to bootstrap.php that we have already loaded the foundation.
define('CDP_API_TEST_MODE', true);

// ── Core classes ──────────────────────────────────────────────────────────────
$root = dirname(__DIR__);
require_once $root . '/lib/Conexion.php';

// ── Stubs for ionCube-protected functions ─────────────────────────────────────

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

if (!function_exists('cdp_getAgencyContext')) {
    function cdp_getAgencyContext()
    {
        return ['is_restricted' => false, 'agency_id' => null];
    }
}

if (!function_exists('cdp_getAgencyBranchIdForUser')) {
    function cdp_getAgencyBranchIdForUser($name_off)
    {
        return 0;
    }
}

// ── API classes ───────────────────────────────────────────────────────────────
require_once $root . '/api/v1/ApiResponse.php';
require_once $root . '/api/v1/ApiValidator.php';
require_once $root . '/api/v1/ApiAuth.php';
