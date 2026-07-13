"use strict";

$(function () {
    $('#daterange').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        autoUpdateInput: true,
        locale: { format: 'Y/M/D', separator: ' - ', firstDay: 1 },
        ranges: {
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Year': [moment().startOf('year'), moment().endOf('year')]
        }
    }).on('apply.daterangepicker change', function () { cdpFoLoad(); });

    cdpFoLoad();
});

function cdpFoLoad() {
    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/reports/financial_overview_ajax.php',
        data: { range: $("#daterange").val() || "" },
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
            cdpFoApplyCurrency();
            cdpFoRenderCharts();
            $("#loader").fadeOut('slow');
        },
        error: function () { $("#loader").fadeOut('slow'); }
    });
}

// ---- Charts (ApexCharts), rendered after each AJAX load. --------------------
var cdpFoChartObjs = [];
function cdpFoRenderCharts() {
    // Tear down any previous instances so a reload doesn't stack canvases.
    cdpFoChartObjs.forEach(function (c) { try { c.destroy(); } catch (e) {} });
    cdpFoChartObjs = [];
    if (typeof ApexCharts === "undefined") { return; }

    var raw = $("#fo_chart_data").text();
    if (!raw) { return; }
    var d;
    try { d = JSON.parse(raw); } catch (e) { return; }

    var money = function (v) { return "₵" + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); };

    // 1) Billed vs Received — area/line trend.
    if (d.trend && document.querySelector("#fo_chart_trend")) {
        var t = new ApexCharts(document.querySelector("#fo_chart_trend"), {
            chart: { type: "area", height: 280, toolbar: { show: false }, fontFamily: "inherit" },
            series: [
                { name: "Billed", data: d.trend.billed || [] },
                { name: "Received", data: d.trend.received || [] }
            ],
            xaxis: { categories: d.trend.labels || [] },
            colors: ["#536dfe", "#1b8a5a"],
            dataLabels: { enabled: false },
            stroke: { curve: "smooth", width: 2 },
            fill: { type: "gradient", gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            yaxis: { labels: { formatter: function (v) { return money(v); } } },
            tooltip: { y: { formatter: function (v) { return money(v); } } },
            legend: { position: "top" }
        });
        t.render(); cdpFoChartObjs.push(t);
    }

    // 2) Money in by method — donut.
    if (d.method && (d.method.values || []).length && document.querySelector("#fo_chart_method")) {
        var m = new ApexCharts(document.querySelector("#fo_chart_method"), {
            chart: { type: "donut", height: 280, fontFamily: "inherit" },
            series: d.method.values || [],
            labels: d.method.labels || [],
            colors: ["#1b8a5a", "#536dfe", "#e67e22", "#7460ee", "#00c1d4"],
            dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + "%"; } },
            tooltip: { y: { formatter: function (v) { return money(v); } } },
            legend: { position: "bottom" }
        });
        m.render(); cdpFoChartObjs.push(m);
    } else if (document.querySelector("#fo_chart_method")) {
        $("#fo_chart_method").html('<div class="text-muted small py-4 text-center">No receipts in this period.</div>');
    }

    // 3) Receivables aging — bar.
    if (d.aging && document.querySelector("#fo_chart_aging")) {
        var a = new ApexCharts(document.querySelector("#fo_chart_aging"), {
            chart: { type: "bar", height: 170, toolbar: { show: false }, fontFamily: "inherit" },
            series: [{ name: "Outstanding", data: d.aging.values || [] }],
            xaxis: { categories: d.aging.labels || [] },
            colors: ["#e74c3c"],
            plotOptions: { bar: { borderRadius: 3, columnWidth: "55%", distributed: false } },
            dataLabels: { enabled: false },
            yaxis: { labels: { formatter: function (v) { return money(v); } } },
            tooltip: { y: { formatter: function (v) { return money(v); } } },
            legend: { show: false }
        });
        a.render(); cdpFoChartObjs.push(a);
    }
}

// ---- Currency toggle (GHS actual money received/owed; USD via stored rate) --
var cdpFoCur = 'ghs';
function cdpFoSetCurrency(c) {
    cdpFoCur = (c === 'usd') ? 'usd' : 'ghs';
    $("#fo_cur_ghs").toggleClass('btn-dark', cdpFoCur === 'ghs').toggleClass('btn-outline-dark', cdpFoCur !== 'ghs');
    $("#fo_cur_usd").toggleClass('btn-dark', cdpFoCur === 'usd').toggleClass('btn-outline-dark', cdpFoCur !== 'usd');
    cdpFoApplyCurrency();
}
function cdpFoFmt(n) {
    return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function cdpFoApplyCurrency() {
    var sym = (cdpFoCur === 'usd') ? '$' : '₵';
    $(".fo-money").each(function () {
        var v = (cdpFoCur === 'usd') ? $(this).data("usd") : $(this).data("ghs");
        $(this).text(sym + cdpFoFmt(v));
    });
}
