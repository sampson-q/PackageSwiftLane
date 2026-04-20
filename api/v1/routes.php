<?php
// routes.php - dispatcher for api v1
// Included after bootstrap.php in index.php

function dispatch(string $method, string $path) {
    // Normalise
    $path = rtrim($path, '/');
    if ($path === '') $path = '/';

    // Auth endpoints
    if ($method === 'POST' && $path === '/auth/register') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->register();
    }
    if ($method === 'POST' && $path === '/auth/verify-register-otp') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->verifyRegisterOtp();
    }
    if ($method === 'POST' && $path === '/auth/login') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->login();
    }
    if ($method === 'POST' && $path === '/auth/verify-login-otp') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->verifyLoginOtp();
    }
    if ($method === 'GET' && $path === '/auth/me') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->me();
    }
    if ($method === 'POST' && $path === '/auth/logout') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->logout();
    }
    if ($method === 'POST' && $path === '/auth/refresh') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->refresh();
    }
    if ($method === 'POST' && $path === '/auth/forgot-password') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->forgotPassword();
    }
    if ($method === 'POST' && $path === '/auth/reset-password') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->resetPassword();
    }
    if ($method === 'POST' && $path === '/auth/devices/revoke-all') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/AuthController.php';
        $c = new AuthController();
        return $c->revokeAllDevices();
    }

    // Protected sample resource
    if ($method === 'GET' && $path === '/customers') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/CustomersController.php';
        $c = new CustomersController();
        return $c->index();
    }

    // Health
    if ($method === 'GET' && $path === '/health') {
        require_once __DIR__ . '/controllers/HealthController.php';
        $c = new HealthController();
        return $c->status();
    }

    // Public tracking
    if ($method === 'GET' && preg_match('#^/tracking/([^/]+)$#', $path, $m)) {
        require_once __DIR__ . '/controllers/TrackingController.php';
        $c = new TrackingController();
        return $c->history($m[1]);
    }

    // Lookups
    if ($method === 'GET' && $path === '/lookup/countries') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->countries();
    }
    if ($method === 'GET' && $path === '/lookup/states') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->states();
    }
    if ($method === 'GET' && $path === '/lookup/cities') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->cities();
    }
    if ($method === 'GET' && $path === '/lookup/shipping-modes') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->shippingModes();
    }
    if ($method === 'GET' && $path === '/lookup/delivery-times') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->deliveryTimes();
    }
    if ($method === 'GET' && $path === '/lookup/packaging') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->packaging();
    }
    if ($method === 'GET' && $path === '/lookup/payment-methods') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->paymentMethods();
    }
    if ($method === 'GET' && $path === '/lookup/courier-companies') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->courierCompanies();
    }
    if ($method === 'GET' && $path === '/lookup/incoterms') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->incoterms();
    }
    if ($method === 'GET' && $path === '/lookup/offices') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->offices();
    }
    if ($method === 'GET' && $path === '/lookup/branches') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->branches();
    }
    if ($method === 'GET' && $path === '/lookup/statuses') {
        require_once __DIR__ . '/controllers/LookupController.php';
        $c = new LookupController();
        return $c->statuses();
    }

    // Profile
    if ($method === 'GET' && $path === '/profile') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ProfileController.php';
        $c = new ProfileController();
        return $c->show();
    }
    if ($method === 'PUT' && $path === '/profile') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ProfileController.php';
        $c = new ProfileController();
        return $c->update();
    }

    // Notifications
    if ($method === 'GET' && $path === '/notifications') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/NotificationsController.php';
        $c = new NotificationsController();
        return $c->index();
    }
    if ($method === 'POST' && preg_match('#^/notifications/(\\d+)/read$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/NotificationsController.php';
        $c = new NotificationsController();
        return $c->markRead((int)$m[1]);
    }

    // Recipients
    if ($method === 'GET' && $path === '/recipients') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/RecipientsController.php';
        $c = new RecipientsController();
        return $c->index();
    }
    if ($method === 'POST' && $path === '/recipients') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/RecipientsController.php';
        $c = new RecipientsController();
        return $c->create();
    }
    if ($method === 'GET' && preg_match('#^/recipients/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/RecipientsController.php';
        $c = new RecipientsController();
        return $c->show((int)$m[1]);
    }

    // Pre-alerts
    if ($method === 'GET' && $path === '/prealerts') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PrealertsController.php';
        $c = new PrealertsController();
        return $c->index();
    }
    if ($method === 'POST' && $path === '/prealerts') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PrealertsController.php';
        $c = new PrealertsController();
        return $c->create();
    }
    if ($method === 'GET' && preg_match('#^/prealerts/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PrealertsController.php';
        $c = new PrealertsController();
        return $c->show((int)$m[1]);
    }

    // Shipments & pickups
    if ($method === 'GET' && $path === '/shipments') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(false);
        return $c->index();
    }
    if ($method === 'GET' && preg_match('#^/shipments/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(false);
        return $c->show((int)$m[1]);
    }
    if ($method === 'GET' && preg_match('#^/shipments/(\\d+)/tracking$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(false);
        return $c->tracking((int)$m[1]);
    }
    if ($method === 'GET' && $path === '/pickups') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(true);
        return $c->index();
    }
    if ($method === 'GET' && preg_match('#^/pickups/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(true);
        return $c->show((int)$m[1]);
    }
    if ($method === 'GET' && preg_match('#^/pickups/(\\d+)/tracking$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ShipmentsController.php';
        $c = new ShipmentsController(true);
        return $c->tracking((int)$m[1]);
    }

    // Packages
    if ($method === 'GET' && $path === '/packages') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PackagesController.php';
        $c = new PackagesController();
        return $c->index();
    }
    if ($method === 'GET' && preg_match('#^/packages/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PackagesController.php';
        $c = new PackagesController();
        return $c->show((int)$m[1]);
    }
    if ($method === 'GET' && preg_match('#^/packages/(\\d+)/tracking$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/PackagesController.php';
        $c = new PackagesController();
        return $c->tracking((int)$m[1]);
    }

    // Consolidations
    if ($method === 'GET' && $path === '/consolidations') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ConsolidationsController.php';
        $c = new ConsolidationsController();
        return $c->index();
    }
    if ($method === 'GET' && preg_match('#^/consolidations/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ConsolidationsController.php';
        $c = new ConsolidationsController();
        return $c->show((int)$m[1]);
    }
    if ($method === 'GET' && preg_match('#^/consolidations/(\\d+)/details$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ConsolidationsController.php';
        $c = new ConsolidationsController();
        return $c->details((int)$m[1]);
    }
    if ($method === 'GET' && $path === '/consolidations-packages') {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ConsolidationsController.php';
        $c = new ConsolidationsController();
        return $c->packagesIndex();
    }
    if ($method === 'GET' && preg_match('#^/consolidations-packages/(\\d+)$#', $path, $m)) {
        require_once __DIR__ . '/middlewares/AuthMiddleware.php';
        require_auth();
        require_once __DIR__ . '/controllers/ConsolidationsController.php';
        $c = new ConsolidationsController();
        return $c->packagesShow((int)$m[1]);
    }

    \send_error('Endpoint not found', 404);
}
