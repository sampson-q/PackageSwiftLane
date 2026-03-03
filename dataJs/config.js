"use strict";


// AJAX sweetalert2 guardar

$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#save_config").submit(function(event) {
        event.preventDefault();

        // Obtén los valores de los campos
        var site_name = $('#site_name').val(); 
        var site_url = $('#site_url').val();
        var site_email = $('#site_email').val();
        var c_nit = $('#c_nit').val();
        var c_phone = $('#c_phone').val();
        var cell_phone = $('#cell_phone').val();
        var c_address = $('#c_address').val();
        var locker_address = $('#locker_address').val();
        var c_country = $('#c_country').val();
        var c_city = $('#c_city').val();
        var c_postal = $('#c_postal').val();
        var digit_random_locker = $('#digit_random_locker').val();
        var prefix_locker = $('#prefix_locker').val();

        var reg_verify = $('input:radio[name=reg_verify]:checked').val();
        var auto_verify = $('input:radio[name=auto_verify]:checked').val();
        var reg_allowed = $('input:radio[name=reg_allowed]:checked').val();
        var notify_admin = $('input:radio[name=notify_admin]:checked').val();
        var code_number_locker = $('input:radio[name=code_number_locker]:checked').val();

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
            url: "./ajax/tools/config_system_ajax.php",
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
                        window.location.href = 'tools.php?list=config';
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



