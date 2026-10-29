<?php
/** OTP lifecycle checks with SQLite fixtures. FOR UPDATE is recorded, then stripped for SQLite. */
$root = dirname(__DIR__, 2);
define('CDP_DB_NAME', 'security_fixture');
define('CDP_DB_USER', 'fixture');
define('CDP_AUTH_SECRET', str_repeat('fixture-only-key-', 4));
require_once $root . '/lib/OtpService.php';
class OtpFixturePDO extends PDO {
    public $rollbacks = 0;
    public function rollBack(): bool { $this->rollbacks++; return parent::rollBack(); }
}
class Conexion {
    public $dbh;
    public $failStatusWrite = false;
    public $throwQuery = false;
    public $lockingReads = 0;
    private $stmt;
    private $query;
    public function __construct($pdo) { $this->dbh = $pdo; }
    public function cdp_query($query) {
        if ($this->throwQuery) throw new RuntimeException('Injected fixture failure');
        if (strpos($query, 'FOR UPDATE') !== false) $this->lockingReads++;
        $this->query = $query;
        $this->stmt = $this->dbh->prepare(str_replace(' FOR UPDATE', '', $query));
    }
    public function bind($key, $value) { $this->stmt->bindValue($key, $value); }
    public function cdp_execute() {
        if ($this->failStatusWrite && strpos($this->query, 'SET status=:status') !== false) { $this->failStatusWrite = false; return false; }
        return $this->stmt->execute();
    }
    public function cdp_registro() { $this->stmt->execute(); return $this->stmt->fetch(PDO::FETCH_OBJ); }
    public function cdp_rowCount() { return $this->stmt->rowCount(); }
}
$pdo = new OtpFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE cdb_auth_otp_challenges (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, purpose TEXT, channel TEXT, code_hash TEXT, code_salt TEXT, expires_at TEXT, max_attempts INT DEFAULT 5, attempts INT DEFAULT 0, status TEXT DEFAULT 'pending', metadata TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE cdb_password_reset_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, token_hash TEXT, expires_at TEXT, consumed_at TEXT, created_at TEXT)');
$db = new Conexion($pdo);
$otp = (new ReflectionClass(OtpService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(OtpService::class, 'db'))->setValue($otp, $db);
$checks = 0;
function checkOtp($condition, $label) { global $checks; $checks++; if (!$condition) throw new RuntimeException($label); }
$challenge = $otp->createChallenge(1, 'api_login');
$info = $otp->getChallengeInfo($challenge['id']);
checkOtp((int)$info->user_id === 1 && $info->purpose === 'api_login', 'challenge identity bound to API login purpose');
checkOtp(!empty($otp->verifyChallenge($challenge['id'], $challenge['code'], 'api_login')['ok']), 'correct OTP succeeds');
checkOtp(empty($otp->verifyChallenge($challenge['id'], $challenge['code'], 'api_login')['ok']), 'OTP cannot be replayed');
$wrong = $otp->createChallenge(2, 'login');
for ($i=0; $i<5; $i++) checkOtp(empty($otp->verifyChallenge($wrong['id'], '000000', 'login')['ok']), 'wrong OTP rejected');
checkOtp(empty($otp->verifyChallenge($wrong['id'], $wrong['code'], 'login')['ok']), 'exhausted OTP cannot be used');
checkOtp($otp->getChallengeInfo($wrong['id'])->status === 'locked', 'five attempts lock the challenge');
$expired = $otp->createChallenge(3, 'login', [], -1);
checkOtp(empty($otp->verifyChallenge($expired['id'], $expired['code'], 'login')['ok']), 'expired OTP rejected');
$failure = $otp->createChallenge(4, 'login');
$db->failStatusWrite = true;
checkOtp(empty($otp->verifyChallenge($failure['id'], $failure['code'], 'login')['ok']), 'failed consume write cannot authenticate');
checkOtp(empty($otp->verifyChallenge($failure['id'], $failure['code'], 'forgot')['ok']), 'challenge cannot cross purposes');
$reset = $otp->createResetSession(10);
checkOtp($otp->consumeResetSession($reset) === 10, 'valid reset consumed');
checkOtp($otp->consumeResetSession($reset) === false, 'reset token cannot be replayed');
checkOtp($otp->consumeResetSession('invalid') === false, 'invalid reset rejected');
$old = $otp->createResetSession(11, -1);
checkOtp($otp->consumeResetSession($old) === false, 'expired reset rejected');
checkOtp($db->lockingReads >= 10, 'lifecycle reads request row locks on MySQL');
$db->throwQuery = true;
try { $otp->consumeResetSession($reset); throw new RuntimeException('Expected injected failure'); }
catch (RuntimeException $error) { checkOtp($error->getMessage() === 'Injected fixture failure', 'database failure propagated'); }
checkOtp(!$pdo->inTransaction() && $pdo->rollbacks === 1, 'database exception rolls back transaction');
checkOtp(strpos(cdp_msgRedact('Code 123456', 'otp'), '123456') === false, 'OTP is not retained in message content');
checkOtp(strpos(cdp_msgRedact('Password: FixtureSecret', 'user_account'), 'FixtureSecret') === false, 'account credentials are not retained in message content');
checkOtp(strpos(cdp_msgRedact('{"access_token":"FixtureToken"}', 'api'), 'FixtureToken') === false, 'provider access tokens are redacted');
echo "OTP security regressions passed: {$checks} checks (SQLite lifecycle fixtures; MySQL concurrency still requires staging).\n";
