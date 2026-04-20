<?php
// helpers/Jwt.php
// Small dependency-free JWT encode/decode (HS256 only).
// Note: This is intentionally minimal for learning & local dev.
// For production consider a well-tested library.

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    $pad = 4 - (strlen($data) % 4);
    if ($pad !== 4) $data .= str_repeat('=', $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Create a JWT (HMAC SHA256)
 * @param array $payload
 * @param string $secret
 * @param int|null $ttl seconds to live
 * @return string
 */
function jwt_encode(array $payload, string $secret, ?int $ttl = null): string {
    $header = ['typ'=>'JWT', 'alg'=>'HS256'];
    $now = time();
    $payload['iat'] = $now;
    if ($ttl !== null) $payload['exp'] = $now + $ttl;

    $segments = [];
    $segments[] = base64url_encode(json_encode($header));
    $segments[] = base64url_encode(json_encode($payload));
    $signing_input = implode('.', $segments);
    $signature = hash_hmac('sha256', $signing_input, $secret, true);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

/**
 * Decode & verify a JWT
 * @param string $jwt
 * @param string $secret
 * @return array|false Returns payload array if valid (also includes iat/exp if present), false if invalid
 */
function jwt_decode(string $jwt, string $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    list($b64head, $b64payload, $b64sig) = $parts;
    $head = json_decode(base64url_decode($b64head), true);
    $payload = json_decode(base64url_decode($b64payload), true);
    $sig = base64url_decode($b64sig);

    if (!is_array($head) || !is_array($payload)) return false;
    if (empty($head['alg']) || $head['alg'] !== 'HS256') return false;

    $valid_sig = hash_hmac('sha256', "$b64head.$b64payload", $secret, true);
    if (!hash_equals($valid_sig, $sig)) return false;

    // Verify exp if present
    if (isset($payload['exp']) && time() > $payload['exp']) return false;

    return $payload;
}
