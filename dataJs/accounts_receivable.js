"use strict";


$(function () {



	cdp_select2_init();

	var start = moment().startOf('month');
	var end = moment().endOf('month');

	$('#daterange').daterangepicker({
		startDate: start,
		endDate: end,
		locale: {
			'format': 'Y/M/D',
			"separator": " - ",
			"applyLabel": range_calendar_text17,
			"cancelLabel": range_calendar_text16,
			"fromLabel": range_calendar_text14,
			"toLabel": range_calendar_text15,
			"customRangeLabel": range_calendar_text13,
			"daysOfWeek": [
				range_calendar_text24,
				range_calendar_text25,
				range_calendar_text26,
				range_calendar_text27,
				range_calendar_text28,
				range_calendar_text29,
				range_calendar_text30,
			],
			"monthNames": [
				range_calendar_text1,
				range_calendar_text2,
				range_calendar_text3,
				range_calendar_text4,
				range_calendar_text5,
				range_calendar_text6,
				range_calendar_text7,
				range_calendar_text8,
				range_calendar_text9,
				range_calendar_text10,
				range_calendar_text11,
				range_calendar_text12,
			],
			"firstDay": 1
		},
		ranges: {
			[range_calendar_text18]: [moment(), moment()],
			[range_calendar_text19]: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
			[range_calendar_text20]: [moment().subtract(6, 'days'), moment()],
			[range_calendar_text21]: [moment().subtract(29, 'days'), moment()],
			[range_calendar_text22]: [moment().startOf('month'), moment().endOf('month')],
			[range_calendar_text23]: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
		}
	}).on('change', function (e) {
		cdp_load(1);
	});

	cdp_load(1);

});




//Cargar datos AJAX
function cdp_load(page) {
	var search = $("#search").val();
	var agency_courier = $("#agency_courier").val();
	var customer_id = $("#customer_id").val();
	var daterange = $("#daterange").val();
	var parametros = { "page": page, 'search': search, 'agency_courier': agency_courier, 'customer_id': customer_id, 'range': daterange };
	$("#loader").fadeIn('slow');
	$.ajax({
		url: './ajax/accounts_receivable/accounts_receivable_ajax.php',
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (data) {
			$(".outer_div").html(data).fadeIn('slow');
		}
	})
}


//cdp_eliminar
function cdp_delete_charge(id) {

	var parent = $('#item_' + id).parent().parent();
	var name = $(this).attr('data-rel');
	new Messi('<p class="messi-warning"><i class="icon-warning-sign icon-3x pull-left"></i>' + message_delete_confirm + '<br /><strong>' + message_delete_confirm2 + '</strong></p>', {
		title: message_delete_confirm1,
		titleClass: '',
		modal: true,
		closeButton: true,
		buttons: [{
			id: 0,
			label: message_delete_confirm1,
			class: '',
			val: 'Y'
		}],
		callback: function (val) {
			if (val === 'Y') {
				$.ajax({
					type: 'post',
					url: './ajax/accounts_receivable/charge_delete_ajax.php',
					data: {
						'id': id,
					},
					beforeSend: function () {
						parent.animate({
							'backgroundColor': '#FFBFBF'
						}, 400);
					},
					success: function (data) {

						$('html, body').animate({
							scrollTop: 0
						}, 600);
						$('.resultados_ajax_charges_add_results').html(data);
						cdp_load_charges();

						cdp_load(1);
					}
				});
			}
		}

	});
}




function cdp_select2_init() {

	$(".select2").select2({
		ajax: {
			url: "ajax/customers_select2.php",
			dataType: 'json',

			delay: 250,
			data: function (params) {
				return {
					q: params.term // search term
				};
			},
			processResults: function (data) {
				// parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to
				// alter the remote JSON data
				return {
					results: data
				};
			},
			cache: true
		},
		minimumInputLength: 2,
		placeholder: search_sender,
		allowClear: true
	}).on('change', function (e) {
		cdp_load(1);
	});
}

$('#charges_list').on('show.bs.modal', function (event) {
	var button = $(event.relatedTarget) // Button that triggered the modal
	var id = button.data('id') // Extract info from data-* attributes
	$('#order_id').val(id);
	$(".resultados_ajax_charges_add_results").html('');
	cdp_load_charges(order_id);//Cargas los pagos	
})

function cdp_load_charges() {

	var id = $('#order_id').val();
	var parametros = { "id": id };
	$.ajax({
		url: 'ajax/accounts_receivable/charges_list_ajax.php',
		data: parametros,
		success: function (data) {
			$(".resultados_ajax_charges_list").html(data).fadeIn('slow');
		}
	});
}


$('#charges_add').on('show.bs.modal', function (event) {

	var id = $('#order_id').val();
	var parametros = { "id": id };
	$.ajax({
		url: 'ajax/accounts_receivable/modal_add_charges.php',
		data: parametros,
		success: function (data) {
			$(".resultados_ajax_add_modal").html(data).fadeIn('slow');
		}
	});
})





$("#add_charges").on('submit', function (event) {
	var parametros = $(this).serialize();

	$.ajax({
		type: "POST",
		url: "ajax/accounts_receivable/add_charges_ajax.php",
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (datos) {
			$(".resultados_ajax_charges_add_results").html(datos);

			$('#charges_add').modal('hide');
			cdp_load_charges();
			cdp_load(1);


		}
	});
	event.preventDefault();

})



$('#charges_edit').on('show.bs.modal', function (event) {

	var id = $('#order_id').val();

	var button = $(event.relatedTarget) // Button that triggered the modal
	var id_charge = button.data('id_charge')

	var parametros = { "id": id, 'id_charge': id_charge };

	$.ajax({
		url: 'ajax/accounts_receivable/modal_edit_charges.php',
		data: parametros,
		success: function (data) {
			$(".resultados_ajax_add_modal_edit").html(data).fadeIn('slow');
		}
	});
})


$("#edit_charges").on('submit', function (event) {
	var parametros = $(this).serialize();

	$.ajax({
		type: "POST",
		url: "ajax/accounts_receivable/update_charges_ajax.php",
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (datos) {
			$(".resultados_ajax_charges_add_results").html(datos);
			$('#charges_edit').modal('hide');
			cdp_load_charges();
			cdp_load(1);
		}
	});
	event.preventDefault();

})