<?php
// Test router only. It must never run as a deployed application endpoint.
if (PHP_SAPI !== 'cli-server' || getenv('SWIFTLANE_SECURITY_FIXTURE') !== '1') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('CDP_SESSION_COOKIE_SECURE', false); // This disposable fixture listens on local HTTP only.
require_once $root . '/helpers/security.php';
require_once $root . '/helpers/csrf.php';
ini_set('session.save_path', sys_get_temp_dir());
cdp_securityStartSession();
header('Content-Type: application/json');

class User {
    public $logged_in = true;
    public $uid = 10;
    public $userlevel = 1;
    public function cdp_getUserPermissions() { return []; }
    public function cdp_hasPermission($permissions) { return false; }
}
class Conexion {
    private $stmt;
    public $dbh;
    public function __construct() {
        $this->dbh = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->dbh->exec('CREATE TABLE cdb_api_tokens (id INT, user_id INT, token_hash TEXT, expires_at TEXT, revoked_at TEXT, last_used_at TEXT)');
        $this->dbh->exec('CREATE TABLE cdb_users (id INT, username TEXT, email TEXT, fname TEXT, lname TEXT, userlevel INT, active INT, name_off TEXT, agency_id INT)');
        $this->dbh->exec("INSERT INTO cdb_users VALUES (10,'fixture','fixture@example.invalid','Fixture','User',1,1,'Branch',7)");
        $stmt = $this->dbh->prepare('INSERT INTO cdb_api_tokens VALUES (1,10,?,?,NULL,NULL)');
        $stmt->execute([hash('sha256', str_repeat('a', 64)), '2099-01-01 00:00:00']);
    }
    public function cdp_query($query) { $this->stmt = $this->dbh->prepare(stripos(ltrim($query), 'CREATE TABLE') === 0 ? 'SELECT 1' : $query); }
    public function bind($key, $value) { $this->stmt->bindValue($key, $value); }
    public function cdp_execute() { return $this->stmt->execute(); }
    public function cdp_registro() { $this->stmt->execute(); return $this->stmt->fetch(PDO::FETCH_OBJ); }
    public function cdp_registros() { $this->stmt->execute(); return $this->stmt->fetchAll(PDO::FETCH_OBJ); }
}
function cdp_getSettingsCourier() {}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/seed') {
    $_SESSION = ['browser_marker' => 'guest'];
    if (isset($_GET['privileged'])) $_SESSION['userid'] = 999;
    echo json_encode(['csrf' => cdp_csrf_token()]);
} elseif ($path === '/session') {
    echo json_encode(['userid' => $_SESSION['userid'] ?? null, 'marker' => $_SESSION['browser_marker'] ?? null]);
} elseif ($path === '/auth') {
    require_once $root . '/api/v1/ApiResponse.php';
    require_once $root . '/api/v1/ApiAuth.php';
    $user = ApiAuth::requireAuth();
    echo json_encode(['userid' => (int) $user->id]);
} elseif ($path === '/guard' || $path === '/delete') {
    define('CDP_RBAC_MODE', $_GET['mode'] ?? 'audit');
    require_once $root . '/helpers/ajax_guard.php';
    if ($path === '/delete') { require_post(); require_login(); }
    else { require_permission('fixture_denied'); }
    echo json_encode(['allowed' => true]);
} elseif ($path === '/upload') {
    if (!cdp_csrf_validate_request()) { http_response_code(403); echo '{}'; exit; }
    $dir = rtrim(getenv('SWIFTLANE_SECURITY_FIXTURE_DIR'), '/') . '/';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $destination = ($_POST['outside'] ?? '') === '1' ? sys_get_temp_dir() . '/fixture-upload.png' : $dir . basename($_POST['name']);
    $ok = cdp_moveUploadedFile($_FILES['file']['tmp_name'] ?? '', $destination);
    echo json_encode(['stored' => $ok]);
} elseif ($path === '/redirect') {
    require_once $root . '/helpers/autoload_lang.php';
    cdp_redirect_to($_GET['target'] ?? '');
} elseif ($path === '/cors') {
    define('CDP_DB_HOST', 'fixture');
    if (isset($_GET['explicit'])) define('CDP_API_ALLOWED_ORIGINS', 'https://allowed.example.invalid');
    require_once $root . '/api/v1/bootstrap.php';
    echo '{}';
} else { http_response_code(404); echo '{}'; }
