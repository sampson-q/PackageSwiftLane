<?php
// index.php - front controller for /api/v1
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/routes.php';

// Run CORS middleware first
require_once __DIR__ . '/middlewares/CorsMiddleware.php';

// Build method and path relative to mount point
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Detect the base mount. Adjust if you use a different base path.
$scriptName = dirname($_SERVER['SCRIPT_NAME']); // e.g., /HostingerRepoRaptors/api/v1
$basePath = rtrim($scriptName, '/');

// Remove base path prefix from URI
$path = $uri;
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $path = substr($uri, strlen($basePath));
}
$path = '/' . ltrim($path, '/');
if ($path === '') $path = '/';

// Dispatch the route (routes.php defines dispatch())
dispatch($method, $path);
