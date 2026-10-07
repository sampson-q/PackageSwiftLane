"use strict";

// Pickup codes — loaded on every page by views/inc/footer.php.
// Server side: helpers/pickup_code.php, ajax/pickup_code/pickup_code_ajax.php.
//
//   cdpPickupCode.open({module, orderIds, context, onVerified})  the code modal
//   cdpPickupCode.ensure({module, orderIds, context})            Promise, resolves once verified
//   cdpPickupCode.list()                                         codes alive right now
//
// Markup hooks:
//   .cdp-pickup-code[data-module][data-order-ids][data-context]   opens the code modal
//   .cdp-pickup-code-list                                         opens the list modal
//   <form data-pickup-module data-pickup-id="#field" data-pickup-status="8"
//         | data-pickup-status-field="[name=status_courier]">       asks for a verified
//         code before a Delivered / Picked up submit reaches the page's own handler.

(function ($) {
    if (!$ || window.cdpPickupCode) { return; }

    var URL = "ajax/pickup_code/pickup_code_ajax.php";
    var GATED = [8, 15];
    var timers = [];

    function esc(s) {
        return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }
    function mmss(sec) {
        sec = Math.max(0, Math.floor(sec));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ":" + (s < 10 ? "0" : "") + s;
    }
    function post(data) {
        return $.ajax({ url: URL, method: "POST", data: data, dataType: "json" })
            .then(null, function (xhr) {
                var r = xhr.responseJSON || {};
                return $.Deferred().resolve({ success: false, message: r.message || ("Request failed (HTTP " + xhr.status + ").") });
            });
    }
    function ids(list) {
        return $.map(String(list || "").split(","), function (v) { v = parseInt(v, 10); return v > 0 ? v : null; });
    }
    function clearTimers() {
        $.each(timers, function (_, t) { clearInterval(t); });
        timers = [];
    }

    // ── Modal shells (built once, on first use) ──────────────────────────────
    function shell(id, title, wide) {
        var $m = $("#" + id);
        if ($m.length) { return $m; }
        $m = $(
            '<div class="modal fade" id="' + id + '" tabindex="-1" role="dialog" aria-hidden="true">' +
              '<div class="modal-dialog modal-dialog-centered' + (wide ? " modal-lg" : "") + '" role="document">' +
                '<div class="modal-content">' +
                  '<div class="modal-header"><h5 class="modal-title"><i class="fas fa-key"></i>&nbsp;' + esc(title) + '</h5>' +
                  '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>' +
                  '<div class="modal-body"></div>' +
                  '<div class="modal-footer"></div>' +
                '</div>' +
              '</div>' +
            '</div>'
        ).appendTo(document.body);
        $m.on("hidden.bs.modal", clearTimers);
        // A modal that finished hiding just before this one opened strips the body lock.
        $m.on("shown.bs.modal", function () { $(document.body).addClass("modal-open"); });
        return $m;
    }

    /** Bootstrap 4 drops a show() while another modal is still fading out: wait for it. */
    function show($m) {
        var $busy = $(".modal").not($m).filter(function () {
            var d = $(this).data("bs.modal");
            return d && d._isTransitioning;
        });
        if ($busy.length) {
            $busy.one("hidden.bs.modal", function () { $m.modal("show"); });
        } else {
            $(".modal.show").not($m).modal("hide");
            $m.modal("show");
        }
    }

    // ── The code modal ───────────────────────────────────────────────────────
    function open(opts) {
        var module = opts.module === "sea" ? "sea" : "air";
        var orderIds = $.isArray(opts.orderIds) ? opts.orderIds : ids(opts.orderIds);
        var context = opts.context || "";
        var onVerified = typeof opts.onVerified === "function" ? opts.onVerified : null;
        var $m = shell("cdpPickupCodeModal", "Pickup Code");
        var $body = $m.find(".modal-body"), $foot = $m.find(".modal-footer");
        var verifiedFired = false;

        if (!orderIds.length) { return; }
        clearTimers();
        $body.html('<div class="text-center text-muted py-3">Loading...</div>');
        $foot.empty();
        show($m);

        function header(s) {
            return '<p class="mb-1"><b>Customer:</b> ' + esc(s.owner || "N/A") + '</p>' +
                   '<p class="mb-3"><b>' + (s.trackings && s.trackings.length > 1 ? "Packages" : "Package") + ':</b> ' + esc((s.trackings || []).join(", ")) + '</p>';
        }

        function render(s) {
            clearTimers();
            if (s.verified) {
                $body.html(header(s) + '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i>&nbsp;Pickup code verified. The package can be handed over.</div>');
                $foot.html('<button type="button" class="btn btn-success" data-act="done">' + (onVerified ? "Continue" : "Close") + '</button>');
                return;
            }
            if (s.live) {
                var left = s.live.expires_in;
                $body.html(header(s) +
                    '<p class="mb-1 small text-muted">Sent to: ' + esc((s.live.channels || []).join(", ") || "the customer") + '</p>' +
                    '<p class="mb-3">Code expires in <b class="cdp-pc-left">' + mmss(left) + '</b></p>' +
                    '<div class="form-group mb-1"><label for="cdp_pc_input">Code The Customer Received</label>' +
                    '<input type="text" inputmode="numeric" maxlength="6" autocomplete="off" class="form-control form-control-lg text-center" id="cdp_pc_input" style="letter-spacing:6px;font-weight:700;"></div>' +
                    '<div class="cdp-pc-msg small text-danger"></div>');
                $foot.html('<button type="button" class="btn btn-outline-secondary" data-act="resend" disabled>Send New Code</button>' +
                           '<button type="button" class="btn btn-dark" data-act="verify">Verify Code</button>');
                setTimeout(function () { $("#cdp_pc_input").trigger("focus"); }, 300);
                var t0 = Date.now();
                timers.push(setInterval(function () {
                    var now = left - (Date.now() - t0) / 1000;
                    $body.find(".cdp-pc-left").text(mmss(now));
                    if (now <= 0) {
                        clearTimers();
                        $body.find(".cdp-pc-left").text("0:00");
                        $body.find(".cdp-pc-msg").text("This code has expired. A new one can be sent now.");
                        $foot.find("[data-act=verify]").prop("disabled", true);
                        $foot.find("[data-act=resend]").prop("disabled", false);
                    }
                }, 500));
                return;
            }
            $body.html(header(s) + '<p class="mb-0 text-muted">No code has been sent for this collection yet. The code goes to the customer by SMS, WhatsApp and e-mail and is valid for ' + Math.round((s.ttl || 180) / 60) + ' minutes.</p>' +
                '<div class="cdp-pc-msg small text-danger mt-2"></div>');
            $foot.html('<button type="button" class="btn btn-dark" data-act="send">Send Pickup Code</button>');
        }

        function load() {
            post({ action: "state", module: module, order_ids: orderIds }).then(function (r) {
                if (!r.success) {
                    $body.html('<div class="alert alert-danger mb-0">' + esc(r.message) + '</div>');
                    return;
                }
                $m.data("cdpState", r);
                render(r);
            });
        }

        function send($btn) {
            $btn.prop("disabled", true).text("Sending...");
            post({ action: "generate", module: module, order_ids: orderIds, context: context }).then(function (r) {
                if (!r.success && !r.live) {
                    $btn.prop("disabled", false).text("Send Pickup Code");
                    $body.find(".cdp-pc-msg").text(r.message);
                    return;
                }
                load();
            });
        }

        $m.off("click.cdppc").on("click.cdppc", "[data-act]", function () {
            var act = $(this).data("act");
            if (act === "send" || act === "resend") { send($(this)); }
            if (act === "verify") {
                var $btn = $(this), s = $m.data("cdpState") || {};
                var code = $.trim($("#cdp_pc_input").val());
                if (!/^\d{6}$/.test(code)) { $body.find(".cdp-pc-msg").text("Enter the 6-digit code."); return; }
                $btn.prop("disabled", true).text("Checking...");
                post({ action: "verify", code_id: s.live ? s.live.code_id : 0, code: code }).then(function (r) {
                    $btn.prop("disabled", false).text("Verify Code");
                    if (!r.success) { $body.find(".cdp-pc-msg").text(r.message); return; }
                    load();
                });
            }
            if (act === "done") {
                var cb = onVerified;
                onVerified = null;
                $m.modal("hide");
                if (cb && !verifiedFired) { verifiedFired = true; cb(); }
            }
        });
        $m.off("keydown.cdppc").on("keydown.cdppc", "#cdp_pc_input", function (e) {
            if (e.key === "Enter") { e.preventDefault(); $foot.find("[data-act=verify]").trigger("click"); }
        });

        load();
    }

    /** Resolves once every package has a verified code (opens the modal when needed). */
    function ensure(opts) {
        var d = $.Deferred();
        var module = opts.module === "sea" ? "sea" : "air";
        var orderIds = $.isArray(opts.orderIds) ? opts.orderIds : ids(opts.orderIds);
        post({ action: "state", module: module, order_ids: orderIds }).then(function (r) {
            if (r.success && r.verified) { d.resolve(); return; }
            open({ module: module, orderIds: orderIds, context: opts.context, onVerified: function () { d.resolve(); } });
        });
        return d.promise();
    }

    // ── Codes alive right now ────────────────────────────────────────────────
    function list() {
        var $m = shell("cdpPickupCodeListModal", "Pickup Codes", true);
        var $body = $m.find(".modal-body");
        $m.find(".modal-footer").html('<small class="text-muted mr-auto">Waiting: time left to enter the code. Verified: time left to hand the package over.</small>' +
            '<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>');
        clearTimers();
        show($m);

        function load() {
            post({ action: "list" }).then(function (r) {
                if (!r.success) { $body.html('<div class="alert alert-danger mb-0">' + esc(r.message) + '</div>'); return; }
                if (!r.codes.length) { $body.html('<div class="text-center text-muted py-4">No pickup code is active right now.</div>'); return; }
                var t0 = Date.now();
                clearTimers();
                var rows = $.map(r.codes, function (c) {
                    return '<tr>' +
                        '<td>' + esc(c.owner) + '</td>' +
                        '<td class="small">' + esc(c.trackings) + '</td>' +
                        '<td><b style="letter-spacing:3px;">' + esc(c.code) + '</b></td>' +
                        '<td><span class="badge ' + (c.status === "Verified" ? "badge-success" : "badge-warning") + '">' + esc(c.status) + '</span></td>' +
                        '<td class="cdp-pc-count" data-left="' + c.seconds + '">' + mmss(c.seconds) + '</td>' +
                        '<td class="small">' + esc(c.sent_by) + '</td>' +
                    '</tr>';
                }).join("");
                $body.html('<div class="table-responsive"><table class="table table-sm table-hover mb-0">' +
                    '<thead><tr><th>Customer</th><th>Packages</th><th>Code</th><th>Status</th><th>Time Left</th><th>Sent By</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody></table></div>');
                timers.push(setInterval(function () {
                    $body.find(".cdp-pc-count").each(function () {
                        var left = parseFloat($(this).data("left")) - (Date.now() - t0) / 1000;
                        $(this).text(mmss(left));
                        if (left <= 0) { $(this).closest("tr").addClass("text-muted"); }
                    });
                }, 1000));
            });
        }
        load();
        // Refresh while open: new codes appear, used ones drop out.
        clearInterval($m.data("cdpRefresh"));
        $m.data("cdpRefresh", setInterval(function () {
            if ($m.hasClass("show")) { load(); } else { clearInterval($m.data("cdpRefresh")); }
        }, 15000));
    }

    window.cdpPickupCode = { open: open, ensure: ensure, list: list };

    // Row actions (delegated: list responses re-render their rows).
    $(document).on("click", ".cdp-pickup-code", function (e) {
        e.preventDefault();
        var $a = $(this);
        open({ module: $a.data("module"), orderIds: ids($a.attr("data-order-ids")), context: $a.data("context") });
    });
    $(document).on("click", ".cdp-pickup-code-list", function (e) {
        e.preventDefault();
        list();
    });

    // Forms that hand a package over: ask for the verified code first. Capture
    // phase, so this runs before the page's own submit handler.
    document.addEventListener("submit", function (e) {
        var form = e.target;
        if (!form || !form.getAttribute || !form.getAttribute("data-pickup-module")) { return; }
        if (form.getAttribute("data-pickup-ok") === "1") { form.removeAttribute("data-pickup-ok"); return; }
        var status = form.getAttribute("data-pickup-status");
        if (!status) {
            var f = form.querySelector(form.getAttribute("data-pickup-status-field") || "[name=status_courier]");
            status = f ? f.value : "";
        }
        if (GATED.indexOf(parseInt(status, 10)) === -1) { return; }
        var idField = form.querySelector(form.getAttribute("data-pickup-id"));
        var orderId = idField ? parseInt(idField.value, 10) : 0;
        if (!(orderId > 0)) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        ensure({ module: form.getAttribute("data-pickup-module"), orderIds: [orderId], context: form.getAttribute("data-pickup-context") || "deliver_form" })
            .then(function () {
                form.setAttribute("data-pickup-ok", "1");
                if (typeof form.requestSubmit === "function") { form.requestSubmit(); } else { $(form).trigger("submit"); }
            });
    }, true);
})(window.jQuery);
