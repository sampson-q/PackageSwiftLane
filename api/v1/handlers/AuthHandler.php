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
            'otp_code' => 'nullable|string|regex:/^[0-9]{6}$/',
            'challenge_id' => 'nullable|integer|min:1',
            'name' => 'nullable|string|max:100',
            'remember_me' => 'nullable|boolean',
        ]);

        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user = new User();
        $status = $user->cdp_checkStatus(cdp_sanitize($data['username']), $data['password']);
        // Never interpret an unknown/null status as a successful password check.
        if ($status !== 1) {
            ApiResponse::error('invalid_credentials', 'Incorrect username or password.', 401);
        }

        // Load user info
        $userInfo = $user->cdp_getUserInfo(cdp_sanitize($data['username']));
        if (!$userInfo) {
            ApiResponse::serverError('Could not load user profile.');
        }

        // API credentials must satisfy the same OTP policy as browser credentials.
        if (cdp_otpEnabled()) {
            require_once dirname(__DIR__, 3) . '/lib/OtpService.php';
            $otp = new OtpService();
            if (!empty($data['otp_code']) && !empty($data['challenge_id'])) {
                $challenge = $otp->getChallengeInfo((int) $data['challenge_id']);
                if (!$challenge || (int) $challenge->user_id !== (int) $userInfo->id
                    || $challenge->purpose !== 'api_login') {
                    ApiResponse::unauthorized('Invalid one-time code request.');
                }
                $verified = $otp->verifyChallenge((int) $data['challenge_id'], $data['otp_code'], 'api_login');
                if (empty($verified['ok'])) {
                    ApiResponse::unauthorized('Invalid or expired one-time code.');
                }
            } else {
                $challenge = $otp->createChallenge((int) $userInfo->id, 'api_login');
                if (!empty($challenge['blocked'])) {
                    ApiResponse::error('rate_limited', 'Please wait before requesting another code.', 429);
                }
                $otp->sendOtpEmail($userInfo->email, $userInfo->fname . ' ' . $userInfo->lname, $challenge['code'], 'login');
                ApiResponse::success(['status' => 'otp_required', 'challenge_id' => $challenge['id']], [], 202);
            }
        }

        // Issue token
        $ttl      = in_array($data['remember_me'] ?? false, [true, 1, '1', 'true'], true) ? 24 * 30 : ApiAuth::DEFAULT_TTL_HOURS;
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
        ApiAuth::requireAuth();
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
