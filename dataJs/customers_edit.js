"use strict";

var errorMsg = document.querySelector("#error-msg");
var validMsg = document.querySelector("#valid-msg");

// here, the index maps to the error code returned from getValidationError - see readme
var errorMap = [
  "Invalid number",
  "Invalid country code",
  "Mobile number too short",
  "Mobile number too long",
  "Invalid mobile number",
];

var input = document.querySelector("#phone_custom");
var iti = window.intlTelInput(input, {
  geoIpLookup: function (callback) {
    $.get("http://ipinfo.io", function () {}, "jsonp").always(function (resp) {
      var countryCode = resp && resp.country ? resp.country : "";
      callback(countryCode);
    });
  },
  initialCountry: "auto",
  nationalMode: true,
  separateDialCode: true,
  utilsScript: "assets/template/assets/libs/intlTelInput/utils.js",
});

var reset = function () {
  input.classList.remove("error");
  errorMsg.innerHTML = "";
  errorMsg.classList.add("hide");
  validMsg.classList.add("hide");
};

// on blur: validate
input.addEventListener("blur", function () {
  reset();
  if (input.value.trim()) {
    if (iti.isValidNumber()) {
      $("#phone").val(iti.getNumber());

      validMsg.classList.remove("hide");
    } else {
      input.classList.add("error");
      var errorCode = iti.getValidationError();
      errorMsg.innerHTML = errorMap[errorCode];
      errorMsg.classList.remove("hide");
    }
  }
});

// on keyup / change flag: reset
input.addEventListener("change", reset);
input.addEventListener("keyup", reset);

$(function () {
  var count = $("#count_address").val();
  for (var no = 1; no <= count; no++) {
    cdp_load_countries(no);
    cdp_load_states(no);
    cdp_load_cities(no);
  }
});

function cdp_load_countries(count) {
  $("#country" + count)
    .select2({
      ajax: {
        url: "ajax/select2_countries.php",
        dataType: "json",

        delay: 250,
        data: function (params) {
          return {
            q: params.term, // search term
          };
        },
        processResults: function (data) {
          return {
            results: data,
          };
        },
        cache: true,
      },
      placeholder: translate_search_country,
      allowClear: true,
    })
    .on("change", function (e) {
      var country = $("#country" + count).val();

      $("#state" + count).attr("disabled", true);
      $("#state" + count).val(null);

      if (country != null) {
        $("#state" + count).attr("disabled", false);
      }

      cdp_load_states(count);
    });
}

function cdp_load_states(count) {
  var country = $("#country" + count).val();

  $("#state" + count)
    .select2({
      ajax: {
        url: "ajax/select2_states.php?id=" + country,
        dataType: "json",

        delay: 250,
        data: function (params) {
          return {
            q: params.term, // search term
          };
        },
        processResults: function (data) {
          return {
            results: data,
          };
        },
        cache: true,
      },
      placeholder: translate_search_state,
      allowClear: true,
    })
    .on("change", function (e) {
      var state = $("#state" + count).val();

      $("#city" + count).attr("disabled", true);
      $("#city" + count).val(null);

      if (state != null) {
        $("#city" + count).attr("disabled", false);
      }

      cdp_load_cities(count);
    });
}

function cdp_load_cities(count) {
  var state = $("#state" + count).val();

  $("#city" + count).select2({
    ajax: {
      url: "ajax/select2_cities.php?id=" + state,
      dataType: "json",
      delay: 250,
      data: function (params) {
        return {
          q: params.term, // search term
        };
      },
      processResults: function (data) {
        return {
          results: data,
        };
      },
      cache: true,
    },
    placeholder: translate_search_city,
    allowClear: true,
  });
}

$(function () {
  var count = $("#count_address").val();

  $(document).on("click", "#add_row", function () {
    count++;
    var total = $("#total_address").val(count);
    var html_code = "";
    var parent = $("#div_parent_" + count);
    html_code += '<div id="div_parent_' + count + '">';
    html_code += "<hr>";
    html_code += "<h4>" + translate_label_address + " " + count + "</h4>";
    html_code += '<div class="row">';

    html_code +=
      '<div class="col-md-4 mb-3">' +
      '<div class="form-group" >' +
      '<label class="control-label col-form-label">' +
      translate_label_country +
      "</label>" +
      '<select  class="select2 form-control custom-select" name="country[]" id="country' +
      count +
      '">' +
      "</select>" +
      "</div >" +
      "</div > ";

    html_code +=
      '<div class="col-md-4 mb-3">' +
      '<div class="form-group" >' +
      '<label class="control-label col-form-label">' +
      translate_label_state +
      "</label>" +
      '<select  disabled class="select2 form-control custom-select" name="state[]" id="state' +
      count +
      '">' +
      "</select>" +
      "</div >" +
      "</div > ";

    html_code +=
      '<div class="col-md-4 mb-3">' +
      '<div class="form-group" >' +
      '<label class="control-label col-form-label">' +
      translate_label_city +
      "</label>" +
      '<select  disabled class="select2 form-control custom-select" name="city[]" id="city' +
      count +
      '">' +
      "</select>" +
      "</div >" +
      "</div > ";

    html_code +=
      '<div class="col-md-4">' +
      '<div class="form-group">' +
      '<label class="control-label col-form-label">' +
      translate_label_zip +
      "</label>" +
      '<input type="text" class="form-control" name="postal[]" id="postal' +
      count +
      '" >' +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-md-4">' +
      '<div class="form-group">' +
      '<label class="control-label col-form-label">' +
      translate_label_address +
      "</label>" +
      '<input type="text" class="form-control" name="address[]" id="address' +
      count +
      '" >' +
      "</div>" +
      "</div>";

    html_code += '<div class="col-md-4">';
    html_code += "   <label> &nbsp;</label>";
    html_code += '  <div class="form-group">';
    html_code +=
      '      <button type="button" name="remove_row" id="' +
      count +
      '"  class="btn btn-danger remove_row">' +
      '<span class="fa fa-trash"></span>' +
      translate_delete_address +
      "" +
      "</button >" +
      "</div >" +
      "</div >";

    html_code += "</div>"; //div parent

    $("#div_address_multiple").append(html_code);

    cdp_load_countries(count);
    cdp_load_states(count);
    cdp_load_cities(count);
  });
 

// Función para confirmar eliminación
function confirmDelete(addressId, rowId) {
    Swal.fire({
        title: translate_delete_address,
        html: `<p class="messi-warning">${message_delete_confirm}<br /><strong>${message_delete_confirm2}</strong></p>`,
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#336aea',
        cancelButtonColor: '#eb644c',
        confirmButtonText: message_delete_confirm1,
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return $.ajax({
                type: 'post',
                url: './ajax/customers/customers_delete_address_ajax.php',
                dataType: 'json',
                data: {
                    id: addressId,
                },
                beforeSend: function () {
                    $("#div_parent_" + rowId).animate({
                        backgroundColor: "#FFBFBF",
                    }, 400);
                    $("#resultados_ajax").html("");
                },
            });
        },
     }).then(function (result) {
        if (result.value.success) {
            cdp_showSuccess(result.value.messages);
            count--;
            $("#div_parent_" + rowId).fadeOut(400, function () {
                $("#div_parent_" + rowId).remove();
            });
            $("#total_address").val(count);
            
        } else {
            cdp_showError(result.value.errors);
            
        }
    });
}

// Evento click en el botón de eliminar dirección
$(document).on("click", ".remove_row", function () {
    var row_id = $(this).attr("id");
    var address_id = $("#address_id" + row_id).val();

    if (address_id) {
        confirmDelete(address_id, row_id);
    } else {
        count--;
        $("#div_parent_" + row_id).fadeOut(400, function () {
            $("#div_parent_" + row_id).remove();
        });
        $("#total_address").val(count);
    }
});



});

 


$("#edit_user").on("submit", function (event) {
      var count = $('#total_address').val();

      // Validación de direcciones
      for (var no = 1; no <= count; no++) {
          var addressField = $('#address' + no);
          var countryField = $('#country' + no);
          var stateField = $('#state' + no);
          var cityField = $('#city' + no);
          var postalField = $('#postal' + no);

          if ($.trim(addressField.val()).length === 0 ||
              $.trim(countryField.val()).length === 0 ||
              $.trim(stateField.val()).length === 0 ||
              $.trim(cityField.val()).length === 0 ||
              $.trim(postalField.val()).length === 0) {
              Swal.fire({
                  type: 'error',
                  title: message_error_form1,
                  text: message_error_form2,
                  confirmButtonColor: '#336aea',
                  showConfirmButton: true,
              });
              addressField.focus();
              return false;
          }
      }

  if (iti.isValidNumber()) {


    $("#save_data").attr("disabled", true);
    var parametros = $(this).serialize();

    var username = $("#username").val();
    var email = $("#email").val();
    var document_type = $("#document_type").val();
    var document_number = $("#document_number").val();
    var fname = $("#fname").val();
    var lname = $("#lname").val();
    var notes = $("#notes").val();
    var phone = $("#phone").val();
    var gender = $("#gender").val();
    var password = $("#password").val();
    var notify = $("#notify:checked").val();
    var active = $("input:radio[name=active]:checked").val();
    var newsletter = $("input:radio[name=newsletter]:checked").val();
    var total_address = $("#total_address").val();
    var id = $("#id").val();

    var address = document.getElementsByName("address[]");
    var country = document.getElementsByName("country[]");
    var state = document.getElementsByName("state[]");
    var city = document.getElementsByName("city[]");
    var postal = document.getElementsByName("postal[]");
    var address_id = document.getElementsByName("address_id[]");

    // Almacena los nombres de los campos faltantes
    var missingFields = [];

    // Verifica si los campos obligatorios están vacíos y guarda los nombres en el array
    if (!email) missingFields.push(message_error_form8);
    if (!fname) missingFields.push(message_error_form9);
    if (!lname) missingFields.push(message_error_form10);
    if (!phone) missingFields.push(message_error_form11);
    if (!document_type) missingFields.push(message_error_form12);
    if (!document_number) missingFields.push(message_error_form13);

    // Verifica si hay campos faltantes
    if (missingFields.length > 0) {
        // Construye el mensaje de alerta
        var alertMessage = message_error_form5;
        alertMessage += '\n\n- ' + missingFields.join('\n- ');

        // Muestra el mensaje de alerta
        Swal.fire({
            type: 'error',
            title: message_error_form1,
            text: alertMessage,
            confirmButtonColor: '#336aea',
            showConfirmButton: true,
        });

    } else { 

    var data = new FormData();

    data.append("username", username);
    data.append("password", password);
    data.append("document_number", document_number);
    data.append("document_type", document_type);
    data.append("fname", fname);
    data.append("lname", lname);
    data.append("email", email);
    data.append("phone", phone);
    data.append("gender", gender);
    data.append("active", active);
    data.append("newsletter", newsletter);
    data.append("notes", notes);
    data.append("notify", notify);
    data.append("total_address", total_address);
    data.append("id", id);

    for (var a of address) {
      data.append("address[]", a.value);
    }
    for (var c of country) {
      data.append("country[]", c.value);
    }
    for (var c of state) {
      data.append("state[]", c.value);
    }
    for (var ci of city) {
      data.append("city[]", ci.value);
    }
    for (var p of postal) {
      data.append("postal[]", p.value);
    }
    for (var ai of address_id) {
      data.append("address_id[]", ai.value);
    }
 
    $.ajax({
      type: "POST",
      url: "ajax/customers/customers_edit_ajax.php",
      data: data,
      contentType: false,
      cache: false,
      processData: false,
      beforeSend: function (objeto) {

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
                    window.location.href = 'customers_list.php';
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
        error: function() {
            Swal.close();
            Swal.fire({
                type: 'error',
                title: message_error_form18,
                text: message_error_form19,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });
        },

    });

  }

  } else {
    input.classList.add("error");
    var errorCode = iti.getValidationError();
    errorMsg.innerHTML = errorMap[errorCode];
    errorMsg.classList.remove("hide");
  }
  event.preventDefault();
});



$(document).ready(function() {
    $('#edit_avatar_form').on('submit', function(event) {
        event.preventDefault(); // Evita que el formulario se envíe de forma convencional
        updateAvatar();
    });

    function updateAvatar() {
        var formData = new FormData($('#edit_avatar_form')[0]);

        $.ajax({
            type: 'POST',
            url: './ajax/customers/customers_avatar_edit_ajax.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // Manejar la respuesta del servidor
                 if (response.success) {
                    // Mostrar SweetAlert2 de éxito
                    Swal.fire({
                        type: 'success',
                        title: 'Avatar updated',
                        text: response.message
                    }).then(() => {
                        // Redirigir al mismo sitio
                        window.location.href = window.location.href;
                    });
                } else {
                    // Mostrar SweetAlert2 de error
                    Swal.fire({
                         type: 'error',
                        title: 'Avatar Update Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                // Manejar errores de conexión u otros errores
                Swal.fire({
                    type: 'error',
                    title: 'Error',
                    text: 'Connection or processing error on the server.'
                });
            }
        });
    }
});




function cdp_showError(errors) {
  var html_code = "";

  html_code +=
    '<div class="alert alert-danger" id="success-alert">' +
    '<p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>' +
    '<ul class="error" > ';

  for (var error in errors) {
    html_code += "<li>";
    html_code += '<i class="icon-double-angle-right"></i>';
    html_code += errors[error];
    html_code += "</li>";
  }
  "    </ul > " + " </p > " + " </div > ";

  $("#resultados_ajax").append(html_code);
}

function cdp_showSuccess(messages) {
  var html_code = "";

  html_code +=
    '<div class="alert alert-info" id="success-alert">' +
    '<p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>';

  for (var message in messages) {
    html_code += messages[message];
  }
  " </p > " + "</div > ";

  $("#resultados_ajax").append(html_code);
}
