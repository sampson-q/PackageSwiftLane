"use strict";
// Warehouse Delivery — only cleared-for-delivery packages, grouped by customer.
// Reuses ajax/courier/warehouse_deliver_ajax.php (preview -> deliver, status 8).

$(function () {
    cdp_load(1);
    $("#search").on("input", cdpWhDebounced);
});

var cdpWhTimer = null;
function cdpWhDebounced() {
    clearTimeout(cdpWhTimer);
    cdpWhTimer = setTimeout(function () { cdp_load(1); }, 300);
}

function cdp_load() {
    $("#loader").fadeIn("fast");
    $.ajax({
        url: "./ajax/courier/warehouse_delivery_ajax.php",
        data: { search: $("#search").val() || "", status_courier: $("#status_courier").val() || 0 },
        success: function (html) { $(".outer_div").html(html); $("#loader").fadeOut("fast"); },
        error: function () { $("#loader").fadeOut("fast"); }
    });
}

// Selection helpers.
function cdpWhToggleCustomer(box) {
    $(box).closest(".wh-cust-card").find(".wh-pkg").prop("checked", box.checked);
}
function cdpWhSelected() {
    var nos = [];
    $(".wh-pkg:checked").each(function () { nos.push($(this).val()); });
    return nos;
}

function cdpWhDeliverOne(btn) { cdpWhDeliver([$(btn).data("no")]); }
function cdpWhDeliverCustomer(btn) { cdpWhDeliver($(btn).data("nos") || []); }
function cdpWhDeliverSelected() {
    var nos = cdpWhSelected();
    if (!nos.length) { Swal.fire({ icon: "warning", text: "Tick at least one package first.", confirmButtonText: "Ok" }); return; }
    cdpWhDeliver(nos);
}

function cdpWhDeliver(orderNos) {
    orderNos = (orderNos || []).map(String);
    if (!orderNos.length) return;
    $.ajax({
        url: "./ajax/courier/warehouse_deliver_ajax.php",
        method: "POST",
        data: { action: "preview", order_nos: JSON.stringify(orderNos) },
        dataType: "json",
        success: function (r) {
            if (!r || !r.ok || !r.packages || !r.packages.length) {
                Swal.fire({ icon: "error", text: (r && r.message) ? r.message : "Could not load package details.", confirmButtonText: "Ok" });
                return;
            }
            var rows = r.packages.map(function (p) {
                var warn = p.already_delivered ? ' <span style="color:#dc3545;">(already delivered)</span>' : "";
                return '<div style="text-align:left;border-bottom:1px solid #eee;padding:6px 2px;">' +
                    "<b>" + $("<div>").text(p.tracking).html() + "</b>" + warn + "<br>" +
                    '<span style="font-size:12px;color:#666;">' + $("<div>").text(p.name).html() +
                    " &middot; Carrier: " + $("<div>").text(p.carrier).html() + "</span></div>";
            }).join("");
            Swal.fire({
                title: "Confirm Delivery",
                html: '<div style="max-height:260px;overflow-y:auto;">' + rows + "</div>" +
                      "<p class='mt-2 mb-0'>Mark " + (r.packages.length === 1 ? "this package" : "these " + r.packages.length + " packages") + " as delivered?</p>",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Yes, deliver",
                reverseButtons: true,
                showLoaderOnConfirm: true,
                allowOutsideClick: function () { return !Swal.isLoading(); },
                preConfirm: function () {
                    return $.ajax({
                        url: "./ajax/courier/warehouse_deliver_ajax.php",
                        method: "POST",
                        data: { action: "deliver", order_nos: JSON.stringify(orderNos) },
                        dataType: "json"
                    }).then(function (dr) {
                        if (!dr || !dr.ok) { Swal.showValidationMessage((dr && dr.message) || "Could not mark as delivered."); return false; }
                        return dr;
                    }, function () { Swal.showValidationMessage("Request failed."); return false; });
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;
                var n = (result.value && result.value.delivered) || 0;
                var sk = (result.value && result.value.skipped_uncleared) || 0;
                var msg = n + " package(s) marked as delivered.";
                if (sk > 0) { msg += " " + sk + " skipped (not cleared for delivery)."; }
                Swal.fire({ icon: "success", text: msg, confirmButtonText: "Ok" })
                    .then(function () { cdp_load(1); });
            });
        },
        error: function () { Swal.fire({ icon: "error", text: "Request failed.", confirmButtonText: "Ok" }); }
    });
}
