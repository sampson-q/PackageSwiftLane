
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



function toggleDriverFields() {
    var userlevelSelect = document.getElementById("userlevel");
    var driverFieldsDiv = document.getElementById("driverFields");

    // Muestra u oculta el div según la opción seleccionada
    driverFieldsDiv.style.display = userlevelSelect.value == "3" ? "block" : "none";
}


// AJAX sweetalert2 guardar

$(document).ready(function() {
    $("#save_user").submit(function(event) {
        event.preventDefault();

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
        var branch_office = $('#branch_office').val();
        var email = $('#email').val();
        var fname = $('#fname').val();
        var lname = $('#lname').val();
        var notes = $('#notes').val();
        var phone = $('#phone').val();
        var gender = $('#gender').val();

        // Validación de userlevel
        var userlevel = $('#userlevel').val();
        if (userlevel === null || userlevel === '') {
            Swal.fire({
                type: 'info',
                title: message_error_form1,
                text: message_error_form20,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });
            return false; // Detiene el envío del formulario
        }

        var password = $('#password').val();
        var notify = $('#notify:checked').val();
        var active = $('input:radio[name=active]:checked').val();
        var newsletter = $('input:radio[name=newsletter]:checked').val();


        // Verifica si userlevel es 3 (Conductor)
        if (userlevel == 3) {
            var vehiclecode = $('#vehiclecode').val();
            var enrollment = $('#enrollment').val();
        }


       // Almacena los nombres de los campos faltantes
        var missingFields = [];

        // Verifica si los campos obligatorios están vacíos y guarda los nombres en el array
        if (!username) missingFields.push(message_error_form7);
        if (!email) missingFields.push(message_error_form8);
        if (!fname) missingFields.push(message_error_form9);
        if (!lname) missingFields.push(message_error_form10);
        if (!phone) missingFields.push(message_error_form11);
        if (!userlevel) missingFields.push('Please select a user level.');

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
            url: "ajax/users/users_add_ajax.php",
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
                        window.location.href = 'users_list.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form17,
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


