"use strict";


// AJAX sweetalert2 guardar

$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#save_data").submit(function(event) {
        event.preventDefault();

        // Obtén los valores de los campos
        var api_ws_url = $('#api_ws_url').val(); 
        var api_ws_token = $('#api_ws_token').val();
        var active_whatsapp = $('#active_whatsapp').val();

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
            url: "./ajax/tools/api_whatsapp_config_ajax.php",
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
                        window.location.href = 'config_whatsapp.php';
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
