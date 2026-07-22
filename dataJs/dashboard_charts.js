"use strict";

/* Shared control-panel chart renderer (ApexCharts, SwiftLane theme).
 * Pages declare window.cdpDashCharts = [{el, type, series, labels, colors,
 * money, height}] (emitted by cdp_dashChartsRender in helpers/dashboard_data.php)
 * and this file draws them all with one consistent look. */

(function () {
    var SW = ["#f2b21b", "#ef2628", "#111111", "#36bea6", "#2962ff", "#7460ee", "#e67e22", "#17a1e6"];

    function fmtMoney(v) {
        var cur = window.cdpDashCurrency || "$";
        return cur + " " + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtInt(v) { return Number(v || 0).toLocaleString(); }

    function baseOptions(cfg) {
        var money = !!cfg.money;
        var fmt = money ? fmtMoney : fmtInt;
        return {
            chart: {
                type: cfg.type === "donut" ? "donut" : cfg.type,
                height: cfg.height || 300,
                fontFamily: "'Public Sans', sans-serif",
                toolbar: { show: false },
                animations: { speed: 500 }
            },
            colors: (cfg.colors && cfg.colors.length ? cfg.colors : SW),
            dataLabels: { enabled: false },
            grid: { borderColor: "rgba(15,23,42,0.08)", strokeDashArray: 4 },
            legend: { position: "bottom", fontSize: "12px" },
            tooltip: { y: { formatter: fmt } },
            noData: { text: "No data yet" }
        };
    }

    function render(cfg) {
        var el = document.querySelector(cfg.el);
        if (!el || typeof ApexCharts === "undefined") { return; }
        var o = baseOptions(cfg);
        var money = !!cfg.money;
        var fmt = money ? fmtMoney : fmtInt;

        if (cfg.type === "donut") {
            o.series = cfg.series;                       // plain number array
            o.labels = cfg.labels || [];
            o.stroke = { width: 2, colors: ["#fff"] };
            o.plotOptions = { pie: { donut: { size: "68%" } } };
        } else {
            o.series = cfg.series;                       // [{name, data}]
            o.xaxis = {
                categories: cfg.labels || [],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: "#64748b" } }
            };
            o.yaxis = { labels: { formatter: fmt, style: { colors: "#64748b" } } };
            if (cfg.type === "area") {
                o.stroke = { curve: "smooth", width: 2.5 };
                o.fill = { type: "gradient", gradient: { opacityFrom: 0.28, opacityTo: 0.03 } };
            } else if (cfg.type === "line") {
                o.stroke = { curve: "smooth", width: 2.5 };
            } else if (cfg.type === "bar") {
                o.plotOptions = { bar: { borderRadius: 5, columnWidth: "55%" } };
                o.stroke = { show: false };
            }
        }
        try { new ApexCharts(el, o).render(); } catch (e) { /* keep page alive */ }
    }

    document.addEventListener("DOMContentLoaded", function () {
        (window.cdpDashCharts || []).forEach(render);
    });
})();
