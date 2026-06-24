"use strict";
/* ===========================================================================
   Pickup Aging — shared by the admin dashboard popup and the dedicated page.
   - Dashboard: when window.cdpPaDashboard is set, check for packages aged
     >= 2 weeks and, if any, prompt the admin to notify their senders.
   - Page: "Notify senders for selected" collects the checked rows.
   Both POST to the same notify action; ajax needs no user gesture, so the
   plain .then() confirm flow is fine (no window.open here).
   =========================================================================== */

var PA_AJAX = "./ajax/pickup_aging/pickup_aging_ajax.php";

function cdpPaNotify(orderIds, onDone) {
    if (!orderIds || !orderIds.length) return;
    $.ajax({
        url: PA_AJAX,
        method: "POST",
        data: { action: "notify", order_ids: JSON.stringify(orderIds) },
        dataType: "json",
        success: function (r) {
            if (r && r.ok) {
                Swal.fire({
                    icon: "success",
                    title: "Senders notified",
                    html: "<b>" + (r.updated || 0) + "</b> package(s) set to Pending Collection, "
                        + "<b>" + (r.sent || 0) + "</b> message(s) sent"
                        + (r.skipped ? ", " + r.skipped + " skipped" : "") + "."
                });
                if (typeof onDone === "function") onDone();
            } else {
                Swal.fire({ icon: "error", text: "Could not notify senders." });
            }
        },
        error: function () { Swal.fire({ icon: "error", text: "Request failed." }); }
    });
}

/* ---- Dashboard popup: only when the dashboard opted in ---- */
$(function () {
    if (!window.cdpPaDashboard) return;
    $.getJSON(PA_AJAX + "?action=pending", function (r) {
        if (!r || !r.ok || !r.count) return;
        var ids = r.packages.map(function (p) { return p.order_id; });
        Swal.fire({
            title: "Packages not picked up",
            html: "<b>" + r.count + "</b> package(s) have not been picked up for two weeks now. Notify the senders?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, notify",
            cancelButtonText: "Cancel",
            reverseButtons: true
        }).then(function (res) {
            if (res.isConfirmed) cdpPaNotify(ids);
        });
    });
});

/* ---- Page: notify the senders of the selected packages ---- */
function cdpPaNotifySelected() {
    var ids = [];
    $(".pa-check:checked").each(function () { ids.push(parseInt($(this).val(), 10)); });
    if (!ids.length) {
        Swal.fire({ icon: "warning", text: "Select at least one package." });
        return;
    }
    Swal.fire({
        title: "Notify senders",
        html: "Notify the senders of <b>" + ids.length + "</b> package(s) and set them to Pending Collection?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Notify",
        cancelButtonText: "Cancel",
        reverseButtons: true
    }).then(function (res) {
        if (res.isConfirmed) {
            cdpPaNotify(ids, function () { setTimeout(function () { location.reload(); }, 1400); });
        }
    });
}
