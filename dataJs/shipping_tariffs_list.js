"use strict";

$(function () {
  // Inicializas Select2
  cdp_load_countries_origin();
  cdp_load_countries_destiny();
  cdp_load_ship_modes();

  // Cuando cambie cualquiera de los 3 selects, recarga la tabla
  $('#country_origin, #country_destiny, #name_item')
    .off('change.selects select2:clear.selects')
    .on('change.selects select2:clear.selects', function () {
      cdp_load(1);
    });

  // Primera carga
  cdp_load(1);
});

// Cargar datos AJAX (lista)
function cdp_load(page) {
  var origin   = $("#country_origin").val() || '';
  var destiny  = $("#country_destiny").val() || '';
  var shipMode = $("#name_item").val() || '';

  // debug opcional:
  // console.log({origin, destiny, shipMode});

  $.ajax({
    url: './ajax/tools/ship_tariffs/ship_tariffs_list_ajax.php',
    type: 'GET',
    data: { origin, destiny, shipMode },
    beforeSend: function(){ $("#loader").fadeIn('slow'); },
    success: function (data) {
      $(".outer_div").html(data).fadeIn('slow');
      $("#loader").fadeOut('fast');
    },
    error: function () {
      $("#loader").fadeOut('fast');
      $(".outer_div").html("<div class='alert alert-danger'>No se pudo cargar el listado.</div>");
    }
  });
}

function cdp_load_countries_destiny() {
  $("#country_destiny").select2({
    ajax: {
      url: "ajax/select2_countries.php",
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term || '' }),
      processResults: data => ({ results: data }),
      cache: true
    },
    minimumInputLength: 2,
    placeholder: (typeof translate_search_destiny!=='undefined'?translate_search_destiny:'Buscar país destino'),
    allowClear: true,
    width: '100%'
  });
}

function cdp_load_countries_origin() {
  $("#country_origin").select2({
    ajax: {
      url: "ajax/select2_countries.php",
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term || '' }),
      processResults: data => ({ results: data }),
      cache: true
    },
    minimumInputLength: 2,
    placeholder: (typeof translate_search_origin!=='undefined'?translate_search_origin:'Buscar país origen'),
    allowClear: true,
    width: '100%'
  });
}

function cdp_load_ship_modes() {
  $("#name_item").select2({
    ajax: {
      url: "ajax/select2_shipping_mode.php", // asegúrate que este archivo exista
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term || '' }),
      processResults: data => ({ results: data }),
      cache: true
    },
    minimumInputLength: 0, // ver opciones al abrir
    placeholder: (typeof translate_search_shipmode!=='undefined' ? translate_search_shipmode : 'Buscar modo de envío'),
    allowClear: true,
    width: '100%'
  });
}




// Borrado (se invoca desde el onclick impreso en la tabla)
function cdp_eliminar(id) {
  swal({
    title: message_delete_confirm,
    text: message_delete_confirm2,
    type: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#336aea',
    cancelButtonColor: '#eb644c',
    confirmButtonText: message_delete_confirm1,
    showLoaderOnConfirm: true,
    preConfirm: function () {
      return new Promise(function (resolve) {
        $.ajax({
          url: './ajax/tools/ship_tariffs/ship_tariffs_delete_ajax.php',
          type: 'POST',
          data: { id: id },
          dataType: 'json'
        })
        .done(function (response) {
          swal(response.message, message_delete_error2, response.status);
          $('html, body').animate({ scrollTop: 0 }, 600);
          cdp_load(1);
          resolve();
        })
        .fail(function () {
          swal('Oops...', message_delete_error, 'error');
        });
      });
    },
    allowOutsideClick: false
  });
}
