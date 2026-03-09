"use strict";

$(function () {

    // Initialise the date range picker
    $('#daterange-warehouse').daterangepicker({
        autoUpdateInput: false,
        showCancel: true,
        locale: {
            format: 'YYYY/MM/DD',
            separator: ' - ',
            applyLabel:       range_calendar_text17,
            cancelLabel:      range_calendar_text16,
            fromLabel:        range_calendar_text14,
            toLabel:          range_calendar_text15,
            customRangeLabel: range_calendar_text13,
            daysOfWeek: [
                range_calendar_text24,
                range_calendar_text25,
                range_calendar_text26,
                range_calendar_text27,
                range_calendar_text28,
                range_calendar_text29,
                range_calendar_text30
            ],
            monthNames: [
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
                range_calendar_text12
            ],
            firstDay: 1
        },
        ranges: {
            [range_calendar_text18]: [moment(), moment()],
            [range_calendar_text19]: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            [range_calendar_text20]: [moment().subtract(6, 'days'), moment()],
            [range_calendar_text21]: [moment().subtract(29, 'days'), moment()],
            [range_calendar_text22]: [moment().startOf('month'), moment().endOf('month')],
            [range_calendar_text23]: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    });

    $('#daterange-warehouse').on('apply.daterangepicker', function (ev, picker) {
        $(this).val(
            picker.startDate.format('Y/M/D') + ' - ' + picker.endDate.format('Y/M/D')
        );
        cdp_load(1);
    });

    $('#daterange-warehouse').on('cancel.daterangepicker', function () {
        $(this).val('');
        cdp_load(1);
    });

    $('#btn-clear-daterange').on('click', function () {
        $('#daterange-warehouse').val('');
        cdp_load(1);
    });

    // Initial load
    cdp_load(1);
});


// Load warehouse table via AJAX
function cdp_load(page) {
    var search         = $("#search").val();
    var status_courier = $("#status_courier").val();
    var filterby       = $("#filterby").val();
    var range          = $("#daterange-warehouse").val();

    var parametros = {
        page:           page,
        search:         search,
        status_courier: status_courier,
        filterby:       filterby,
        range:          range
    };

    $("#loader").fadeIn('slow');

    $.ajax({
        url: './ajax/courier/warehouse_view_ajax.php',
        data: parametros,
        success: function (data) {
            $(".outer_divx").html(data).fadeIn('slow');
        }
    });
}


// Bulk status update
$("#send_checkbox_status").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var checked_data = [];
    $('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function () {
        checked_data.push($(this).val());
    });

    var status = $('#status_courier_modal').val();

    $.ajax({
        type: "GET",
        url: './ajax/courier/courier_update_multiple_ajax.php?status=' + status,
        data: { 'checked_data': JSON.stringify(checked_data) },
        success: function (datos) {
            $("#resultados_ajax").html(datos);
            $('#guardar_datos').attr("disabled", false);
            $('#modalCheckboxStatus').modal('hide');
            cdp_load(1);
            $('#div-actions-checked').addClass('hide');
            $('#countChecked').addClass('hide');
            $('html, body').animate({ scrollTop: 0 }, 600);
        }
    });

    event.preventDefault();
});