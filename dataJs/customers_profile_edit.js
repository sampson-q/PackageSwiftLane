"use strict";

/**
 * Customer "My Profile" page.
 *
 *  - profile photo     -> ajax/customers/customers_avatar_edit_ajax.php
 *  - ID document       -> ajax/save_profile_document_ajax.php   (optional)
 *  - WhatsApp number   -> handled by dataJs/check_user_update.js (confirm the
 *                         number on file, then a one-time code unless OTP is off)
 *  - everything else   -> ajax/customers/customers_profile_edit_ajax.php
 *
 * Every response is JSON; every failure is shown with its real reason.
 */

var cdpProfileCfg = window.cdpProfile || { isOwn: false, userId: 0, phone: "" };

function cdpProfileT(key, fallback) {
    return (typeof window[key] !== "undefined" && window[key]) ? window[key] : fallback;
}

function cdpProfileAlert(icon, title, text) {
    return Swal.fire({
        icon: icon,
        title: title,
        text: text || "",
        confirmButtonColor: "#336aea"
    });
}

function cdpProfileLoading(title) {
    Swal.fire({
        title: title || cdpProfileT("message_error_form6", "Processing..."),
        text: cdpProfileT("message_error_form14", "Please wait..."),
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: function () { Swal.showLoading(); },
        onBeforeOpen: function () { Swal.showLoading(); }
    });
}

function cdpProfileParse(xhr) {
    try {
        return xhr.responseJSON || JSON.parse(xhr.responseText || "{}");
    } catch (e) {
        return {};
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Address selects (country → state → city), one set per address block
// ─────────────────────────────────────────────────────────────────────────────

function cdpSelect2Ajax($el, url, placeholder) {
    if ($el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
    $el.select2({
        width: "100%",
        placeholder: placeholder,
        allowClear: true,
        ajax: {
            url: url,
            dataType: "json",
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        }
    });
}

function cdp_load_countries(n) {
    var $country = $("#country" + n);
    cdpSelect2Ajax($country, "ajax/select2_countries.php", cdpProfileT("translate_search_country", "Search country"));
    $country.off("change.profile").on("change.profile", function () {
        var country = $(this).val();
        $("#state" + n).val(null).trigger("change.select2");
        $("#city" + n).val(null).trigger("change.select2");
        $("#state" + n).prop("disabled", !country);
        $("#city" + n).prop("disabled", true);
        cdp_load_states(n);
    });
}

function cdp_load_states(n) {
    var country = $("#country" + n).val();
    var $state = $("#state" + n);
    cdpSelect2Ajax($state, "ajax/select2_states.php?id=" + encodeURIComponent(country || ""), cdpProfileT("translate_search_state", "Search state"));
    $state.prop("disabled", !country);
    $state.off("change.profile").on("change.profile", function () {
        var state = $(this).val();
        $("#city" + n).val(null).trigger("change.select2");
        $("#city" + n).prop("disabled", !state);
        cdp_load_cities(n);
    });
}

function cdp_load_cities(n) {
    var state = $("#state" + n).val();
    var $city = $("#city" + n);
    cdpSelect2Ajax($city, "ajax/select2_cities.php?id=" + encodeURIComponent(state || ""), cdpProfileT("translate_search_city", "Search city"));
    $city.prop("disabled", !state);
}

$(function () {
    var count = parseInt($("#count_address").val(), 10) || 0;
    for (var n = 1; n <= count; n++) {
        cdp_load_countries(n);
        cdp_load_states(n);
        cdp_load_cities(n);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// Add / remove address blocks
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    var count = parseInt($("#count_address").val(), 10) || 0;

    $(document).on("click", "#add_row", function () {
        count++;
        $("#total_address").val(count);

        var req = ' <span class="required-mark">*</span>';
        var html = '';
        html += '<div id="div_parent_' + count + '" class="address-block">';
        html += '<h5>' + cdpProfileT("translate_label_address", "Address") + ' ' + count + '</h5>';
        html += '<div class="row">';
        html += '<div class="col-md-4 mb-3"><div class="form-group"><label class="control-label col-form-label">' + cdpProfileT("translate_label_country", "Country") + req + '</label>' +
                '<select class="select2 form-control custom-select" name="country[]" id="country' + count + '"></select></div></div>';
        html += '<div class="col-md-4 mb-3"><div class="form-group"><label class="control-label col-form-label">' + "State" + req + '</label>' +
                '<select disabled class="select2 form-control custom-select" name="state[]" id="state' + count + '"></select></div></div>';
        html += '<div class="col-md-4 mb-3"><div class="form-group"><label class="control-label col-form-label">' + cdpProfileT("translate_label_city", "City") + req + '</label>' +
                '<select disabled class="select2 form-control custom-select" name="city[]" id="city' + count + '"></select></div></div>';
        html += '<div class="col-md-4"><div class="form-group"><label class="control-label col-form-label">' + cdpProfileT("translate_label_zip", "Zip Code") + req + '</label>' +
                '<input type="text" class="form-control" name="postal[]" id="postal' + count + '"></div></div>';
        html += '<div class="col-md-4"><div class="form-group"><label class="control-label col-form-label">' + cdpProfileT("translate_label_address", "Address") + req + '</label>' +
                '<input type="text" class="form-control" name="address[]" id="address' + count + '"></div></div>';
        html += '<input type="hidden" name="address_id[]" id="address_id' + count + '" value="">';
        html += '<div class="col-md-4"><label>&nbsp;</label><div class="form-group">' +
                '<button type="button" name="remove_row" id="' + count + '" class="btn btn-danger remove_row"><span class="fa fa-trash"></span> ' + cdpProfileT("translate_delete_address", "Delete Address") + '</button>' +
                '</div></div>';
        html += '</div></div>';

        $("#div_address_multiple").append(html);
        cdp_load_countries(count);
        cdp_load_states(count);
        cdp_load_cities(count);
    });

    function confirmDelete(addressId, rowId) {
        Swal.fire({
            title: cdpProfileT("translate_delete_address", "Delete Address"),
            html: '<p class="messi-warning">' + cdpProfileT("message_delete_confirm", "Are you sure?") + '<br /><strong>' + cdpProfileT("message_delete_confirm2", "") + '</strong></p>',
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#336aea",
            cancelButtonColor: "#eb644c",
            confirmButtonText: cdpProfileT("message_delete_confirm1", "Yes, delete it"),
            showLoaderOnConfirm: true,
            preConfirm: function () {
                return $.ajax({
                    type: "post",
                    url: "./ajax/customers/customers_delete_address_ajax.php",
                    dataType: "json",
                    data: { id: addressId }
                }).catch(function (xhr) {
                    Swal.showValidationMessage(cdpProfileParse(xhr).message || "Could not delete the address.");
                });
            }
        }).then(function (result) {
            if (!result.value) { return; }
            if (result.value.success) {
                $("#div_parent_" + rowId).fadeOut(300, function () { $(this).remove(); });
            } else {
                var errs = result.value.errors;
                var text = (errs && typeof errs === "object") ? Object.values(errs).join(" ") : (errs || "Could not delete the address.");
                cdpProfileAlert("error", cdpProfileT("message_error_form18", "Error"), text);
            }
        });
    }

    $(document).on("click", ".remove_row", function () {
        var rowId = $(this).attr("id");
        var addressId = $("#address_id" + rowId).val();
        if (addressId) {
            confirmDelete(addressId, rowId);
        } else {
            $("#div_parent_" + rowId).fadeOut(300, function () { $(this).remove(); });
        }
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Profile details form
// ─────────────────────────────────────────────────────────────────────────────

function cdpProfileClearErrors() {
    $("#edit_user .form-group").removeClass("has-error");
    $("#edit_user .field-error-message").remove();
}

function cdpProfileFieldError($field, message) {
    $field.closest(".form-group").addClass("has-error");
    var $target = $field.next(".select2").length ? $field.next(".select2") : $field;
    $target.after('<span class="field-error-message text-danger">' + message + '</span>');
}

$("#edit_user").on("submit", function (event) {
    event.preventDefault();
    cdpProfileClearErrors();

    var problems = [];
    var fname = $.trim($("#fname").val());
    var lname = $.trim($("#lname").val());
    var email = $.trim($("#email").val());
    var gender = $("#gender").val();
    var password = $("#password").val();

    if (fname.length < 2) {
        cdpProfileFieldError($("#fname"), "Your name is required.");
        problems.push(cdpProfileT("message_error_form9", "Name"));
    }
    if (lname.length < 2) {
        cdpProfileFieldError($("#lname"), "Your last name is required.");
        problems.push(cdpProfileT("message_error_form10", "Last Name"));
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        cdpProfileFieldError($("#email"), "A valid email address is required.");
        problems.push(cdpProfileT("message_error_form8", "Email"));
    }
    if (!gender) {
        cdpProfileFieldError($("#gender"), "Please select your gender.");
        problems.push("Gender");
    }
    if (password && password.length < 6) {
        cdpProfileFieldError($("#password"), "Password must be at least 6 characters (leave empty to keep the current one).");
        problems.push("Password");
    }

    // Addresses: at least one block, every field of every block filled.
    var blocks = $('[id^="div_parent_"]');
    if (blocks.length === 0) {
        problems.push("At least one address");
    }
    blocks.each(function () {
        var n = this.id.replace("div_parent_", "");
        var ok = true;
        $.each(["country", "state", "city"], function (i, k) {
            if (!$("#" + k + n).val()) { cdpProfileFieldError($("#" + k + n), "Required."); ok = false; }
        });
        $.each(["postal", "address"], function (i, k) {
            if (!$.trim($("#" + k + n).val())) { cdpProfileFieldError($("#" + k + n), "Required."); ok = false; }
        });
        if (!ok) { problems.push("Address " + n + " (all fields)"); }
    });

    if (problems.length) {
        Swal.fire({
            icon: "error",
            title: cdpProfileT("message_error_form1", "Missing information"),
            html: "<p>" + cdpProfileT("message_error_form5", "Please complete the following:") + "</p><ul class='text-left'><li>" + problems.join("</li><li>") + "</li></ul>",
            confirmButtonColor: "#336aea"
        });
        return false;
    }

    var data = new FormData();
    data.append("id", $("#profile_user_id").val());
    data.append("fname", fname);
    data.append("lname", lname);
    data.append("email", email);
    data.append("gender", gender);
    data.append("password", password);
    data.append("notes", $("#notes").val());
    data.append("_csrf_token", $('#edit_user input[name="_csrf_token"]').val());

    // Address blocks are posted by index so removed rows never shift the others.
    var idx = 0;
    blocks.each(function () {
        var n = this.id.replace("div_parent_", "");
        data.append("address_id[" + idx + "]", $("#address_id" + n).val() || "");
        data.append("country[" + idx + "]", $("#country" + n).val());
        data.append("state[" + idx + "]", $("#state" + n).val());
        data.append("city[" + idx + "]", $("#city" + n).val());
        data.append("postal[" + idx + "]", $.trim($("#postal" + n).val()));
        data.append("address[" + idx + "]", $.trim($("#address" + n).val()));
        idx++;
    });
    data.append("total_address", idx);

    var $btn = $("#save_data").prop("disabled", true);

    $.ajax({
        type: "POST",
        url: "ajax/customers/customers_profile_edit_ajax.php",
        data: data,
        dataType: "json",
        contentType: false,
        cache: false,
        processData: false,
        beforeSend: function () { cdpProfileLoading(); },
        success: function (response) {
            Swal.close();
            if (response.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: cdpProfileT("message_error_form15", "Saved"),
                    text: response.message || "",
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true
                }).then(function () { window.location.href = window.location.href; });
            } else {
                if (response.errors && typeof response.errors === "object") {
                    $.each(response.errors, function (field, msg) {
                        if ($("#" + field).length) { cdpProfileFieldError($("#" + field), msg); }
                    });
                }
                cdpProfileAlert("error", cdpProfileT("message_error_form18", "Could not save"), response.message || cdpProfileT("message_error_form17", "Please review the form."));
            }
        },
        error: function (xhr) {
            Swal.close();
            var r = cdpProfileParse(xhr);
            cdpProfileAlert("error", cdpProfileT("message_error_form18", "Error"), r.message || cdpProfileT("message_error_form19", "Connection error. Please try again."));
        },
        complete: function () { $btn.prop("disabled", false); }
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Profile photo
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    $("#avatarInput").on("change", function () {
        var file = this.files && this.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function (e) { $("#avatarPreview").attr("src", e.target.result); };
        reader.readAsDataURL(file);
        $("#avatarSubmitBtn").prop("disabled", false).attr("title", "Save the new photo");
    });

    $("#edit_avatar_form").on("submit", function (event) {
        event.preventDefault();
        if (!$("#avatarInput")[0].files.length) {
            cdpProfileAlert("info", "Choose a photo", "Click the photo to choose an image first.");
            return;
        }
        var $btn = $("#avatarSubmitBtn").prop("disabled", true);
        $.ajax({
            type: "POST",
            url: "./ajax/customers/customers_avatar_edit_ajax.php",
            data: new FormData(this),
            dataType: "json",
            contentType: false,
            processData: false,
            beforeSend: function () { cdpProfileLoading("Uploading photo..."); },
            success: function (response) {
                Swal.close();
                if (response.success) {
                    if (response.avatar_url) {
                        // Update everywhere the photo is shown without a reload.
                        $("#avatarPreview, .pro-pic img, .user-pro img, .user-profile img").attr("src", response.avatar_url);
                    }
                    $("#avatarInput").val("");
                    cdpProfileAlert("success", "Photo updated", response.message || "");
                } else {
                    $btn.prop("disabled", false);
                    cdpProfileAlert("error", "Photo not updated", response.message || "");
                }
            },
            error: function (xhr) {
                Swal.close();
                $btn.prop("disabled", false);
                cdpProfileAlert("error", "Photo not updated", cdpProfileParse(xhr).message || "Connection or processing error on the server.");
            }
        });
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ID document (optional)
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    function enableDocSave() {
        $("#documentSubmitBtn").prop("disabled", false).attr("title", "Save the document");
    }

    $("#documentInput").on("change", function () {
        var file = this.files && this.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            $("#documentPreview").attr("src", e.target.result);
            $("#documentViewBtn").attr("href", e.target.result).removeClass("d-none");
        };
        reader.readAsDataURL(file);
        enableDocSave();
    });
    $("#document_type, #document_number").on("change input", enableDocSave);

    $("#edit_document_form").on("submit", function (event) {
        event.preventDefault();

        var type = $("#document_type").val();
        var number = $.trim($("#document_number").val());
        var hasFile = $("#documentInput")[0].files.length > 0;

        if ((type && !number) || (!type && number)) {
            cdpProfileAlert("error", "Incomplete document", "Please provide both the document type and the document number, or leave both empty.");
            return;
        }
        if (!type && !number && !hasFile) {
            cdpProfileAlert("info", "Nothing to save", "The document is optional. Add a type and number, or a photo, then save.");
            return;
        }

        var $btn = $("#documentSubmitBtn").prop("disabled", true);
        $.ajax({
            type: "POST",
            url: "./ajax/save_profile_document_ajax.php",
            data: new FormData(this),
            dataType: "json",
            contentType: false,
            processData: false,
            beforeSend: function () { cdpProfileLoading("Saving document..."); },
            success: function (response) {
                Swal.close();
                if (response.status === "success") {
                    if (response.document_url) {
                        $("#documentPreview").attr("src", response.document_url);
                        $("#documentViewBtn").attr("href", response.document_url).removeClass("d-none");
                    }
                    $("#documentInput").val("");
                    cdpProfileAlert("success", "Document saved", response.message || "");
                } else {
                    $btn.prop("disabled", false);
                    cdpProfileAlert("error", "Document not saved", response.message || "");
                }
            },
            error: function (xhr) {
                Swal.close();
                $btn.prop("disabled", false);
                cdpProfileAlert("error", "Document not saved", cdpProfileParse(xhr).message || "Connection or processing error on the server.");
            }
        });
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// WhatsApp number (confirm the number on file first, then a code)
// ─────────────────────────────────────────────────────────────────────────────

$(function () {
    $("#btn_change_whatsapp").on("click", function () {
        if (!cdpProfileCfg.isOwn) { return; }
        if (typeof window.cdpProfileWhatsAppFlow !== "function") {
            cdpProfileAlert("error", "Unavailable", "The WhatsApp verification module did not load. Please refresh the page.");
            return;
        }
        window.cdpProfileWhatsAppFlow(cdpProfileCfg.phone).then(function (done) {
            if (done) {
                Swal.fire({
                    icon: "success",
                    title: "WhatsApp number saved",
                    showConfirmButton: false,
                    timer: 1400
                }).then(function () { window.location.href = window.location.href; });
            }
        });
    });
});
