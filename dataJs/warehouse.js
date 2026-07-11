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
    var search          = $("#search").val();
    var search_customer = $("#search_customer").val();
    var status_courier  = $("#status_courier").val();
    var filterby        = $("#filterby").val();
    var range           = $("#daterange-warehouse").val();
    var per_page        = $("#per_page").val();

    var parametros = {
        page:            page,
        search:          search,
        search_customer: search_customer,
        status_courier:  status_courier,
        filterby:        filterby,
        range:           range,
        per_page:        per_page
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


// Export current warehouse view to a printable / PDF page
function cdp_exportPrint() {
    var search         = $("#search").val();
    var status_courier = $("#status_courier").val();
    var filterby       = $("#filterby").val();
    var range          = $("#daterange-warehouse").val();

    window.open(
        'warehouse_print.php?search=' + encodeURIComponent(search) +
        '&status_courier=' + encodeURIComponent(status_courier) +
        '&filterby=' + encodeURIComponent(filterby) +
        '&range=' + encodeURIComponent(range)
    );
}


// New delivery flow (single row's "Deliver Package" or the bulk "Deliver
// Selected") — deliberately separate from the old courier_deliver_shipment.php
// page, which is no longer linked from here but stays untouched in the system.
// Preview the package(s) for an explicit confirm, then flip their status.
function cdpWarehouseDeliver(orderNos) {
    if (!orderNos || !orderNos.length) {
        Swal.fire({ icon: "warning", text: "Please select at least one package.", confirmButtonText: "Ok" });
        return;
    }

    $.ajax({
        url: "./ajax/courier/warehouse_deliver_ajax.php",
        method: "POST",
        data: { action: "preview", order_nos: JSON.stringify(orderNos) },
        dataType: "json",
        success: function (r) {
            if (!r || !r.ok || !r.packages || !r.packages.length) {
                Swal.fire({ icon: "error", text: (r && r.message) ? r.message : "Could not load package details.", confirmButtonText: "Ok" });
                return;
            }

            // Only finance-cleared, undelivered packages can actually be
            // delivered; the rest are shown but flagged and excluded so the
            // bulk path can't bypass the Awaiting Clearance restriction.
            var deliverable = r.packages.filter(function (p) { return p.cleared && !p.already_delivered; });

            var rows = r.packages.map(function (p) {
                var warn = "";
                if (p.already_delivered) { warn = ' <span style="color:#dc3545;">(already delivered)</span>'; }
                else if (!p.cleared) { warn = ' <span style="color:#b26a00;">(Awaiting Clearance — will be skipped)</span>'; }
                return '<div style="text-align:left;border-bottom:1px solid #eee;padding:6px 2px;">' +
                    "<b>" + $("<div>").text(p.tracking).html() + "</b>" + warn + "<br>" +
                    '<span style="font-size:12px;color:#666;">' +
                    $("<div>").text(p.name).html() + " &middot; Carrier: " + $("<div>").text(p.carrier).html() +
                    "</span></div>";
            }).join("");

            if (!deliverable.length) {
                Swal.fire({ icon: "warning", html: '<div style="max-height:260px;overflow-y:auto;">' + rows + "</div>" +
                    "<p class='mt-2 mb-0'>None of the selected packages are cleared for delivery.</p>", confirmButtonText: "Ok" });
                return;
            }
            var deliverNos = deliverable.map(function (p) { return String(p.order_no); });

            Swal.fire({
                title: "Confirm Delivery",
                html: '<div style="max-height:260px;overflow-y:auto;">' + rows + "</div>" +
                      "<p class='mt-2 mb-0'>Mark " + (deliverable.length === 1 ? "the 1 cleared package" : "the " + deliverable.length + " cleared packages") + " as delivered?</p>",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Yes, deliver",
                cancelButtonText: "Cancel",
                reverseButtons: true,
                preConfirm: function () {
                    return $.ajax({
                        url: "./ajax/courier/warehouse_deliver_ajax.php",
                        method: "POST",
                        data: { action: "deliver", order_nos: JSON.stringify(deliverNos) },
                        dataType: "json"
                    }).then(
                        function (dr) {
                            if (!dr || !dr.ok) {
                                Swal.showValidationMessage((dr && dr.message) || "Could not mark as delivered.");
                                return false;
                            }
                            return dr;
                        },
                        function () {
                            Swal.showValidationMessage("Request failed.");
                            return false;
                        }
                    );
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;
                var n = (result.value && result.value.delivered) || 0;
                var skipped = r.packages.length - deliverable.length;
                var msg = n + " package(s) marked as delivered.";
                if (skipped > 0) { msg += " " + skipped + " skipped (not cleared / already delivered)."; }
                Swal.fire({ icon: "success", text: msg, confirmButtonText: "Ok" }).then(function () {
                    cdp_load(1);
                    if (typeof cdpSelClear === "function") cdpSelClear();
                });
            });
        },
        error: function () {
            Swal.fire({ icon: "error", text: "Request failed.", confirmButtonText: "Ok" });
        }
    });
}


// Bulk status update
$("#send_checkbox_status").on('submit', function (event) {

    $('#guardar_datos').attr("disabled", true);

    var checked_data = (typeof cdpSelGet === 'function') ? cdpSelGet() : [];

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
            if (typeof cdpSelClear === 'function') cdpSelClear();
            $('html, body').animate({ scrollTop: 0 }, 600);
        }
    });

    event.preventDefault();
});