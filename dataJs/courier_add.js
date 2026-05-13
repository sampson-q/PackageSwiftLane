"use strict";

/* =========================================================================
   COURIER ADD — JS COMPLETO (AUTO-TARIFA EN VIVO, NUNCA OCULTA TOTALES)
   ========================================================================= */

var deleted_file_ids = [];

// Estado de paquetes en memoria
var packagesItems = [
  { qty: 1, description: "", length: 0, width: 0, height: 0, weight: 0, declared_value: 0, fixed_value: 0 }
];

// Última cotización obtenida (endpoint). Si no hay, se usa el price_lb del formulario.
window.lastQuote = null;

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

  // Modo de tarifa: manual vs automática
  $("#tariff_mode").on("change click", function () {
    const manual = $(this).is(":checked");
    $("#price_lb").prop("readonly", !manual);
    scheduleRecalc();
    if (!manual) scheduleAutoFetch(true);
  });

  if (!$("#tariff_mode").is(":checked")) {
    $("#price_lb").prop("readonly", true);
  }

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
    var countLabel = typeof translate_attached_files_count !== "undefined" ? translate_attached_files_count : "attached files";
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
    "input.qty, input.weight, input.length, input.width, input.height, input[name='description']",
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

            $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
            $("#add_address_recipient").prop("disabled", true);

            if ($(this).val()) {
                $("#recipient_address_id").prop("disabled", false);
                $("#add_address_recipient").prop("disabled", false);
            }

            // re-init with correct type
            cdp_select2_init_recipient_address();
            scheduleAutoFetch();
        })
        .on("change", function () {
            if (!$(this).val()) {
                window.recipient_type = 'recipient'; // reset on clear
                $("#recipient_address_id").prop("disabled", true).val(null).trigger("change");
                $("#add_address_recipient").prop("disabled", true);
                cdp_select2_init_recipient_address();
                scheduleAutoFetch();
            }
        })
}

function cdp_select2_init_recipient_address() {
    var recipient_id = $("#recipient_id").val();

    // get type (default fallback = recipient)
    var recipient_type = window.recipient_type || 'recipient';

    $("#recipient_address_id")
        .select2({
            ajax: {
                url: "ajax/select2_recipient_addresses.php",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        id: recipient_id,
                        type: recipient_type,
                        q: params.term
                    };
                },
                processResults: function (data) { return { results: data }; },
                cache: true
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
  if (!$tbody.length) {
    $tbody = $("<tbody/>");
    $table.append($tbody);
  }

  $tbody.empty();

  packagesItems.forEach(function (item, index) {
    var tr = `
      <tr id="row_id_${index}">
        <td style="width: 3%;"><input type="text" class="form-control form-control-sm qty"    name="qty"    id="qty_${index}"    value="${item.qty}"    onkeypress="return isNumberKey(event,this)"></td>
        <td style="width: 75%;">
          <input type="text" class="form-control form-control-sm" name="description" id="description_${index}" value="${item.description || ''}" placeholder="${typeof translate_description!=='undefined'?translate_description:'Description'}">
          <input type="hidden" name="fixed_value"     id="fixedValue_${index}"     value="${Number(item.fixed_value || 0)}">
          <input type="hidden" name="declared_value"  id="declaredValue_${index}"  value="${Number(item.declared_value || 0)}">
          <input type="hidden" name="weightVol"       id="weightVol_${index}"      value="0">
        </td>
        <td style="width: 7%;"><input type="text" class="form-control form-control-sm weight" name="weight" id="weight_${index}" value="${item.weight}" onkeypress="return isNumberKey(event,this)"></td>
        <td style="width: 7%;"><input type="text" class="form-control form-control-sm length" name="length" id="length_${index}" value="${item.length}" onkeypress="return isNumberKey(event,this)"></td>
        <td style="width: 7%;"><input type="text" class="form-control form-control-sm width"  name="width"  id="width_${index}"  value="${item.width}"  onkeypress="return isNumberKey(event,this)"></td>
        <td style="width: 7%;"><input type="text" class="form-control form-control-sm height" name="height" id="height_${index}" value="${item.height}" onkeypress="return isNumberKey(event,this)"></td>
        
        <td style class="text-center">
          ${index > 0 ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="deletePackage(${index})"><i class="fa fa-trash"></i></button>` : ``}
        </td>
      </tr>`;
    $tbody.append(tr);
  });

  // Botón agregar fila (id antiguo y nuevo)
  $("#add_rows, #add_row")
    .off("click.addPkg")
    .on("click.addPkg", function (e) {
      e.preventDefault();
      addPackage();
    });

  calculateFinalTotal();
  if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch();
}
function addPackage() {
  packagesItems.push({ qty: 1, description: "", length: 0, width: 0, height: 0, weight: 0, declared_value: 0, fixed_value: 0 });
  var index = packagesItems.length - 1;
  loadPackages();
  $("#row_id_" + index).css({ backgroundColor: "#18BC9C" });
  setTimeout(function () {
    $("#row_id_" + index).css({ backgroundColor: "" });
  }, 900);
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
  var idx = parseInt(parts[1], 10);
  var field = el.name;

  packagesItems = packagesItems.map(function (item, i) {
    if (i === idx) {
      item[field] = el.value || 0;
    }
    return item;
  });

  calculateFinalTotal();
  if (!$("#tariff_mode").is(":checked")) scheduleAutoFetch();
}

/* ==========================
   Adjuntos
   ========================== */
function cdp_preview_images() {

    const input = document.getElementById("filesMultiple");
    if (!input) return;

    const files = Array.from(input.files || []);
    const previewWrap = document.getElementById("image_preview");

    if (!previewWrap) return;

    // clear previous previews
    previewWrap.innerHTML = "";

    files.forEach((file) => {

        const mimeRoot = (file.type || "").split("/")[0];

        let previewBlob;

        if (mimeRoot === "image") {
            previewBlob = file;
        } else {

            // fake blob for non-images
            previewBlob = new Blob([], { type: "image/jpeg" });

            previewBlob.previewFallback =
                "assets/images/no-preview.jpeg";
        }

        addUnifiedThumbnail(previewBlob, file.name, file);
    });

    $("#total_item_files").val(files.length);

    if (files.length > 0) {
        $("#clean_files").removeClass("hide");
    } else {
        $("#clean_files").addClass("hide");
    }

    $("#selectItem").html("attached files (" + files.length + ")");
}


function addUnifiedThumbnail(blob, filename, originalFile = null) {

    const previewWrap = document.getElementById("image_preview");
    if (!previewWrap) return;

    const isRealImage = !blob.previewFallback;

    const url = isRealImage
        ? URL.createObjectURL(blob)
        : blob.previewFallback;

    const container = document.createElement("div");

    container.className = "file-thumb";

    container.dataset.filename = filename;

    container.style = `
        display:inline-block;
        margin:6px;
        position:relative;
        width:130px;
        vertical-align:top;
    `;

    container.innerHTML = `
        <div style="
            position:relative;
            border-radius:10px;
            overflow:hidden;
            border:1px solid #ddd;
            background:#fff;
        ">
            <img
                src="${url}"
                alt="${filename}"
                style="
                    width:130px;
                    height:100px;
                    object-fit:cover;
                    display:block;
                "
            >

            <button
                type="button"
                class="remove-preview-btn"
                style="
                    position:absolute;
                    top:6px;
                    right:6px;
                    width:24px;
                    height:24px;
                    border:none;
                    border-radius:50%;
                    background:rgba(0,0,0,.65);
                    color:#fff;
                    cursor:pointer;
                    font-size:14px;
                    line-height:24px;
                "
            >
                ×
            </button>
        </div>

        <div style="
            font-size:11px;
            margin-top:5px;
            text-align:center;
            word-break:break-word;
        ">
            ${filename}
        </div>

        <div style="
            font-size:10px;
            color:#666;
            text-align:center;
        ">
            ${Math.round((originalFile?.size || blob.size || 0) / 1024)} KB
        </div>
    `;

    previewWrap.prepend(container);

    const removeBtn = container.querySelector(".remove-preview-btn");

    removeBtn.addEventListener("click", () => {

        container.remove();

        removeFileFromInputByName(
            document.getElementById("filesMultiple"),
            filename
        );

        removeFileFromInputByName(
            document.getElementById("filesCapture"),
            filename
        );

        if (
            window.__capturedFilesFallback &&
            window.__capturedFilesFallback.length
        ) {
            window.__capturedFilesFallback =
                window.__capturedFilesFallback.filter(
                    f => f.name !== filename
                );
        }

        const total =
            document.querySelectorAll(".file-thumb").length;

        $("#total_item_files").val(total);

        $("#selectItem").html(
            "attached files (" + total + ")"
        );

        if (total <= 0) {
            $("#clean_files").addClass("hide");
        }
    });

    if (isRealImage) {
        setTimeout(() => URL.revokeObjectURL(url), 60000);
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
  return (packagesItems || []).map(function(p){
    return {
      qty: nf(p.qty, 1),
      description: p.description || "",
      weight: nf(p.weight),
      length: nf(p.length),
      width: nf(p.width),
      height: nf(p.height),
      declared_value: nf(p.declared_value),
      fixed_value: nf(p.fixed_value)
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
  var core_meter                 = nf($("#core_meter").val());
  var core_min_cost_tax          = nf($("#core_min_cost_tax").val());
  var core_min_cost_declared_tax = nf($("#core_min_cost_declared_tax").val());

  var isManual = $("#tariff_mode").is(":checked");

  var sum_weight_real = 0;
  var sum_weight_vol  = 0;
  var sum_declared    = 0;
  var sum_fixed       = 0;

  (packagesItems || []).forEach(function (item, i) {
    var qty     = Math.max(1, nf(item.qty, 1));
    var weight  = nf(item.weight);

    var $lengthEl = $("#length_" + i);
    var $widthEl  = $("#width_" + i);
    var $heightEl = $("#height_" + i);

    var lengthRaw = $.trim($lengthEl.val() || "");
    var widthRaw  = $.trim($widthEl.val() || "");
    var heightRaw = $.trim($heightEl.val() || "");

    var length = nf(lengthRaw);
    var width  = nf(widthRaw);
    var height = nf(heightRaw);

    var fixed   = nf(item.fixed_value);
    var decl    = nf(item.declared_value);

    function isDimensionEmpty(val) {
        return (
            val === "" ||
            val === null ||
            val === undefined ||
            val === "0" ||
            Number(val) === 0
        );
    }

    var hasAnyDimension =
        !isDimensionEmpty(lengthRaw) ||
        !isDimensionEmpty(widthRaw) ||
        !isDimensionEmpty(heightRaw);

    if (hasAnyDimension) {

        $lengthEl.css(
            "border",
            isDimensionEmpty(lengthRaw)
                ? "1px solid red"
                : ""
        );

        $widthEl.css(
            "border",
            isDimensionEmpty(widthRaw)
                ? "1px solid red"
                : ""
        );

        $heightEl.css(
            "border",
            isDimensionEmpty(heightRaw)
                ? "1px solid red"
                : ""
        );

    } else {

        $lengthEl.css("border", "");
        $widthEl.css("border", "");
        $heightEl.css("border", "");
    }

    var vol_piece = 0;
    if (core_meter > 0 && length > 0 && width > 0 && height > 0) {
      vol_piece = (length * width * height) / core_meter;
    }

    if ($("#weightVol_" + i).length) $("#weightVol_" + i).val(r2(vol_piece));

    sum_weight_real += weight * qty;
    sum_weight_vol  += vol_piece * qty;
    sum_declared    += decl * qty;
    sum_fixed       += fixed * qty;
  });

  var chargeable = Math.max(nf(sum_weight_real.toFixed(2)), nf(sum_weight_vol.toFixed(2)));
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
      } else if (window.lastQuote.data && typeof window.lastQuote.data.price !== "undefined") {
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
  var total_aduana = ((sum_weight_real + sum_weight_vol) * tariffs_value) / 100;

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
  $("#total_vol_weight").html(r2(sum_weight_vol));
  if ($("#total_fixed").length) $("#total_fixed").html(r2(sum_fixed));
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
    if ($.trim($("#description_" + i).val()).length == 0) {
      Swal.fire({ text: validation_description, icon: "error", confirmButtonText: "Ok" });
      $("#description_" + i).focus();
      return false;
    }
    if ($.trim($("#qty_" + i).val()).length == 0) {
      Swal.fire({ text: validation_quantity, icon: "error", confirmButtonText: "Ok" });
      $("#qty_" + i).focus();
      return false;
    }
    if ($.trim($("#weight_" + i).val()).length == 0) {
      Swal.fire({ text: validation_weight, icon: "error", confirmButtonText: "Ok" });
      $("#weight_" + i).focus();
      return false;
    }
    if ($.trim($("#length_" + i).val()).length == 0) {
      Swal.fire({ text: validation_length, icon: "error", confirmButtonText: "Ok" });
      $("#length_" + i).focus();
      return false;
    }
    if ($.trim($("#width_" + i).val()).length == 0) {
      Swal.fire({ text: validation_width, icon: "error", confirmButtonText: "Ok" });
      $("#width_" + i).focus();
      return false;
    }
    if ($.trim($("#height_" + i).val()).length == 0) {
      Swal.fire({ text: validation_height, icon: "error", confirmButtonText: "Ok" });
      $("#height_" + i).focus();
      return false;
    }
    if ($.trim($("#fixedValue_" + i).val()).length == 0) {
      Swal.fire({ text: validation_charge, icon: "error", confirmButtonText: "Ok" });
      $("#fixedValue_" + i).focus();
      return false;
    }
    if ($.trim($("#declaredValue_" + i).val()).length == 0) {
      Swal.fire({ text: validation_declared, icon: "error", confirmButtonText: "Ok" });
      $("#declaredValue_" + i).focus();
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
  if (tax_value)            data.append("tax_value", tax_value);
  if (declared_value_tax)   data.append("declared_value_tax", declared_value_tax);
  if (tariffs_value)        data.append("tariffs_value", tariffs_value);
  if (insurance_value)      data.append("insurance_value", insurance_value);
    if (tracking_number)    data.append("tracking_number", tracking_number);
    if (estimated_eta)      data.append("estimated_eta", estimated_eta);
  

  // motor tarifas (siempre enviar para que el backend calcule cuando manual_tariff=0)
  data.append("rate_provider", rate_provider || "internal");
  data.append("distance_miles", distance_miles);

  // notificaciones
  if (notify_whatsapp_sender)   data.append("notify_whatsapp_sender", notify_whatsapp_sender);
  if (notify_whatsapp_receiver) data.append("notify_whatsapp_receiver", notify_whatsapp_receiver);
  if (notify_sms_sender)        data.append("notify_sms_sender", notify_sms_sender);
  if (notify_sms_receiver)      data.append("notify_sms_receiver", notify_sms_receiver);
  if (tariff_mode)              data.append("tariff_mode", tariff_mode);

  if (deleted_file_ids_val) data.append("deleted_file_ids", deleted_file_ids_val);

  // archivos
  var fileInput = document.getElementById("filesMultiple");
  if (fileInput && fileInput.files) {
    for (var j = 0; j < fileInput.files.length; j++) {
      data.append("filesMultiple[]", fileInput.files[j]);
    }
  }

    // camera captures
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

  data.append('_csrf_token', $('input[name="_csrf_token"]').val());
  data.append('recipient_type', window.recipient_type || 'recipient');

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
    confirmButtonText: "Ok"
  });
}
function cdp_showSuccess(message, shipment_id) {
  Swal.fire({
    title: message || "OK",
    icon: "success",
    allowOutsideClick: false,
    confirmButtonText: "Ok"
  }).then(function (result) {
    if (result.isConfirmed) {
      setTimeout(function () {
        window.location = "courier_view.php?id=" + shipment_id;
      }, 2000);
    }
  });
}

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

    // Elements (IDs must match your HTML)
    const openBtn = document.getElementById('openCameraButton');
    const cameraPreview = document.getElementById('cameraPreview');
    const takeBtn = document.getElementById('takeCameraPhoto');
    const stopBtn = document.getElementById('stopCamera');
    const filesCaptureInput = document.getElementById('filesCapture'); // hidden input
    const previewWrap = document.getElementById('image_preview');

    // fallback array if DataTransfer not supported or setting input.files fails
    window.__capturedFilesFallback = window.__capturedFilesFallback || [];

    let stream = null;

    function log(...args){ console.log('[capture]', ...args); }
    function warn(...args){ console.warn('[capture]', ...args); }
    function fail(msg, e){ console.error('[capture]', msg, e); alert(msg + (e && e.message ? '\n' + e.message : '')); }

    // canvas.toBlob wrapper with DataURL fallback (very defensive)
    function canvasToBlob(canvas, mime='image/jpeg', quality=0.92){
        return new Promise((resolve, reject) => {
        try {
            if (canvas.toBlob) {
            canvas.toBlob(b => {
                if (b) resolve(b);
                else {
                // fallback
                try {
                    const dataUrl = canvas.toDataURL(mime, quality);
                    const parts = dataUrl.split(';base64,');
                    const binary = atob(parts[1]);
                    const len = binary.length;
                    const u8 = new Uint8Array(len);
                    for (let i=0;i<len;i++) u8[i] = binary.charCodeAt(i);
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
            for (let i=0;i<len;i++) u8[i] = binary.charCodeAt(i);
            resolve(new Blob([u8], { type: mime }));
            }
        } catch (e) { reject(e); }
        });
    }

    // iterative compression: reduce quality, then downscale until under limit
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
        canvas.width = w; canvas.height = h;
        ctx.drawImage(img, 0, 0, w, h);

        let quality = 0.92;
        let out = await canvasToBlob(canvas, 'image/jpeg', quality);

        while (out.size > maxBytes && quality > 0.08) {
            quality = Math.max(0.08, quality - 0.07);
            out = await canvasToBlob(canvas, 'image/jpeg', quality);
        }

        while (out.size > maxBytes && Math.min(w,h) > 200) {
            w = Math.floor(w * 0.92);
            h = Math.floor(h * 0.92);
            canvas.width = w; canvas.height = h;
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

    // create a thumbnail in #image_preview and add a remove button
    function addThumbnail(blob, filename) {
        if (!previewWrap) return;
        const url = URL.createObjectURL(blob);
        const container = document.createElement('div');
        container.className = 'thumb';
        container.style = 'display:inline-block;margin:6px;position:relative;';
        container.dataset.filename = filename;
        container.innerHTML = `
        <img src="${url}" alt="${filename}" style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
        <div class="remove-btn" title="Remove" style="position:absolute;right:6px;top:6px;background:rgba(0,0,0,0.6);color:#fff;border-radius:50%;width:22px;height:22px;text-align:center;line-height:22px;cursor:pointer;font-size:12px;">&times;</div>
        <div style="font-size:11px;margin-top:4px;text-align:center;">${Math.round((blob.size||0)/1024)} KB</div>
        `;
        previewWrap.prepend(container);

        const removeEl = container.querySelector('.remove-btn');
        removeEl.addEventListener('click', () => {
        // remove from DOM
        container.remove();
        // remove from filesCapture input (if present)
        if (filesCaptureInput) removeFileFromInputByName(filesCaptureInput, filename);
        // also remove from fallback store if present
        if (window.__capturedFilesFallback && window.__capturedFilesFallback.length) {
            window.__capturedFilesFallback = window.__capturedFilesFallback.filter(f => f.name !== filename);
        }
        });

        // revoke blob url eventually
        setTimeout(() => URL.revokeObjectURL(url), 60 * 1000);
    }

    // append a File object to an input[type=file] using DataTransfer; returns true if worked.
    function appendFileToInput(inputEl, file) {
        if (!inputEl) {
            // store in fallback
            window.__capturedFilesFallback.push(file);
            log('No input element; saved in fallback array');
            return false;
        }
        try {
            const dt = new DataTransfer();
            // copy existing files
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

    // remove file from input's FileList by matching filename
    function removeFileFromInputByName(inputEl, filename) {
        if (!inputEl) return;
        try {
        const dt = new DataTransfer();
        Array.from(inputEl.files || []).forEach(f => { if (f.name !== filename) dt.items.add(f); });
        inputEl.files = dt.files;
        log('Removed', filename, 'from input.files (if existed)');
        } catch (e) { warn('removeFileFromInputByName failed', e); }
    }

    // small helper: wait until video has size
    function waitForVideoReady(videoEl, timeout = 3000) {
        return new Promise((resolve, reject) => {
        if (videoEl.videoWidth && videoEl.videoHeight) return resolve();
        let elapsed = 0;
        const iv = 100;
        const t = setInterval(() => {
            elapsed += iv;
            if (videoEl.videoWidth && videoEl.videoHeight) { clearInterval(t); resolve(); }
            else if (elapsed >= timeout) { clearInterval(t); reject(new Error('Video not ready (timed out)')); }
        }, iv);
        });
    }

    // --- camera controls ---
    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Camera not supported by this browser.');
        return;
        }
        try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        cameraPreview.srcObject = stream;
        cameraPreview.style.display = 'block';
        if (takeBtn) takeBtn.style.display = 'inline-block';
        if (stopBtn) stopBtn.style.display = 'inline-block';
        if (openBtn) openBtn.style.display = 'none';
        } catch (e) {
        fail('Unable to open camera', e);
        }
    }

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

    // capture frame, compress, and attach to filesCapture input (no upload)
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
                // very old browsers may not support File constructor
                file = blob;
                file.name = filename;
                warn('File() constructor not supported — using blob with .name');
            }

            // show thumb
            addUnifiedThumbnail(blob, filename, file);

            // attempt to add to input.files
            appendFileToInput(filesCaptureInput, file);


            Swal.fire({
                position: "top",
                icon: "success",
                title: "Capture saved!",
                showConfirmButton: false,
                timer: 460
            });

        } catch (e) {
            fail('Capture failed', e);
        }
    }

    // wire events (defensive)
    if (openBtn) openBtn.addEventListener('click', startCamera);
    else warn('openCameraButton not found');

    if (stopBtn) stopBtn.addEventListener('click', stopCamera);
    else warn('stopCamera not found');

    if (takeBtn) takeBtn.addEventListener('click', captureOnlyAttach);
    else warn('takeCameraPhoto button not found');

    // cleanup on unload
    window.addEventListener('beforeunload', () => { if (stream) stopCamera(); });

})();