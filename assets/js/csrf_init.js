/**
 * csrf_init.js — Auto-attach CSRF token to all jQuery AJAX POST calls.
 * Loaded on every authenticated page (via footer.php), after jQuery.
 *
 * Uses $(document).ajaxSend() instead of $.ajaxSetup({ beforeSend }) so that
 * the token is injected even when individual $.ajax() calls define their own
 * beforeSend (which would otherwise replace the global one).
 */
(function ($) {
    "use strict";

    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return; // unauthenticated pages (login, track, signup) have no token

    var csrfToken = meta.getAttribute('content');
    if (!csrfToken) return;

    // ajaxSend fires for EVERY jQuery AJAX request regardless of per-call beforeSend
    $(document).ajaxSend(function (event, xhr, settings) {
        var method = (settings.type || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    });

}(jQuery));
