<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['left127'] ?> | <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon ?>">
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link href="assets/custom_dependencies/css/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #1a3a6b;
            --brand2: #2a5298;
            --brand-grad: linear-gradient(135deg, #1a3a6b 0%, #2a5298 100%);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; }

        /* ── NAV ─────────────────────────── */
        .pub-nav {
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            position: sticky; top: 0; z-index: 1030;
        }
        .pub-nav .inner {
            max-width: 1200px; margin: 0 auto;
            height: 64px; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link-item {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            font-size: .855rem; font-weight: 600;
            color: #374151; text-decoration: none;
            transition: background .15s, color .15s;
        }
        .nav-link-item:hover { background: #f0f4ff; color: var(--brand); }
        .nav-link-item.active { color: var(--brand2); }
        .btn-nav-login {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 8px;
            background: var(--brand-grad);
            color: #fff; font-size: .855rem; font-weight: 600;
            text-decoration: none; transition: opacity .15s;
            border: none;
        }
        .btn-nav-login:hover { opacity: .88; color: #fff; }

        /* ── HERO ────────────────────────── */
        .hero {
            background: var(--brand-grad);
            position: relative;
            overflow: hidden;
            padding: 90px 24px 100px;
            text-align: center;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url('assets/css_main_deprixa/images/user/bg.jpg') center/cover no-repeat;
            opacity: .08;
        }
        .hero-content { position: relative; z-index: 2; }
        .hero h1 {
            color: #fff;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 12px;
            text-shadow: 0 2px 12px rgba(0,0,0,.2);
        }
        .hero p {
            color: rgba(255,255,255,.82);
            font-size: 1.05rem;
            margin-bottom: 0;
        }

        /* ── SEARCH CARD ─────────────────── */
        .search-section {
            margin-top: -52px;
            position: relative;
            z-index: 10;
            padding: 0 24px 60px;
        }
        .search-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(26,58,107,.16);
            padding: 40px 44px;
            max-width: 680px;
            margin: 0 auto;
        }
        .search-card h4 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 6px;
        }
        .search-card > p {
            font-size: .875rem;
            color: #6b7280;
            margin-bottom: 24px;
        }

        /* Toggle type */
        .track-type-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .track-type-toggle label {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: .85rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all .15s;
        }
        .track-type-toggle input[type=radio] { display: none; }
        .track-type-toggle input[type=radio]:checked + label {
            border-color: var(--brand2);
            background: #eef2ff;
            color: var(--brand);
        }

        /* Textarea */
        .search-card textarea.form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: .9rem;
            padding: 14px 16px;
            resize: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .search-card textarea.form-control:focus {
            border-color: var(--brand2);
            box-shadow: 0 0 0 3px rgba(42,82,152,.1);
            outline: none;
        }

        .btn-track {
            background: var(--brand-grad);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            height: 52px;
            width: 100%;
            transition: opacity .2s, transform .15s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }
        .btn-track:hover { opacity: .9; transform: translateY(-1px); }
        .btn-track i { font-size: 20px; }

        /* ── FEATURES ────────────────────── */
        .features-row {
            max-width: 680px;
            margin: 24px auto 0;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .feat-pill {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(26,58,107,.12);
            border-radius: 50px;
            padding: 7px 16px;
            font-size: .78rem;
            font-weight: 600;
            color: var(--brand);
        }
        .feat-pill i { font-size: 15px; }

        /* ── FOOTER ──────────────────────── */
        .pub-footer {
            text-align: center;
            padding: 20px 24px;
            font-size: .8rem;
            color: #9ca3af;
            border-top: 1px solid #f3f4f6;
        }
        .pub-footer a { color: #6b7280; text-decoration: none; font-weight: 600; }
        .pub-footer a:hover { color: var(--brand); }
    </style>
</head>
<body style="background:#f0f4ff">

<!-- NAV -->
<nav class="pub-nav">
    <div class="inner">
        <a class="nav-logo" href="index.php">
            <?php if ($core->logo_web): ?>
                <img src="assets/<?php echo htmlspecialchars((string)$core->logo_web, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?>"
                     style="height:36px;width:auto">
            <?php else: ?>
                <span style="font-weight:800;font-size:1.1rem;color:var(--brand)"><?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <div class="nav-links">
            <a href="tracking.php" class="nav-link-item active">
                <i class="bi bi-geo-alt"></i>
                <?php echo isset($lang['langs_06']) ? $lang['langs_06'] : 'Track & Trace'; ?>
            </a>
            <a href="cotizar.php" class="nav-link-item">
                <i class="bi bi-calculator"></i>
                <?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Get a Quote'; ?>
            </a>
            <a href="login.php" class="btn-nav-login">
                <i class="bi bi-box-arrow-in-right"></i>
                <?php echo isset($lang['left121']) ? $lang['left121'] : 'Login'; ?>
            </a>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <h1><?php echo $lang['left127'] ?></h1>
        <p><?php echo $lang['left128'] ?></p>
    </div>
</section>

<!-- SEARCH CARD -->
<div class="search-section">
    <div class="search-card">
        <h4><i class="bi bi-search me-2" style="color:var(--brand2)"></i><?php echo $lang['left127'] ?></h4>
        <p><?php echo $lang['left130'] ?></p>

        <form method="POST" name="ib_form" id="ib_form">
            <!-- Type toggle -->
            <div class="track-type-toggle">
                <input type="radio" name="trackingType" id="type1" value="1" checked>
                <label for="type1">
                    <i class="bi bi-box-seam"></i>
                    <?php echo $lang['message_title_tracking1'] ?>
                </label>
                <input type="radio" name="trackingType" id="type2" value="2">
                <label for="type2">
                    <i class="bi bi-bag"></i>
                    <?php echo $lang['message_title_tracking2'] ?>
                </label>
            </div>

            <textarea name="order_track"
                      id="order_track"
                      rows="3"
                      class="form-control"
                      placeholder="<?php echo $lang['left130'] ?>"
                      required></textarea>

            <button type="submit" class="btn-track">
                <i class="bi bi-search"></i>
                <?php echo $lang['left131'] ?>
            </button>
        </form>
    </div>

    <div class="features-row">
        <div class="feat-pill"><i class="bi bi-lightning-charge text-warning"></i> <?php echo isset($lang['cotizador_feat1']) ? $lang['cotizador_feat1'] : 'Instant'; ?></div>
        <div class="feat-pill"><i class="bi bi-lock" style="color:var(--brand2)"></i> <?php echo isset($lang['cotizador_feat2']) ? $lang['cotizador_feat2'] : 'Secure'; ?></div>
        <div class="feat-pill"><i class="bi bi-clock" style="color:var(--accent,#17b26a)"></i> <?php echo isset($lang['cotizador_feat3']) ? $lang['cotizador_feat3'] : 'Real-time'; ?></div>
    </div>
</div>

<!-- FOOTER -->
<footer class="pub-footer">
    &copy; <?php echo date('Y') ?> <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?> &nbsp;&middot;&nbsp;
    <a href="cotizar.php"><?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Get a Quote'; ?></a> &nbsp;&middot;&nbsp;
    <a href="login.php"><?php echo isset($lang['left121']) ? $lang['left121'] : 'Login'; ?></a>
</footer>

<script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
<script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
<script src="dataJs/tracking.js"></script>
</body>
</html>
