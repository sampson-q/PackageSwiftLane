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
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/materialdesignicons.min.css') ?>" rel="stylesheet" type="text/css" />
    <!-- Main Css -->
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/style.css') ?>" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/colors/default.css') ?>" rel="stylesheet" id="color-opt">
    <?php $cdpAuthPhoto = 'terms'; include 'views/inc/auth_head.php'; ?>

    <style>
        /* ── Terms page: design-system tokens (auth-pages.css supplies them) ── */
        .terms-card {
            width: 100%;
            max-width: 880px;
            background: var(--white);
            border-radius: var(--radius-24);
            padding: 40px;
            box-shadow: 0 24px 64px rgba(8, 16, 28, .38);
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
        @media (max-width: 575.98px) {
            .terms-card { padding: 24px 20px; border-radius: 20px; }
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

    <?php cdp_authFrameOpen($core, [
        'photo' => 'terms',
        'badge' => 'Legal',
        'title' => 'Terms & Conditions',
        'links' => [['href' => 'sign-up.php', 'label' => 'Register', 'icon' => 'user-plus'], ['href' => 'login.php', 'label' => 'Sign In', 'icon' => 'log-in', 'primary' => true]],
        'wide'  => true,
    ]); ?>

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

    <?php cdp_authFrameClose($core); ?>


    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/feather.min.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/plugins.init.js') ?>"></script>
    <script src="<?= cdp_asset('assets/css_main_swiftlane/js/app.js') ?>"></script>
</body>

</html>
