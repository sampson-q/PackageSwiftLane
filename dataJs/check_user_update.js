"use strict";

/**
 * Customer account setup + WhatsApp number self-service.
 *
 * 1. Forced onboarding (runs on every page for customers): reads the
 *    checklist in cdb_user_details_update_check and walks the customer through
 *    the steps still at 0 — address, WhatsApp number, ID document (the document
 *    step can be skipped: it is optional).
 * 2. Re-usable WhatsApp flow, also used by the "My Profile" page:
 *       window.cdpProfileWhatsAppFlow(phoneOnFile) -> Promise<boolean>
 *    Asks "Is <number> the WhatsApp number you use?" — Yes sends a code to it,
 *    No opens the entry modal for another number. When one-time codes are
 *    switched off system-wide the server stores the number immediately and the
 *    code modal is skipped (response.otp_required === false).
 *
 * Modals live in views/modals/modal_user_update_*.php (included by footer.php).
 */

var forceProfileState = {
    otpChallengeId: null,
    phoneIntl: null,
    currentPhone: "",
    pendingPhone: "",
    otpVerified: false
};

$(function () {
    checkProfileCompletion();
    bindForcedProfileEvents();
});

function fpEscape(text) {
    return $("<div>").text(text || "").html();
}

// ─────────────────────────────────────────────────────────────────────────────
// Forced onboarding
// ─────────────────────────────────────────────────────────────────────────────

function checkProfileCompletion() {
    $.ajax({
        type: "POST",
        url: "ajax/check_user_update_ajax.php",
        dataType: "json",
        cache: false,
        success: function (data) {
            forceProfileState.currentPhone = (data.phone || "").toString();

            var steps = [];
            if (parseInt(data.update_address, 10) === 0) { steps.push("address"); }
            if (parseInt(data.update_phone, 10) === 0) { steps.push("phone"); }
            if (parseInt(data.update_document, 10) === 0) { steps.push("document"); }

            if (steps.length === 0) { return; }
            runForcedProfileSteps(steps, 0);
        }
    });
}

function runForcedProfileSteps(steps, index) {
    if (index >= steps.length) {
        // Everything done — refresh so the topbar / profile reflect the changes.
        window.location.reload();
        return;
    }

    var step = steps[index];
    var next = function (ok) {
        if (ok) { runForcedProfileSteps(steps, index + 1); }
    };

    if (step === "address") {
        openAddressModal().then(next);
    } else if (step === "phone") {
        startWhatsappVerification(forceProfileState.currentPhone).then(next);
    } else if (step === "document") {
        openDocumentModal().then(next);
    }
}

function openAddressModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdateAddress");
        $("#force_profile_address_error").text("");
        initForcedAddressSelects();

        $modal.off("hidden.bs.modal.forceAddress").on("hidden.bs.modal.forceAddress", function () {
            resolve($(this).data("saved") === true);
            $(this).removeData("saved");
        });
        $modal.modal({ backdrop: "static", keyboard: false, show: true });
    });
}

function openDocumentModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdateDocument");
        $("#force_profile_document_error").text("");

        $modal.off("hidden.bs.modal.forceDocument").on("hidden.bs.modal.forceDocument", function () {
            resolve($(this).data("saved") === true);
            $(this).removeData("saved");
        });
        $modal.modal({ backdrop: "static", keyboard: false, show: true });
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// WhatsApp number: confirm the number on file → code (or direct save)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send the code (or save directly when OTP is off) for `phone`.
 * Resolves true when the number is verified/saved, false when abandoned.
 */
function sendPhoneAndVerify(phone) {
    return new Promise(function (resolve) {
        forceProfileState.pendingPhone = phone;
        $.ajax({
            type: "POST",
            url: "ajax/send_profile_phone_otp_ajax.php",
            dataType: "json",
            data: { phone: phone },
            success: function (resp) {
                if (resp.status !== "success") {
                    Swal.fire({
                        icon: "error",
                        title: "Couldn't send the code",
                        text: resp.message || "That number couldn't receive a WhatsApp message. Please use another number.",
                        allowOutsideClick: false
                    }).then(function () { resolve(null); }); // null = let the caller offer another number
                    return;
                }
                if (resp.otp_required === false) {
                    // OTP switched off system-wide: already saved.
                    forceProfileState.currentPhone = phone;
                    resolve(true);
                    return;
                }
                forceProfileState.otpChallengeId = resp.challenge_id;
                openOtpModal(resp.masked || phone).then(function (verified) {
                    if (verified) { forceProfileState.currentPhone = phone; }
                    resolve(verified === true);
                });
            },
            error: function (xhr) {
                var msg = "Something went wrong sending the code. Please try again.";
                try { msg = (xhr.responseJSON || JSON.parse(xhr.responseText)).message || msg; } catch (e) {}
                Swal.fire({ icon: "error", title: "Error", text: msg, allowOutsideClick: false }).then(function () { resolve(null); });
            }
        });
    });
}

function askForNewNumber() {
    return openPhoneModal().then(function (phone) {
        if (!phone) { return false; }
        return sendPhoneAndVerify(phone).then(function (result) {
            if (result === null) { return askForNewNumber(); } // send failed → try another number
            return result;
        });
    });
}

function startWhatsappVerification(phone) {
    phone = (phone || "").trim();

    if (!phone) {
        return askForNewNumber();
    }

    return Swal.fire({
        title: "WhatsApp number",
        html: "Is <b>" + fpEscape(phone) + "</b> the WhatsApp number you receive messages on?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, this is my number",
        cancelButtonText: "No, use another number",
        confirmButtonColor: "#28a745",
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(function (result) {
        if (!result.isConfirmed) {
            return askForNewNumber();
        }
        return sendPhoneAndVerify(phone).then(function (r) {
            if (r === null) { return askForNewNumber(); }
            return r;
        });
    });
}

// Public entry points for the profile page.
window.cdpProfileWhatsAppFlow = function (phoneOnFile) {
    return startWhatsappVerification(phoneOnFile || forceProfileState.currentPhone);
};
window.cdpProfileChangeWhatsAppNumber = function () {
    return askForNewNumber();
};

/** Resolves with the E.164 number entered, or "" when the modal is abandoned. */
function openPhoneModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdatePhone");

        $("#force_profile_phone_error").text("");
        $("#force_phone_error_msg").text("").addClass("hide");
        $("#force_phone_valid_msg").text("").addClass("hide");
        $("#force_phone_custom").val("");
        $("#force_phone").val("");

        initForcedPhoneInput();

        $modal.off("hidden.bs.modal.forcePhone").on("hidden.bs.modal.forcePhone", function () {
            var phone = $(this).data("phone") || "";
            $(this).removeData("phone");
            resolve(phone);
        });
        $modal.modal({ backdrop: "static", keyboard: false, show: true });
    });
}

function openOtpModal(maskedNumber) {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdatePhoneOtp");

        forceProfileState.otpVerified = false;
        $("#force_profile_phone_otp_error").text("");
        $("#force_phone_otp_code").val("");
        if (maskedNumber) {
            $("#force_profile_phone_otp_error").removeClass("text-danger").addClass("text-muted")
                .text("Enter the 6-digit code sent to " + maskedNumber + " on WhatsApp.");
        }

        $modal.off("hidden.bs.modal.forceOtp").on("hidden.bs.modal.forceOtp", function () {
            resolve(forceProfileState.otpVerified === true);
        });
        $modal.modal({ backdrop: "static", keyboard: false, show: true });
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// Modal buttons
// ─────────────────────────────────────────────────────────────────────────────

function bindForcedProfileEvents() {
    $(document).on("click", "#btn_force_save_address", saveForcedAddress);
    $(document).on("click", "#btn_force_save_phone", saveForcedPhone);
    $(document).on("click", "#btn_force_verify_phone_otp", verifyForcedPhoneOtp);
    $(document).on("click", "#btn_force_resend_phone_otp", resendForcedPhoneOtp);
    $(document).on("click", "#btn_force_save_document", function () { saveForcedDocument(false); });
    $(document).on("click", "#btn_force_skip_document", function () { saveForcedDocument(true); });
    $(document).on("keypress", "#force_phone_otp_code", function (e) {
        if (e.which === 13) { e.preventDefault(); verifyForcedPhoneOtp(); }
    });
}

// ── Address ──────────────────────────────────────────────────────────────────

function initForcedAddressSelects() {
    var $popup = $("#userUpdateAddress");
    var $country = $("#force_country_address");
    var $state = $("#force_state_address");
    var $city = $("#force_city_address");

    destroySelect2IfNeeded($country);
    destroySelect2IfNeeded($state);
    destroySelect2IfNeeded($city);

    var ajaxOpts = function (url) {
        return {
            url: url,
            dataType: "json",
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        };
    };

    $country.select2({
        dropdownParent: $popup, width: "100%", allowClear: true,
        placeholder: typeof translate_search_country !== "undefined" ? translate_search_country : "Search country",
        ajax: ajaxOpts("ajax/select2_countries.php")
    });

    $state.prop("disabled", true).val(null).trigger("change");
    $city.prop("disabled", true).val(null).trigger("change");

    $country.off("change.forceAddress").on("change.forceAddress", function () {
        var countryId = $(this).val();
        $state.prop("disabled", true).val(null).trigger("change");
        $city.prop("disabled", true).val(null).trigger("change");
        destroySelect2IfNeeded($state);
        destroySelect2IfNeeded($city);

        if (!countryId) { return; }
        $state.prop("disabled", false).select2({
            dropdownParent: $popup, width: "100%", allowClear: true,
            placeholder: typeof translate_search_state !== "undefined" ? translate_search_state : "Search state",
            ajax: ajaxOpts("ajax/select2_states.php?id=" + encodeURIComponent(countryId))
        });

        $state.off("change.forceState").on("change.forceState", function () {
            var stateId = $(this).val();
            $city.prop("disabled", true).val(null).trigger("change");
            destroySelect2IfNeeded($city);
            if (!stateId) { return; }
            $city.prop("disabled", false).select2({
                dropdownParent: $popup, width: "100%", allowClear: true,
                placeholder: typeof translate_search_city !== "undefined" ? translate_search_city : "Search city",
                ajax: ajaxOpts("ajax/select2_cities.php?id=" + encodeURIComponent(stateId))
            });
        });
    });
}

function destroySelect2IfNeeded($el) {
    if ($el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
}

function saveForcedAddress() {
    var country = $("#force_country_address").val();
    var state = $("#force_state_address").val();
    var city = $("#force_city_address").val();
    var postal = $.trim($("#force_zip_address").val());
    var address = $.trim($("#force_full_address").val());

    $("#force_profile_address_error").text("");
    if (!country || !state || !city || !postal || !address) {
        $("#force_profile_address_error").text("All address fields are required.");
        return;
    }

    $.ajax({
        type: "POST",
        url: "ajax/save_profile_address_ajax.php",
        dataType: "json",
        data: { country: country, state: state, city: city, postal: postal, address: address },
        success: function (resp) {
            if (resp.status === "success") {
                $("#userUpdateAddress").data("saved", true).modal("hide");
            } else {
                $("#force_profile_address_error").text(resp.message || "Could not save address.");
            }
        },
        error: function () {
            $("#force_profile_address_error").text("An error occurred while saving the address.");
        }
    });
}

// ── Document (optional) ──────────────────────────────────────────────────────

function saveForcedDocument(skip) {
    var documentType = $("#force_document_type").val();
    var documentNumber = $.trim($("#force_document_number").val());
    var documentPhoto = $("#force_document_photo")[0] ? $("#force_document_photo")[0].files[0] : null;

    $("#force_profile_document_error").text("");

    var formData = new FormData();
    if (skip || (!documentType && !documentNumber && !documentPhoto)) {
        formData.append("skip", "1");
    } else {
        if ((documentType && !documentNumber) || (!documentType && documentNumber)) {
            $("#force_profile_document_error").text("Please provide both the document type and the document number, or skip this step.");
            return;
        }
        formData.append("document_type", documentType);
        formData.append("document_number", documentNumber);
        if (documentPhoto) { formData.append("document_photo", documentPhoto); }
    }

    $.ajax({
        type: "POST",
        url: "ajax/save_profile_document_ajax.php",
        data: formData,
        dataType: "json",
        contentType: false,
        processData: false,
        success: function (resp) {
            if (resp.status === "success") {
                $("#userUpdateDocument").data("saved", true).modal("hide");
            } else {
                $("#force_profile_document_error").text(resp.message || "Could not save document.");
            }
        },
        error: function () {
            $("#force_profile_document_error").text("An error occurred while saving the document.");
        }
    });
}

// ── Phone entry ──────────────────────────────────────────────────────────────

function initForcedPhoneInput() {
    if (forceProfileState.phoneIntl) { return; }
    var input = document.querySelector("#force_phone_custom");
    if (!input || typeof window.intlTelInput !== "function") { return; }

    forceProfileState.phoneIntl = window.intlTelInput(input, {
        initialCountry: "gh",
        nationalMode: true,
        separateDialCode: true,
        utilsScript: "assets/template/assets/libs/intlTelInput/utils.js"
    });

    input.addEventListener("blur", function () {
        $("#force_phone_error_msg").text("").addClass("hide");
        $("#force_phone_valid_msg").text("").addClass("hide");
        if (!input.value.trim()) { return; }
        if (forceProfileState.phoneIntl.isValidNumber()) {
            $("#force_phone").val(forceProfileState.phoneIntl.getNumber());
            $("#force_phone_valid_msg").text("Valid number").removeClass("hide");
        } else {
            $("#force_phone_error_msg").text("Invalid phone number").removeClass("hide");
        }
    });
}

/** "Continue" on the entry modal: validate and hand the number back. */
function saveForcedPhone() {
    $("#force_profile_phone_error").text("");
    if (!forceProfileState.phoneIntl) { initForcedPhoneInput(); }

    var phone = "";
    if (forceProfileState.phoneIntl && forceProfileState.phoneIntl.isValidNumber()) {
        phone = forceProfileState.phoneIntl.getNumber();
    } else {
        // utils.js not loaded (offline) → accept a plausible international number
        var raw = $.trim($("#force_phone_custom").val()).replace(/[^\d+]/g, "");
        if (/^\+?\d{7,15}$/.test(raw) && raw.indexOf("+") === 0) { phone = raw; }
    }

    if (!phone) {
        $("#force_profile_phone_error").text("Please enter a valid WhatsApp number with its country code.");
        return;
    }

    $("#force_phone").val(phone);
    $("#userUpdatePhone").data("phone", phone).modal("hide");
}

// ── Code entry ───────────────────────────────────────────────────────────────

function verifyForcedPhoneOtp() {
    var code = $.trim($("#force_phone_otp_code").val());
    var $err = $("#force_profile_phone_otp_error").removeClass("text-muted").addClass("text-danger").text("");

    if (code.length === 0) {
        $err.text("Please enter the code.");
        return;
    }

    var $btn = $("#btn_force_verify_phone_otp").prop("disabled", true);
    $.ajax({
        type: "POST",
        url: "ajax/verify_profile_phone_otp_ajax.php",
        dataType: "json",
        data: { otp_code: code },
        success: function (resp) {
            if (resp.status === "success") {
                forceProfileState.otpVerified = true;
                if (resp.phone) { forceProfileState.currentPhone = resp.phone; }
                $("#userUpdatePhoneOtp").modal("hide");
            } else {
                $err.text(resp.message || "Verification failed.");
            }
        },
        error: function () {
            $err.text("An error occurred while verifying the code.");
        },
        complete: function () { $btn.prop("disabled", false); }
    });
}

function resendForcedPhoneOtp() {
    var phone = forceProfileState.pendingPhone || forceProfileState.currentPhone || "";
    var $err = $("#force_profile_phone_otp_error");
    if (!phone) { return; }

    $.ajax({
        type: "POST",
        url: "ajax/send_profile_phone_otp_ajax.php",
        dataType: "json",
        data: { phone: phone, resend: 1 },
        success: function (resp) {
            if (resp.status === "success") {
                if (resp.otp_required === false) {
                    forceProfileState.otpVerified = true;
                    $("#userUpdatePhoneOtp").modal("hide");
                    return;
                }
                forceProfileState.otpChallengeId = resp.challenge_id;
                $err.removeClass("text-danger").addClass("text-muted").text("A new code has been sent.");
            } else {
                $err.removeClass("text-muted").addClass("text-danger").text(resp.message || "Could not resend the code.");
            }
        },
        error: function () {
            $err.removeClass("text-muted").addClass("text-danger").text("An error occurred while resending the code.");
        }
    });
}
