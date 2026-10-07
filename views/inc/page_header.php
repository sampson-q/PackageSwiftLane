<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * The page header: the title row every page opens with.
 *
 * The app shell has no separate top bar. The first element inside
 * .page-wrapper, when it is a .page-breadcrumb, is pulled up into the top
 * strip beside the icon tray (swiftlane-ds.css, section 4 "Page header").
 * A page that opens with anything else - or with an empty title row - leaves
 * the left of that strip blank.
 *
 * So EVERY page with the top bar calls this as the first thing inside
 * <div class="page-wrapper">:
 *
 *   <?php cdp_pageHeader('Shipping Rates'); ?>
 *   <?php cdp_pageHeader('Edit Office', ['sub' => $row_off->name_off ?? '']); ?>
 *
 * Options:
 *   sub      plain text shown beside the title (a record name, a reference)
 *   icon     an iconify icon name shown before the title
 *   actions  trusted HTML (buttons) at the right end of the title row
 *
 * Titles are Title Case. Loaded by views/inc/topbar.php, so it exists on every
 * page that has the top bar. scripts/check_page_shell.js (run by the
 * pre-commit hook in .githooks/) rejects a page that does not open with a
 * title row carrying a title.
 */

if (!function_exists('cdp_pageHeader')) {

    function cdp_pageHeader($title, array $opt = [])
    {
        $e     = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $title = trim((string) $title);
        $sub   = trim((string) ($opt['sub'] ?? ''));
        $icon  = trim((string) ($opt['icon'] ?? ''));
        $acts  = (string) ($opt['actions'] ?? '');

        echo '<div class="page-breadcrumb">'
           . '<div class="row">'
           . '<div class="col-12 align-self-center">'
           . '<h4 class="page-title">'
           . ($icon !== '' ? '<iconify-icon icon="' . $e($icon) . '"></iconify-icon> ' : '')
           . $e($title)
           . ($sub !== '' ? ' <small>' . $e($sub) . '</small>' : '')
           . '</h4>'
           . '</div>'
           . (trim($acts) !== '' ? '<div class="col-auto">' . $acts . '</div>' : '')
           . '</div>'
           . '</div>';
    }
}
