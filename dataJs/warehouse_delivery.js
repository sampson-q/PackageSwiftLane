"use strict";
// ============================================================================
// Warehouse Delivery — tiered (FS-style) accordion + delivery actions.
// Consolidation -> customer -> package -> item, lazy-loaded per tier. Delivery
// is by package only; "Deliver All" covers a customer's cleared packages; undo
// reopens a delivered package. The server (warehouse_delivery_ajax.php) is the
// authoritative permission gate — buttons only appear when allowed.
// ============================================================================

var WD_URL = "./ajax/courier/warehouse_delivery_ajax.php";
var wdTimer = null;
var wdReopen = null; // {cid, sid} to auto-expand after a full reload

$(function () {
    cdp_load();
    $("#search").on("input", function () {
        clearTimeout(wdTimer);
        wdTimer = setTimeout(cdp_load, 300);
    });
});

function wdEsc(s) { return $("<div>").text(s == null ? "" : s).html(); }
function wdResolved() { return $.Deferred().resolve().promise(); }

// ---- Level 1: consolidation list -------------------------------------------
function cdp_load() {
    $("#loader").fadeIn("fast");
    return $.ajax({
        url: WD_URL,
        data: { action: "list", search: $("#search").val() || "" }
    }).done(function (html) {
        $(".outer_div").html(html);
        $("#loader").fadeOut("fast");
        if (wdReopen && wdReopen.cid) { wdReopenAfterLoad(); }
    }).fail(function () {
        $("#loader").fadeOut("fast");
        $(".outer_div").html('<div class="text-danger p-3">Could not load. Please retry.</div>');
    });
}

// ---- Accordion toggles ------------------------------------------------------
function wdToggle(header, event, level) {
    var $h = $(header);
    if (level === "consol") {
        var $card = $h.closest(".wd-consol-card");
        var $body = $card.children(".wd-consol-body");
        var wasOpen = $body.is(":visible");
        $body.slideToggle(120);
        $h.toggleClass("wd-open", !wasOpen);
        if (!wasOpen) { wdLoadCustomers($card.data("cid")); }
    } else if (level === "cust") {
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

// ---- Level 2/3 lazy loaders -------------------------------------------------
function wdLoadCustomers(cid, force) {
    var $box = $(".wd-customers[data-cid='" + cid + "']");
    if (!$box.length) { return wdResolved(); }
    if (String($box.data("loaded")) === "1" && !force) { return wdResolved(); }
    return $.ajax({ url: WD_URL, data: { action: "customers", consolidate_id: cid } })
        .done(function (html) { $box.html(html).attr("data-loaded", "1").data("loaded", "1"); })
        .fail(function () { $box.html('<div class="text-danger small">Could not load customers.</div>'); });
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

// After a write, reload the list and re-expand the consolidation/customer that
// was acted on, so the refreshed state (badges, chips, buttons) shows in place.
function wdAfterAction(cid, sid, msg) {
    wdReopen = { cid: cid, sid: sid };
    cdp_load();
    Swal.fire({ icon: "success", text: msg, timer: 1500, showConfirmButton: false });
}

function wdReopenAfterLoad() {
    var t = wdReopen; wdReopen = null;
    var $ch = $(".wd-consol-card[data-cid='" + t.cid + "'] .wd-consol-header").first();
    if (!$ch.length) { return; }
    $ch.closest(".wd-consol-card").children(".wd-consol-body").show();
    $ch.addClass("wd-open");
    wdLoadCustomers(t.cid, true).done(function () {
        if (!t.sid) { return; }
        var $custH = $(".wd-cust-card[data-cid='" + t.cid + "'][data-sid='" + t.sid + "'] .wd-cust-header").first();
        if (!$custH.length) { return; }
        $custH.closest(".wd-cust-card").children(".wd-cust-body").show();
        $custH.addClass("wd-open");
        wdLoadPackages(t.cid, t.sid, true);
    });
}

// ---- Delivery actions -------------------------------------------------------
function wdDeliverOne(btn) {
    var $b = $(btn);
    var no = String($b.data("no")), track = $b.data("track"), cid = $b.data("cid"), sid = $b.data("sid");
    Swal.fire({
        title: "Deliver package?",
        html: "Mark <b>" + wdEsc(track) + "</b> as delivered?",
        icon: "question", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, deliver", confirmButtonColor: "#1b8a5a",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "deliver_package", order_no: no, consolidate_id: cid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        wdAfterAction(cid, sid, "Package delivered.");
    });
}

function wdDeliverUser(btn) {
    var $b = $(btn);
    var cid = $b.data("cid"), sid = $b.data("sid"), name = $b.data("name"), ready = $b.data("ready");
    Swal.fire({
        title: "Deliver all cleared packages?",
        html: "Deliver <b>" + ready + "</b> cleared package(s) for <b>" + wdEsc(name) + "</b>?<br>" +
              "<span class='text-muted' style='font-size:12px;'>Only cleared, undelivered packages are delivered.</span>",
        icon: "question", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, deliver all", confirmButtonColor: "#1b8a5a",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "deliver_user", consolidate_id: cid, sender_id: sid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        var v = r.value || {};
        var msg = (v.delivered || 0) + " package(s) delivered" + ((v.skipped > 0) ? ", " + v.skipped + " skipped" : "") + ".";
        wdAfterAction(cid, sid, msg);
    });
}

function wdUndoOne(btn) {
    var $b = $(btn);
    var no = String($b.data("no")), track = $b.data("track"), cid = $b.data("cid"), sid = $b.data("sid");
    Swal.fire({
        title: "Undo delivery?",
        html: "Reopen <b>" + wdEsc(track) + "</b>?<br>" +
              "<span class='text-muted' style='font-size:12px;'>Reverts it to In-Warehouse and reopens the consolidation if it was closed as Delivered.</span>",
        icon: "warning", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, undo", confirmButtonColor: "#c0392b",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () { return wdPost({ action: "undo_package", order_no: no, consolidate_id: cid }); }
    }).then(function (r) {
        if (!r.isConfirmed) { return; }
        wdAfterAction(cid, sid, "Delivery reversed.");
    });
}
