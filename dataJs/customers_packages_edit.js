"use strict";

var deleted_file_ids = [];
var packagesItems = [];

// Recipient type state (must mirror add flow)
window.recipient_type = window.recipient_type || "recipient";

function getShipment() {
  var order_id = $("#order_id").val();
  $.ajax({
    type: "POST",
    url: "ajax/customers_packages/get_data_customers_packages_edit_ajax.php?id=" + order_id,
    dataType: "json",
    success: function (datos) {
      packagesItems = datos || [];
      loadPackages();
      calculateFinalTotal();
    },
  });
}

$(function () {
  getShipment();

  // Unified preload of existing DB files
  preloadExistingFilesFromDom();

  // Datepicker (if present)
  if ($("#order_date").length && typeof $("#order_date").datepicker === "function") {
    $("#order_date").datepicker({
      format: "yyyy-mm-dd",
      autoclose: true,
    });
  }

  $("#register_customer_to_user").click(function () {
    if ($(this).is(":checked")) $("#show_hide_user_inputs").removeClass("d-none");
    else $("#show_hide_user_inputs").addClass("d-none");
  });

  // Sender modal selects
  cdp_load_countries("_modal_user");
  cdp_load_states("_modal_user");
  cdp_load_cities("_modal_user");

  cdp_load_countries("_modal_user_address");
  cdp_load_states("_modal_user_address");
  cdp_load_cities("_modal_user_address");

  // Recipient modal selects (mirror add)
  cdp_load_countries("_modal_recipient");
  cdp_load_states("_modal_recipient");
  cdp_load_cities("_modal_recipient");

  cdp_load_countries("_modal_recipient_address");
  cdp_load_states("_modal_recipient_address");
  cdp_load_cities("_modal_recipient_address");

  // Init Select2 (mirror add: sender + sender address + recipient + recipient address)
  cdp_select2_init_sender();
  cdp_select2_init_sender_address();
  cdp_select2_init_recipient();
  cdp_select2_init_recipient_address();

  // Prefill from hidden inputs without disabling/invisibility issues
  prefillSenderRecipientFromHiddenInputs();

  // Make sure attach label state is correct
  updateFileLabels();
  checkShowCleanButton();
});

/* ==========================
   Country/State/City Select2
   ========================== */
function cdp_load_countries(modal) {
  $("#country" + modal)
    .select2({
      ajax: {
        url: "ajax/select2_countries.php",
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
      placeholder: translate_search_country,
      allowClear: true,
    })
    .on("change", function () {
      var country = $("#country" + modal).val();

      $("#state" + modal).attr("disabled", true).val(null).trigger("change");
      if (country != null) $("#state" + modal).attr("disabled", false);

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
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true,
      },
      placeholder: translate_search_state,
      allowClear: true,
    })
    .on("change", function () {
      var state = $("#state" + modal).val();

      $("#city" + modal).attr("disabled", true).val(null).trigger("change");
      if (state != null) $("#city" + modal).attr("disabled", false);

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
        return { q: params.term };
      },
      processResults: function (data) {
        return { results: data };
      },
      cache: true,
    },
    placeholder: translate_search_city,
    allowClear: true,
  });
}

/* ==========================
   Existing DB files deletion
   ========================== */
function cdp_deleteImgAttached(id) {
  // EDIT MODE: do NOT delete immediately on the server.
  // Mark for deletion, remove from UI, and submit will delete transactionally.
  id = parseInt(id, 10);
  if (!id) return;

  var current = ($("#deleted_db_file_ids").val() || "").trim();
  var ids = current
    ? current
        .split(",")
        .map(function (x) {
          return parseInt(x, 10);
        })
        .filter(Boolean)
    : [];

  if (ids.indexOf(id) === -1) ids.push(id);

  $("#deleted_db_file_ids").val(ids.join(","));

  // Remove from unified preview if present
  var el = document.querySelector('.file-thumb[data-existing-id="' + id + '"]');
  if (el) el.remove();

  updateFileLabels();
  checkShowCleanButton();
}

/* ==========================
   Unified previews
   ========================== */
function cdp_preview_images() {
  var input = document.getElementById("filesMultiple");
  if (!input) return;

  var files = Array.from(input.files || []);
  var previewWrap = document.getElementById("image_preview");
  if (!previewWrap) return;

  // Remove only new upload thumbnails, not captured and not existing preloaded
  var existingUploads = previewWrap.querySelectorAll('.file-thumb[data-type="upload"]');
  existingUploads.forEach(function (el) {
    el.remove();
  });

  files.forEach(function (file) {
    var mimeRoot = (file.type || "").split("/")[0];
    var previewBlob;

    if (mimeRoot === "image") {
      previewBlob = file;
    } else {
      previewBlob = new Blob([], { type: "image/jpeg" });
      previewBlob.previewFallback = "assets/images/no-preview.jpeg";
    }

    addUnifiedThumbnail(previewBlob, file.name, file, "upload");
  });

  updateFileLabels();
  checkShowCleanButton();
}

function addUnifiedThumbnail(blob, filename, originalFile, fileType) {
  var previewWrap = document.getElementById("image_preview");
  if (!previewWrap) return;

  var isRealImage = !blob.previewFallback;
  var url = isRealImage ? URL.createObjectURL(blob) : blob.previewFallback;

  var container = document.createElement("div");
  container.className = "file-thumb";
  container.dataset.filename = filename;
  container.dataset.type = fileType;

  container.style.cssText =
    "display:inline-block;margin:6px;position:relative;width:130px;vertical-align:top;";

  var sizeKB = Math.round(((originalFile && originalFile.size) || blob.size || 0) / 1024);

  container.innerHTML =
    '<div style="position:relative;border-radius:10px;overflow:hidden;border:1px solid #ddd;background:#fff;">' +
    '  <img src="' +
    url +
    '" alt="' +
    filename +
    '" style="width:130px;height:100px;object-fit:cover;display:block;">' +
    '  <button type="button" class="remove-preview-btn" ' +
    '    style="position:absolute;top:6px;right:6px;width:24px;height:24px;border:none;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;font-size:14px;line-height:24px;">×</button>' +
    "</div>" +
    '<div style="font-size:11px;margin-top:5px;text-align:center;word-break:break-word;">' +
    filename +
    "</div>" +
    '<div style="font-size:10px;color:#666;text-align:center;">' +
    sizeKB +
    " KB</div>";

  previewWrap.prepend(container);

  var removeBtn = container.querySelector(".remove-preview-btn");
  removeBtn.addEventListener("click", function () {
    container.remove();

    // Remove from new-upload input(s)
    removeFileFromInputByName(document.getElementById("filesMultiple"), filename);
    removeFileFromInputByName(document.getElementById("filesCapture"), filename);

    // Remove from fallback captured list if any
    if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
      window.__capturedFilesFallback = window.__capturedFilesFallback.filter(function (f) {
        return f && f.name !== filename;
      });
    }

    // If this thumbnail represents an existing DB file, mark it for deletion
    var existingId = container.getAttribute("data-existing-id");
    if (existingId) {
      cdp_deleteImgAttached(existingId);
      return;
    }

    updateFileLabels();
    checkShowCleanButton();
  });

  if (isRealImage) {
    setTimeout(function () {
      URL.revokeObjectURL(url);
    }, 60000);
  }
}

function updateFileLabels() {
  var uploadCount = document.querySelectorAll('.file-thumb[data-type="upload"]').length;
  var cameraCount = document.querySelectorAll('.file-thumb[data-type="camera"]').length;
  var existingCount = document.querySelectorAll('.file-thumb[data-type="existing"]').length;

  var totalAttach = uploadCount + existingCount;

  if (totalAttach > 0) $("#selectItem").html("attached files (" + totalAttach + ")");
  else $("#selectItem").html("attached files");

  if ($("#captureItem").length) {
    if (cameraCount > 0) $("#captureItem").html("camera captures (" + cameraCount + ")");
    else $("#captureItem").html("camera captures");
  }
}

function checkShowCleanButton() {
  var totalThumbs = document.querySelectorAll(".file-thumb").length;
  if (totalThumbs > 0) $("#clean_files").removeClass("hide");
  else $("#clean_files").addClass("hide");
}

function removeFileFromInputByName(inputEl, filename) {
  if (!inputEl) return;
  try {
    var dt = new DataTransfer();
    Array.from(inputEl.files || []).forEach(function (f) {
      if (f.name !== filename) dt.items.add(f);
    });
    inputEl.files = dt.files;
  } catch (e) {
    console.warn("removeFileFromInputByName failed", e);
  }
}

/* ==========================
   File input controls
   ========================== */
$("#openMultiFile").on("click", function () {
  $("#filesMultiple").click();
});

$("#clean_file_button").on("click", function () {
  $("#filesMultiple").val("");
  $("#filesCapture").val("");

  $("#selectItem").html("Attach files");
  if ($("#captureItem").length) $("#captureItem").html("camera captures");

  $("#clean_files").addClass("hide");
  $("#image_preview").html("");
  $(".resultados_file").html("");

  deleted_file_ids = [];
  $("#deleted_file_ids").val("");
  $("#deleted_db_file_ids").val("");

  updateFileLabels();
  checkShowCleanButton();
});

$("input[type=file]").on("change", function () {
  deleted_file_ids = [];

  var inputFile = document.getElementById("filesMultiple");
  var file = inputFile ? inputFile.files : [];
  var contador = 0;

  for (var i = 0; i < (file ? file.length : 0); i++) contador++;

  $("#total_item_files").val(contador);

  var count_files = $("#total_item_files").val();
  if (count_files > 0) $("#clean_files").removeClass("hide");
  else $("#clean_files").addClass("hide");

  $("#selectItem").html("attached files (" + count_files + ")");

  // update preview thumbnails
  cdp_preview_images();
});

/* ==========================
   Packages UI
   ========================== */
function loadPackages() {
  $("#data_items").html("");

  (packagesItems || []).forEach(function (item, index) {
    var html_code = "";
    html_code += '<div class="card-hover" id="row_id_' + index + '">';
    html_code += "<hr>";
    html_code += '<div class="row"> ';

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_quantity +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.qty +
      '" onkeypress="return isNumberKey(event, this)" name="qty" id="qty_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_quantity +
      '" />' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-3">' +
      '<div class="form-group">' +
      "<label> " +
      translate_description +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      (item.description || "") +
      '" name="description" id="description_' +
      index +
      '" class="form-control input-sm" placeholder="' +
      translate_description +
      '">' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_weight +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.weight +
      '" onkeypress="return isNumberKey(event, this)" name="weight" id="weight_' +
      index +
      '" class="form-control input-sm" style="border: 1px solid red;" title="' +
      translate_weight +
      '"/>' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_length +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.length +
      '" onkeypress="return isNumberKey(event, this)" name="length" id="length_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_length +
      '"/>' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_width +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.width +
      '" onkeypress="return isNumberKey(event, this)" name="width" id="width_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_width +
      '"/>' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_height +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.height +
      '" onkeypress="return isNumberKey(event, this)" name="height" id="height_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_height +
      '" />' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_volweight +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" readonly value="0" name="weightVol" id="weightVol_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_volweight +
      '" />' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_charge +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.fixed_value +
      '" onkeypress="return isNumberKey(event, this)" name="fixed_value" id="fixedValue_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_charge +
      '"/>' +
      "</div></div></div>";

    html_code +=
      '<div class="col-sm-12 col-md-6 col-lg-1">' +
      '<div class="form-group">' +
      "<label> " +
      translate_declared +
      "</label>" +
      '<div class="input-group">' +
      '<input type="text" onchange="changePackage(this)" value="' +
      item.declared_value +
      '" onkeypress="return isNumberKey(event, this)" name="declared_value" id="declaredValue_' +
      index +
      '" class="form-control input-sm" title="' +
      translate_declared +
      '"/>' +
      "</div></div></div>";

    if (index > 0) {
      html_code +=
        '<div class="col-sm-12 col-md-6 col-lg-1">' +
        '<div class="form-group mt-4">' +
        '<button type="button" onclick="deletePackage(' +
        index +
        ')" class="btn btn-outline-danger"><i class="fa fa-trash"></i></button>' +
        "</div></div>";
    }

    html_code += "</div><hr></div>";

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

  $("#row_id_" + index).animate({ backgroundColor: "#18BC9C" }, 400);

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

  $("#row_id_" + index).animate({ backgroundColor: "#FFBFBF" }, 400);

  $("#row_id_" + index).fadeOut(400, function () {
    $("#row_id_" + index).remove();
    loadPackages();
    calculateFinalTotal();
  });
}

function changePackage(e) {
  var field = e.id.split("_");
  packagesItems = packagesItems.map(function (item, index) {
    if (index === parseInt(field[1], 10)) {
      item[e.name] = e.value;
    }

    if (field[0] !== "description") {
      if (!e.value) {
        $("#" + e.id).val(0);
        item[e.name] = 0;
      }
    }
    return item;
  });

  calculateFinalTotal();
}

/* ==========================
   Totals
   ========================== */
function calculateFinalTotal(element) {
  if (element && !element.value) $(element).val(0);

  var sumador_total = 0;
  var sumador_valor_declarado = 0;
  var max_fixed_charge = 0;
  var sumador_libras = 0;
  var sumador_volumetric = 0;

  var total_impuesto = 0;
  var total_descuento = 0;
  var total_seguro = 0;
  var total_peso = 0;
  var total_impuesto_aduanero = 0;
  var total_valor_declarado = 0;

  var tariffs_value = parseFloat($("#tariffs_value").val() || 0);
  var declared_value_tax = parseFloat($("#declared_value_tax").val() || 0);
  var insurance_value = parseFloat($("#insurance_value").val() || 0);
  var tax_value = parseFloat($("#tax_value").val() || 0);
  var discount_value = parseFloat($("#discount_value").val() || 0);
  var reexpedicion_value = parseFloat($("#reexpedicion_value").val() || 0);
  var price_lb = parseFloat($("#price_lb").val() || 0);
  var insured_value = parseFloat($("#insured_value").val() || 0);

  packagesItems.forEach(function (item, i) {
    var weight = parseFloat(item.weight || 0);
    var length = parseFloat(item.length || 0);
    var width = parseFloat(item.width || 0);
    var height = parseFloat(item.height || 0);
    var fixed_value = parseFloat(item.fixed_value || 0);
    var declared_value = parseFloat(item.declared_value || 0);

    var core_meter = parseFloat($("#core_meter").val() || 1);
    var core_min_cost_tax = parseFloat($("#core_min_cost_tax").val() || 0);
    var core_min_cost_declared_tax = parseFloat($("#core_min_cost_declared_tax").val() || 0);

    var total_metric = (length * width * height) / core_meter;
    total_metric = parseFloat(total_metric || 0);

    $("#weightVol_" + i).val(total_metric.toFixed(2));

    sumador_libras += weight;
    sumador_volumetric += total_metric;

    var calculate_weight = sumador_libras > sumador_volumetric ? sumador_libras : sumador_volumetric;

    sumador_total = calculate_weight * price_lb;
    sumador_valor_declarado += declared_value;
    max_fixed_charge += fixed_value;

    if (sumador_total > core_min_cost_tax) {
      total_impuesto = (sumador_total * tax_value) / 100;
    }

    if (sumador_valor_declarado > core_min_cost_declared_tax) {
      total_valor_declarado = (sumador_valor_declarado * declared_value_tax) / 100;
    }
  });

  total_descuento = (sumador_total * discount_value) / 100;
  total_peso = sumador_libras + sumador_volumetric;

  total_seguro = (insured_value * insurance_value) / 100;
  total_impuesto_aduanero = (total_peso * tariffs_value) / 100;

  var total_envio =
    sumador_total -
    total_descuento +
    total_seguro +
    total_impuesto +
    total_impuesto_aduanero +
    total_valor_declarado +
    max_fixed_charge +
    reexpedicion_value;

  if (total_descuento > sumador_total) {
    alert(validation_discount_1);
    $("#discount_value").val(0);
    return false;
  } else if (discount_value < 0) {
    alert(validation_discount_2);
    $("#discount_value").val(0);
    return false;
  }

  $("#subtotal").html(sumador_total.toFixed(2));
  $("#discount").html(total_descuento.toFixed(2));
  $("#impuesto").html(total_impuesto.toFixed(2));
  $("#declared_value_label").html(total_valor_declarado.toFixed(2));
  $("#fixed_value_label").html(max_fixed_charge.toFixed(2));
  $("#insurance").html(total_seguro.toFixed(2));
  $("#total_impuesto_aduanero").html(total_impuesto_aduanero.toFixed(2));
  $("#total_envio").html(total_envio.toFixed(2));
  $("#total_weight").html(sumador_libras.toFixed(2));
  $("#total_vol_weight").html(sumador_volumetric.toFixed(2));
  $("#total_fixed").html(max_fixed_charge.toFixed(2));
  $("#total_declared").html(sumador_valor_declarado.toFixed(2));
}

/* ==========================
   Submit (mirror add: include recipient fields)
   ========================== */
$("#invoice_form").on("submit", function (event) {
  event.preventDefault();

  if (cdp_validateZiseFiles() === true) {
    alert("error files");
    return false;
  }

  for (let [i] of packagesItems.entries()) {
    if ($.trim($("#description_" + i).val()).length === 0) {
      alert(validation_description);
      $("#description_" + i).focus();
      return false;
    }
    if ($.trim($("#qty_" + i).val()).length === 0) {
      alert(validation_quantity);
      $("#qty_" + i).focus();
      return false;
    }
    if ($.trim($("#weight_" + i).val()).length === 0) {
      alert(validation_weight);
      $("#weight_" + i).focus();
      return false;
    }
    if ($.trim($("#length_" + i).val()).length === 0) {
      alert(validation_length);
      $("#length_" + i).focus();
      return false;
    }
    if ($.trim($("#width_" + i).val()).length === 0) {
      alert(validation_width);
      $("#width_" + i).focus();
      return false;
    }
    if ($.trim($("#height_" + i).val()).length === 0) {
      alert(validation_height);
      $("#height_" + i).focus();
      return false;
    }
    if ($.trim($("#fixedValue_" + i).val()).length === 0) {
      alert(validation_charge);
      $("#fixedValue_" + i).focus();
      return false;
    }
    if ($.trim($("#declaredValue_" + i).val()).length === 0) {
      alert(validation_declared);
      $("#declaredValue_" + i).focus();
      return false;
    }
  }

  var notify_sms_sender = $("input:checkbox[name=notify_sms_sender]:checked").val();

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

  var provider_purchase = $("#provider_purchase").val();
  var price_purchase = $("#price_purchase").val();
  var tracking_purchase = $("#tracking_purchase").val();
  var status_courier = $("#status_courier").val();
  var order_id = $("#order_id").val();

  var driver_id = $("#driver_id").val();

  var price_lb = $("#price_lb").val();
  var insured_value = $("#insured_value").val();
  var insurance_value = $("#insurance_value").val();
  var reexpedicion_value = $("#reexpedicion_value").val();
  var discount_value = $("#discount_value").val();
  var tax_value = $("#tax_value").val();
  var declared_value_tax = $("#declared_value_tax").val();
  var tariffs_value = $("#tariffs_value").val();

  var deleted_file_ids_val = $("#deleted_file_ids").val();
  var deletedDbIds = $("#deleted_db_file_ids").val();

  var data = new FormData();
  data.append("packages", JSON.stringify(packagesItems));
  data.append("_csrf_token", $('input[name="_csrf_token"]').val());
  data.append("estimated_eta", $("#estimated_eta").val());

  if (order_id) data.append("order_id", order_id);
  if (tracking_purchase) data.append("tracking_purchase", tracking_purchase);
  if (status_courier) data.append("status_courier", status_courier);
  if (provider_purchase) data.append("provider_purchase", provider_purchase);
  if (price_purchase) data.append("price_purchase", price_purchase);

  if (agency) data.append("agency", agency);
  if (origin_off) data.append("origin_off", origin_off);

  if (sender_id) data.append("sender_id", sender_id);
  if (sender_address_id) data.append("sender_address_id", sender_address_id);

  // Recipient fields (the missing piece)
  if (recipient_id) data.append("recipient_id", recipient_id);
  if (recipient_address_id) data.append("recipient_address_id", recipient_address_id);
  data.append("recipient_type", window.recipient_type || "recipient");

  if (order_item_category) data.append("order_item_category", order_item_category);
  if (order_courier) data.append("order_courier", order_courier);
  if (order_service_options) data.append("order_service_options", order_service_options);
  if (order_package) data.append("order_package", order_package);
  if (order_date) data.append("order_date", order_date);
  if (order_deli_time) data.append("order_deli_time", order_deli_time);

  if (driver_id) data.append("driver_id", driver_id);

  if (price_lb) data.append("price_lb", price_lb);
  if (insured_value) data.append("insured_value", insured_value);
  if (reexpedicion_value) data.append("reexpedicion_value", reexpedicion_value);
  if (discount_value) data.append("discount_value", discount_value);
  if (tax_value) data.append("tax_value", tax_value);
  if (declared_value_tax) data.append("declared_value_tax", declared_value_tax);
  if (tariffs_value) data.append("tariffs_value", tariffs_value);
  if (insurance_value) data.append("insurance_value", insurance_value);

  if (notify_sms_sender) data.append("notify_sms_sender", notify_sms_sender);

  if (deleted_file_ids_val) data.append("deleted_file_ids", deleted_file_ids_val);
  if (deletedDbIds) data.append("deleted_db_file_ids", deletedDbIds);

  // New uploads
  var inputMulti = document.getElementById("filesMultiple");
  if (inputMulti && inputMulti.files) {
    for (var i = 0; i < inputMulti.files.length; i++) {
      data.append("filesMultiple[]", inputMulti.files[i]);
    }
  }

  // Camera captures
  appendAllFilesToFormData(data);

  $.ajax({
    type: "POST",
    url: "ajax/customers_packages/edit_customers_packages_ajax.php",
    data: data,
    contentType: false,
    dataType: "json",
    cache: false,
    processData: false,
    beforeSend: function () {
      $("#create_invoice").attr("disabled", true);
      Swal.fire({
        title: message_loading,
        allowOutsideClick: false,
        didOpen: function () {
          Swal.showLoading();
        },
      });
    },
    success: function (resp) {
      $("#create_invoice").attr("disabled", false);
      if (resp && resp.success) {
        cdp_showSuccess(resp.messages, resp.shipment_id);
      } else {
        cdp_showError(resp && resp.errors ? resp.errors : { error: message_error });
      }
    },
    error: function () {
      $("#create_invoice").attr("disabled", false);
      cdp_showError({ error: message_error });
    },
  });

  return false;
});

/* ==========================
   Select2 sender/recipient (mirror add patterns)
   ========================== */
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
      // Mirror add flow behavior: clear dependent selects and enable properly
      window.recipient_type = "recipient";

      var sender_id = $("#sender_id").val();

      $("#sender_address_id").prop("disabled", true).val(null).trigger("change");
      $("#add_address_sender").prop("disabled", true);

      $("#recipient_id").prop("disabled", true).val(null).trigger("change");
      $("#add_recipient").prop("disabled", true);

      $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
      $("#add_address_recipient").prop("disabled", true);

      if (sender_id != null) {
        $("#sender_address_id").prop("disabled", false);
        $("#add_address_sender").prop("disabled", false);

        $("#recipient_id").prop("disabled", false);
        $("#add_recipient").prop("disabled", false);
      }

      cdp_select2_init_sender_address();
      cdp_select2_init_recipient();
      cdp_select2_init_recipient_address();
    });
}

function cdp_select2_init_sender_address() {
  var sender_id = $("#sender_id").val();
  $("#sender_address_id").select2({
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
  });
}

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
      placeholder: typeof search_recipient !== "undefined" ? search_recipient : translate_search_recipient,
      allowClear: true,
    })
    .on("select2:select", function (e) {
      var data = e.params.data || {};
      window.recipient_type = data.type || "recipient";

      $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
      $("#add_address_recipient").prop("disabled", true);

      if ($("#recipient_id").val()) {
        $("#recipient_address_id").prop("disabled", false);
        $("#add_address_recipient").prop("disabled", false);
      }

      cdp_select2_init_recipient_address();
    })
    .on("change", function () {
      if (!$("#recipient_id").val()) {
        window.recipient_type = "recipient";
        $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
        $("#add_address_recipient").prop("disabled", true);
        cdp_select2_init_recipient_address();
      }
    });
}

function cdp_select2_init_recipient_address() {
  var recipient_id = $("#recipient_id").val();
  var recipient_type = window.recipient_type || "recipient";

  $("#recipient_address_id").select2({
    ajax: {
      url: "ajax/select2_recipient_addresses.php",
      dataType: "json",
      delay: 250,
      data: function (params) {
        return {
          id: recipient_id,
          type: recipient_type,
          q: params.term,
        };
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
    placeholder:
      typeof search_recipient_address !== "undefined"
        ? search_recipient_address
        : translate_search_recipient_address,
    allowClear: true,
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
    (item.country || "") +
    " | <b>" +
    translate_search_address_state +
    ": </b>" +
    (item.state || "") +
    " | <b>" +
    translate_search_address_city +
    ": </b>" +
    (item.city || "") +
    " | <b>" +
    translate_search_address_zip +
    ": </b>" +
    (item.zip_code || "") +
    " </div></div></div>";
  return markup;
}

function cdp_formatAdressSelection(repo) {
  return repo.text;
}

/* ==========================
   Prefill select2 from hidden inputs
   ========================== */
function prefillSenderRecipientFromHiddenInputs() {
  // sender already has an <option> in the HTML, but keep behavior consistent
  var senderId = $("#prefill_sender_id").val();
  var senderAddrId = $("#prefill_sender_address_id").val();
  var recipId = $("#prefill_recipient_id").val();
  var recipAddrId = $("#prefill_recipient_address_id").val();

  // ensure dependent buttons are enabled when ids exist
  if (senderId && parseInt(senderId, 10) > 0) {
    $("#sender_address_id").prop("disabled", false);
    $("#add_address_sender").prop("disabled", false);

    $("#recipient_id").prop("disabled", false);
    $("#add_recipient").prop("disabled", false);
  }

  if (senderAddrId && parseInt(senderAddrId, 10) > 0) {
    $("#sender_address_id").prop("disabled", false);
    $("#add_address_sender").prop("disabled", false);
  }

  if (recipId && parseInt(recipId, 10) > 0) {
    $("#recipient_id").prop("disabled", false);
    $("#add_recipient").prop("disabled", false);

    $("#recipient_address_id").prop("disabled", false);
    $("#add_address_recipient").prop("disabled", false);

    // Select2 may need option injection to keep selection stable
    if ($("#recipient_id option[value='" + recipId + "']").length === 0) {
      $("#recipient_id").append(new Option("...", recipId, true, true)).trigger("change");
    }

    // trigger init address
    cdp_select2_init_recipient_address();

    if (recipAddrId && parseInt(recipAddrId, 10) > 0) {
      if ($("#recipient_address_id option[value='" + recipAddrId + "']").length === 0) {
        $("#recipient_address_id").append(new Option("...", recipAddrId, true, true)).trigger("change");
      }
    }
  } else {
    // if no recipient, keep address disabled
    $("#recipient_address_id").prop("disabled", true);
    $("#add_address_recipient").prop("disabled", true);
  }
}

/* ==========================
   Validation helpers
   ========================== */
function cdp_validateZiseFiles() {
  var inputFile = document.getElementById("filesMultiple");
  if (!inputFile) return false;

  var file = inputFile.files;
  var size = 0;

  for (var i = 0; i < file.length; i++) {
    var filesSize = file[i].size;
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

function isNumberKey(evt, element) {
  var charCode = evt.which ? evt.which : event.keyCode;
  if (charCode > 31 && (charCode < 48 || charCode > 57) && !(charCode == 46 || charCode == 8)) return false;
  else {
    var len = $(element).val().length;
    var index = $(element).val().indexOf(".");
    if (index > 0 && charCode == 46) return false;
    if (index > 0) {
      var CharAfterdot = len + 1 - index;
      if (CharAfterdot > 4) return false;
    }
  }
  return true;
}

/* ==========================
   SweetAlert helpers
   ========================== */
function cdp_showError(errors) {
  var html_code = "";
  html_code += '<ul class="error">';

  if (typeof errors === "string") {
    html_code += '<li class="text-left"><i class="icon-double-angle-right"></i>' + errors + "</li>";
  } else {
    for (var error in errors) {
      html_code += '<li class="text-left"><i class="icon-double-angle-right"></i>' + errors[error] + "</li>";
    }
  }

  html_code += "</ul>";

  Swal.fire({
    title: message_error,
    html: html_code,
    icon: "error",
    allowOutsideClick: false,
    confirmButtonText: "Ok",
  });
}

function cdp_showSuccess(messages, shipment_id) {
  Swal.fire({
    title: messages,
    icon: "success",
    allowOutsideClick: false,
    confirmButtonText: "Ok",
  }).then((result) => {
    if (result.isConfirmed) {
      setTimeout(function () {
        window.location = "customer_packages_view.php?id=" + shipment_id;
      }, 2000);
    }
  });
}

/* ==========================
   Camera capture integration
   ========================== */
(() => {
  "use strict";
  const MAX_BYTES = 1024 * 1024; // 1MB

  const openBtn = document.getElementById("openCameraButton");
  const cameraPreview = document.getElementById("cameraPreview");
  const takeBtn = document.getElementById("takeCameraPhoto");
  const stopBtn = document.getElementById("stopCamera");
  const filesCaptureInput = document.getElementById("filesCapture");

  window.__capturedFilesFallback = window.__capturedFilesFallback || [];

  let stream = null;

  function warn() {
    try {
      console.warn.apply(console, arguments);
    } catch (e) {}
  }

  function fail(msg, e) {
    console.error(msg, e);
    alert(msg + (e && e.message ? "\n" + e.message : ""));
  }

  function canvasToBlob(canvas, mime, quality) {
    mime = mime || "image/jpeg";
    if (typeof quality === "undefined") quality = 0.92;

    return new Promise((resolve, reject) => {
      try {
        if (canvas.toBlob) {
          canvas.toBlob(
            (b) => {
              if (b) resolve(b);
              else reject(new Error("toBlob returned null"));
            },
            mime,
            quality
          );
        } else {
          const dataUrl = canvas.toDataURL(mime, quality);
          const parts = dataUrl.split(";base64,");
          const binary = atob(parts[1]);
          const len = binary.length;
          const u8 = new Uint8Array(len);
          for (let i = 0; i < len; i++) u8[i] = binary.charCodeAt(i);
          resolve(new Blob([u8], { type: mime }));
        }
      } catch (e) {
        reject(e);
      }
    });
  }

  async function compressBlobToLimit(blob, maxBytes) {
    maxBytes = maxBytes || MAX_BYTES;
    try {
      if (!blob || blob.size <= maxBytes) return blob;

      const img = await new Promise((res, rej) => {
        const url = URL.createObjectURL(blob);
        const i = new Image();
        i.onload = () => {
          URL.revokeObjectURL(url);
          res(i);
        };
        i.onerror = (e) => {
          URL.revokeObjectURL(url);
          rej(e);
        };
        i.src = url;
      });

      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");
      let w = img.width,
        h = img.height;

      canvas.width = w;
      canvas.height = h;
      ctx.drawImage(img, 0, 0, w, h);

      let quality = 0.92;
      let out = await canvasToBlob(canvas, "image/jpeg", quality);

      while (out.size > maxBytes && quality > 0.08) {
        quality = Math.max(0.08, quality - 0.07);
        out = await canvasToBlob(canvas, "image/jpeg", quality);
      }

      while (out.size > maxBytes && Math.min(w, h) > 200) {
        w = Math.floor(w * 0.92);
        h = Math.floor(h * 0.92);
        canvas.width = w;
        canvas.height = h;
        ctx.drawImage(img, 0, 0, w, h);

        quality = 0.85;
        out = await canvasToBlob(canvas, "image/jpeg", quality);
      }

      return out;
    } catch (e) {
      warn("compressBlobToLimit failed; returning original blob", e);
      return blob;
    }
  }

  function appendFileToInput(inputEl, file) {
    if (!inputEl) {
      window.__capturedFilesFallback.push(file);
      return false;
    }
    try {
      const dt = new DataTransfer();
      Array.from(inputEl.files || []).forEach((f) => dt.items.add(f));
      dt.items.add(file);
      inputEl.files = dt.files;
      return true;
    } catch (e) {
      warn("appendFileToInput failed; using fallback", e);
      window.__capturedFilesFallback.push(file);
      return false;
    }
  }

  async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Camera not supported by this browser.");
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "environment" },
        audio: false,
      });
      if (cameraPreview) {
        cameraPreview.srcObject = stream;
        cameraPreview.style.display = "block";
      }
      if (takeBtn) takeBtn.style.display = "inline-block";
      if (stopBtn) stopBtn.style.display = "inline-block";
      if (openBtn) openBtn.style.display = "none";
    } catch (e) {
      fail("Unable to open camera", e);
    }
  }

  function stopCamera() {
    try {
      if (stream) {
        stream.getTracks().forEach((t) => t.stop());
        stream = null;
      }
      if (cameraPreview) cameraPreview.style.display = "none";
      if (takeBtn) takeBtn.style.display = "none";
      if (stopBtn) stopBtn.style.display = "none";
      if (openBtn) openBtn.style.display = "inline-block";
    } catch (e) {
      warn("stopCamera error", e);
    }
  }

  async function captureOnlyAttach() {
    try {
      if (!cameraPreview || !cameraPreview.videoWidth || !cameraPreview.videoHeight) {
        throw new Error("Camera frame not ready yet.");
      }

      const canvas = document.createElement("canvas");
      canvas.width = cameraPreview.videoWidth;
      canvas.height = cameraPreview.videoHeight;
      const ctx = canvas.getContext("2d");
      ctx.drawImage(cameraPreview, 0, 0, canvas.width, canvas.height);

      let blob = await canvasToBlob(canvas, "image/jpeg", 0.92);
      blob = await compressBlobToLimit(blob, MAX_BYTES);

      const filename = "capture_" + Date.now() + ".jpg";
      let file;
      try {
        file = new File([blob], filename, { type: blob.type });
      } catch (e) {
        file = blob;
        file.name = filename;
      }

      addUnifiedThumbnail(blob, filename, file, "camera");
      appendFileToInput(filesCaptureInput, file);

      updateFileLabels();
      checkShowCleanButton();
    } catch (e) {
      fail("Capture failed", e);
    }
  }

  if (openBtn) openBtn.addEventListener("click", startCamera);
  if (stopBtn) stopBtn.addEventListener("click", stopCamera);
  if (takeBtn) takeBtn.addEventListener("click", captureOnlyAttach);

  window.addEventListener("beforeunload", function () {
    if (stream) stopCamera();
  });
})();

function appendAllFilesToFormData(fd) {
  const filesCaptureInput = document.getElementById("filesCapture");
  if (filesCaptureInput && filesCaptureInput.files && filesCaptureInput.files.length) {
    Array.from(filesCaptureInput.files).forEach(function (f) {
      fd.append("filesCapture[]", f, f.name || "capture_" + Date.now() + ".jpg");
    });
  }

  if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
    window.__capturedFilesFallback.forEach(function (f, idx) {
      const name = f.name || "capture_fallback_" + Date.now() + "_" + idx + ".jpg";
      fd.append("filesCapture[]", f, name);
    });
  }
}

/* ==========================
   Existing files preload from view payload
   ========================== */
function preloadExistingFilesFromDom() {
  if (!window.__existing_customer_package_files || !Array.isArray(window.__existing_customer_package_files)) return;

  window.__existing_customer_package_files.forEach(function (f) {
    if (!f || !f.id || !f.url) return;

    // Avoid duplication
    if (document.querySelector('.file-thumb[data-existing-id="' + f.id + '"]')) return;

    var filename = f.name || "file_" + f.id;
    var container = document.createElement("div");

    container.className = "file-thumb";
    container.dataset.type = "existing";
    container.dataset.filename = filename;
    container.setAttribute("data-existing-id", f.id);

    container.style.cssText =
      "display:inline-block;margin:6px;position:relative;width:130px;vertical-align:top;";

    var src = f.is_image ? f.url : "assets/images/no-preview.jpeg";

    container.innerHTML =
      '<div style="position:relative;border-radius:10px;overflow:hidden;border:1px solid #ddd;background:#fff;">' +
      '  <img src="' +
      src +
      '" alt="' +
      filename +
      '" style="width:130px;height:100px;object-fit:cover;display:block;">' +
      '  <button type="button" class="remove-preview-btn" ' +
      '    style="position:absolute;top:6px;right:6px;width:24px;height:24px;border:none;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;font-size:14px;line-height:24px;">×</button>' +
      "</div>" +
      '<div style="font-size:11px;margin-top:5px;text-align:center;word-break:break-word;">' +
      filename +
      "</div>";

    var previewWrap = document.getElementById("image_preview");
    if (!previewWrap) return;

    previewWrap.prepend(container);

    container.querySelector(".remove-preview-btn").addEventListener("click", function () {
      cdp_deleteImgAttached(f.id);
    });
  });

  updateFileLabels();
  checkShowCleanButton();
}