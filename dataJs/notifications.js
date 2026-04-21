"use strict";

$(function () {
  cdp_load(1);
});

//Cargar datos AJAX
function cdp_load(page) {
  $.ajax({
    url: "./ajax/notifications_list_ajax.php",
    data: { page: page },
    beforeSend: function (objeto) {},
    success: function (data) {
      $(".outer_div").html(data).fadeIn("slow");
    },
  });
}


function cdp_updateNotificationsRead() {
    var name = $(this).attr('data-rel');
    
    Swal.fire({
        title: 'Update Notifications',
        html: '<p><i class="icon-warning-sign icon-3x pull-left"></i>Are you sure to mark all notifications as readed?</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Update',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                $.ajax({
                    type: 'post',
                    url: './ajax/notifications_update_read_ajax.php',
                    data: (function () {
                        var token = $('meta[name="csrf-token"]').attr('content') || '';
                        var param = $('meta[name="csrf-param"]').attr('content') || '_csrf_token';
                        var payload = {};
                        if (token) {
                            payload[param] = token;
                        }
                        return payload;
                    })(),
                    success: function(data) {
                        $('html, body').animate({
                            scrollTop: 0
                        }, 600);
                        $('#resultados_ajax').html(data);
                        cdp_load(1); // Recargar el contenido
                        resolve();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error: ' + status + error);
                    }
                });
            });
        }
    });
}

