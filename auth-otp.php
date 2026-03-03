<?php
require_once("loader.php");
require_once("lib/OtpService.php");

$user = new User();
$core = new Core();
$db = new Conexion();
$otp = new OtpService();
$flow = isset($_GET['flow']) ? $_GET['flow'] : 'login';
$message = '';
$error = '';

$sessionKey = 'otp_' . $flow . '_challenge';
$challengeId = isset($_SESSION[$sessionKey]) ? (int)$_SESSION[$sessionKey] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $uid = 0;
        if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
            $uid = (int)$_SESSION['otp_login_user_id'];
        } elseif ($challengeId > 0) {
            $db->cdp_query("SELECT user_id FROM cdb_auth_otp_challenges WHERE id=:id LIMIT 1");
            $db->bind(':id', $challengeId);
            $ch = $db->cdp_registro();
            if ($ch) {
                $uid = (int)$ch->user_id;
            }
        }

        if ($uid > 0) {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id=:id LIMIT 1");
            $db->bind(':id', $uid);
            $u = $db->cdp_registro();
            if ($u) {
                $challenge = $otp->createChallenge($u->id, $flow, ['remember_me' => !empty($_SESSION['otp_login_remember'])]);
                $_SESSION[$sessionKey] = $challenge['id'];
                $challengeId = $challenge['id'];
                $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $flow);
                $message = 'A new OTP has been sent.';
            }
        }
    } elseif (isset($_POST['otp_code'])) {
        $verify = $otp->verifyChallenge($challengeId, $_POST['otp_code'], $flow);
        if ($verify['ok']) {
            if ($flow === 'login') {
                $user->cdp_finalizeLoginById($verify['user_id']);
                if (!empty($_SESSION['otp_login_remember'])) {
                    $otp->rememberTrustedDevice($verify['user_id']);
                }
                unset($_SESSION['otp_login_challenge'], $_SESSION['otp_login_remember'], $_SESSION['otp_login_user_id']);
                header('Location: index.php');
                exit;
            }

            if ($flow === 'signup') {
                $db->cdp_query("UPDATE cdb_users SET active=1 WHERE id=:id");
                $db->bind(':id', (int)$verify['user_id']);
                $db->cdp_execute();
                $user->cdp_finalizeLoginById($verify['user_id']);
                unset($_SESSION['otp_signup_challenge']);
                header('Location: index.php');
                exit;
            }

            if ($flow === 'forgot') {
                $token = $otp->createResetSession($verify['user_id'], 900);
                $_SESSION['forgot_reset_token'] = $token;
                unset($_SESSION['otp_forgot_challenge']);
                header('Location: forgot-reset.php');
                exit;
            }
        } else {
            $error = $verify['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | <?php echo $core->site_name; ?></title>
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Verify OTP</h4>
                    <p class="text-muted">Enter the one-time password sent to your email.</p>
                    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">OTP Code</label>
                            <input type="text" name="otp_code" maxlength="6" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Verify</button>
                        <button type="submit" name="resend" value="1" class="btn btn-link">Resend code</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>