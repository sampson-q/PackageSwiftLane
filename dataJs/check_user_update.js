"use strict";

var forceProfileState = {
    otpChallengeId: null,
    phoneIntl: null
};

$(function () {
    checkProfileCompletion();
    bindForcedProfileEvents();
});

function checkProfileCompletion() {
    $.ajax({
        type: "POST",
        url: "ajax/check_user_update_ajax.php",
        dataType: "json",
        cache: false,
        success: function (data) {
            var steps = [];

            if (parseInt(data.update_address, 10) === 0) {
                steps.push("address");
            }

            if (parseInt(data.update_phone, 10) === 0) {
                steps.push("phone");
            }

            if (parseInt(data.update_document, 10) === 0) {
                steps.push("document");
            }

            if (steps.length === 0) {
                return;
            }

            runForcedProfileSteps(steps, 0);
        }
    });
}

function runForcedProfileSteps(steps, index) {
    if (index >= steps.length) {
        return;
    }

    var step = steps[index];

    if (step === "address") {
        openAddressModal().then(function (saved) {
            if (saved) {
                runForcedProfileSteps(steps, index + 1);
            }
        });
        return;
    }

    if (step === "phone") {
        openPhoneModal().then(function (saved) {
            if (saved) {
                openOtpModal().then(function (verified) {
                    if (verified) {
                        runForcedProfileSteps(steps, index + 1);
                    }
                });
            }
        });
        return;
    }

    if (step === "document") {
        openDocumentModal().then(function (saved) {
            if (saved) {
                runForcedProfileSteps(steps, index + 1);
            }
        });
        return;
    }
}

function openAddressModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdateAddress");

        $("#force_profile_address_error").text("");

        initForcedAddressSelects();

        $modal.off("hidden.bs.modal.forceAddress");
        $modal.on("hidden.bs.modal.forceAddress", function () {
            resolve($(this).data("saved") === true);
            $(this).removeData("saved");
        });

        $modal.modal({
            backdrop: "static",
            keyboard: false,
            show: true
        });
    });
}

function openPhoneModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdatePhone");

        $("#force_profile_phone_error").text("");
        $("#force_phone_error_msg").text("").addClass("hide");
        $("#force_phone_valid_msg").text("").addClass("hide");
        $("#force_phone_custom").val("");
        $("#force_phone").val("");

        initForcedPhoneInput();

        $modal.off("hidden.bs.modal.forcePhone");
        $modal.on("hidden.bs.modal.forcePhone", function () {
            resolve($(this).data("saved") === true);
            $(this).removeData("saved");
        });

        $modal.modal({
            backdrop: "static",
            keyboard: false,
            show: true
        });
    });
}

function openOtpModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdatePhoneOtp");

        $("#force_profile_phone_otp_error").text("");
        $("#force_phone_otp_code").val("");

        $modal.off("hidden.bs.modal.forceOtp");
        $modal.on("hidden.bs.modal.forceOtp", function () {
            resolve($(this).data("verified") === true);
            $(this).removeData("verified");
        });

        $modal.modal({
            backdrop: "static",
            keyboard: false,
            show: true
        });
    });
}

function openDocumentModal() {
    return new Promise(function (resolve) {
        var $modal = $("#userUpdateDocument");

        $("#force_profile_document_error").text("");

        $modal.off("hidden.bs.modal.forceDocument");
        $modal.on("hidden.bs.modal.forceDocument", function () {
            resolve($(this).data("saved") === true);
            $(this).removeData("saved");
        });

        $modal.modal({
            backdrop: "static",
            keyboard: false,
            show: true
        });
    });
}

function bindForcedProfileEvents() {
    $(document).on("click", "#btn_force_save_address", function () {
        saveForcedAddress();
    });

    $(document).on("click", "#btn_force_save_phone", function () {
        saveForcedPhone();
    });

    $(document).on("click", "#btn_force_verify_phone_otp", function () {
        verifyForcedPhoneOtp();
    });

    $(document).on("click", "#btn_force_resend_phone_otp", function () {
        resendForcedPhoneOtp();
    });

    $(document).on("click", "#btn_force_save_document", function () {
        saveForcedDocument();
    });
}

function initForcedAddressSelects() {
    var $popup = $("#userUpdateAddress");

    var $country = $("#force_country_address");
    var $state = $("#force_state_address");
    var $city = $("#force_city_address");

    destroySelect2IfNeeded($country);
    destroySelect2IfNeeded($state);
    destroySelect2IfNeeded($city);

    $country.select2({
        dropdownParent: $popup,
        width: "100%",
        placeholder: typeof translate_search_country !== "undefined" ? translate_search_country : "Search country",
        allowClear: true,
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        }
    });

    $state.prop("disabled", true).val(null).trigger("change");
    $city.prop("disabled", true).val(null).trigger("change");

    $country.off("change.forceAddress").on("change.forceAddress", function () {
        var countryId = $(this).val();

        $state.prop("disabled", true).val(null).trigger("change");
        $city.prop("disabled", true).val(null).trigger("change");

        destroySelect2IfNeeded($state);
        destroySelect2IfNeeded($city);

        if (countryId) {
            $state.prop("disabled", false);
            $state.select2({
                dropdownParent: $popup,
                width: "100%",
                placeholder: typeof translate_search_state !== "undefined" ? translate_search_state : "Search state",
                allowClear: true,
                ajax: {
                    url: "ajax/select2_states.php?id=" + encodeURIComponent(countryId),
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                }
            });

            $state.off("change.forceState").on("change.forceState", function () {
                var stateId = $(this).val();

                $city.prop("disabled", true).val(null).trigger("change");
                destroySelect2IfNeeded($city);

                if (stateId) {
                    $city.prop("disabled", false);
                    $city.select2({
                        dropdownParent: $popup,
                        width: "100%",
                        placeholder: typeof translate_search_city !== "undefined" ? translate_search_city : "Search city",
                        allowClear: true,
                        ajax: {
                            url: "ajax/select2_cities.php?id=" + encodeURIComponent(stateId),
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return { q: params.term };
                            },
                            processResults: function (data) {
                                return { results: data };
                            },
                            cache: true
                        }
                    });
                }
            });
        }
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

    if (!country || !state || !city || postal.length === 0 || address.length === 0) {
        $("#force_profile_address_error").text("All address fields are required.");
        return;
    }

    $.ajax({
        type: "POST",
        url: "ajax/save_profile_address_ajax.php",
        dataType: "json",
        data: {
            country: country,
            state: state,
            city: city,
            postal: postal,
            address: address
        },
        success: function (resp) {
            if (resp.status === "success") {
                $("#userUpdateAddress").data("saved", true);
                $("#userUpdateAddress").modal("hide");
            } else {
                $("#force_profile_address_error").text(resp.message || "Could not save address.");
            }
        },
        error: function () {
            $("#force_profile_address_error").text("An error occurred while saving the address.");
        }
    });
}

function saveForcedDocument() {
    var documentType = $("#force_document_type").val();
    var documentNumber = $.trim($("#force_document_number").val());
    var documentPhoto = $("#force_document_photo")[0].files[0];

    $("#force_profile_document_error").text("");

    if (!documentType || documentNumber.length === 0) {
        $("#force_profile_document_error").text("Document type and document number are required.");
        return;
    }

    var formData = new FormData();
    formData.append("document_type", documentType);
    formData.append("document_number", documentNumber);

    if (documentPhoto) {
        formData.append("document_photo", documentPhoto);
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
                $("#userUpdateDocument").data("saved", true);
                $("#userUpdateDocument").modal("hide");
            } else {
                $("#force_profile_document_error").text(resp.message || "Could not save document.");
            }
        },
        error: function () {
            $("#force_profile_document_error").text("An error occurred while saving the document.");
        }
    });
}

function initForcedPhoneInput() {
    if (forceProfileState.phoneIntl) {
        return;
    }

    var input = document.querySelector("#force_phone_custom");
    if (!input) {
        return;
    }

    forceProfileState.phoneIntl = window.intlTelInput(input, {
        initialCountry: "auto",
        nationalMode: true,
        separateDialCode: true,
        utilsScript: "assets/template/assets/libs/intlTelInput/utils.js",
        geoIpLookup: function (callback) {
            callback("gh");
        }
    });

    input.addEventListener("blur", function () {
        $("#force_phone_error_msg").text("").addClass("hide");
        $("#force_phone_valid_msg").text("").addClass("hide");

        if (input.value.trim()) {
            if (forceProfileState.phoneIntl.isValidNumber()) {
                $("#force_phone").val(forceProfileState.phoneIntl.getNumber());
                $("#force_phone_valid_msg").text("Valid number").removeClass("hide");
            } else {
                $("#force_phone_error_msg").text("Invalid phone number").removeClass("hide");
            }
        }
    });
}

function saveForcedPhone() {
    $("#force_profile_phone_error").text("");
    $("#force_phone_error_msg").text("").addClass("hide");
    $("#force_phone_valid_msg").text("").addClass("hide");

    if (!forceProfileState.phoneIntl) {
        initForcedPhoneInput();
    }

    if (!forceProfileState.phoneIntl || !forceProfileState.phoneIntl.isValidNumber()) {
        $("#force_profile_phone_error").text("Please enter a valid WhatsApp number.");
        return;
    }

    var phone = forceProfileState.phoneIntl.getNumber();
    $("#force_phone").val(phone);

    $.ajax({
        type: "POST",
        url: "ajax/send_profile_phone_otp_ajax.php",
        dataType: "json",
        data: {
            phone: phone
        },
        success: function (resp) {
            if (resp.status === "success") {
                forceProfileState.otpChallengeId = resp.challenge_id;
                $("#userUpdatePhone").data("saved", true);
                $("#userUpdatePhone").modal("hide");
                setTimeout(function () {
                    $("#userUpdatePhoneOtp").modal({
                        backdrop: "static",
                        keyboard: false,
                        show: true
                    });
                }, 250);
            } else {
                $("#force_profile_phone_error").text(resp.message || "Could not send OTP.");
            }
        },
        error: function () {
            $("#force_profile_phone_error").text("An error occurred while sending the OTP.");
        }
    });
}

function verifyForcedPhoneOtp() {
    var code = $.trim($("#force_phone_otp_code").val());

    $("#force_profile_phone_otp_error").text("");

    if (code.length === 0) {
        $("#force_profile_phone_otp_error").text("Please enter the OTP code.");
        return;
    }

    $.ajax({
        type: "POST",
        url: "ajax/verify_profile_phone_otp_ajax.php",
        dataType: "json",
        data: {
            otp_code: code
        },
        success: function (resp) {
            if (resp.status === "success") {
                $("#userUpdatePhoneOtp").data("verified", true);
                $("#userUpdatePhoneOtp").modal("hide");
                window.location.reload();
            } else {
                $("#force_profile_phone_otp_error").text(resp.message || "OTP verification failed.");
            }
        },
        error: function () {
            $("#force_profile_phone_otp_error").text("An error occurred while verifying the OTP.");
        }
    });
}

function resendForcedPhoneOtp() {
    if (!forceProfileState.phoneIntl) {
        return;
    }

    var phone = forceProfileState.phoneIntl.getNumber();

    $.ajax({
        type: "POST",
        url: "ajax/send_profile_phone_otp_ajax.php",
        dataType: "json",
        data: {
            phone: phone,
            resend: 1
        },
        success: function (resp) {
            if (resp.status === "success") {
                forceProfileState.otpChallengeId = resp.challenge_id;
                $("#force_profile_phone_otp_error").text("A new OTP has been sent.");
            } else {
                $("#force_profile_phone_otp_error").text(resp.message || "Could not resend OTP.");
            }
        },
        error: function () {
            $("#force_profile_phone_otp_error").text("An error occurred while resending the OTP.");
        }
    });
}