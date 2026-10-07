"use strict";

// Tools > SMS (Hubtel + mNotify): save the settings, send a test SMS through
// one provider, check the mNotify balance.
// ajax/tools/config_sms_hubtel_ajax.php answers JSON {success, message, errors?}.

(function () {
    var endpoint = "ajax/tools/config_sms_hubtel_ajax.php";

    function notify(ok, message) {
        if (window.Swal && typeof Swal.fire === "function") {
            Swal.fire({ icon: ok ? "success" : "error", title: ok ? "Done" : "Not Saved", text: message });
        } else {
            alert(message);
        }
    }

    function clearErrors($form) {
        $form.find(".is-invalid").removeClass("is-invalid");
        $form.find(".invalid-feedback.cdp-sms").remove();
    }

    function showErrors($form, errors) {
        $.each(errors || {}, function (field, text) {
            var $f = $form.find("[name='" + field + "']");
            if (!$f.length) { return; }
            $f.addClass("is-invalid");
            $("<div class='invalid-feedback cdp-sms d-block'></div>").text(text).insertAfter($f.is(":checkbox") ? $f.closest("label") : $f);
        });
    }

    function post($form, $btn, onDone, data) {
        clearErrors($form);
        var label = $btn.text();
        $btn.prop("disabled", true).text("Please Wait...");
        $.ajax({ type: "POST", url: endpoint, data: data || $form.serialize(), dataType: "json" })
            .done(function (r) {
                if (!r || !r.success) {
                    showErrors($form, r && r.errors);
                    notify(false, (r && r.message) || "Something went wrong.");
                    return;
                }
                notify(true, r.message);
                if (onDone) { onDone(r); }
            })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                notify(false, r.message || ("Request failed (HTTP " + xhr.status + ")."));
            })
            .always(function () { $btn.prop("disabled", false).text(label); });
    }

    $("#save_sms_settings").on("submit", function (e) {
        e.preventDefault();
        var $form = $(this);
        post($form, $("#sms_save_btn"), function (r) {
            $.each(r.providers || {}, function (p, s) {
                var $badge = $("[data-sms-badge='" + p + "']");
                var cls  = !s.configured ? "badge-secondary" : (s.enabled ? "badge-success" : "badge-warning");
                var text = !s.configured ? "Not Configured" : (s.enabled ? "On" : "Off");
                $badge.removeClass("badge-secondary badge-success badge-warning").addClass(cls).text(text);
            });
            $("#hubtel_client_secret, #mnotify_api_key").each(function () {
                if ($(this).val() !== "") {
                    $(this).val("").attr("placeholder", "••••••••••••");
                }
            });
        });
    });

    $("#sms_test_form").on("submit", function (e) {
        e.preventDefault();
        post($(this), $("#sms_test_btn"));
    });

    // Checks the typed API key (or the stored one when the field is blank).
    $("#mnotify_balance_btn").on("click", function () {
        var $form = $("#save_sms_settings");
        post($form, $(this), null, {
            action: "mnotify_balance",
            _csrf_token: $form.find("[name='_csrf_token']").val(),
            mnotify_api_key: $("#mnotify_api_key").val()
        });
    });
})();
