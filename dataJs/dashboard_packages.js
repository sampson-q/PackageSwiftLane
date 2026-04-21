"use strict";

//Grafico de ventas de paquetes MORRIS LINE CHART AJAX

$(document).ready(function () {

    $.ajax({
        url: './ajax/dashboard/packages_registered/load_graphics_packages_registered_ajax.php',
        type: 'POST',
        dataType: 'json',
        success: function (response) {

            if (!response || !Array.isArray(response) || response.length === 0) {
                return;
            }

            var months = [
                translate_graphic_0,
                translate_graphic_1,
                translate_graphic_2,
                translate_graphic_3,
                translate_graphic_4,
                translate_graphic_5,
                translate_graphic_6,
                translate_graphic_7,
                translate_graphic_8,
                translate_graphic_9,
                translate_graphic_10,
                translate_graphic_11
            ];

            var data = [];
            for (var i = 0; i < response.length; i++) {
                data.push({
                    month: months[i],
                    sales: parseFloat(response[i]) || 0
                });
            }

            Morris.Line({
                element: 'morris-sales-chart-packages',
                data: data,
                xkey: 'month',
                ykeys: ['sales'],
                labels: [translate_graphic_13],
                gridLineColor: '#eef0f2',
                lineColors: ['#2962FF'],
                lineWidth: 2,
                hideHover: 'auto',
                xLabelAngle: 60,
                parseTime: false,
                gridTextSize: 12,
                ymin: 0
            });
        },
        error: function (xhr, status, err) {
            console.error('Error loading chart data:', status, err);
        }
    });

});
