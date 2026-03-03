"use strict";

$(function () {
    cdp_load_countries_origin();
    cdp_load_countries_destiny();
    cdp_select2_clients();
    cdp_load_states();
    cdp_load_cities();

    // SUBMIT
    $("#save_data").on("submit", function (event) {
        event.preventDefault();

        // ---- Lee valores del form (mismos name/id que tu HTML) ----
        var tariff_price          = $('#tariff_price').val();
        var initial_range         = $('#initial_range').val();
        var final_range           = $('#final_range').val();
        var country_origin        = $('#country_origin').val();
        var country_destiny       = $('#country_destiny').val();
        var state_destinystates   = $('#state_destinystates').val();
        var city_destinycities    = $('#city_destinycities').val();
        var ship_mode             = $('#ship_mode').val();
        var volumetric_percentage = $('#volumetric_percentage').val();
        var price_mile            = $('#price_mile').val();
        // client_id es opcional (select2)
        var client_id             = $('#client_id').val();

        // ---- Validación sencilla (como la tuya) ----
        var missingFields = [];
        if (!tariff_price)           missingFields.push(message_error_form34);
        if (!initial_range)          missingFields.push(message_error_form32);
        if (!final_range)            missingFields.push(message_error_form33);
        if (!country_origin)         missingFields.push(message_error_form31);
        if (!country_destiny)        missingFields.push(message_error_form28);
        if (!state_destinystates)    missingFields.push(message_error_form29);
        if (!city_destinycities)     missingFields.push(message_error_form30);
        if (!ship_mode)              missingFields.push('shipping mode');
        if (!volumetric_percentage)  missingFields.push('volumetric factor');
        if (!price_mile)             missingFields.push('price per mile');

        if (missingFields.length > 0) {
            const alertMessage = message_error_form5 + '\n\n- ' + missingFields.join('\n- ');
            Swal.fire({
                type: 'error',
                title: message_error_form1,
                text: alertMessage,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });
            return;
        }

        // ⚠️ MUY IMPORTANTE:
        // Antes de serializar, asegúrate de que NO estén disabled,
        // porque los campos disabled NO viajan en el POST.
        $('#state_destinystates, #city_destinycities').prop('disabled', false);

        // Serializa TODO el formulario (sin archivos)
        var payload = $(this).serialize();

        // (Opcional) Depuración: ver qué se envía
        // console.log('POST ->', payload);

        $.ajax({
            url: "ajax/tools/ship_tariffs/ship_tariffs_add_ajax.php",
            type: 'POST',
            data: payload,
            dataType: 'json', // esperamos JSON del backend
            beforeSend: function() {
                Swal.fire({
                    title: message_error_form6,
                    text: message_error_form14,
                    icon: 'info',
                    showCancelButton: false,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
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
                        window.location.href = 'shipping_tariffs_list.php';
                    });
                } else {
                    // Muestra mensaje del backend
                    let msg = response.message || message_error_form17;
                    if (response.errors && Array.isArray(response.errors) && response.errors.length) {
                        msg += '\n\n- ' + response.errors.join('\n- ');
                    }
                    Swal.fire({
                        type: 'error',
                        title: message_error_form18,
                        text: msg,
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function(xhr) {
                Swal.close();
                // (Opcional) Depuración: mirar respuesta cruda del servidor
                // console.error('XHR responseText:', xhr.responseText);
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

    // Validación numérica
    document.getElementById("initial_range").addEventListener("keypress", onlyValidNumber);
    document.getElementById("final_range").addEventListener("keypress", onlyValidNumber);
    document.getElementById("tariff_price").addEventListener("keypress", onlyValidNumber);
    document.getElementById("price_mile").addEventListener("keypress", onlyValidNumber);
    document.getElementById("volumetric_percentage").addEventListener("keypress", onlyValidNumber);
});

function onlyValidNumber(event) {
    if (event.charCode < 46 || event.charCode > 57) {
        event.preventDefault();
    }
}

// ------ Select2 loaders (sin cambios de lógica) ------
function cdp_select2_clients() {
    $("#client_id").select2({
        ajax: {
            url: "ajax/select2_clients.php",
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        placeholder: search_sender,
        allowClear: true,
        width: '100%',
        minimumInputLength: 2
    });
}

function cdp_load_countries_origin() {
    $("#country_origin").select2({
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        placeholder: translate_search_country,
        allowClear: true,
        width: '100%'
    });
}

function cdp_load_countries_destiny() {
    $("#country_destiny").select2({
        ajax: {
            url: "ajax/select2_countries.php",
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        placeholder: translate_search_country,
        allowClear: true,
        width: '100%'
    }).on('change', function () {
        const country = $("#country_destiny").val();

        $("#state_destinystates").prop("disabled", true).val(null).trigger('change');
        $("#city_destinycities").prop("disabled", true).val(null).trigger('change');

        if (country) {
            $("#state_destinystates").prop("disabled", false);
        }
        cdp_load_states();
        cdp_load_cities();
    });
}

function cdp_load_states() {
    const country = $("#country_destiny").val() || '';
    $("#state_destinystates").select2({
        ajax: {
            url: "ajax/select2_states.php?id=" + encodeURIComponent(country),
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        placeholder: translate_search_state,
        allowClear: true,
        width: '100%'
    }).on('change', function () {
        const state = $("#state_destinystates").val();

        $("#city_destinycities").prop("disabled", true).val(null).trigger('change');
        if (state) {
            $("#city_destinycities").prop("disabled", false);
        }
        cdp_load_cities();
    });
}

function cdp_load_cities() {
    const state = $("#state_destinystates").val() || '';
    $("#city_destinycities").select2({
        ajax: {
            url: "ajax/select2_cities.php?id=" + encodeURIComponent(state),
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        placeholder: translate_search_city,
        allowClear: true,
        width: '100%'
    });
}
