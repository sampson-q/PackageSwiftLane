<?php
/**
 * API Request Logging Helper
 * Logs all API calls for audit trails and security monitoring
 */

function api_log_request(string $action = 'request', ?int $userId = null, string $level = 'info') {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'ip' => api_get_client_ip(),
        'user_id' => $userId,
        'action' => $action,
        'level' => $level,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
    ];

    error_log('[API-' . strtoupper($level) . '] ' . json_encode($logData, JSON_UNESCAPED_UNICODE));
}

function api_log_auth_success(int $userId, string $method = 'password') {
    api_log_request('auth_success_' . $method, $userId, 'info');
}

function api_log_auth_failure(string $username = '', string $reason = 'invalid_credentials') {
    api_log_request('auth_failure_' . $reason, null, 'warning');
}

function api_log_security_event(string $event, ?int $userId = null, array $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $userId,
        'ip' => api_get_client_ip(),
        'details' => $details,
    ];
    error_log('[API-SECURITY] ' . json_encode($logData, JSON_UNESCAPED_UNICODE));
}
