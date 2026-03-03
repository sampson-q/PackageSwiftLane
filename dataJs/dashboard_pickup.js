"use strict";


$(function () {
  cdp_load(1);

});


//Grafico de ventas de paquetes recogidas MORRIS LINE CHART AJAX

$(document).ready(() => {
  const loadGraphicsmorris = async () => {
    try {
      const response = await $.ajax({
        url: './ajax/dashboard/pickup/load_graphics_pickup_ajax.php',
        type: 'POST',
        dataType: 'json'
      });

      // Convertimos los datos recibidos en el formato necesario para Morris.js
      const data = [];
      const months = [
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


      for (let i = 0; i < response.length; i++) {
        data.push({
          month: months[i], // Usamos los nombres de los meses en lugar de "Month 1", "Month 2", etc.
          sales: parseFloat(response[i] || 0) // Convertimos el dato de ventas a número
        });
      }

      // Creamos el gráfico de líneas utilizando Morris.js
      const salesChart = new Morris.Line({
        element: 'morris-sales-chart-pickup',
        data: data,
        xkey: 'month',
        ykeys: ['sales'],
        labels: [translate_graphic_13],
        gridLineColor: '#eef0f2',
        lineColors: ['#2962FF'],
        lineWidth: 1,
        hideHover: 'auto',
        xLabelAngle: 60, // Rotamos los nombres de los meses para evitar superposiciones
        parseTime: false, // Desactivamos el análisis de tiempo para los nombres de los meses
        gridTextSize: 12 // Tamaño del texto en el eje Y (afecta la altura de las líneas horizontales)
      });
    } catch (error) {
      console.error('Error loading sales data:', error);
    }
  };

  loadGraphicsmorris();
  cdp_load(1);
});


//Cargar datos AJAX
function cdp_load(page) {

  var parametros = { "page": page };
  $("#loader").fadeIn('slow');
  $.ajax({
    url: './ajax/dashboard/pickup/load_pickup_ajax.php',
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (data) {
      $(".outer_div").html(data).fadeIn('slow');
    }
  })
}