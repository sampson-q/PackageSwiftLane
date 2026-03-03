    <?php
    ini_set('display_errors', 0);

    require_once("../loader.php");
    require_once("../lib/OtpService.php");

    $user = new User;
    $db = new Conexion;
    $otp = new OtpService;
    $errors = array();

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email)) $errors['email'] = 'Enter a valid email address';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'The email address you entered is invalid.';
    if (!$user->cdp_emailExists($email)) $errors['email'] = 'The email address you entered does not exist.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => implode(' ', $errors)]);
        exit;
    }

    $db->cdp_query("SELECT id,fname,lname,email FROM cdb_users WHERE email=:email LIMIT 1");
    $db->bind(':email', $email);
    $u = $db->cdp_registro();
    if (!$u) {
        echo json_encode(['success' => false, 'errors' => 'Unable to process request.']);
        exit;
    }

    $challenge = $otp->createChallenge((int)$u->id, 'forgot', ['email' => $u->email]);
    $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], 'password reset');
    $_SESSION['otp_forgot_challenge'] = $challenge['id'];

    echo json_encode([
        'success' => true,
        'messages' => 'OTP sent to your email. Verify it to continue password reset.',
        'redirect' => 'auth-otp.php?flow=forgot'
    ]);