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
