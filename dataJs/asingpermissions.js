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
		url: './ajax/tools/permissions/asingpermissions_list_ajax.php',
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

function cdp_eliminar(role_id) {
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
                        url: './ajax/tools/permissions/asingpermissions_delete_ajax.php',
                        type: 'POST',
                        data: {
                            'id': role_id,
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



// ASING MODULE PERMISSIONS
$(document).ready(function () {
    $("#save_data").submit(function (event) {
        event.preventDefault();

        // Obtener valores del formulario
        const roleName = $("#role_name").val();
        const description = $("#description").val();
        const permissions = $("input[name='permissions[]']:checked").map(function () {
            return this.value;
        }).get();

        // Validar campos obligatorios
        if (!roleName || !description) {
            Swal.fire({
                icon: "error",
                title: message_error_moduleroles11,
                text: message_error_moduleroles10,
            });
            return;
        }

        if (permissions.length === 0) {
            Swal.fire({
                icon: "error",
                title: message_error_moduleroles12,
                text: message_error_moduleroles13,
            });
            return;
        }

        // Configurar datos para enviar
        const formData = {
            role_name: roleName,
            description: description,
            permissions: permissions,
        };

        // Realizar solicitud AJAX
        $.ajax({
            url: "ajax/tools/permissions/asingpermissions_add_ajax.php",
            type: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    Swal.fire({
                        icon: "success",
                        title: message_error_form15,
                        text: message_error_moduleroles14,
                    }).then(() => {
                        window.location.href = "permissions_list.php";
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: response.message,
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: message_error_form19,
                });
            },
        });
    });
});




// ASING MODULE PERMISSIONS UPDATE
$("#update_data").submit(function (event) {
    event.preventDefault();

    // Obtener valores del formulario
    const roleId = $("#role_id").val(); // Agregar el ID del rol
    const roleName = $("#role_name").val();
    const description = $("#description").val();
    const permissions = $("input[name='permissions[]']:checked")
        .map(function () {
            return this.value;
        })
        .get();

    // Validar campos obligatorios
    if (!roleName || !description) {
        Swal.fire({
            icon: "error",
            title: message_error_moduleroles11,
            text: message_error_moduleroles10,
        });
        return;
    }

    // Permisos vacíos - Avisar al usuario
    if (permissions.length === 0) {
        Swal.fire({
            icon: "error",
            title: message_error_moduleroles12,
            text: message_error_moduleroles13,
        });
        return;
    }

    // Configurar datos para enviar
    const formData = {
        role_id: roleId, // Incluye el role_id
        role_name: roleName,
        description: description,
        permissions: permissions,
    };

    // Enviar datos al servidor
    $.ajax({
        url: "ajax/tools/permissions/asingpermissions_update_ajax.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function (response) {
            // Validar la respuesta del servidor
            if (response.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: "¡Éxito!",
                    text: "Rol y permisos actualizados correctamente.",
                }).then(() => {
                    // Redireccionar a la lista de permisos
                    window.location.href = "permissions_list.php";
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: response.message || "No se pudo actualizar el rol.",
                });
            }
        },
        error: function (xhr, status, error) {
            // Manejo de errores del servidor
            Swal.fire({
                icon: "error",
                title: "Error del servidor",
                text: "Ocurrió un problema al actualizar los datos.",
            });
        },
    });
});


//SELECT ALL PERMISSSIONS EXPAND
 document.querySelectorAll('.toggle-actions').forEach(button => {
    button.addEventListener('click', function () {
        const moduleId = this.dataset.moduleId;
        const hiddenActions = document.querySelectorAll(`.hidden-action.module-${moduleId}`);
        
        // Cambia la clase para mostrar/ocultar
        hiddenActions.forEach(action => action.classList.toggle('show'));
        
        // Cambia el texto del botón
        this.textContent = this.textContent === message_error_moduleroles15 
            ? message_error_moduleroles16 
            : message_error_moduleroles15;

        console.log(`Toggled actions for module ${moduleId}`);
    });
});

function toggleAll(selectAllCheckbox, groupClass) {
    const checkboxes = document.querySelectorAll(`.${groupClass}`);
    checkboxes.forEach(checkbox => {
        if (!checkbox.disabled) {
            checkbox.checked = selectAllCheckbox.checked;
        }
    });
}
