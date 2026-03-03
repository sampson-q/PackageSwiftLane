"use strict";

$(function () {
  cdp_load_countries_origin();
  cdp_load_countries_destiny();
  cdp_load_states();
  cdp_load_cities();
  cdp_load_ship_modes_edit(); // ya lo tienes
  cdp_load_clients_edit();    // ← AÑADIR

  // Marcar/desmarcar requeridos
  $('.required').on('input change', function () {
    $(this).toggleClass('highlight', ($(this).val() === '' || $(this).val() === null));
  });
});




function cdp_load_countries_origin() {

    $("#country_origin").select2({
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: 'json',

            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_country,
        allowClear: true
    });
}

function cdp_load_countries_destiny() {

    $("#country_destiny").select2({
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_country,
        allowClear: true
    }).on('change', function (e) {

        var country = $("#country_destiny").val();
        $("#state_destinystates").attr("disabled", true);
        $("#state_destinystates").val(null);

        $("#city_destinycities").attr("disabled", true);
        $("#city_destinycities").val(null);

        if (country !== null) {
            $("#state_destinystates").attr("disabled", false);
        }

        cdp_load_cities();
        cdp_load_states();
    });
}

function cdp_load_states() {
    var country = $("#country_destiny").val();

    $("#state_destinystates").select2({
        ajax: {
            url: "ajax/select2_states.php?id=" + country,
            dataType: 'json',

            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_state,
        allowClear: true
    }).on('change', function (e) {

        var state = $("#state_destinystates").val();

        $("#city_destinycities").attr("disabled", true);
        $("#city_destinycities").val(null);

        if (state !== null) {
            $("#city_destinycities").attr("disabled", false);
        }

        cdp_load_cities();
    });
}

function cdp_load_cities() {
    var state = $("#state_destinystates").val();

    $("#city_destinycities").select2({
        ajax: {
            url: "ajax/select2_cities.php?id=" + state,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: translate_search_city,
        allowClear: true
    });
}

// Ship mode (cdb_category.id) usado en cdb_shipping_fees.order_service_options
function cdp_load_ship_modes_edit() {
  $("#ship_mode").select2({
    ajax: {
      url: "ajax/select2_shipping_mode.php",
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


// Client (opcional) - Select2 AJAX
function cdp_load_clients_edit() {
  $("#client_id").select2({
    ajax: {
      url: "ajax/select2_clients.php", // endpoint nuevo abajo
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term || '' }),
      processResults: data => ({ results: data }),
      cache: true
    },
    minimumInputLength: 0, // ver opciones al abrir (puede ser 2 si prefieres)
    placeholder: (typeof translate_search_client!=='undefined' ? translate_search_client : 'Buscar cliente'),
    allowClear: true,
    width: '100%'
  });
}



// AJAX sweetalert2 update

$(document).ready(function() {
    // Validar y resaltar campos obligatorios (usa la clase .required)
    $('.required').on('input', function() {
        $(this).toggleClass('highlight', $(this).val().trim() === '');
    });

    $("#save_data").on("submit", function(event) {
        event.preventDefault();

        // Recopilar campos
        var form = this;
        var data = new FormData(form);

        // Verificar obligatorios
        var camposVacios = [];
        $('.required').each(function() {
            if ($(this).val().trim() === '') {
                camposVacios.push($(this).attr('id'));
            }
        });

        if (camposVacios.length > 0) {
            Swal.fire({
                type: 'error',
                title: message_error_form21,
                text: message_error_form22,
                confirmButtonColor: '#336aea'
            });
            camposVacios.forEach(function(campo) {
                $('#' + campo).addClass('highlight');
            });
            return;
        }

        $.ajax({
            url: "./ajax/tools/ship_tariffs/ship_tariffs_edit_ajax.php",
            type: "POST",
            data: data,
            dataType: "json",
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
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                Swal.close();
                if (response.status === 'success') {
                    Swal.fire({
                        type: 'success',
                        title: message_error_form15,
                        timer: 1500,
                        showConfirmButton: false,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'shipping_tariffs_list.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form19,
                        text: response.message || message_error_form17,
                        confirmButtonColor: '#336aea'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    type: 'error',
                    title: message_error_form18,
                    text: message_error_form19,
                    confirmButtonColor: '#336aea'
                });
            }
        });
    });
});

// Permitir solo números y punto decimal
function onlyValidNumber(event) {
    const charCode = event.which || event.keyCode;
    // permitir backspace (8), tab (9), delete (46), flechas (37–40), punto (46) y dígitos (48–57)
    if (
        !(charCode === 8 || charCode === 9 || (charCode >= 37 && charCode <= 40) ||
          charCode === 46 || (charCode >= 48 && charCode <= 57))
    ) {
        event.preventDefault();
    }
}

document.getElementById("initial_range").addEventListener("keypress", onlyValidNumber);
document.getElementById("final_range").addEventListener("keypress", onlyValidNumber);
document.getElementById("tariff_price").addEventListener("keypress", onlyValidNumber);
document.getElementById("price_mile").addEventListener("keypress", onlyValidNumber);
document.getElementById("volumetric_percentage").addEventListener("keypress", onlyValidNumber);

