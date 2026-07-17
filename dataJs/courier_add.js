"use strict";

/* =========================================================================
   COURIER ADD — JS COMPLETO (AUTO-TARIFA EN VIVO, NUNCA OCULTA TOTALES)
   ========================================================================= */

var deleted_file_ids = [];

// Estado de paquetes en memoria
// qty defaults to 0 on purpose: the submit gate rejects qty 0/blank, forcing
// staff to set a real quantity per item instead of lumping counts into the
// description ("1 perfume, 3 yogurts").
var packagesItems = [
    { qty: 0, description: "", weight: 0, custom_price: 0, use_custom_price: false, declared_value: 0, fixed_value: 0 }
];

// Última cotización obtenida (endpoint). Si no hay, se usa el price_lb del formulario.
window.lastQuote = null;

// The per-weight rate (#price_lb) is a hidden, non-editable value. Mirror it
// into the read-only #price_lb_display box whenever it changes.
function cdpRenderPriceLbDisplay() {
  var v = $("#price_lb").val();
  if ($("#price_lb_display").length) { $("#price_lb_display").text(v === undefined || v === null ? "" : v); }
}

const AUTO_FETCH_DEBOUNCE = 400;
let autoFetchTimer = null;

(function init() {
  loadPackages();

  // Fecha
  if ($("#order_date").length && typeof $("#order_date").datepicker === "function") {
    $("#order_date").datepicker({ format: "yyyy-mm-dd", autoclose: true });
  }

  // Registro de usuario
  $("#register_customer_to_user").on("click", function () {
    $("#show_hide_user_inputs").toggleClass("d-none", !$(this).is(":checked"));
  });

  // Modo de tarifa: manual vs automática. El precio por peso es del sistema y
  // solo se muestra (input oculto #price_lb reflejado en #price_lb_display).
  $("#tariff_mode").on("change click", function () {
    const manual = $(this).is(":checked");
    scheduleRecalc();
    if (!manual) scheduleAutoFetch(true);
  });

  cdpRenderPriceLbDisplay();

  // País/Estado/Ciudad
  cdp_load_countries("_modal_user");             cdp_load_states("_modal_user");             cdp_load_cities("_modal_user");
  cdp_load_countries("_modal_recipient");        cdp_load_states("_modal_recipient");        cdp_load_cities("_modal_recipient");
  cdp_load_countries("_modal_user_address");     cdp_load_states("_modal_user_address");     cdp_load_cities("_modal_user_address");
  cdp_load_countries("_modal_recipient_address");cdp_load_states("_modal_recipient_address");cdp_load_cities("_modal_recipient_address");

  cdp_select2_init_sender();
  cdp_select2_init_sender_address();
  cdp_select2_init_recipient();
  cdp_select2_init_recipient_address();

  // Archivos adjuntos
  $("#openMultiFile").on("click", function () { $("#filesMultiple").trigger("click"); });
  $("#clean_file_button").on("click", function () {
    $("#filesMultiple").val("");
    $("#selectItem").html(typeof translate_attach_files !== "undefined" ? translate_attach_files : "Attach files");
    $("#clean_files").addClass("hide");
    $("#image_preview").html("");
    $("#total_item_files").val(0);
    deleted_file_ids = [];
    $("#deleted_file_ids").val("");
    capturedImages = [];
  });
  $("#filesMultiple").on("change", function () {
    deleted_file_ids = [];
    const files = this.files || [];
    $("#total_item_files").val(files.length);
    if (files.length > 0) $("#clean_files").removeClass("hide"); else $("#clean_files").addClass("hide");
    var countLabel = typeof translate_attached_files_count !== "undefined" ? translate_attached_files_count : "Attached Files";
    $("#selectItem").html(countLabel + " (" + files.length + ")");
    if (cdp_validateZiseFiles()) return;
    cdp_preview_images();
  });

  // Prefijo teléfono remitente
  $("#code_prefix2").hide();
  $("#prefix_check").on("change", function () {
    if ($(this).is(":checked")) {
      $("#code_prefix").hide().prop("disabled", true);
      $("#prefix_check").val(1);
      $("#code_prefix2").show().prop("disabled", false).prop("required", true);
    } else {
      $("#prefix_check").val(0);
      $("#code_prefix2").hide().prop("disabled", true).prop("required", false);
      $("#code_prefix").show().prop("disabled", false);
    }
  });

  // Solo números en order_no
  var orderNoInput = document.getElementById("order_no");
  if (orderNoInput) {
    orderNoInput.addEventListener("keypress", function (event) {
      if (event.charCode < 48 || event.charCode > 57) event.preventDefault();
    });
  }

  // Habilitar selects de direcciones
  $("#sender_id").on("change", function () {
    const ok = !!$(this).val();
    $("#sender_address_id, #add_address_sender").prop("disabled", !ok);
    scheduleAutoFetch();
  });
  $("#recipient_id").on("change", function () {
    const ok = !!$(this).val();
    $("#recipient_address_id, #add_address_recipient").prop("disabled", !ok);
    scheduleAutoFetch();
  });

  // Cambios que afectan la tarifa automática (change + select2:select por si usan Select2)
  $("#sender_address_id, #recipient_address_id, #order_service_options, #order_item_category, #rate_provider, #distance_miles")
    .on("change", scheduleAutoFetch);
  $("#sender_id, #sender_address_id, #recipient_id, #recipient_address_id, #order_service_options").on("select2:select", function () {
    if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch(true);
  });

  // Cambios en impuestos, seguro, etc.
  $("#price_lb, #insured_value, #insurance_value, #reexpedicion_value, #discount_value, #tax_value, #declared_value_tax, #tariffs_value, #core_meter, #core_min_cost_tax, #core_min_cost_declared_tax")
    .on("input change", scheduleRecalc);

  // Botones de calcular
  $(document)
    .off("click.autoCalc")
    .on("click.autoCalc", "#calculate_invoice, #calculate_list_price, #btn_calculate_price", function(e){
      e.preventDefault();
      scheduleAutoFetch(true);
    });

  // Cambios en la tabla de paquetes
  $("#packages_table").on(
    "input change",
    "input.qty, input.weight, input.custom-price-input, input[name='description']",
    function () { changePackage(this); }
  );

  // Teléfonos internacionales
  setupIntlTelInputs();

  // Totales siempre visibles
  $("#table-totals").removeClass("d-none");

  // Primer cálculo automático si no es manual
  if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch(true);
})();

/* ==========================
   Helpers de recálculo/auto-fetch
   ========================== */
function scheduleRecalc() {
  calculateFinalTotal();
}

function scheduleAutoFetch(immediate = false) {
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
  const pkgs = collectPackages();
  const sender_id = $("#sender_id").val();
  const saddr_id  = $("#sender_address_id").val();
  const recip_id  = $("#recipient_id").val();
  const raddr_id  = $("#recipient_address_id").val();
  const serviceOpt= $("#order_service_options").val() || $("#order_item_category").val() || null;
  const provider  = $("#rate_provider").val() || "internal";
  const miles     = nf($("#distance_miles").val(), 0);

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
      recipient_type: window.recipient_type || 'recipient',
      order_service_options: serviceOpt,
      order_item_category: ($("#order_item_category").val() || ""),
      rate_provider: provider,
      distance_miles: miles
    },
    success: function(res) {
      if (res && res.success) {
        window.lastQuote = res;
        const cw = nf(res.chargeable_weight, 0);
        const totalTarifa = nf(res.total_tarifa, 0);
        if (cw > 0 && totalTarifa > 0) {
          $("#price_lb").val((totalTarifa / cw).toFixed(2));
        } else {
          const unit = nf(res.data && res.data.price, 0);
          if (unit > 0) $("#price_lb").val(unit.toFixed(2));
        }
        cdpRenderPriceLbDisplay();
        if ($("#chargeable_weight").length) $("#chargeable_weight").val(cw.toFixed(2));
      } else {
        window.lastQuote = null;
        if (res && res.error) {
          Swal.fire({ text: res.error, icon: "warning", confirmButtonText: "OK" });
        }
      }
      $("#table-totals").removeClass("d-none");
      calculateFinalTotal();
    },
    error: function() {
      window.lastQuote = null;
      $("#table-totals").removeClass("d-none");
      calculateFinalTotal();
    }
  });
}

/* ==========================
   SELECT2 País/Estado/Ciudad
   ========================== */
function cdp_load_countries(modal) {
  $("#country" + modal)
    .select2({
      ajax: {
        url: "ajax/select2_countries.php",
        dataType: "json",
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return { results: data }; },
        cache: true
      },
      placeholder: typeof translate_search_country !== "undefined" ? translate_search_country : "Search country",
      allowClear: true
    })
    .on("change", function () {
      $("#state" + modal).prop("disabled", !$("#country" + modal).val()).val(null).trigger("change");
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
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return { results: data }; },
        cache: true
      },
      placeholder: typeof translate_search_state !== "undefined" ? translate_search_state : "Search state",
      allowClear: true
    })
    .on("change", function () {
      $("#city" + modal).prop("disabled", !$("#state" + modal).val()).val(null).trigger("change");
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
      data: function (params) { return { q: params.term }; },
      processResults: function (data) { return { results: data }; },
      cache: true
    },
    placeholder: typeof translate_search_city !== "undefined" ? translate_search_city : "Search city",
    allowClear: true
  });
}

/* ==========================
   SELECT2 Remitente/Destinatario
   ========================== */
function cdp_select2_init_sender() {
    $("#sender_id")
        .select2({
            ajax: {
                url: "ajax/select2_sender.php",
                dataType: "json",
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: typeof search_sender !== "undefined" ? search_sender : "Buscar remitente",
            allowClear: true
        })
        .on("change", function () {
            window.recipient_type = 'recipient';
            
            $("#sender_address_id").prop("disabled", true).val(null).trigger("change");
            $("#recipient_id").prop("disabled", true).val(null).trigger("change");
            $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
            $("#add_address_sender, #add_recipient, #add_address_recipient").prop("disabled", true);

            if ($(this).val()) {
                $("#sender_address_id").prop("disabled", false);
                $("#add_address_sender").prop("disabled", false);
                $("#recipient_id").prop("disabled", false);
                $("#add_recipient").prop("disabled", false);
            }

            cdp_select2_init_sender_address();
            cdp_select2_init_recipient();
            cdp_select2_init_recipient_address();
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
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return { results: data }; },
        cache: true
      },
      escapeMarkup: function (m) { return m; },
      templateResult: cdp_formatAdress,
      templateSelection: cdp_formatAdressSelection,
      placeholder: typeof search_sender_address !== "undefined" ? search_sender_address : "Buscar dirección remitente",
      allowClear: true
    })
    .on("change", scheduleAutoFetch);
}
function cdp_select2_init_recipient() {
    var sender_id = $("#sender_id").val();
    $("#recipient_id")
        .select2({
            ajax: {
                url: "ajax/select2_recipient.php?id=" + sender_id,
                dataType: "json",
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            placeholder: typeof search_recipient !== "undefined" ? search_recipient : "Buscar destinatario",
            allowClear: true
        })
        .on("select2:select", function (e) {
            var data = e.params.data;
            window.recipient_type = data.type || 'recipient';

            $("#recipient_address_id").val(null).trigger("change").prop("disabled", true);
            $("#add_address_recipient").prop("disabled", true);

            if ($(this).val()) {
                $("#recipient_address_id").prop("disabled", false);
                $("#add_address_recipient").prop("disabled", false);
            }
            scheduleAutoFetch();
        })
        .on("select2:unselect", function () {
            window.recipient_type = 'recipient';
            $("#recipient_address_id").val(null).trigger("change").prop("disabled", true);
            $("#add_address_recipient").prop("disabled", true);
            scheduleAutoFetch();
        })
}

function cdp_select2_init_recipient_address() {
    $("#recipient_address_id")
        .select2({
            ajax: {
                url: "ajax/select2_recipient_addresses.php",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        id: $("#recipient_id").val(),
                        type: window.recipient_type || 'recipient',
                        q: params.term
                    };
                },
                processResults: function (data) { return { results: data }; },
                cache: false
            },
            escapeMarkup: function (m) { return m; },
            templateResult: cdp_formatAdress,
            templateSelection: cdp_formatAdressSelection,
            placeholder: typeof search_recipient_address !== "undefined" ? search_recipient_address : "Buscar dirección destinatario",
            allowClear: true
        })
        .on("change", scheduleAutoFetch);
}

function cdp_formatAdress(item) {
  if (item.loading) return item.text;
  var markup = "<div class='select2-result-repository clearfix'>";
  markup += "<div class='select2-result-repository__statistics'>";
  markup += "<div class='select2-result-repository__forks'><i class='la la-code-fork mr-0'></i> <b> " +
    (typeof translate_search_address_address !== "undefined" ? translate_search_address_address : "Address") +
    ":</b> " + item.text +
    " | <b>" + (typeof translate_search_address_country !== "undefined" ? translate_search_address_country : "Country") + ":</b> " + (item.country || "") +
    " | <b>" + (typeof translate_search_address_state !== "undefined" ? translate_search_address_state : "State") + ":</b> " + (item.state || "") +
    " | <b>" + (typeof translate_search_address_city !== "undefined" ? translate_search_address_city : "City") + ":</b> " + (item.city || "") +
    " | <b>" + (typeof translate_search_address_zip !== "undefined" ? translate_search_address_zip : "Zip") + ":</b> " + (item.zip_code || "") +
    "</div></div></div>";
  return markup;
}
function cdp_formatAdressSelection(repo) {
  return repo.text;
}

/* ==========================
   Paquetes (tabla)
   ========================== */
function loadPackages() {
    var $table = $("#packages_table");
    if (!$table.length) return;

    var $tbody = $table.find("tbody");
    if (!$tbody.length) { $tbody = $("<tbody/>"); $table.append($tbody); }

    $tbody.empty();

    var priceLb = nf($("#price_lb").val(), 0);

    packagesItems.forEach(function (item, index) {
        var useCustom = !!item.use_custom_price;
        var lineTotal = computeLineTotal(item, priceLb);

        var tr = `
            <tr id="row_id_${index}">
                <td><input type="text" class="form-control form-control-sm qty"
                    name="qty" id="qty_${index}" value="${nf(item.qty,1)}"
                    onkeypress="return isNumberKey(event,this)"
                    oninput="changePackage(this)"></td>

                <td>
                    <input type="text" class="form-control form-control-sm"
                        name="description" id="description_${index}"
                        value="${item.description || ''}"
                        placeholder="${typeof translate_description!=='undefined'?translate_description:'Description'}"
                        oninput="changePackage(this)">
                    <input type="hidden" name="fixed_value"    id="fixedValue_${index}"    value="${nf(item.fixed_value,0)}">
                    <input type="hidden" name="declared_value" id="declaredValue_${index}" value="${nf(item.declared_value,0)}">
                    <input type="hidden" name="use_custom_price" id="useCustomPrice_${index}" value="${useCustom ? '1' : '0'}">
                </td>

                <td>
                    <div class="btn-group btn-group-sm pricing-mode-toggle" role="group" aria-label="Pricing mode">
                        <button type="button" id="modeWeight_${index}"
                            class="btn ${useCustom ? 'btn-outline-dark' : 'btn-dark'}"
                            onclick="setPricingMode(${index}, false)">
                            <i class="fa fa-balance-scale"></i> Weight
                        </button>
                        <button type="button" id="modeCustom_${index}"
                            class="btn ${useCustom ? 'btn-success' : 'btn-outline-success'}"
                            onclick="setPricingMode(${index}, true)">
                            <i class="fa fa-dollar-sign"></i> Custom
                        </button>
                    </div>
                </td>

                <td class="${useCustom ? 'pkg-disabled-cell' : ''}">
                    <input type="text"
                        class="form-control form-control-sm weight"
                        name="weight" id="weight_${index}"
                        value="${useCustom ? '' : nf(item.weight,0) || ''}"
                        placeholder="${useCustom ? '—' : 'Weight (lbs)'}"
                        ${useCustom ? 'disabled' : ''}
                        onkeypress="return isNumberKey(event,this)"
                        oninput="changePackage(this)">
                </td>

                <td class="${useCustom ? '' : 'pkg-disabled-cell'}">
                    <div class="input-group input-group-sm">
                        <input type="text"
                            class="form-control form-control-sm custom-price-input"
                            name="custom_price" id="customPrice_${index}" data-cur="usd"
                            value="${useCustom ? nf(item.custom_price,0) || '' : ''}"
                            placeholder="${useCustom ? 'USD' : '—'}"
                            ${!useCustom ? 'disabled' : ''}
                            onkeypress="return isNumberKey(event,this)"
                            oninput="changePackage(this)">
                        <div class="input-group-append" ${!useCustom ? 'style="display:none;"' : ''}>
                            <button type="button" class="btn btn-dark py-0 px-2" id="curUsd_${index}"
                                onclick="setEntryCurrency(${index}, 'usd')">$</button>
                            <button type="button" class="btn btn-outline-dark py-0 px-2" id="curGhs_${index}"
                                onclick="setEntryCurrency(${index}, 'ghs')">&#8373;</button>
                        </div>
                    </div>
                </td>

                <td class="text-center">
                    <span id="lineTotal_${index}" class="font-weight-bold text-success"
                        style="font-size:0.95rem; white-space:nowrap;">
                        ${r2(lineTotal)}
                    </span>
                </td>

                <td class="text-center">
                    ${index > 0
                        ? `<button type="button" class="btn btn-outline-danger btn-sm"
                               onclick="deletePackage(${index})"><i class="fa fa-trash"></i></button>`
                        : ''}
                </td>
            </tr>`;

        $tbody.append(tr);
    });

    $("#add_rows, #add_row").off("click.addPkg").on("click.addPkg", function (e) {
        e.preventDefault();
        addPackage();
    });

    calculateFinalTotal();
    if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch();
}

function addPackage() {
    packagesItems.push({
        qty: 0, description: "", weight: 0,
        custom_price: 0, use_custom_price: false,
        declared_value: 0, fixed_value: 0
    });
    var index = packagesItems.length - 1;
    loadPackages();
    // $("#row_id_" + index).css({ backgroundColor: "#81aaa2" });
    setTimeout(function () { $("#row_id_" + index).css({ backgroundColor: "" }); }, 900);
    $("#qty_" + index).trigger("focus"); // ready to accept the quantity immediately
    $("#create_invoice").prop("disabled", false);
}

function deletePackage(index) {
    packagesItems = packagesItems.filter(function(_, i){ return i !== index; });
    $("#row_id_" + index).fadeOut(300, function () {
        $(this).remove();
        loadPackages();
        $("#create_invoice").prop("disabled", false);
    });
}

function changePackage(el) {
    var parts = el.id.split("_");
    var idx   = parseInt(parts[parts.length - 1], 10);
    var field = el.name;

    var val = el.value;

    packagesItems = packagesItems.map(function (item, i) {
        if (i === idx) {
            if (field === "qty")          item.qty          = nf(val, 1);
            if (field === "description")  item.description  = val;
            if (field === "weight")       item.weight       = nf(val, 0);
            if (field === "custom_price") item.custom_price = cdpEntryToUsd(idx, nf(val, 0));
        }
        return item;
    });

    // Update just this row's line total live without re-rendering the whole table
    var priceLb   = nf($("#price_lb").val(), 0);
    var lineTotal = computeLineTotal(packagesItems[idx], priceLb);
    $("#lineTotal_" + idx).text(r2(lineTotal));

    calculateFinalTotal();
    if (!$("#tariff_mode").is(":checked") && (field === "weight" || field === "qty")) {
        scheduleAutoFetch();
    }
}

/* ==========================
   Adjuntos
   ========================== */
function cdp_preview_images() {
  var input = document.getElementById("filesMultiple");
  if (!input) return;

  var files = Array.from(input.files || []);
  var previewWrap = document.getElementById("image_preview");
  if (!previewWrap) return;

  // Remove only previously uploaded thumbnails, not captured ones
  var existingUploads = previewWrap.querySelectorAll('.file-thumb[data-type="upload"]');
  existingUploads.forEach(function(el) { el.remove(); });

  files.forEach(function(file) {
    var mimeRoot = (file.type || "").split("/")[0];
    var previewBlob;

    if (mimeRoot === "image") {
      previewBlob = file;
    } else {
      previewBlob = new Blob([], { type: "image/jpeg" });
      previewBlob.previewFallback = "assets/images/no-preview.jpeg";
    }

    addUnifiedThumbnail(previewBlob, file.name, file, 'upload');
  });

  updateFileLabels();
  checkShowCleanButton();
}


function addUnifiedThumbnail(blob, filename, originalFile = null, fileType = 'upload') {
  var previewWrap = document.getElementById("image_preview");
  if (!previewWrap) return;

  var isRealImage = !blob.previewFallback;
  var url = isRealImage
    ? URL.createObjectURL(blob)
    : blob.previewFallback;

  var container = document.createElement("div");
  container.className = "file-thumb";
  container.dataset.filename = filename;
  container.dataset.type = fileType;

  container.style.cssText = "display:inline-block;margin:6px;position:relative;width:130px;vertical-align:top;";

  var sizeKB = Math.round((originalFile?.size || blob.size || 0) / 1024);

  container.innerHTML = `
    <div style="position:relative;border-radius:10px;overflow:hidden;border:1px solid #ddd;background:#fff;">
      <img
        src="${url}"
        alt="${filename}"
        style="width:130px;height:100px;object-fit:cover;display:block;"
      >
      <button
        type="button"
        class="remove-preview-btn"
        style="position:absolute;top:6px;right:6px;width:24px;height:24px;border:none;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;font-size:14px;line-height:24px;"
      >
        ×
      </button>
    </div>
    <div style="font-size:11px;margin-top:5px;text-align:center;word-break:break-word;">${filename}</div>
    <div style="font-size:10px;color:#666;text-align:center;">${sizeKB} KB</div>
  `;

  previewWrap.prepend(container);

  var removeBtn = container.querySelector(".remove-preview-btn");
  removeBtn.addEventListener("click", function() {
    container.remove();
    removeFileFromInputByName(document.getElementById("filesMultiple"), filename);
    removeFileFromInputByName(document.getElementById("filesCapture"), filename);

    if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
      window.__capturedFilesFallback = window.__capturedFilesFallback.filter(function(f) {
        return f.name !== filename;
      });
    }

    updateFileLabels();
    checkShowCleanButton();
  });

  if (isRealImage) {
    setTimeout(function() { URL.revokeObjectURL(url); }, 60000);
  }
}

/* ==========================
   Utilidades/Totales
   ========================== */
function cdp_validateZiseFiles() {
  const input = document.getElementById("filesMultiple");
  if (!input) return false;
  const files = input.files || [];
  let totalSize = 0;
  for (let i = 0; i < files.length; i++) totalSize += files[i].size;
  if (totalSize > 5242880) {
    $(".resultados_file").html("<div class='alert alert-danger'><button type='button' class='close' data-dismiss='alert'>&times;</button><strong>" + (typeof validation_files_size !== "undefined" ? validation_files_size : "El tamaño total de los archivos excede el límite (5 MB).") + "</strong></div>");
    $("#filesMultiple").val("");
    $("#clean_files").addClass("hide");
    $("#image_preview").html("");
    $("#total_item_files").val(0);
    return true;
  } else {
    $(".resultados_file").html("");
    return false;
  }
}
function isNumberKey(evt, element) {
  var charCode = evt.which ? evt.which : evt.keyCode;
  if (charCode > 31 && (charCode < 48 || charCode > 57) && !(charCode === 46 || charCode === 8)) return false;
  var val = $(element).val();
  var idx = val.indexOf(".");
  if (idx > -1 && charCode === 46) return false;
  if (idx > -1) {
    var after = val.length + 1 - idx;
    if (after > 4) return false;
  }
  return true;
}
function nf(v, def) {
  if (typeof def === "undefined") def = 0;
  var n = parseFloat(v);
  return (isNaN(n) || !isFinite(n)) ? def : n;
}
function r2(v) {
  var n = parseFloat(v);
  return (isNaN(n) || !isFinite(n)) ? "0.00" : n.toFixed(2);
}

function collectPackages() {
    return (packagesItems || []).map(function (p) {
        return {
            qty:             Math.max(1, nf(p.qty, 1)),
            description:     p.description || "",
            weight:          nf(p.weight, 0),
            custom_price:    nf(p.custom_price, 0),
            use_custom_price: p.use_custom_price ? 1 : 0,
            declared_value:  nf(p.declared_value, 0),
            fixed_value:     nf(p.fixed_value, 0)
        };
    });
}

function calculateFinalTotal(element) {
    if (element && !element.value) { $(element).val(0); }

    var tariffs_value              = nf($("#tariffs_value").val());
    var declared_value_tax         = nf($("#declared_value_tax").val());
    var insurance_value            = nf($("#insurance_value").val());
    var tax_value                  = nf($("#tax_value").val());
    var discount_value             = nf($("#discount_value").val());
    var reexpedicion_value         = nf($("#reexpedicion_value").val());
    var price_lb                   = nf($("#price_lb").val());
    var insured_value              = nf($("#insured_value").val());
    var core_min_cost_tax          = nf($("#core_min_cost_tax").val());
    var core_min_cost_declared_tax = nf($("#core_min_cost_declared_tax").val());

    var isManual = $("#tariff_mode").is(":checked");

    var sum_weight_real  = 0;
    var sum_declared     = 0;
    var sum_fixed        = 0;
    var sum_lines_usd    = 0; // sum of all per-row line totals (USD)
    var sum_custom_usd   = 0; // sum of custom-priced lines (USD)

    (packagesItems || []).forEach(function (item, i) {
        var qty    = Math.max(1, nf(item.qty, 1));
        var weight = nf(item.weight, 0);

        sum_weight_real += weight * qty;
        sum_declared    += nf(item.declared_value, 0) * qty;
        sum_fixed       += nf(item.fixed_value, 0) * qty;

        if (item.use_custom_price) {
            sum_custom_usd += nf(item.custom_price, 0) * qty;
        }

        var lineTotal = computeLineTotal(item, price_lb);
        sum_lines_usd += lineTotal;

        // Update the row's live display
        $("#lineTotal_" + i).text(r2(lineTotal));
    });

    // chargeable weight for auto-tariff display (volumetric gone)
    var chargeable = sum_weight_real;
    if ($("#chargeable_weight").length) {
        $("#chargeable_weight").val(r2(chargeable));
    }

    // BASE FLETE (USD — courier_add never converts to GHS; that happens later
    // at the customer/payment/messaging stage).
    // Manual mode: use the per-row line totals sum (weight*rate + custom_price).
    // Auto mode: use tariff engine result (price_lb already set by fetchTariff).
    var base_flete = 0;
    if (isManual) {
        base_flete = sum_lines_usd;
    } else {
        if (window.lastQuote && window.lastQuote.success) {
            if (typeof window.lastQuote.total_tarifa !== "undefined") {
                base_flete = parseFloat(window.lastQuote.total_tarifa);
            } else {
                base_flete = chargeable * price_lb;
            }
        } else {
            base_flete = sum_lines_usd;
        }
    }

    // TAX
    var total_impuesto = 0;
    if (base_flete > core_min_cost_tax) {
        total_impuesto = (base_flete * tax_value) / 100;
    }

    // DECLARED VALUE
    var total_declared = 0;
    if (sum_declared > core_min_cost_declared_tax) {
        total_declared = (sum_declared * declared_value_tax) / 100;
    }

    // DISCOUNT — percentage of the base, or a flat amount (USD), per the toggle.
    var discount_type = $("#discount_type").val() || "percent";
    var total_desc = (discount_type === "amount") ? discount_value : (base_flete * discount_value) / 100;
    if (total_desc > base_flete || discount_value < 0) {
        $("#discount_value").val(0);
        total_desc = 0;
    }

    var total_seguro = (insured_value * insurance_value) / 100;
    var total_aduana = (sum_weight_real * tariffs_value) / 100;

    var total = base_flete - total_desc + total_seguro + total_impuesto + total_aduana + total_declared + sum_fixed + reexpedicion_value;
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
    if ($("#total_custom_price").length) $("#total_custom_price").html(r2(sum_custom_usd));
    $("#total_vol_weight").html("—"); // volumetric removed
    if ($("#total_fixed").length)    $("#total_fixed").html(r2(sum_fixed));
    if ($("#total_declared").length) $("#total_declared").html(r2(sum_declared));
}


/* ==========================
   Submit — Crear envío
   ========================== */
$("#invoice_form").on("submit", function (event) {
    event.preventDefault();

    if (cdp_validateZiseFiles() === true) {
        alert("error files");
        return false;
    }

    // Validación de filas de paquetes
    for (var i = 0; i < packagesItems.length; i++) {
        if ($.trim($("#description_" + i).val()).length === 0) {
            Swal.fire({ text: validation_description, icon: "error", confirmButtonText: "OK" });
            $("#description_" + i).focus();
            return false;
        }
        var qty_val = $.trim($("#qty_" + i).val());
        if (!qty_val || qty_val === "0") {
            Swal.fire({ text: "Enter quantity for row " + (i+1), icon: "error", confirmButtonText: "OK" });
            $("#qty_" + i).focus();
            return false;
        }
        // Pricing (weight OR custom) is set LATER by the Ghana-side updaters — the
        // adder only enters qty + description. So we don't require it here; we only
        // guard against an item carrying BOTH at once.
        var wt = nf($("#weight_" + i).val(), 0);
        var cp = nf($("#customPrice_" + i).val(), 0);
        if (wt > 0 && cp > 0) {
            Swal.fire({ text: "Row " + (i+1) + ": use either weight OR custom price, not both.", icon: "error", confirmButtonText: "OK" });
            return false;
        }
    }

    var tracking_number = $("#tracking_number").val();
    var estimated_eta = $("#estimated_eta").val();

  // ====== Campos de cabecera ======
  var prefix_check = $("#prefix_check").val();
  var code_prefix = $("#code_prefix").val();
  var code_prefix2 = $("#code_prefix2").val();

  var notify_whatsapp_sender   = $("input:checkbox[name=notify_whatsapp_sender]:checked").val();
  var notify_whatsapp_receiver = $("input:checkbox[name=notify_whatsapp_receiver]:checked").val();
  var notify_sms_sender        = $("input:checkbox[name=notify_sms_sender]:checked").val();
  var notify_sms_receiver      = $("input:checkbox[name=notify_sms_receiver]:checked").val();
  var tariff_mode              = $("input:checkbox[name=tariff_mode]:checked").val();

  var order_no            = $("#order_no").val();
  var agency              = $("#agency").val();
  var origin_off          = $("#origin_off").val();
  var sender_id           = $("#sender_id").val();
  var sender_address_id   = $("#sender_address_id").val();
  var recipient_id        = $("#recipient_id").val();
  var recipient_address_id= $("#recipient_address_id").val();
  var order_item_category = $("#order_item_category").val();
  var order_courier       = $("#order_courier").val();
  var order_service_options= $("#order_service_options").val();
  var order_package       = $("#order_package").val();
  var order_date          = $("#order_date").val();
  var order_deli_time     = $("#order_deli_time").val();
  var order_pay_mode      = $("#order_pay_mode").val();
  var order_payment_method= $("#order_payment_method").val();
  var status_courier      = $("#status_courier").val();
  var driver_id           = $("#driver_id").val();

  var price_lb            = $("#price_lb").val();
  var insured_value       = $("#insured_value").val();
  var insurance_value     = $("#insurance_value").val();
  var reexpedicion_value  = $("#reexpedicion_value").val();
  var discount_value      = $("#discount_value").val();
  var tax_value           = $("#tax_value").val();
  var declared_value_tax  = $("#declared_value_tax").val();
  var tariffs_value       = $("#tariffs_value").val();

  var deleted_file_ids_val = $("#deleted_file_ids").val();

  // motor de tarifas
  var rate_provider  = $("#rate_provider").val() || "internal";
  var distance_miles = $("#distance_miles").val() || 0;

  var courier_notes = $("#courier_notes").val() || "";

  var data = new FormData();

  // paquetes en JSON
  data.append("packages", JSON.stringify(packagesItems));

  // Datos de cabecera
  if (prefix_check)         data.append("prefix_check", prefix_check);
  if (code_prefix)          data.append("code_prefix", code_prefix);
  if (code_prefix2)         data.append("code_prefix2", code_prefix2);
  if (order_no)             data.append("order_no", order_no);
  if (agency)               data.append("agency", agency);
  if (origin_off)           data.append("origin_off", origin_off);
  if (sender_id)            data.append("sender_id", sender_id);
  if (sender_address_id)    data.append("sender_address_id", sender_address_id);
  if (recipient_id)         data.append("recipient_id", recipient_id);
  if (recipient_address_id) data.append("recipient_address_id", recipient_address_id);
  if (order_item_category)  data.append("order_item_category", order_item_category);
  if (order_courier)        data.append("order_courier", order_courier);
  if (order_service_options)data.append("order_service_options", order_service_options);
  if (order_package)        data.append("order_package", order_package);
  if (order_date)           data.append("order_date", order_date);
  if (order_deli_time)      data.append("order_deli_time", order_deli_time);
  if (order_pay_mode)       data.append("order_pay_mode", order_pay_mode);
  if (order_payment_method) data.append("order_payment_method", order_payment_method);
  if (status_courier)       data.append("status_courier", status_courier);
  if (driver_id)            data.append("driver_id", driver_id);
  if (price_lb)             data.append("price_lb", price_lb);
  if (insured_value)        data.append("insured_value", insured_value);
  if (reexpedicion_value)   data.append("reexpedicion_value", reexpedicion_value);
  if (discount_value)       data.append("discount_value", discount_value);
  data.append("discount_type", $("#discount_type").val() || "percent");
  if (tax_value)            data.append("tax_value", tax_value);
  if (declared_value_tax)   data.append("declared_value_tax", declared_value_tax);
  if (tariffs_value)        data.append("tariffs_value", tariffs_value);
  if (insurance_value)      data.append("insurance_value", insurance_value);
    if (tracking_number)    data.append("tracking_number", tracking_number);
    if (estimated_eta)      data.append("estimated_eta", estimated_eta);
    if (courier_notes)      data.append("courier_notes", courier_notes);
    data.append("package_total_weight", $("#package_total_weight").val() || "");
  

  // motor tarifas (siempre enviar para que el backend calcule cuando manual_tariff=0)
  data.append("rate_provider", rate_provider || "internal");
  data.append("distance_miles", distance_miles);

  // notificaciones
  if (notify_whatsapp_sender)   data.append("notify_whatsapp_sender", notify_whatsapp_sender);
  if (notify_whatsapp_receiver) data.append("notify_whatsapp_receiver", notify_whatsapp_receiver);
  if (notify_sms_sender)        data.append("notify_sms_sender", notify_sms_sender);
  if (notify_sms_receiver)      data.append("notify_sms_receiver", notify_sms_receiver);
  if (tariff_mode)              data.append("tariff_mode", tariff_mode);

  // Dangerous-goods (hazmat) flag. Sent unconditionally (0 or 1) so the saved
  // value always reflects the toggle — this FormData is hand-built, so an
  // unchecked box would otherwise never reach the server.
  data.append("is_dangerous_good", $("#is_dangerous_good").is(":checked") ? 1 : 0);

  if (deleted_file_ids_val) data.append("deleted_file_ids", deleted_file_ids_val);

  // archivos
  var fileInput = document.getElementById("filesMultiple");
  if (fileInput && fileInput.files) {
    for (var j = 0; j < fileInput.files.length; j++) {
      data.append("filesMultiple[]", fileInput.files[j]);
    }
  }

    // Camera Captures
    var captureInput = document.getElementById("filesCapture");
    if (captureInput && captureInput.files) {
        for (var k = 0; k < captureInput.files.length; k++) {
            data.append("filesMultiple[]", captureInput.files[k]);
        }
    }

    // fallback support
    if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
        window.__capturedFilesFallback.forEach(function(file){
            data.append("filesMultiple[]", file);
        });
    }

    // Recorded videos (kept ~2–5 MB by the shared capture module → filesVideo[])
    if (typeof window.cdpAppendVideosToFormData === "function") {
        window.cdpAppendVideosToFormData(data);
    }

  data.append('_csrf_token', $('input[name="_csrf_token"]').val());
  data.append('recipient_type', window.recipient_type || 'recipient');
  data.append("exchange_rate", $("#core_exchange_rate").val() || "1");

  $.ajax({
    type: "POST",
    url: "ajax/courier/add_courier_ajax.php",
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
        didOpen: function () { Swal.showLoading(); },
      });
    },

    success: function (resp) {
      // cerrar loader
      try { Swal.close(); } catch (e) {}

      $("#create_invoice").attr("disabled", false);

      var ok = false;
      var shipment_id = null;
      var msg = "";

      // detectar éxito con varias formas típicas
      if (resp) {
        if (resp.success === true || resp.success === "true") {
          ok = true;
        } else if (resp.status === "success" || resp.status === 200 || resp.ok === true) {
          ok = true;
        }
      }

      if (ok) {
        msg = resp.messages || resp.message || "Shipment created successfully";
        shipment_id = resp.shipment_id || resp.id || resp.order_id;

        cdp_showSuccess(msg, shipment_id);
      } else {
        var errs = resp && (resp.errors || resp.error || resp.message) ? (resp.errors || resp.error || resp.message) : "No se pudo crear el envío.";
        cdp_showError(errs);
      }
    },

    error: function (xhr, textStatus) {
      // cerrar loader
      try { Swal.close(); } catch (e) {}

      $("#create_invoice").attr("disabled", false);

      var errs = [];

      if (textStatus === "timeout") {
        errs.push("Tiempo de espera agotado al crear el envío.");
      }
      if (xhr && xhr.responseText) {
        errs.push(xhr.responseText);
        console.error(xhr.responseText);
      }
      if (!errs.length) {
        errs.push("No se pudo completar la operación.");
      }

      cdp_showError(errs);
    }

    // OJO: SIN 'complete' que cierre el SweetAlert final
  });

  return false;
});


/* ==========================
   SweetAlert helpers
   ========================== */
function cdp_showError(errors) {
  var list = [];

  if (Array.isArray(errors)) {
    list = errors;
  } else if (typeof errors === "string") {
    list = [errors];
  } else if (errors && typeof errors === "object") {
    for (var k in errors) {
      if (Object.prototype.hasOwnProperty.call(errors, k)) {
        list.push(errors[k]);
      }
    }
  }

  if (!list.length) {
    list = ["Ha ocurrido un error al procesar la petición."];
  }

  var html = "<ul class='error'>";
  for (var i = 0; i < list.length; i++) {
    html += '<li class="text-left"><i class="icon-double-angle-right"></i> ' + list[i] + "</li>";
  }
  html += "</ul>";

  Swal.fire({
    title: typeof message_error !== "undefined" ? message_error : "Error",
    html: html,
    icon: "error",
    allowOutsideClick: false,
    confirmButtonText: "OK"
  });
}

function cdp_showSuccess(message, shipment_id) {
    Swal.fire({
        title: message || "OK",
        icon: "success",
        allowOutsideClick: false,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: "Print Label",
        denyButtonText: "Edit Shipment",
        cancelButtonText: "Add a New Shipment"
    }).then(function (result) {
        if (result.isConfirmed) {
            window.open("print_label_ship.php?id=" + shipment_id, "_blank");
        } else if (result.isDenied) {
            window.open("courier_edit.php?id=" + shipment_id, "_blank");
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            window.location.reload();
        }
    });
    // // 1. Save flag and message to storage
    // localStorage.setItem('cdp_alert_pending', 'true');
    // localStorage.setItem('cdp_alert_message', message || "OK");
    
    // // Optional: If you need shipment_id later on the reloaded page, uncomment below
    // // localStorage.setItem('cdp_alert_shipment_id', shipment_id || "");

    // // 2. Perform reload
    // window.location.reload();
}

// Add this globally so it executes every time the page loads up fresh
$(document).ready(function() {
    if (localStorage.getItem('cdp_alert_pending') === 'true') {
        var savedMessage = localStorage.getItem('cdp_alert_message');

        Swal.fire({
            position: "top-end",
            icon: "success",
            title: savedMessage,
            showConfirmButton: false,
            timer: 250
        });

        // Clean up keys immediately so it doesn't loop on future manual refreshes
        localStorage.removeItem('cdp_alert_pending');
        localStorage.removeItem('cdp_alert_message');
        // localStorage.removeItem('cdp_alert_shipment_id'); // Uncomment if used above
    }
});


/* ==========================
   intlTelInput
   ========================== */
var errorMsgSender     = document.querySelector("#error-msg-sender");
var validMsgSender     = document.querySelector("#valid-msg-sender");
var errorMsgRecipient  = document.querySelector("#error-msg-recipient");
var validMsgRecipient  = document.querySelector("#valid-msg-recipient");
var errorMap = ["Invalid number", "Invalid country code", "Mobile number too short", "Mobile number too long", "Invalid mobile number"];
var input_sender    = document.querySelector("#phone_custom");
var input_recipient = document.querySelector("#phone_custom_recipient");
var iti_sender, iti_recipient;

function setupIntlTelInputs() {
  if (input_sender) {
    iti_sender = window.intlTelInput(input_sender, {
      geoIpLookup: function (cb) {
        $.get("http://ipinfo.io", function () {}, "jsonp").always(function (resp) {
          cb((resp && resp.country) ? resp.country : "");
        });
      },
      initialCountry: "auto",
      nationalMode: true,
      separateDialCode: true,
      utilsScript: "assets/template/assets/libs/intlTelInput/utils.js"
    });
    input_sender.addEventListener("blur", function () {
      resetPhones();
      if (input_sender.value.trim()) {
        if (iti_sender.isValidNumber()) {
          $("#phone").val(iti_sender.getNumber());
          validMsgSender.classList.remove("hide");
        } else {
          input_sender.classList.add("error");
          var errorCode = iti_sender.getValidationError();
          errorMsgSender.innerHTML = errorMap[errorCode] || "Invalid phone";
          errorMsgSender.classList.remove("hide");
        }
      }
    });
    input_sender.addEventListener("change", resetPhones);
    input_sender.addEventListener("keyup", resetPhones);
  }
  if (input_recipient) {
    iti_recipient = window.intlTelInput(input_recipient, {
      geoIpLookup: function (cb) {
        $.get("http://ipinfo.io", function () {}, "jsonp").always(function (resp) {
          cb((resp && resp.country) ? resp.country : "");
        });
      },
      initialCountry: "auto",
      nationalMode: true,
      separateDialCode: true,
      utilsScript: "assets/template/assets/libs/intlTelInput/utils.js"
    });
    input_recipient.addEventListener("blur", function () {
      resetPhones();
      if (input_recipient.value.trim()) {
        if (iti_recipient.isValidNumber()) {
          $("#phone_recipient").val(iti_recipient.getNumber());
          validMsgRecipient.classList.remove("hide");
        } else {
          input_recipient.classList.add("error");
          var errorCode = iti_recipient.getValidationError();
          errorMsgRecipient.innerHTML = errorMap[errorCode] || "Invalid phone";
          errorMsgRecipient.classList.remove("hide");
        }
      }
    });
    input_recipient.addEventListener("change", resetPhones);
    input_recipient.addEventListener("keyup", resetPhones);
  }
}
function resetPhones() {
  if (input_sender) input_sender.classList.remove("error");
  if (input_recipient) input_recipient.classList.remove("error");
  if (errorMsgSender) {
    errorMsgSender.innerHTML = "";
    errorMsgSender.classList.add("hide");
  }
  if (validMsgSender) validMsgSender.classList.add("hide");
  if (errorMsgRecipient) {
    errorMsgRecipient.innerHTML = "";
    errorMsgRecipient.classList.add("hide");
  }
  if (validMsgRecipient) validMsgRecipient.classList.add("hide");
}

(() => {
  'use strict';
  const MAX_BYTES = 1024 * 1024; // 1MB

  const openBtn = document.getElementById('openCameraButton');
  const cameraPreview = document.getElementById('cameraPreview');
  const takeBtn = document.getElementById('takeCameraPhoto');
  const stopBtn = document.getElementById('stopCamera');
  const filesCaptureInput = document.getElementById('filesCapture');
  const previewWrap = document.getElementById('image_preview');

  window.__capturedFilesFallback = window.__capturedFilesFallback || [];

  let stream = null;

  function log(...args) { console.log('[capture]', ...args); }
  function warn(...args) { console.warn('[capture]', ...args); }
  function fail(msg, e) { console.error('[capture]', msg, e); alert(msg + (e && e.message ? '\n' + e.message : '')); }

  // Canvas to blob conversion with fallback
  function canvasToBlob(canvas, mime = 'image/jpeg', quality = 0.92) {
    return new Promise((resolve, reject) => {
      try {
        if (canvas.toBlob) {
          canvas.toBlob(b => {
            if (b) resolve(b);
            else {
              try {
                const dataUrl = canvas.toDataURL(mime, quality);
                const parts = dataUrl.split(';base64,');
                const binary = atob(parts[1]);
                const len = binary.length;
                const u8 = new Uint8Array(len);
                for (let i = 0; i < len; i++) u8[i] = binary.charCodeAt(i);
                resolve(new Blob([u8], { type: mime }));
              } catch (e2) { reject(e2); }
            }
          }, mime, quality);
        } else {
          const dataUrl = canvas.toDataURL(mime, quality);
          const parts = dataUrl.split(';base64,');
          const binary = atob(parts[1]);
          const len = binary.length;
          const u8 = new Uint8Array(len);
          for (let i = 0; i < len; i++) u8[i] = binary.charCodeAt(i);
          resolve(new Blob([u8], { type: mime }));
        }
      } catch (e) { reject(e); }
    });
  }

  // Iterative compression
  async function compressBlobToLimit(blob, maxBytes = MAX_BYTES) {
    try {
      if (!blob) throw new Error('No blob provided');
      if (blob.size <= maxBytes) return blob;

      const img = await new Promise((res, rej) => {
        const url = URL.createObjectURL(blob);
        const i = new Image();
        i.onload = () => { URL.revokeObjectURL(url); res(i); };
        i.onerror = e => { URL.revokeObjectURL(url); rej(e); };
        i.src = url;
      });

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      let w = img.width, h = img.height;
      canvas.width = w;
      canvas.height = h;
      ctx.drawImage(img, 0, 0, w, h);

      let quality = 0.92;
      let out = await canvasToBlob(canvas, 'image/jpeg', quality);

      while (out.size > maxBytes && quality > 0.08) {
        quality = Math.max(0.08, quality - 0.07);
        out = await canvasToBlob(canvas, 'image/jpeg', quality);
      }

      while (out.size > maxBytes && Math.min(w, h) > 200) {
        w = Math.floor(w * 0.92);
        h = Math.floor(h * 0.92);
        canvas.width = w;
        canvas.height = h;
        ctx.drawImage(img, 0, 0, w, h);
        quality = 0.85;
        out = await canvasToBlob(canvas, 'image/jpeg', quality);
        while (out.size > maxBytes && quality > 0.08) {
          quality = Math.max(0.08, quality - 0.07);
          out = await canvasToBlob(canvas, 'image/jpeg', quality);
        }
      }

      return out;
    } catch (e) {
      warn('compressBlobToLimit failed — returning original blob', e);
      return blob;
    }
  }

  // Add thumbnail with remove button
  function addThumbnail(blob, filename) {
    if (!previewWrap) return;
    const url = URL.createObjectURL(blob);

    var container = document.createElement('div');
    container.className = 'file-thumb';
    container.dataset.filename = filename;
    container.dataset.type = 'camera';

    container.style.cssText = "display:inline-block;margin:6px;position:relative;width:130px;vertical-align:top;";

    var sizeKB = Math.round((blob.size || 0) / 1024);

    container.innerHTML = `
      <div style="position:relative;border-radius:10px;overflow:hidden;border:1px solid #ddd;background:#fff;">
        <img
          src="${url}"
          alt="${filename}"
          style="width:130px;height:100px;object-fit:cover;display:block;"
        >
        <button
          type="button"
          class="remove-preview-btn"
          style="position:absolute;top:6px;right:6px;width:24px;height:24px;border:none;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;font-size:14px;line-height:24px;"
        >
          ×
        </button>
      </div>
      <div style="font-size:11px;margin-top:5px;text-align:center;word-break:break-word;">${filename}</div>
      <div style="font-size:10px;color:#666;text-align:center;">${sizeKB} KB</div>
    `;

    previewWrap.prepend(container);

    const removeBtn = container.querySelector('.remove-preview-btn');
    removeBtn.addEventListener('click', () => {
      container.remove();
      removeFileFromInputByName(filesCaptureInput, filename);

      updateFileLabels();
      checkShowCleanButton();
    });

    setTimeout(() => URL.revokeObjectURL(url), 60 * 1000);
  }

  // Append file to input using DataTransfer
  function appendFileToInput(inputEl, file) {
    if (!inputEl) {
      window.__capturedFilesFallback.push(file);
      log('No input element; saved in fallback array');
      return false;
    }
    try {
      const dt = new DataTransfer();
      Array.from(inputEl.files || []).forEach(f => dt.items.add(f));
      dt.items.add(file);
      inputEl.files = dt.files;
      log('Appended file to input.files', file.name);
      return true;
    } catch (e) {
      warn('Could not append to input.files; storing in fallback', e);
      window.__capturedFilesFallback.push(file);
      return false;
    }
  }

  // Wait for video to be ready
  function waitForVideoReady(videoEl, timeout = 3000) {
    return new Promise((resolve, reject) => {
      if (videoEl.videoWidth && videoEl.videoHeight) return resolve();
      let elapsed = 0;
      const iv = 100;
      const t = setInterval(() => {
        elapsed += iv;
        if (videoEl.videoWidth && videoEl.videoHeight) {
          clearInterval(t);
          resolve();
        }
        else if (elapsed >= timeout) {
          clearInterval(t);
          reject(new Error('Video not ready (timed out)'));
        }
      }, iv);
    });
  }

  // Start camera
  async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert('Camera not supported by this browser.');
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
        audio: false
      });
      cameraPreview.srcObject = stream;
      cameraPreview.style.display = 'block';
      if (takeBtn) takeBtn.style.display = 'inline-block';
      if (stopBtn) stopBtn.style.display = 'inline-block';
      if (openBtn) openBtn.style.display = 'none';
    } catch (e) {
      fail('Unable to open camera', e);
    }
  }

  // Stop camera
  function stopCamera() {
    try {
      if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
      }
      cameraPreview.style.display = 'none';
      if (takeBtn) takeBtn.style.display = 'none';
      if (stopBtn) stopBtn.style.display = 'none';
      if (openBtn) openBtn.style.display = 'inline-block';
    } catch (e) { warn('stopCamera error', e); }
  }

  // Capture and attach
  async function captureOnlyAttach() {
    try {
      if (!cameraPreview) throw new Error('cameraPreview element missing');
      await waitForVideoReady(cameraPreview).catch(e => warn('video readiness wait failed (continuing):', e));

      if (!cameraPreview.videoWidth || !cameraPreview.videoHeight) {
        throw new Error('Camera frame not available yet - try waiting a second after opening camera.');
      }

      const canvas = document.createElement('canvas');
      canvas.width = cameraPreview.videoWidth || 1280;
      canvas.height = cameraPreview.videoHeight || 720;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Could not get canvas 2D context');

      ctx.drawImage(cameraPreview, 0, 0, canvas.width, canvas.height);

      let blob = await canvasToBlob(canvas, 'image/jpeg', 0.92);
      if (!blob) throw new Error('canvasToBlob returned null');

      blob = await compressBlobToLimit(blob, MAX_BYTES);

      const filename = 'capture_' + Date.now() + '.jpg';
      let file;
      try {
        file = new File([blob], filename, { type: blob.type });
      } catch (e) {
        file = blob;
        file.name = filename;
        warn('File() constructor not supported — using blob with .name');
      }

      addThumbnail(blob, filename);
      appendFileToInput(filesCaptureInput, file);

      updateFileLabels();
      checkShowCleanButton();

      Swal.fire({
        position: "top-end",
        icon: "success",
        title: "Capture saved!",
        showConfirmButton: false,
        timer: 460
      });

    } catch (e) {
      fail('Capture failed', e);
    }
  }

  // Wire events
  if (openBtn) openBtn.addEventListener('click', startCamera);
  else warn('openCameraButton not found');

  if (stopBtn) stopBtn.addEventListener('click', stopCamera);
  else warn('stopCamera not found');

  if (takeBtn) takeBtn.addEventListener('click', captureOnlyAttach);
  else warn('takeCameraPhoto button not found');

  // Cleanup on unload
  window.addEventListener('beforeunload', () => {
    if (stream) stopCamera();
  });

})();

// Helper function to append captured files to FormData
function appendAllFilesToFormData(fd) {
  const filesCaptureInput = document.getElementById('filesCapture');
  if (filesCaptureInput && filesCaptureInput.files && filesCaptureInput.files.length) {
    Array.from(filesCaptureInput.files).forEach(function (f) {
      fd.append('filesCapture[]', f, f.name || ('capture_' + Date.now() + '.jpg'));
    });
  }

  if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
    window.__capturedFilesFallback.forEach(function (f, idx) {
      const name = f.name || ('capture_fallback_' + Date.now() + '_' + idx + '.jpg');
      fd.append('filesCapture[]', f, name);
    });
  }
}

function updateFileLabels() {
  var uploadCount = document.querySelectorAll('.file-thumb[data-type="upload"]').length;
  var cameraCount = document.querySelectorAll('.file-thumb[data-type="camera"]').length;

  if (uploadCount > 0) {
    $("#selectItem").html("Attached Files (" + uploadCount + ")");
  } else {
    $("#selectItem").html("Attached Files");
  }

  if (cameraCount > 0) {
    $("#captureItem").html("Camera Captures (" + cameraCount + ")");
  } else {
    $("#captureItem").html("Camera Captures");
  }
}

function checkShowCleanButton() {
  var totalThumbs = document.querySelectorAll(".file-thumb").length;
  if (totalThumbs > 0) {
    $("#clean_files").removeClass("hide");
  } else {
    $("#clean_files").addClass("hide");
  }
}

function removeFileFromInputByName(inputEl, filename) {
  if (!inputEl) return;
  try {
    var dt = new DataTransfer();
    Array.from(inputEl.files || []).forEach(function(f) {
      if (f.name !== filename) dt.items.add(f);
    });
    inputEl.files = dt.files;
  } catch (e) { console.warn('removeFileFromInputByName failed', e); }
}

/**
 * Switch a row between the two mutually-exclusive pricing modes.
 * @param {number}  index     Row index.
 * @param {boolean} useCustom true = custom price (USD); false = weight-based.
 *
 * Clearing the unused field guarantees an item never carries both a weight and
 * a custom price (enforced again on the backend). A full re-render keeps the
 * segmented toggle's active/inactive button styling in sync.
 */
function setPricingMode(index, useCustom) {
    useCustom = !!useCustom;
    if (packagesItems[index].use_custom_price === useCustom) return;

    packagesItems[index].use_custom_price = useCustom;
    if (useCustom) {
        packagesItems[index].weight = 0;
    } else {
        packagesItems[index].custom_price = 0;
    }

    loadPackages();
    calculateFinalTotal();
    if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch();

    // Put the cursor in the now-active field for fast data entry.
    $("#" + (useCustom ? "customPrice_" : "weight_") + index).trigger("focus");
}

/* ---- Custom-price entry currency ($ / ₵) ---------------------------------
   Storage (packagesItems + what is submitted) is ALWAYS USD; the toggle only
   controls how the typed value is interpreted. No handling fee applies here. */
function cdpEntryRate() {
    return Number(window.CDP_RATE) > 0 ? Number(window.CDP_RATE) : 0;
}

function cdpEntryToUsd(index, value) {
    var cur = $("#customPrice_" + index).attr("data-cur") || "usd";
    return (cur === "ghs" && cdpEntryRate() > 0) ? value / cdpEntryRate() : value;
}

function setEntryCurrency(index, cur) {
    cur = (cur === "ghs" && cdpEntryRate() > 0) ? "ghs" : "usd";
    var $i = $("#customPrice_" + index);
    var old = $i.attr("data-cur") || "usd";
    if (old !== cur) {
        var raw = nf($i.val(), 0);
        if (raw > 0) {
            var usd = (old === "ghs") ? raw / cdpEntryRate() : raw;
            $i.val(r2(cur === "ghs" ? usd * cdpEntryRate() : usd));
        }
        $i.attr("data-cur", cur).attr("placeholder", cur.toUpperCase());
    }
    $("#curUsd_" + index).toggleClass("btn-dark", cur === "usd").toggleClass("btn-outline-dark", cur !== "usd");
    $("#curGhs_" + index).toggleClass("btn-dark", cur === "ghs").toggleClass("btn-outline-dark", cur !== "ghs");
}

/**
 * Toggle the discount between a percentage of the base and a flat USD amount.
 */
function setDiscountType(type) {
    type = (type === "amount") ? "amount" : "percent";
    $("#discount_type").val(type);
    $("#discount_type_percent")
        .toggleClass("btn-dark", type === "percent")
        .toggleClass("btn-outline-dark", type !== "percent");
    $("#discount_type_amount")
        .toggleClass("btn-dark", type === "amount")
        .toggleClass("btn-outline-dark", type !== "amount");
    calculateFinalTotal();
}

/**
 * Compute a single row's line total in USD (courier_add never converts to GHS).
 * Weight mode : weight * qty * price_lb   (price_lb = system rate, e.g. $8/kilo)
 * Custom mode : custom_price * qty        (custom_price already entered in USD)
 */
function computeLineTotal(item, priceLb) {
    var qty = Math.max(1, nf(item.qty, 1));

    if (item.use_custom_price) {
        return nf(item.custom_price, 0) * qty;
    } else {
        return nf(item.weight, 0) * qty * priceLb;
    }
}