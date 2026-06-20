"use strict";
var deleted_file_ids = [];

$(function () {
  $("#order_date").datepicker({
    format: "yyyy-mm-dd",
    autoclose: true,
  });

  $("#register_customer_to_user").click(function () {
    if ($(this).is(":checked")) {
      $("#show_hide_user_inputs").removeClass("d-none");
    } else {
      $("#show_hide_user_inputs").addClass("d-none");
    }
  });

  cdp_load(1);

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
            url: "./ajax/consolidate/consolidate_files_uploads_delete_ajax.php",
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

      // });
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
  console.log(file);
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

// Dangerous-goods consolidation kind. On the EDIT screen this is fixed to the
// consolidation's own kind (set from the page); Find Shipments and the filter
// control stay locked to it so dangerous and normal goods can never be mixed.
window.cdpConsType =
  window.CDP_DG_MODE === "edit" ? (window.CDP_DG_LOCK ? 1 : 0) : null;

function cdp_currentDg() {
  if (window.CDP_DG_MODE === "edit") {
    return window.CDP_DG_LOCK ? 1 : 0;
  }
  var v = $('input[name="dg_filter"]:checked').val();
  return String(v) === "1" ? 1 : 0;
}

// Lock the filter control to the consolidation's kind and explain why.
function cdpApplyDgLock() {
  var $group = $("#dg_filter_group");
  if (!$group.length) return;
  var lockVal = window.cdpConsType ? 1 : 0;
  $("#dg_filter_normal").prop("checked", lockVal === 0).closest("label").toggleClass("active", lockVal === 0);
  $("#dg_filter_dg").prop("checked", lockVal === 1).closest("label").toggleClass("active", lockVal === 1);
  $group.find("input").prop("disabled", true);
  $group.css({ opacity: 0.85, "pointer-events": "none" });
  $("#dg_filter_note")
    .text(lockVal ? "This consolidation holds dangerous goods." : "This consolidation holds normal goods.")
    .show();
}

$(function () {
  cdpApplyDgLock();
  $("#myModalConsolidate").on("shown.bs.modal", cdpApplyDgLock);
});

//Cargar datos AJAX
function cdp_load(page) {
  var search = $("#search").val();
  var parametros = {
    page: page,
    search: search,
    dg: cdp_currentDg(),
  };
  $("#loader").fadeIn("slow");
  $.ajax({
    url: "./ajax/consolidate/courier_list_add_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {},
    success: function (data) {
      $(".outer_div").html(data).fadeIn("slow");
      // "Add Selected" + the modal search are handled by delegated handlers
      // (bound once, below) so they survive every content reload.
    },
  });
}

// Find-shipments modal: "Add Selected". Delegated + preventDefault so the button
// can never fall through to a form submit (a submit reloads the page, which is
// what made the whole modal disappear).
$(document).on("click", "#add_checked_packages", function (e) {
  e.preventDefault();
  var added = 0;
  document.querySelectorAll(".pkg-checkbox:checked").forEach(function (checkbox) {
    var row = checkbox.closest("tr");
    if (!row) return;
    cdp_add_item(
      row.getAttribute("data-order-id"),
      row.getAttribute("data-total-metric"),
      row.getAttribute("data-weight"),
      row.getAttribute("data-length"),
      row.getAttribute("data-width"),
      row.getAttribute("data-height"),
      row.getAttribute("data-tracking"),
      row.getAttribute("data-order-no"),
      row.getAttribute("data-order-prefix"),
      row.getAttribute("data-sender"),
      row.getAttribute("data-description"),
      row.getAttribute("data-total-order"),
      row.getAttribute("data-quantity"),
      row.getAttribute("data-total-order-raw")
    );
    row.remove();
    added++;
  });
  if (typeof updateSelectionBar === "function") updateSelectionBar();
  // Selected packages are now in the consolidation list — close the modal.
  if (added > 0) {
    $("#myModalConsolidate").modal("hide");
  }
});

// The modal search must filter via AJAX, never submit/reload the page (a reload
// closes the modal). Covers Enter and the search button.
$(document).on("submit", "#send_email", function (e) {
  e.preventDefault();
  cdp_load(1);
});

$("#save_data").on("submit", function (event) {
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/tools/category/category_add_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("Please wait...");
    },
    success: function (datos) {
      $("#resultados_ajax").html(datos);

      $("html, body").animate(
        {
          scrollTop: 0,
        },
        600
      );
    },
  });
  event.preventDefault();
});

$(function () {
  var count = $("#total_item").val();

    $(document).on("click", ".remove_row", function () {
        var row_id = $(this).attr("id");
        var parent = $("#row_id_" + row_id);

        Swal.fire({
            title: message_delete_confirm,
            text: message_delete_confirm2,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: message_delete_confirm1
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    type: "post",
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_csrf_token"]').val()
                    },
                    url: "./ajax/consolidate/consolidate_item_delete_ajax.php",
                    data: {
                        id: row_id,
                        origin_off: $("#exampleFormControlSelect1").val(),
                    },
                    beforeSend: function () {
                    parent.animate({
                        backgroundColor: "#FFBFBF",
                        }, 400);
                    },
                    success: function (data) {
                        var index = selected.indexOf(row_id);
                        selected.splice(index, 1);
    
                        parent.animate({
                        backgroundColor: "#FFBFBF",
                        }, 40);
    
                        count--;
                        parent.fadeOut(40, function () {
                            // parent.remove();
                            $("#row_id_" + row_id).remove();
                            cdp_cal_final_total();
                        });
    
                        $("#total_item").val(selected.length);
                        cdp_load(localStorage.getItem('currentTablePageConsolidateEdit'));

                        Swal.fire({
                            title: "Package Removed!",
                            text: "Package has been removed from consolidation.",
                            icon: "success"
                        });
                    },
                });
            }
        });
    });

  $("#create_invoice").on("click", function () {
    if ($.trim($("#total_item").val()) <= 1) {
      Swal.fire({
        type: "warning",
        title: "Oops...",
        text: message_error_form85,
        iconHtml: '<i class="fas fa-exclamation-triangle" style="font-size: 48px; color: orange;"></i>',
        confirmButtonColor: "#336aea",
      });
      return false;
    }

    if ($.trim($("#seals").val()).length == 0) {
      Swal.fire({
        title: "Oops...",
        text: message_error_form91,
        icon: "error",
        confirmButtonColor: "#336aea",
      });
      $("#seals").css("border-color", "red"); // Aplica el borde rojo al input
      $("#seals").focus();
      return false;
    }

    // if ($.trim($("#recipient_id").val()).length == 0) {
    //   Swal.fire({
    //     title: "Oops...",
    //     text: message_error_form86,
    //     icon: "error",
    //     confirmButtonColor: "#336aea",
    //   });
    //   return false;
    // }

    // if ($.trim($("#recipient_address_id").val()).length == 0) {
    //   Swal.fire({
    //     title: "Oops...",
    //     text: message_error_form87,
    //     icon: "error",
    //     confirmButtonColor: "#336aea",
    //   });
    //   return false;
    // }

    // //data sender

    // if ($.trim($("#sender_id").val()).length == 0) {
    //   Swal.fire({
    //     title: "Oops...",
    //     text: message_error_form88,
    //     icon: "error",
    //     confirmButtonColor: "#336aea",
    //   });
    //   return false;
    // }

    // if ($.trim($("#sender_address_id").val()).length == 0) {
    //   Swal.fire({
    //     title: "Oops...",
    //     text: message_error_form89,
    //     icon: "error",
    //     confirmButtonColor: "#336aea",
    //   });
    //   return false;
    // }

    // if ($.trim($("#driver_id").val()) == 0) {
    //   Swal.fire({
    //     title: "Oops...",
    //     text: message_error_form90,
    //     icon: "error",
    //     confirmButtonColor: "#336aea",
    //   });
    //   return false;
    // }

    $("#invoice_form").submit();
  });
});

function cdp_cal_final_total() {
  var total_weight_sum = 0;
  var total_cost_sum = 0;

  selected.forEach(function (orderId) {
    var weight = parseFloat($("#weight_" + orderId).val()) || 0;
    var totalPrice = parseFloat($("#total_price_" + orderId).val()) || 0;

    total_weight_sum += weight;
    total_cost_sum += totalPrice;
  });

  $("#total_weight_sum").html(total_weight_sum.toFixed(2));
  $("#total_cost_sum").html(typeof format_currency !== 'undefined' ? format_currency(total_cost_sum) : total_cost_sum.toFixed(2));
}

function cdp_add_item(id, total_vol, weight, length, width, height, tracking, order_no, order_prefix, sender, description, total_price, quantity, total_price_raw) {
  if (selected.includes(id)) {
    $("#modal_consolidate").html(
      '<div class="alert alert-danger" id="success-alert">' +
        '<p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>' +
        "  <span>Error! </span> " +
        message_error_consolidate_add_packages +
        "" +
        "</p>" +
        "</div>"
    );
  } else {
    count++;

    $("#modal_consolidate").html("");
    selected.push(id);
    $("#total_item").val(selected.length);

    var parent = $("#row_id_" + id);

    var html_code = "";
    html_code += '<tr class="card-hover " id="row_id_' + id + '">';

    html_code += '<td><b>' + sender + "</b></td>";
    html_code += '<td><b>' + tracking + "</b></td>";
    html_code += '<td class="text-right">' + (quantity ? parseInt(quantity) : '') + '</td>';
    html_code += '<td><b>' + description + "</b></td>";
    html_code += '<td><b>' + weight + "</b></td>";
    html_code += '<td></td>';
    html_code += '<td><b>' + total_price + "</b></td>";
    html_code += '<td></td>';

    html_code += '<input type="hidden"  id="total_vol_' + id + '"  value="' + total_vol + '" name="weight_vol[]">';
    html_code += '<input type="hidden"   value="' + order_prefix + '" name="prefix[]">';
    html_code += '<input type="hidden"   value="' + order_no + '" name="order_no_item[]">';
    html_code += '<input type="hidden" id="weight_' + id + '"   value="' + weight + '" name="weight[]">';
    html_code += '<input type="hidden" id="total_price_' + id + '"   value="' + (total_price_raw || total_price) + '" name="total_price[]">';

    html_code += '<input type="hidden" id="length_' + id + '"   value="' + length + '" name="length[]">';
    html_code += '<input type="hidden" id="height_' + id + '"   value="' + height + '" name="height[]">';
    html_code += '<input type="hidden" id="width_' + id + '"   value="' + width + '" name="width[]">';
    html_code += '<input type="hidden" id="order_id_' + id + '"   value="' + id + '" name="order_id[]">';

    html_code +=
      '<td class="text-center"><button type="button" name="remove_row" id="' +
      id +
      '" class="btn btn-danger btn-xs remove_row mt-2"><i class="fa fa-trash"></i></button></td>';

    html_code += "</tr>";

    $("#invoice-item-table").append(html_code);

    $("#row_id_" + id).animate(
      {
        backgroundColor: "#18BC9C",
      },
      400
    );

    cdp_cal_final_total();

    $("#add_row").attr("disabled", true);

    setTimeout(function () {
      $("#row_id_" + id).css({ "background-color": "" });
      $("#add_row").attr("disabled", false);
    }, 900);
  }
}

function cdp_select2_init_sender() {
  $("#sender_id")
    .select2({
      ajax: {
        url: "ajax/select2_sender.php",
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

      minimumInputLength: 2,
      placeholder: search_sender,
      allowClear: true,
    })
    .on("change", function (e) {
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

    escapeMarkup: function (markup) {
      return markup;
    }, // let our custom formatter work
    // minimumInputLength: 1,
    templateResult: cdp_formatAdress, // omitted for brevity, see the source of this page
    templateSelection: cdp_formatAdressSelection, // omitted for brevity, see the source of this page
    // minimumInputLength: 2,
    placeholder: search_sender_address,
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
      // minimumInputLength: 2,
      placeholder: search_recipient,
      allowClear: true,
    })
    .on("change", function (e) {
      var recipient_id = $("#recipient_id").val();
      $("#add_address_recipient").attr("disabled", true);
      $("#recipient_address_id").attr("disabled", true);
      $("#recipient_address_id").val(null);

        // Capture the type from the selected option's data
      var selectedData = $("#recipient_id").select2("data");
      selectedRecipientType = selectedData && selectedData[0] && selectedData[0].type ? selectedData[0].type : "recipient";

      if (recipient_id != null) {
        $("#recipient_address_id").attr("disabled", false);
        $("#add_address_recipient").attr("disabled", false);
      }
      cdp_select2_init_recipient_address();
    });
}

function cdp_select2_init_recipient_address() {
  var recipient_id = $("#recipient_id").val();

  $("#recipient_address_id").select2({
    ajax: {
      url: "ajax/select2_recipient_addresses.php?id=" + recipient_id + "&type=" + selectedRecipientType,
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

    escapeMarkup: function (markup) {
      return markup;
    }, // let our custom formatter work
    // minimumInputLength: 1,
    templateResult: cdp_formatAdress, // omitted for brevity, see the source of this page
    templateSelection: cdp_formatAdressSelection, // omitted for brevity, see the source of this page
    // minimumInputLength: 2,
    placeholder: search_recipient_address,
    allowClear: true,
  });
}

// modal guardar cliente remitente formulario de envo, si activas el check adicionas contraseña al cliente

$("#add_user_from_modal_shipments").on("submit", function (event) {
  event.preventDefault(); // Evitar el envío del formulario por defecto

  if ($.trim($("#fname").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: message_error_form81,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#fname").focus();
    return false;
  }

  if ($.trim($("#lname").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: message_error_form82,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#lname").focus();
    return false;
  }

  // Validación del correo electrónico en el lado del cliente
  var email = $.trim($("#email").val());
  if (email.length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: message_error_form83,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#email").focus();
    return false;
  } else if (!isValidEmailAddress(email)) {
    // Función para validar el formato del correo electrónico
    Swal.fire({
      type: "warning",
      title: "Oops...",
      text: message_error_form84,
      icon: "warning",
      confirmButtonColor: "#336aea",
    });
    $("#email").focus();
    return false;
  }

  // Función para validar el formato del correo electrónico
  function isValidEmailAddress(email) {
    var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
  }

  if ($.trim($("#country_modal_user").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: validation_country,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#country_modal_user").focus();
    return false;
  }

  if ($.trim($("#state_modal_user").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_state,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#state_modal_user").focus();
    return false;
  }

  if ($.trim($("#city_modal_user").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_city,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#city_modal_user").focus();
    return false;
  }

  if ($.trim($("#postal_modal_user").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_zip,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#postal_modal_user").focus();
    return false;
  }

  if ($.trim($("#address_modal_user").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_address,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#address_modal_user").focus();
    return false;
  }

  if (iti.isValidNumber()) {
    var sender_id = $("#sender_id").val();
    $("#save_data_user").attr("disabled", true);
    var parametros = $(this).serialize();

    $.ajax({
      type: "POST",
      url: "ajax/courier/add_users_ajax.php?sender=" + sender_id,
      data: parametros,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            type: "success",
            title: message_error_form80,
            icon: "success",
            showConfirmButton: false,
            timer: 1500,
          }).then(() => {
            cdp_select2_init_sender();
            $(".resultados_ajax_add_user_modal_sender").html(response.data);
            $("#save_data_user").attr("disabled", false);
            $("#myModalAddUser").modal("hide");

            // Obtener la información del cliente y la dirección del cliente de la respuesta
            var data = {
              id: response.customer_data.id,
              text: response.customer_data.fname + " " + response.customer_data.lname,
            };

            var newOption = new Option(data.text, data.id, false, false);

            $("#sender_id").append(newOption).trigger("change");
            $("#sender_id").val(data.id).trigger("change");

            var data_address = {
              id: response.customer_address.id_addresses,
              text: response.customer_address.address,
            };

            var newOptionAddress = new Option(data_address.text, data_address.id, false, false);

            $("#sender_address_id").append(newOptionAddress).trigger("change");
            $("#sender_address_id").val(data_address.id).trigger("change");

            $("#recipient_address_id").attr("disabled", true);
            $("#add_address_recipient").attr("disabled", true);
            $("#recipient_id").val(null).trigger("change");
            $("#recipient_address_id").val(null).trigger("change");

            window.setTimeout(function () {
              $(".alert")
                .fadeTo(500, 0)
                .slideUp(500, function () {
                  $(this).remove();
                });
            }, 5000);
          });
        } else {
          Swal.fire({
            type: "error",
            title: "Oops...",
            text: response.message,
            icon: "error",
            confirmButtonColor: "#336aea",
          });
          $("#save_data_user").attr("disabled", false);
        }
      },
      error: function () {
        Swal.fire({
          type: "error",
          title: "Oops...",
          text: message_error_form19,
          icon: "error",
          confirmButtonColor: "#336aea",
        });
        $("#save_data_user").attr("disabled", false);
      },
    });
  } else {
    input.classList.add("error");
    var errorCode = iti.getValidationError();
    errorMsgSender.innerHTML = errorMap[errorCode];
    errorMsgSender.classList.remove("hide");
  }
});

// modal guardar cliente destinatario formulario de envios

$("#add_recipient_from_modal_shipments").on("submit", function (event) {
  event.preventDefault(); // Evitar el envío del formulario por defecto

  if ($.trim($("#fname_recipient").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: translate_label_firstname,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#fname_recipient").focus();
    return false;
  }

  if ($.trim($("#lname_recipient").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: translate_label_lastname,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#lname_recipient").focus();
    return false;
  }

  // Validación del correo electrónico en el lado del cliente
  var email = $.trim($("#email_recipient").val());
  if (email.length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: translate_label_email,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#email_recipient").focus();
    return false;
  } else if (!isValidEmailAddress(email)) {
    // Función para validar el formato del correo electrónico
    Swal.fire({
      type: "warning",
      title: "Oops...",
      text: message_error_form84,
      icon: "warning",
      confirmButtonColor: "#336aea",
    });
    $("#email_recipient").focus();
    return false;
  }

  // Función para validar el formato del correo electrónico
  function isValidEmailAddress(email) {
    var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
  }

  if ($.trim($("#country_modal_recipient").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: validation_country,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#country_modal_recipient").focus();
    return false;
  }

  if ($.trim($("#state_modal_recipient").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_state,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#state_modal_recipient").focus();
    return false;
  }

  if ($.trim($("#city_modal_recipient").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_city,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#city_modal_recipient").focus();
    return false;
  }

  if ($.trim($("#postal_modal_recipient").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_zip,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#postal_modal_recipient").focus();
    return false;
  }

  if ($.trim($("#address_modal_recipient").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_address,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#address_modal_recipient").focus();
    return false;
  }

  if (iti_recipient.isValidNumber()) {
    var sender_id = $("#sender_id").val();
    $("#save_data_recipient").attr("disabled", true);
    var parametros = $(this).serialize();

    $.ajax({
      type: "POST",
      url: "ajax/courier/add_recipients_ajax.php?sender=" + sender_id,
      data: parametros,
      success: function (response) {
        if (response.status === "success") {
          Swal.fire({
            type: "success",
            title: message_error_form80,
            icon: "success",
            showConfirmButton: false,
            timer: 1500,
          }).then(() => {
            // Acciones después de un éxito
            cdp_select2_init_sender();
            $(".resultados_ajax_add_user_modal_recipient").html(response.data);
            $("#save_data_recipient").attr("disabled", false);
            $("#myModalAddRecipient").modal("hide");

            // Actualizar campos de select
            var data = {
              id: response.customer_data.id,
              text: response.customer_data.fname + " " + response.customer_data.lname,
            };

            var newOption = new Option(data.text, data.id, false, false);

            $("#recipient_id").append(newOption).trigger("change");
            $("#recipient_id").val(data.id).trigger("change");

            var data_address = {
              id: response.customer_address.id_addresses,
              text: response.customer_address.address,
            };

            var newOption = new Option(data_address.text, data_address.id, false, false);

            $("#recipient_address_id").append(newOption).trigger("change");
            $("#recipient_address_id").val(data_address.id).trigger("change");

            // Ocultar mensaje de alerta
            window.setTimeout(function () {
              $(".alert")
                .fadeTo(500, 0)
                .slideUp(500, function () {
                  $(this).remove();
                });
            }, 5000);
          });
        } else {
          // Manejo de errores si la respuesta no es exitosa
          Swal.fire({
            type: "error",
            title: "Oops...",
            text: response.message,
            icon: "error",
            confirmButtonColor: "#336aea",
          });
          $("#save_data_recipient").attr("disabled", false);
        }
      },
      error: function () {
        // Manejo de errores si la solicitud falla
        Swal.fire({
          type: "error",
          title: "Oops...",
          text: message_error_form19,
          icon: "error",
          confirmButtonColor: "#336aea",
        });
        $("#save_data_recipient").attr("disabled", false);
      },
    });
  } else {
    input_recipient.classList.add("error");
    var errorCode = iti_recipient.getValidationError();
    errorMsgRecipient.innerHTML = errorMap[errorCode];
    errorMsgRecipient.classList.remove("hide");
  }
});

// modal guardar direccion cliente remitente

$("#add_address_users_from_modal_shipments").on("submit", function (event) {
  event.preventDefault(); // Evitar el envío del formulario por defecto

  if ($.trim($("#country_modal_user_address").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: validation_country,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#country_modal_user_address").focus();
    return false;
  }

  if ($.trim($("#state_modal_user_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_state,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#state_modal_user_address").focus();
    return false;
  }

  if ($.trim($("#city_modal_user_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_city,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#city_modal_user_address").focus();
    return false;
  }

  if ($.trim($("#postal_modal_user_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_zip,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#postal_modal_user_address").focus();
    return false;
  }

  if ($.trim($("#address_modal_user_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_address,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#address_modal_user_address").focus();
    return false;
  }

  var sender_id = $("#sender_id").val();
  $("#save_data_address_users").attr("disabled", true);
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/courier/add_address_users_ajax.php?sender=" + sender_id,
    data: parametros,
    success: function (response) {
      if (response.status === "success") {
        Swal.fire({
          type: "success",
          title: message_error_form80,
          icon: "success",
          showConfirmButton: false,
          timer: 1500,
        }).then(() => {
          $("#save_data_address_users").attr("disabled", false);
          $(".resultados_ajax_add_user_modal_sender").html(response.data);
          $("#myModalAddUserAddresses").modal("hide");

          var data_address = {
            id: response.customer_address.id_addresses,
            text: response.customer_address.address,
          };

          var newOptionAddress = new Option(data_address.text, data_address.id, false, false);

          $("#sender_address_id").append(newOptionAddress).trigger("change");
          $("#sender_address_id").val(data_address.id).trigger("change");

          window.setTimeout(function () {
            $(".alert")
              .fadeTo(500, 0)
              .slideUp(500, function () {
                $(this).remove();
              });
          }, 5000);
        });
      } else {
        Swal.fire({
          type: "error",
          title: "Oops...",
          text: response.message,
          icon: "error",
          confirmButtonColor: "#336aea",
        });
        $("#save_data_address_users").attr("disabled", false);
      }
    },
    error: function () {
      Swal.fire({
        type: "error",
        title: "Oops...",
        text: message_error_form19,
        icon: "error",
        confirmButtonColor: "#336aea",
      });
      $("#save_data_address_users").attr("disabled", false);
    },
  });
});

// modal guardar direccion cliente destinatario

$("#add_address_recipients_from_modal_shipments").on("submit", function (event) {
  event.preventDefault(); // Evitar el envío del formulario por defecto

  if ($.trim($("#country_modal_recipient_address").val()).length == 0) {
    Swal.fire({
      type: "Error!",
      title: "Oops...",
      text: validation_country,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#country_modal_recipient_address").focus();
    return false;
  }

  if ($.trim($("#state_modal_recipient_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_state,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#state_modal_recipient_address").focus();
    return false;
  }

  if ($.trim($("#city_modal_recipient_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_city,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#city_modal_recipient_address").focus();
    return false;
  }

  if ($.trim($("#postal_modal_recipient_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_zip,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#postal_modal_recipient_address").focus();
    return false;
  }

  if ($.trim($("#address_modal_recipient_address").val()).length == 0) {
    Swal.fire({
      type: "error",
      title: "Oops...",
      text: validation_address,
      icon: "error",
      confirmButtonColor: "#336aea",
    });
    $("#address_modal_recipient_address").focus();
    return false;
  }

  var recipient_id = $("#recipient_id").val();
  $("#save_data_address_recipients").attr("disabled", true);
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/courier/add_address_recipients_ajax.php?recipient=" + recipient_id,
    data: parametros,
    success: function (response) {
      if (response.status === "success") {
        Swal.fire({
          type: "success",
          title: message_error_form80,
          icon: "success",
          showConfirmButton: false,
          timer: 1500,
        }).then(() => {
          $("#save_data_address_recipients").attr("disabled", false);
          $(".resultados_ajax_add_user_modal_recipient").html(response.data);
          $("#myModalAddRecipientAddresses").modal("hide");

          var data_address = {
            id: response.customer_address.id_addresses,
            text: response.customer_address.address,
          };

          var newOptionAddress = new Option(data_address.text, data_address.id, false, false);

          $("#recipient_address_id").append(newOptionAddress).trigger("change");
          $("#recipient_address_id").val(data_address.id).trigger("change");

          window.setTimeout(function () {
            $(".alert")
              .fadeTo(500, 0)
              .slideUp(500, function () {
                $(this).remove();
              });
          }, 5000);
        });
      } else {
        Swal.fire({
          type: "error",
          title: "Oops...",
          text: response.message,
          icon: "error",
          confirmButtonColor: "#336aea",
        });
        $("#save_data_address_recipients").attr("disabled", false);
      }
    },
    error: function () {
      Swal.fire({
        type: "error",
        title: "Oops...",
        text: message_error_form19,
        icon: "error",
        confirmButtonColor: "#336aea",
      });
      $("#save_data_address_recipients").attr("disabled", false);
    },
  });
});

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

var input_recipient = document.querySelector("#phone_custom_recipient");
var iti = window.intlTelInput(input_recipient, {
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

// on blur: validate
input_recipient.addEventListener("blur", function () {
  reset();
  if (input_recipient.value.trim()) {
    if (iti.isValidNumber()) {
      $("#phone_recipient").val(iti.getNumber());

      validMsg.classList.remove("hide");
    } else {
      input_recipient.classList.add("error");
      var errorCode = iti.getValidationError();
      errorMsg.innerHTML = errorMap[errorCode];
      errorMsg.classList.remove("hide");
    }
  }
});

// on keyup / change flag: reset
input_recipient.addEventListener("change", reset);
input_recipient.addEventListener("keyup", reset);

function isNumberKey(evt, element) {
  var charCode = evt.which ? evt.which : event.keyCode;
  if (charCode > 31 && (charCode < 48 || charCode > 57) && !(charCode == 46 || charCode == 8)) return false;
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
