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
            $("#loader").fadeOut('slow');
        },
        error: function () { $("#loader").fadeOut('slow'); }
    });
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
