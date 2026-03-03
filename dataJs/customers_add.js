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



// AJAX sweetalert2 guardar

$(document).ready(function() {
    $("#save_user").submit(function(event) {
        event.preventDefault();

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


        // Obtén los valores de los campos
        var username = $('#username').val();
        var email = $('#email').val();
        var fname = $('#fname').val();
        var lname = $('#lname').val();
        var notes = $('#notes').val();
        var phone = $('#phone').val();
        var gender = $('#gender').val();
        var locker = $('#locker').val();
        var password = $('#password').val();
        var notify = $('#notify:checked').val();
        var active = $('input:radio[name=active]:checked').val();
        var newsletter = $('input:radio[name=newsletter]:checked').val();
        var total_address = $('#total_address').val();
        var document_type = $('#document_type').val();
        var document_number = $('#document_number').val();

       // Almacena los nombres de los campos faltantes
        var missingFields = [];

        // Verifica si los campos obligatorios están vacíos y guarda los nombres en el array
        if (!username) missingFields.push(message_error_form7);
        if (!email) missingFields.push(message_error_form8);
        if (!fname) missingFields.push(message_error_form9);
        if (!lname) missingFields.push(message_error_form10);
        if (!phone) missingFields.push(message_error_form11);
        if (!document_type) missingFields.push(message_error_form12);
        if (!document_number) missingFields.push(message_error_form13);

        // Verifica si hay campos faltantes
        if (missingFields.length > 0) {
            // Construye el mensaje de alerta
            var alertMessage = message_error_form5;
            alertMessage += '\n\n- ' + missingFields.join('\n- ');

            // Muestra el mensaje de alerta
            Swal.fire({
                type: 'error',
                title: message_error_form1,
                text: alertMessage,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });

        } else { 

        // Configurar objeto FormData
        var data = new FormData(this);

      

        // Realizar la solicitud AJAX
        $.ajax({
            url: "./ajax/customers/customers_add_ajax.php",
            type: 'POST',
            data: data,
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                Swal.fire({
                    title: message_error_form6,
                    text: message_error_form14,
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
                if (response.status === 'success') {
                    Swal.fire({
                        type: 'success',
                        title: message_error_form15,
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true,
                    }).then(() => {
                        // Redirigir al listado de clientes
                        window.location.href = 'customers_list.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form15,
                        text: response.message || message_error_form17,
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    type: 'error',
                    title: message_error_form18,
                    text: message_error_form19,
                    confirmButtonColor: '#336aea',
                    showConfirmButton: true,
                });
            }
        });

      }

    });
    
});




