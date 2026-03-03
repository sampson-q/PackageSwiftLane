"use strict";

// AJAX SweetAlert2 save
$(document).ready(function () {
    $("#save_seo_config").submit(function (event) {
        event.preventDefault();

        // Validación de campos vacíos
        const requiredFields = [
            { id: "meta_description", message: message_error_seo1 },
            { id: "og_title", message: message_error_seo2 },
            { id: "og_description", message: message_error_seo3 },
            { id: "og_type", message: message_error_seo4 },
            { id: "og_url", message: message_error_seo5 },
            { id: "og_image", message: message_error_seo6 },
        ];

        let isValid = true;

        requiredFields.forEach(field => {
            if (!$(`#${field.id}`).val().trim()) {
                Swal.fire({
                    type: 'error',
                    title: message_error_seo7,
                    text: field.message,
                    confirmButtonColor: '#336aea',
                    showConfirmButton: true,
                });
                isValid = false;
                return false; // Detener el bucle en el primer campo vacío encontrado
            }
        });

        if (!isValid) return; // Detener el envío si falta algún campo

        // Configurar objeto FormData
        var data = new FormData(this);

        // Realizar la solicitud AJAX
        $.ajax({
            url: "./ajax/tools/config_seo_ajax.php",
            type: 'POST',
            data: data,
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function () {
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
            success: function (response) {
                Swal.close();
                if (response.status === 'success') {
                    Swal.fire({
                        type: 'success',
                        title: message_error_form15,
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true,
                    }).then(() => {
                        window.location.href = 'tools.php';
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
            error: function () {
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
