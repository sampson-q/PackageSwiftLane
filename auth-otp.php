<?php
require_once("loader.php");
require_once("helpers/querys.php");
require_once("lib/OtpService.php");

$user = new User();
$core = new Core();
$db   = new Conexion();
$otp  = new OtpService();

$flow = isset($_GET['flow']) ? $_GET['flow'] : 'login';

$message     = '';
$error       = '';
$otpSuccess  = false;
$otpRedirect = 'index.php';

$sessionKey  = 'otp_' . $flow . '_challenge';
$challengeId = isset($_SESSION[$sessionKey]) ? (int) $_SESSION[$sessionKey] : 0;

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

    // ── Resend ────────────────────────────────────────────────────────────────
    if (isset($_POST['resend'])) {
        $uid = 0;

        if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
            $uid = (int) $_SESSION['otp_login_user_id'];

        } elseif ($flow === 'forgot' && !empty($_SESSION['otp_forgot_user_id'])) {
            $uid = (int) $_SESSION['otp_forgot_user_id'];

        } elseif ($flow === 'signup' && !empty($_SESSION['pending_signup'])) {
            $pending   = $_SESSION['pending_signup'];
            $challenge = $otp->createChallenge(0, 'signup', ['email' => $pending['email']]);
            $_SESSION[$sessionKey] = $challenge['id'];
            $challengeId = $challenge['id'];
            $otp->sendOtpEmail(
                $pending['email'],
                $pending['fname'] . ' ' . $pending['lname'],
                $challenge['code'],
                'signup'
            );
            $message = 'A new OTP has been sent.';

        } elseif ($challengeId > 0) {
            $db->cdp_query("SELECT user_id FROM cdb_auth_otp_challenges WHERE id=:id LIMIT 1");
            $db->bind(':id', $challengeId);
            $ch = $db->cdp_registro();
            if ($ch) {
                $uid = (int) $ch->user_id;
            }
        }

        if ($uid > 0) {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id=:id LIMIT 1");
            $db->bind(':id', $uid);
            $u = $db->cdp_registro();
            if ($u) {
                $challenge = $otp->createChallenge($u->id, $flow, [
                    'remember_me' => !empty($_SESSION['otp_login_remember']),
                ]);
                $_SESSION[$sessionKey] = $challenge['id'];
                $challengeId = $challenge['id'];

                $purpose = ($flow === 'forgot') ? 'password reset' : $flow;
                $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $purpose);
                $otp->sendOtpWhatsApp($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $purpose);

                $message = 'A new OTP has been sent to your email and WhatsApp.';
            }
        }

    // ── Verify ────────────────────────────────────────────────────────────────
    } elseif (isset($_POST['otp_code'])) {
        $verify = $otp->verifyChallenge($challengeId, $_POST['otp_code'], $flow);

        if ($verify['ok']) {

            // ── Login flow ────────────────────────────────────────────────────
            if ($flow === 'login') {
                $user->cdp_finalizeLoginById($verify['user_id']);

                if (!empty($_SESSION['otp_login_remember'])) {
                    $otp->rememberTrustedDevice($verify['user_id']);
                }

                unset(
                    $_SESSION['otp_login_challenge'],
                    $_SESSION['otp_login_remember'],
                    $_SESSION['otp_login_user_id']
                );

                $otpSuccess  = true;
                $otpRedirect = 'index.php';

            // ── Sign-up flow ──────────────────────────────────────────────────
            } elseif ($flow === 'signup') {
                $pending = isset($_SESSION['pending_signup']) ? $_SESSION['pending_signup'] : null;

                if (!$pending) {
                    $error = 'Session expired. Please register again.';
                } else {
                    $tempDir   = 'assets/uploads/tmp/' . $pending['temp_token'] . '/';
                    $uploadDir = 'assets/uploads/users/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $avatarPath        = cdp_commitUpload($tempDir, $pending['avatar_tmp'],         $uploadDir, 'avatar');
                    $documentPhotoPath = cdp_commitUpload($tempDir, $pending['document_photo_tmp'], $uploadDir, 'docphoto');
                    cdp_cleanupTempDir($tempDir);

                    $db->cdp_query('INSERT INTO cdb_users
                        (username, password, locker, userlevel, email, fname, lname,
                         document_number, document_type, created, phone, active, terms, avatar, document_photo)
                        VALUES
                        (:username, :password, :locker, :userlevel, :email, :fname, :lname,
                         :document_number, :document_type, :created, :phone, :active, :terms, :avatar, :document_photo)');

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

                        $db->cdp_query("INSERT INTO cdb_user_details_update_check
                            (user_id, update_address, update_document)
                            VALUES (:user_id, 1, 1)");
                        $db->bind(':user_id', $user_created_id);
                        $db->cdp_execute();

                        unset($_SESSION['otp_signup_challenge'], $_SESSION['pending_signup']);
                        $otpSuccess  = true;
                        $otpRedirect = 'index.php';
                    } else {
                        $error = 'An error occurred while creating your account. Please contact the administrator.';
                    }
                }

            // ── Forgot-password flow ──────────────────────────────────────────
            } elseif ($flow === 'forgot') {
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
    <link href="assets/css_main_deprixa/css/auth-pages.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <style>
        .otp-box {
            width: 48px;
            height: 56px;
            padding: 0;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .otp-box:focus {
            border-color: #336aea;
            box-shadow: 0 0 0 3px rgba(51, 106, 234, 0.15);
            outline: none;
        }
        .otp-box.filled {
            border-color: #336aea;
        }
    </style>
</head>

<body class="auth-page">

    <div id="preloader">
        <div id="status">
            <div class="spinner">
                <div class="double-bounce1"></div>
                <div class="double-bounce2"></div>
            </div>
        </div>
    </div>

    <div class="back-to-home">
        <a href="login.php" class="back-button btn btn-icon btn-primary" aria-label="Back to login"><i data-feather="arrow-left" class="icons"></i></a>
    </div>

    <section class="auth-shell">
        <div class="container-fluid px-0">
            <div class="row g-0 auth-shell__grid">
                <div class="col-lg-6 auth-shell__panel auth-shell__panel--visual order-2 order-lg-1" style="--auth-visual-image: url('assets/images/OT(1)P.svg');">
                    <div class="auth-visual"></div>
                </div>

                <div class="col-lg-6 auth-shell__panel auth-shell__panel--form order-1 order-lg-2">
                    <div class="auth-card auth-card--compact card login-page border-0">
                        <div class="auth-card__top text-center">
                            <a class="logo" href="index.php">
                                <?php echo ($core->logo_web)
                                    ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>'
                                    : $core->site_name; ?>
                            </a>
                        </div>

                        <div class="card-body">
                            <div class="text-center">
                                <h4 class="auth-heading mb-2">OTP Verification</h4>
                                <p class="auth-subtitle">Enter the code sent to your email or WhatsApp.</p>
                            </div>

                            <div id="msgholder2" class="mt-4">
                                <?php if ($message): ?>
                                    <div class="alert alert-success">
                                        <p class="mb-0"><?php echo $message; ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger">
                                        <p class="mb-0">
                                            <span class="icon-minus-sign"></span>
                                            <i class="close icon-remove-circle"></i>
                                            <span>Error!</span> <?php echo $error; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div id="loader" style="display:none"></div>

                            <form class="login-form mt-4" method="post" id="otp-form">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-center gap-2 flex-wrap" id="otp_boxes">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                                <input type="text" maxlength="1" class="otp-box form-control fw-bold fs-4" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                                            </div>
                                            <input type="hidden" name="otp_code" id="otp_code_hidden">
                                        </div>
                                    </div>

                                    <div class="col-12 mt-2">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-grad">Verify</button>
                                        </div>
                                    </div>

                                    <div class="col-12 text-center">
                                        <p class="mb-0 mt-3">
                                            <button type="submit" name="resend" value="1" class="btn btn-link p-0 text-dark fw-bold align-baseline">Resend code</button>
                                        </p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>

    <script>
    (function () {
        /**
         * Wire up a group of single-digit boxes so they behave as one OTP input.
         *
         * @param {string} containerId  - id of the wrapper element (WITHOUT #)
         * @param {string} hiddenId     - id of the hidden <input> to sync into (WITHOUT #)
         */
        function initOtpBoxes(containerId, hiddenId) {
            var $boxes  = $('#' + containerId + ' .otp-box');
            var $hidden = $('#' + hiddenId);

            function syncHidden() {
                $hidden.val(
                    $boxes.map(function () { return $(this).val(); }).get().join('')
                );
            }

            $boxes.on('keydown', function (e) {
                var $this = $(this);
                var idx   = $boxes.index($this);

                if (e.key === 'Backspace') {
                    if ($this.val() !== '') {
                        $this.val('').removeClass('filled');
                    } else if (idx > 0) {
                        $boxes.eq(idx - 1).val('').removeClass('filled').focus();
                    }
                    syncHidden();
                    e.preventDefault();
                    return;
                }
                if (e.key === 'ArrowLeft'  && idx > 0)                { $boxes.eq(idx - 1).focus(); }
                if (e.key === 'ArrowRight' && idx < $boxes.length - 1){ $boxes.eq(idx + 1).focus(); }
            });

            $boxes.on('input', function () {
                var $this = $(this);
                var val   = $this.val().replace(/\D/g, '').slice(-1);
                $this.val(val);
                val ? $this.addClass('filled') : $this.removeClass('filled');
                syncHidden();
                if (val && $boxes.index($this) < $boxes.length - 1) {
                    $boxes.eq($boxes.index($this) + 1).focus();
                }
            });

            $boxes.on('paste', function (e) {
                var pasted = (e.originalEvent.clipboardData || window.clipboardData)
                    .getData('text').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                e.preventDefault();
                $boxes.each(function (i) {
                    var ch = pasted[i] || '';
                    $(this).val(ch);
                    ch ? $(this).addClass('filled') : $(this).removeClass('filled');
                });
                syncHidden();
                var $nextEmpty = $boxes.filter(function () { return !$(this).val(); }).first();
                ($nextEmpty.length ? $nextEmpty : $boxes.last()).focus();
            });
        }

        // ── Modal OTP boxes (used elsewhere in the app) ────────────────────────
        if ($('#force_otp_boxes').length) {
            initOtpBoxes('force_otp_boxes', 'force_phone_otp_code');
            $('#userUpdatePhoneOtp').on('show.bs.modal', function () {
                $('#force_otp_boxes .otp-box').val('').removeClass('filled');
                $('#force_phone_otp_code').val('');
                setTimeout(function () { $('#force_otp_boxes .otp-box').first().focus(); }, 300);
            });
        }

        // ── Standalone OTP page ────────────────────────────────────────────────
        if ($('#otp_boxes').length) {
            /*
             * FIX: the hidden input now has id="otp_code_hidden" so we pass a plain
             * ID here instead of the attribute-selector string that was used before.
             * The old code passed "input[name='otp_code']" as the second argument but
             * initOtpBoxes prepends '#' to it — meaning jQuery looked for
             * #input[name='otp_code'] which never matches anything, so syncHidden()
             * silently wrote to nothing and the field was always empty on submit.
             */
            initOtpBoxes('otp_boxes', 'otp_code_hidden');

            // Belt-and-suspenders: also sync immediately before the form is submitted
            $('#otp-form').on('submit', function () {
                var val = $('#otp_boxes .otp-box').map(function () { return $(this).val(); }).get().join('');
                $('#otp_code_hidden').val(val);
            });

            // Focus first box on page load
            setTimeout(function () { $('#otp_boxes .otp-box').first().focus(); }, 100);
        }
    })();
    </script>

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