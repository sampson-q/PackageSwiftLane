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
                $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $challenge['expires_at'], $flow);
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
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <!-- favicon -->
    <title>OTP Verification | <?php echo $core->site_name; ?></title>
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
        <a href="login.php" class="back-button btn btn-icon btn-primary"><i data-feather="arrow-left" class="icons"></i></a>
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
                                        <h4 class="card-title text-center">OTP Verification</h4>
                                        <p class="text-center">Enter the one-time password sent to your email.</p>

                                        <div id="msgholder2">
                                            <?php if ($message): ?>
                                                <div class="alert alert-success" id="success-alert">
                                                    <p><?php echo $message; ?></p>
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

                                        <form class="login-form mt-4" method="post">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">OTP Code <span class="text-danger">*</span></label>
                                                        <div class="form-icon position-relative">
                                                            <i data-feather="shield" class="fea icon-sm icons"></i>
                                                            <input type="text" name="otp_code" maxlength="6" class="form-control ps-5" placeholder="Enter 6-digit code">
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--end col-->

                                                <div class="col-lg-12 mb-0">
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn btn-grad">Verify</button>
                                                    </div>
                                                </div>
                                                <!--end col-->

                                                <div class="col-12 text-center">
                                                    <p class="mb-0 mt-3">
                                                        <small class="text-dark me-2">Didn't receive a code?</small>
                                                        <button type="submit" name="resend" value="1" class="btn btn-link p-0 text-dark fw-bold" style="vertical-align: baseline;">Resend code</button>
                                                    </p>
                                                </div>
                                                <!--end col-->
                                            </div>
                                            <!--end row-->
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <!--end col-->
                        </div>
                        <!--end row-->
                    </div> <!-- end about detail -->
                </div> <!-- end col -->

                <div class="col-lg-7 offset-lg-5 padding-less img order-1" style="background-image:url('assets/images/OneTimePassword.svg')" data-jarallax='{"speed": 0.5}'></div>
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