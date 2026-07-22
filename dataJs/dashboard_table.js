"use strict";

/* Shared control-panel table loader. Pages set:
 *   window.cdpDashTable = { url: './ajax/...', target: '.outer_div',
 *                           searchEl: '#search_shipment' (optional) }
 * Pagination links emitted by helpers/pagination.php call cdp_load(n),
 * so the global name must stay. */

function cdp_load(page) {
    var cfg = window.cdpDashTable;
    if (!cfg) { return; }
    var params = { page: page };
    if (cfg.searchEl && $(cfg.searchEl).length) { params.search = $(cfg.searchEl).val(); }
    if ($("#per_page").length) { params.per_page = $("#per_page").val(); }
    if ($("#loader").length) { $("#loader").fadeIn("slow"); }
    $.ajax({
        url: cfg.url,
        data: params,
        success: function (data) {
            $(cfg.target).html(data).fadeIn("slow");
        },
        complete: function () {
            if ($("#loader").length) { $("#loader").fadeOut("slow"); }
        }
    });
}

$(function () {
    if (window.cdpDashTable) { cdp_load(1); }
});
