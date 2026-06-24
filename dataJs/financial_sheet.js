"use strict";

/* ===========================================================================
   FINANCIAL SHEET
   Nested accordions: consolidation -> packages -> items (plain jQuery show/hide,
   no Bootstrap collapse). Opening a consolidation tints the whole card and shows
   its packages; opening a package tints it and reveals its items + change log.
   Each item's custom price is entered in USD or GHS via a tiny per-input toggle;
   storage is always USD.
   =========================================================================== */

var FS_AJAX = "./ajax/reports/financial_sheet_ajax.php";
var fsOpenLocks = {};      // oid -> true (packages we currently hold the lock for)
var fsHeartbeat = null;

function fsRate() { return Number(window.FS_RATE) > 0 ? Number(window.FS_RATE) : 1; }
function fsMoney(usd) { return "$" + (Number(usd) || 0).toFixed(2); }

$(function () {
    $("#fs_rate_label").text(fsRate().toFixed(2));
    fsLoad();

    $("#fs_search").on("keyup", function (e) { if (e.key === "Enter") fsLoad(); });
    $("#fs_search_btn").on("click", fsLoad);

    // Release every lock we hold when leaving the page.
    window.addEventListener("beforeunload", function () {
        Object.keys(fsOpenLocks).forEach(function (oid) {
            navigator.sendBeacon
                ? navigator.sendBeacon(FS_AJAX + "?action=unlock&order_id=" + oid)
                : $.ajax({ url: FS_AJAX, data: { action: "unlock", order_id: oid }, async: false });
        });
    });
});

function fsLoad() {
    var q = $("#fs_search").val() || "";
    $("#loader").fadeIn("fast");
    $.ajax({
        url: FS_AJAX,
        data: { action: "list", q: q },
        success: function (html) { $(".outer_div").html(html); },
        complete: function () { $("#loader").fadeOut("fast"); }
    });
}

/* ----------------------- Accordion: consolidation ----------------------- */
// One consolidation open at a time. Opening loads + shows its packages and
// tints the whole card so the packages sit on the active background.
function fsToggleConsolidation(header, cid) {
    var $card = $(header).closest(".fs-consol-card");
    var $body = $(".fs-consol-body[data-cid='" + cid + "']");
    var willOpen = $body.is(":hidden");

    $(".fs-consol-body:visible").each(function () {
        if (String($(this).data("cid")) !== String(cid)) {
            $(this).find(".fs-pkg-body:visible").each(function () { fsStopLock($(this).data("oid")); });
            $(this).slideUp(120);
        }
    });
    $(".fs-consol-card.fs-active, .fs-pkg-card.fs-active").removeClass("fs-active");

    if (!willOpen) {
        $body.find(".fs-pkg-body:visible").each(function () { fsStopLock($(this).data("oid")); });
        $body.slideUp(120);
        return;
    }

    $card.addClass("fs-active");
    var $box = $body.find(".fs-packages").first();
    $body.slideDown(120);

    if ($box.attr("data-loaded") !== "1") {
        $box.attr("data-loaded", "1");
        $.ajax({
            url: FS_AJAX,
            data: { action: "packages", consolidate_id: cid },
            success: function (html) { $box.html(html); },
            error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load packages.</div>'); }
        });
    }
}

/* -------------------------- Accordion: package -------------------------- */
function fsTogglePackage(header, oid) {
    var $card = $(header).closest(".fs-pkg-card");
    var $body = $(".fs-pkg-body[data-oid='" + oid + "']");
    var $box  = $body.find(".fs-items").first();
    var willOpen = $body.is(":hidden");

    // One package open at a time within a consolidation (releases sibling locks).
    var $container = $body.closest(".fs-packages");
    $container.find(".fs-pkg-body:visible").each(function () {
        if (String($(this).data("oid")) !== String(oid)) {
            fsStopLock($(this).data("oid"));
            $(this).slideUp(120);
        }
    });
    $container.find(".fs-pkg-card.fs-active").removeClass("fs-active");

    if (!willOpen) {
        $body.slideUp(120);
        fsStopLock(oid);
        return;
    }

    $card.addClass("fs-active");
    $body.slideDown(120);

    if ($box.attr("data-loaded") !== "1") {
        $box.attr("data-loaded", "1");
        $.ajax({
            url: FS_AJAX,
            data: { action: "items", order_id: oid },
            success: function (html) {
                $box.html(html);
                if ($box.find(".fs-save").length) fsStartLock(oid);
            },
            error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load items.</div>'); }
        });
    } else if ($box.find(".fs-save").length) {
        fsStartLock(oid); // re-acquire on re-open
    }
}

/* ------------------------------ Locking -------------------------------- */
function fsStartLock(oid) {
    fsOpenLocks[oid] = true;
    if (!fsHeartbeat) {
        fsHeartbeat = setInterval(function () {
            Object.keys(fsOpenLocks).forEach(function (id) {
                $.ajax({
                    url: FS_AJAX,
                    data: { action: "lock", order_id: id },
                    success: function (r) {
                        if (r && r.ok === false) {
                            fsStopLock(id);
                            var $items = $(".fs-pkg-body[data-oid='" + id + "'] .fs-items");
                            $items.find("input,button.fs-save,.fs-mode .btn,.fs-cur-btn").prop("disabled", true);
                            $items.prepend('<div class="alert alert-warning mb-2"><i class="fas fa-lock"></i> Lock lost — now being edited by <b>' + (r.by || "another user") + '</b>.</div>');
                        }
                    }
                });
            });
        }, 30000); // 30s; lock TTL is 120s server-side
    }
}

function fsStopLock(oid) {
    if (!fsOpenLocks[oid]) return;
    delete fsOpenLocks[oid];
    $.ajax({ url: FS_AJAX, data: { action: "unlock", order_id: oid } });
    if (Object.keys(fsOpenLocks).length === 0 && fsHeartbeat) {
        clearInterval(fsHeartbeat);
        fsHeartbeat = null;
    }
}

/* --------------------------- Item editing ------------------------------ */
function fsSetMode(iid, mode) {
    var $row    = $("tr[data-iid='" + iid + "']");
    var $weight = $row.find(".fs-weight");
    var $custom = $row.find(".fs-custom");
    var $btns   = $row.find(".fs-mode .btn");

    $row.find(".fs-mode-val").val(mode);
    $btns.eq(0).toggleClass("btn-dark", mode === "weight").toggleClass("btn-outline-dark", mode !== "weight");
    $btns.eq(1).toggleClass("btn-success", mode === "custom").toggleClass("btn-outline-success", mode !== "custom");

    if (mode === "custom") {
        $weight.val("").prop("disabled", true).attr("placeholder", "—");
        $custom.prop("disabled", false).attr("placeholder", fsItemCur(iid).toUpperCase()).focus();
        fsCustomLiveEquiv($custom[0]);
    } else {
        $custom.val("").prop("disabled", true).attr("placeholder", "—");
        $row.find(".fs-equiv").text("");
        $weight.prop("disabled", false).attr("placeholder", "weight").focus();
    }
}

/* Per-input currency toggle: switch ONE custom-price box between USD and GHS,
   converting whatever is currently typed. Storage stays USD (server converts). */
function fsItemCur(iid) { return $("tr[data-iid='" + iid + "'] .fs-custom").attr("data-cur") || "usd"; }

function fsToggleItemCur(iid, cur) {
    cur = (cur === "ghs") ? "ghs" : "usd";
    var $row = $("tr[data-iid='" + iid + "']");
    var $i   = $row.find(".fs-custom");
    var old  = $i.attr("data-cur") || "usd";

    if (old !== cur) {
        var raw = parseFloat(String($i.val() || "").replace(/,/g, ""));
        if (!isNaN(raw) && raw > 0) {
            var usd   = (old === "ghs") ? raw / fsRate() : raw;        // normalise to USD
            var shown = (cur === "ghs") ? usd * fsRate() : usd;        // re-express
            $i.val(shown.toFixed(2));
        }
        $i.attr("data-cur", cur).attr("placeholder", cur.toUpperCase());
    }
    $row.find(".fs-cur-btn").removeClass("active btn-primary").addClass("btn-outline-secondary");
    $row.find(".fs-cur-btn[data-cur='" + cur + "']").addClass("active btn-primary").removeClass("btn-outline-secondary");
    fsCustomLiveEquiv($i[0]);
}

// Live "≈ $X / ≈ ₵X" helper under a custom-price input, based on ITS toggle.
function fsCustomLiveEquiv(input) {
    var $i  = $(input);
    var $eq = $i.closest("td").find(".fs-equiv");
    var raw = parseFloat(String($i.val() || "").replace(/,/g, ""));
    if (isNaN(raw) || raw <= 0) { $eq.text(""); return; }
    if (($i.attr("data-cur") || "usd") === "ghs") {
        $eq.text("≈ $" + (raw / fsRate()).toFixed(2) + " USD");
    } else {
        $eq.text("≈ ₵" + (raw * fsRate()).toFixed(2) + " GHS");
    }
}

function fsSaveItem(oid, iid, btn) {
    var $row  = $("tr[data-iid='" + iid + "']");
    var mode  = $row.find(".fs-mode-val").val() || "weight";
    var $cust = $row.find(".fs-custom");
    var cur   = $cust.attr("data-cur") || "usd";
    var raw   = (mode === "custom") ? $cust.val() : $row.find(".fs-weight").val();
    var value = parseFloat(String(raw || "").replace(/,/g, ""));

    if (!value || value <= 0) {
        Swal.fire({ icon: "error", text: "Enter a " + (mode === "custom" ? "custom price" : "weight") + " greater than 0.", confirmButtonText: "Ok" });
        return;
    }

    $(btn).prop("disabled", true);
    $.ajax({
        url: FS_AJAX,
        method: "POST",
        // currency tells the server how to interpret a custom price (GHS -> USD).
        data: { action: "save_item", order_id: oid, order_item_id: iid, mode: mode, value: value, currency: cur },
        dataType: "json",
        success: function (r) {
            $(btn).prop("disabled", false);
            if (r && r.ok) {
                $(btn).removeClass("btn-success").addClass("btn-outline-success");
                setTimeout(function () { $(btn).removeClass("btn-outline-success").addClass("btn-success"); }, 1200);

                // Keep the item's canonical USD in sync.
                if (mode === "custom") {
                    var usd = (cur === "ghs") ? (value / fsRate()) : value;
                    $cust.attr("data-usd", usd.toFixed(2));
                } else {
                    $cust.attr("data-usd", "");
                    $row.find(".fs-equiv").text("");
                }

                // Update the package total (lives in the package header).
                if (r.total_order != null) {
                    var $pkgTotal = $(".fs-pkg-body[data-oid='" + oid + "']").closest(".fs-pkg-card").find(".fs-pkg-total");
                    $pkgTotal.attr("data-usd", r.total_order).text(fsMoney(r.total_order));
                }

                // Prepend a line to this package's change log.
                if (r.history) fsPrependHistory(oid, r.history);
            } else if (r && r.error === "locked") {
                Swal.fire({ icon: "warning", text: "This package is now being edited by " + (r.by || "another user") + ".", confirmButtonText: "Ok" });
            } else {
                Swal.fire({ icon: "error", text: (r && r.message) ? r.message : "Could not save.", confirmButtonText: "Ok" });
            }
        },
        error: function () {
            $(btn).prop("disabled", false);
            Swal.fire({ icon: "error", text: "Save failed.", confirmButtonText: "Ok" });
        }
    });
}

// Drop a freshly-recorded change at the top of the package's change log.
function fsPrependHistory(oid, h) {
    var $log = $(".fs-pkg-body[data-oid='" + oid + "'] .fs-history");
    if (!$log.length) return;
    $log.find(".fs-history-empty").remove();
    var line = '<div class="fs-hist-item"><b>' + (h.who || "Someone") + '</b> ' +
               $("<span>").text(h.what || "").html() +
               ' <span class="text-muted">— ' + (h.when || "just now") + '</span></div>';
    $log.find(".fs-history-list").prepend(line);
}

function fsIsNumber(evt) {
    var c = evt.which ? evt.which : evt.keyCode;
    if (c > 31 && (c < 48 || c > 57) && c !== 46 && c !== 8) return false;
    return true;
}

/* ------------------------------ Export --------------------------------- */
function fsExportConsolidation(cid) {
    window.open("views/print/print_financial_sheet.php?consolidate_id=" + encodeURIComponent(cid), "_blank");
}
