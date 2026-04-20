<?php
/**
 * AuthHandler – login, logout, current-user endpoints.
 *
 * POST  /api/v1/auth/login   → issue API token
 * POST  /api/v1/auth/logout  → revoke current token
 * GET   /api/v1/auth/me      → return authenticated user profile
 */
class AuthHandler
{
    // ── POST /api/v1/auth/login ───────────────────────────────────────────────

    public function login(): void
    {
        $data   = ApiResponse::getRequestData();
        $errors = ApiValidator::validate($data, [
            'username' => 'required|string|min:1|max:100',
            'password' => 'required|string|min:1|max:255',
        ]);

        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user = new User();
        // cdp_login returns true | false | 'otp_required'
        // For the API, we skip OTP to allow machine-to-machine auth.
        // Credentials are validated via cdp_checkStatus; OTP is browser-flow only.
        $status = $user->cdp_checkStatus(cdp_sanitize($data['username']), $data['password']);

        // 0 = wrong credentials, 2 = inactive
        if ($status === 0) {
            ApiResponse::error('invalid_credentials', 'Incorrect username or password.', 401);
        }
        if ($status === 2) {
            ApiResponse::error('account_inactive', 'Your account is not active.', 403);
        }

        // Load user info
        $userInfo = $user->cdp_getUserInfo(cdp_sanitize($data['username']));
        if (!$userInfo) {
            ApiResponse::serverError('Could not load user profile.');
        }

        // Issue token
        $ttl      = isset($data['remember_me']) && $data['remember_me'] ? 24 * 30 : ApiAuth::DEFAULT_TTL_HOURS;
        $tokenName = isset($data['name']) ? cdp_sanitize($data['name']) : '';
        $auth      = new ApiAuth();
        $tokenData = $auth->createToken((int)$userInfo->id, $ttl, $tokenName);

        // Update last login
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_users SET lastlogin=:ts, lastip=:ip WHERE id=:id');
        $db->bind(':ts', date('Y-m-d H:i:s'));
        $db->bind(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $db->bind(':id', (int)$userInfo->id);
        $db->cdp_execute();

        ApiResponse::success([
            'token'      => $tokenData['token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenData['expires_at'],
            'user'       => self::formatUser($userInfo),
        ]);
    }

    // ── POST /api/v1/auth/logout ──────────────────────────────────────────────

    public function logout(): void
    {
        $rawToken = ApiAuth::extractBearerToken();
        if (empty($rawToken)) {
            ApiResponse::unauthorized('No token to revoke.');
        }

        $auth = new ApiAuth();
        $auth->revokeToken($rawToken);

        ApiResponse::success(['revoked' => true]);
    }

    // ── GET /api/v1/auth/me ───────────────────────────────────────────────────

    public function me(): void
    {
        $userRow = ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_users WHERE id = :id LIMIT 1');
        $db->bind(':id', (int)$userRow->id);
        $db->cdp_execute();
        $full = $db->cdp_registro();

        if (!$full) {
            ApiResponse::notFound('User not found.');
        }

        ApiResponse::success(self::formatUser($full));
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    public static function formatUser(object $row): array
    {
        return [
            'id'        => (int)$row->id,
            'username'  => $row->username,
            'email'     => $row->email,
            'first_name'=> $row->fname,
            'last_name' => $row->lname,
            'phone'     => $row->phone ?? null,
            'userlevel' => (int)$row->userlevel,
            'active'    => (bool)(int)$row->active,
            'locker'    => $row->locker ?? null,
            'name_off'  => $row->name_off ?? null,
            'agency_id' => isset($row->agency_id) ? (int)$row->agency_id : null,
            'created'   => $row->created ?? null,
        ];
    }
}
