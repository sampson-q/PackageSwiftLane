<?php
function send_json($payload, int $status_code = 200) {
    // Set security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('Content-Security-Policy: default-src "self"; script-src "self"; style-src "self" "unsafe-inline"');

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function send_success($data = null, string $message = 'OK', int $status_code = 200, array $meta = []) {
    $out = [
        'status' => 'success',
        'code'   => $status_code,
        'message'=> $message,
        'data'   => $data,
    ];
    if ($meta !== null) $out['meta'] = $meta;
    send_json($out, $status_code);
}

function send_error(string $message = 'Error', int $status_code = 400, array $errors = []) {
    $out = [
        'status'  => 'error',
        'code'    => $status_code,
        'message' => $message,
    ];
    if ($errors !== null) $out['errors'] = $errors;
    send_json($out, $status_code);
}
