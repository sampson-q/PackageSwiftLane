<?php require_once __DIR__ . '/../../helpers/csrf.php'; ?>
<?php require_once __DIR__ . '/../../helpers/asset.php'; ?>
<?php
// Audit trail: one `view` row per page a signed-in user opens. This include is
// in the <head> of every authenticated page, which makes it the single place
// page views can be captured. See helpers/activity_log.php.
require_once __DIR__ . '/../../helpers/activity_log.php';
cdp_activityPageView();
?>
<link href="<?= cdp_asset('assets/vendor/libs/sweetalert2/sweetalert2.css') ?>" rel="stylesheet">
<meta name="csrf-param" content="<?php echo htmlspecialchars(cdp_csrf_param(), ENT_QUOTES, 'UTF-8'); ?>">
<meta name="csrf-token" content="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Fonts: Archivo Black (display) + Inter (UI) for the design system -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<?php /* Loaded without blocking the first paint: the sheet is fetched as a preload
   and switched to a stylesheet when it arrives; the fonts carry display=swap,
   so text shows in the fallback face until then. A render-blocking link here
   held every page's first paint on Google's answer (0.4 s here, seconds on
   a slow link). */ ?>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'" />
<noscript><link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" /></noscript>
<!-- Icons: Solar (Iconify) + legacy -->
<?php /* defer: a web component, it upgrades the icons whenever it arrives; without it a
   slow CDN answer held up the first paint of every page. */ ?>
<script defer src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
<style>
	iconify-icon{display:inline-flex;vertical-align:middle;line-height:1;}
</style>
<link rel="stylesheet" href="<?= cdp_asset('assets/vendor/fonts/fontawesome.css') ?>" />
<?php /* Tabler icons: four views use one glyph (ti ti-briefcase). They set
   $cdp_needs_tabler before including this file; everyone else skips the
   235 KB sheet. */ ?>
<?php if (!empty($cdp_needs_tabler)) { ?><link rel="stylesheet" href="<?= cdp_asset('assets/vendor/fonts/tabler-icons.css') ?>" />
<?php } ?>
<link rel="stylesheet" href="<?= cdp_asset('assets/vendor/fonts/flag-icons.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= cdp_asset('assets/template/dist/css/uicons-regular-rounded.css') ?>" />
<link href="<?= cdp_asset('assets/template/dist/css/style.min.css') ?>" rel="stylesheet">
<link href="<?= cdp_asset('assets/customClassPagination.css') ?>" rel="stylesheet">
<link href="<?= cdp_asset('assets/css/scroll-menu.css') ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= cdp_asset('assets/css/jquery.dataTables.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= cdp_asset('assets/template/assets/libs/select2/dist/css/select2.min.css') ?>">
<link rel="stylesheet" href="<?= cdp_asset('assets/template/assets/libs/intlTelInput/intlTelInput.css') ?>">
<!-- Swift Lane design system (tokens + shell override) — must load last so it cascades over the template -->
<link href="<?= cdp_asset('assets/css_main_swiftlane/css/swiftlane-tokens.css') ?>" rel="stylesheet" type="text/css" />
<link href="<?= cdp_asset('assets/css_main_swiftlane/css/swiftlane-ds.css') ?>" rel="stylesheet" type="text/css" />


<?php
if ($direction_layout == 'rtl') {
?>
    <link href="https://fonts.googleapis.com/css?family=Tajawal&subset=arabic" rel="stylesheet">
    <style>
        * {
            font-family: 'Tajawal';
        }
    </style>
<?php
}
?>
