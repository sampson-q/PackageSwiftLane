<?php
/**
 * Stylesheet block shared by the public/auth pages (login, register, OTP,
 * forgot/reset, tracking, terms). Replaces the two @import lines that used to
 * sit at the top of auth-pages.css: an @import is only discovered once that
 * sheet has downloaded, so tokens and the Google Fonts CSS were fetched in
 * series after it, and all of it blocked the first paint. Here the tokens are
 * linked directly and the fonts load without blocking (preload, then switched
 * to a stylesheet; display=swap shows text in the fallback face meanwhile).
 *
 * A page that sets $cdpAuthPhoto before including this file gets its background
 * photo preloaded (see views/inc/auth_chrome.php).
 */
require_once __DIR__ . '/auth_chrome.php';
?>
    <?php if (!empty($cdpAuthPhoto)) { cdp_authPhotoPreload($cdpAuthPhoto); } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/swiftlane-tokens.css') ?>" rel="stylesheet" type="text/css" />
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'" />
    <noscript><link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" /></noscript>
    <link href="<?= cdp_asset('assets/css_main_swiftlane/css/auth-pages.css') ?>" rel="stylesheet" type="text/css" />
