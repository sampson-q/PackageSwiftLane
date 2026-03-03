"use strict";



// AJAX sweetalert2 guardar

$(document).ready(function() {
    $("#save_config_general").submit(function(event) {
        event.preventDefault();



        // Obtén los valores de los campos
        var timezone = $('#timezone').val();
        var language = $('#language').val();
        var for_currency = $('#for_currency').val();
        var for_symbol = $('#for_symbol').val();
        var for_decimal = $('#for_decimal').val();
        var cformat = $('#cformat').val();
        var dec_point = $('#dec_point').val();
        var thousands_sep = $('#thousands_sep').val();

        // Configurar objeto FormData
        var data = new FormData(this);

      

        // Realizar la solicitud AJAX
        $.ajax({
            url: "./ajax/tools/config_general_ajax.php",
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
                        window.location.href = 'tools.php';
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



 
document.getElementById('colaboradores').onchange = function () {
    /* Referencia al option seleccionado */
    var mOption = this.options[this.selectedIndex];
    /* Referencia a los atributos data de la opción seleccionada */
    var mData = mOption.dataset;

    /* Referencia a los input */
    var elId = document.getElementById('id');
    var elCodigo = document.getElementById('currency_symbol');
    var elCurren = document.getElementById('currency');


    /* Asignamos cada dato a su input*/
    elId.value = this.value;
    elCodigo.value = mData.currency_symbol;
    elCurren.value = mData.currency;


};

// Inicializa Select2 en tu select

$(document).ready(function() {
        $('#timezone').select2();
    });

    $(document).ready(function() {
        $('#language').select2();
    });

     $(document).ready(function() {
        $('#colaboradores').select2();
    });