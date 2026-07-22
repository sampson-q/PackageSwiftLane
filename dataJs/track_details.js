/* ==========================================================================
   SwiftLane — Shipment Tracking Detail interactions
   Reveal-on-scroll, count-up numbers, progress fill, and an animated Leaflet
   route map (origin → destination) geocoded live via OpenStreetMap Nominatim.
   Degrades gracefully: no Leaflet, no data, or a failed lookup just shows a
   styled fallback — the rest of the page still works.
   ========================================================================== */
(function () {
  "use strict";

  // Marker: reveal-hiding CSS is gated on html.trk-js, so if this script fails
  // to load the page still shows all content instead of staying blank.
  document.documentElement.classList.add("trk-js");

  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  document.addEventListener("DOMContentLoaded", function () {
    revealOnScroll();
    countUps();
    fillProgress();
    initMap();
  });

  /* ── Reveal on scroll ──────────────────────────────────────────────────── */
  function revealOnScroll() {
    var els = [].slice.call(document.querySelectorAll("[data-reveal]"));
    if (!els.length) return;
    if (reduce || !("IntersectionObserver" in window)) {
      els.forEach(function (el) { el.classList.add("is-in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("is-in"); io.unobserve(e.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ── Count-up numbers ──────────────────────────────────────────────────── */
  function countUps() {
    var els = [].slice.call(document.querySelectorAll("[data-countup]"));
    els.forEach(function (el) {
      var target = parseFloat(el.getAttribute("data-countup")) || 0;
      var decimals = (String(target).split(".")[1] || "").length;
      if (reduce || target === 0) { el.textContent = fmt(target, decimals); return; }
      var start = null, dur = 1200;
      function step(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(target * eased, decimals);
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = fmt(target, decimals);
      }
      requestAnimationFrame(step);
    });
  }
  function fmt(n, d) { return d ? n.toFixed(d) : Math.round(n).toString(); }

  /* ── Progress bar fill ─────────────────────────────────────────────────── */
  function fillProgress() {
    var fill = document.querySelector(".trk-steps__fill");
    if (!fill) return;
    // data-fill is the absolute fill width (already scaled to the rail) in %.
    var pct = parseFloat(fill.getAttribute("data-fill")) || 0;
    requestAnimationFrame(function () {
      setTimeout(function () { fill.style.width = pct + "%"; }, 60);
    });
  }

  /* ── Map ───────────────────────────────────────────────────────────────── */
  function initMap() {
    var el = document.getElementById("trk-map");
    if (!el) return;
    var fallback = document.querySelector(".trk-map-fallback");
    function fail() { if (fallback) fallback.classList.add("show"); }

    if (typeof window.L === "undefined") { fail(); return; }

    var origin = (el.getAttribute("data-origin") || "").trim();
    var dest = (el.getAttribute("data-dest") || "").trim();
    var mode = (el.getAttribute("data-mode") || "sea").trim();
    if (!origin && !dest) { fail(); return; }

    var map = L.map(el, { zoomControl: true, scrollWheelZoom: false, attributionControl: true });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 18,
      attribution: "&copy; OpenStreetMap"
    }).addTo(map);
    map.setView([20, 0], 2);

    Promise.all([geocode(origin), geocode(dest)]).then(function (pts) {
      var a = pts[0], b = pts[1];
      if (!a && !b) { map.remove(); fail(); return; }

      if (a) L.marker(a, { icon: pin("a") }).addTo(map).bindPopup("Origin<br><b>" + esc(origin) + "</b>");
      if (b) L.marker(b, { icon: pin("b") }).addTo(map).bindPopup("Destination<br><b>" + esc(dest) + "</b>");

      if (a && b) {
        var arc = buildArc(a, b);
        // faint full path
        L.polyline(arc, { color: "#94a3b8", weight: 2, opacity: 0.4, dashArray: "2 8" }).addTo(map);
        // animated drawn path
        var drawn = L.polyline([arc[0]], { color: "#ef2628", weight: 3.5, opacity: 0.9 }).addTo(map);
        var vehicle = L.marker(arc[0], { icon: vehicleIcon(mode), zIndexOffset: 1000 }).addTo(map);
        map.fitBounds(L.latLngBounds([a, b]).pad(0.35));
        animatePath(arc, drawn, vehicle);
      } else {
        map.setView(a || b, 6);
      }
    }).catch(function () { try { map.remove(); } catch (e) {} fail(); });
  }

  function pin(which) {
    return L.divIcon({
      className: "",
      html: '<div class="trk-marker trk-marker--' + which + '"><span>' + (which === "a" ? "A" : "B") + "</span></div>",
      iconSize: [34, 34],
      iconAnchor: [17, 34],
      popupAnchor: [0, -32]
    });
  }
  function vehicleIcon(mode) {
    var em = mode === "air" ? "✈️" : mode === "package" ? "📦" : "🚢";
    return L.divIcon({
      className: "",
      html: '<div class="trk-vehicle">' + em + "</div>",
      iconSize: [40, 40],
      iconAnchor: [20, 20]
    });
  }

  // quadratic bezier arc so the route bows nicely instead of a flat line
  function buildArc(a, b, n) {
    n = n || 64;
    var mx = (a[0] + b[0]) / 2, my = (a[1] + b[1]) / 2;
    var dx = b[0] - a[0], dy = b[1] - a[1];
    var dist = Math.sqrt(dx * dx + dy * dy);
    var lift = Math.min(dist * 0.22, 24);
    // control point offset perpendicular to the a→b line
    var cx = mx + (-dy / (dist || 1)) * lift;
    var cy = my + (dx / (dist || 1)) * lift;
    var pts = [];
    for (var i = 0; i <= n; i++) {
      var t = i / n, u = 1 - t;
      var lat = u * u * a[0] + 2 * u * t * cx + t * t * b[0];
      var lng = u * u * a[1] + 2 * u * t * cy + t * t * b[1];
      pts.push([lat, lng]);
    }
    return pts;
  }

  function animatePath(arc, drawn, vehicle) {
    if (reduce) {
      drawn.setLatLngs(arc);
      vehicle.setLatLng(arc[arc.length - 1]);
      return;
    }
    var i = 0;
    function tick() {
      i++;
      drawn.setLatLngs(arc.slice(0, i + 1));
      vehicle.setLatLng(arc[Math.min(i, arc.length - 1)]);
      if (i < arc.length - 1) {
        setTimeout(tick, 40);
      } else {
        // pause, then replay for a living feel
        setTimeout(function () { i = 0; drawn.setLatLngs([arc[0]]); vehicle.setLatLng(arc[0]); tick(); }, 2600);
      }
    }
    tick();
  }

  /* ── Geocoding via Nominatim (no key). Cached in sessionStorage. ────────── */
  function geocode(q) {
    if (!q) return Promise.resolve(null);
    var key = "trkgeo:" + q.toLowerCase();
    try {
      var hit = sessionStorage.getItem(key);
      if (hit) return Promise.resolve(JSON.parse(hit));
    } catch (e) {}
    var url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" + encodeURIComponent(q);
    return fetch(url, { headers: { "Accept": "application/json" } })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (j) {
        if (j && j.length) {
          var p = [parseFloat(j[0].lat), parseFloat(j[0].lon)];
          try { sessionStorage.setItem(key, JSON.stringify(p)); } catch (e) {}
          return p;
        }
        return null;
      })
      .catch(function () { return null; });
  }

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
})();
