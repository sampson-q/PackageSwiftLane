"use strict";

// ============================================================================
// Staff presence beacon.
//
// Loaded from views/inc/footer.php for STAFF roles only (see
// helpers/staff_activity.php::cdp_spBeaconWanted). Watches for keyboard, mouse,
// scroll and touch input and, on a timer, tells the server which minutes saw
// any input at all. The server stores one row per (staff member, minute) in
// cdb_staff_presence; the Staff Productivity report reads that to tell working
// time from idle time.
//
// What it does NOT do: it records no keystrokes, no mouse positions, no page
// content — only "this minute had input" and the page's file name. A hidden
// tab receives no input events, so it reports nothing.
//
// Minutes travel as "minutes ago" offsets, so the server clock decides the
// timestamp and a wrong client clock cannot back-date rows.
// ============================================================================

(function () {
    var cfg = window.CDP_PRESENCE;
    if (!cfg || !cfg.url) return;

    var MAX_AGO = 30;
    var every = Math.max(15, parseInt(cfg.every, 10) || 60) * 1000;
    var pending = {};   // client-clock minute bucket => true
    var flushing = false;

    function bucket(ms) { return Math.floor(ms / 60000); }
    function markInput() { pending[bucket(Date.now())] = true; }

    var opts = { passive: true, capture: true };
    ['mousemove', 'mousedown', 'pointerdown', 'keydown', 'wheel', 'scroll', 'touchstart', 'input']
        .forEach(function (ev) { window.addEventListener(ev, markInput, opts); });

    function meta(name, fallback) {
        var m = document.querySelector('meta[name="' + name + '"]');
        return (m && m.getAttribute('content')) || fallback || '';
    }

    // Build the form body; drop anything too old to be accepted.
    function payload() {
        var nowB = bucket(Date.now());
        var keys = Object.keys(pending);
        var fd = new FormData();
        var sent = [];
        fd.append(meta('csrf-param', '_csrf_token'), meta('csrf-token'));
        fd.append('page', (location.pathname.split('/').pop() || '').replace(/\.php$/, ''));
        keys.forEach(function (k) {
            var ago = nowB - parseInt(k, 10);
            if (ago < 0 || ago > MAX_AGO) { delete pending[k]; return; }
            fd.append('ago[]', ago);
            sent.push(k);
        });
        return sent.length ? { fd: fd, keys: sent } : null;
    }

    function flush(unloading) {
        var p = payload();
        if (!p) return;

        if (unloading && navigator.sendBeacon) {
            // sendBeacon cannot set headers, so the CSRF token rides in the body.
            if (navigator.sendBeacon(cfg.url, p.fd)) {
                p.keys.forEach(function (k) { delete pending[k]; });
            }
            return;
        }
        if (flushing) return;
        flushing = true;

        fetch(cfg.url, {
            method: 'POST',
            body: p.fd,
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'X-CSRF-Token': meta('csrf-token') }
        }).then(function (r) {
            if (r.ok) { p.keys.forEach(function (k) { delete pending[k]; }); }
            // Otherwise keep them: retried next tick, dropped once older than MAX_AGO.
        }).catch(function () {
            // Network hiccup — same as above.
        }).then(function () {
            flushing = false;
        });
    }

    setInterval(function () { flush(false); }, every);
    window.addEventListener('pagehide', function () { flush(true); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) flush(true); });
})();
