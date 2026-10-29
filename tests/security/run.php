<?php
/** Run: php -d extension=pdo_sqlite tests/security/run.php. Uses disposable SQLite fixtures, never production. */
$root = dirname(__DIR__, 2);
require_once $root . '/helpers/security.php';
require_once $root . '/helpers/csrf.php';
require_once $root . '/helpers/autoload_lang.php';
require_once $root . '/lib/User.php';
require_once $root . '/api/v1/ApiValidator.php';
require_once $root . '/api/v1/ApiAccess.php';

ini_set('session.save_path', sys_get_temp_dir());
$checks = 0;
function verify($condition, string $label): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('Failed: ' . $label);
}

if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Install the pdo_sqlite test extension.');
class Conexion {
    public static $pdo;
    public $dbh;
    private $stmt;
    public function __construct() { $this->dbh = self::$pdo; }
    public function cdp_query($query) { $this->stmt = $this->dbh->prepare($query); }
    public function bind($key, $value) { $this->stmt->bindValue($key, $value); }
    public function cdp_execute() { return $this->stmt->execute(); }
    public function cdp_registro() { $this->stmt->execute(); return $this->stmt->fetch(PDO::FETCH_OBJ); }
    public function cdp_registros() { $this->stmt->execute(); return $this->stmt->fetchAll(PDO::FETCH_OBJ); }
    public function cdp_rowCount() { return $this->stmt->rowCount(); }
    public function cdp_cerrarConexion() {}
}
Conexion::$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Conexion::$pdo->exec('CREATE TABLE cdb_user_roles (role_id INT, rol_active INT, is_superadmin INT, is_admin INT, is_staff INT, is_client INT, is_driver INT, is_agency INT, parent_role_id INT)');
// A custom role inheriting the customer type must have the same object isolation as role 1.
Conexion::$pdo->exec('INSERT INTO cdb_user_roles VALUES (1,1,0,0,0,1,0,0,0), (21,1,0,0,0,0,0,0,1)');
Conexion::$pdo->exec('CREATE TABLE cdb_users (id INT, username TEXT, password TEXT, active INT, userlevel INT, agency_id INT, email TEXT, fname TEXT, lname TEXT, name_off TEXT, lastlogin TEXT, lastip TEXT, locker TEXT)');
$hash = password_hash('Fixture password only', PASSWORD_DEFAULT);
$insert = Conexion::$pdo->prepare('INSERT INTO cdb_users VALUES (?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([10, 'alice', $hash, 7, 'alice@example.invalid', 'Alice', 'One', 'Branch', '', '', 'A']);
$insert->execute([11, 'bob', $hash, 8, 'bob@example.invalid', 'Bob', 'Two', 'Branch', '', '', 'B']);
Conexion::$pdo->exec('CREATE TABLE cdb_recipients (id INT, sender_id INT, agency_id INT)');
Conexion::$pdo->exec('INSERT INTO cdb_recipients VALUES (100,10,7), (101,11,8)');
Conexion::$pdo->exec('CREATE TABLE cdb_add_order (order_id INT, sender_id INT, driver_id INT, agency INT)');
Conexion::$pdo->exec('INSERT INTO cdb_add_order VALUES (200,10,30,70), (201,11,31,80)');
Conexion::$pdo->exec('CREATE TABLE cdb_pre_alert (pre_alert_id INT, customer_id INT)');
Conexion::$pdo->exec('INSERT INTO cdb_pre_alert VALUES (300,10), (301,11)');
foreach (['cdb_customers_packages' => 'order_id', 'cdb_consolidate' => 'consolidate_id', 'cdb_consolidate_packages' => 'consolidate_id'] as $table => $column) {
    Conexion::$pdo->exec('CREATE TABLE ' . $table . ' (' . $column . ' INT, sender_id INT, driver_id INT, agency INT)');
    Conexion::$pdo->exec('INSERT INTO ' . $table . ' VALUES (400,10,30,70), (401,11,31,80)');
}
$context = ['is_restricted' => false, 'agency_id' => null];
function cdp_getAgencyContext() { global $context; return $context; }
function cdp_getAgencyBranchIdForUser($name) { return $name === 'Branch' ? 70 : 0; }

function visible(object $actor, string $resource, string $table): array {
    return Conexion::$pdo->query('SELECT * FROM ' . $table . ' WHERE 1=1' . ApiAccess::scope($actor, $resource))->fetchAll(PDO::FETCH_ASSOC);
}
$client = (object) ['id' => 10, 'userlevel' => 1, 'name_off' => 'Branch'];
$resources = ['customer' => 'cdb_users', 'recipient' => 'cdb_recipients', 'shipment' => 'cdb_add_order', 'prealert' => 'cdb_pre_alert', 'package' => 'cdb_customers_packages', 'consolidation' => 'cdb_consolidate', 'consolidated_package' => 'cdb_consolidate_packages'];
foreach ($resources as $resource => $table) verify(count(visible($client, $resource, $table)) === 1, 'client isolates ' . $resource);
$client->userlevel = 21;
foreach ($resources as $resource => $table) verify(count(visible($client, $resource, $table)) === 1, 'inherited client isolates ' . $resource);
$unknown = (object) ['id' => 10, 'userlevel' => 999, 'name_off' => 'Branch'];
foreach ($resources as $resource => $table) verify(visible($unknown, $resource, $table) === [], 'unknown role fails closed for ' . $resource);
$driver = (object) ['id' => 30, 'userlevel' => 3, 'name_off' => 'Branch'];
verify(count(visible($driver, 'shipment', 'cdb_add_order')) === 1, 'driver only assigned shipments');
$agency = (object) ['id' => 40, 'userlevel' => 6, 'name_off' => 'Branch'];
$context = ['is_restricted' => true, 'agency_id' => 7];
foreach ($resources as $resource => $table) verify(count(visible($agency, $resource, $table)) === 1, 'agency isolates ' . $resource);
$context['agency_id'] = null;
foreach ($resources as $resource => $table) verify(visible($agency, $resource, $table) === [], 'unassigned agency fails closed for ' . $resource);
$context['agency_id'] = 7; $agency->name_off = 'Missing';
verify(visible($agency, 'shipment', 'cdb_add_order') === [], 'unknown branch fails closed');
$context = ['is_restricted' => false, 'agency_id' => null];

cdp_securityStartSession();
verify(ini_get('session.use_strict_mode') === '1', 'strict session IDs');
verify(session_get_cookie_params()['httponly'] === true, 'HttpOnly cookies');
verify(session_get_cookie_params()['secure'] === true, 'Secure cookies are the default behind any proxy');
verify(session_get_cookie_params()['samesite'] === 'Lax', 'SameSite cookies');
$token = cdp_csrf_token();
verify(strlen($token) === 64, 'CSRF entropy');
$_POST = []; $_REQUEST = ['_csrf_token' => $token];
verify(!cdp_csrf_validate_request(), 'URL or cookie tokens do not satisfy CSRF');
$_POST['_csrf_token'] = 'wrong'; verify(!cdp_csrf_validate_request(), 'wrong CSRF rejected');
$_POST['_csrf_token'] = $token; verify(cdp_csrf_validate_request(), 'valid form CSRF accepted');
$_POST = []; $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
verify(cdp_csrf_validate_request(), 'valid header CSRF accepted');
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

$editor = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
$editor->logged_in = true; $editor->uid = 50; $editor->userlevel = 4;
$targetDriver = (object) ['id' => 30, 'userlevel' => 3, 'agency_id' => 7];
$targetAdmin = (object) ['id' => 60, 'userlevel' => 9, 'agency_id' => 7];
verify(cdp_canManageTarget($editor, $targetDriver, ['is_restricted' => true, 'agency_id' => 7], 'is_driver'), 'staff can manage assigned-tenant driver');
verify(!cdp_canManageTarget($editor, $targetAdmin, ['is_restricted' => false], 'is_driver'), 'driver editor cannot target an administrator');
verify(!cdp_canManageTarget($editor, $targetDriver, ['is_restricted' => true, 'agency_id' => 8], 'is_driver'), 'driver management cannot cross agencies');
verify(!cdp_canManageTarget($editor, $targetAdmin, ['is_restricted' => false]), 'generic editor cannot manage a higher-rank account');

// Verify session rotation and password-change revocation through the real User methods.
$user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(User::class, 'db'))->setValue($user, new Conexion());
$oldSession = session_id();
$_SESSION['imp_original_userid'] = 999;
$_SESSION['imp_original_credential_version'] = 'stale impersonation';
verify($user->cdp_finalizeLoginById(10), 'valid account can log in');
verify(session_id() !== $oldSession, 'login rotates session identifier');
verify(!isset($_SESSION['imp_original_userid']), 'new login cannot inherit stale impersonation authority');
verify(!isset($_SESSION['csrf_token']), 'login rotates CSRF token');
verify($user->cdp_loginCheck(), 'unchanged credential retains login');
Conexion::$pdo->exec("UPDATE cdb_users SET password='new fixture hash' WHERE id=10");
verify(!$user->cdp_loginCheck(), 'password change invalidates old browser session');
Conexion::$pdo->exec('UPDATE cdb_users SET active=0 WHERE id=11');
verify(!$user->cdp_finalizeLoginById(11), 'inactive account cannot finalize login');

verify(strpos(cdp_sanitize("‘ OR 1=1 --"), "'") === false, 'curly quote cannot reintroduce SQL delimiter');
verify(strpos(cdp_sanitize('“ onmouseover=alert(1)'), '"') === false, 'curly quote cannot reintroduce HTML delimiter');
verify(ApiValidator::validate(['code' => '000001'], ['code' => 'string|min:6|max:6']) === [], 'numeric text validates by string length');
verify(ApiValidator::validate(['email' => ['injected']], ['email' => 'email']) !== [], 'array input rejected');

$temp = tempnam(sys_get_temp_dir(), 'swiftlane-security-test-');
try {
    file_put_contents($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4mkAAAAASUVORK5CYII='));
    verify(cdp_securityUploadAllowed($temp, 'invoice.png'), 'real PNG accepted');
    foreach (['shell.php', 'shell.php.png', 'shell.phtml.png', '.htaccess', 'invoice.svg', "bad\n.png"] as $name) {
        verify(!cdp_securityUploadAllowed($temp, $name), 'unsafe upload name rejected: ' . json_encode($name));
    }
    verify(!cdp_moveUploadedFile($temp, $root . '/assets/uploads/forged.png'), 'local file cannot masquerade as HTTP upload');
    file_put_contents($temp, '<?php echo "payload";');
    verify(!cdp_securityUploadAllowed($temp, 'image.png'), 'MIME spoof rejected');
    file_put_contents($temp, '%PDF-1.4 fixture');
    verify(cdp_securityUploadAllowed($temp, 'invoice.pdf'), 'PDF accepted');
    verify(!cdp_securityUploadAllowed($temp, 'invoice.pdf', 4), 'actual file size limit enforced');
} finally { unlink($temp); }
$directory = sys_get_temp_dir() . '/swiftlane-confine-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$outside = tempnam(sys_get_temp_dir(), 'swiftlane-outside-');
file_put_contents($directory . '/backup.sql', 'fixture');
symlink($outside, $directory . '/escape.sql');
try {
    verify(cdp_securityConfinedFile($directory, 'backup.sql') === realpath($directory . '/backup.sql'), 'valid backup path resolves');
    verify(cdp_securityConfinedFile($directory, '../backup.sql') === null, 'backup traversal rejected');
    verify(cdp_securityConfinedFile($directory, 'escape.sql') === null, 'backup symlink escape rejected');
    verify(cdp_securityConfinedFile($directory, "bad\0.sql") === null, 'backup null byte rejected');
} finally { unlink($directory . '/escape.sql'); unlink($directory . '/backup.sql'); unlink($outside); rmdir($directory); }
$bucket = 'test:' . bin2hex(random_bytes(16));
verify(cdp_securityRateLimit($bucket, 2, 60), 'first limited request allowed');
verify(cdp_securityRateLimit($bucket, 2, 60), 'second limited request allowed');
verify(!cdp_securityRateLimit($bucket, 2, 60), 'excess limited request rejected');

// Both legacy entry paths resolve to the patched package and keep mail composition working.
require_once $root . '/helpers/phpmailer/class.phpmailer.php';
require_once $root . '/helpers/phpmailer/class.smtp.php';
$mail = new PHPMailer(true);
$mail->setFrom('sender@example.invalid', 'Sender');
$mail->addAddress('recipient@example.invalid');
$mail->Subject = 'Fixture'; $mail->Body = '<b>Mail fixture</b>'; $mail->isHTML(true);
verify($mail->preSend(), 'legacy mail facade composes mail');
verify(strpos($mail->getSentMIMEMessage(), 'Mail fixture') !== false, 'mail content retained');
verify(version_compare(PHPMailer::VERSION, '6.12.0', '>='), 'patched mail implementation loaded');
require_once $root . '/helpers/vendor/autoload.php';
verify(class_exists('ClickSend\\Api\\SMSApi'), 'SMS SDK legacy autoload works');
verify(class_exists('GuzzleHttp\\Client'), 'HTTP client legacy autoload works');
require_once $root . '/helpers/pdf.php';
$pdf = swiftlane_render_html_to_pdf('<h1>Security regression fixture</h1>');
verify(substr($pdf, 0, 5) === '%PDF-', 'PDF generation retained');
require_once $root . '/pdf/html2pdf.class.php';
verify(class_exists('HTML2PDF'), 'legacy PDF entrypoint retained');
// Preserve deferred-send snapshots and failure auditing while replacing the old transport.
$deferMail = false; $mailQueue = []; $mailFailures = 0; $sentBodies = [];
function cdp_notifyDeferred() { global $deferMail; return $deferMail; }
function cdp_afterResponse($callback) { global $mailQueue; $mailQueue[] = $callback; }
function cdp_msgMailFailed($mail, $reason) { global $mailFailures; $mailFailures++; }
class SecurityTransportFixture extends PHPMailer {
    public function postSend() { global $sentBodies; $sentBodies[] = $this->Body; return true; }
}
$queued = new SecurityTransportFixture(true);
$queued->setFrom('sender@example.invalid'); $queued->addAddress('recipient@example.invalid');
$queued->Subject = 'Deferred fixture'; $queued->Body = 'original snapshot';
$deferMail = true;
verify($queued->send(), 'deferred message accepted');
verify(count($sentBodies) === 0 && count($mailQueue) === 1, 'deferred send waits for response completion');
$queued->Body = 'later mutation'; $deferMail = false;
$mailQueue[0]();
verify($sentBodies === ['original snapshot'], 'deferred mail uses cloned message snapshot');
$failedMail = new PHPMailer(false);
verify(!$failedMail->send(), 'invalid mail reports failure');
verify($mailFailures === 1, 'failed mail is audited once');
session_write_close();
echo "Security regressions passed: {$checks} checks (SQLite fixtures; no production database).\n";
