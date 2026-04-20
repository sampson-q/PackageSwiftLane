<?php
/**
 * ApiAuth – stateless Bearer-token authentication for the REST API.
 *
 * Tokens are 64-byte hex strings. Only the SHA-256 hash is stored in the DB
 * so a compromised DB dump reveals nothing useful.
 *
 * Table: cdb_api_tokens (created automatically on first use)
 */
class ApiAuth
{
    /** Default token TTL in hours. */
    const DEFAULT_TTL_HOURS = 24;

    /** Max tokens per user (excess oldest ones are pruned on login). */
    const MAX_TOKENS_PER_USER = 10;

    private $db;

    public function __construct()
    {
        $this->db = new Conexion();
        $this->ensureTable();
    }

    // ── Table bootstrap ───────────────────────────────────────────────────────

    private function ensureTable(): void
    {
        $this->db->cdp_query("
            CREATE TABLE IF NOT EXISTS cdb_api_tokens (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                token_hash  VARCHAR(128) NOT NULL UNIQUE,
                name        VARCHAR(100) NULL,
                expires_at  DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                created_at  DATETIME NOT NULL,
                revoked_at  DATETIME NULL,
                INDEX idx_token_hash (token_hash),
                INDEX idx_user_id    (user_id),
                INDEX idx_expires    (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->cdp_execute();
    }

    // ── Token lifecycle ───────────────────────────────────────────────────────

    /**
     * Issue a new token for a user.
     *
     * @param int    $userId
     * @param int    $ttlHours
     * @param string $name    Optional human label (e.g. "mobile-app")
     * @return array{token: string, expires_at: string}
     */
    public function createToken(int $userId, int $ttlHours = self::DEFAULT_TTL_HOURS, string $name = ''): array
    {
        $this->pruneExpired();
        $this->pruneUserExcess($userId);

        $rawToken  = bin2hex(random_bytes(32)); // 64 hex chars
        $hash      = hash('sha256', $rawToken);
        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlHours * 3600);

        $this->db->cdp_query("
            INSERT INTO cdb_api_tokens (user_id, token_hash, name, expires_at, created_at)
            VALUES (:user_id, :token_hash, :name, :expires_at, :created_at)
        ");
        $this->db->bind(':user_id',    $userId);
        $this->db->bind(':token_hash', $hash);
        $this->db->bind(':name',       $name ?: null);
        $this->db->bind(':expires_at', $expiresAt);
        $this->db->bind(':created_at', $now);
        $this->db->cdp_execute();

        return ['token' => $rawToken, 'expires_at' => $expiresAt];
    }

    /**
     * Validate a raw token string.
     *
     * On success, updates last_used_at and returns the user row from cdb_users.
     * Returns null on failure (not found / expired / revoked).
     *
     * @return object|null cdb_users row
     */
    public function validateToken(string $rawToken): ?object
    {
        if (empty($rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $now  = date('Y-m-d H:i:s');

        $this->db->cdp_query("
            SELECT t.id AS token_id, t.user_id, t.expires_at, t.revoked_at,
                   u.id, u.username, u.email, u.fname, u.lname,
                   u.userlevel, u.active, u.name_off, u.agency_id
            FROM cdb_api_tokens t
            INNER JOIN cdb_users u ON u.id = t.user_id
            WHERE t.token_hash = :hash
              AND t.expires_at  > :now
              AND t.revoked_at IS NULL
              AND u.active = 1
            LIMIT 1
        ");
        $this->db->bind(':hash', $hash);
        $this->db->bind(':now',  $now);
        $this->db->cdp_execute();
        $row = $this->db->cdp_registro();

        if (!$row) {
            return null;
        }

        // Touch last_used_at
        $this->db->cdp_query("UPDATE cdb_api_tokens SET last_used_at = :now WHERE id = :id");
        $this->db->bind(':now', $now);
        $this->db->bind(':id',  (int)$row->token_id);
        $this->db->cdp_execute();

        return $row;
    }

    /**
     * Revoke a single token by raw value.
     */
    public function revokeToken(string $rawToken): bool
    {
        if (empty($rawToken)) {
            return false;
        }
        $hash = hash('sha256', $rawToken);
        $this->db->cdp_query("UPDATE cdb_api_tokens SET revoked_at = :now WHERE token_hash = :hash");
        $this->db->bind(':now',  date('Y-m-d H:i:s'));
        $this->db->bind(':hash', $hash);
        return (bool)$this->db->cdp_execute();
    }

    /**
     * Revoke all tokens for a user (e.g. on password change).
     */
    public function revokeAllForUser(int $userId): bool
    {
        $this->db->cdp_query("UPDATE cdb_api_tokens SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL");
        $this->db->bind(':now', date('Y-m-d H:i:s'));
        $this->db->bind(':uid', $userId);
        return (bool)$this->db->cdp_execute();
    }

    // ── Request helper ────────────────────────────────────────────────────────

    /**
     * Extract the Bearer token from the current HTTP request.
     * Returns empty string if absent or malformed.
     */
    public static function extractBearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (empty($header) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Authenticate the current request.
     *
     * On success: populates $_SESSION with user data (for downstream helpers)
     *             and returns the user row.
     * On failure: sends a 401 JSON response and exits.
     *
     * @return object cdb_users row (never returns on failure)
     */
    public static function requireAuth(): object
    {
        $rawToken = self::extractBearerToken();
        if (empty($rawToken)) {
            ApiResponse::unauthorized('No API token provided. Use Authorization: Bearer <token>');
        }

        $auth = new self();
        $user = $auth->validateToken($rawToken);

        if (!$user) {
            ApiResponse::unauthorized('Invalid or expired API token.');
        }

        // Populate session so existing helper functions (cdp_getAgencyContext, etc.) work.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['userid']    = (int)$user->id;
        $_SESSION['username']  = $user->username;
        $_SESSION['email']     = $user->email;
        $_SESSION['userlevel'] = $user->userlevel;
        $_SESSION['name_off']  = $user->name_off ?? '';

        // Minimise session-file locking for API requests.
        session_write_close();

        return $user;
    }

    /**
     * Require auth AND a specific permission.
     *
     * @param string|string[] $permission
     * @return object cdb_users row
     */
    public static function requirePermission($permission): object
    {
        $user = self::requireAuth();

        // Superadmin (level 9) always passes
        if ((int)$user->userlevel === 9) {
            return $user;
        }

        $db = new Conexion();
        $perms = (array)$permission;

        // Build placeholders
        $placeholders = [];
        foreach ($perms as $i => $_p) {
            $placeholders[] = ':p' . $i;
        }
        $inList = implode(', ', $placeholders);

        $db->cdp_query("
            SELECT COUNT(*) AS cnt
            FROM cdb_user_role_permissions rp
            JOIN cdb_user_roles r ON rp.role_id = r.role_id
            JOIN cdb_user_module_actions ma ON rp.module_action_id = ma.id
            WHERE rp.role_id = :role_id
              AND rp.permitted = 1
              AND r.rol_active = 1
              AND ma.action_name IN ($inList)
        ");
        $db->bind(':role_id', (int)$user->userlevel);
        foreach ($perms as $i => $p) {
            $db->bind(':p' . $i, $p);
        }
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row || (int)$row->cnt === 0) {
            ApiResponse::forbidden("Permission required: " . implode(' or ', $perms));
        }

        return $user;
    }

    // ── Internal maintenance ──────────────────────────────────────────────────

    private function pruneExpired(): void
    {
        $this->db->cdp_query("DELETE FROM cdb_api_tokens WHERE expires_at < :now");
        $this->db->bind(':now', date('Y-m-d H:i:s'));
        $this->db->cdp_execute();
    }

    private function pruneUserExcess(int $userId): void
    {
        $this->db->cdp_query("
            SELECT id FROM cdb_api_tokens
            WHERE user_id = :uid AND revoked_at IS NULL
            ORDER BY created_at ASC
        ");
        $this->db->bind(':uid', $userId);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros();

        if (count($rows) >= self::MAX_TOKENS_PER_USER) {
            $excess = array_slice($rows, 0, count($rows) - self::MAX_TOKENS_PER_USER + 1);
            foreach ($excess as $r) {
                $this->db->cdp_query("DELETE FROM cdb_api_tokens WHERE id = :id");
                $this->db->bind(':id', (int)$r->id);
                $this->db->cdp_execute();
            }
        }
    }
}
