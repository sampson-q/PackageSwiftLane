<?php
/**
 * AuthHandlerTest – tests for AuthHandler using a real (test) database.
 *
 * @group integration
 *
 * These tests require a MySQL / MariaDB instance to be available and the
 * test bootstrap to define valid CDP_DB_* constants. Skip by running:
 *
 *   phpunit --exclude-group integration
 */

use PHPUnit\Framework\TestCase;

class AuthHandlerTest extends TestCase
{
    private static bool $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        try {
            $pdo = new PDO(
                'mysql:host=' . CDP_DB_HOST . ';dbname=' . CDP_DB_NAME . ';charset=utf8mb4',
                CDP_DB_USER,
                CDP_DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
            );
            self::$dbAvailable = true;
        } catch (\PDOException $e) {
            self::$dbAvailable = false;
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function skipIfNoDb(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('MySQL test database not available (set CDP_DB_* env vars).');
        }
    }

    // ── ApiAuth::extractBearerToken ───────────────────────────────────────────

    public function testExtractBearerTokenReturnsEmptyWhenAbsent(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        $token = ApiAuth::extractBearerToken();
        $this->assertSame('', $token);
    }

    public function testExtractBearerTokenReturnsTokenFromHeader(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123def456';
        $token = ApiAuth::extractBearerToken();
        $this->assertSame('abc123def456', $token);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testExtractBearerTokenIgnoresNonBearerScheme(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
        $token = ApiAuth::extractBearerToken();
        $this->assertSame('', $token);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testExtractBearerTokenIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'BEARER mytoken123';
        $token = ApiAuth::extractBearerToken();
        $this->assertSame('mytoken123', $token);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    // ── ApiAuth token lifecycle (DB required) ────────────────────────────────

    public function testCreateAndValidateToken(): void
    {
        $this->skipIfNoDb();

        // Insert a temporary test user
        $pdo = new PDO(
            'mysql:host=' . CDP_DB_HOST . ';dbname=' . CDP_DB_NAME . ';charset=utf8mb4',
            CDP_DB_USER,
            CDP_DB_PASS
        );
        $pdo->exec("INSERT INTO cdb_users (username, email, password, userlevel, active, fname, lname, created)
            VALUES ('_api_test_user_', '_api_test_@example.com', '"
            . password_hash('test1234', PASSWORD_DEFAULT) . "', 9, 1, 'Test', 'User', NOW())
            ON DUPLICATE KEY UPDATE id = id");
        $stmt = $pdo->query("SELECT id FROM cdb_users WHERE username = '_api_test_user_' LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_OBJ);
        $userId = (int)$row->id;

        $auth   = new ApiAuth();
        $result = $auth->createToken($userId, 1, 'unit-test');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertNotEmpty($result['token']);

        // Validate
        $user = $auth->validateToken($result['token']);
        $this->assertNotNull($user);
        $this->assertSame($userId, (int)$user->user_id);

        // Revoke
        $auth->revokeToken($result['token']);
        $afterRevoke = $auth->validateToken($result['token']);
        $this->assertNull($afterRevoke);

        // Cleanup
        $pdo->exec("DELETE FROM cdb_users WHERE username = '_api_test_user_'");
    }

    public function testValidateTokenReturnsFalseForInvalidToken(): void
    {
        $this->skipIfNoDb();

        $auth = new ApiAuth();
        $result = $auth->validateToken('completely_invalid_token_that_does_not_exist');
        $this->assertNull($result);
    }

    // ── AuthHandler::formatUser ────────────────────────────────────────────────

    public function testFormatUserMapsAllFields(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/handlers/AuthHandler.php';

        $row            = new stdClass();
        $row->id        = 42;
        $row->username  = 'jdoe';
        $row->email     = 'jdoe@example.com';
        $row->fname     = 'John';
        $row->lname     = 'Doe';
        $row->phone     = '+1 555 0100';
        $row->userlevel = 9;
        $row->active    = 1;
        $row->locker    = 'L0042';
        $row->name_off  = 'HQ';
        $row->agency_id = null;
        $row->created   = '2024-01-01 00:00:00';

        $formatted = AuthHandler::formatUser($row);

        $this->assertSame(42, $formatted['id']);
        $this->assertSame('jdoe', $formatted['username']);
        $this->assertSame('John', $formatted['first_name']);
        $this->assertSame('Doe', $formatted['last_name']);
        $this->assertTrue($formatted['active']);
        $this->assertSame(9, $formatted['userlevel']);
        $this->assertNull($formatted['agency_id']);
    }
}
