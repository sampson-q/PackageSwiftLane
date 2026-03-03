"use strict";


$(function () {
    cdp_load(1);

});


//Cargar datos AJAX
function cdp_load(page) {
    var search = $("#search").val();
    var parametros = { "page": page, 'search': search };
    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/users/users_list_ajax.php',
        data: parametros,
        beforeSend: function (objeto) {
        },
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
        }
    })
}



//AJAX sweetalert2 borrar admin

$(document).ready(function() {
    $(document).on('click', '#item_', function(e) {
        var id = $(this).data('id');
        cdp_eliminar(id);
        e.preventDefault();
    });
});

function cdp_eliminar(id) {
    swal({
        title: message_delete_confirm,
        text: message_delete_confirm2,
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#336aea',
        cancelButtonColor: '#eb644c',
        confirmButtonText: message_delete_confirm1,
        showLoaderOnConfirm: true,

        preConfirm: function() {
            return new Promise(function(resolve) {
                $.ajax({
                        url: './ajax/users/users_delete_ajax.php',
                        type: 'POST',
                        data: {
                            'id': id,
                        },
                        dataType: 'json'
                    })
                    .done(function(response) {
                        swal(response.message, message_delete_error2, response.status);
                        $('html, body').animate({
                            scrollTop: 0
                        }, 600);
                        $('#resultados_ajax').html(response);
                        cdp_load(1);
                    })
                    .fail(function() {
                        swal('Oops...', message_delete_error, 'error');
                    });
            });
        },
        allowOutsideClick: false
    });
}



//AJAX sweetalert2 borrar driver

$(document).ready(function() {
    $(document).on('click', '#itemdriver_', function(e) {
        var id = $(this).data('id');
        cdp_eliminar_driver(id);
        e.preventDefault();
    });
});

function cdp_eliminar_driver(id) {
    swal({
        title: message_delete_confirm,
        text: message_delete_confirm2,
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#336aea',
        cancelButtonColor: '#eb644c',
        confirmButtonText: message_delete_confirm1,
        showLoaderOnConfirm: true,

        preConfirm: function() {
            return new Promise(function(resolve) {
                $.ajax({
                        url: './ajax/drivers/drivers_delete_ajax.php',
                        type: 'POST',
                        data: {
                            'id': id,
                        },
                        dataType: 'json'
                    })
                    .done(function(response) {
                        swal(response.message, message_delete_error2, response.status);
                        $('html, body').animate({
                            scrollTop: 0
                        }, 600);
                        $('#resultados_ajax').html(response);
                        cdp_load(1);
                    })
                    .fail(function() {
                        swal('Oops...', message_delete_error, 'error');
                    });
            });
        },
        allowOutsideClick: false
    });
}











