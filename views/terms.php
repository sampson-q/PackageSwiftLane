<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Terms &amp; Conditions | <?php echo $core->site_name ?></title>
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Main Css -->
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">
    <link href="assets/css_main_deprixa/css/auth-pages.css" rel="stylesheet" type="text/css" />

    <style>
        /* ── Terms-specific layout ──────────────────────────────── */
        .terms-topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(226,160,39,0.14);
            box-shadow: 0 2px 14px rgba(15,23,42,0.05);
        }

        .terms-topbar-inner {
            max-width: 880px;
            margin: 0 auto;
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .terms-topbar .logo img { max-height: 48px; width: auto; }

        .terms-topbar .btn-back {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: linear-gradient(135deg, #f2b21b, #ef2628);
            border: 0;
            box-shadow: 0 8px 22px rgba(239,38,40,0.18);
            color: #ffffff;
            transition: transform 0.15s;
            flex-shrink: 0;
        }

        .terms-topbar .btn-back:hover { transform: translateY(-1px); }

        .terms-main {
            max-width: 880px;
            margin: 0 auto;
            padding: 3rem 1.5rem 4rem;
        }

        .terms-hero {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .terms-hero .auth-badge { margin-bottom: 1rem; }

        .terms-hero h1 {
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
            margin: 0 0 0.6rem;
        }

        .terms-hero p {
            color: #64748b;
            font-size: 1rem;
            margin: 0;
        }

        .terms-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(226,160,39,0.14);
            border-radius: 20px;
            box-shadow: 0 12px 48px rgba(15,23,42,0.08);
            backdrop-filter: blur(14px);
            padding: 2.5rem 2.5rem 2rem;
        }

        .terms-card h4 {
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            font-size: 1.05rem;
            margin: 1.75rem 0 0.55rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .terms-card h4::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f2b21b, #ef2628);
            flex-shrink: 0;
            box-shadow: 0 0 0 3px rgba(242,178,27,0.14);
        }

        .terms-card h4:first-of-type { margin-top: 0; }

        .terms-card p {
            color: #334155;
            line-height: 1.78;
            margin: 0 0 0.5rem;
            font-size: 0.95rem;
        }

        .terms-card .terms-contact {
            margin-top: 2rem;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            background: rgba(242,178,27,0.07);
            border: 1px solid rgba(242,178,27,0.18);
            font-size: 0.9rem;
            color: #0f172a;
        }

        .terms-footer {
            text-align: center;
            margin-top: 2.5rem;
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .terms-footer a { color: #ef2628; font-weight: 700; text-decoration: none; }
        .terms-footer a:hover { text-decoration: underline; }

        @media (max-width: 575.98px) {
            .terms-topbar-inner { padding: 0.7rem 1rem; }
            .terms-main { padding: 2rem 0.75rem 3rem; }
            .terms-card { padding: 1.5rem 1.25rem; }
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
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <script src="assets/css_main_deprixa/js/app.js"></script>
</body>

</html>
