<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************

require_once("loader.php");

$login = new User;
$core  = new Core;

if ($login->cdp_loginCheck() == true) {
    header("location: index.php");
    exit();
}

if (isset($_POST['login'])) {
    $result = $login->cdp_login($_POST['username'], $_POST['password']);
    if ($result) {
        header("location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['message_title_login0'] ?> | <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon ?>">

    <!-- Bootstrap 5 (local) -->
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (local) -->
    <link href="assets/custom_dependencies/css/bootstrap-icons.css" rel="stylesheet">
    <!-- MDI Icons (local) -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #1a3a6b;
            --brand-light: #2a5298;
            --brand-grad: linear-gradient(135deg, #1a3a6b 0%, #2a5298 100%);
            --accent: #17b26a;
            --accent-grad: linear-gradient(135deg, #0a8f50 0%, #17b26a 100%);
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4ff;
        }

        /* ── LAYOUT ─────────────────────────── */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Left panel */
        .login-panel {
            flex: 0 0 480px;
            max-width: 480px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 52px;
            position: relative;
            z-index: 2;
            box-shadow: 4px 0 30px rgba(0,0,0,0.08);
        }

        /* Right hero */
        .login-hero {
            flex: 1;
            background: var(--brand-grad);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }

        .login-hero-img {
            position: absolute;
            inset: 0;
            background-image: url('assets/css_main_deprixa/images/user/01.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.25;
        }

        .login-hero-content {
            position: relative;
            z-index: 2;
            padding: 52px;
            color: #fff;
        }

        .login-hero-content h1 {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }

        .login-hero-content p {
            font-size: 1.05rem;
            opacity: 0.85;
            max-width: 400px;
            line-height: 1.6;
        }

        .hero-stat-grid {
            display: flex;
            gap: 24px;
            margin-top: 36px;
        }

        .hero-stat {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 16px 20px;
            text-align: center;
            backdrop-filter: blur(6px);
        }

        .hero-stat .stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            display: block;
        }

        .hero-stat .stat-label {
            font-size: 0.75rem;
            opacity: 0.75;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── BRAND HEADER ───────────────────── */
        .login-brand {
            margin-bottom: 32px;
        }

        .brand-icon-wrap {
            width: 56px;
            height: 56px;
            background: var(--brand-grad);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }

        .brand-icon-wrap i {
            font-size: 26px;
            color: #fff;
        }

        .login-brand h2 {
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 4px;
        }

        .login-brand p {
            color: #6c757d;
            font-size: 0.875rem;
            margin: 0;
        }

        /* ── FORM ELEMENTS ──────────────────── */
        .form-label-sm {
            font-size: 0.83rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 17px;
            pointer-events: none;
        }

        .input-wrap .form-control {
            padding-left: 44px;
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: 0.9rem;
            height: 46px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrap .form-control:focus {
            border-color: var(--brand-light);
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
            outline: none;
        }

        /* ── BUTTONS ────────────────────────── */
        .btn-login {
            background: var(--brand-grad);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            height: 48px;
            width: 100%;
            letter-spacing: 0.2px;
            transition: opacity 0.2s, transform 0.15s;
            cursor: pointer;
        }

        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Quick action buttons */
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-qa {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            padding: 14px 10px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            transition: transform 0.15s, box-shadow 0.15s;
            line-height: 1;
        }

        .btn-qa:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.13);
            text-decoration: none;
        }

        .btn-qa i {
            font-size: 21px;
        }

        .btn-qa-quote {
            background: var(--accent-grad);
            color: #fff;
        }

        .btn-qa-quote:hover { color: #fff; }

        .btn-qa-track {
            background: var(--brand-grad);
            color: #fff;
        }

        .btn-qa-track:hover { color: #fff; }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #9ca3af;
            font-size: 0.78rem;
            font-weight: 500;
            margin: 22px 0 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* ── ALERT ──────────────────────────── */
        .alert-login {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: #b91c1c;
            font-size: 0.875rem;
            padding: 10px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── DEMO BADGES ────────────────────── */
        .demo-section {
            margin-top: 20px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px dashed #cbd5e1;
            text-align: center;
        }

        .demo-section p {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .demo-section .btn {
            font-size: 0.78rem;
            padding: 4px 12px;
        }

        /* ── RESPONSIVE ─────────────────────── */
        @media (max-width: 991px) {
            .login-wrapper { flex-direction: column; }
            .login-panel {
                flex: none;
                max-width: 100%;
                padding: 36px 28px;
                box-shadow: none;
            }
            .login-hero {
                min-height: 220px;
                align-items: center;
            }
            .login-hero-content {
                padding: 28px 24px;
            }
            .login-hero-content h1 { font-size: 1.5rem; }
            .hero-stat-grid { gap: 12px; }
        }
    </style>
</head>

<body>
<div class="login-wrapper">

    <!-- ── LEFT: Form Panel ─────────────────────────────── -->
    <div class="login-panel">

        <!-- Brand -->
        <div class="login-brand">
            <?php if ($core->logo_web): ?>
                <a href="index.php" style="display:inline-block;margin-bottom:10px">
                    <img src="assets/<?php echo htmlspecialchars((string)$core->logo_web, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8'); ?>"
                         style="max-height:42px;width:auto">
                </a>
            <?php endif; ?>
            <h2><?php echo $lang['message_title_login2'] ?> <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?php echo $lang['message_title_login1'] ?></p>
        </div>

        <!-- Errors -->
        <?php if (isset($login) && $login->errors): ?>
            <?php foreach ($login->errors as $error): ?>
                <div class="alert-login">
                    <i class="mdi mdi-alert-circle-outline"></i>
                    <?php echo $error; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Form -->
        <form method="post" name="login_form" id="login-form" autocomplete="off">

            <div class="mb-3">
                <label class="form-label-sm"><?php echo $lang['left115'] ?> <span class="text-danger">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text"
                           class="form-control"
                           placeholder="<?php echo $lang['left116'] ?>"
                           name="username"
                           id="username"
                           required
                           autocomplete="username">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label-sm"><?php echo $lang['left117'] ?> <span class="text-danger">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password"
                           class="form-control"
                           placeholder="<?php echo $lang['left118'] ?>"
                           name="password"
                           id="password"
                           required
                           autocomplete="current-password">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label" for="rememberMe" style="font-size:.83rem;color:#6b7280">
                        <?php echo $lang['left120'] ?>
                    </label>
                </div>
                <a href="forgot-password.php" style="font-size:.83rem;font-weight:600;color:var(--brand-light);text-decoration:none">
                    <?php echo $lang['left119'] ?>
                </a>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                <?php echo $lang['left121'] ?>
            </button>
            <input name="login" type="hidden" value="1">

            <?php if (isset($lang['left122']) && isset($lang['left123'])): ?>
            <p class="text-center mt-3 mb-0" style="font-size:.83rem;color:#6b7280">
                <?php echo $lang['left122'] ?>
                <a href="sign-up.php" style="font-weight:600;color:var(--brand-light);text-decoration:none">
                    <?php echo $lang['left123'] ?>
                </a>
            </p>
            <?php endif; ?>

        </form>

        <!-- Quick Access -->
        <div class="divider"><?php echo isset($lang['leftorder286']) ? $lang['leftorder286'] : 'Quick Access'; ?></div>

        <div class="quick-grid">
            <a href="cotizar.php" class="btn-qa btn-qa-quote">
                <i class="bi bi-calculator"></i>
                <span><?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Get a Quote'; ?></span>
            </a>
            <a href="tracking.php" class="btn-qa btn-qa-track">
                <i class="bi bi-geo-alt"></i>
                <span><?php echo isset($lang['langs_06']) ? $lang['langs_06'] : 'Track Shipment'; ?></span>
            </a>
        </div>

        <!-- Demo fast-login -->
        <?php if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true): ?>
        <div class="demo-section">
            <p>Demo — Fast Login</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <button type="button" onclick="setDemo('admin','09731')" class="btn btn-outline-secondary btn-sm">Admin</button>
                <button type="button" onclick="setDemo('employee','09731')" class="btn btn-outline-secondary btn-sm">Employee</button>
                <button type="button" onclick="setDemo('customer','09731')" class="btn btn-outline-secondary btn-sm">Customer</button>
                <button type="button" onclick="setDemo('driver','09731')" class="btn btn-outline-secondary btn-sm">Driver</button>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <!-- /.login-panel -->

    <!-- ── RIGHT: Hero Panel ─────────────────────────────── -->
    <div class="login-hero d-none d-lg-flex">
        <div class="login-hero-img"></div>
        <div class="login-hero-content">
            <h1><?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?php echo isset($lang['cotizador_sub']) ? $lang['cotizador_sub'] : 'Manage your shipments, track packages in real time, and get instant shipping rate estimates.'; ?></p>
            <div class="hero-stat-grid">
                <div class="hero-stat">
                    <span class="stat-num"><i class="bi bi-box-seam"></i></span>
                    <span class="stat-label"><?php echo isset($lang['ltracking']) ? $lang['ltracking'] : 'Tracking'; ?></span>
                </div>
                <div class="hero-stat">
                    <span class="stat-num"><i class="bi bi-calculator"></i></span>
                    <span class="stat-label"><?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Rates'; ?></span>
                </div>
                <div class="hero-stat">
                    <span class="stat-num"><i class="bi bi-shield-check"></i></span>
                    <span class="stat-label"><?php echo isset($lang['left499']) ? $lang['left499'] : 'Secure'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <!-- /.login-hero -->

</div>
<!-- /.login-wrapper -->

<!-- Bootstrap 5 JS (local) -->
<script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
<?php if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true): ?>
<script>
function setDemo(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>
<?php endif; ?>
</body>
</html>
