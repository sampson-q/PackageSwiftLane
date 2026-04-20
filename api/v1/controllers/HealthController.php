<?php
require_once __DIR__ . '/../helpers/Response.php';

class HealthController {
    public function status() {
        $payload = [
            'status' => 'ok',
            'time' => date('c'),
            'version' => 'v1',
        ];
        send_success($payload, 'API is healthy', 200);
    }
}

