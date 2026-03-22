<?php
require_once("loader.php");
require_once("helpers/querys.php");
require_once("lib/OtpService.php");

$user = new User();
$core = new Core();
$db = new Conexion();
$otp = new OtpService();
$flow = isset($_GET['flow']) ? $_GET['flow'] : 'login';
$message = '';
$error = '';
$otpSuccess  = false;  // triggers the Swal on the page
$otpRedirect = 'index.php';

$sessionKey  = 'otp_' . $flow . '_challenge';
$challengeId = isset($_SESSION[$sessionKey]) ? (int)$_SESSION[$sessionKey] : 0;

/**
 * Move a file from the temp folder to the real uploads folder.
 * Returns the final relative path, or '' if no temp file was set.
 */
function cdp_commitUpload($tempDir, $tempName, $uploadDir, $prefix) {
    if (empty($tempName) || !file_exists($tempDir . $tempName)) {
        return '';
    }
    $ext       = pathinfo($tempName, PATHINFO_EXTENSION);
    $finalName = $prefix . '_' . uniqid() . '.' . $ext;
    rename($tempDir . $tempName, $uploadDir . $finalName);
    return $uploadDir . $finalName;
}

/**
 * Delete the temp folder and everything in it.
 */
function cdp_cleanupTempDir($tempDir) {
    if (!is_dir($tempDir)) return;
    foreach (glob($tempDir . '*') as $f) {
        unlink($f);
    }
    rmdir($tempDir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $uid = 0;
        if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
            $uid = (int)$_SESSION['otp_login_user_id'];
        } elseif ($flow === 'forgot' && !empty($_SESSION['otp_forgot_user_id'])) {
            $uid = (int)$_SESSION['otp_forgot_user_id'];
        } elseif ($flow === 'signup' && !empty($_SESSION['pending_signup'])) {
            $pending   = $_SESSION['pending_signup'];
            $challenge = $otp->createChallenge(0, 'signup', ['email' => $pending['email']]);
            $_SESSION[$sessionKey] = $challenge['id'];
            $challengeId = $challenge['id'];
            $otp->sendOtpEmail(
                $pending['email'],
                $pending['fname'] . ' ' . $pending['lname'],
                $challenge['code'],
                $challenge['expires_at'],
                'signup'
            );
            $message = 'A new OTP has been sent.';
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
                $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $challenge['expires_at'], $flow === 'forgot' ? 'password reset' : $flow);
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
                $otpSuccess  = true;
                $otpRedirect = 'index.php';
            }

            elseif ($flow === 'signup') {
                $pending = isset($_SESSION['pending_signup']) ? $_SESSION['pending_signup'] : null;

                if (!$pending) {
                    $error = 'Session expired. Please register again.';
                } else {
                    $tempDir   = 'assets/uploads/tmp/' . $pending['temp_token'] . '/';
                    $uploadDir = 'assets/uploads/users/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $avatarPath        = cdp_commitUpload($tempDir, $pending['avatar_tmp'],          $uploadDir, 'avatar');
                    $documentPhotoPath = cdp_commitUpload($tempDir, $pending['document_photo_tmp'],  $uploadDir, 'docphoto');
                    cdp_cleanupTempDir($tempDir);

                    $db->cdp_query('INSERT INTO cdb_users (username,password,locker,userlevel,email,fname,lname,document_number,document_type,created,phone,active,terms,avatar,document_photo)
                        VALUES (:username,:password,:locker,:userlevel,:email,:fname,:lname,:document_number,:document_type,:created,:phone,:active,:terms,:avatar,:document_photo)');

                    $db->bind(':username',        $pending['username']);
                    $db->bind(':password',        $pending['password']);
                    $db->bind(':locker',          $pending['locker']);
                    $db->bind(':userlevel',       1);
                    $db->bind(':email',           $pending['email']);
                    $db->bind(':fname',           $pending['fname']);
                    $db->bind(':lname',           $pending['lname']);
                    $db->bind(':document_number', $pending['document_number']);
                    $db->bind(':document_type',   $pending['document_type']);
                    $db->bind(':created',         $pending['created']);
                    $db->bind(':phone',           $pending['phone']);
                    $db->bind(':active',          1);
                    $db->bind(':terms',           $pending['terms']);
                    $db->bind(':avatar',          '../' . $avatarPath);
                    $db->bind(':document_photo',  '../' . $documentPhotoPath);

                    $db->cdp_execute();
                    $user_created_id = $db->dbh->lastInsertId();

                    if ($user_created_id) {
                        cdp_insertAddressCustomer([
                            'user_id' => $user_created_id,
                            'address' => $pending['address'],
                            'country' => $pending['country'],
                            'city'    => $pending['city'],
                            'state'   => $pending['state'],
                            'postal'  => $pending['postal'],
                        ]);

                        $db->cdp_query("INSERT INTO cdb_user_details_update_check (user_id, update_address, update_document) VALUES (:user_id, 1, 1)");
                        $db->bind(':user_id', $user_created_id);
                        $db->cdp_execute();

                        unset($_SESSION['otp_signup_challenge'], $_SESSION['pending_signup']);
                        $otpSuccess  = true;
                        $otpRedirect = 'index.php';
                    } else {
                        $error = 'An error occurred while creating your account. Please contact the administrator.';
                    }
                }
            }

            elseif ($flow === 'forgot') {
                $token = $otp->createResetSession($verify['user_id'], 900);
                $_SESSION['forgot_reset_token'] = $token;
                unset($_SESSION['otp_forgot_challenge']);
                $otpSuccess  = true;
                $otpRedirect = 'forgot-reset.php';
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
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
</head>

<body>

    <div id="preloader">
        <div id="status">
            <div class="spinner">
                <div class="double-bounce1"></div>
                <div class="double-bounce2"></div>
            </div>
        </div>
    </div>

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
                                                <div class="alert alert-success">
                                                    <p><?php echo $message; ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($error): ?>
                                                <div class="alert alert-danger">
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

                                                <div class="col-lg-12 mb-0">
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn btn-grad">Verify</button>
                                                    </div>
                                                </div>

                                                <div class="col-12 text-center">
                                                    <p class="mb-0 mt-3">
                                                        <small class="text-dark me-2">Didn't receive a code?</small>
                                                        <button type="submit" name="resend" value="1" class="btn btn-link p-0 text-dark fw-bold" style="vertical-align: baseline;">Resend code</button>
                                                    </p>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 offset-lg-5 padding-less img order-1" style="background-image:url('assets/images/OneTimePassword.svg')" data-jarallax='{"speed": 0.5}'></div>
            </div>
        </div>
    </section>

    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>

    <?php if ($otpSuccess): ?>
    <script>
        Swal.fire({
            title: 'Verified!',
            text: 'Your identity has been confirmed successfully.',
            icon: 'success',
            allowOutsideClick: false,
            confirmButtonText: 'Continue',
            confirmButtonColor: '#336aea',
        }).then(function () {
            window.location.href = '<?php echo $otpRedirect; ?>';
        });
    </script>
    <?php endif; ?>

</body>
</html>