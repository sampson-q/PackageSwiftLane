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
		url: './ajax/tools/payment_gateways/setting_method_list_ajax.php',
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (data) {
			$(".outer_div").html(data).fadeIn('slow');
		}
	})
}



//update payment cash
$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data_cash").submit(function(event) {
        event.preventDefault();

        // Obtén los valores de los campos
        var name_pay = $('#name_pay').val(); 
        var detail_pay = $('#detail_pay').val();
        var is_active = $('#is_active').val();
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
            url: "./ajax/tools/payment_gateways/setting_payment_cash_list_ajax.php",
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
                        window.location.href = 'payment_mode_list.php';
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




//update payment paypal
$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data_paypal").submit(function(event) {
        event.preventDefault();



        // Obtén los valores de los campos
        var name_pay = $('#name_pay').val(); 
        var detail_pay = $('#detail_pay').val();
        var paypal_client_id = $('#paypal_client_id').val();
        var is_active = $('#is_active').val();
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
            url: "./ajax/tools/payment_gateways/setting_payment_paypal_list_ajax.php",
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
                        window.location.href = 'payment_mode_list.php';
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






//update payment stripe
$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data_stripe").submit(function(event) {
        event.preventDefault();



        // Obtén los valores de los campos
        var name_pay = $('#name_pay').val(); 
        var detail_pay = $('#detail_pay').val();
        var public_key = $('#public_key').val();
        var secret_key = $('#secret_key').val();
        var is_active = $('#is_active').val();
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
            url: "./ajax/tools/payment_gateways/setting_payment_stripe_list_ajax.php",
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
                        window.location.href = 'payment_mode_list.php';
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




//update payment paystack
$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data_paystack").submit(function(event) {
        event.preventDefault();



        // Obtén los valores de los campos
        var name_pay = $('#name_pay').val(); 
        var detail_pay = $('#detail_pay').val();
        var public_key = $('#public_key').val();
        var secret_key = $('#secret_key').val();
        var is_active = $('#is_active').val();
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
            url: "./ajax/tools/payment_gateways/setting_payment_paystack_list_ajax.php",
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
                        window.location.href = 'payment_mode_list.php';
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



//update payment paystack
$(document).ready(function() {
    // Cuando cambia el contenido del campo
    $('.required').on('input', function() {
        // Agrega o quita la clase 'highlight' según si el campo está vacío o no
        $(this).toggleClass('highlight', $(this).val() === '');
    });

    $("#update_data_wire").submit(function(event) {
        event.preventDefault();



        // Obtén los valores de los campos
        var name_pay = $('#name_pay').val(); 
        var detail_pay = $('#detail_pay').val();
        var is_active = $('#is_active').val();
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
            url: "./ajax/tools/payment_gateways/setting_payment_wire_list_ajax.php",
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
                        window.location.href = 'payment_mode_list.php';
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




$(".bt-switch input[type='checkbox'], .bt-switch input[type='radio']").bootstrapSwitch();
var radioswitch = function () {
	var bt = function () {
		$(".radio-switch").on("switch-change", function () {
			$(".radio-switch").bootstrapSwitch("toggleRadioState")
		}), $(".radio-switch").on("switch-change", function () {
			$(".radio-switch").bootstrapSwitch("toggleRadioStateAllowUncheck")
		}), $(".radio-switch").on("switch-change", function () {
			$(".radio-switch").bootstrapSwitch("toggleRadioStateAllowUncheck", !1)
		})
	};
	return {
		init: function () {
			bt()
		}
	}
}();
$(document).ready(function () {
	radioswitch.init()
});
