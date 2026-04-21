"use strict";

// reporte de ventas cuentas por cobrar basic bar
$(document).ready(function () {

    var months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'
    ];

    $.ajax({
        url: './ajax/dashboard/account_receivable/load_graphics_account_receivable_ajax.php',
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            if (!response || !Array.isArray(response)) {
                console.error('Invalid chart data');
                return;
            }

            var salesData = response.map(parseFloat);

            var myChart = echarts.init(document.getElementById('basic-bar'));

            var option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' }
                },
                legend: { data: [translate_graphic_16] },
                toolbox: {
                    show: true,
                    feature: {
                        magicType: { show: true, type: ['line', 'bar'] },
                        restore: { show: true },
                        saveAsImage: { show: true }
                    }
                },
                color: ["#2962FF"],
                calculable: true,
                xAxis: { type: 'category', data: months },
                yAxis: { type: 'value' },
                series: [{
                    name: translate_graphic_16,
                    type: 'bar',
                    data: salesData,
                    markPoint: {
                        data: [
                            { type: 'max', name: 'Max' },
                            { type: 'min', name: 'Min' }
                        ]
                    },
                    markLine: {
                        data: [{ type: 'average', name: 'Average' }]
                    }
                }]
            };

            myChart.setOption(option);
        },
        error: function (xhr, status, err) {
            console.error('Error loading chart data:', status, err);
        }
    });

    cdp_load(1);
});


//Cargar datos AJAX
function cdp_load(page) {
    var parametros = { "page": page };
    $("#loader").fadeIn('slow');
    $.ajax({
        url: './ajax/dashboard/account_receivable/load_account_receivable_ajax.php',
        data: parametros,
        success: function (data) {
            $(".outer_div").html(data).fadeIn('slow');
        }
    });
}
