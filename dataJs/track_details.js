"use strict";

/* ============================================================================
   Public tracking detail pages (track.php / track_online_shopping.php).

   Reveal-on-scroll, count-up numbers, and "copy tracking number". Vanilla —
   these pages do not load jQuery.

   No map: the route map and its Leaflet/Nominatim dependency were removed, so
   nothing here talks to a third party any more.
   ========================================================================== */

(function () {
  // Reveal-hiding is gated on this class so a JS failure can never leave the
  // page blank.
  document.documentElement.classList.add("trk-js");

  document.addEventListener("DOMContentLoaded", function () {
    initReveal();
    initCountUps();
    initCopy();
  });

  /* ── Reveal on scroll ───────────────────────────────────────────────────── */
  function initReveal() {
    var els = document.querySelectorAll("[data-reveal]");
    if (!els.length) return;

    if (!("IntersectionObserver" in window)) {
      for (var i = 0; i < els.length; i++) els[i].classList.add("is-in");
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-in");
        io.unobserve(entry.target);
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });

    els.forEach(function (el) { io.observe(el); });

    // Belt and braces: anything still hidden after 2s (throttled rAF in a
    // background tab, a headless preview pane) is shown anyway.
    setTimeout(function () {
      document.querySelectorAll("[data-reveal]:not(.is-in)").forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.top < window.innerHeight) el.classList.add("is-in");
      });
    }, 2000);
  }

  /* ── Count-ups ──────────────────────────────────────────────────────────── */
  function initCountUps() {
    var els = document.querySelectorAll("[data-countup]");

    els.forEach(function (el) {
      var target = parseFloat(el.getAttribute("data-countup"));
      if (isNaN(target)) { el.textContent = el.getAttribute("data-countup"); return; }

      var decimals = (String(target).split(".")[1] || "").length;
      var start = null;
      var duration = 900;

      function frame(ts) {
        if (start === null) start = ts;
        var p = Math.min(1, (ts - start) / duration);
        // ease-out cubic
        var v = target * (1 - Math.pow(1 - p, 3));
        el.textContent = decimals ? v.toFixed(decimals) : Math.round(v).toString();
        if (p < 1) requestAnimationFrame(frame);
        else el.textContent = decimals ? target.toFixed(decimals) : String(target);
      }

      requestAnimationFrame(frame);
    });
  }

  /* ── Copy tracking number ───────────────────────────────────────────────── */
  function initCopy() {
    var btn = document.querySelector("[data-copy]");
    if (!btn) return;

    btn.addEventListener("click", function () {
      var text = btn.getAttribute("data-copy") || "";
      var done = function () {
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="mdi mdi-check"></i> Copied';
        setTimeout(function () { btn.innerHTML = original; }, 1600);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }

      function fallback() {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.setAttribute("readonly", "");
        ta.style.position = "absolute";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand("copy"); done(); } catch (e) { /* nothing to do */ }
        document.body.removeChild(ta);
      }
    });
  }
})();
