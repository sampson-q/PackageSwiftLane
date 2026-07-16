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

/* ---- Display currency toggle (GH₵ / $). Billed chips carry their own stored
   rate (data-usd); package/consolidation totals convert at the live rate (a
   current-value estimate). The billing log keeps its per-transaction rates. --- */
var fsCurrency = null; // null = native (server-rendered): totals $, chips ₵
function fsCurFmt(n) {
    return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fsSetCurrency(c) {
    fsCurrency = (c === "usd") ? "usd" : "ghs";
    $("#fs_cur_ghs").toggleClass("btn-dark", fsCurrency === "ghs").toggleClass("btn-outline-dark", fsCurrency !== "ghs");
    $("#fs_cur_usd").toggleClass("btn-dark", fsCurrency === "usd").toggleClass("btn-outline-dark", fsCurrency !== "usd");
    fsApplyCurrency();
}
function fsApplyCurrency(root) {
    if (!fsCurrency) return; // native — leave server-rendered values
    var $scope = root ? $(root) : $(document);
    // Chips with both stored values.
    $scope.find(".fs-cur-chip").each(function () {
        var v = (fsCurrency === "usd") ? $(this).data("usd") : $(this).data("ghs");
        $(this).text((fsCurrency === "usd" ? "$" : "₵") + fsCurFmt(v));
    });
    // USD-canonical totals (package / customer / consolidation).
    $scope.find(".fs-money").each(function () {
        var usd = Number($(this).data("usd")) || 0;
        var pre = $(this).hasClass("fs-consol-total") ? "Due " : "";
        var val = (fsCurrency === "usd") ? usd : usd * fsRate();
        $(this).text(pre + (fsCurrency === "usd" ? "$" : "₵") + fsCurFmt(val));
    });
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
        success: function (html) {
            $(".outer_div").html(html);
            fsApplyCurrency();
            // The list renders instantly with "…" placeholders; the heavy
            // numbers (due/fees/weight/priced/received) stream in right after.
            if (data.action === "list") fsLoadListStats();
        },
        complete: function () { $("#loader").fadeOut("fast"); }
    });
}

var fsStatsCache = {}; // cid -> summary; lives for the page session
var fsStatsXhr = null;

function fsLoadListStats() {
    var cids = $(".outer_div .fs-consol-total[data-cid]").map(function () {
        return $(this).data("cid");
    }).get();
    if (!cids.length) return;

    // Serve already-fetched consolidations from cache (filtering re-renders the
    // same cards constantly — don't re-run the heavy query for them).
    var missing = [];
    cids.forEach(function (cid) {
        if (fsStatsCache[cid]) {
            fsApplyConsolSummary(cid, fsStatsCache[cid]);
        } else {
            missing.push(cid);
        }
    });
    if (!missing.length) return;

    // Never stack heavy stats queries: a newer list load supersedes the old one.
    if (fsStatsXhr && fsStatsXhr.readyState !== 4) fsStatsXhr.abort();
    fsStatsXhr = $.ajax({
        url: FS_AJAX,
        data: { action: "list_stats", cids: missing.join(",") },
        dataType: "json",
        success: function (r) {
            if (!r || !r.ok || !r.stats) return;
            $.each(r.stats, function (cid, s) {
                fsStatsCache[cid] = s;
                fsApplyConsolSummary(cid, s);
            });
        }
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
            fsApplyCurrency($box);
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
            success: function (html) { $box.html(html); fsApplyCurrency($box); },
            error: function () { $box.attr("data-loaded", "0").html('<div class="text-danger small">Failed to load packages.</div>'); }
        });
    }
}

/* -------------------------- Accordion: package -------------------------- */
function fsTogglePackage(header, oid, ev) {
    // Clicks inside Package Actions must not toggle the accordion.
    if (ev && $(ev.target).closest(".fs-pkg-actions").length) return;
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
    // Consolidation-level "Received" is displayed in USD (list chips stay
    // hidden until there is actually something received).
    var $paid = $(".fs-consol-paid[data-cid='" + cid + "']");
    if ($paid.length && s.paid_usd != null) {
        $paid.text("Received " + fsMoney(s.paid_usd));
        if (s.paid_usd > 0) $paid.show();
    }
    var $weight = $(".fs-consol-weight[data-cid='" + cid + "']");
    if ($weight.length && s.weight != null) {
        $weight.html('<i class="mdi mdi-weight"></i> ' + Number(s.weight).toFixed(2) + " lb");
    }
    var $badge = $(".fs-consol-custpriced[data-cid='" + cid + "']");
    if ($badge.length && s.custs != null) {
        var done = s.custs > 0 && s.custs_priced >= s.custs;
        $badge.attr("class", "badge " + (done ? "badge-success" : "badge-warning") + " ml-3 fs-consol-custpriced")
            .attr("data-cid", cid)
            .html('<i class="mdi mdi-account-check"></i> ' + s.custs_priced + "/" + s.custs + " customers priced");
    }
    fsApplyCurrency(); // re-flip the "Due" total to the chosen currency
}

function fsApplyAggregates(oid, r) {
    var $pkgCard = $(".fs-pkg-body[data-oid='" + oid + "']").closest(".fs-pkg-card");
    if (r.package_total != null) {
        $pkgCard.find(".fs-pkg-total").attr("data-usd", r.package_total).text(fsMoney(r.package_total));
        fsApplyCurrency($pkgCard);
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
                ? '<li>update the recorded bill,</li>'
                : '<li><b>clear the selected package(s) for delivery</b>,</li>') +
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
// Payment is PER PACKAGE: fetch the customer's packages, present them as a
// checklist (all checked by default), and the amount = sum of the checked
// (not-yet-cleared) packages + the one-time handling fee. "You don't pick a
// package you didn't pay for."
function fsRecordPayment(btn) {
    var $b = $(btn);
    var cid = $b.data("cid"), sid = $b.data("sid");
    var name = $b.data("name") || "this customer";

    $.ajax({
        url: FS_AJAX, method: "POST", dataType: "json",
        data: { action: "payment_form", consolidate_id: cid, sender_id: sid }
    }).then(function (f) {
        if (!f || !f.ok) {
            Swal.fire("Cannot record payment", (f && f.message) ? f.message : "Could not load the packages.", "warning");
            return;
        }
        fsOpenPaymentDialog(cid, sid, name, f);
    }, function () {
        Swal.fire("Error", "Could not load the packages — server error.", "error");
    });
}

/**
 * The ONE payment dialog. Recording a payment and clearing a debt are the same
 * transaction — a payment against a customer's bill — so they share this builder
 * rather than two drifting implementations. opts.mode: 'payment' | 'debt'.
 * Debt mode differs only in wording, the server action, and defaulting the cash
 * amount to the full owing; it gets the same package list, the same gateway
 * checkout (Paystack/Hubtel), and the same layout.
 */
function fsOpenPaymentDialog(cid, sid, name, f, opts) {
    opts = opts || {};
    var isDebt = opts.mode === "debt";
    var pkgs = f.packages || [];
    // Gross value of ALL packages, and net-per-gross so a cleared subset can be
    // valued against the customer's net bill (which folds in fee − discount).
    var grossAll = 0;
    pkgs.forEach(function (p) { grossAll += Number(p.ghs) || 0; });
    var netPerGross = grossAll > 0 ? (Number(f.net_ghs) / grossAll) : 1;

    var owing = Number(f.balance_ghs) || 0;
    var owedAll = Number(f.owed_ghs) || 0;

    var rows = pkgs.map(function (p) {
        var cleared = !!p.cleared;
        var delivered = !!p.delivered;
        // A delivered package can still be unpaid (the customer was let go with
        // it). It still carries debt, but "clear for delivery" is meaningless
        // once it is gone — so it gets no tick. Clearance is for UNCLEARED
        // packages only.
        // No tick once a package is already cleared (there is nothing left to
        // clear) or delivered (it is gone). Only genuinely uncleared packages
        // get a checkbox. An already-cleared package still keeps its value on a
        // .fsp-done marker so the cash tally can count what WILL be cleared
        // after this save.
        var actionable = !cleared && !delivered;
        var box = actionable
            ? '<input type="checkbox" class="fsp-pkg" data-oid="' + p.oid + '" data-ghs="' + Number(p.ghs) + '" checked>'
            : '<span class="fsp-nobox" style="display:inline-block;width:13px;"></span>' +
              (cleared ? '<span class="fsp-done" data-ghs="' + Number(p.ghs) + '" style="display:none;"></span>' : '');
        var badge = delivered
            ? ' <span class="badge badge-dark">delivered</span>' + (cleared ? ' <span class="badge badge-success">cleared</span>' : '')
            : (cleared ? ' <span class="badge badge-success">cleared</span>' : '');
        return '<label class="d-flex align-items-center justify-content-between mb-1" style="gap:8px; cursor:' + (actionable ? 'pointer' : 'default') + ';">' +
            '<span>' + box + ' ' +
            '<span style="font-family:SFMono-Regular,Consolas,monospace;">' + p.no + '</span>' +
            badge + '</span>' +
            '<b>₵' + Number(p.ghs).toFixed(2) + '</b></label>';
    }).join('');

    // Money summary — one tidy block instead of loose stacked rows.
    var sum =
        '<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:10px 12px;margin-bottom:10px;">' +
        '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Bill (net)</span><b>₵' + Number(f.net_ghs).toFixed(2) + '</b></div>' +
        (Number(f.paid_ghs) > 0 ? '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Already paid</span><b>₵' + Number(f.paid_ghs).toFixed(2) + '</b></div>' : '') +
        '<div class="d-flex justify-content-between"><span class="text-danger">Owing (this consolidation)</span><b class="text-danger">₵' + owing.toFixed(2) + '</b></div>' +
        (owedAll > owing + 0.01
            ? '<div class="d-flex justify-content-between mt-1 pt-1" style="border-top:1px dashed #dee2e6;">' +
              '<span class="text-muted" title="Everything this customer owes across all their consolidations">Total owed (all consolidations)</span>' +
              '<b class="text-danger">₵' + owedAll.toFixed(2) + '</b></div>'
            : '') +
        '</div>';

    Swal.fire({
        title: (isDebt ? "Clear Debt — " : "Record Payment — ") + name,
        html:
            '<div class="text-left" style="font-size:14px;">' +
            sum +
            '<div class="mb-1"><b>Clear These Packages for Delivery</b> <span class="text-muted">(untick any the customer isn\'t paying for)</span></div>' +
            '<div style="max-height:200px; overflow:auto; border:1px solid #eee; border-radius:6px; padding:6px;">' + (rows || '<div class="text-muted">No packages.</div>') + '</div>' +
            '<div class="d-flex justify-content-between mt-1"><span class="text-muted">Value of ticked packages:</span><b id="fsp_val">₵0.00</b></div>' +
            '<label class="mb-1 mt-2">Payment Method</label>' +
            '<select id="fsp_mode" class="form-control mb-2">' +
            '<option value="cash">Cash</option>' +
            '<option value="paystack">Paystack</option>' +
            '<option value="hubtel">Hubtel</option>' +
            '</select>' +
            // Cash block: manual amount received (physical cash has no e-record).
            '<div id="fsp_cash">' +
            '<label class="mb-1">Amount Received</label>' +
            '<div class="input-group">' +
            '<div class="input-group-prepend"><span class="input-group-text">GH₵</span></div>' +
            '<input id="fsp_paid" class="form-control" placeholder="0.00" value="' + (isDebt ? owing.toFixed(2) : '') + '">' +
            '</div>' +
            '<div id="fsp_warn" class="mt-1" style="display:none; font-size:12.5px;"></div>' +
            '</div>' +
            // Online block: no manual amount — the gateway is charged & confirms it.
            '<div id="fsp_online" style="display:none;">' +
            '<div class="d-flex justify-content-between mb-1"><span>Amount to Charge:</span><b id="fsp_charge">₵0.00</b></div>' +
            '<button type="button" id="fsp_init" class="btn btn-sm btn-outline-primary mb-1">Start Checkout</button>' +
            '<div id="fsp_init_msg" class="text-muted mb-1" style="font-size:12px;"></div>' +
            '<label class="mb-1">Transaction Reference</label>' +
            '<input id="fsp_ref" class="form-control" placeholder="Filled by checkout — verified on save">' +
            '</div>' +
            '<label class="mb-1 mt-1">Note <span class="text-muted">(internal use only)</span></label>' +
            '<textarea id="fsp_note" class="form-control" rows="2" placeholder="Optional note…"></textarea>' +
            '<p class="text-muted mt-2 mb-0" style="font-size:12px;">The customer will receive a payment receipt by WhatsApp and email.</p>' +
            '</div>',
        width: 680,
        showCancelButton: true,
        confirmButtonText: isDebt ? "Record Debt Payment" : "Save Payment",
        showLoaderOnConfirm: true,
        allowOutsideClick: function () { return !Swal.isLoading(); },
        didOpen: function () {
            // Net value of the packages that WILL be cleared after this save:
            // the ones ticked now, PLUS the ones already cleared. Already-cleared
            // packages no longer carry a checkbox, so their value comes off the
            // .fsp-done marker instead.
            function clearedAfterNet() {
                var g = 0;
                $(".fsp-pkg:checked").each(function () { g += parseFloat($(this).data("ghs")) || 0; });
                $(".fsp-done").each(function () { g += parseFloat($(this).data("ghs")) || 0; });
                return g * netPerGross;
            }
            // Net value of the newly-ticked packages only (the online charge).
            // Every remaining .fsp-pkg is by definition not yet cleared.
            function newlyTickedNet() {
                var g = 0;
                $(".fsp-pkg:checked").each(function () { g += parseFloat($(this).data("ghs")) || 0; });
                return g * netPerGross;
            }
            function isOnline() { return $("#fsp_mode").val() !== "cash"; }
            // Straight sum of the ticked packages' own prices (what the user sees
            // ticked), not a net/gross round-trip which drifted when a discount
            // made netPerGross != 1.
            function tickedGross() {
                var g = 0;
                $(".fsp-pkg:checked").each(function () { g += parseFloat($(this).data("ghs")) || 0; });
                return g;
            }
            function refresh() {
                $("#fsp_val").text("₵" + tickedGross().toFixed(2)); // value of ticked packages
                $("#fsp_charge").text("₵" + (isDebt ? owing : newlyTickedNet()).toFixed(2));
                // The tally below reconciles CASH RECEIVED against the packages
                // being cleared. In debt mode the operator is settling an owed
                // amount (possibly for already-delivered packages), so that
                // comparison is meaningless — skip it.
                if (isOnline() || isDebt) { $("#fsp_warn").hide(); return; }
                // Cash tally: does total-paid-after cover the net value of cleared pkgs?
                var clearedNet = clearedAfterNet();
                var paidAfter = Number(f.paid_ghs) + (parseFloat(String($("#fsp_paid").val() || "").replace(/,/g, "")) || 0);
                var diff = paidAfter - clearedNet;
                var $w = $("#fsp_warn");
                if (diff < -0.01) {
                    $w.attr("class", "mt-1 alert alert-warning py-1 px-2 mb-0").html(
                        "⚠ Short by ₵" + Math.abs(diff).toFixed(2) + " — you're clearing packages worth ₵" +
                        clearedNet.toFixed(2) + " but only ₵" + paidAfter.toFixed(2) + " will have been received in total."
                    ).show();
                } else if (diff > 0.01) {
                    var unticked = $(".fsp-pkg").length - $(".fsp-pkg:checked").length;
                    $w.attr("class", "mt-1 alert alert-warning py-1 px-2 mb-0").html(
                        "⚠ ₵" + paidAfter.toFixed(2) + " received, but the cleared packages are only worth ₵" +
                        clearedNet.toFixed(2) + (unticked > 0 ? " — " + unticked + " package(s) left un-cleared." : " (overpayment).")
                    ).show();
                } else {
                    $w.hide().empty();
                }
            }
            $(".fsp-pkg").on("change", refresh);
            $("#fsp_paid").on("input", refresh);
            $("#fsp_mode").on("change", function () {
                var online = isOnline();
                $("#fsp_cash").toggle(!online);
                $("#fsp_online").toggle(online);
                $("#fsp_init_msg").empty();
                refresh();
            });
            $("#fsp_init").on("click", function () {
                var mode = $("#fsp_mode").val();
                // Debt mode charges the outstanding amount; payment mode charges
                // the packages being newly cleared.
                var amt = Math.round((isDebt ? owing : newlyTickedNet()) * 100) / 100;
                if (amt <= 0) {
                    $("#fsp_init_msg").html('<span class="text-danger">' +
                        (isDebt ? "Nothing outstanding to charge." : "Tick at least one package to charge for.") + '</span>');
                    return;
                }
                $("#fsp_init_msg").text("Starting " + mode + " checkout…");
                $.ajax({
                    url: FS_AJAX, method: "POST", dataType: "json",
                    data: { action: "gateway_init", consolidate_id: cid, sender_id: sid, mode: mode, amount: amt }
                }).then(function (r) {
                    if (r && r.ok && r.url) {
                        $("#fsp_ref").val(r.reference || "");
                        window.open(r.url, "_blank");
                        $("#fsp_init_msg").html('<span class="text-success">Checkout opened — complete it, then Save to verify.</span>');
                    } else {
                        $("#fsp_init_msg").html('<span class="text-danger">' + ((r && r.message) ? r.message : "Could not start checkout.") + '</span>');
                    }
                }, function () {
                    $("#fsp_init_msg").html('<span class="text-danger">Could not reach the gateway.</span>');
                });
            });
            refresh();
        },
        preConfirm: function () {
            // Only uncleared, undelivered packages render a .fsp-pkg checkbox, so
            // everything ticked here is genuinely clearable.
            var oids = [];
            $(".fsp-pkg:checked").each(function () {
                oids.push(parseInt($(this).data("oid"), 10));
            });
            var mode = $("#fsp_mode").val();
            var data = {
                action: isDebt ? "clear_debt" : "record_payment", consolidate_id: cid, sender_id: sid,
                orders: JSON.stringify(oids), mode: mode,
                note: String($("#fsp_note").val() || "").trim()
            };
            if (mode === "cash") {
                var raw = String($("#fsp_paid").val() || "").replace(/,/g, "").trim();
                var amt = parseFloat(raw);
                if (raw === "" || isNaN(amt) || amt <= 0) {
                    Swal.showValidationMessage("Enter the amount received (greater than 0).");
                    return false;
                }
                data.paid = amt;
            } else {
                var ref = String($("#fsp_ref").val() || "").trim();
                if (ref === "") {
                    Swal.showValidationMessage("Start the " + mode + " checkout first (it fills the reference).");
                    return false;
                }
                data.reference = ref;
            }
            return $.ajax({
                url: FS_AJAX, method: "POST", dataType: "json", data: data
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
        var nCleared = (r.cleared_orders && r.cleared_orders.length) || 0;
        var lines =
            '<div class="text-left" style="font-size:14px;">' +
            '<p class="mb-1">Received: <b>₵' + Number(r.this_payment).toFixed(2) + '</b> (' + (r.mode || 'cash') + ')</p>' +
            '<p class="mb-1">Total paid: <b>₵' + Number(r.paid_ghs).toFixed(2) + '</b> of ₵' + Number(r.net_ghs).toFixed(2) +
            ' — balance <b>₵' + Number(r.balance_ghs).toFixed(2) + '</b></p>' +
            '<p class="mb-1 text-success">' + nCleared + ' package(s) cleared for delivery.</p>' +
            '<p class="mb-1">Receipt: WhatsApp ' + (r.sent_whatsapp ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') +
            ' &middot; Email ' + (r.sent_email ? '<span class="text-success">sent</span>' : '<span class="text-danger">not sent</span>') + '</p>' +
            ((r.warnings && r.warnings.length)
                ? '<p class="text-muted mt-2 mb-0" style="font-size:12px;">' + r.warnings.join('<br>') + '</p>'
                : '') +
            '</div>';
        Swal.fire({
            icon: "success",
            title: "Payment Recorded",
            html: lines,
            confirmButtonText: "Ok"
        }).then(function () {
            fsApplyConsolSummary(cid, r.consol);
            fsReloadCustomers(cid);
        });
    });
}

/* ---------------------- Customer Actions: discount ---------------------- */
function fsApplyDiscount(el) {
    var $b = $(el);
    var cid = $b.data("cid"), sid = $b.data("sid");
    var name = $b.data("name") || "this customer";
    var billGhs = parseFloat($b.data("bill")) || 0;   // customer's gross bill (GHS)
    var existing = parseFloat($b.data("disc")) || 0;

    var unit = (typeof window.FS_WEIGHT_UNIT !== "undefined") ? window.FS_WEIGHT_UNIT : "lb";
    var cur  = (typeof window.FS_CUR_SYMBOL !== "undefined") ? window.FS_CUR_SYMBOL : "$";
    var genRate = parseFloat(window.FS_WEIGHT_RATE) || 0;

    Swal.fire({
        title: "Discount — " + name,
        html:
            '<div class="text-left" style="font-size:14px;">' +
            '<div class="d-flex justify-content-between mb-2"><span>Customer bill:</span><b>₵' + billGhs.toFixed(2) + '</b></div>' +
            '<label class="mb-1">Discount</label>' +
            '<div class="input-group">' +
            '<span class="input-group-prepend btn-group btn-group-sm" role="group">' +
            '<button type="button" id="fsd_amt" class="btn btn-dark py-0 px-2">₵</button>' +
            '<button type="button" id="fsd_pct" class="btn btn-outline-dark py-0 px-2">%</button>' +
            '<button type="button" id="fsd_rate" class="btn btn-outline-dark py-0 px-2" title="Charge this customer a custom per-' + unit + ' rate">' + cur + '/' + unit + '</button>' +
            '</span>' +
            '<input id="fsd_val" class="form-control" placeholder="0.00" value="' + (existing > 0 ? existing.toFixed(2) : '') + '">' +
            '</div>' +
            '<div class="mt-1">Discount amount: <b id="fsd_preview">₵' + existing.toFixed(2) + '</b></div>' +
            '<div id="fsd_rate_hint" class="text-muted mt-1" style="display:none;font-size:12px;">' +
            'General rate is <b>' + cur + genRate.toFixed(2) + '/' + unit + '</b>. Enter a lower rate for this customer; the difference on their weight-priced items becomes the discount (computed on save).' +
            '</div>' +
            '<label class="mb-1 mt-2">Reason <span class="text-muted">(optional, internal)</span></label>' +
            '<input id="fsd_reason" class="form-control" placeholder="e.g. loyalty, damage, goodwill">' +
            '</div>',
        width: 520,
        showCancelButton: true,
        confirmButtonText: "Save discount",
        showLoaderOnConfirm: true,
        allowOutsideClick: function () { return !Swal.isLoading(); },
        didOpen: function () {
            var type = "amount";
            function preview() {
                if (type === "weight_rate") {
                    $("#fsd_preview").text("computed on save");
                    return;
                }
                var v = parseFloat(String($("#fsd_val").val() || "").replace(/,/g, "")) || 0;
                var amt = (type === "percent") ? (billGhs * Math.min(v, 100) / 100) : v;
                if (amt > billGhs) amt = billGhs;
                $("#fsd_preview").text("₵" + amt.toFixed(2));
            }
            function setActive(btn) {
                $("#fsd_amt,#fsd_pct,#fsd_rate").addClass("btn-outline-dark").removeClass("btn-dark");
                $(btn).addClass("btn-dark").removeClass("btn-outline-dark");
            }
            $("#fsd_val").on("input", preview);
            $("#fsd_amt").on("click", function () {
                type = "amount"; setActive(this);
                $("#fsd_val").attr("placeholder", "0.00"); $("#fsd_rate_hint").hide(); preview();
            });
            $("#fsd_pct").on("click", function () {
                type = "percent"; setActive(this);
                $("#fsd_val").attr("placeholder", "0.00"); $("#fsd_rate_hint").hide(); preview();
            });
            $("#fsd_rate").on("click", function () {
                type = "weight_rate"; setActive(this);
                $("#fsd_val").attr("placeholder", "new " + cur + "/" + unit + " rate").val("");
                $("#fsd_rate_hint").show(); preview();
            });
            $("#fsd_amt").data("get", function () { return type; });
        },
        preConfirm: function () {
            var raw = String($("#fsd_val").val() || "").replace(/,/g, "").trim();
            var v = parseFloat(raw);
            if (raw === "" || isNaN(v) || v <= 0) {
                Swal.showValidationMessage("Enter a value greater than 0.");
                return false;
            }
            var type = $("#fsd_amt").data("get") ? $("#fsd_amt").data("get")() : "amount";
            return $.ajax({
                url: FS_AJAX, method: "POST", dataType: "json",
                data: {
                    action: "apply_discount", consolidate_id: cid, sender_id: sid,
                    disc_type: type, value: v, reason: String($("#fsd_reason").val() || "").trim()
                }
            }).then(function (r) {
                if (!r || !r.ok) {
                    Swal.showValidationMessage((r && r.message) ? r.message : "Could not save the discount.");
                    return false;
                }
                return r;
            }, function () {
                Swal.showValidationMessage("Could not save the discount — server error.");
                return false;
            });
        }
    }).then(function (res) {
        if (!res.isConfirmed || !res.value) return;
        var r = res.value;
        Swal.fire({ icon: "success", title: "Discount applied", html: "Discount: <b>₵" + Number(r.discount_ghs).toFixed(2) + "</b>", confirmButtonText: "Ok" })
            .then(function () {
                if (r.consol) fsApplyConsolSummary(cid, r.consol);
                fsReloadCustomers(cid);
            });
    });
}

function fsRemoveDiscount(el) {
    var $b = $(el);
    var cid = $b.data("cid"), sid = $b.data("sid");
    Swal.fire({
        title: "Remove discount?",
        text: "Remove this customer's discount?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Remove",
        showLoaderOnConfirm: true,
        allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () {
            return $.ajax({
                url: FS_AJAX, method: "POST", dataType: "json",
                data: { action: "remove_discount", consolidate_id: cid, sender_id: sid }
            }).then(function (r) {
                if (!r || !r.ok) {
                    Swal.showValidationMessage((r && r.message) ? r.message : "Could not remove the discount.");
                    return false;
                }
                return r;
            }, function () {
                Swal.showValidationMessage("Could not remove the discount — server error.");
                return false;
            });
        }
    }).then(function (res) {
        if (!res.isConfirmed || !res.value) return;
        var r = res.value;
        if (r.consol) fsApplyConsolSummary(cid, r.consol);
        fsReloadCustomers(cid);
    });
}

/* ------------------------ Customer payment history ---------------------- */
// Customer Actions -> Payment History. A statement of every payment and
// discount recorded against this customer across all consolidations.
// Customer Actions -> Clear Debt. Light payment against an outstanding balance
// once all packages are already cleared for delivery (no package checklist, no
// clearance/status changes — just records the money so the debt drops).
/**
 * Clear Debt — the SAME dialog as Record Payment (see fsOpenPaymentDialog).
 * It used to be a separate hand-rolled Swal with no package list and, more
 * importantly, no gateway checkout: it only offered a bare reference box, so
 * Paystack/Hubtel could not actually be started from it. Loading payment_form
 * and reusing the shared dialog fixes that and keeps the two in step.
 */
function fsClearDebt(el) {
    var $b = $(el);
    var cid = $b.data("cid"), sid = $b.data("sid");
    var name = $b.data("name") || "this customer";

    $.ajax({
        url: FS_AJAX, method: "POST", dataType: "json",
        data: { action: "payment_form", consolidate_id: cid, sender_id: sid }
    }).then(function (f) {
        if (!f || !f.ok) {
            Swal.fire("Cannot clear debt", (f && f.message) ? f.message : "Could not load the packages.", "warning");
            return;
        }
        fsOpenPaymentDialog(cid, sid, name, f, { mode: "debt" });
    }, function () {
        Swal.fire("Error", "Could not load the packages — server error.", "error");
    });
}

function fsPaymentHistory(el) {
    var $b = $(el);
    var sid = $b.data("sid");
    var name = $b.data("name") || "";
    Swal.fire({
        title: "Loading payment history…",
        didOpen: function () { Swal.showLoading(); },
        allowOutsideClick: false
    });
    $.ajax({
        url: FS_AJAX, method: "POST", dataType: "json",
        data: { action: "payment_history", sender_id: sid }
    }).then(function (r) {
        if (!r || !r.ok) {
            Swal.fire({ icon: "error", text: (r && r.message) ? r.message : "Could not load payment history." });
            return;
        }
        Swal.fire({
            title: "Payment History — " + (r.name || name),
            html: r.html,
            width: "48rem",
            confirmButtonText: "Close",
            customClass: { htmlContainer: "text-left" }
        });
        // Respect the current currency toggle on the freshly injected chips.
        if (typeof fsApplyCurrency === "function") { fsApplyCurrency(); }
    }, function () {
        Swal.fire({ icon: "error", text: "Request failed while loading payment history." });
    });
}

/* --------------------- Global per-weight rate (settings) ----------------- */
// FS toolbar -> set the system's per-weight rate (value_weight). Display-only
// at courier add/edit; changed here. Does not touch already-captured bills.
function fsSetWeightRate() {
    var current = (typeof window.FS_WEIGHT_RATE !== "undefined") ? window.FS_WEIGHT_RATE : "";
    var unit = (typeof window.FS_WEIGHT_UNIT !== "undefined") ? window.FS_WEIGHT_UNIT : "";
    Swal.fire({
        title: "Set Weight Rate",
        html:
            '<p style="font-size:13px;margin-bottom:8px;">The price charged per ' +
            $("<span>").text(unit).html() + ' of chargeable weight. This is the system default used ' +
            'when pricing new shipments; existing bills keep their own captured rate.</p>' +
            '<input id="fs_weight_rate_input" class="swal2-input" inputmode="decimal" ' +
            'value="' + $("<span>").text(current).html() + '" placeholder="0.00" ' +
            'style="text-align:center;font-size:20px;font-weight:700;">',
        showCancelButton: true,
        confirmButtonText: "Save Rate",
        showLoaderOnConfirm: true,
        allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () {
            var v = ($("#fs_weight_rate_input").val() || "").replace(/,/g, "").trim();
            if (v === "" || isNaN(parseFloat(v)) || parseFloat(v) <= 0) {
                Swal.showValidationMessage("Enter a rate greater than 0.");
                return false;
            }
            return $.ajax({
                url: FS_AJAX, method: "POST", dataType: "json",
                data: { action: "set_weight_rate", value: v }
            }).then(function (r) {
                if (!r || !r.ok) {
                    Swal.showValidationMessage((r && r.message) ? r.message : "Could not update the rate.");
                    return false;
                }
                return r;
            }, function () {
                Swal.showValidationMessage("Could not update the rate — server error.");
                return false;
            });
        }
    }).then(function (res) {
        if (!res.isConfirmed || !res.value) return;
        window.FS_WEIGHT_RATE = res.value.value;
        var $lbl = $("#fs_weight_rate_label");
        if ($lbl.length) { $lbl.text(res.value.value); }
        Swal.fire({ icon: "success", text: "Weight rate updated.", timer: 1400, showConfirmButton: false });
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

// Package Actions -> Clear Package for Delivery. Sets fs_cleared_for_delivery so
// the warehouse can release it; updates the package chip in place on success.
function fsClearPackage(btn) {
    var $b = $(btn), oid = $b.data("oid"), track = $b.data("track");
    Swal.fire({
        title: "Clear Package for Delivery?",
        html: "Mark <b>" + $("<div>").text(track == null ? "" : track).html() + "</b> as cleared for delivery?",
        icon: "question", showCancelButton: true, reverseButtons: true,
        confirmButtonText: "Yes, Clear", confirmButtonColor: "#1b8a5a",
        showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
        preConfirm: function () {
            return $.ajax({ url: FS_AJAX, method: "POST", data: { action: "clear_package", order_id: oid }, dataType: "json" })
                .then(function (r) {
                    if (!r || !r.ok) { Swal.showValidationMessage((r && r.message) || "Could not clear the package."); return false; }
                    return r;
                }, function () { Swal.showValidationMessage("Request failed."); return false; });
        }
    }).then(function (res) {
        if (!res.isConfirmed) { return; }
        var $card = $b.closest(".fs-pkg-card");
        $card.find(".fs-pkg-unpaid").replaceWith('<span class="fs-pkg-paid fs-chip-settled ml-2" title="Cleared for delivery"><i class="mdi mdi-check-decagram"></i> Cleared</span>');
        // Only action in the menu was Clear — drop the whole Package Actions button.
        $card.find(".fs-pkg-header .fs-pkg-actions").remove();
        if (typeof window.wdRefreshNavBadge === "function") { window.wdRefreshNavBadge(); }
        Swal.fire({ icon: "success", text: "Package cleared for delivery.", timer: 1400, showConfirmButton: false });
    });
}
