/* Swift Lane UI runtime adapter.
 *
 * The design-system stylesheet (assets/css_main_swiftlane/css/swiftlane-ds.css)
 * restyles the shared shell from one place, but two things only exist in markup
 * that AJAX handlers render after page load and that CSS cannot reach:
 *
 *   1. Status labels carry their colour INLINE — `<span class="label"
 *      style="background:#hex">` from cdb_styles — so the design's tinted pill
 *      (hue on a 12% surface, 1.5px ring) needs the colour lifted into a CSS
 *      variable. Done here, then swiftlane-ds.css draws the pill.
 *   2. List-page search inputs (#search, #search_shipment, …) get the design's
 *      pill search field with a leading icon.
 *
 * Runs on load and again whenever the DOM changes (MutationObserver, debounced),
 * so every paginated / filtered table re-render is picked up. Loaded globally
 * from views/inc/footer.php. */
(function () {
    "use strict";

    var SEARCH_SELECTOR = 'input#search, input#search_shipment, input#search_package, input#search_pickup, input[name="search"]';
    var SEARCH_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';

    function upgradeStatusLabels(root) {
        var labels = root.querySelectorAll('.label[style]');
        for (var i = 0; i < labels.length; i++) {
            var el = labels[i];
            if (el.dataset.swl === '1') continue;
            var colour = el.style.backgroundColor || el.style.background;
            if (!colour) continue;
            el.style.background = '';
            el.style.backgroundColor = '';
            el.style.setProperty('--swl-status', colour);
            el.classList.add('swl-status');
            el.dataset.swl = '1';
        }
    }

    function upgradeSearchFields(root) {
        var inputs = root.querySelectorAll(SEARCH_SELECTOR);
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            if (input.type !== 'text' && input.type !== 'search') continue;
            if (input.closest('.swl-search')) continue;
            var wrap = document.createElement('div');
            wrap.className = 'swl-search';
            input.parentNode.insertBefore(wrap, input);
            wrap.innerHTML = SEARCH_ICON;
            wrap.appendChild(input);
        }
    }

    // Row-action dropdown toggles inside tables: the stylesheet draws a 32px
    // circle for an icon-only button ("•••") and a worded pill for "Actions".
    // Only the DOM knows whether there is text, so the class is set here.
    function upgradeRowActions(root) {
        var toggles = root.querySelectorAll('.table .btn-group .btn.dropdown-toggle');
        for (var i = 0; i < toggles.length; i++) {
            var t = toggles[i];
            t.classList.toggle('swl-icon-only', (t.textContent || '').trim() === '');
        }
    }

    // Any button that is an icon alone (the "+" beside a search field, a
    // trash can on a row) is tagged too: the stylesheet lets its grid column
    // shrink to the button so the field next to it keeps the room.
    function upgradeIconButtons(root) {
        var btns = root.querySelectorAll('.btn:not([data-swl-icon])');
        for (var i = 0; i < btns.length; i++) {
            var b = btns[i];
            b.setAttribute('data-swl-icon', '1');
            if ((b.textContent || '').trim() === '' && b.querySelector('i, svg, iconify-icon, img')) b.classList.add('swl-icon-only');
        }
    }

    // Table cells with short content (a date, an amount, a reference, a status)
    // stay on one line; a narrow column used to break "2026-08-06" over three
    // lines and triple the row height. Long text keeps wrapping.
    function upgradeTableCells(root) {
        var cells = root.querySelectorAll('.table td:not([data-swl-cell]), .table th:not([data-swl-cell])');
        for (var i = 0; i < cells.length; i++) {
            var td = cells[i];
            td.setAttribute('data-swl-cell', '1');
            if (td.querySelector('br, p, ul, table, textarea, .form-control, .custom-select, .dropdown-menu')) continue;
            var text = (td.textContent || '').trim().replace(/\s+/g, ' ');
            if (text.length > 0 && text.length <= 26) td.classList.add('swl-nowrap');
        }
    }

    // A table wider than its box scrolls sideways; the box shows a shadow on
    // the side that has more (swiftlane-ds.css, section 17).
    function syncScroller(box) {
        var more = box.scrollWidth - box.clientWidth;
        box.classList.toggle('swl-scroll-right', more > 2 && box.scrollLeft < more - 2);
        box.classList.toggle('swl-scroll-left', more > 2 && box.scrollLeft > 2);
    }
    function upgradeScrollers(root) {
        var boxes = root.querySelectorAll('.table-responsive');
        for (var i = 0; i < boxes.length; i++) {
            var box = boxes[i];
            if (box.dataset.swlScroll !== '1') {
                box.dataset.swlScroll = '1';
                box.addEventListener('scroll', function (e) { syncScroller(e.currentTarget); }, { passive: true });
            }
            syncScroller(box);
        }
    }
    window.addEventListener('resize', function () { upgradeScrollers(document); });

    // A row-action menu opened inside a scrolling table box is clipped by the
    // box (the last rows' menus open into the box's own scrollbar, a menu at
    // the right edge is cut). When such a menu opens it is pinned to the
    // viewport at the toggle's position instead; it closes if the box or the
    // page scrolls, so it never drifts from its button.
    function pinMenus($) {
        $(document).on('shown.bs.dropdown', function (e) {
            var toggle = e.target.querySelector ? e.target.querySelector('[data-toggle="dropdown"]') : null;
            var menu = e.target.querySelector ? e.target.querySelector('.dropdown-menu') : null;
            var box = e.target.closest ? e.target.closest('.table-responsive') : null;
            if (!toggle || !menu || !box) return;
            var dd = $(toggle).data('bs.dropdown');
            if (dd && dd._popper) { dd._popper.destroy(); dd._popper = null; }
            menu.dataset.swlCss = menu.getAttribute('style') || '';
            var t = toggle.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.transform = 'none';
            menu.style.willChange = 'auto';
            menu.style.margin = '0';
            var w = menu.offsetWidth, h = menu.offsetHeight;
            var left = Math.max(8, Math.min(t.right - w, window.innerWidth - w - 8));
            var top = t.bottom + 4;
            if (top + h > window.innerHeight - 8 && t.top - h - 4 > 8) top = t.top - h - 4;
            menu.style.left = Math.round(left) + 'px';
            menu.style.top = Math.round(top) + 'px';
            menu.style.right = 'auto';
            menu.style.bottom = 'auto';
            var close = function () { $(toggle).dropdown('hide'); };
            box.addEventListener('scroll', close, { once: true, passive: true });
            window.addEventListener('scroll', close, { once: true, passive: true });
            menu.swlUnpin = function () {
                box.removeEventListener('scroll', close);
                window.removeEventListener('scroll', close);
            };
        }).on('hidden.bs.dropdown', function (e) {
            var menu = e.target.querySelector ? e.target.querySelector('.dropdown-menu') : null;
            if (!menu || menu.dataset.swlCss === undefined) return;
            if (menu.swlUnpin) { menu.swlUnpin(); menu.swlUnpin = null; }
            menu.setAttribute('style', menu.dataset.swlCss);
            delete menu.dataset.swlCss;
        });
    }
    if (window.jQuery) pinMenus(window.jQuery);

    function run(root) {
        upgradeStatusLabels(root);
        upgradeSearchFields(root);
        upgradeRowActions(root);
        upgradeIconButtons(root);
        upgradeTableCells(root);
        upgradeScrollers(root);
    }

    var pending = null;
    function schedule() {
        if (pending) return;
        pending = window.requestAnimationFrame(function () {
            pending = null;
            run(document);
        });
    }

    function start() {
        run(document);
        if (!('MutationObserver' in window)) return;
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) { schedule(); return; }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    // Exposed for pages that build markup outside the DOM before inserting it.
    window.cdpSwiftLaneUi = { refresh: function (root) { run(root || document); } };
})();

/* ── App shell: sidebar collapse state, top bar tray ───────────────────────────
   The template decides the sidebar type again on EVERY window resize event, and
   plenty of widgets fire one (`$("body").trigger("resize")`), so a collapsed
   sidebar used to snap open by itself, and nothing remembered the choice on the
   next page. The choice now lives in localStorage and is enforced whenever the
   template changes the wrapper, on screens wide enough to have a choice
   (>= 1170px; below that the template's automatic mini mode stays in charge). */
(function () {
    'use strict';
    // The collapse choice is honoured from tablet width up. Below 1170px the
    // template's own resize handler forces mini-sidebar on every resize event and
    // (in app.min.js) sets the attribute without the class, so its click handler
    // toggles the class out of step with the attribute and the first click does
    // nothing. From here on the class and the attribute are kept in step and the
    // stored preference wins.
    var KEY = 'swl.nav', MIN_WIDTH = 768;

    function readPref() { try { return window.localStorage.getItem(KEY); } catch (e) { return null; } }
    function savePref(v) { try { window.localStorage.setItem(KEY, v); } catch (e) { /* private mode */ } }

    function shell() {
        var wrap = document.getElementById('main-wrapper');
        if (!wrap || !wrap.querySelector('.left-sidebar')) return;

        function isMini() { return wrap.getAttribute('data-sidebartype') === 'mini-sidebar'; }

        function enforce() {
            if (window.innerWidth < MIN_WIDTH) return;
            var pref = readPref();
            if (pref !== 'mini' && pref !== 'full') return;
            // Below 1170px the full menu is a passing overlay, never the state a
            // page should open in: a remembered "full" is left to the rail there.
            if (pref === 'full' && window.innerWidth < 1170) return;
            var mini = pref === 'mini', type = mini ? 'mini-sidebar' : 'full';
            if (wrap.classList.contains('mini-sidebar') !== mini) wrap.classList.toggle('mini-sidebar', mini);
            if (wrap.getAttribute('data-sidebartype') !== type) wrap.setAttribute('data-sidebartype', type);
        }

        // Capture phase: runs before the template's own click handler, which
        // toggles the class and then copies it to the attribute. The class is
        // first brought in step with the attribute (the two drift below 1170px),
        // so that toggle lands on the opposite of what is on screen; the
        // preference is stored so the observer below agrees with the click.
        document.addEventListener('click', function (e) {
            var t = e.target && e.target.closest ? e.target.closest('.sidebartoggler') : null;
            if (!t || window.innerWidth < MIN_WIDTH) return;
            wrap.classList.toggle('mini-sidebar', isMini());
            var next = isMini() ? 'full' : 'mini';
            // On a tablet the opened menu is an overlay for this page only.
            if (next === 'mini' || window.innerWidth >= 1170) savePref(next);
            t.setAttribute('aria-expanded', next === 'full' ? 'true' : 'false');
            // The toggle sits inside the sidebar, so the pointer is still on it:
            // hold off the hover-expansion until it leaves (swiftlane-ds.css).
            wrap.classList.add('swl-nav-settling');
        }, true);

        var aside = wrap.querySelector('.left-sidebar');

        // Closing the opened menu on small screens. Phones: the menu slides over
        // the page (show-sidebar); tablets (< 1170px): a "full" sidebar lays over
        // the page beside the rail. A tap on the backdrop (anywhere outside the
        // menu), the Escape key or the X in the brand row closes either.
        function menuOpen() {
            if (window.innerWidth < MIN_WIDTH) return wrap.classList.contains('show-sidebar');
            return window.innerWidth < 1170 && !isMini();
        }
        function closeMenu() {
            if (window.innerWidth < MIN_WIDTH) { wrap.classList.remove('show-sidebar'); return; }
            savePref('mini');
            wrap.classList.add('mini-sidebar');
            wrap.setAttribute('data-sidebartype', 'mini-sidebar');
        }
        document.addEventListener('click', function (e) {
            if (!menuOpen()) return;
            var t = e.target;
            if (t.closest && t.closest('.nav-toggler, .sidebartoggler')) {
                // the brand-row X on phones closes; the header hamburger is the template's toggle
                if (window.innerWidth < MIN_WIDTH && t.closest('.swl-brand__toggle')) { e.preventDefault(); e.stopPropagation(); closeMenu(); }
                return;
            }
            if (aside.contains(t)) return;
            e.preventDefault(); e.stopPropagation(); closeMenu();
        }, true);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menuOpen()) closeMenu(); });

        function settled() { wrap.classList.remove('swl-nav-settling'); }
        aside.addEventListener('mouseleave', settled);
        document.addEventListener('pointermove', function (e) {
            if (wrap.classList.contains('swl-nav-settling') && !aside.contains(e.target)) settled();
        }, { passive: true });

        if ('MutationObserver' in window) {
            new MutationObserver(enforce).observe(wrap, { attributes: true, attributeFilter: ['class', 'data-sidebartype'] });
        }
        window.addEventListener('resize', enforce);
        enforce();

        // Every page opens with its title row, written on the server by
        // cdp_pageHeader() (views/inc/page_header.php); the row shares this
        // strip with the tray. scripts/check_page_shell.js keeps it that way.

        // Title row and tray share one line: tell the CSS how wide the tray is so
        // the page title and its actions stop short of it.
        var tray = document.querySelector('.topbar .navbar-nav.float-right');
        function measureTray() {
            if (!tray) return;
            var w = Math.ceil(tray.getBoundingClientRect().width);
            if (w > 0) wrap.style.setProperty('--swl-tray-w', w + 'px');
        }
        measureTray();
        window.addEventListener('resize', measureTray);
        window.addEventListener('load', measureTray);

        // A badge showing 0 is noise: hide it until there is something to count.
        function syncBadges() {
            var badges = document.querySelectorAll('.topbar .badge-notify');
            for (var i = 0; i < badges.length; i++) {
                var n = parseInt((badges[i].textContent || '').trim(), 10);
                badges[i].classList.toggle('is-zero', !(n > 0));
            }
            measureTray();
        }
        syncBadges();
        if (tray && 'MutationObserver' in window) {
            new MutationObserver(syncBadges).observe(tray, { childList: true, subtree: true, characterData: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', shell);
    } else {
        shell();
    }
})();
