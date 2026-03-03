"use strict";
var deleted_file_ids = [];

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


//Update user admin


$("#edit_user").on("submit", function (event) {
      


  if (iti.isValidNumber()) {


    $("#save_data").attr("disabled", true);
    var parametros = $(this).serialize();

    var username = $("#username").val();
    var branch_office = $('#branch_office').val();
    var email = $("#email").val();
    var fname = $("#fname").val();
    var lname = $("#lname").val();
    var notes = $("#notes").val();
    var phone = $("#phone").val();
    var gender = $("#gender").val();
    var userlevel = $("#userlevel").val();
    if (userlevel == 9 || userlevel == 2) {

    }
    var password = $("#password").val();
    var notify = $("#notify:checked").val();
    var active = $("input:radio[name=active]:checked").val();
    var newsletter = $("input:radio[name=newsletter]:checked").val();
    var id = $("#id").val();

    var userlevel = $('#userlevel').val();
    // Si existe el select #role (solo al editar un Super Admin), usar su valor
    if ($('#role').length && $('#role').is('select')) {
        userlevel = $('#role').val();
    }

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
    data.append("notify", notify);
    data.append("id", id);

    // Siempre enviar rol/userlevel (Administrator, Driver, Employee, Agency, etc.)
    data.append("userlevel", userlevel);
 

    $.ajax({
      type: "POST",
      url: "ajax/users/users_edit_ajax.php",
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
                    title: message_error_form18,
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




function cdp_deleteImgAttached(id) {

    var parent = $('#file_delete_item_' + id);
    var name = $(this).attr('data-rel');
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

        // });
    });
}


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
            '<div class="row">' +
            '<div class=" col-md-12 mt-2 mb-2">' +
            '<span>' + event.target.files[i].name + '</span>' +
            '</div>' +
            '</div>' +

            '<div class="row">' +
            '<div class="  mb-2">' +
            '<button type="button" class="btn btn-danger btn-sm pull-left" onclick="cdp_deletePreviewImage(' + i + ');"><i class="fa fa-trash"></i></button>' +
            '</div>' +
            '</div>' +
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

    var deleted_file = $('#deleted_file_ids').val();



}


function cdp_validateZiseFiles() {

    var inputFile = document.getElementById('filesMultiple');
    var file = inputFile.files;

    var size = 0;

    for (var i = 0; i < file.length; i++) {

        var filesSize = file[i].size;

        if (size > 5242880) {

            $('.resultados_file').html("<div class='alert alert-danger'>" +
                "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
                "<strong>" + validation_files_size + " </strong>" +

                "</div>"
            );

            $("#filesMultiple").val('');
            $('#clean_files').addClass('hide');
            $('#image_preview').html("");

        } else {
            $('.resultados_file').html("");
        }

        size += filesSize;
    }

    if (size > 5242880) {
        $('.resultados_file').html("<div class='alert alert-danger'>" +
            "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
            "<strong>" + validation_files_size + " </strong>" +

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
    console.log(file)
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
