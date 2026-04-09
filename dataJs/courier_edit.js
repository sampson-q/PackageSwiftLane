"use strict";
var deleted_file_ids = [];
var packagesItems = [];

// Última cotización obtenida desde el endpoint de tarifas.
// Si no hay cotización, se usa el precio por libra del formulario.
window.lastQuote = window.lastQuote || null;

// Tiempo de espera para llamadas automáticas al endpoint
const AUTO_FETCH_DEBOUNCE = 400;
let autoFetchTimer = null;

function getShipment() {
  var order_id = $("#order_id").val();
  $.ajax({
    type: "POST",
    url: "ajax/courier/get_data_shipment_edit_ajax.php?id=" + order_id,
    dataType: "json",
    success: function (datos) {
      // datos debe venir como array de objetos {qty, description, length, width, height, weight, declared_value, fixed_value}
      packagesItems = datos || [];
      loadPackages();

      // Siempre mostramos totales y recalculamos al cargar
      $("#table-totals").removeClass("d-none");
      calculateFinalTotal();

      // Si el modo es automático, pedimos tarifa inicial al motor
      if (!$("#tariff_mode").is(":checked")) {
        scheduleAutoFetch(true);
      }
    },
  });
}

$(function () {
  getShipment();

  $("#order_date").datepicker({
    format: "yyyy-mm-dd",
    autoclose: true,
  });

  $("#register_customer_to_user").on("click", function () {
    if ($(this).is(":checked")) {
      $("#show_hide_user_inputs").removeClass("d-none");
    } else {
      $("#show_hide_user_inputs").addClass("d-none");
    }
  });

  // ==========================
  // Modo de tarifa: manual (checkbox on) vs automático (off)
  // ==========================
  $("#tariff_mode").on("change click", function () {
    var manual = $(this).is(":checked");

    // Manual → el usuario escribe el precio por libra
    // Automático → viene del motor de tarifas
    $("#price_lb").prop("readonly", !manual);

    if (manual) {
      // En manual ignoramos cualquier cotización previa
      window.lastQuote = null;
    } else {
      // En automático pedimos inmediatamente una tarifa
      scheduleAutoFetch(true);
    }

    scheduleRecalc();
  });

  // Estado inicial de price_lb según el modo
  if (!$("#tariff_mode").is(":checked")) {
    $("#price_lb").prop("readonly", true);
  }

  // ==========================
  // País / Estado / Ciudad
  // ==========================
  cdp_load_countries("_modal_user");
  cdp_load_states("_modal_user");
  cdp_load_cities("_modal_user");

  cdp_load_countries("_modal_recipient");
  cdp_load_states("_modal_recipient");
  cdp_load_cities("_modal_recipient");

  cdp_load_countries("_modal_user_address");
  cdp_load_states("_modal_user_address");
  cdp_load_cities("_modal_user_address");

  cdp_load_countries("_modal_recipient_address");
  cdp_load_states("_modal_recipient_address");
  cdp_load_cities("_modal_recipient_address");

  cdp_select2_init_sender();
  cdp_select2_init_sender_address();
  cdp_select2_init_recipient_address();
  cdp_select2_init_recipient();

  // ==========================
  // Listeners para recálculo / motor de tarifas
  // ==========================

  // Cuando cambian direcciones/servicio/proveedor/distancia → afecta motor
  $("#sender_address_id, #recipient_address_id, #order_service_options, #order_item_category, #rate_provider, #distance_miles")
    .on("change", function () {
      scheduleAutoFetch();
    });

  // Campos numéricos que afectan totales
  $("#price_lb, #insured_value, #insurance_value, #reexpedicion_value, #discount_value, #tax_value, #declared_value_tax, #tariffs_value, #core_meter, #core_min_cost_tax, #core_min_cost_declared_tax")
    .on("input change", function () {
      scheduleRecalc();
    });

  // Primer cálculo al cargar la página según el modo
  if (!$("#tariff_mode").is(":checked")) {
    scheduleAutoFetch(true);
  } else {
    calculateFinalTotal();
  }
});

function cdp_load_countries(modal) {
  $("#country" + modal)
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
      var country = $("#country" + modal).val();

      $("#state" + modal).attr("disabled", true);
      $("#state" + modal).val(null);

      if (country != null) {
        $("#state" + modal).attr("disabled", false);
      }

      cdp_load_states(modal);
    });
}

function cdp_load_states(modal) {
  var country = $("#country" + modal).val();

  $("#state" + modal)
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
      var state = $("#state" + modal).val();

      $("#city" + modal).attr("disabled", true);
      $("#city" + modal).val(null);

      if (state != null) {
        $("#city" + modal).attr("disabled", false);
      }

      cdp_load_cities(modal);
    });
}

function cdp_load_cities(modal) {
  var state = $("#state" + modal).val();

  $("#city" + modal).select2({
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

function cdp_deleteImgAttached(id) {
  var parent = $("#file_delete_item_" + id);
  var name = $(this).attr("data-rel");
  new Messi(
    '<p class="messi-warning"><i class="icon-warning-sign icon-3x pull-left"></i>' +
      message_delete_confirm +
      "<br /><strong>" +
      message_delete_confirm2 +
      "</strong></p>",
    {
      title: "Delete file",
      titleClass: "",
      modal: true,
      closeButton: true,
      buttons: [
        {
          id: 0,
          label: message_delete_confirm1,
          class: "",
          val: "Y",
        },
      ],
      callback: function (val) {
        if (val === "Y") {
          $.ajax({
            type: "post",
            url: "./ajax/courier/courier_files_uploads_delete_ajax.php",
            data: {
              id: id,
            },
            beforeSend: function () {
              parent.animate(
                {
                  backgroundColor: "#FFBFBF",
                },
                400
              );

              parent.remove();
            },
            success: function (data) {
              $("#resultados_ajax_delete_file").html(data);
            },
          });
        }
      },
    }
  );
}

function cdp_preview_images() {
  $("#image_preview").html("");
  var total_file = document.getElementById("filesMultiple").files.length;
  for (var i = 0; i < total_file; i++) {
    var mime_type = event.target.files[i].type.split("/");
    var src = "";
    if (mime_type[0] == "image") {
      src = URL.createObjectURL(event.target.files[i]);
    } else {
      src = "assets/images/no-preview.jpeg";
    }

    $("#image_preview").append(
      '<div class="col-md-3" id="image_' +
        i +
        '">' +
        '<img style="width: 180px; height: 180px;" class="img-thumbnail" src="' +
        src +
        '">' +
        '<div class="row">' +
        '<div class=" col-md-12 mt-2 mb-2">' +
        "<span>" +
        event.target.files[i].name +
        "</span>" +
        "</div>" +
        "</div>" +
        '<div class="row">' +
        '<div class="  mb-2">' +
        '<button type="button" class="btn btn-danger btn-sm pull-left" onclick="cdp_deletePreviewImage(' +
        i +
        ');"><i class="fa fa-trash"></i></button>' +
        "</div>" +
        "</div>" +
        "</div>"
    );
  }
}

function cdp_deletePreviewImage(index) {
  deleted_file_ids.push(index);

  $("#deleted_file_ids").val(deleted_file_ids);

  $("#image_" + index).remove();

  var count_files = $("#total_item_files").val();

  count_files--;

  $("#total_item_files").val(count_files);

  if (count_files > 0) {
    $("#clean_files").removeClass("hide");
  } else {
    $("#clean_files").addClass("hide");
  }

  $("#selectItem").html("attached files (" + count_files + ")");

  var deleted_file = $("#deleted_file_ids").val();
}

function cdp_validateZiseFiles() {
  var inputFile = document.getElementById("filesMultiple");
  var file = inputFile.files;
  var size = 0;

  for (var i = 0; i < file.length; i++) {
    var filesSize = file[i].size;
    if (size > 5242880) {
      $(".resultados_file").html(
        "<div class='alert alert-danger'>" +
          "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
          "<strong>" +
          validation_files_size +
          " </strong>" +
          "</div>"
      );

      $("#filesMultiple").val("");
      $("#clean_files").addClass("hide");
      $("#image_preview").html("");
    } else {
      $(".resultados_file").html("");
    }

    size += filesSize;
  }

  if (size > 5242880) {
    $(".resultados_file").html(
      "<div class='alert alert-danger'>" +
        "<button type='button' class='close' data-dismiss='alert'>&times;</button>" +
        "<strong>" +
        validation_files_size +
        " </strong>" +
        "</div>"
    );

    $("#filesMultiple").val("");
    $("#clean_files").addClass("hide");
    $("#image_preview").html("");
    return true;
  } else {
    $(".resultados_file").html("");

    return false;
  }
}

$("#openMultiFile").on("click", function () {
  $("#filesMultiple").click();
});

$("#clean_file_button").on("click", function () {
  $("#filesMultiple").val("");
  $("#selectItem").html("Attach files");
  $("#clean_files").addClass("hide");
  $("#image_preview").html("");
  $(".resultados_file").html("");
});

$("input[type=file]").on("change", function () {
  deleted_file_ids = [];
  var inputFile = document.getElementById("filesMultiple");
  var file = inputFile.files;
  var contador = 0;
  for (var i = 0; i < file.length; i++) {
    contador++;
  }
  $("#total_item_files").val(contador);

  var count_files = $("#total_item_files").val();

  if (count_files > 0) {
    $("#clean_files").removeClass("hide");
  } else {
    $("#clean_files").addClass("hide");
  }

  $("#selectItem").html("attached files (" + count_files + ")");
});

function loadPackages() {
  $("#data_items").html("");

  // Si por alguna razón viene vacío, aseguramos al menos una fila
  if (!Array.isArray(packagesItems) || packagesItems.length === 0) {
    packagesItems = [
      {
        qty: 1,
        description: "",
        length: 0,
        width: 0,
        height: 0,
        weight: 0,
        declared_value: 0,
        fixed_value: 0,
      },
    ];
  }

  packagesItems.forEach(function (item, index) {
    var html_code = "";
    html_code += '<div class="card-hover" id="row_id_' + index + '">';
    html_code += "<hr>";
    html_code += '<div class="row"> ';
    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_quantity +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.qty != null ? item.qty : 1) +
      '" onkeypress="return isNumberKey(event, this)"  name="qty" id="qty_' +
      index +
      '" class="form-control input-sm" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_quantity +
      '" />' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-3">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_description +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.description != null ? item.description : "") +
      '" name="description" id="description_' +
      index +
      '" class="form-control input-sm" data-toggle="tooltip" data-placement="bottom" placeholder=" ' +
      translate_description +
      '" >' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_weight +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.weight != null ? item.weight : 0) +
      '" onkeypress="return isNumberKey(event, this)"  name="weight" id="weight_' +
      index +
      '" class="form-control input-sm" style="border: 1px solid red;" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_weight +
      '"/>' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_length +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.length != null ? item.length : 0) +
      '" onkeypress="return isNumberKey(event, this)" name="length" id="length_' +
      index +
      '" class="form-control input-sm text_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_length +
      '"/>' +
      "</div>" +
      "</div>" +
      "</div>";
    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_width +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.width != null ? item.width : 0) +
      '" onkeypress="return isNumberKey(event, this)" name="width" id="width_' +
      index +
      '" class="form-control input-sm text_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_width +
      '"/>' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_height +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.height != null ? item.height : 0) +
      '" onkeypress="return isNumberKey(event, this)"  name="height" id="height_' +
      index +
      '" class="form-control input-sm number_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_height +
      '" />' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_volweight +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" readonly value="' +
      (item.weightVol != null ? item.weightVol : 0) +
      '" onkeypress="return isNumberKey(event, this)"  name="weightVol" id="weightVol_' +
      index +
      '" class="form-control input-sm number_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_volweight +
      '" />' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_charge +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.fixed_value != null ? item.fixed_value : 0) +
      '" onkeypress="return isNumberKey(event, this)"  name="fixed_value" id="fixedValue_' +
      index +
      '" class="form-control input-sm number_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_charge +
      '"/>' +
      "</div>" +
      "</div>" +
      "</div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      '<label for="emailAddress1"> ' +
      translate_declared +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.declared_value != null ? item.declared_value : 0) +
      '" onkeypress="return isNumberKey(event, this)"  name="declared_value" id="declaredValue_' +
      index +
      '" class="form-control input-sm number_only" data-toggle="tooltip" data-placement="bottom" title="' +
      translate_declared +
      '"/>' +
      "</div>" +
      "</div>" +
      "</div>";

    if (index > 0) {
      html_code +=
        '<div class="col-sm-12 col-md-6 col-lg-1">' +
        '<div class="form-group  mt-4">' +
        '<button type="button"  onclick="deletePackage(' +
        index +
        ')"  name="remove_rows"  class="btn btn-outline-danger "><i class="fa fa-trash"></i></button>' +
        "</div>" +
        "</div>";
    }
    html_code += "</div>";

    html_code += "<hr>";

    html_code += "</div>";

    $("#data_items").append(html_code);
  });
}

function addPackage() {
  packagesItems.push({
    qty: 1,
    description: "",
    length: 0,
    width: 0,
    height: 0,
    weight: 0,
    declared_value: 0,
    fixed_value: 0,
  });

  var index = packagesItems.length - 1;

  loadPackages();
  calculateFinalTotal();

  $("#row_id_" + index).animate(
    {
      backgroundColor: "#18BC9C",
    },
    400
  );

  $("#add_row").attr("disabled", true);

  setTimeout(function () {
    $("#row_id_" + index).css({ "background-color": "" });
    $("#add_row").attr("disabled", false);
  }, 900);
}

function deletePackage(index) {
  packagesItems = packagesItems.filter(function (item, i) {
    return index !== i;
  });
  $("#row_id_" + index).animate(
    {
      backgroundColor: "#FFBFBF",
    },
    400
  );

  $("#row_id_" + index).fadeOut(400, function () {
    $("#row_id_" + index).remove();
    loadPackages();
    calculateFinalTotal();
  });
}

function changePackage(e) {
  var field = e.id.split("_");
  var idx = parseInt(field[1], 10);

  packagesItems = packagesItems.map(function (item, index) {
    if (index === idx) {
      item[e.name] = e.value || 0;
    }

    if (field[0] !== "description") {
      if (!e.value) {
        $("#" + e.id).val(0);
        item[e.name] = 0;
      }
    }
    return item;
  });

  // Recalcular totales
  calculateFinalTotal();

  // Si estamos en modo automático, reconsultar tarifa
  if (!$("#tariff_mode").is(":checked")) {
    scheduleAutoFetch();
  }
}

// ==========================
// Cálculo de totales (mismo motor que courier_add.js)
// ==========================
function calculateFinalTotal(element) {
  if (element && !element.value) {
    $(element).val(0);
  }

  var tariffs_value = nf($("#tariffs_value").val());
  var declared_value_tax = nf($("#declared_value_tax").val());
  var insurance_value = nf($("#insurance_value").val());
  var tax_value = nf($("#tax_value").val());
  var discount_value = nf($("#discount_value").val());
  var reexpedicion_value = nf($("#reexpedicion_value").val());
  var price_lb = nf($("#price_lb").val());
  var insured_value = nf($("#insured_value").val());
  var core_meter = nf($("#core_meter").val());
  var core_min_cost_tax = nf($("#core_min_cost_tax").val());
  var core_min_cost_declared_tax = nf($("#core_min_cost_declared_tax").val());

  var isManual = $("#tariff_mode").is(":checked");

  var sum_weight_real = 0;
  var sum_weight_vol = 0;
  var sum_declared = 0;
  var sum_fixed = 0;

  (packagesItems || []).forEach(function (item, i) {
    var qty = Math.max(1, nf(item.qty, 1));
    var weight = nf(item.weight);
    var length = nf(item.length);
    var width = nf(item.width);
    var height = nf(item.height);
    var fixed = nf(item.fixed_value);
    var decl = nf(item.declared_value);

    var vol_piece = 0;
    if (core_meter > 0) vol_piece = (length * width * height) / core_meter;
    if ($("#weightVol_" + i).length) $("#weightVol_" + i).val(r2(vol_piece));

    sum_weight_real += weight * qty;
    sum_weight_vol += vol_piece * qty;
    sum_declared += decl * qty;
    sum_fixed += fixed * qty;
  });

  var chargeable = Math.max(
    nf(sum_weight_real.toFixed(2)),
    nf(sum_weight_vol.toFixed(2))
  );
  if ($("#chargeable_weight").length) {
    $("#chargeable_weight").val(r2(chargeable));
  }

  var base_flete = 0;
  if (isManual) {
    base_flete = chargeable * price_lb;
  } else {
    if (window.lastQuote && window.lastQuote.success) {
      if (typeof window.lastQuote.total_tarifa !== "undefined") {
        base_flete = parseFloat(window.lastQuote.total_tarifa);
      } else if (
        window.lastQuote.data &&
        typeof window.lastQuote.data.price !== "undefined"
      ) {
        base_flete = chargeable * nf(window.lastQuote.data.price, price_lb);
      } else {
        base_flete = chargeable * price_lb;
      }
    } else {
      base_flete = chargeable * price_lb;
    }
  }

  var total_impuesto = 0;
  if (base_flete > core_min_cost_tax) {
    total_impuesto = (base_flete * tax_value) / 100;
  }

  var total_declared = 0;
  if (sum_declared > core_min_cost_declared_tax) {
    total_declared = (sum_declared * declared_value_tax) / 100;
  }

  var total_desc = (base_flete * discount_value) / 100;
  if (total_desc > base_flete || discount_value < 0) {
    $("#discount_value").val(0);
    total_desc = 0;
  }

  var total_seguro = (insured_value * insurance_value) / 100;
  var total_aduana =
    ((sum_weight_real + sum_weight_vol) * tariffs_value) / 100;

  var total =
    base_flete -
    total_desc +
    total_seguro +
    total_impuesto +
    total_aduana +
    total_declared +
    sum_fixed +
    reexpedicion_value;
  if (!isFinite(total) || total < 0) total = 0;

  $("#table-totals").removeClass("d-none");
  $("#subtotal").html(r2(base_flete));
  $("#discount").html(r2(total_desc));
  $("#impuesto").html(r2(total_impuesto));
  $("#declared_value_label").html(r2(total_declared));
  $("#fixed_value_label").html(r2(sum_fixed));
  $("#insurance").html(r2(total_seguro));
  $("#total_impuesto_aduanero").html(r2(total_aduana));
  $("#total_envio").html(r2(total));

  $("#total_weight").html(r2(sum_weight_real));
  $("#total_vol_weight").html(r2(sum_weight_vol));
  if ($("#total_fixed").length) $("#total_fixed").html(r2(sum_fixed));
  if ($("#total_declared").length) $("#total_declared").html(r2(sum_declared));
}

$("#invoice_form").on("submit", function (event) {
  event.preventDefault();

  if (cdp_validateZiseFiles() == true) {
    alert("error files");
    return false;
  }

  // Validación de filas de paquetes
  for (let [i, val] of packagesItems.entries()) {
    if ($.trim($("#description_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_description,
        confirmButtonText: "Ok",
      });
      $("#description_" + i).focus();
      return false;
    }
    if ($.trim($("#qty_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_quantity,
        confirmButtonText: "Ok",
      });
      $("#qty_" + i).focus();
      return false;
    }
    if ($.trim($("#weight_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_weight,
        confirmButtonText: "Ok",
      });
      $("#weight_" + i).focus();
      return false;
    }
    if ($.trim($("#length_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_length,
        confirmButtonText: "Ok",
      });
      $("#length_" + i).focus();
      return false;
    }
    if ($.trim($("#width_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_width,
        confirmButtonText: "Ok",
      });
      $("#width_" + i).focus();
      return false;
    }
    if ($.trim($("#height_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_height,
        confirmButtonText: "Ok",
      });
      $("#height_" + i).focus();
      return false;
    }
    if ($.trim($("#fixedValue_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_charge,
        confirmButtonText: "Ok",
      });
      $("#fixedValue_" + i).focus();
      return false;
    }
    if ($.trim($("#declaredValue_" + i).val()).length == 0) {
      Swal.fire({
        icon: "error",
        text: validation_declared,
        confirmButtonText: "Ok",
      });
      $("#declaredValue_" + i).focus();
      return false;
    }
  }

  var prefix_check = $("#prefix_check").val();
  var notify_sms_sender = $("input:checkbox[name=notify_sms_sender]:checked").val();
  var notify_sms_receiver = $("input:checkbox[name=notify_sms_receiver]:checked").val();
  var core_meter = $("#core_meter").val();
  var notify_whatsapp_sender = $("#notify_whatsapp_sender").val();
  var notify_whatsapp_receiver = $("#notify_whatsapp_receiver").val();
  var tariff_mode = $("input:checkbox[name=tariff_mode]:checked").val();

  var order_no = $("#order_no").val();
  var agency = $("#agency").val();
  var origin_off = $("#origin_off").val();
  var sender_id = $("#sender_id").val();
  var sender_address_id = $("#sender_address_id").val();
  var recipient_id = $("#recipient_id").val();
  var recipient_address_id = $("#recipient_address_id").val();
  var order_item_category = $("#order_item_category").val();
  var order_courier = $("#order_courier").val();
  var order_service_options = $("#order_service_options").val();
  var order_package = $("#order_package").val();
  var order_date = $("#order_date").val();
  var order_deli_time = $("#order_deli_time").val();
  var order_pay_mode = $("#order_pay_mode").val();
  var order_payment_method = $("#order_payment_method").val();
  var status_courier = $("#status_courier").val();
  var driver_id = $("#driver_id").val();
  var order_id = $("#order_id").val();
  var price_lb = $("#price_lb").val();
  var insured_value = $("#insured_value").val();
  var insurance_value = $("#insurance_value").val();
  var reexpedicion_value = $("#reexpedicion_value").val();
  var discount_value = $("#discount_value").val();
  var tax_value = $("#tax_value").val();
  var declared_value_tax = $("#declared_value_tax").val();
  var tariffs_value = $("#tariffs_value").val();
  var deleted_file_ids_val = $("#deleted_file_ids").val();
  var distance_miles = $("#distance_miles").val() || 0;

  var data = new FormData();

  data.append("packages", JSON.stringify(packagesItems));
  data.append("distance_miles", distance_miles);

  if (core_meter) data.append("meter", core_meter);
  if (prefix_check) data.append("prefix_check", prefix_check);
  if (order_id) data.append("order_id", order_id);
  if (order_no) data.append("order_no", order_no);
  if (agency) data.append("agency", agency);
  if (origin_off) data.append("origin_off", origin_off);
  if (sender_id) data.append("sender_id", sender_id);
  if (sender_address_id) data.append("sender_address_id", sender_address_id);
  if (recipient_id) data.append("recipient_id", recipient_id);
  if (recipient_address_id) data.append("recipient_address_id", recipient_address_id);
  if (order_item_category) data.append("order_item_category", order_item_category);
  if (order_courier) data.append("order_courier", order_courier);
  if (order_service_options) data.append("order_service_options", order_service_options);
  if (order_package) data.append("order_package", order_package);
  if (order_date) data.append("order_date", order_date);
  if (order_deli_time) data.append("order_deli_time", order_deli_time);
  if (order_pay_mode) data.append("order_pay_mode", order_pay_mode);
  if (order_payment_method) data.append("order_payment_method", order_payment_method);
  if (status_courier) data.append("status_courier", status_courier);
  if (driver_id) data.append("driver_id", driver_id);
  if (price_lb) data.append("price_lb", price_lb);
  if (insured_value) data.append("insured_value", insured_value);
  if (reexpedicion_value) data.append("reexpedicion_value", reexpedicion_value);
  if (discount_value) data.append("discount_value", discount_value);
  if (tax_value) data.append("tax_value", tax_value);
  if (declared_value_tax) data.append("declared_value_tax", declared_value_tax);
  if (tariffs_value) data.append("tariffs_value", tariffs_value);
  if (insurance_value) data.append("insurance_value", insurance_value);
  if (notify_whatsapp_sender) data.append("notify_whatsapp_sender", notify_whatsapp_sender);
  if (notify_sms_sender) data.append("notify_sms_sender", notify_sms_sender);
  if (notify_whatsapp_receiver) data.append("notify_whatsapp_receiver", notify_whatsapp_receiver);
  if (notify_sms_receiver) data.append("notify_sms_receiver", notify_sms_receiver);
  if (deleted_file_ids_val) data.append("deleted_file_ids", deleted_file_ids_val);
  if (tariff_mode) data.append("tariff_mode", tariff_mode);

  var total_file = document.getElementById("filesMultiple").files.length;
  for (var i = 0; i < total_file; i++) {
    data.append("filesMultiple[]", document.getElementById("filesMultiple").files[i]);
  }

  $.ajax({
    type: "POST",
    url: "ajax/courier/edit_courier_ajax.php",
    data: data,
    contentType: false,
    dataType: "json",
    cache: false,
    processData: false,
    beforeSend: function () {
      $("#create_invoice").attr("disabled", true);
      Swal.fire({
        title: message_loading || "Espera un momento por favor...",
        allowOutsideClick: false,
        didOpen: function () {
          Swal.showLoading();
        },
      });
    },
    success: function (response) {
      $("#create_invoice").attr("disabled", false);
      Swal.close(); // cerrar loader

      if (response && response.success) {
        var msg = "";
        if (Array.isArray(response.messages)) {
          msg = response.messages.join("<br>");
        } else if (typeof response.messages === "string") {
          msg = response.messages;
        }

        Swal.fire({
          icon: "success",
          title: "Envío actualizado",
          html: msg || "Los datos del envío se han guardado correctamente.",
        }).then(function () {
          if (response.shipment_id) {
            // Redirigir a la vista del paquete
            window.location.href = "courier_view.php?id=" + response.shipment_id;
          } else {
            // Como respaldo, recargar la página
            window.location.reload();
          }
        });
      } else {
        var errorHtml = "";

        if (response && response.errors) {
          if (Array.isArray(response.errors)) {
            errorHtml = response.errors.join("<br>");
          } else if (typeof response.errors === "object") {
            errorHtml = Object.values(response.errors).join("<br>");
          } else {
            errorHtml = response.errors;
          }
        }

        Swal.fire({
          icon: "error",
          title: "Error al actualizar",
          html: errorHtml || "No se pudo actualizar el envío.",
        });
      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      $("#create_invoice").attr("disabled", false);
      Swal.close();

      console.error("Error AJAX courier_edit:", textStatus, errorThrown);

      Swal.fire({
        icon: "error",
        title: "Error de comunicación",
        text: "No fue posible guardar los cambios. Intente nuevamente.",
      });
    },
  });

  return false;
});



function isNumberKey(evt, element) {
  var charCode = evt.which ? evt.which : event.keyCode;
  if (
    charCode > 31 &&
    (charCode < 48 || charCode > 57) &&
    !(charCode == 46 || charCode == 8)
  )
    return false;
  else {
    var len = $(element).val().length;
    var index = $(element).val().indexOf(".");
    if (index > 0 && charCode == 46) {
      return false;
    }
    if (index > 0) {
      var CharAfterdot = len + 1 - index;
      if (CharAfterdot > 4) {
        return false;
      }
    }
  }
  return true;
}

function cdp_select2_init_sender() {
  $("#sender_id")
    .select2({
      ajax: {
        url: "ajax/select2_sender.php",
        dataType: "json",
        delay: 250,
        data: function (params) {
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true,
      },
      minimumInputLength: 2,
      placeholder: search_sender,
      allowClear: true,
    })
    .on("change", function () {
      var sender_id = $("#sender_id").val();
      $("#sender_address_id").attr("disabled", true);
      $("#recipient_id").attr("disabled", true);
      $("#recipient_address_id").attr("disabled", true);
      $("#add_address_sender").attr("disabled", true);
      $("#add_recipient").attr("disabled", true);
      $("#add_address_recipient").attr("disabled", true);

      $("#recipient_id").val(null);
      $("#sender_address_id").val(null);
      $("#recipient_address_id").val(null);

      if (sender_id != null) {
        $("#add_address_sender").attr("disabled", false);
        $("#sender_address_id").attr("disabled", false);
        $("#recipient_id").attr("disabled", false);
        $("#add_recipient").attr("disabled", false);
      }
      cdp_select2_init_sender_address();
      cdp_select2_init_recipient_address();
      cdp_select2_init_recipient();

      scheduleAutoFetch();
    });
}

function cdp_select2_init_sender_address() {
  var sender_id = $("#sender_id").val();
  $("#sender_address_id")
    .select2({
      ajax: {
        url: "ajax/select2_sender_addresses.php?id=" + sender_id,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true,
      },
      escapeMarkup: function (markup) {
        return markup;
      },
      templateResult: cdp_formatAdress,
      templateSelection: cdp_formatAdressSelection,
      placeholder: search_sender_address,
      allowClear: true,
    })
    .on("change", function () {
      var sender_address_id = $("#sender_address_id").val();
      var recipient_address_id = $("#recipient_address_id").val();
      if (!recipient_address_id || !sender_address_id) {
        // nada
      }
      scheduleAutoFetch();
    });
}

function cdp_formatAdress(item) {
  if (item.loading) return item.text;
  var markup = "<div class='select2-result-repository clearfix'>";
  markup +=
    "<div class='select2-result-repository__statistics'>" +
    "<div class='select2-result-repository__forks'><i class='la la-code-fork mr-0'></i> <b> " +
    translate_search_address_address +
    ": </b> " +
    item.text +
    " | <b>" +
    translate_search_address_country +
    ": </b>" +
    item.country +
    " | <b>" +
    translate_search_address_state +
    ": </b>" +
    item.state +
    " | <b>" +
    translate_search_address_city +
    ": </b>" +
    item.city +
    " | <b>" +
    translate_search_address_zip +
    ": </b>" +
    item.zip_code +
    " </div>" +
    "</div>" +
    "</div></div>";
  return markup;
}

function cdp_formatAdressSelection(repo) {
  return repo.text;
}

var selectedRecipientType = 'recipient';

function cdp_select2_init_recipient() {
  var sender_id = $("#sender_id").val();

  $("#recipient_id")
    .select2({
      ajax: {
        url: "ajax/select2_recipient.php?id=" + sender_id,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true,
      },
      placeholder: search_recipient,
      allowClear: true,
    })
    .on("change", function () {
      var recipient_id = $("#recipient_id").val();
      $("#add_address_recipient").attr("disabled", true);
      $("#recipient_address_id").attr("disabled", true);
      $("#recipient_address_id").val(null);

      var selectedData = $("#recipient_id").select2("data");
      selectedRecipientType = selectedData && selectedData[0] && selectedData[0].type ? selectedData[0].type : "recipient";

      if (recipient_id != null) {
        $("#recipient_address_id").attr("disabled", false);
        $("#add_address_recipient").attr("disabled", false);
      }
      cdp_select2_init_recipient_address();
      scheduleAutoFetch();
    });
}

function cdp_select2_init_recipient_address() {
  var recipient_id = $("#recipient_id").val();

  $("#recipient_address_id")
    .select2({
      ajax: {
        url: "ajax/select2_recipient_addresses.php?id=" + recipient_id + "&type=" + selectedRecipientType,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true,
      },
      escapeMarkup: function (markup) {
        return markup;
      },
      templateResult: cdp_formatAdress,
      templateSelection: cdp_formatAdressSelection,
      placeholder: search_recipient_address,
      allowClear: true,
    })
    .on("change", function () {
      var recipient_address_id = $("#recipient_address_id").val();
      var sender_address_id = $("#sender_address_id").val();
      if (!recipient_address_id || !sender_address_id) {
        // nada
      }
      scheduleAutoFetch();
    });
}

// -----------------------------
// Modales de creación de cliente/direcciones
// (se mantienen igual que tenías)
// -----------------------------

// ... (todo el bloque de modales y teléfonos queda igual que en tu archivo, no lo repito por espacio)

// ==========================
// Utilidades de números y paquetes
// ==========================
function nf(v, def) {
  if (typeof def === "undefined") def = 0;
  var n = parseFloat(v);
  return isNaN(n) || !isFinite(n) ? def : n;
}

function r2(v) {
  var n = parseFloat(v);
  return isNaN(n) || !isFinite(n) ? "0.00" : n.toFixed(2);
}

function collectPackages() {
  return (packagesItems || []).map(function (p) {
    return {
      qty: nf(p.qty, 1),
      description: p.description || "",
      weight: nf(p.weight),
      length: nf(p.length),
      width: nf(p.width),
      height: nf(p.height),
      declared_value: nf(p.declared_value),
      fixed_value: nf(p.fixed_value),
    };
  });
}

// ==========================
// Helpers de recálculo / motor tarifas
// ==========================
function scheduleRecalc() {
  calculateFinalTotal();
}

// Programar llamadas al endpoint de tarifas
function scheduleAutoFetch(immediate) {
  if (typeof immediate === "undefined") immediate = false;

  // Si el modo es manual no se consulta el endpoint
  if ($("#tariff_mode").is(":checked")) {
    calculateFinalTotal();
    return;
  }

  if (immediate) {
    fetchTariff();
    return;
  }

  clearTimeout(autoFetchTimer);
  autoFetchTimer = setTimeout(fetchTariff, AUTO_FETCH_DEBOUNCE);
}

function fetchTariff() {
  var pkgs = collectPackages();
  var sender_id = $("#sender_id").val();
  var saddr_id = $("#sender_address_id").val();
  var recip_id = $("#recipient_id").val();
  var raddr_id = $("#recipient_address_id").val();
  var serviceOpt =
    $("#order_service_options").val() || $("#order_item_category").val() || null;
  var provider = $("#rate_provider").val() || "internal";
  var miles = nf($("#distance_miles").val(), 0);

  if (!sender_id || !recip_id || !saddr_id || !raddr_id) {
    window.lastQuote = null;
    $("#table-totals").removeClass("d-none");
    calculateFinalTotal();
    return;
  }

  $.ajax({
    url: "ajax/courier/get_price_range_weight_tariffs_ajax.php",
    type: "POST",
    dataType: "json",
    data: {
      packages: JSON.stringify(pkgs),
      sender_id: sender_id,
      sender_address: saddr_id,
      recipient_id: recip_id,
      recipient_address: raddr_id,
      order_service_options: serviceOpt,
      rate_provider: provider,
      distance_miles: miles,
    },
    success: function (res) {
      if (res && res.success) {
        window.lastQuote = res;
        var cw = nf(res.chargeable_weight, 0);
        var totalTarifa = nf(res.total_tarifa, 0);
        if (cw > 0 && totalTarifa > 0) {
          $("#price_lb").val((totalTarifa / cw).toFixed(2));
        } else {
          var unit = nf(res.data && res.data.price, 0);
          if (unit > 0) $("#price_lb").val(unit.toFixed(2));
        }
        if ($("#chargeable_weight").length)
          $("#chargeable_weight").val(cw.toFixed(2));
      } else {
        window.lastQuote = null;
        if (res && res.error) {
          Swal.fire({
            text: res.error,
            icon: "warning",
            confirmButtonText: "OK",
          });
        }
      }
      $("#table-totals").removeClass("d-none");
      calculateFinalTotal();
    },
    error: function () {
      window.lastQuote = null;
      $("#table-totals").removeClass("d-none");
      calculateFinalTotal();
    },
  });
}
