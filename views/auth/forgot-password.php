<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['langs_010106'] ?> | <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon ?>">

    <!-- Bootstrap 5 (local) -->
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (local) -->
    <link href="assets/custom_dependencies/css/bootstrap-icons.css" rel="stylesheet">
    <!-- MDI Icons (local) -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">

    <!-- jQuery (needed by forgot_password.js) -->
    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/js/global.js"></script>
    <script src="assets/js/custom.js"></script>

    <style>
        :root {
            --brand: #1a3a6b;
            --brand-light: #2a5298;
            --brand-grad: linear-gradient(135deg, #1a3a6b 0%, #2a5298 100%);
        }

        html, body { height: 100%; margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; }

        /* ── LAYOUT ─────────────────────────── */
        .fp-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Left panel */
        .fp-panel {
            flex: 0 0 480px;
            max-width: 480px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 52px;
            box-shadow: 4px 0 30px rgba(0,0,0,0.07);
            position: relative;
            z-index: 2;
        }

        /* Right hero */
        .fp-hero {
            flex: 1;
            background: var(--brand-grad);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fp-hero-img {
            position: absolute;
            inset: 0;
            background-image: url('assets/css_main_deprixa/images/user/03.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.2;
        }

        .fp-hero-content {
            position: relative;
            z-index: 2;
            color: #fff;
            text-align: center;
            padding: 40px;
        }

        .fp-hero-icon {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            backdrop-filter: blur(6px);
        }

        .fp-hero-icon i { font-size: 44px; color: #fff; }

        .fp-hero-content h2 {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 12px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }

        .fp-hero-content p {
            font-size: 0.95rem;
            opacity: 0.82;
            max-width: 340px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ── BRAND ──────────────────────────── */
        .fp-brand { margin-bottom: 32px; }

        .fp-brand h2 {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--brand);
            margin: 12px 0 4px;
        }

        .fp-brand p {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.5;
        }

        /* ── ICON BADGE ─────────────────────── */
        .lock-badge {
            width: 60px;
            height: 60px;
            background: var(--brand-grad);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .lock-badge i { font-size: 26px; color: #fff; }

        /* ── FORM ───────────────────────────── */
        .form-label-sm {
            font-size: 0.83rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .input-wrap { position: relative; }

        .input-wrap .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
            pointer-events: none;
        }

        .input-wrap .form-control {
            padding-left: 42px;
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: 0.9rem;
            height: 46px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrap .form-control:focus {
            border-color: var(--brand-light);
            box-shadow: 0 0 0 3px rgba(42,82,152,0.1);
            outline: none;
        }

        .btn-send {
            background: var(--brand-grad);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            height: 48px;
            width: 100%;
            transition: opacity 0.2s, transform 0.15s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-send:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-send:active { transform: translateY(0); }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #9ca3af;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 22px 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* Back link */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--brand-light);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.15s;
        }

        .back-link:hover { opacity: 0.75; color: var(--brand-light); }

        /* Alert result */
        #resultados_ajax .alert {
            border-radius: 8px;
            font-size: 0.875rem;
        }

        @media (max-width: 991px) {
            .fp-wrapper { flex-direction: column; }
            .fp-panel {
                flex: none;
                max-width: 100%;
                padding: 36px 28px;
                box-shadow: none;
            }
            .fp-hero {
                min-height: 200px;
                order: -1;
            }
        }
    </style>
</head>
<body>

<div class="fp-wrapper">

    <!-- ── LEFT: Form ───────────────────────────────── -->
    <div class="fp-panel">

        <div class="fp-brand">
            <?php if ($core->logo_web): ?>
                <a href="login.php" style="display:inline-block;margin-bottom:14px">
                    <img src="assets/<?php echo htmlspecialchars((string)$core->logo_web, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8'); ?>"
                         style="max-height:40px;width:auto">
                </a>
            <?php endif; ?>
            <div class="lock-badge">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h2><?php echo $lang['left172'] ?></h2>
            <p><?php echo $lang['message_title_forgot1'] ?></p>
        </div>

        <div id="resultados_ajax"></div>
        <div id="loader" style="display:none"></div>

        <form name="forgotPassword" id="forgotPassword" method="post" autocomplete="off">

            <div class="mb-4">
                <label class="form-label-sm"><?php echo $lang['lemailad'] ?> <span class="text-danger">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email"
                           class="form-control"
                           placeholder="<?php echo $lang['left176'] ?>"
                           id="email"
                           name="email"
                           required>
                </div>
            </div>

            <button type="submit" name="dosubmit" class="btn-send">
                <i class="bi bi-send"></i>
                <?php echo $lang['langs_010108'] ?>
            </button>

        </form>

        <div class="divider"><?php echo isset($lang['leftorder286']) ? $lang['leftorder286'] : 'or'; ?></div>

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            <?php echo $lang['langs_010111'] ?>
        </a>

        <?php if ($core->reg_allowed): ?>
        <p class="text-center mt-3 mb-0" style="font-size:.83rem;color:#6b7280">
            <?php echo $lang['langs_010109'] ?>
            <a href="sign-up.php" style="font-weight:600;color:var(--brand-light);text-decoration:none">
                <?php echo $lang['langs_010110'] ?>
            </a>
        </p>
        <?php endif; ?>

    </div>
    <!-- /.fp-panel -->

    <!-- ── RIGHT: Hero ──────────────────────────────── -->
    <div class="fp-hero d-none d-lg-flex">
        <div class="fp-hero-img"></div>
        <div class="fp-hero-content">
            <div class="fp-hero-icon">
                <i class="bi bi-key"></i>
            </div>
            <h2><?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?php echo $lang['message_title_forgot1'] ?></p>
        </div>
    </div>

</div>
<!-- /.fp-wrapper -->

<script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
<script src="dataJs/forgot_password.js"></script>

</body>
</html>
