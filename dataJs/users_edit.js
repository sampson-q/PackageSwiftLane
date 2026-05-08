"use strict";
var deleted_file_ids = [];

var errorMsg = document.querySelector("#error-msg");
var validMsg = document.querySelector("#valid-msg");

// Error map for international tel input validation
var errorMap = [
    "Invalid number",
    "Invalid country code",
    "Mobile number too short",
    "Mobile number too long",
    "Invalid mobile number",
];

var input = document.querySelector("#phone_custom");
var iti = window.intlTelInput(input, {
    geoIpLookup: function (callback) {
        $.get("http://ipinfo.io", function () { }, "jsonp").always(function (resp) {
            var countryCode = (resp && resp.country) ? resp.country : "";
            callback(countryCode);
        });
    },
    initialCountry: "auto",
    nationalMode: true,
    separateDialCode: true,
    utilsScript: "assets/template/assets/libs/intlTelInput/utils.js",
});

var reset = function () {
    input.classList.remove("error");
    errorMsg.innerHTML = "";
    errorMsg.classList.add("hide");
    validMsg.classList.add("hide");
};

// on blur: validate phone number
input.addEventListener('blur', function () {
    reset();
    if (input.value.trim()) {
        if (iti.isValidNumber()) {
            $('#phone').val(iti.getNumber());
            validMsg.classList.remove("hide");
        } else {
            input.classList.add("error");
            var errorCode = iti.getValidationError();
            errorMsg.innerHTML = errorMap[errorCode];
            errorMsg.classList.remove("hide");
        }
    }
});

// on keyup / change flag: reset
input.addEventListener('change', reset);
input.addEventListener('keyup', reset);

// ============================================================
// CLEAR FIELD ERRORS
// ============================================================

function clearAllFieldErrors() {
    // Remove error classes
    $('.form-group').removeClass('has-error');
    // Remove inline error messages
    $('.field-error-message').remove();
}

function showFieldError(fieldName, errorMessage) {
    var field = $('[name="' + fieldName + '"]');
    if (field.length) {
        field.closest('.form-group').addClass('has-error');
        field.after('<span class="field-error-message text-danger" style="display:block; font-size:0.875rem; margin-top:5px;">' + errorMessage + '</span>');
    }
}

// ============================================================
// UPDATE USER FORM SUBMISSION
// ============================================================

$("#edit_user").on("submit", function (event) {
    event.preventDefault();

    // Validate phone if it was changed
    if (input.value.trim() && !iti.isValidNumber()) {
        input.classList.add("error");
        var errorCode = iti.getValidationError();
        errorMsg.innerHTML = errorMap[errorCode];
        errorMsg.classList.remove("hide");
        return;
    }

    // Clear previous errors
    clearAllFieldErrors();

    // Disable submit button
    var submitBtn = $(this).find('button[type="submit"]');
    submitBtn.prop('disabled', true);

    // Collect all form data
    var username = $("#username").val();
    var branch_office = $('#branch_office').val();
    var email = $("#email").val();
    var fname = $("#fname").val();
    var lname = $("#lname").val();
    var notes = $("#notes").val();
    var phone = $("#phone").val();
    var gender = $("#gender").val();
    var userlevel = $("#userlevel").val();
    var password = $("#password").val();
    var active = $("input:radio[name=active]:checked").val();
    var newsletter = $("input:radio[name=newsletter]:checked").val();
    var id = $("#id").val();

    // If super admin is being edited, use role dropdown
    if ($('#role').length && $('#role').is('select')) {
        userlevel = $('#role').val();
    }

    var data = new FormData();

    data.append("username", username);
    data.append("branch_office", branch_office);
    data.append("password", password);
    data.append("fname", fname);
    data.append("lname", lname);
    data.append("email", email);
    data.append("phone", phone);
    data.append("gender", gender);
    data.append("active", active);
    data.append("newsletter", newsletter);
    data.append("notes", notes);
    data.append("id", id);
    data.append("userlevel", userlevel);
    data.append('_csrf_token', $('input[name="_csrf_token"]').val());

    // Make AJAX request
    $.ajax({
        type: "POST",
        url: "ajax/users/users_edit_ajax.php",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        beforeSend: function () {
            Swal.fire({
                title: message_error_form6 || 'Processing...',
                text: message_error_form14 || 'Please wait...',
                type: 'info',
                showCancelButton: false,
                showConfirmButton: false,
                allowOutsideClick: false,
                onBeforeOpen: () => {
                    Swal.showLoading();
                },
            });
        },

        success: function(response) {
            Swal.close();
            submitBtn.prop('disabled', false);

            if (response.status === 'success') {
                // SUCCESS: Show confirmation and reload
                Swal.fire({
                    type: 'success',
                    title: message_error_form15 || 'Success!',
                    text: response.message || 'User updated successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                }).then(() => {
                    window.location.href = window.location.href;
                });
            } else {
                // ERROR: Show validation errors or generic error
                if (response.errors && typeof response.errors === 'object' && Object.keys(response.errors).length > 0) {
                    // Display field-specific errors
                    $.each(response.errors, function(fieldName, errorMessage) {
                        showFieldError(fieldName, errorMessage);
                    });

                    Swal.fire({
                        type: 'error',
                        title: 'Validation Error',
                        html: '<p>' + (response.message || 'Please correct the errors highlighted in the form.') + '</p>',
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                } else {
                    // Generic error message
                    Swal.fire({
                        type: 'error',
                        title: message_error_form18 || 'Error',
                        text: response.message || 'An error occurred while updating the user.',
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            }
        },

        error: function(xhr, status, error) {
            Swal.close();
            submitBtn.prop('disabled', false);

            var errorMessage = 'Connection error. Please try again.';

            // Try to parse error response
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // Could not parse
            }

            Swal.fire({
                type: 'error',
                title: message_error_form18 || 'Error',
                text: errorMessage,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });
        },

        complete: function() {
            submitBtn.prop('disabled', false);
        }
    });
});

// ============================================================
// UPDATE AVATAR
// ============================================================

$(document).ready(function() {
    $('#edit_avatar_form').on('submit', function(event) {
        event.preventDefault();
        updateAvatar();
    });

    function updateAvatar() {
        var formData = new FormData($('#edit_avatar_form')[0]);
        var submitBtn = $('#edit_avatar_form').find('button[type="submit"]');
        submitBtn.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: './ajax/users/users_avatar_edit_ajax.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                submitBtn.prop('disabled', false);

                if (response.success) {
                    Swal.fire({
                        type: 'success',
                        title: 'Avatar Updated',
                        text: response.message || 'Avatar updated successfully.',
                        showConfirmButton: false,
                        timer: 1500,
                    }).then(() => {
                        window.location.href = window.location.href;
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: 'Update Failed',
                        text: response.message || 'Failed to update avatar.',
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function() {
                submitBtn.prop('disabled', false);
                Swal.fire({
                    type: 'error',
                    title: 'Error',
                    text: 'Connection error on the server.',
                    confirmButtonColor: '#336aea',
                    showConfirmButton: true,
                });
            }
        });
    }
});

// ============================================================
// DELETE ATTACHED FILES
// ============================================================

function cdp_deleteImgAttached(id) {
    var parent = $('#file_delete_item_' + id);
    new Messi('<p class="messi-warning"><i class="icon-warning-sign icon-3x pull-left"></i>' + message_delete_confirm + '<br /><strong>' + message_delete_confirm2 + '</strong></p>', {
        title: 'Delete file',
        titleClass: '',
        modal: true,
        closeButton: true,
        buttons: [{
            id: 0,
            label: message_delete_confirm1,
            class: '',
            val: 'Y'
        }],
        callback: function (val) {
            if (val === 'Y') {
                $.ajax({
                    type: 'post',
                    url: './ajax/users/users_files_uploads_delete_ajax.php',
                    data: {
                        'id': id,
                    },
                    beforeSend: function () {
                        parent.animate({
                            'backgroundColor': '#FFBFBF'
                        }, 400);
                        parent.remove();
                    },
                    success: function (data) {
                        $('#resultados_ajax_delete_file').html(data);
                    }
                });
            }
        }
    });
}

// ============================================================
// FILE UPLOAD PREVIEW
// ============================================================

function cdp_preview_images() {
    $('#image_preview').html("");
    var total_file = document.getElementById("filesMultiple").files.length;

    for (var i = 0; i < total_file; i++) {
        var mime_type = event.target.files[i].type.split("/");
        var src = "";

        if (mime_type[0] == "image") {
            src = URL.createObjectURL(event.target.files[i]);
        } else {
            src = 'assets/images/no-preview.jpeg';
        }

        $('#image_preview').append(
            '<div class="col-md-6" id="image_' + i + '">' +
            '<img style="width: 180px; height: 180px;" class="img-thumbnail" src="' + src + '">' +
            '<div class="row"><div class="col-md-12 mt-2 mb-2"><span>' + event.target.files[i].name + '</span></div></div>' +
            '<div class="row"><div class="mb-2"><button type="button" class="btn btn-danger btn-sm pull-left" onclick="cdp_deletePreviewImage(' + i + ');"><i class="fa fa-trash"></i></button></div></div>' +
            '</div>'
        );
    }
}

function cdp_deletePreviewImage(index) {
    deleted_file_ids.push(index);
    $('#deleted_file_ids').val(deleted_file_ids);
    $('#image_' + index).remove();

    var count_files = $('#total_item_files').val();
    count_files--;
    $('#total_item_files').val(count_files);

    if (count_files > 0) {
        $('#clean_files').removeClass('hide');
    } else {
        $('#clean_files').addClass('hide');
    }

    $('#selectItem').html('attached files (' + count_files + ')');
}

// ============================================================
// FILE SIZE VALIDATION
// ============================================================

function cdp_validateZiseFiles() {
    var inputFile = document.getElementById('filesMultiple');
    var file = inputFile.files;
    var size = 0;

    for (var i = 0; i < file.length; i++) {
        size += file[i].size;
    }

    if (size > 5242880) {
        $('.resultados_file').html(
            "<div class='alert alert-danger'>" +
            "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
            "<strong>" + (validation_files_size || 'File size exceeds limit') + " </strong>" +
            "</div>"
        );
        $("#filesMultiple").val('');
        $('#clean_files').addClass('hide');
        $('#image_preview').html("");
        return true;
    } else {
        $('.resultados_file').html("");
        return false;
    }
}

// ============================================================
// FILE UPLOAD EVENT HANDLERS
// ============================================================

$('#openMultiFile').on('click', function () {
    $("#filesMultiple").click();
});

$('#clean_file_button').on('click', function () {
    $("#filesMultiple").val('');
    $('#selectItem').html('Attach files');
    $('#clean_files').addClass('hide');
    $('#image_preview').html("");
    $('.resultados_file').html("");
});

function verifyCountFiles() {
    deleted_file_ids = [];
    var inputFile = document.getElementById('filesMultiple');
    var file = inputFile.files;
    var contador = 0;

    for (var i = 0; i < file.length; i++) {
        contador++;
    }

    $('#total_item_files').val(contador);
    var count_files = $('#total_item_files').val();

    if (count_files > 0) {
        $('#clean_files').removeClass('hide');
    } else {
        $('#clean_files').addClass('hide');
    }

    $('#selectItem').html('attached files (' + count_files + ')');
}