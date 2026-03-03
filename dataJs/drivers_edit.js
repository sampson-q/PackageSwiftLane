
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
    // hiddenInput: "full_number",
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


//Update user admin


$("#edit_user").on("submit", function (event) {
      


  if (iti.isValidNumber()) {


    $("#save_data").attr("disabled", true);
    var parametros = $(this).serialize();
    var vehiclecode = $("#vehiclecode").val();
    var enrollment = $("#enrollment").val();
    var username = $("#username").val();
    var email = $("#email").val();
    var fname = $("#fname").val();
    var lname = $("#lname").val();
    var notes = $("#notes").val();
    var phone = $("#phone").val();
    var gender = $("#gender").val();
    var password = $("#password").val();
    var active = $("input:radio[name=active]:checked").val();
    var newsletter = $("input:radio[name=newsletter]:checked").val();
    var id = $("#id").val();

    var userlevel = $('#userlevel').val();


    // Almacena los nombres de los campos faltantes
    var missingFields = [];

    // Verifica si los campos obligatorios están vacíos y guarda los nombres en el array
    if (!email) missingFields.push(message_error_form8);
    if (!fname) missingFields.push(message_error_form9);
    if (!lname) missingFields.push(message_error_form10);
    if (!phone) missingFields.push(message_error_form11);

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

    var data = new FormData();


    data.append('enrollment', enrollment);
    data.append('vehiclecode', vehiclecode);
    data.append('username', username);
    data.append('password', password);
    data.append('fname', fname);
    data.append('lname', lname);
    data.append('email', email);
    data.append('phone', phone);
    data.append('gender', gender);
    data.append('active', active);
    data.append('newsletter', newsletter);
    data.append('notes', notes);
    data.append('id', id);


    $.ajax({
      type: "POST",
      url: "ajax/drivers/drivers_edit_ajax.php",
      data: data,
      contentType: false,
      cache: false,
      processData: false,
      beforeSend: function (objeto) {

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
                    // Redirigir al mismo sitio
                    window.location.href = window.location.href;
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
        },

    });

  }

  } else {
    input.classList.add("error");
    var errorCode = iti.getValidationError();
    errorMsg.innerHTML = errorMap[errorCode];
    errorMsg.classList.remove("hide");
  }
  event.preventDefault();
});


// update avatar

$(document).ready(function() {
    $('#edit_avatar_form').on('submit', function(event) {
        event.preventDefault(); // Evita que el formulario se envíe de forma convencional
        updateAvatar();
    });

    function updateAvatar() {
        var formData = new FormData($('#edit_avatar_form')[0]);

        $.ajax({
            type: 'POST',
            url: './ajax/users/users_avatar_edit_ajax.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // Manejar la respuesta del servidor
                 if (response.success) {
                    // Mostrar SweetAlert2 de éxito
                    Swal.fire({
                        type: 'success',
                        title: 'Avatar updated',
                        text: response.message
                    }).then(() => {
                        // Redirigir al mismo sitio
                        window.location.href = window.location.href;
                    });
                } else {
                    // Mostrar SweetAlert2 de error
                    Swal.fire({
                         type: 'error',
                        title: 'Avatar Update Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                // Manejar errores de conexión u otros errores
                Swal.fire({
                    type: 'error',
                    title: 'Error',
                    text: 'Connection or processing error on the server.'
                });
            }
        });
    }
});

