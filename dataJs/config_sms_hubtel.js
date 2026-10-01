"use strict";

// Tools > SMS (Hubtel): save the settings and send a test SMS.
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

    function post($form, $btn, onDone) {
        clearErrors($form);
        var label = $btn.text();
        $btn.prop("disabled", true).text("Please Wait...");
        $.ajax({ type: "POST", url: endpoint, data: $form.serialize(), dataType: "json" })
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
            var $badge = $("#sms_status_badge");
            $badge.toggleClass("badge-success", !!r.configured).toggleClass("badge-secondary", !r.configured)
                  .text(r.configured ? "Configured" : "Not Configured");
            var $secret = $("#hubtel_client_secret");
            if ($secret.val() !== "") {
                $secret.val("").attr("placeholder", "••••••••••••");
            }
        });
    });

    $("#sms_test_form").on("submit", function (e) {
        e.preventDefault();
        post($(this), $("#sms_test_btn"));
    });
})();
