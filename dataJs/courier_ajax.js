"use strict";
// Multi-select persistence (checked rows survive search / filter / pagination)
// is handled centrally by dataJs/persist_selection.js. The bulk actions below
// read the current selection via cdpSelGet(); destructive ones call cdpSelClear()
// once the action has been applied.


$("#send_checkbox_status").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var parametros = $(this).serialize();
    var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

    var status = $('#status_courier_modal').val();

    $.ajax({
        type: "GET",
        url: './ajax/courier/courier_update_multiple_ajax.php?status=' + status,

        data: { 'checked_data': JSON.stringify(checked_data) },
        beforeSend: function (objeto) {
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#guardar_datos').attr("disabled", false);
            $('#modalCheckboxStatus').modal('hide');


            cdp_load(1);

            if (typeof cdpSelClear === 'function') cdpSelClear();
            $('html, body').animate({
                scrollTop: 0
            }, 600);


        }
    });
    event.preventDefault();

})


 // Función para imprimir etiquetas de envios
function cdp_printMultipleLabel() {
  var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

  if (checked_data.length === 0) {
    Swal.fire({ text: "Please select at least one shipment.", icon: "warning", confirmButtonText: "OK" });
    return;
  }

  // Mostramos una alerta de confirmación utilizando SweetAlert
  Swal.fire({
    title: message_print_confirm1, // Título de la alerta
    html: '<b>' + message_print_confirm2 + '</b>', // Mensaje de la alerta
    icon: 'question', // Ícono de la alerta
    showCancelButton: true, // Mostrar botón de cancelar
    confirmButtonText: 'Print', // Texto del botón de confirmación
    cancelButtonText: 'Cancel', // Texto del botón de cancelar
    reverseButtons: true, // Revertir el orden de los botones (colocar "Print" a la derecha)
    // Open inside the click gesture: SweetAlert's .then resolves after the close
    // animation, which Chrome treats as a lost user gesture and blocks window.open.
    preConfirm: function () {
      var win = window.open(
        "print_label_ship_multiple.php?data=" +
        JSON.stringify(checked_data), // Pasamos los datos de los paquetes seleccionados como parámetro
        "_blank"
      );
      if (!win) {
        Swal.showValidationMessage("Your browser blocked the print window. Please allow pop-ups for this site and try again.");
        return false;
      }
    }
  });
}

// Bulk-print the shipment invoice/receipt for every selected shipment.
// Opens a single tab that auto-prints each receipt one after the other.
function cdp_printMultipleInvoice() {
  var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

  if (checked_data.length === 0) {
    Swal.fire({ text: "Please select at least one shipment.", icon: "warning", confirmButtonText: "OK" });
    return;
  }

  Swal.fire({
    title: 'Print Shipments',
    html: '<b>Print the receipt/invoice for the ' + checked_data.length + ' selected shipment(s)?</b>',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Print',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    // Open inside the click gesture: SweetAlert's .then resolves after the close
    // animation, which Chrome treats as a lost user gesture and blocks window.open.
    preConfirm: function () {
      var win = window.open(
        "print_inv_ship_multiple.php?data=" + JSON.stringify(checked_data),
        "_blank"
      );
      if (!win) {
        Swal.showValidationMessage("Your browser blocked the print window. Please allow pop-ups for this site and try again.");
        return false;
      }
    }
  });
}


$("#driver_update_multiple").on('submit', function (event) {

    $('#update_driver2').attr("disabled", true);

    var parametros = $(this).serialize();
    var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

    var driver = $('#driver_id_multiple').val();

    $.ajax({
        type: "GET",
        url: './ajax/courier/courier_update_driver_multiple_ajax.php?driver=' + driver,

        data: { 'checked_data': JSON.stringify(checked_data) },
        beforeSend: function (objeto) {
        },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#update_driver2').attr("disabled", false);
            $('#modalDriverCheckbox').modal('hide');


            cdp_load(1);

            if (typeof cdpSelClear === 'function') cdpSelClear();
            $('html, body').animate({
                scrollTop: 0
            }, 600);


        }
    });
    event.preventDefault();

})