"use strict";


$(function () {
	cdp_load(1);


});


//Cargar datos AJAX
function cdp_load(page) {
	var search = $("#search").val();
	var status_courier = $("#status_courier").val();
	var filterby = $("#filterby").val();
	var parametros = { "page": page, 'search': search, 'status_courier': status_courier, 'filterby': filterby };
	$("#loader").fadeIn('slow');
	$.ajax({
		url: './ajax/pre_alerts/prealert_list_ajax.php',
		data: parametros,
		beforeSend: function (objeto) {
		},
		success: function (data) {
			$(".outer_div").html(data).fadeIn('slow');
		}
	})
}

