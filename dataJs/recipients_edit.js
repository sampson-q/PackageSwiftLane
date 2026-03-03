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

    $("#total_address").val(count);

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
                  url: './ajax/recipients/recipients_delete_address_ajax.php',
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



// update recipients
$("#save_user").on("submit", function (event) {
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


      // Validación de número de teléfono
      if (!iti.isValidNumber()) {
          Swal.fire({
              type: 'error',
              title: message_error_form3,
              text: message_error_form4,
              confirmButtonColor: '#336aea',
              showConfirmButton: true,
          });
          $('#phone_custom').addClass('is-invalid');
          return false;
      }

    var email = $("#email").val();
    var fname = $("#fname").val();
    var lname = $("#lname").val();
    var total_address = $("#total_address").val();
    var id = $("#id").val();
    var originalEmail = $("#original_email").val();

    // Validar si el correo electrónico ha sido modificado
    if (email !== originalEmail) {
        // Realizar la solicitud AJAX para verificar si el correo electrónico existe en la base de datos
        $.ajax({
            type: 'POST',
            url: 'ajax/recipients/recipients_check_email_exist_ajax.php',
            data: { email: email },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'error') {
                    Swal.fire({
                        type: 'error',
                        title: 'Error!',
                        text: response.message,
                    });
                    $('#email').addClass('is-invalid');
                } else {
                    // Si el correo electrónico no existe en la base de datos, proceder con la actualización de los datos
                    updateRecipientData();
                }
            },
            error: function () {
                Swal.fire({
                    type: 'error',
                    title: 'Error!',
                    text: message_error_form17,
                });
            }
        });
    } else {
        // Si el correo electrónico no ha sido modificado, proceder con la actualización de los datos
        updateRecipientData();
    }

    // Función para actualizar los datos del destinatario
    function updateRecipientData() {
        // Aquí colocarías el código para enviar los datos del destinatario al servidor para su actualización

        var formData = new FormData($("#save_user")[0]);
        $.ajax({
            url: "./ajax/recipients/recipients_edit_ajax.php",
            type: 'POST',
            data: formData,
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
                        // Redirigir al listado de clientes
                        window.location.href = 'recipients_list.php';
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
    }

    event.preventDefault();
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
