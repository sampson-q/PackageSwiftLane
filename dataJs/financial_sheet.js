"use strict";

/* ===========================================================================
   FINANCIAL SHEET
   Nested accordions: consolidation -> customer -> packages -> items
   (plain jQuery show/hide — NOT Bootstrap collapse: lazy-loading into a
   collapsing container races the height calc and clips content).

   - All three filters run debounced on input (no search buttons).
   - Items are priced by weight OR custom price (0 allowed), individually or
     grouped ("priced together": one value covers the whole batch).
   - Money is stored canonical USD; the $/₵ toggle converts typed input only.
   - Billing a customer is one-time and permanent: confirm dialog, ledger,
     packages exit the consolidation, pricing locks, WhatsApp+email best-effort.
   =========================================================================== */

var FS_AJAX = "./ajax/reports/financial_sheet_ajax.php";
var fsOpenLocks = {};      // oid -> true (packages we currently hold the lock for)
var fsHeartbeat = null;
var fsSearchTimer = null;

function fsRate() {
    return Number(window.FS_RATE) > 0 ? Number(window.FS_RATE) : 1;
}

function fsMoney(usd) {
    return "$" + (Number(usd) || 0).toFixed(2);
}

$(function () {
    $("#fs_rate_label").text(fsRate().toFixed(2));

    // Two modes: the list page (search + consolidation cards linking out) and
    // the single-consolidation page (window.FS_CID set → load its customers).
    if (window.FS_CID) {
        fsReloadCustomers(window.FS_CID);
    } else {
        fsLoadList();
    }

    // All filters fire as you type (debounced). Typing in one clears the others.
    $("#fs_q_consol").on("input", function () { fsDebouncedSearch("consol"); });
    $("#fs_q_package").on("input", function () { fsDebouncedSearch("package"); });
    $("#fs_q_customer").on("input", function () { fsDebouncedSearch("customer"); });

    // Show "Group & price selected (N)" once 2+ items are ticked in a package.
    $(document).on("change", ".fs-item-check", function () {
        var $items = $(this).closest(".fs-items");
        var n = $items.find(".fs-item-check:checked").length;
        $items.find(".fs-sel-count").text(n);
        $items.find(".fs-group-bar").toggle(n >= 2);
    });

    // Release every lock we hold when leaving the page.
    window.addEventListener("beforeunload", function () {
        Object.keys(fsOpenLocks).forEach(function (oid) {
            navigator.sendBeacon
                ? navigator.sendBeacon(FS_AJAX + "?action=unlock&order_id=" + oid)
                : $.ajax({ url: FS_AJAX, data: { action: "unlock", order_id: oid }, async: false });
        });
    });
});

/* ------------------------------- Search --------------------------------- */
function fsDebouncedSearch(which) {
    if (which !== "consol") $("#fs_q_consol").not(document.activeElement).val("");
    if (which !== "package") $("#fs_q_package").not(document.activeElement).val("");
    if (which !== "customer") $("#fs_q_customer").not(document.activeElement).val("");

    clearTimeout(fsSearchTimer);
    fsSearchTimer = setTimeout(function () {
        var pkg = ($("#fs_q_package").val() || "").trim();
        var cust = ($("#fs_q_customer").val() || "").trim();
        if (which === "package" && pkg) return fsLoadInto({ action: "search_package", q: pkg });
        if (which === "customer" && cust) return fsLoadInto({ action: "search_customer", q: cust });
        fsLoadList();
    }, 400);
}

function fsLoadList() {
    fsLoadInto({ action: "list", q: ($("#fs_q_consol").val() || "").trim() });
}

function fsLoadInto(data) {
    $("#loader").fadeIn("fast");
    $.ajax({
        url: FS_AJAX,
        data: data,
        success: function (html) { $(".outer_div").html(html); },
        complete: function () { $("#loader").fadeOut("fast"); }
    });
}

/* --------------------- Consolidation tier (page mode) ------------------- */
// Reload the customers tier of one consolidation (page load / after billing,
// payments, …). The customer/package you were working in is re-opened after
// the refresh so nothing has to be found again.
function fsReloadCustomers(cid) {
    var $box = $(".fs-customers[data-cid='" + cid + "']").first();
    if (!$box.length) return;

    var openSid = $box.find(".fs-cust-body:visible").first().closest(".fs-cust-card").data("sid");
    var openOid = $box.find(".fs-pkg-body:visible").first().data("oid");

    $box.find(".fs-pkg-body:visible").each(function () {
        fsStopLock($(this).data("oid"));
    });
    $.ajax({
        url: FS_AJAX,
        data: { action: "customers", consolidate_id: cid },
        success: function (html) {
            $box.attr("data-loaded", "1").html(html);
            if (openSid != null) {
                var $hdr = $box.find(".fs-cust-card[data-sid='" + openSid + "'] .fs-cust-header").first();
                if ($hdr.length) {
                    fsToggleCustomer($hdr[0]);
                    if (openOid != null) fsExpandPackageWhenReady(openOid, 20);
                }
            }
        }
    });
}

// The packages tier loads async — re-open the package once its card exists.
function fsExpandPackageWhenReady(oid, tries) {
    var $body = $(".fs-pkg-body[data-oid='" + oid + "']");
    if ($body.length) {
        var $hdr = $body.closest(".fs-pkg-card").find(".fs-pkg-header").first();
        if ($hdr.length && $body.is(":hidden")) fsTogglePackage($hdr[0], oid);
        return;
    }
    if (tries > 0) setTimeout(function () { fsExpandPackageWhenReady(oid, tries - 1); }, 150);
}

/* ------------------------- Accordion: customer -------------------------- */
function fsToggleCustomer(header, ev) {
    // Clicks inside the Actions dropdown must not toggle the accordion
    // (stopPropagation would break Bootstrap's document-level dropdown handler).
    if (ev && $(ev.target).closest(".fs-actions").length) return;
    var $card = $(header).closest(".fs-cust-card");
    var cid = $card.data("cid"), sid = $card.data("sid");
    var $body = $card.find(".fs-cust-body").first();
    var willOpen = $body.is(":hidden");

    // One customer open at a time within a consolidation.
    var $container = $card.closest(".fs-customers");
    $container.find(".fs-cust-body:visible").each(function () {
        if ($(this).closest(".fs-cust-card")[0] !== $card[0]) {
            $(this).find(".fs-pkg-body:visible").each(function () {
                fsStopLock($(this).data("oid"));
            });
            $(this).slideUp(120);
        }
    });
    $container.find(".fs-cust-card.fs-active, .fs-pkg-card.fs-active").removeClass("fs-active");

    if (!willOpen) {
        $body.find(".fs-pkg-body:visible").each(function () {
            fsStopLock($(this).data("oid"));
        });
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
            data: { action: "packages", consolidate_id: cid, sender_id: sid },
            success: function (html) { $box.html(html); },
            error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load packages.</div>'); }
        });
    }
}

/* -------------------------- Accordion: package -------------------------- */
function fsTogglePackage(header, oid) {
    var $card = $(header).closest(".fs-pkg-card");
    var $body = $(".fs-pkg-body[data-oid='" + oid + "']");
    var $box = $body.find(".fs-items").first();
    var willOpen = $body.is(":hidden");

    // One package open at a time within a customer (releases sibling locks).
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
        fsReloadItems(oid);
    } else if ($box.find(".fs-save").length) {
        fsStartLock(oid); // re-acquire on re-open
    }
}

function fsReloadItems(oid) {
    var $box = $(".fs-pkg-body[data-oid='" + oid + "'] .fs-items").first();
    var $cust = $box.closest(".fs-cust-body");
    $box.attr("data-loaded", "1");
    $.ajax({
        url: FS_AJAX,
        // cid/sid let the server check the billing ledger for this customer.
        data: {
            action: "items", order_id: oid,
            consolidate_id: $cust.data("cid") || 0, sender_id: $cust.data("sid") || 0
        },
        success: function (html) {
            $box.html(html);
            if ($box.find(".fs-save").length) fsStartLock(oid);
        },
        error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load items.</div>'); }
    });
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
                            $items.find("input,button").prop("disabled", true);
                            $items.prepend('<div class="alert alert-warning py-2 mb-2"><i class="fas fa-lock"></i> Lock lost — now being edited by <b>' + (r.by || "another user") + '</b>.</div>');
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

/* ---------------------- Pricing control (shared) ------------------------ */
// One control = mode toggle (Weight | Custom) + a single value input.
// Weight mode shows a "kg" suffix; custom mode shows the $/₵ entry toggle.
function fsSetMode(btn, mode) {
    var $ctl = $(btn).closest(".fs-price-ctl");
    var $btns = $ctl.find(".fs-mode .btn");
    var $val = $ctl.find(".fs-value");

    $ctl.find(".fs-mode-val").val(mode);
    $btns.eq(0).toggleClass("btn-secondary", mode === "weight").toggleClass("btn-outline-secondary", mode !== "weight");
    $btns.eq(1).toggleClass("btn-success", mode === "custom").toggleClass("btn-outline-success", mode !== "custom");

    $ctl.find(".fs-unit").toggle(mode === "weight");
    $ctl.find(".fs-curs").toggle(mode === "custom");
    $ctl.find(".fs-equiv").text("");
    $val.val("").attr("placeholder", mode === "custom" ? ($val.attr("data-cur") || "usd").toUpperCase() : "weight").focus();
}

// Switch ONE custom-price input between USD and GHS, converting typed value.
function fsToggleCur(btn, cur) {
    cur = (cur === "ghs") ? "ghs" : "usd";
    var $ctl = $(btn).closest(".fs-price-ctl");
    var $i = $ctl.find(".fs-value");
    var old = $i.attr("data-cur") || "usd";

    if (old !== cur) {
        var raw = parseFloat(String($i.val() || "").replace(/,/g, ""));
        if (!isNaN(raw) && raw > 0) {
            var usd = (old === "ghs") ? raw / fsRate() : raw;
            var shown = (cur === "ghs") ? usd * fsRate() : usd;
            $i.val(shown.toFixed(2));
        }
        $i.attr("data-cur", cur).attr("placeholder", cur.toUpperCase());
    }

    // Same visual pattern as the courier_add/edit discount-type toggle.
    $ctl.find(".fs-cur-btn").removeClass("active btn-dark").addClass("btn-outline-dark");
    $ctl.find(".fs-cur-btn[data-cur='" + cur + "']").addClass("active btn-dark").removeClass("btn-outline-dark");
    fsLiveEquiv($i[0]);
}

// Live "≈ $X / ≈ ₵X" hint under a custom-price input.
function fsLiveEquiv(input) {
    var $i = $(input);
    var $ctl = $i.closest(".fs-price-ctl");
    var $eq = $ctl.find(".fs-equiv");
    if (($ctl.find(".fs-mode-val").val() || "weight") !== "custom") { $eq.text(""); return; }

    var raw = parseFloat(String($i.val() || "").replace(/,/g, ""));
    if (isNaN(raw) || raw <= 0) { $eq.text(""); return; }

    if (($i.attr("data-cur") || "usd") === "ghs") {
        $eq.text("≈ $" + (raw / fsRate()).toFixed(2) + " USD");
    } else {
        $eq.text("≈ ₵" + (raw * fsRate()).toFixed(2) + " GHS");
    }
}

// Read + validate a pricing control. 0 is allowed; negatives/empty are not.
function fsCollect($ctl) {
    var mode = $ctl.find(".fs-mode-val").val() || "weight";
    var $i = $ctl.find(".fs-value");
    var raw = String($i.val() || "").replace(/,/g, "").trim();
    var value = parseFloat(raw);

    if (raw === "" || isNaN(value) || value < 0) {
        Swal.fire({ icon: "error", text: "Enter a " + (mode === "custom" ? "custom price" : "weight") + " of 0 or more.", confirmButtonText: "Ok" });
        return null;
    }
    return { mode: mode, value: value, currency: $i.attr("data-cur") || "usd" };
}

/* --------------------------- Save / aggregates -------------------------- */
// The customer body div carries data-cid / data-sid for aggregate refreshes.
function fsCtx(el) {
    var $cust = $(el).closest(".fs-cust-body");
    return { cid: $cust.data("cid") || 0, sid: $cust.data("sid") || 0 };
}

// Live-refresh the consolidation header (page + list): due, received, priced.
function fsApplyConsolSummary(cid, s) {
    if (!s) return;
    var $total = $(".fs-consol-total[data-cid='" + cid + "']");
    if ($total.length && s.due_usd != null) {
        $total.attr("data-usd", s.due_usd).text("Due " + fsMoney(s.due_usd));
    }
    var $fees = $(".fs-consol-fees[data-cid='" + cid + "']");
    if ($fees.length && s.base_usd != null) {
        $fees.text(fsMoney(s.base_usd) + " + " + fsMoney(s.fee_usd) + " fees");
    }
    // Consolidation-level "Received" is displayed in USD.
    var $paid = $(".fs-consol-paid[data-cid='" + cid + "']");
    if ($paid.length && s.paid_usd != null) {
        $paid.text("Received " + fsMoney(s.paid_usd));
    }
    var $badge = $(".fs-consol-custpriced[data-cid='" + cid + "']");
    if ($badge.length && s.custs != null) {
        var done = s.custs > 0 && s.custs_priced >= s.custs;
        $badge.attr("class", "badge " + (done ? "badge-success" : "badge-warning") + " ml-3 fs-consol-custpriced")
            .attr("data-cid", cid)
            .html('<i class="mdi mdi-account-check"></i> ' + s.custs_priced + "/" + s.custs + " customers priced");
    }
}

function fsApplyAggregates(oid, r) {
    var $pkgCard = $(".fs-pkg-body[data-oid='" + oid + "']").closest(".fs-pkg-card");
    if (r.package_total != null) {
        $pkgCard.find(".fs-pkg-total").attr("data-usd", r.package_total).text(fsMoney(r.package_total));
    }
    // Per-package "n/m priced" badge follows every save live.
    if (r.pkg_stat) {
        var done = r.pkg_stat.items > 0 && r.pkg_stat.priced >= r.pkg_stat.items;
        $pkgCard.find(".fs-pkg-priced").first()
            .attr("class", "badge " + (done ? "badge-success" : "badge-light") + " ml-2 fs-pkg-priced")
            .text(r.pkg_stat.priced + "/" + r.pkg_stat.items + " priced");
    }
    var a = r.aggregates;
    if (!a) return;

    var $cust = $(".fs-pkg-body[data-oid='" + oid + "']").closest(".fs-cust-card");
    $cust.find(".fs-cust-total").first().attr("data-usd", a.customer_total).text(fsMoney(a.customer_total));

    // Package-level ratio: a package counts once ALL its items are priced.
    var $badge = $cust.find(".fs-cust-priced").first();
    if (a.pkgs != null) {
        var done = a.pkgs > 0 && a.pkgs_priced >= a.pkgs;
        $badge.attr("class", "badge " + (done ? "badge-success" : "badge-warning") + " fs-cust-priced")
            .text(a.pkgs_priced + "/" + a.pkgs + " pkgs priced");
    }

    // Bill is a plain button pre-billing; Re-bill lives in the dropdown after.
    var $bill = $cust.find(".fs-bill-btn").first();
    if ($bill.length && !a.billed) {
        if ($bill.is("button")) {
            $bill.prop("disabled", !a.billable);
        } else {
            $bill.toggleClass("disabled", !a.billable);
        }
        $bill.attr("title", a.billable ? "" : "All packages must be fully priced before billing");
    }

    fsApplyConsolSummary($cust.data("cid"), a.consol);
}

function fsSaveItem(btn, oid, iid) {
    var $ctl = $("tr[data-iid='" + iid + "'] .fs-price-ctl");
    var v = fsCollect($ctl);
    if (!v) return;
    var ctx = fsCtx(btn);

    $(btn).prop("disabled", true);
    $.ajax({
        url: FS_AJAX,
        method: "POST",
        data: {
            action: "save_item", order_id: oid, order_item_id: iid,
            mode: v.mode, value: v.value, currency: v.currency,
            consolidate_id: ctx.cid, sender_id: ctx.sid
        },
        dataType: "json",
        success: function (r) {
            $(btn).prop("disabled", false);
            if (r && r.ok) {
                $(btn).removeClass("btn-success").addClass("btn-outline-success");
                setTimeout(function () { $(btn).removeClass("btn-outline-success").addClass("btn-success"); }, 1200);
                // Mark the row as priced.
                var $row = $("tr[data-iid='" + iid + "']");
                if (!$row.find(".mdi-check-circle").length) {
                    $row.find("td").eq($row.find(".fs-item-check").length ? 2 : 1)
                        .append(' <i class="mdi mdi-check-circle text-success ml-1" title="Priced"></i>');
                }
                fsApplyAggregates(oid, r);
                if (r.history) fsPrependHistory(oid, r.history);
            } else {
                fsSaveError(r);
            }
        },
        error: function () {
            $(btn).prop("disabled", false);
            Swal.fire({ icon: "error", text: "Save failed.", confirmButtonText: "Ok" });
        }
    });
}

function fsSaveError(r) {
    if (r && r.error === "locked") {
        Swal.fire({ icon: "warning", text: "This package is now being edited by " + (r.by || "another user") + ".", confirmButtonText: "Ok" });
    } else {
        Swal.fire({ icon: "error", text: (r && r.message) ? r.message : "Could not save.", confirmButtonText: "Ok" });
    }
}

/* ------------------------------ Grouping -------------------------------- */
// "Priced together": one weight or custom price covers the whole batch.
function fsGroupSelected(btn, oid) {
    var $items = $(btn).closest(".fs-items");
    var ids = $items.find(".fs-item-check:checked").map(function () { return this.value; }).get();
    if (ids.length < 2) {
        Swal.fire({ icon: "info", text: "Select at least two items to group.", confirmButtonText: "Ok" });
        return;
    }
    var ctx = fsCtx(btn);

    Swal.fire({
        title: "Price " + ids.length + " items together",
        html:
            '<div class="text-left" style="font-size:14px;">' +
            '<p class="mb-2">One value covers the whole batch (no per-item quantity multiplier).</p>' +
            '<label class="mr-3"><input type="radio" name="fsg_mode" value="weight" checked> Weight (lb)</label>' +
            '<label><input type="radio" name="fsg_mode" value="custom"> Custom price</label>' +
            '<div class="d-flex mt-2" style="gap:8px;">' +
            '<input id="fsg_value" class="form-control" placeholder="Value" style="flex:1;">' +
            '<select id="fsg_cur" class="form-control" style="width:90px;display:none;">' +
            '<option value="usd">USD $</option><option value="ghs">GHS ₵</option></select>' +
            '</div></div>',
        showCancelButton: true,
        confirmButtonText: "Group & save",
        didOpen: function () {
            $(document).off("change.fsg").on("change.fsg", "input[name='fsg_mode']", function () {
                $("#fsg_cur").toggle(this.value === "custom");
            });
        },
        preConfirm: function () {
            var mode = $("input[name='fsg_mode']:checked").val() || "weight";
            var raw = String($("#fsg_value").val() || "").replace(/,/g, "").trim();
            var value = parseFloat(raw);
            if (raw === "" || isNaN(value) || value < 0) {
                Swal.showValidationMessage("Enter a value of 0 or more.");
                return false;
            }
            return { mode: mode, value: value, currency: $("#fsg_cur").val() || "usd" };
        }
    }).then(function (res) {
        $(document).off("change.fsg");
        if (!res.isConfirmed || !res.value) return;
        fsPostGroup(oid, {
            action: "save_group", order_id: oid, item_ids: ids.join(","),
            mode: res.value.mode, value: res.value.value, currency: res.value.currency,
            consolidate_id: ctx.cid, sender_id: ctx.sid
        });
    });
}

function fsSaveGroup(btn, oid, token) {
    var $ctl = $(btn).closest(".fs-group-ctl").find(".fs-price-ctl");
    var v = fsCollect($ctl);
    if (!v) return;
    var ctx = fsCtx(btn);
    $(btn).prop("disabled", true);
    fsPostGroup(oid, {
        action: "save_group", order_id: oid, group: token,
        mode: v.mode, value: v.value, currency: v.currency,
        consolidate_id: ctx.cid, sender_id: ctx.sid
    });
}

function fsUngroup(btn, oid, token) {
    var ctx = fsCtx(btn);
    Swal.fire({
        icon: "question",
        title: "Ungroup these items?",
        text: "The items return to the list unpriced — each will need to be priced again.",
        showCancelButton: true,
        confirmButtonText: "Ungroup"
    }).then(function (res) {
        if (!res.isConfirmed) return;
        fsPostGroup(oid, {
            action: "clear_group", order_id: oid, group: token,
            consolidate_id: ctx.cid, sender_id: ctx.sid
        });
    });
}

// Shared POST for group operations; the items panel re-renders afterwards.
function fsPostGroup(oid, data) {
    $.ajax({
        url: FS_AJAX,
        method: "POST",
        data: data,
        dataType: "json",
        success: function (r) {
            if (r && r.ok) {
                fsApplyAggregates(oid, r);
                fsReloadItems(oid);
            } else {
                fsSaveError(r);
                fsReloadItems(oid);
            }
        },
        error: function () {
            Swal.fire({ icon: "error", text: "Save failed.", confirmButtonText: "Ok" });
        }
    });
}

/* ------------------------------- Billing -------------------------------- */
// Per-package price table for the bill confirmation + success dialogs. The
// handling fee is a single line below the table — it is determined by the
// customer's TOTAL across all packages, not per package.
function fsBillTable(packages) {
    var rows = (packages || []).map(function (p) {
        return '<tr><td style="font-family:monospace;">' + p.no + '</td>' +
            '<td class="text-right">' + fsMoney(p.usd) + '</td>' +
            '<td class="text-right"><b>₵' + Number(p.ghs).toFixed(2) + '</b></td></tr>';
    }).join("");
    return '<table class="table table-sm table-bordered mb-2" style="font-size:12.5px;">' +
        '<thead><tr><th>Package</th><th class="text-right">Price ($)</th><th class="text-right">Price (₵)</th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table>';
}

// Stage 1: bill the customer (sort + notify + Ready for PickUp). Re-billing is
// allowed after re-pricing — it only refreshes the amount, logs and notifies
// (packages are NOT moved again). Flow: fetch the per-package preview first,
// confirm with the full breakdown, then bill.
function fsBillCustomer(btn) {
    var $b = $(btn);
    if ($b.hasClass("disabled") || $b.prop("disabled")) return;
    var cid = $b.data("cid"), sid = $b.data("sid");
    var name = $b.data("name") || "this customer";

    $b.addClass("disabled");
    if ($b.is("button")) $b.prop("disabled", true);
    $.ajax({
        url: FS_AJAX,
        data: { action: "bill_preview", consolidate_id: cid, sender_id: sid },
        dataType: "json"
    }).always(function () {
        $b.removeClass("disabled");
        if ($b.is("button")) $b.prop("disabled", false);
    }).then(function (p) {
        if (!p || !p.ok) {
            Swal.fire({ icon: "error", text: (p && p.message) ? p.message : "Could not load the bill preview.", confirmButtonText: "Ok" });
            return;
        }
        var rebill = !!p.rebill;
        var html =
            '<div class="text-left" style="font-size:14px;">' +
            fsBillTable(p.packages) +
            '<p class="mb-1">Subtotal: <b>₵' + Number(p.sub_ghs).toFixed(2) + '</b> (' + fsMoney(p.usd) + ')<br>' +
            'Handling Fee (on the customer total): <b>₵' + Number(p.fee_ghs).toFixed(2) + '</b><br>' +
            'Total: <b>₵' + Number(p.total_ghs).toFixed(2) + '</b>' +
            (rebill && p.prev_ghs != null
                ? '<br><span class="text-muted">Previous bill: ₵' + Number(p.prev_ghs).toFixed(2) + '</span>'
                : '') +
            '</p>' +
            '<p class="mb-1">This will:</p>' +
            '<ul class="pl-3 mb-0">' +
            '<li>notify the customer by WhatsApp and email' + (rebill ? ' <b>with the updated bill</b>' : '') + ',</li>' +
            (rebill
                ? '<li>update the recorded bill (packages are <b>not</b> moved again),</li>'
                : '<li>move the package(s) to <b>Ready for PickUp</b>,</li>') +
            '<li>log this action under your name.</li>' +
            '</ul>' +
            '</div>';

        Swal.fire({
            icon: "question",
            title: (rebill ? "Re-bill " : "Bill ") + name + "?",
            html: html,
            width: 620,
            showCancelButton: true,
            confirmButtonText: rebill ? "Yes, re-bill" : "Yes, bill now",
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                return $.ajax({
                    url: FS_AJAX,
                    method: "POST",
                    data: { action: "bill_customer", consolidate_id: cid, sender_id: sid },
                    dataType: "json"
                }).then(function (r) {
                    if (!r || !r.ok) {
                        Swal.showValidationMessage((r && r.message) ? r.message : "Billing failed.");
                        return false;
                    }
                    return r;
                }, function () {
                    Swal.showValidationMessage("Billing failed — server error.");
                    return false;
                });
            }
        }).then(function (res) {
            if (!res.isConfirmed || !res.value) return;
            var r = res.value;
            var lines =
                '<div class="text-left" style="font-size:14px;">' +
                fsBillTable(r.packages) +
                '<p class="mb-1">Subtotal: <b>₵' + Number(r.sub_ghs).toFixed(2) + '</b> (' + fsMoney(r.amount_usd) + ')<br>' +
                'Handling Fee: <b>₵' + Number(r.handling_ghs || 0).toFixed(2) + '</b><br>' +
                'Total Billed: <b>₵' + Number(r.amount_ghs).toFixed(2) + '</b> — by ' + (r.billed_by || "you") +
                (r.rebill && r.prev_ghs != null
                    ? '<br><span class="text-muted">Changed from ₵' + Number(r.prev_ghs).toFixed(2) + ' to ₵' + Number(r.amount_ghs).toFixed(2) + '</span>'
                    : '') +
                '</p>' +
                '<p class="mb-1">Notifications:</p>' +
                '<ul class="pl-3 mb-0">' +
                '<li>WhatsApp: ' + (r.sent_whatsapp ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') + '</li>' +
                '<li>Email: ' + (r.sent_email ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') + '</li>' +
                '</ul>' +
                ((r.warnings && r.warnings.length)
                    ? '<p class="text-muted mt-2 mb-0" style="font-size:12px;">' + r.warnings.join('<br>') + '</p>'
                    : '') +
                '</div>';
            Swal.fire({
                icon: "success",
                title: r.rebill ? "Customer re-billed" : "Customer billed",
                html: lines,
                width: 620,
                confirmButtonText: "Ok"
            }).then(function () {
                fsApplyConsolSummary(cid, r.consol);
                fsReloadCustomers(cid);
            });
        });
    });
}

/* --------------------------- Record payment ----------------------------- */
// Stage 2: the customer pays on pickup. The discount (bill − paid) is
// auto-calculated live; the small ₵/$ toggle (courier_add style) switches the
// currency the discount is DISPLAYED in — payment itself is entered in GHS.
function fsRecordPayment(btn) {
    var $b = $(btn);
    var cid = $b.data("cid"), sid = $b.data("sid");
    var name = $b.data("name") || "this customer";
    var bill = parseFloat($b.data("bill")) || 0;
    var prevPaid = $b.data("paid");

    function discountHtml(paid, cur) {
        var d = Math.max(0, bill - paid);
        var shown = (cur === "usd") ? "$" + (d / fsRate()).toFixed(2) : "₵" + d.toFixed(2);
        return shown;
    }

    var isUpdate = (prevPaid !== "" && prevPaid != null);

    Swal.fire({
        title: (isUpdate ? "Update payment — " : "Record payment — ") + name,
        html:
            '<div class="text-left" style="font-size:14px;">' +
            '<p class="mb-2">Bill: <b>₵' + bill.toFixed(2) + '</b></p>' +
            '<label class="mb-1">Amount paid</label>' +
            '<div class="input-group">' +
            '<div class="input-group-prepend"><span class="input-group-text">GH₵</span></div>' +
            '<input id="fsp_paid" class="form-control" placeholder="0.00" value="' + (isUpdate ? Number(prevPaid).toFixed(2) : '') + '">' +
            '</div>' +
            '<div class="d-flex align-items-center mt-2" style="gap:8px;">' +
            '<span>Discount: <b id="fsp_discount">' + discountHtml(parseFloat(prevPaid) || 0, "ghs") + '</b></span>' +
            '<span class="btn-group btn-group-sm" role="group" aria-label="Discount display currency">' +
            '<button type="button" id="fsp_cur_ghs" class="btn btn-dark py-0 px-2">₵</button>' +
            '<button type="button" id="fsp_cur_usd" class="btn btn-outline-dark py-0 px-2">$</button>' +
            '</span>' +
            '</div>' +
            '<label class="mb-1 mt-2">Note <span class="text-muted">(internal use only — never sent to the customer)</span></label>' +
            '<textarea id="fsp_note" class="form-control" rows="2" placeholder="Optional note…"></textarea>' +
            '<p class="text-muted mt-2 mb-0" style="font-size:12px;">The customer will receive a payment receipt by WhatsApp and email.</p>' +
            '</div>',
        width: 560,
        showCancelButton: true,
        confirmButtonText: isUpdate ? "Update payment" : "Save payment",
        showLoaderOnConfirm: true,
        allowOutsideClick: function () { return !Swal.isLoading(); },
        didOpen: function () {
            var cur = "ghs";
            function refresh() {
                var paid = parseFloat(String($("#fsp_paid").val() || "").replace(/,/g, "")) || 0;
                $("#fsp_discount").text(discountHtml(paid, cur));
            }
            $("#fsp_paid").on("input", refresh);
            $("#fsp_cur_ghs").on("click", function () {
                cur = "ghs";
                $(this).addClass("btn-dark").removeClass("btn-outline-dark");
                $("#fsp_cur_usd").addClass("btn-outline-dark").removeClass("btn-dark");
                refresh();
            });
            $("#fsp_cur_usd").on("click", function () {
                cur = "usd";
                $(this).addClass("btn-dark").removeClass("btn-outline-dark");
                $("#fsp_cur_ghs").addClass("btn-outline-dark").removeClass("btn-dark");
                refresh();
            });
        },
        preConfirm: function () {
            var raw = String($("#fsp_paid").val() || "").replace(/,/g, "").trim();
            var paid = parseFloat(raw);
            if (raw === "" || isNaN(paid) || paid < 0) {
                Swal.showValidationMessage("Enter an amount of 0 or more.");
                return false;
            }
            return $.ajax({
                url: FS_AJAX,
                method: "POST",
                data: {
                    action: "record_payment", consolidate_id: cid, sender_id: sid,
                    paid: paid, note: String($("#fsp_note").val() || "").trim()
                },
                dataType: "json"
            }).then(function (r) {
                if (!r || !r.ok) {
                    Swal.showValidationMessage((r && r.message) ? r.message : "Could not save the payment.");
                    return false;
                }
                return r;
            }, function () {
                Swal.showValidationMessage("Could not save the payment — server error.");
                return false;
            });
        }
    }).then(function (res) {
        if (!res.isConfirmed || !res.value) return;
        var r = res.value;
        var lines =
            '<div class="text-left" style="font-size:14px;">' +
            '<p class="mb-1">Paid: <b>₵' + Number(r.paid_ghs).toFixed(2) + '</b> of ₵' + Number(r.bill_ghs).toFixed(2) +
            (Number(r.discount_ghs) > 0 ? ' — discount <b>₵' + Number(r.discount_ghs).toFixed(2) + '</b>' : '') + '</p>' +
            '<p class="mb-1">Receipt: WhatsApp ' + (r.sent_whatsapp ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') +
            ' &middot; Email ' + (r.sent_email ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') + '</p>' +
            ((r.warnings && r.warnings.length)
                ? '<p class="text-muted mt-2 mb-0" style="font-size:12px;">' + r.warnings.join('<br>') + '</p>'
                : '') +
            '</div>';
        Swal.fire({
            icon: "success",
            title: isUpdate ? "Payment updated" : "Payment recorded",
            html: lines,
            confirmButtonText: "Ok"
        }).then(function () {
            fsApplyConsolSummary(cid, r.consol);
            fsReloadCustomers(cid);
        });
    });
}

/* --------------------------- Print shipments ---------------------------- */
// Direct click = user gesture, so window.open is not popup-blocked here.
function fsPrintShipments(el) {
    var orders = $(el).data("orders") || [];
    if (!orders.length) return;
    if (orders.length === 1) {
        window.open("print_inv_ship.php?id=" + encodeURIComponent(orders[0].id), "_blank");
        return;
    }
    var nos = orders.map(function (o) { return o.no; });
    let cleanedNos = nos.map(item => item.replace(/^\D+/, ""));
    window.open("print_inv_ship_multiple.php?data=" + JSON.stringify(cleanedNos), "_blank");

}

/* ------------------------------ Change log ------------------------------ */
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

// Excel export (.xls download, no blank tab).
function fsExportConsolidationExcel(cid) {
    window.location.href = "views/print/print_financial_sheet_excel.php?consolidate_id=" + encodeURIComponent(cid);
}
