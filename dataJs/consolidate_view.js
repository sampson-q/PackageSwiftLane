"use strict";



$("#driver_update").on('submit', function (event) {
  var parametros = $(this).serialize();

  $.ajax({
    type: "POST",
    url: "ajax/consolidate/consolidate_update_driver_ajax.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("<img src='assets/images/loader.gif'/><br/>Wait a moment please...");
    },
    success: function (datos) {
      $("#resultados_ajax").html(datos);

      $('html, body').animate({
        scrollTop: 0
      }, 600);

      $('#modalDriver').modal('hide');

      cdp_load(1);


    }
  });
  event.preventDefault();

})


$('#modalDriver').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget)
  var id_shipment = button.data('id_shipment')
  var id_sender = button.data('id_sender')
  var modal = $(this)
  $('#id_shipment').val(id_shipment)
  $('#id_senderclient_driver_update').val(id_sender)
})


$("#send_email").on('submit', function (event) {

  $('#guardar_datos').attr("disabled", true);

  var parametros = $(this).serialize();
  $.ajax({
    type: "GET",
    url: "send_email_pdf_consolidate.php",
    data: parametros,
    beforeSend: function (objeto) {
      $(".resultados_ajax_mail").html("<img src='assets/images/loader.gif'/><br/>Wait a moment please...");
    },
    success: function (datos) {
      $(".resultados_ajax_mail").html(datos);
      $('#guardar_datos').attr("disabled", false);

    }
  });
  event.preventDefault();

})

$('#myModal').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget)
  var order = button.data('order')
  var id = button.data('id')
  var email = button.data('email')
  var modal = $(this)
  $('#subject').val("#" + order)
  $('#id').val(id)
  $('#sendto').val(email)
})



$('#detail_payment_packages').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget)
  var id = button.data('id')
  var customer = button.data('customer')

  $('#order_id_confirm_payment').val(id);
  $('#customer_id_confirm_payment').val(customer);

  $(".resultados_ajax_payment_data").html('');

  cdp_load_payment_detail(id);

})

function cdp_load_payment_detail(id) {

  var parametros = { "id": id };
  $.ajax({

    url: 'ajax/consolidate/consolidate_payment_detail_ajax.php',
    data: parametros,
    success: function (data) {
      $(".resultados_ajax_payment_data").html(data).fadeIn('slow');
    }
  });
}


$("#send_payment").on('submit', function (event) {

  $('#save_payment').attr("disabled", true);

  var parametros = $(this).serialize();
  $.ajax({
    type: "POST",
    url: "ajax/consolidate/consolidate_confirm_payment.php",
    data: parametros,
    beforeSend: function (objeto) {
      $("#resultados_ajax").html("load...");
    },
    success: function (datos) {

      $('#detail_payment_packages').modal('hide');

      $("#resultados_ajax").html(datos);
      $('#save_payment').attr("disabled", false);

      setTimeout('document.location.recdp_load()', 3000);


      cdp_load(1);

    }
  });
  event.preventDefault();

})

document.getElementById('export-order-btn').addEventListener('click', function (ev) {
    const card = ev.currentTarget.closest('.card');
    if (!card) return alert('Card not found.');

    const tables = Array.from(card.querySelectorAll('table'));
    if (tables.length === 0) return alert('No tables found in this card.');

    let doc = '<!DOCTYPE html><html><head>';
    doc += '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    doc += '<style>td,th{border:1px solid #000;padding:4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;} table{border-collapse:collapse;}</style>';
    doc += '</head><body>';

    tables.forEach(tbl => {
        const clone = tbl.cloneNode(true);

        // Reveal all hidden rows so the export always contains the full dataset
        clone.querySelectorAll('tbody tr').forEach(function (tr) {
            tr.style.display = '';
        });

        clone.querySelectorAll('p').forEach(p => {
            const br = document.createElement('span');
            br.innerHTML = p.innerHTML + '<br/>';
            p.parentNode.replaceChild(br, p);
        });

        doc += clone.outerHTML;
        doc += '<div style="height:10px;"></div>';
    });

    doc += '</body></html>';

    const blob = new Blob(["\uFEFF", doc], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const orderId = card.dataset.orderId || ('order_' + Date.now());
    a.download = orderId + '.xls';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
});

function cdp_load(page) {
    var per_page = $("#filter_rows").val();

    if (!per_page || per_page === "0") per_page = 10;
    if (per_page === "show_all")       per_page = 99999;

    var parametros = {
        page:     page,
        per_page: parseInt(per_page, 10),
        id:       cdp_consolidate_id
    };

    $("#tabla-items tbody").css("opacity", "0.4");

    $.ajax({
        type: "GET",
        url:  "ajax/consolidate/consolidate_items_ajax.php",
        data: parametros,
        success: function (data) {
            $("#tabla-items tbody").css("opacity", "1").html(data);
        }
    });
}

function cdp_filterByRows() {
    if ($("#filter_rows").val() === "0") return;
    cdp_load(1);
}