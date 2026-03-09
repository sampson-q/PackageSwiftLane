"use strict";

$(function () {
    // 1) Initialize the Top Shipper picker
    var startShip = moment().startOf('month');
    var endShip   = moment().endOf('month');

    $('#daterange-topshipper').daterangepicker({
        startDate:       startShip,
        endDate:         endShip,
        autoUpdateInput: false,
        showCancel:      true,
        locale: {
            format: 'YYYY/MM/DD',
            separator:        ' - ',
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

    $('#daterange-topshipper').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(
            picker.startDate.format('Y/M/D')
            + ' - ' +
            picker.endDate.format('Y/M/D')
        );
        // Explicitly pass the range of the "Top Shipper" picker
        cdp_load('topshipper', $(this).val(), 1);
    });

    $('#daterange-topshipper').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        cdp_load('topshipper', '', 1);
    });

    $('#btn-clear-topshipper').on('click', function() {
        $('#daterange-topshipper').val('');
        cdp_load('topshipper', '', 1);
    });


    // 2) Initialize the Top Invoice picker
    var startInv = moment().startOf('month');
    var endInv   = moment().endOf('month');

    $('#daterange-topinvoice').daterangepicker({
        startDate:       startInv,
        endDate:         endInv,
        autoUpdateInput: false,
        showCancel:      true,
        locale: {
            format: 'YYYY/MM/DD',
            separator:        ' - ',
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

    $('#daterange-topinvoice').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(
            picker.startDate.format('Y/M/D')
            + ' - ' +
            picker.endDate.format('Y/M/D')
        );
        // Explicitly pass the range of the "Top Invoice" picker
        cdp_load('topinvoice', $(this).val(), 1);
    });

    $('#daterange-topinvoice').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        cdp_load('topinvoice', '', 1);
    });

    $('#btn-clear-topinvoice').on('click', function() {
        $('#daterange-topinvoice').val('');
        cdp_load('topinvoice', '', 1);
    });


    // 3) Initial load of both reports (no range = all-time)
    cdp_load('topshipper', '', 1);
    cdp_load('topinvoice', '', 1);
});

// A single cdp_load() function for both “topshipper” and “topinvoice”
function cdp_load(page, range, table_page) {
    // Pass both page and its own range string
    var parametros = {
        page:  page,
        range: range,
        table_page: table_page,
    };

    $("#loader").fadeIn('slow');

    $.ajax({
        url: './ajax/reports/report_top_users_ajax_sea.php',
        data: parametros,
        success: function(data) {
            if (page === 'topshipper') {
                $(".topshipper-outer_div").html(data).fadeIn('slow');
            } else if (page === 'topinvoice') {
                $(".topinvoice-outer_div").html(data).fadeIn('slow');
            }
        }
    });
}

function cdp_exportExcel(page) {
    var daterange;
    if (page === 'topshipper') {
        daterange = $("#daterange-topshipper").val();
    } else if (page === 'topinvoice') {
        daterange = $("#daterange-topinvoice").val();
    }

	window.open('top_users_sea_excel.php?range=' + daterange + '&page=' + page);
}

function cdp_exportPrint(page) {
	var daterange;
    if (page === 'topshipper') {
        daterange = $("#daterange-topshipper").val();
    } else if (page === 'topinvoice') {
        daterange = $("#daterange-topinvoice").val();
    }

	window.open('top_users_sea_print.php?range=' + daterange + '&page=' + page);
}

$('.topshipper-outer_div').on('click', '.pagination a.page-link', function(e) {
    e.preventDefault();
    var onclickAttr = $(this).attr('onclick');
    if (!onclickAttr) return;

    var m = onclickAttr.match(/cdp_load\((\d+)\)/);
    if (!m) return;

    var targetPage = parseInt(m[1], 10);
    var currentRange = $('#daterange-topshipper').val() || '';

    cdp_load('topshipper', currentRange, targetPage);
});

