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
		url: './ajax/tools/packaging/packaging_list_ajax.php',
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (data) {
			$(".outer_div").html(data).fadeIn('slow');
		}
	})
}




//AJAX sweetalert2 borrar ID

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
                        url: './ajax/tools/packaging/packaging_delete_ajax.php',
                        type: 'POST',
                        data: {
                            'id': id,
                        },
                        dataType: 'json'
                    })
                    .done(function(response) {
				    if (response.status === 'success') {
				        // Eliminación exitosa
				        swal(response.message, message_delete_error2, response.status);
				        $('html, body').animate({
				            scrollTop: 0
				        }, 600);
				        $('#resultados_ajax').html(response);
				        cdp_load(1);
				    } else if (response.status === 'error1') {
				        // Restricciones de integridad referencial
				        swal('Oops...', response.message, 'info');
				    } else {
				        // Otro tipo de error
				        swal('Oops...', message_delete_error, 'error');
				    }
				})
				.fail(function() {
				    // Error de conexión u otro error
				    swal('Oops...', message_delete_error, 'error');
				});

            });
        },
        allowOutsideClick: false
    });
}



// AJAX sweetalert2 guardar

$(document).ready(function() {
    $("#save_data").submit(function(event) {
        event.preventDefault();


        // Obtén los valores de los campos
        var name_pack = $('#name_pack').val(); 
        var detail_pack = $('#detail_pack').val();



       // Almacena los nombres de los campos faltantes
        var missingFields = [];

        // Verifica si los campos obligatorios están vacíos y guarda los nombres en el array
        if (!name_pack) missingFields.push(message_error_form35);
        if (!detail_pack) missingFields.push(message_error_form36);



         // Verifica si hay campos faltantes
        if (missingFields.length > 0) {
            // Construye el mensaje de alerta
            var alertMessage = message_error_form5;
            alertMessage += '\n\n- ' + missingFields.join('\n- ');

            // Muestra el mensaje de alerta
            Swal.fire({
                type: 'error',
                title: message_error_form1,
                text: alertMessage,
                confirmButtonColor: '#336aea',
                showConfirmButton: true,
            });

        } else {

        // Configurar objeto FormData
        var data = new FormData(this);

      

        // Realizar la solicitud AJAX
        $.ajax({
            url: "ajax/tools/packaging/packaging_add_ajax.php",
            type: 'POST',
            data: data,
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
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
            success: function(response) {
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
                        window.location.href = 'packaging_list.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form18,
                        text: response.message || message_error_form17,
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function() {
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

    });
    
});




$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data").submit(function(event) {
        event.preventDefault();

        // Obtén los valores de los campos
        var name_pack = $('#name_pack').val(); 
        var detail_pack = $('#detail_pack').val();
        var id = $('#id').val();

        // Configurar objeto FormData
        var data = new FormData(this);


        // Validar campos requeridos
		var camposVacios = [];
		$('.required').each(function() {
		    if ($(this).val() === '') {
		        camposVacios.push($(this).attr('id'));
		    }
		});

		// Remover la clase 'highlight' de todos los campos
		$('.required').removeClass('highlight');

		if (camposVacios.length > 0) {
		    // Muestra un mensaje de error con SweetAlert
		    Swal.fire({
		        type: 'error',
		        title: message_error_form21,
		        text: message_error_form22,
		        confirmButtonColor: '#336aea',
		        showConfirmButton: true,
		    });

		    // Resalta los campos vacíos
		    camposVacios.forEach(function(campo) {
		        $('#' + campo).addClass('highlight');
		    });

		    // Detiene el envío del formulario
		    return;
		}

        // Realizar la solicitud AJAX
        $.ajax({
            url: "./ajax/tools/packaging/packaging_edit_ajax.php",
            type: 'POST',
            data: data,
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
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
            success: function(response) {
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
                        window.location.href = 'packaging_list.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form19,
                        text: response.message || message_error_form17,
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function() {
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
    });
});



