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

    function run(root) {
        upgradeStatusLabels(root);
        upgradeSearchFields(root);
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
            savePref(next);
            t.setAttribute('aria-expanded', next === 'full' ? 'true' : 'false');
            // The toggle sits inside the sidebar, so the pointer is still on it:
            // hold off the hover-expansion until it leaves (swiftlane-ds.css).
            wrap.classList.add('swl-nav-settling');
        }, true);

        var aside = wrap.querySelector('.left-sidebar');
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

        // About a third of the views have no header row: their title is a
        // .card-title inside the first card, which left an empty strip holding
        // only the tray. Promote that title into a standard header so every
        // page has the same title row. The document title is the fallback, and a
        // card title that carries controls is left where it is.
        (function promoteTitle() {
            var pw = wrap.querySelector('.page-wrapper');
            if (!pw || pw.querySelector('.page-breadcrumb')) return;
            // Only the FIRST card's own title qualifies, and only when it is that
            // card's first heading: the detail views open with an h4 header card
            // and their first .card-title is a later section ("User Action
            // History"), which used to be promoted as the page title.
            var card = pw.querySelector('.card'), src = null, text = '';
            if (card) {
                var firstHeading = card.querySelector('h1, h2, h3, h4, h5, .card-title');
                if (firstHeading && firstHeading.classList.contains('card-title')) src = firstHeading;
            }
            if (src && !src.querySelector('a, button, input, select, textarea')) {
                text = (src.textContent || '').replace(/\s+/g, ' ').trim();
            } else {
                src = null;
            }
            if (!text) text = (document.title || '').split('|')[0].trim();
            if (!text || text.length > 80) return;
            var head = document.createElement('div');
            head.className = 'page-breadcrumb swl-page-head--auto';
            head.innerHTML = '<div class="row"><div class="col-12 align-self-center"><h4 class="page-title"></h4></div></div>';
            head.querySelector('.page-title').textContent = text;
            pw.insertBefore(head, pw.firstElementChild);
            if (src) src.classList.add('swl-promoted');
        })();

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
