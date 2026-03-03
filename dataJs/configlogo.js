"use strict";


// AJAX sweetalert2 logo and favicon
$(document).ready(function () {
    $('#edit_avatar_form').on('submit', function (event) {
        event.preventDefault(); // Evita que el formulario se envíe de forma convencional
        updateLogo();
    });

    // Evento change para mostrar la vista previa del logo al seleccionar un archivo
    $('#logo').on('change', function () {
        showImagePreview(this, '#logo-preview');
    });

    // Evento change para mostrar la vista previa del logo_web al seleccionar un archivo
    $('#logo_web').on('change', function () {
        showImagePreview(this, '#logo-web-preview');
    });

    // Evento change para mostrar la vista previa del favicon al seleccionar un archivo
    $('#favicon').on('change', function () {
        showImagePreview(this, '#favicon-preview');
    });

    function updateLogo() {
        // Validar campos antes de enviar el formulario
        if (!validateFields()) {
            return;
        }

        var formData = new FormData($('#edit_avatar_form')[0]);

        $.ajax({
            type: 'POST',
            url: './ajax/tools/config_logo_ajax.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                handleServerResponse(response);
            },
            error: function (xhr, status, error) {
                handleServerError(xhr, status, error);
            }
        });
    }

    function handleServerResponse(response) {
        // Manejar la respuesta del servidor
        if (response.success) {
            // Mostrar SweetAlert2 de éxito
            Swal.fire({
                type: 'success',
                title: 'Logo updated',
                text: response.message
            }).then(() => {
                // Redirigir al mismo sitio
                window.location.href = 'tools.php?list=configlogo';
            });
        } else {
            // Mostrar SweetAlert2 de error
            Swal.fire({
                type: 'error',
                title: 'Logo Update Error',
                text: response.message
            });
        }
    }

    function handleServerError(xhr, status, error) {
        // Manejar errores de conexión u otros errores
        Swal.fire({
            type: 'error',
            title: 'Error',
            text: 'Connection or processing error on the server. ' + error
        });
    }

    // Función para validar campos
    function validateFields() {
        var thumbW = $('#thumb_w').val();
        var thumbH = $('#thumb_h').val();
        var thumbWeb = $('#thumb_web').val();
        var thumbHWeb = $('#thumb_hweb').val();

        // Validar que los campos sean numéricos y no estén vacíos
        if (!isNumeric(thumbW) || !isNumeric(thumbH) || !isNumeric(thumbWeb) || !isNumeric(thumbHWeb)) {
            // Mostrar mensaje de error
            Swal.fire({
                type: 'error',
                title: 'Validation Error',
                text: 'Thumbnail dimensions must be numeric values.'
            });
            return false;
        }

        // Validar tamaño de archivos
        var logoFile = $('#logo')[0].files[0];
        var logoWebFile = $('#logo_web')[0].files[0];
        var faviconFile = $('#favicon')[0].files[0];

        if (logoFile && logoFile.size > 1024 * 1024) { // Tamaño máximo de 1 MB
            Swal.fire({
                type: 'error',
                title: 'File Size Error',
                text: 'Logo file size must be less than 1 MB.'
            });
            return false;
        }

        if (logoWebFile && logoWebFile.size > 1024 * 1024) { // Tamaño máximo de 1 MB
            Swal.fire({
                type: 'error',
                title: 'File Size Error',
                text: 'Logo Web file size must be less than 1 MB.'
            });
            return false;
        }

        if (faviconFile && faviconFile.size > 1024 * 1024) { // Tamaño máximo de 1 MB
            Swal.fire({
                type: 'error',
                title: 'File Size Error',
                text: 'Favicon file size must be less than 1 MB.'
            });
            return false;
        }

        return true;
    }

    // Función para verificar si un valor es numérico
    function isNumeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }

    // Función para mostrar la vista previa de la imagen
    function showImagePreview(input, previewId) {
        var fileInput = $(input)[0];
        var file = fileInput.files[0];

        if (file) {
            var reader = new FileReader();

            reader.onload = function (e) {
                $(previewId).attr('src', e.target.result);
            };

            reader.readAsDataURL(file);
        }
    }
});
