"use strict";

$(function () {
  $("#t_date").datepicker({
    format: "yyyy-mm-dd",
    autoclose: true,
  });
});

$("#invoice_form").on("submit", function (event) {
  event.preventDefault(); // Prevent the form from submitting normally

  var data = $(this).serialize();
  $.ajax({
    type: "POST",
    url: "ajax/customers_packages/add_customers_packages_tracking.php",
    data: data,
    dataType: "json",
    cache: false, // To disable request pages from being cached
    beforeSend: function () {
      $("#create_invoice").attr("disabled", true);
      Swal.fire({
        title: message_loading,
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });
    },
    success: function (data) {
      $("#create_invoice").attr("disabled", false);
      if (data.success) {
        cdp_showSuccess(data.messages, data.package_id);
      } else {
        cdp_showError(data.errors);
      }
    }
  });
});

function cdp_showError(errors) {
  var html_code = "<ul class='error'>";
  for (var i = 0; i < errors.length; i++) {
    html_code += '<li class="text-left">';
    html_code += errors[i];
    html_code += "</li>";
  }
  html_code += "</ul>"; // Corrected the closing tag

  Swal.fire({
    title: message_error,
    html: html_code,
    icon: "error",
    allowOutsideClick: false,
    confirmButtonText: "Ok",
  });
}

function cdp_showSuccess(messages, package_id) {
  Swal.fire({
    title: messages,
    icon: 'success',
    allowOutsideClick: false,
    confirmButtonText: 'Ok'
  }).then((result) => {
    if (result.isConfirmed) {
      setTimeout(function () {
        window.location = "customer_packages_view.php?id=" + package_id;
      }, 2000);
    }
  });
}