"use strict";

$(function () {
    var start = moment().startOf('month');
    var end = moment().endOf('month');

    $('#daterange').daterangepicker({
        startDate: start,
        endDate: end,
        autoUpdateInput: true,
        locale: { format: 'Y/M/D', separator: ' - ', firstDay: 1 },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }).on('apply.daterangepicker change', function () { cdp_load(1); });

    cdp_load(1);
});

var cdp_txTimer = null;
function cdp_txDebounced() {
    clearTimeout(cdp_txTimer);
    cdp_txTimer = setTimeout(function () { cdp_load(1); }, 300);
}

function cdp_load(page) {
    var params = {
        page: page,
        search: $("#search").val() || "",
        range: $("#daterange").val() || "",
        mode: $("#f_mode").val() || "",
        status: $("#f_status").val() || "",
        per_page: $("#per_page").val() || "25"
    };
    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/reports/transactions_ajax.php',
        data: params,
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
            cdpTxApplyCurrency();     // render amounts in the chosen currency
            $("#loader").fadeOut('slow');
        },
        error: function () { $("#loader").fadeOut('slow'); }
    });
}

// ---- Currency toggle (audit: GHS shows the rate each transaction used) ------
var cdpTxCur = 'ghs';
function cdpTxSetCurrency(c) {
    cdpTxCur = (c === 'usd') ? 'usd' : 'ghs';
    $("#tx_cur_ghs").toggleClass('btn-dark', cdpTxCur === 'ghs').toggleClass('btn-outline-dark', cdpTxCur !== 'ghs');
    $("#tx_cur_usd").toggleClass('btn-dark', cdpTxCur === 'usd').toggleClass('btn-outline-dark', cdpTxCur !== 'usd');
    cdpTxApplyCurrency();
}
function cdpTxFmt(n) {
    return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function cdpTxApplyCurrency() {
    var sym = (cdpTxCur === 'usd') ? '$' : '₵';
    $(".tx-money").each(function () {
        var v = (cdpTxCur === 'usd') ? $(this).data("usd") : $(this).data("ghs");
        $(this).text(sym + cdpTxFmt(v));
    });
    // The per-transaction rate only matters when reading GHS figures.
    $(".tx-rate-col").toggle(cdpTxCur === 'ghs');
}
