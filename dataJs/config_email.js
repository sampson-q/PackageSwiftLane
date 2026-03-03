"use strict";

$("#save_config_email").on('submit', function (event) {

    var parametros = $(this).serialize();
    $.ajax({
        type: "POST",
        url: "ajax/tools/config_smtp_ajax.php",
        data: parametros,
        beforeSend: function (objeto) {
            $("#resultados_ajax").html("Wait a moment...");
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);

            $("html, body").animate({
                scrollTop: 0
            }, 600);

        }
    });
    event.preventDefault();

});

$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#save_config_email").submit(function(event) {
        event.preventDefault();

        // Obtén los valores de los campos
        var mailer = $('#mailer').val(); 
        var smtp_names = $('#smtp_names').val();
        var email_address = $('#email_address').val();
        var smtp_host = $('#smtp_host').val();
        var smtp_user = $('#smtp_user').val();
        var smtp_password = $('#smtp_password').val();
        var smtp_port = $('#smtp_port').val();
        var smtp_secure = $('#smtp_secure').val();


        // Configurar objeto FormData
        var data = new FormData(this);

        // Validar campos requeridos
        var camposVacios = [];
        $('.required').each(function() {
            if ($(this).val() === '') {
                camposVacios.push($(this).attr('id'));
            }
        });

        if (camposVacios.length > 0) {
            // Muestra un mensaje de error con SweetAlert
            Swal.fire({
                type: 'error',
                title: message_error_form21,
                text: message_error_form22,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });

            // Resalta los campos vacíos
            camposVacios.forEach(function(campo) {
                $('#' + campo).addClass('highlight');
            });

            // Detiene el envío del formulario
            return;
        }

        // Realizar la solicitud AJAX
        $.ajax({
            url: "./ajax/tools/config_smtp_ajax.php",
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
                        window.location.href = 'tools.php?list=config_email';
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
    });
});





$(function () {
    updateSmtpVisibility(); // Llama a la función al cargar la página

    $('#mailer').change(updateSmtpVisibility); // Llama a la función cuando cambia la opción del select

    function updateSmtpVisibility() {
        var isSmtp = $('#mailer').val() === "SMTP";
        $('.showsmtp').toggle(isSmtp); // Muestra o oculta .showsmtp según el valor de isSmtp
    }
});
