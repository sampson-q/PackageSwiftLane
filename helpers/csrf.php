<?php

if (!function_exists('cdp_csrf_ensure_session')) {
    function cdp_csrf_ensure_session()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}

if (!function_exists('cdp_csrf_param')) {
    function cdp_csrf_param()
    {
        return '_csrf_token';
    }
}

if (!function_exists('cdp_csrf_token')) {
    function cdp_csrf_token()
    {
        cdp_csrf_ensure_session();

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('cdp_csrf_validate_request')) {
    function cdp_csrf_validate_request()
    {
        cdp_csrf_ensure_session();
        $expected = $_SESSION['csrf_token'] ?? '';

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $provided = '';

        if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $provided = trim($_SERVER['HTTP_X_CSRF_TOKEN']);
        }

        if ($provided === '' && isset($_POST[cdp_csrf_param()]) && is_string($_POST[cdp_csrf_param()])) {
            $provided = trim($_POST[cdp_csrf_param()]);
        }

        if ($provided === '' && isset($_REQUEST[cdp_csrf_param()]) && is_string($_REQUEST[cdp_csrf_param()])) {
            $provided = trim($_REQUEST[cdp_csrf_param()]);
        }

        return $provided !== '' && hash_equals($expected, $provided);
    }
}

