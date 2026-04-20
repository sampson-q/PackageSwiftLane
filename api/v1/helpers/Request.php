<?php

function api_get_request_data(): array {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }

    return $_POST;
}

/**
 * Get client IP address, considering proxy headers
 * @return string
 */
function api_get_client_ip(): string {
    // Check for IP from Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    // Check for IP from shared internet
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IP from proxy forwarded header
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Take the first IP if multiple are present
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    // Fall back to direct connection
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function api_pagination_params(int $defaultPer = 25, int $maxPer = 100): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min($maxPer, (int)($_GET['per_page'] ?? $defaultPer)));
    $offset = ($page - 1) * $perPage;

    // Prevent DoS via excessive page numbers (limit to 100,000 pages)
    $maxPage = 100000;
    if ($page > $maxPage) {
        $page = $maxPage;
        $offset = ($page - 1) * $perPage;
    }

    return [$page, $perPage, $offset];
}

function api_pagination_meta(int $page, int $perPage, int $total): array {
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
    ];
}

