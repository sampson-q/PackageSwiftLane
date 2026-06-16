"use strict";


$(function () {
    var storedPerPage = localStorage.getItem('currentPerPage-Customers');
    if(storedPerPage) {
        $("#per_page").val(storedPerPage);
    }
    cdp_load(1);
});


//Cargar datos AJAX
function cdp_load(page) {
    localStorage.setItem('currentTablePage-Customers', page);
    var search = $("#search").val();
    var filterby_active = $("#filterby_active").val();
    var filterby_approve = $("#filterby_approve").val();
    var filterby_new = $("#filterby_new").val();
    var per_page = $("#per_page").val();

    localStorage.setItem('currentPerPage-Customers', per_page);

    var parametros = {
        "page": page,
        'search': search,
        'filterby_active': filterby_active,
        'filterby_approve': filterby_approve,
        'filterby_new': filterby_new,
        'per_page': per_page
    };

    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/customers/customers_list_ajax.php',
        data: parametros,
        beforeSend: function (objeto) {},
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
        }
    });
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
                        url: './ajax/customers/customers_delete_ajax.php',
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
                        cdp_load(localStorage.getItem('currentTablePage-Customers'));
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


//Registro de datos
$("#save_user").on('submit', function (event) {
	$('#save_data').attr("disabled", true);
	var inputFileImage = document.getElementById("avatar");
	var username = $('#username').val();
	var email = $('#email').val();
	var fname = $('#fname').val();
	var lname = $('#lname').val();
	var country = $('#country').val();
	var city = $('#city').val();
	var postal = $('#postal').val();
	var notes = $('#notes').val();
	var code_phone = $('#code_phone').val();
	var phone = $('#phone').val();
	var address = $('#address').val();
	var gender = $('#gender').val();
	var locker = $('#locker').val();
	var password = $('#password').val();
	var notify = $('#notify:checked').val();
	var active = $('input:radio[name=active]:checked').val();
	var newsletter = $('input:radio[name=newsletter]:checked').val();


	var file = inputFileImage.files[0];
	var data = new FormData();

	data.append('avatar', file);
	data.append('locker', locker);
	data.append('username', username);
	data.append('password', password);
	data.append('fname', fname);
	data.append('lname', lname);
	data.append('email', email);
	data.append('address', address);
	data.append('code_phone', code_phone);
	data.append('phone', phone);
	data.append('gender', gender);
	data.append('country', country);
	data.append('city', city);
	data.append('postal', postal);
	data.append('active', active);
	data.append('newsletter', newsletter);
	data.append('notes', notes);
	data.append('notify', notify);
	$.ajax({
		type: "POST",
		url: "ajax/customers/customers_add_ajax.php",
		data: data,
		contentType: false,       // The content type used when sending data to the server.
		cache: false,             // To unable request pages to be cached
		processData: false,
		beforeSend: function (objeto) {
			$("#resultados_ajax").html("Please wait...");
		},
		success: function (datos) {
			$("#resultados_ajax").html(datos);
			$('#save_data').attr("disabled", false);

			$('html, body').animate({
				scrollTop: 0
			}, 600);


		}
	});
	event.preventDefault();

})

$("#edit_user").on('submit', function (event) {
	$('#save_data').attr("disabled", true);
	var inputFileImage = document.getElementById("avatar");
	var id = $('#id').val();
	var email = $('#email').val();
	var fname = $('#fname').val();
	var lname = $('#lname').val();
	var country = $('#country').val();
	var city = $('#city').val();
	var postal = $('#postal').val();
	// var newsletter = $('#newsletter').val();
	var notes = $('#notes').val();
	var code_phone = $('#code_phone').val();
	var phone = $('#phone').val();
	var address = $('#address').val();
	var gender = $('#gender').val();
	// var userlevel = $('#userlevel').val();
	// var active = $('#active').val();
	var password = $('#password').val();
	// var notify = $('#notify').val();
	var active = $('input:radio[name=active]:checked').val();
	var newsletter = $('input:radio[name=newsletter]:checked').val();


	var file = inputFileImage.files[0];
	var data = new FormData();

	data.append('avatar', file);
	data.append('password', password);
	data.append('fname', fname);
	data.append('lname', lname);
	data.append('email', email);
	data.append('address', address);
	data.append('code_phone', code_phone);
	data.append('phone', phone);
	data.append('gender', gender);
	data.append('country', country);
	data.append('city', city);
	data.append('postal', postal);
	// data.append('userlevel',userlevel);	
	data.append('active', active);
	data.append('newsletter', newsletter);
	data.append('notes', notes);
	data.append('id', id);
	// data.append('notify',notify);	
	$.ajax({
		type: "POST",
		url: "ajax/customers/customers_edit_ajax.php",
		data: data,
		contentType: false,       // The content type used when sending data to the server.
		cache: false,             // To unable request pages to be cached
		processData: false,
		beforeSend: function (objeto) {
			$("#resultados_ajax").html("Enviando...");
		},
		success: function (datos) {
			$("#resultados_ajax").html(datos);
			$('#save_data').attr("disabled", false);

			$('html, body').animate({
				scrollTop: 0
			}, 600);

			// window.setTimeout(function() {
			// $(".alert").fadeTo(500, 0).slideUp(500, function(){
			// $(this).remove();});}, 5000);				
		}
	});
	event.preventDefault();
})

$(document).ready(function() {
    $(document).on('click', '#activateUserBtn', function(e) {
        var id = $(this).data('id');
        updateStatusActive(id); // Pass both id and stat
        e.preventDefault();
    });
});

function updateStatusActive(id) {
    $.ajax({
        type: "POST",
        url: "ajax/customers/customers_status_ajax.php",  // Ensure this URL points to the correct PHP file
        data: { id: id, stat: 1 }, // Send id and stat as an object
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                location.reload();
            } else {
                $("#resultados_ajax").html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $("#resultados_ajax").html('<div class="alert alert-danger">An error occurred while processing the request.</div>');
        }
    });
}


$(document).ready(function() {
    $(document).on('click', '#deactivateUserBtn', function(e) {
        var id = $(this).data('id');
        updateStatusInActive(id); // Pass both id and stat
        e.preventDefault();
    });
});

function updateStatusInActive(id) {
    $.ajax({
        type: "POST",
        url: "ajax/customers/customers_status_ajax.php",
        data: { id: id, stat: 0 },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // location.reload();
                cdp_load(localStorage.getItem('currentTablePage-Customers'));
            } else {
                $("#resultados_ajax").html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $("#resultados_ajax").html('<div class="alert alert-danger">An error occurred while processing the request.</div>');
        }
    });
}

$(document).ready(function() {
    $(document).on('click', '.approveUserBtn', function(e) {
        var id = $(this).data('id');
        approveUser(id); // Pass the id for approval
        e.preventDefault();
    });
});

function approveUser(id) {
    $.ajax({
        type: "POST",
        url: "ajax/customers/customers_status_ajax.php",
        data: { id: id, approve: 1 },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // location.reload();
                cdp_load(localStorage.getItem('currentTablePage-Customers'));
            } else {
                $("#resultados_ajax").html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $("#resultados_ajax").html('<div class="alert alert-danger">An error occurred while processing the request.</div>');
        }
    });
}

// Toggle "new users (last 30 days)" filter from stat card
var filterCardMap = {
    'active':     { hidden: 'filterby_active',  val: '1', card: 'card-active-users',    badge: 'filter-badge-active' },
    'inactive':   { hidden: 'filterby_active',  val: '2', card: 'card-inactive-users',  badge: 'filter-badge-inactive' },
    'approved':   { hidden: 'filterby_approve', val: '3', card: 'card-approved-users',  badge: 'filter-badge-approved' },
    'unapproved': { hidden: 'filterby_approve', val: '4', card: 'card-unapproved-users',badge: 'filter-badge-unapproved' }
};

function cdp_toggleFilter(type) {
    var cfg = filterCardMap[type];
    var current = $('#' + cfg.hidden).val();
    var isActive = (current === cfg.val);

    // If this filter is already on → turn it off
    if (isActive) {
        $('#' + cfg.hidden).val('0');
        $('#' + cfg.card).removeClass('filter-active');
        $('#' + cfg.badge).hide();
    } else {
        // If the sibling card on the same hidden input is active, deactivate it first
        if (type === 'active' && current === '2')   { _deactivateCard('inactive'); }
        if (type === 'inactive' && current === '1') { _deactivateCard('active'); }
        if (type === 'approved' && current === '4') { _deactivateCard('unapproved'); }
        if (type === 'unapproved' && current === '3'){ _deactivateCard('approved'); }

        $('#' + cfg.hidden).val(cfg.val);
        $('#' + cfg.card).addClass('filter-active');
        $('#' + cfg.badge).show();
    }
    cdp_load(1);
}

function _deactivateCard(type) {
    var cfg = filterCardMap[type];
    $('#' + cfg.card).removeClass('filter-active');
    $('#' + cfg.badge).hide();
}

function cdp_toggleNewFilter() {
    var isActive = $('#filterby_new').val() === '1';

    if (isActive) {
        // Turn off new-users filter, unlock all cards
        $('#filterby_new').val('0');
        $('#card-new-users').removeClass('filter-active');
        $('#new-filter-badge').hide();
        $('.stat-card-clickable').not('#card-new-users').removeClass('filter-locked');
    } else {
        // Turn on new-users filter — reset & lock all other card filters
        $('#filterby_active').val('0');
        $('#filterby_approve').val('0');
        Object.keys(filterCardMap).forEach(function(type) {
            _deactivateCard(type);
        });
        $('#filterby_new').val('1');
        $('#card-new-users').addClass('filter-active');
        $('#new-filter-badge').show();
        // Lock the other four cards visually
        $('.stat-card-clickable').not('#card-new-users').addClass('filter-locked');
    }
    cdp_load(1);
}