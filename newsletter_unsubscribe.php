<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Public page behind the [UNSUBSCRIBE_URL] link of every newsletter.
 *
 * The link carries the address and an HMAC of it (helpers/newsletter.php), so
 * nobody can take someone else off the list. Opening the link (GET) only asks
 * for confirmation: mail scanners open links on their own. The button, or a
 * mail client's one-click unsubscribe (RFC 8058 POST), takes the address off.
 */

require_once("loader.php");
require_once("helpers/newsletter.php");

$core  = new Core();
$email = strtolower(trim((string) ($_REQUEST['e'] ?? '')));
$token = (string) ($_REQUEST['t'] ?? '');
$valid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && cdp_nlUnsubscribeValid($email, $token);
$done  = false;

if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $done = cdp_nlUnsubscribe($email);
    if (isset($_POST['List-Unsubscribe'])) {
        // One-click from the mail client: no page to show.
        header('Content-Type: text/plain; charset=utf-8');
        echo $done ? 'Unsubscribed' : 'Error';
        exit;
    }
}

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Newsletter | <?php echo $e($core->site_name); ?></title>
    <link href="assets/css_main_swiftlane/css/swiftlane-tokens.css" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: var(--surface-page); color: var(--text-primary); font-family: var(--font-ui); }
        .box { background: var(--surface-card); border-radius: var(--radius-24); box-shadow: var(--ring-default);
               padding: var(--space-40); max-width: 440px; margin: var(--space-16); text-align: center; }
        h1 { font-family: var(--font-display); font-size: var(--display-md-size); letter-spacing: var(--display-track); margin: 0 0 var(--space-12); }
        p { line-height: 1.5; color: var(--ink-700); }
        button, a.btn { display: inline-block; border: 0; border-radius: var(--radius-pill); background: var(--accent); color: var(--accent-ink);
               font-weight: var(--weight-semibold); padding: 14px 28px; font-size: 15px; cursor: pointer; text-decoration: none; margin-top: var(--space-12); }
    </style>
</head>
<body>
<div class="box">
    <?php if (!$valid) { ?>
        <h1>Link not recognised</h1>
        <p>This unsubscribe link is incomplete or has been changed. Use the link from the latest newsletter, or reply to it and we will take you off the list.</p>
    <?php } elseif ($done) { ?>
        <h1>You are unsubscribed</h1>
        <p><strong><?php echo $e($email); ?></strong> will no longer receive newsletters from <?php echo $e($core->site_name); ?>. Messages about your shipments are not affected.</p>
        <a class="btn" href="index.php">Go to <?php echo $e($core->site_name); ?></a>
    <?php } else { ?>
        <h1>Unsubscribe?</h1>
        <p>Stop sending newsletters from <?php echo $e($core->site_name); ?> to <strong><?php echo $e($email); ?></strong>? Messages about your shipments are not affected.</p>
        <form method="post">
            <input type="hidden" name="e" value="<?php echo $e($email); ?>">
            <input type="hidden" name="t" value="<?php echo $e($token); ?>">
            <button type="submit">Unsubscribe</button>
        </form>
    <?php } ?>
</div>
</body>
</html>
