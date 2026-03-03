"use strict";


// reorte de ventas consolidados de envios basic bar
$(document).ready(() => {
    const loadGraphicsECharts = async () => {
        try {
            const response = await $.ajax({
                url: './ajax/dashboard/consolidated/load_graphics_consolidated_ajax.php',
                type: 'POST',
                dataType: 'json'
            });

            // Datos para el eje X (meses)
            const months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'
            ];

            // Datos para la serie de ventas (eje Y)
            const salesData = response.map(parseFloat);

            // Inicializa la instancia de ECharts en el elemento con ID 'basic-bar'
            var myChart = echarts.init(document.getElementById('basic-bar'));

            // Configuración del gráfico de barras
            var option = {
                // Configuración del tooltip
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },

                // Leyenda
                legend: {
                    data: ['Site A']
                },

                // Herramientas
                toolbox: {
                    show: true,
                    feature: {
                        magicType: { show: true, type: ['line', 'bar'] },
                        restore: { show: true },
                        saveAsImage: { show: true }
                    }
                },

                // Configuración del eje X
                color: ["#2962FF"],
                calculable : true,
                xAxis: {
                    type: 'category',
                    data: months
                },

                // Configuración del eje Y
                yAxis: {
                    type: 'value'
                },

                // Serie de datos
                series: [{
                    name: translate_graphic_15,
                    type: 'bar',
                    data: salesData,
                    markPoint: {
                        data: [
                            { type: 'max', name: 'Max' },
                            { type: 'min', name: 'Min' }
                        ]
                    },
                    markLine: {
                        data: [
                            { type: 'average', name: 'Average' }
                        ]
                    }
                }]
            };

            // Establece la opción de configuración para mostrar el gráfico
            myChart.setOption(option);
        } catch (error) {
            console.error('Error loading sales data:', error);
        }
    };

    loadGraphicsECharts();
    cdp_load(1);
});




//Cargar datos AJAX
function cdp_load(page) {

  var parametros = { "page": page };
  $("#loader").fadeIn('slow');
  $.ajax({
    url: './ajax/dashboard/consolidated/load_consolidated_ajax.php',
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (data) {
      $(".outer_div").html(data).fadeIn('slow');
    }
  })
}


