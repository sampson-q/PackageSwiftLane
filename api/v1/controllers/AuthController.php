<?php
// api/v1/controllers/AuthController.php
// Uses project's OtpService for database-backed OTP challenges
// Uses OtpService's trusted device management

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';
require_once __DIR__ . '/../helpers/Logger.php';

class AuthController {
    protected $db;
    protected $apiConfig;
    protected $otpService;
    protected $user;
    protected $devMode = false;

    public function __construct() {
        $this->db = $GLOBALS['db'] ?? new Conexion();
        $this->apiConfig = $GLOBALS['api_config'] ?? [];
        $this->otpService = new OtpService();
        $this->user = new User();
        $this->devMode = !empty($this->apiConfig['dev_mode']);
    }

    protected function getRequestData() {
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ctype, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) return $json;
        }
        return $_POST;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REGISTRATION FLOW
    // ─────────────────────────────────────────────────────────────────────────

    public function register() {
        $data = $this->getRequestData();
        $required = ['username','email','pass','pass2','fname','lname','phone','document_number','document_type','country','state','city','address','postal','terms'];
        $errors = [];

        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === '') {
                $errors[$f] = 'Please enter ' . str_replace('_',' ',$f);
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        // Username validation - alphanumeric + underscore, hyphen, dot
        if (!empty($data['username']) && !preg_match('/^[a-z0-9._-]{3,20}$/i', $data['username'])) {
            $errors['username'] = 'Username must be 3-20 characters (alphanumeric, dot, hyphen, underscore)';
        }

        // Phone validation - basic international format
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9+\-()]/i', '', $data['phone']);
            $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
                $errors['phone'] = 'Invalid phone number (must be 10-15 digits)';
            }
        }

        if ($this->user->cdp_emailExists($data['email'] ?? '')) {
            $errors['email'] = 'Email already in use';
        }

        if ($this->user->cdp_usernameExists($data['username'] ?? '')) {
            $errors['username'] = 'Username already in use';
        }

        if (!empty($data['pass']) && !empty($data['pass2'])) {
            if ($data['pass'] !== $data['pass2']) {
                $errors['pass2'] = 'Passwords do not match';
            } elseif (strlen($data['pass']) < 10) {
                $errors['pass'] = 'Password too short (minimum 10 characters)';
            } else {
                // Password strength: require 3 of 4 (upper, lower, digit, special)
                $hasUpper = preg_match('/[A-Z]/', $data['pass']);
                $hasLower = preg_match('/[a-z]/', $data['pass']);
                $hasDigit = preg_match('/\d/', $data['pass']);
                $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/\\\\|`~]/', $data['pass']);
                $strength = $hasUpper + $hasLower + $hasDigit + $hasSpecial;
                if ($strength < 3) {
                    $errors['pass'] = 'Password must contain uppercase, lowercase, digits, and special characters';
                }
            }
        }

        if (!empty($errors)) {
            send_error('validation_failed', 422, $errors);
        }

        // Create temporary user ID for OTP (use email hash to track signup state)
        $tempId = random_int(100000000, 999999999);

        // Store signup payload temporarily
        $payload = [
            'username' => cdp_sanitize($data['username']),
            'password' => password_hash($data['pass'], PASSWORD_DEFAULT),
            'email'    => cdp_sanitize($data['email']),
            'fname'    => cdp_sanitize($data['fname']),
            'lname'    => cdp_sanitize($data['lname']),
            'document_number' => cdp_sanitize($data['document_number']),
            'document_type'   => cdp_sanitize($data['document_type']),
            'phone'    => cdp_sanitize($data['phone']),
            'terms'    => (int)($data['terms'] ?? 0),
            'country'  => cdp_sanitize($data['country']),
            'state'    => cdp_sanitize($data['state']),
            'city'     => cdp_sanitize($data['city']),
            'address'  => cdp_sanitize($data['address']),
            'postal'   => cdp_sanitize($data['postal']),
        ];

        try {
            // Create OTP challenge for signup
            $challenge = $this->otpService->createChallenge(
                $tempId,
                'signup',
                $payload,
                300 // 5 minutes
            );

            // Send OTP email
            $emailResult = $this->otpService->sendOtpEmail(
                $payload['email'],
                $payload['fname'],
                $challenge['code'],
                'signup'
            );

            if (!$emailResult['ok']) {
                api_log_security_event('signup_otp_send_failed', null, ['email' => $payload['email'], 'reason' => $emailResult['error']]);
                send_error('Failed to send OTP email', 500, ['detail' => $emailResult['error']]);
            }

            api_log_request('signup_initiated', null, 'info');

            $resp = [
                'requires_otp' => true,
                'challenge_id' => $challenge['id'],
                'expires_in' => 300
            ];

            if ($this->devMode) {
                $resp['debug_otp'] = $challenge['code'];
            }

            send_success($resp, 'OTP sent to email', 200);

            } catch (Throwable $e) {
            error_log('SIGNUP_OTP_EXCEPTION: ' . $e->getMessage());
            send_error('Failed to initialize signup', 500);
        }
    }

    public function verifyRegisterOtp() {
        $data = $this->getRequestData();
        $challengeId = (int)($data['challenge_id'] ?? 0);
        $code = trim($data['otp'] ?? '');

        if ($challengeId <= 0 || $code === '') {
            send_error('challenge_id and otp required', 400);
        }

        try {
            $result = $this->otpService->verifyChallenge($challengeId, $code, 'signup');
            if (!$result['ok']) {
                api_log_security_event('signup_otp_verification_failed', null, ['reason' => $result['error']]);
                send_error('OTP verification failed: ' . $result['error'], 401);
            }

            $payload = $result['metadata'];
            if (empty($payload) || !isset($payload['email'])) {
                send_error('Invalid signup data', 400);
            }

            // Transaction: Create user with address and recipient
            try {
                $this->db->cdp_query("START TRANSACTION");
                $this->db->cdp_execute();

                // Double-check for duplicates inside transaction
                $this->db->cdp_query('SELECT id FROM cdb_users WHERE email = :email LIMIT 1');
                $this->db->bind(':email', $payload['email']);
                $this->db->cdp_execute();
                    if ($this->db->cdp_registro()) {
                    $this->db->cdp_query("ROLLBACK");
                    $this->db->cdp_execute();
                    send_error('Email already registered', 409);
                }

                $this->db->cdp_query('SELECT id FROM cdb_users WHERE username = :username LIMIT 1');
                $this->db->bind(':username', $payload['username']);
                $this->db->cdp_execute();
                if ($this->db->cdp_registro()) {
                    $this->db->cdp_query("ROLLBACK");
                    $this->db->cdp_execute();
                    send_error('Username already in use', 409);
                }

                // Insert user
                $core = new Core();
                $this->db->cdp_query('INSERT INTO cdb_users
                    (username,password,userlevel,email,fname,lname,document_number,document_type,created,phone,active,terms,locker)
                    VALUES (:username, :password, :userlevel, :email, :fname, :lname, :docnum, :doctype, NOW(), :phone, :active, :terms, :locker)
                ');
                $this->db->bind(':username', $payload['username']);
                $this->db->bind(':password', $payload['password']);
                $this->db->bind(':userlevel', 1); // Customer
                $this->db->bind(':email', $payload['email']);
                $this->db->bind(':fname', $payload['fname']);
                $this->db->bind(':lname', $payload['lname']);
                $this->db->bind(':docnum', $payload['document_number']);
                $this->db->bind(':doctype', $payload['document_type']);
                $this->db->bind(':phone', $payload['phone']);
                $this->db->bind(':active', 1);
                $this->db->bind(':terms', $payload['terms']);
                $this->db->bind(':locker', $core->prefix_locker . ' ' . ($payload['locker'] ?? ''));
                $this->db->cdp_execute();

                $newUserId = (int)$this->db->dbh->lastInsertId();
                if (!$newUserId) {
                    $this->db->cdp_query("ROLLBACK");
                    $this->db->cdp_execute();
                    send_error('Failed to create user', 500);
                }

                // Insert address if helper exists
                if (function_exists('cdp_insertAddressCustomer')) {
                    $addr = [
                        'user_id' => $newUserId,
                        'address' => $payload['address'],
                        'country' => $payload['country'],
                        'city'    => $payload['city'],
                        'state'   => $payload['state'],
                        'postal'  => $payload['postal']
                    ];
                    cdp_insertAddressCustomer($addr);
                }

                // Insert recipient if helpers exist
                if (function_exists('cdp_insertRecipient') && function_exists('cdp_insertAddressRecipient')) {
                    $recipient = [
                        'email'     => $payload['email'],
                        'fname'     => $payload['fname'],
                        'lname'     => $payload['lname'],
                        'phone'     => $payload['phone'],
                        'sender_id' => $newUserId
                    ];
                    $rid = cdp_insertRecipient($recipient);
                    if ($rid) {
                        cdp_insertAddressRecipient(array_merge(['recipient_id' => $rid], $addr ?? []));
                    }
                }

                // Notify admins if helpers exist
                if (function_exists('cdp_getUsersAdminEmployees') && function_exists('cdp_insertNotificationsUsers')) {
                    $this->db->cdp_query("INSERT INTO cdb_notifications
                        (user_id, order_id, notification_description, shipping_type, notification_date)
                        VALUES (:uid, :oid, :desc, :stype, NOW())");
                    $this->db->bind(':uid', $newUserId);
                    $this->db->bind(':oid', $newUserId);
                    $this->db->bind(':desc', $GLOBALS['lang']['messagesform73'] ?? 'New user registered');
                    $this->db->bind(':stype', '0');
                    $this->db->cdp_execute();
                    $notifId = (int)$this->db->dbh->lastInsertId();
                    foreach (cdp_getUsersAdminEmployees() as $emp) {
                        cdp_insertNotificationsUsers($notifId, $emp->id);
                    }
                }

                $this->db->cdp_query("COMMIT");
                $this->db->cdp_execute();

                api_log_auth_success($newUserId, 'signup');

                $resultData = [
                    'user_id' => $newUserId,
                    'username' => $payload['username'],
                    'email' => $payload['email'],
                    'name' => trim($payload['fname'] . ' ' . $payload['lname'])
                ];

                send_success($resultData, 'Account created successfully', 201);

                } catch (Throwable $e) {
                try { $this->db->cdp_query("ROLLBACK"); $this->db->cdp_execute(); } catch (Exception $ee) {}
                error_log('SIGNUP_CREATE_EXCEPTION: ' . $e->getMessage());
                send_error('Internal error during signup', 500);
            }

        } catch (Throwable $e) {
            error_log('SIGNUP_VERIFY_EXCEPTION: ' . $e->getMessage());
            send_error('Verification failed', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOGIN FLOW
    // ─────────────────────────────────────────────────────────────────────────

    public function login() {
        $data = $this->getRequestData();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $rememberMe = !empty($data['remember_me']);

        if ($username === '' || $password === '') {
            api_log_auth_failure($username, 'missing_credentials');
            send_error('username and password required', 400);
        }

        $status = $this->user->cdp_checkStatus($username, $password);
        if ($status === 0) {
            api_log_auth_failure($username, 'invalid_credentials');
            send_error('Invalid credentials', 401);
        }
        if ($status === 2) {
            api_log_auth_failure($username, 'account_inactive');
            send_error('Account not activated', 403);
        }

        $u = $this->user->cdp_getUserInfo($username);
        if (!$u) {
            api_log_auth_failure($username, 'user_not_found');
            send_error('User not found', 404);
        }

        // Check trusted device
        if ($this->otpService->isTrustedDevice((int)$u->id)) {
            return $this->issueJwt((int)$u->id, $u->username, (int)$u->userlevel, 'login_trusted_device');
        }

        try {
            // Create OTP challenge for login
            $challenge = $this->otpService->createChallenge(
                (int)$u->id,
                'login',
                ['remember_me' => $rememberMe],
                300
            );

            // Send OTP email
            $emailResult = $this->otpService->sendOtpEmail(
                $u->email,
                $u->fname,
                $challenge['code'],
                'login'
            );

                if (!$emailResult['ok']) {
                api_log_security_event('login_otp_send_failed', (int)$u->id, ['reason' => $emailResult['error']]);
                send_error('Failed to send OTP', 500, ['detail' => $emailResult['error']]);
            }

            $response = [
                'requires_otp' => true,
                'challenge_id' => $challenge['id'],
                'expires_in' => 300
            ];

            if ($this->devMode) {
                $response['debug_otp'] = $challenge['code'];
            }

            send_success($response, 'OTP sent to email', 200);

        } catch (Throwable $e) {
            error_log('LOGIN_OTP_EXCEPTION: ' . $e->getMessage());
            send_error('Failed to send OTP', 500);
        }
    }

    public function verifyLoginOtp() {
        $data = $this->getRequestData();
        $challengeId = (int)($data['challenge_id'] ?? 0);
        $code = trim($data['otp'] ?? '');

        if ($challengeId <= 0 || $code === '') {
            send_error('challenge_id and otp required', 400);
        }

        try {
            $result = $this->otpService->verifyChallenge($challengeId, $code, 'login');
            if (!$result['ok']) {
                api_log_security_event('login_otp_verification_failed', null, ['reason' => $result['error']]);
                send_error('OTP verification failed: ' . $result['error'], 401);
            }

            $userId = (int)$result['user_id'];
            $metadata = $result['metadata'] ?? [];
            $rememberMe = $metadata['remember_me'] ?? false;

            // Fetch user info
            $this->db->cdp_query("SELECT id, username, email, fname, lname, userlevel, active FROM cdb_users WHERE id = :id LIMIT 1");
            $this->db->bind(':id', $userId);
            $this->db->cdp_execute();
            $u = $this->db->cdp_registro();
            if (!$u || (int)$u->active !== 1) {
                send_error('User not found or inactive', 404);
            }

            // Handle remember_me: create trusted device
            if ($rememberMe) {
                $this->otpService->rememberTrustedDevice($userId);
            }

            // Update lastlogin/lastip
            $ip = api_get_client_ip();
            $this->db->cdp_query('UPDATE cdb_users SET lastlogin=:lastlogin, lastip=:lastip WHERE id=:id');
            $this->db->bind(':lastlogin', date("Y-m-d H:i:s"));
            $this->db->bind(':lastip', $ip);
            $this->db->bind(':id', $userId);
            $this->db->cdp_execute();

            api_log_auth_success($userId, 'login');

            return $this->issueJwt($userId, $u->username, (int)$u->userlevel, 'login');

        } catch (Throwable $e) {
            error_log('LOGIN_VERIFY_EXCEPTION: ' . $e->getMessage());
            send_error('Verification failed', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROFILE & TOKEN
    // ─────────────────────────────────────────────────────────────────────────

    public function me() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);

        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query("SELECT id, username, email, fname, lname, userlevel, lastlogin FROM cdb_users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $uid);
        $this->db->cdp_execute();
        $u = $this->db->cdp_registro();
        if (!$u) send_error('User not found', 404);

        $safe = [
            'id' => (int)$u->id,
            'username' => $u->username,
            'email' => $u->email,
            'name' => trim(($u->fname ?? '') . ' ' . ($u->lname ?? '')),
            'userlevel' => (int)$u->userlevel,
            'lastlogin' => $u->lastlogin
        ];
        send_success(['user' => $safe], 'User fetched', 200);
    }

    public function logout() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if ($payload) {
            api_log_auth_success((int)($payload['uid'] ?? 0), 'logout');
        }
        send_success(null, 'Logged out', 200);
    }

    public function refresh() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);

        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        $this->db->cdp_query("SELECT id, username, userlevel, active FROM cdb_users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $uid);
        $this->db->cdp_execute();
        $u = $this->db->cdp_registro();
        if (!$u || (int)$u->active !== 1) {
            send_error('User no longer active', 403);
        }

        return $this->issueJwt($uid, $u->username, (int)$u->userlevel, 'refresh');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASSWORD RESET
    // ─────────────────────────────────────────────────────────────────────────

    public function forgotPassword() {
        $data = $this->getRequestData();
        $email = trim($data['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            send_error('Valid email required', 400);
        }

        $u = $this->user->cdp_getUserInfo($email);
        if (!$u) {
            // Don't reveal if user exists
            send_success(null, 'If email exists, password reset link sent', 200);
        }

        try {
            // Create password reset session
            $resetToken = $this->otpService->createResetSession((int)$u->id, 900); // 15 minutes

            // Send reset email
            $resetUrl = $this->apiConfig['reset_url_base'] ?? 'https://yourdomain.com/reset-password';
            $resetLink = $resetUrl . '?token=' . urlencode($resetToken);

            $emailResult = $this->otpService->sendOtpEmail(
                $u->email,
                $u->fname,
                $resetLink,
                'password reset'
            );

            if (!$emailResult['ok']) {
                api_log_security_event('reset_email_send_failed', (int)$u->id, ['reason' => $emailResult['error']]);
            }

            send_success(null, 'If email exists, password reset link sent', 200);

        } catch (Throwable $e) {
            error_log('PASSWORD_RESET_EXCEPTION: ' . $e->getMessage());
            send_error('Failed to process reset', 500);
        }
    }

    public function resetPassword() {
        $data = $this->getRequestData();
        $token = trim($data['token'] ?? '');
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if ($token === '' || $newPassword === '' || $confirmPassword === '') {
            send_error('token and passwords required', 400);
        }

        if ($newPassword !== $confirmPassword) {
            send_error('Passwords do not match', 400);
        }

        if (strlen($newPassword) < 10) {
            send_error('Password must be at least 10 characters', 400);
        }

        try {
            $userId = $this->otpService->consumeResetSession($token);
            if (!$userId) {
                api_log_security_event('reset_token_invalid_or_expired', null, ['token' => substr($token, 0, 10) . '...']);
                send_error('Reset token invalid or expired', 401);
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->cdp_query('UPDATE cdb_users SET password=:pwd WHERE id=:id');
            $this->db->bind(':pwd', $hashedPassword);
            $this->db->bind(':id', $userId);
            $this->db->cdp_execute();

            // Revoke all trusted devices after password reset
            $this->otpService->revokeAllTrustedDevices($userId);

            api_log_auth_success($userId, 'password_reset');

            send_success(null, 'Password reset successfully. Please log in.', 200);

        } catch (Throwable $e) {
            error_log('PASSWORD_RESET_CONSUME_EXCEPTION: ' . $e->getMessage());
            send_error('Failed to reset password', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DEVICE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    public function revokeAllDevices() {
        $payload = $GLOBALS['auth_user'] ?? null;
        if (!$payload) send_error('Not authenticated', 401);

        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) send_error('Invalid token', 401);

        try {
            $this->otpService->revokeAllTrustedDevices($uid);
            api_log_request('revoke_all_devices', $uid, 'info');
            send_success(null, 'All trusted devices revoked', 200);
        } catch (Throwable $e) {
            error_log('REVOKE_DEVICES_EXCEPTION: ' . $e->getMessage());
            send_error('Failed to revoke devices', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function issueJwt(int $userId, string $username, int $userlevel, string $method = 'login'): string | null {
        $secret = $this->apiConfig['jwt_secret'] ?? null;
        $ttl = $this->apiConfig['jwt_ttl'] ?? 3600;
        if (!$secret) send_error('Server misconfigured (jwt_secret)', 500);

        $payload = ['uid' => $userId, 'username' => $username, 'userlevel' => $userlevel];
        $token = jwt_encode($payload, $secret, $ttl);

        send_success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => $userId,
                'username' => $username
            ]
        ], 'Login successful', 200);
    }
}
