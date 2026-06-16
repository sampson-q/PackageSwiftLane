"use strict";

/* ===========================================================================
   FINANCIAL SHEET
   Nested lazy-loaded accordions: consolidation -> packages -> items.
   Items editable by weight OR custom price, guarded by a hard per-package lock
   that is refreshed by a heartbeat while open and released on close/unload.
   =========================================================================== */

var FS_AJAX = "./ajax/reports/financial_sheet_ajax.php";
var fsOpenLocks = {};      // oid -> true (packages we currently hold the lock for)
var fsHeartbeat = null;

$(function () {
    fsLoad();

    $("#fs_search").on("keyup", function (e) {
        if (e.key === "Enter") fsLoad();
    });
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
        success: function (html) {
            $(".outer_div").html(html);
        },
        complete: function () { $("#loader").fadeOut("fast"); }
    });
}

/* ----------------------- Accordion: consolidation ----------------------- */
// Only one consolidation open at a time: opening one collapses the others
// (releasing any package locks held inside them).
function fsToggleConsolidation(header, cid) {
    var $body    = $(".fs-consol-body[data-cid='" + cid + "']");
    var willOpen = !$body.hasClass("show");

    $(".fs-consol-body.show").each(function () {
        if (String($(this).data("cid")) !== String(cid)) {
            $(this).find(".fs-pkg-body.show").each(function () { fsStopLock($(this).data("oid")); });
            $(this).collapse("hide");
        }
    });

    var $box = $body.find(".fs-packages").first();
    $body.collapse("toggle");

    if (!willOpen) {
        // Collapsing this consolidation -> release its nested package locks.
        $body.find(".fs-pkg-body.show").each(function () { fsStopLock($(this).data("oid")); });
        return;
    }
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
    var $body = $(".fs-pkg-body[data-oid='" + oid + "']");
    var $box  = $body.find(".fs-items").first();
    var willOpen = !$body.hasClass("show");

    // One package open at a time within a consolidation (releases sibling locks).
    $body.closest(".fs-packages").find(".fs-pkg-body.show").each(function () {
        if (String($(this).data("oid")) !== String(oid)) {
            fsStopLock($(this).data("oid"));
            $(this).collapse("hide");
        }
    });

    $body.collapse("toggle");

    if (willOpen) {
        if ($box.attr("data-loaded") !== "1") {
            $box.attr("data-loaded", "1");
            $.ajax({
                url: FS_AJAX,
                data: { action: "items", order_id: oid },
                success: function (html) {
                    $box.html(html);
                    // We hold the lock only if the server returned editable rows.
                    if ($box.find(".fs-save").length) fsStartLock(oid);
                },
                error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load items.</div>'); }
            });
        } else if ($box.find(".fs-save").length) {
            fsStartLock(oid); // re-acquire on re-open
        }
    } else {
        fsStopLock(oid); // collapsed -> release
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
                            // Lost the lock (expired and taken) — make the rows read-only.
                            fsStopLock(id);
                            var $items = $(".fs-pkg-body[data-oid='" + id + "'] .fs-items");
                            $items.find("input,button.fs-save,.fs-mode .btn").prop("disabled", true);
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
        $custom.prop("disabled", false).attr("placeholder", "USD").focus();
    } else {
        $custom.val("").prop("disabled", true).attr("placeholder", "—");
        $weight.prop("disabled", false).attr("placeholder", "weight").focus();
    }
}

function fsSaveItem(oid, iid, btn) {
    var $row  = $("tr[data-iid='" + iid + "']");
    var mode  = $row.find(".fs-mode-val").val() || "weight";
    var value = (mode === "custom") ? $row.find(".fs-custom").val() : $row.find(".fs-weight").val();

    if (!value || parseFloat(value) <= 0) {
        Swal.fire({ icon: "error", text: "Enter a " + (mode === "custom" ? "custom price" : "weight") + " greater than 0.", confirmButtonText: "Ok" });
        return;
    }

    $(btn).prop("disabled", true);
    $.ajax({
        url: FS_AJAX,
        method: "POST",
        data: { action: "save_item", order_id: oid, order_item_id: iid, mode: mode, value: value },
        dataType: "json",
        success: function (r) {
            $(btn).prop("disabled", false);
            if (r && r.ok) {
                $(btn).removeClass("btn-success").addClass("btn-outline-success");
                setTimeout(function () { $(btn).removeClass("btn-outline-success").addClass("btn-success"); }, 1200);
                var $pkgTotal = $(".fs-pkg-body[data-oid='" + oid + "'] .fs-pkg-total");
                if (r.total_order != null) $pkgTotal.text(Number(r.total_order).toFixed(2));
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

function fsIsNumber(evt) {
    var c = evt.which ? evt.which : evt.keyCode;
    if (c > 31 && (c < 48 || c > 57) && c !== 46 && c !== 8) return false;
    return true;
}

/* ------------------------------ Export --------------------------------- */
// One PDF per consolidation (opens in a new tab).
function fsExportConsolidation(cid) {
    window.open("views/print/print_financial_sheet.php?consolidate_id=" + encodeURIComponent(cid), "_blank");
}
