
"use strict";
var errorMsg = document.querySelector("#error-msg");
var validMsg = document.querySelector("#valid-msg");

// here, the index maps to the error code returned from getValidationError - see readme
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

// on blur: validate
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


$(function () {
    cdp_load_countries(1);
    cdp_load_states(1);
    cdp_load_cities(1);
});

function cdp_load_countries(count) {

    $("#country" + count).select2({
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: 'json',

            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_country,
        allowClear: true
    }).on('change', function (e) {

        var country = $("#country" + count).val();

        $("#state" + count).attr("disabled", true);
        $("#state" + count).val(null);

        if (country != null) {
            $("#state" + count).attr("disabled", false);
        }

        cdp_load_states(count);
    });
}

function cdp_load_states(count) {
    var country = $("#country" + count).val();

    $("#state" + count).select2({
        ajax: {
            url: "ajax/select2_states.php?id=" + country,
            dataType: 'json',

            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_state,
        allowClear: true
    }).on('change', function (e) {

        var state = $("#state" + count).val();

        $("#city" + count).attr("disabled", true);
        $("#city" + count).val(null);

        if (state != null) {
            $("#city" + count).attr("disabled", false);
        }

        cdp_load_cities(count);
    });
}

function cdp_load_cities(count) {
    var state = $("#state" + count).val();

    $("#city" + count).select2({
        ajax: {
            url: "ajax/select2_cities.php?id=" + state,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_city,
        allowClear: true
    });
}




$(function () {
    var count = 1;

    $(document).on('click', '#add_row', function () {

        count++;

        $('#total_address').val(count);

        var html_code = '';
        var parent = $('#div_parent_' + count);

        html_code += '<div id="div_parent_' + count + '">';
        html_code += '<hr>';

        html_code += '<h4>' + translate_label_address + ' ' + count + '</h4>';

        html_code += '<div class="row">';


        html_code += '<div class="col-md-4 mb-3">' +
            '<div class="form-group" >' +
            '<label class="control-label col-form-label">' + translate_label_country + '</label>' +
            '<select  class="select2 form-control custom-select" name="country[]" id="country' + count + '">' +
            '</select>' +
            '</div >' +
            '</div > ';

        html_code += '<div class="col-md-4 mb-3">' +
            '<div class="form-group" >' +
            '<label class="control-label col-form-label">' + translate_label_state + '</label>' +
            '<select  disabled class="select2 form-control custom-select" name="state[]" id="state' + count + '">' +
            '</select>' +
            '</div >' +
            '</div > ';


        html_code += '<div class="col-md-4 mb-3">' +
            '<div class="form-group" >' +
            '<label class="control-label col-form-label">' + translate_label_city + '</label>' +
            '<select  disabled class="select2 form-control custom-select" name="city[]" id="city' + count + '">' +
            '</select>' +
            '</div >' +
            '</div > ';


        html_code += '<div class="col-md-4">' +
            '<div class="form-group">' +
            '<label class="control-label col-form-label">' + translate_label_zip + '</label>' +
            '<input type="text" class="form-control" name="postal[]" id="postal' + count + '" >' +
            '</div>' +
            '</div>';

        html_code += '<div class="col-md-4">' +
            '<div class="form-group">' +
            '<label class="control-label col-form-label">' + translate_label_address + '</label>' +
            '<input type="text" class="form-control" name="address[]" id="address' + count + '" >' +
            '</div>' +
            '</div>';


        html_code += '<div class="col-md-4">';
        html_code += '   <label> &nbsp;</label>';
        html_code += '  <div class="form-group">';
        html_code += '      <button type="button" name="remove_row" id="' + count + '"  class="btn btn-danger remove_row">' +
            '<span class="fa fa-trash"></span>' + translate_delete_address + '' +
            '</button >' +
            '</div >' +
            '</div >';


        html_code += '</div>'; //div parent

        $('#div_address_multiple').append(html_code);

        cdp_load_countries(count);
        cdp_load_states(count);
        cdp_load_cities(count);
    });



    $(document).on('click', '.remove_row', function () {

        var row_id = $(this).attr("id");
        var parent = $('#div_parent_' + row_id);



        count--;
        parent.fadeOut(400, function () {

            $('#div_parent_' + row_id).remove();

        });
        $('#total_address').val(count);

    });


});


// AJAX REGISTROS DE DATOS DESTINATARIOS

$(document).ready(function() {
    $("#save_user").submit(function(event) {
        event.preventDefault();

        const formData = new FormData(this);
        let formIsValid = true;

        var count = $('#total_address').val();

        // Validación de direcciones
        for (var no = 1; no <= count; no++) {
            var addressField = $('#address' + no);
            var countryField = $('#country' + no);
            var stateField = $('#state' + no);
            var cityField = $('#city' + no);
            var postalField = $('#postal' + no);

            if ($.trim(addressField.val()).length === 0 ||
                $.trim(countryField.val()).length === 0 ||
                $.trim(stateField.val()).length === 0 ||
                $.trim(cityField.val()).length === 0 ||
                $.trim(postalField.val()).length === 0) {
                Swal.fire({
                    type: 'error',
                    title: message_error_form1,
                    text: message_error_form2,
                    confirmButtonColor: '#336aea',
                    showConfirmButton: true,
                });
                addressField.focus();
                return false;
            }
        }

        // Validación de número de teléfono
        if (!iti.isValidNumber()) {
            Swal.fire({
                type: 'error',
                title: message_error_form3,
                text: message_error_form4,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });
            $('#phone_custom').focus();
            return false;
        }

        // Validación de campos obligatorios
        if (!formData.get('fname') || !formData.get('lname') || !formData.get('email')) {
            Swal.fire({
                type: 'error',
                title: 'Oops...',
                text: message_error_form78,
                confirmButtonColor: '#336aea',
            });
            return false;
        }

        // Validación del correo electrónico en el lado del cliente
        var email = $.trim($("#email").val());
        if (!isValidEmailAddress(email)) {
            Swal.fire({
                type: 'warning',
                title: 'Oops...',
                text: message_error_form84,
                icon: 'warning',
                confirmButtonColor: '#336aea'
            });
            $("#email").focus();
            return false;
        }

        // Función para validar el formato del correo electrónico
        function isValidEmailAddress(email) {
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(email);
        }

        // Realizar la solicitud AJAX para verificar si el correo electrónico existe en la base de datos
        $.ajax({
            type: 'POST',
            url: 'ajax/recipients/recipients_check_email_ajax.php',
            data: { email: formData.get('email') },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'error') {
                    Swal.fire({
                        type: 'error',
                        title: 'Error!',
                        text: response.message,
                    });
                    $('#email').addClass('is-invalid');
                } else {
                    // Realizar la solicitud AJAX para guardar los datos
                    $.ajax({
                        url: "./ajax/recipients/recipients_add_ajax.php",
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        cache: false,
                        processData: false,
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    type: 'success',
                                    title: message_error_form15,
                                    showConfirmButton: false,
                                    timer: 1500,
                                    timerProgressBar: true,
                                }).then(() => {
                                    // Redirigir al listado de clientes
                                    window.location.href = 'recipients_list.php';
                                });
                            } else {
                                Swal.fire({
                                    type: 'error',
                                    title: 'Error!',
                                    text: response.message,
                                });
                            }
                        },
                        error: function () {
                            Swal.fire({
                                type: 'error',
                                title: 'Error!',
                                text: message_error_form17,
                            });
                        }
                    });
                }
            },
            error: function () {
                Swal.fire({
                    type: 'error',
                    title: 'Error!',
                    text: message_error_form17,
                });
            }
        });
    });
});

