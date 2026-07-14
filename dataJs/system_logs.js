"use strict";

// System Logs — unified categorized activity feed.
var cdpLogsCat = 'all';
var cdpLogsTimer = null;

$(function () {
    cdpLogsGo(1);
});

function cdpLogsSetCat(cat) {
    cdpLogsCat = cat;
    $('.log-cat').removeClass('active');
    $('.log-cat[data-cat="' + cat + '"]').addClass('active');
    cdpLogsGo(1);
}

function cdpLogsDebounced() {
    clearTimeout(cdpLogsTimer);
    cdpLogsTimer = setTimeout(function () { cdpLogsGo(1); }, 350);
}

function cdpLogsGo(page) {
    var params = {
        cat: cdpLogsCat,
        search: $('#search').val() || '',
        per_page: $('#per_page').val() || 50,
        page: page
    };
    $('#loader').fadeIn('fast');
    $.ajax({
        url: './ajax/reports/system_logs_ajax.php',
        data: params,
        cache: false,
        success: function (data) {
            $('.outer_div').html(data);
            $('#loader').fadeOut('fast');
        },
        error: function () {
            $('.outer_div').html('<div class="alert alert-danger m-2">Could not load logs. Please refresh and try again.</div>');
            $('#loader').fadeOut('fast');
        }
    });
}
