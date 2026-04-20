<?php
// middlewares/CorsMiddleware.php
$config = $GLOBALS['api_config']['cors'] ?? null;
$allowOrigin = $config['allow_origin'] ?? '';
$allowMethods = $config['allow_methods'] ?? 'GET, POST, OPTIONS, PUT, DELETE';
$allowHeaders = $config['allow_headers'] ?? 'Content-Type, Authorization, X-Requested-With, Accept';
$allowCredentials = $config['allow_credentials'] ?? false;

// Handle CORS origin - can be array or string
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (is_array($allowOrigin)) {
    if (in_array($origin, $allowOrigin, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
} else {
    header("Access-Control-Allow-Origin: {$allowOrigin}");
}

header("Access-Control-Allow-Methods: {$allowMethods}");
header("Access-Control-Allow-Headers: {$allowHeaders}");
if ($allowCredentials) {
    header('Access-Control-Allow-Credentials: true');
}

// If it's a preflight request, stop here.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
