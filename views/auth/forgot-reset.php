<?php
session_start();
require_once("lib/OtpService.php");

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
    $pass  = isset($_POST['password'])         ? $_POST['password']         : '';
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

            unset($_SESSION['forgot_reset_token']);
            $message = 'Password updated successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <!-- favicon -->
    <title>Reset Password | <?php echo $core->site_name; ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">

    <!-- Bootstrap -->
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v3.0.6/css/line.css">
    <!-- Main Css -->
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">
</head>

<body>

    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner">
                <div class="double-bounce1"></div>
                <div class="double-bounce2"></div>
            </div>
        </div>
    </div>
    <!-- Loader -->

    <div class="back-to-home">
        <a href="forgot-password.php" class="back-button btn btn-icon btn-primary"><i data-feather="arrow-left" class="icons"></i></a>
    </div>

    <!-- Hero Start -->
    <section class="cover-user bg-white">
        <div class="container-fluid px-0">
            <div class="row g-0 position-relative">
                <div class="col-lg-5 cover-my-30 order-2">
                    <div class="cover-user-img d-flex align-items-center">
                        <div class="row">
                            <div class="col-12">
                                <div class="card login-page border-0" style="z-index: 1">
                                    <div class="card-title text-center">
                                        <a class="logo" href="index.php">
                                            <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>
                                        </a>
                                    </div>
                                    <div><br></div>
                                    <div class="card-body p-0">
                                        <h4 class="card-title text-center">Set New Password</h4>
                                        <p class="text-center">Choose a strong password for your account.</p>

                                        <div id="msgholder2">
                                            <?php if ($message): ?>
                                                <div class="alert alert-success" id="success-alert">
                                                    <p><?php echo $message; ?> <a href="login.php" class="fw-bold">Sign in</a></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($error): ?>
                                                <div class="alert alert-danger" id="success-alert">
                                                    <p><span class="icon-minus-sign"></span>
                                                        <i class="close icon-remove-circle"></i>
                                                        <span>Error!</span> <?php echo $error; ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div id="loader" style="display:none"></div>

                                        <?php if (!$message): ?>
                                        <form class="login-form mt-4" method="post">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                                        <div class="form-icon position-relative">
                                                            <i data-feather="lock" class="fea icon-sm icons"></i>
                                                            <input type="password" name="password" class="form-control ps-5" placeholder="Enter new password" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--end col-->

                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                                        <div class="form-icon position-relative">
                                                            <i data-feather="lock" class="fea icon-sm icons"></i>
                                                            <input type="password" name="password_confirm" class="form-control ps-5" placeholder="Confirm new password" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--end col-->

                                                <div class="col-lg-12 mb-0">
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn btn-grad">Update Password</button>
                                                    </div>
                                                </div>
                                                <!--end col-->

                                                <div class="col-12 text-center">
                                                    <p class="mb-0 mt-3">
                                                        <small class="text-dark me-2">Remember your password?</small>
                                                        <a href="login.php" class="text-dark fw-bold">Sign in</a>
                                                    </p>
                                                </div>
                                                <!--end col-->
                                            </div>
                                            <!--end row-->
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <!--end col-->
                        </div>
                        <!--end row-->
                    </div> <!-- end about detail -->
                </div> <!-- end col -->

                <div class="col-lg-7 offset-lg-5 padding-less img order-1" style="background-image:url('assets\\images\\ForgotPassword.svg')" data-jarallax='{"speed": 0.5}'></div>
                <!-- end col -->
            </div>
            <!--end row-->
        </div>
        <!--end container fluid-->
    </section>
    <!--end section-->
    <!-- Hero End -->

    <!-- javascript -->
    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <!-- Icons -->
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <!-- Main Js -->
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <script src="assets/css_main_deprixa/js/app.js"></script>

</body>
</html>