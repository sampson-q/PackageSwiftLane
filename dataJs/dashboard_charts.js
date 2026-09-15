"use strict";

/* Shared control-panel chart renderer (ApexCharts, Swift Lane design system).
 * Pages declare window.cdpDashCharts = [{el, type, series, labels, colors,
 * money, height, tone, rows, xLabels}] (emitted by cdp_dashChartsRender in
 * helpers/dashboard_data.php) and this file draws them all with one look:
 *   bar | area | line | donut  — as before
 *   rings   — concentric radial bars (the design's PackageDonut); series are
 *             raw values, drawn as a share of their sum
 *   heatmap — weekday × hour matrix (the design's DeliveryHeatmap); `rows` is
 *             [[24 ints] × 7], `xLabels` the hour labels
 * tone:'inverse' draws on the ink panel (light labels, translucent tracks). */

(function () {
    var PALETTE = ["#FFCB01", "#0077B6", "#00B4D8", "#E8811A", "#7C3EE2", "#C8410F", "#1DBF73", "#37A4F3"];
    var INK = "#192A3E", SLATE = "#9BA9BB", LINE = "#E1E5EA";

    function fmtMoney(v) {
        var cur = window.cdpDashCurrency || "$";
        return cur + " " + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtInt(v) { return Number(v || 0).toLocaleString(); }

    function baseOptions(cfg) {
        var money = !!cfg.money;
        var fmt = money ? fmtMoney : fmtInt;
        var inverse = cfg.tone === "inverse";
        return {
            chart: {
                type: cfg.type === "donut" ? "donut" : cfg.type,
                height: cfg.height || 300,
                fontFamily: "Inter, 'SF Pro Display', -apple-system, 'Segoe UI', sans-serif",
                foreColor: inverse ? "rgba(255,255,255,.72)" : SLATE,
                toolbar: { show: false },
                animations: { speed: 400 }
            },
            colors: (cfg.colors && cfg.colors.length ? cfg.colors : PALETTE),
            dataLabels: { enabled: false },
            grid: { borderColor: inverse ? "rgba(225,229,234,.18)" : LINE, strokeDashArray: 0, padding: { left: 4, right: 4 } },
            legend: { position: "bottom", fontSize: "12px", fontWeight: 500, markers: { radius: 12 }, labels: { colors: inverse ? "#fff" : INK } },
            tooltip: { theme: inverse ? "dark" : "light", y: { formatter: fmt } },
            noData: { text: "No data yet", style: { fontSize: "13px", color: SLATE } }
        };
    }

    function render(cfg) {
        var el = document.querySelector(cfg.el);
        if (!el || typeof ApexCharts === "undefined") { return; }
        var o = baseOptions(cfg);
        var money = !!cfg.money;
        var fmt = money ? fmtMoney : fmtInt;
        var inverse = cfg.tone === "inverse";

        if (cfg.type === "donut") {
            o.series = cfg.series;
            o.labels = cfg.labels || [];
            o.stroke = { width: 2, colors: [inverse ? INK : "#fff"] };
            o.plotOptions = { pie: { donut: { size: "70%" } } };
        } else if (cfg.type === "rings") {
            var raw = cfg.series || [];
            var total = raw.reduce(function (a, b) { return a + Number(b || 0); }, 0);
            o.chart.type = "radialBar";
            o.series = raw.map(function (v) { return total > 0 ? Math.round(1000 * Number(v || 0) / total) / 10 : 0; });
            o.labels = cfg.labels || [];
            o.legend = { show: false };
            o.stroke = { lineCap: "round" };
            o.plotOptions = {
                radialBar: {
                    hollow: { size: "28%" },
                    track: { background: inverse ? "rgba(255,255,255,.10)" : "#EDF0F3", margin: 4 },
                    dataLabels: {
                        name: { show: true, fontSize: "12px", fontWeight: 500, color: inverse ? "rgba(255,255,255,.72)" : SLATE, offsetY: -6 },
                        value: { show: true, fontSize: "20px", fontWeight: 700, fontFamily: "'Archivo Black', 'Arial Black', sans-serif", color: inverse ? "#fff" : INK, offsetY: 4,
                                 formatter: function (v) { return v + "%"; } },
                        total: { show: true, label: "Total", fontSize: "12px", color: inverse ? "rgba(255,255,255,.72)" : SLATE,
                                 formatter: function () { return fmtInt(total); } }
                    }
                }
            };
            o.tooltip = { enabled: true, theme: inverse ? "dark" : "light", y: { formatter: function (v, opts) { return fmtInt(raw[opts.seriesIndex]) + " (" + v + "%)"; } } };
        } else if (cfg.type === "heatmap") {
            var days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
            var rows = cfg.rows || [];
            var xs = cfg.xLabels || [];
            o.series = rows.map(function (r, i) {
                return { name: days[i] || String(i), data: r.map(function (v, h) { return { x: xs[h] || String(h), y: Number(v || 0) }; }) };
            }).reverse();
            var hue = (cfg.colors && cfg.colors[0]) || "#7C3EE2";
            o.colors = [hue];
            o.legend = { show: false };
            o.stroke = { width: 3, colors: [inverse ? INK : "#fff"] };
            o.plotOptions = { heatmap: { radius: 4, shadeIntensity: 0.9, useFillColorAsStroke: false,
                colorScale: { ranges: [{ from: 0, to: 0, color: inverse ? "#253E5B" : "#EDF0F3", name: "none" }] } } };
            o.xaxis = { type: "category", axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { fontSize: "10px" }, rotate: 0, hideOverlappingLabels: true } };
            o.yaxis = { labels: { style: { fontSize: "11px" } } };
            o.tooltip = { theme: inverse ? "dark" : "light", y: { formatter: function (v) { return fmtInt(v) + " entries"; } } };
            o.grid = { show: false, padding: { left: 0, right: 0 } };
        } else {
            o.series = cfg.series;
            o.xaxis = {
                categories: cfg.labels || [],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: inverse ? "rgba(255,255,255,.72)" : SLATE, fontSize: "11px" } }
            };
            o.yaxis = { labels: { formatter: fmt, style: { colors: inverse ? "rgba(255,255,255,.72)" : SLATE, fontSize: "11px" } } };
            if (cfg.type === "area") {
                o.stroke = { curve: "smooth", width: 2 };
                o.fill = { type: "gradient", gradient: { opacityFrom: 0.22, opacityTo: 0.02 } };
            } else if (cfg.type === "line") {
                o.stroke = { curve: "smooth", width: 2 };
            } else if (cfg.type === "bar") {
                o.plotOptions = { bar: { borderRadius: 4, borderRadiusApplication: "end", columnWidth: "48%" } };
                o.stroke = { show: false };
            }
        }
        try { new ApexCharts(el, o).render(); } catch (e) { /* keep page alive */ }
    }

    /* Segmented toggles (cdp_dashToggle): show the pane whose data-swl-key matches. */
    function bindToggles() {
        var groups = document.querySelectorAll("[data-swl-toggle]");
        for (var g = 0; g < groups.length; g++) {
            groups[g].addEventListener("click", function (ev) {
                var btn = ev.target.closest(".swl-toggle__btn");
                if (!btn) { return; }
                var id = this.getAttribute("data-swl-toggle"), key = btn.getAttribute("data-swl-key");
                var btns = this.querySelectorAll(".swl-toggle__btn");
                for (var i = 0; i < btns.length; i++) { btns[i].classList.toggle("is-active", btns[i] === btn); }
                var panes = document.querySelectorAll('[data-swl-pane="' + id + '"]');
                for (var p = 0; p < panes.length; p++) { panes[p].hidden = panes[p].getAttribute("data-swl-key") !== key; }
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        (window.cdpDashCharts || []).forEach(render);
        bindToggles();
    });
})();
