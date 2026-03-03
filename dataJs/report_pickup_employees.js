"use strict";

$(function () {

});
$(function () {

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
  var status_courier = $("#status_courier").val();
  var employee_id = $("#employee_id").val();
  var daterange = $("#daterange").val();
  var parametros = { "page": page, 'status_courier': status_courier, 'employee_id': employee_id, 'range': daterange };
  $("#loader").fadeIn('slow');
  $.ajax({
    url: './ajax/reports/report_pickup_employees.php',
    data: parametros,
    beforeSend: function (objeto) {
    },
    success: function (data) {
      $(".outer_div").html(data).fadeIn('slow');
    }
  })
}




function cdp_exportExcel() {

  var status_courier = $("#status_courier").val();
  var employee_id = $("#employee_id").val();
  var daterange = $("#daterange").val();

  window.open('report_pickup_employees_excel.php?range=' + daterange + '&status_courier=' + status_courier + '&employee_id=' + employee_id);

}


function cdp_exportPrint() {

  var status_courier = $("#status_courier").val();
  var employee_id = $("#employee_id").val();
  var daterange = $("#daterange").val();

  window.open('report_pickup_employees_print.php?range=' + daterange + '&status_courier=' + status_courier + '&employee_id=' + employee_id);

}