"use strict";
// ============================================================================
// Warehouse Delivery — mirrors the Financial Sheet's two modes:
//   • list page: search + consolidation cards that link out to their own page.
//   • single-consolidation page (window.WD_CID set): the customer -> package ->
//     item accordion + delivery actions.
// Delivery is by package only; "Deliver All" covers a customer's cleared
// packages; undo reopens a delivered package. The server
// (warehouse_delivery_ajax.php) is the authoritative permission gate.
// ============================================================================

var WD_URL = "./ajax/courier/warehouse_delivery_ajax.php";
var wdTimer = null;

$(function () {
    // Two modes, exactly like the Financial Sheet.
    if (window.WD_CID) {
        wdReloadCustomers(window.WD_CID);
    } else {
        cdp_load();
        $("#search").on("input", function () {
            clearTimeout(wdTimer);
            wdTimer = setTimeout(cdp_load, 300);
        });
    }
});

function wdEsc(s) { return $("<div>").text(s == null ? "" : s).html(); }
function wdResolved() { return $.Deferred().resolve().promise(); }

// ---- List page: consolidation cards (each links to its own page) -----------
function cdp_load() {
    $("#loader").fadeIn("fast");
    return $.ajax({
        url: WD_URL,
        data: { action: "list", search: $("#search").val() || "" }
    }).done(function (html) {
        $(".outer_div").html(html);
        $("#loader").fadeOut("fast");
    }).fail(function () {
        $("#loader").fadeOut("fast");
        $(".outer_div").html('<div class="text-danger p-3">Could not load. Please retry.</div>');
    });
}

// ---- Single-consolidation page: load / reload the customers accordion ------
function wdReloadCustomers(cid, reopenSid) {
    var $box = $(".wd-customers[data-cid='" + cid + "']");
    if (!$box.length) { return wdResolved(); }
    $("#loader").fadeIn("fast");
    return $.ajax({ url: WD_URL, data: { action: "customers", consolidate_id: cid } })
        .done(function (html) {
            $box.html(html).attr("data-loaded", "1").data("loaded", "1");
            $("#loader").fadeOut("fast");
            if (reopenSid) { wdOpenCustomer(cid, reopenSid); }
        })
        .fail(function () {
            $("#loader").fadeOut("fast");
            $box.html('<div class="text-danger small">Could not load customers.</div>');
        });
}

function wdOpenCustomer(cid, sid) {
    var $h = $(".wd-cust-card[data-cid='" + cid + "'][data-sid='" + sid + "'] .wd-cust-header").first();
    if (!$h.length) { return; }
    $h.closest(".wd-cust-card").children(".wd-cust-body").show();
    $h.addClass("wd-open");
    wdLoadPackages(cid, sid, true);
}

// ---- Accordion toggles (customer -> packages, package -> items) ------------
function wdToggle(header, event, level) {
    var $h = $(header);
    if (level === "cust") {
        var $c = $h.closest(".wd-cust-card");
        var $cb = $c.children(".wd-cust-body");
        var custOpen = $cb.is(":visible");
        $cb.slideToggle(120);
        $h.toggleClass("wd-open", !custOpen);
        if (!custOpen) { wdLoadPackages($c.data("cid"), $c.data("sid")); }
    } else if (level === "pkg") {
        var $p = $h.closest(".wd-pkg-card");
        var $pb = $p.children(".wd-pkg-body");
        var pkgOpen = $pb.is(":visible");
        $pb.slideToggle(120);
        $h.toggleClass("wd-open", !pkgOpen);
    }
}

function wdLoadPackages(cid, sid, force) {
    var $box = $(".wd-packages[data-cid='" + cid + "'][data-sid='" + sid + "']");
    if (!$box.length) { return wdResolved(); }
    if (String($box.data("loaded")) === "1" && !force) { return wdResolved(); }
    return $.ajax({ url: WD_URL, data: { action: "packages", consolidate_id: cid, sender_id: sid } })
        .done(function (html) { $box.html(html).attr("data-loaded", "1").data("loaded", "1"); })
        .fail(function () { $box.html('<div class="text-danger small">Could not load packages.</div>'); });
}

// ---- POST helper (used inside SweetAlert preConfirm) ------------------------
function wdPost(data) {
    return $.ajax({ url: WD_URL, method: "POST", data: data, dataType: "json" })
        .then(function (dr) {
            if (!dr || !dr.ok) {
                Swal.showValidationMessage((dr && dr.message) || "Action failed.");
                return $.Deferred().reject();
            }
            return dr;
        }, function () {
            Swal.showValidationMessage("Request failed.");
            return $.Deferred().reject();
        });
}

// After a write, reload the consolidation's customers and re-open the one acted
// on, so refreshed state (badges, chips, buttons) shows in place. If the whole
// consolidation just closed as Delivered, refresh the page header too.
function wdAfterAction(cid, sid, msg, summary) {
    Swal.fire({ icon: "success", text: msg, timer: 1500, showConfirmButton: false });
    if (summary) {
        if (summary.prog_html) { $("#wd-hdr-prog").html(summary.prog_html); }
        if (summary.right_html) { $("#wd-hdr-right").html(summary.right_html); }
    }
    wdReloadCustomers(cid, sid);
}

// ---- Delivery actions -------------------------------------------------------
function wdDeliverOne(btn) {
    var $b = $(btn);
    var no = String($b.data("no")), track = $b.data("track"), cid = $b.data("cid"), sid = $b.data("sid");
    Swal.fire({
        title: "Deliver Package?",
        html: "Mark <b>" + wdEsc(track) + "</b> as delivered?",
        icon: "question", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, Deliver", confirmButtonColor: "#1b8a5a",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "deliver_package", order_no: no, consolidate_id: cid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        wdAfterAction(cid, sid, "Package delivered.", r.value && r.value.summary);
    });
}

function wdDeliverUser(btn) {
    var $b = $(btn);
    var cid = $b.data("cid"), sid = $b.data("sid"), name = $b.data("name"), ready = $b.data("ready");
    Swal.fire({
        title: "Deliver All Cleared Packages?",
        html: "Deliver <b>" + ready + "</b> cleared package(s) for <b>" + wdEsc(name) + "</b>?<br>" +
              "<span class='text-muted' style='font-size:12px;'>Only cleared, undelivered packages are delivered.</span>",
        icon: "question", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, Deliver All", confirmButtonColor: "#1b8a5a",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "deliver_user", consolidate_id: cid, sender_id: sid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        var v = r.value || {};
        var msg = (v.delivered || 0) + " package(s) delivered" + ((v.skipped > 0) ? ", " + v.skipped + " skipped" : "") + ".";
        wdAfterAction(cid, sid, msg, v.summary);
    });
}

function wdUndoOne(btn) {
    var $b = $(btn);
    var no = String($b.data("no")), track = $b.data("track"), cid = $b.data("cid"), sid = $b.data("sid");
    Swal.fire({
        title: "Undo Delivery?",
        html: "Reopen <b>" + wdEsc(track) + "</b>?<br>" +
              "<span class='text-muted' style='font-size:12px;'>Reverts it to In-Warehouse and reopens the consolidation if it was closed as Delivered.</span>",
        icon: "warning", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, Undo", confirmButtonColor: "#c0392b",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "undo_package", order_no: no, consolidate_id: cid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        wdAfterAction(cid, sid, "Delivery reversed.", r.value && r.value.summary);
    });
}
