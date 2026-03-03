"use strict";

var count = 0;

$(".sl-all").on('click', function () {

    $('.custom-table-checkbox input:checkbox').not(this).prop('checked', this.checked);

    if ($('.custom-table-checkbox input:checkbox').is(':checked')) {

        $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]').parents('tr').css('background', '#fff8e1');

    } else {

        $('.custom-table-checkbox input:checkbox').parents('tr').css('background', '');

    }

    var $checkboxes = $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]');

    count = $checkboxes.filter(':checked').length;

    if (count > 0) {

        $('#div-actions-checked').removeClass('hide');
        $('#countChecked').removeClass('hide');

    } else {

        $('#div-actions-checked').addClass('hide');
        $('#countChecked').addClass('hide');
    }

    $('#countChecked').html(count);


});



$('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]').on('change', function () {

    if ($(this).is(':checked')) {

        $(this).parents('tr').css('background', '#fff8e1');

    } else {

        $(this).parents('tr').css('background', '');
    }


});




$(function () {

    var $checkboxes = $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]');

    $checkboxes.on('change', function () {

        count = $checkboxes.filter(':checked').length;

        if (count > 0) {

            $('#div-actions-checked').removeClass('hide');
            $('#countChecked').removeClass('hide');

        } else {

            $('#div-actions-checked').addClass('hide');
            $('#countChecked').addClass('hide');
        }


        $('#countChecked').html(count);

    });

});




$("#send_checkbox_status").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var parametros = $(this).serialize();
    var checked_data = new Array();
    $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function () {
        checked_data.push($(this).val());
    });

    var status = $('#status_courier_modal').val();

    $.ajax({
        type: "GET",
        url: './ajax/consolidate/consolidate_update_multiple_ajax.php?status=' + status,

        data: { 'checked_data': JSON.stringify(checked_data) },
        beforeSend: function (objeto) {
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#guardar_datos').attr("disabled", false);
            $('#modalCheckboxStatus').modal('hide');


            cdp_load(1);

            $('#div-actions-checked').addClass('hide');
            $('#countChecked').addClass('hide');
            $('html, body').animate({
                scrollTop: 0
            }, 600);


        }
    });
    event.preventDefault();

})


// Función para imprimir etiquetas de envios múltiples
function cdp_printMultipleLabel() {
  var checked_data = []; // Array para almacenar los datos de los paquetes seleccionados
  // Recorremos las casillas de verificación seleccionadas
  $(".custom-table-checkbox")
    .find("tr > td:first-child")
    .find("input[type=checkbox]:checked")
    .each(function () {
      checked_data.push($(this).val()); // Agregamos el valor de la casilla de verificación al array
    });

  // Mostramos una alerta de confirmación utilizando SweetAlert
  Swal.fire({
    title: message_print_confirm1, // Título de la alerta
    html: '<b>' + message_print_confirm2 + '</b>', // Mensaje de la alerta
    icon: 'question', // Ícono de la alerta
    showCancelButton: true, // Mostrar botón de cancelar
    confirmButtonText: 'Print', // Texto del botón de confirmación
    cancelButtonText: 'Cancel', // Texto del botón de cancelar
    reverseButtons: true // Revertir el orden de los botones (colocar "Print" a la derecha)
  }).then((result) => {
    // Si el usuario hace clic en el botón de confirmación
    if (result.isConfirmed) {
      // Abrimos una nueva ventana para imprimir las etiquetas de paquetes múltiples
      window.open(
        "print_label_consolidate_multiple.php?data=" +
        JSON.stringify(checked_data), // Pasamos los datos de los paquetes seleccionados como parámetro
        "_blank"
      );
    }
  });
}

$(document).on('show.bs.dropdown', function (e) {
    $('#test').css('padding-top', '160px');
});




$("#driver_update_multiple").on('submit', function (event) {

    $('#update_driver2').attr("disabled", true);

    var parametros = $(this).serialize();
    var checked_data = new Array();
    $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function () {
        // $('.custom-table-checkbox input:checkbox:checked').each(function() {
        checked_data.push($(this).val());
    });

    var driver = $('#driver_id_multiple').val();

    $.ajax({
        type: "GET",
        url: './ajax/consolidate/consolidate_update_driver_multiple_ajax.php?driver=' + driver,

        data: { 'checked_data': JSON.stringify(checked_data) },
        beforeSend: function (objeto) {
            $(".resultados_ajax").html("Mensaje: send...");
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#update_driver2').attr("disabled", false);
            $('#modalDriverCheckbox').modal('hide');


            cdp_load(1);

            $('#div-actions-checked').addClass('hide');
            $('#countChecked').addClass('hide');
            $('html, body').animate({
                scrollTop: 0
            }, 600);


        }
    });
    event.preventDefault();

})