<?php require_once __DIR__ . '/../helpers/asset.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Terms &amp; Conditions | <?php echo $core->site_name ?></title>
    <meta name="keywords" content="Swiftlane - Integrated Web Shipping System">
    <meta name="author" content="iSolveAfrica Ltd.">
    <meta name="description" content="">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="assets/css_main_swiftlane/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_swiftlane/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Main Css -->
    <link href="assets/css_main_swiftlane/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_swiftlane/css/colors/default.css" rel="stylesheet" id="color-opt">
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/auth-pages.css') ?>" rel="stylesheet" type="text/css" />

    <style>
        /* ── Terms page: design-system tokens (auth-pages.css supplies them) ── */
        body.auth-page { background: var(--surface-page); }
        .terms-topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--white);
            border-bottom: 1px solid var(--border-default);
        }
        .terms-topbar-inner {
            max-width: 880px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .terms-topbar .logo img { max-height: 44px; width: auto; }
        .terms-topbar .btn-back {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-pill);
            background: var(--white);
            box-shadow: var(--ring-default);
            color: var(--ink-800);
            flex-shrink: 0;
            transition: background var(--motion-fast) var(--ease-standard);
        }
        .terms-topbar .btn-back:hover { background: var(--swift-amber); color: var(--ink-800); }
        .terms-main { max-width: 880px; margin: 0 auto; padding: 48px 24px 64px; }
        .terms-hero { text-align: center; margin-bottom: 32px; }
        .terms-hero .auth-badge { margin-bottom: 16px; }
        .terms-hero h1 {
            font-family: var(--font-display);
            font-weight: 400;
            font-size: clamp(28px, 3vw, 36px);
            line-height: 1.2;
            letter-spacing: var(--display-track);
            text-transform: uppercase;
            color: var(--ink-800);
            margin: 0 0 8px;
        }
        .terms-hero p { font-family: var(--font-ui); color: var(--slate-400); font-size: 16px; line-height: 24px; margin: 0; }
        .terms-card {
            background: var(--white);
            border-radius: var(--radius-24);
            padding: 40px;
        }
        .terms-card h4 {
            font-family: var(--font-ui);
            font-weight: 700;
            font-size: 16px;
            line-height: 24px;
            color: var(--ink-800);
            margin: 28px 0 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .terms-card h4::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--swift-amber);
            flex-shrink: 0;
        }
        .terms-card h4:first-of-type { margin-top: 0; }
        .terms-card p { font-family: var(--font-ui); color: var(--ink-800); line-height: 24px; margin: 0 0 8px; font-size: 14px; }
        .terms-card .terms-contact {
            margin-top: 32px;
            padding: 16px 20px;
            border-radius: var(--radius-16);
            background: var(--surface-page);
            font-family: var(--font-ui);
            font-size: 14px;
            line-height: 20px;
            color: var(--ink-800);
        }
        .terms-footer { text-align: center; margin-top: 32px; font-family: var(--font-ui); color: var(--slate-400); font-size: 14px; line-height: 20px; }
        .terms-footer a { color: var(--ink-800); font-weight: 700; text-decoration: none; }
        .terms-footer a:hover { text-decoration: underline; }

        @media (max-width: 575.98px) {
            .terms-topbar-inner { padding: 12px 16px; }
            .terms-main { padding: 32px 16px 48px; }
            .terms-card { padding: 24px 20px; }
        }
    </style>
</head>

<body class="auth-page">
    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner">
                <div class="double-bounce1"></div>
                <div class="double-bounce2"></div>
            </div>
        </div>
    </div>

    <!-- Sticky top bar -->
    <nav class="terms-topbar">
        <div class="terms-topbar-inner">
            <a class="logo" href="index.php">
                <?php echo ($core->logo_web)
                    ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>'
                    : '<strong>' . $core->site_name . '</strong>'; ?>
            </a>
            <a href="sign-up.php" class="btn-back" aria-label="Back to sign up">
                <i data-feather="arrow-left" class="icons" style="width:16px;height:16px;"></i>
            </a>
        </div>
    </nav>

    <!-- Main content -->
    <main class="terms-main">
        <div class="terms-hero">
            <span class="auth-badge">Legal</span>
            <h1>Terms &amp; Conditions</h1>
            <p>Read carefully before using our services.</p>
        </div>

        <div class="terms-card">
            <h4>1. Acceptance of Terms</h4>
            <p>By accessing and using our website and services, you agree to be bound by these Terms and Conditions. If you disagree with any part of these terms, you should not use our site.</p>

            <h4>2. Services</h4>
            <p>We provide shipping, tracking and logistics services under the conditions set out in these terms of use. We reserve the right to modify or discontinue any service without prior notice.</p>

            <h4>3. User Responsibility</h4>
            <p>The user is responsible for providing accurate information in all forms and processes on the site. Any attempt at fraud, information tampering, or misuse may result in account suspension.</p>

            <h4>4. Intellectual Property</h4>
            <p>All content on this site, including text, graphics and logos, is the property of <?php echo htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') ?> or its respective owners and is protected by copyright law.</p>

            <h4>5. Modifications</h4>
            <p>We reserve the right to modify these Terms at any time. Modifications will take effect as soon as they are published on the website.</p>

            <h4>6. Governing Law</h4>
            <p>These Terms are governed by the laws in effect in the country where our company is registered.</p>

            <div class="terms-contact">
                For any questions or enquiries, contact us at:
                <strong><?php echo htmlspecialchars($core->site_email ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </div>

        <div class="terms-footer">
            &copy; <?php echo date('Y') ?>
            <a href="index.php"><?php echo htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') ?></a>
            &mdash; All rights reserved.
            &nbsp;&bull;&nbsp;
            <a href="sign-up.php">Register</a>
            &nbsp;&bull;&nbsp;
            <a href="login.php">Sign in</a>
        </div>
    </main>

    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/css_main_swiftlane/js/bootstrap.bundle.min.js"></script>
    <script src="assets/css_main_swiftlane/js/feather.min.js"></script>
    <script src="assets/css_main_swiftlane/js/plugins.init.js"></script>
    <script src="assets/css_main_swiftlane/js/app.js"></script>
</body>

</html>
