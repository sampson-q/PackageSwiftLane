<?php
require_once('loader.php');
require_once('lib/OtpService.php');

$core = new Core();
$db = new Conexion();
$otp = new OtpService();
$message = '';
$error = '';

if (empty($_SESSION['forgot_reset_token'])) {
    header('Location: forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $pass2 = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $userId = $otp->consumeResetSession($_SESSION['forgot_reset_token']);
        if (!$userId) {
            $error = 'Reset session expired. Please restart forgot password flow.';
        } else {
            $db->cdp_query("UPDATE cdb_users SET password=:password WHERE id=:id");
            $db->bind(':password', password_hash($pass, PASSWORD_DEFAULT));
            $db->bind(':id', (int)$userId);
            $db->cdp_execute();

            $db->cdp_query("SELECT email,fname,lname FROM cdb_users WHERE id=:id LIMIT 1");
            $db->bind(':id', (int)$userId);
            $u = $db->cdp_registro();
            if ($u) {
                $subject = 'Your password was changed';
                $body = "Hello {$u->fname} {$u->lname},<br>Your password has been changed successfully.";
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=utf-8\r\n";
                $headers .= "From: {$core->site_name} <{$core->site_email}>\r\n";
                @mail($u->email, $subject, $body, $headers);
            }

            unset($_SESSION['forgot_reset_token']);
            $message = 'Password updated successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset Password</title>
<link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-5"><div class="card"><div class="card-body">
<h4>Reset Password</h4>
<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?> <a href="login.php">Login</a></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<form method="post">
<div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirm" class="form-control" required></div>
<button type="submit" class="btn btn-primary">Update password</button>
</form>
</div></div></div></div></div></body></html>