<?php
// *************************************************************************
// *  Access denied (HTTP 403) — friendly, on-brand permission notice.     *
// *  Reached via `header("location: error403.php")` from guarded pages.    *
// *************************************************************************

require_once("loader.php");

$core = new Core();

if (!function_exists('cdp_asset')) {
    require_once __DIR__ . '/helpers/asset.php';
}

$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#111111">
    <title>Access Denied | <?php echo $e($core->site_name); ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $e($core->favicon); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css_main_swiftlane/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="<?= cdp_asset('assets/css_main_swiftlane/css/track-details.css') ?>">
    <style>
        body { margin: 0; background: #f5f1eb; }
        .err-badge {
            display: inline-block;
            margin-bottom: 16px;
            padding: 5px 15px;
            border-radius: 999px;
            font-weight: 800;
            letter-spacing: .12em;
            font-size: .74rem;
            text-transform: uppercase;
            color: #fff;
            background: linear-gradient(135deg, #f2b21b 0%, #ef2628 100%);
            box-shadow: 0 8px 20px rgba(239, 38, 40, 0.28);
        }
    </style>
</head>

<body>
<div class="trk">
    <section class="trk-empty">
        <div class="trk-empty__box">
            <div class="trk-empty__art">
                <span class="ring"></span>
                <span class="ring"></span>
                <span class="em">🔒</span>
            </div>
            <span class="err-badge">Error 403</span>
            <h1>Access denied</h1>
            <p>You don’t have permission to open this page. If you need it, please ask your administrator to grant you access.</p>
            <div class="trk-empty__actions">
                <a href="index.php" class="trk-btn trk-btn--grad"><i class="mdi mdi-home-outline"></i> Take Me Home</a>
                <a href="javascript:history.back()" class="trk-btn trk-btn--ghost"><i class="mdi mdi-arrow-left"></i> Go Back</a>
            </div>
        </div>
    </section>
</div>
</body>

</html>
