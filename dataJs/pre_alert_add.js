"use strict";



$(function () {
    $('#date_prealert').datepicker({
        format: "yyyy-mm-dd",
        autoclose: true
    });
});


function cdp_validateZiseFiles() {

    var inputFile = document.getElementById('file_invoice');
    var file = inputFile.files;

    var size = 0;
    console.log(file);

    for (var i = 0; i < file.length; i++) {

        var filesSize = file[i].size;

        if (size > 5242880) {

            $('.resultados_file').html("<div class='alert alert-danger'>" +
                "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
                "<strong>" + validation_files_size + " </strong>" +

                "</div>"
            );
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

        return true;

    } else {
        $('.resultados_file').html("");

        return false;
    }

}


$('#openMultiFile').on('click', function () {

    $("#file_invoice").click();
});


$('#clean_file_button').on('click', function () {

    $("#file_invoice").val('');

    $('#selectItem').html('Attach files');

    $('#clean_files').addClass('hide');


});



$('input[type=file]').on('change', function () {

    var inputFile = document.getElementById('file_invoice');
    var file = inputFile.files;
    var contador = 0;
    for (var i = 0; i < file.length; i++) {

        contador++;
    }
    if (contador > 0) {

        $('#clean_files').removeClass('hide');
    } else {

        $('#clean_files').addClass('hide');

    }

    $('#selectItem').html('attached files (' + contador + ')');
});



// Registro de datos
$('#form_prealert').submit(function (event) {
    event.preventDefault(); // Evitar el envío del formulario por defecto

    const formData = new FormData(this);
    let formIsValid = true;

    // Validar campos con SweetAlert2

    if (!formData.get('tracking_prealert')) {
        $('#tracking_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#tracking_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }

    if (!formData.get('provider_prealert')) {
        $('#provider_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#provider_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }


    if (!formData.get('courier_prealert')) {
        $('#courier_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#courier_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }

    if (!formData.get('price_prealert')) {
        $('#price_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#price_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }

    if (!formData.get('description_prealert')) {
        $('#description_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#description_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }

    if (!formData.get('date_prealert')) {
        $('#date_prealert').addClass('is-invalid'); // Marcar el input como inválido
        formIsValid = false;
    } else {
        $('#date_prealert').removeClass('is-invalid'); // Remover la marca de inválido
    }


    // Verificar si el campo de archivo no está adjunto y mostrar una alerta si es necesario
    if (formData.get('file_invoice').size === 0) {
        // Mostrar la alerta indicando que se debe adjuntar un archivo
        Swal.fire({
            type: 'error',
            title: 'Oops...',
            text: message_error_form77,
            confirmButtonColor: '#336aea',
        });
        return; // Detener el envío del formulario
    }

    // Si alguno de los campos no es válido, mostrar el mensaje de error y detener el envío del formulario
    if (!formIsValid) {
        Swal.fire({
            type: 'error',
            title: 'Oops...',
            text: message_error_form78,
            confirmButtonColor: '#336aea',
        });
        return;
    }
 

    $.ajax({
        type: 'POST',
        url: 'ajax/check_tracking.php',
        data: { tracking: formData.get('tracking_prealert') },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'error') {
                Swal.fire({
                    type: 'error',
                    title: 'Error!',
                    text: response.message,
                });
                $('#tracking_prealert').addClass('is-invalid');
            } else {
                                // Si el número de seguimiento no está en uso, enviar el formulario
            $.ajax({
                    type: 'POST',
                    url: 'ajax/pre_alerts/prealert_add_ajax.php',
                    data: formData,
                    contentType: false,
                    cache: false,
                    processData: false,
                    beforeSend: function () {
                        Swal.fire({
                            title: 'Process...',
                            allowOutsideClick: false,
                            onBeforeOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                type: 'success',
                                title: message_error_form15,
                                showConfirmButton: false,
                                timer: 1500,
                                timerProgressBar: true,
                            }).then(() => {
                                // Redirigir al listado de clientes
                                window.location.href = 'prealert_list.php';
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
                            text: message_error_form93,
                        });
                    }
                });
            }
        },
        error: function () {
            Swal.fire({
                type: 'error',
                title: 'Error!',
                text: message_error_form93,
            });
        }
    });

});


function cdp_soloNumeros(e) {
    var key = e.charCode;
    return key >= 44 && key <= 57;
}