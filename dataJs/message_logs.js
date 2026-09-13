"use strict";

// ============================================================================
// Message Logs — filters, statistics and the message table.
//
// One filter set drives all three endpoints; stats and table reload together
// so a tile never describes a different set of rows than the table.
// ============================================================================

var ML_ROWS  = 'ajax/reports/message_logs_ajax.php';
var ML_STATS = 'ajax/reports/message_logs_stats_ajax.php';
var ML_CSV   = 'ajax/reports/message_logs_export_ajax.php';

var mlPage = 1;
var mlSearchTimer = null;
var mlChart = null;

$(function () {
    $('#ml_quick').on('click', 'button', function () {
        $('#ml_quick button').removeClass('is-on');
        $(this).addClass('is-on');
        mlApplyRange($(this).data('range'));
        cdpMlGo(1);
    });

    $('#ml_channel, #ml_status, #ml_source, #ml_sent_by, #ml_template, #ml_per_page, #ml_from, #ml_to')
        .on('change', function () { cdpMlGo(1); });

    $('#ml_search, #ml_batch').on('keyup', function () {
        clearTimeout(mlSearchTimer);
        mlSearchTimer = setTimeout(function () { cdpMlGo(1); }, 350);
    });

    // Headline tiles double as quick filters on status.
    $('#ml_kpis').on('click', '.ml-kpi[data-filter]', function () {
        $('#ml_status').val($(this).data('filter'));
        cdpMlGo(1);
    });

    cdpMlGo(1);
});

// ── Filter state ────────────────────────────────────────────────────────────
function mlFilters() {
    return {
        from:        $('#ml_from').val() || '',
        to:          $('#ml_to').val() || '',
        channel:     $('#ml_channel').val() || '',
        status:      $('#ml_status').val() || '',
        source:      $('#ml_source').val() || '',
        sent_by:     $('#ml_sent_by').val() || 0,
        template_id: $('#ml_template').val() || 0,
        batch_id:    $('#ml_batch').val() || '',
        search:      $('#ml_search').val() || ''
    };
}

function mlApplyRange(range) {
    var today = new Date();
    var fmt = function (d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
    if (range === 'all') { $('#ml_from').val(''); $('#ml_to').val(''); return; }
    if (range === 'today') { $('#ml_from').val(fmt(today)); $('#ml_to').val(fmt(today)); return; }
    if (range === 'month') { $('#ml_from').val(fmt(new Date(today.getFullYear(), today.getMonth(), 1))); $('#ml_to').val(fmt(today)); return; }
    var days = parseInt(range, 10) || 30;
    var from = new Date(today.getTime());
    from.setDate(from.getDate() - (days - 1));
    $('#ml_from').val(fmt(from));
    $('#ml_to').val(fmt(today));
}

function cdpMlReset() {
    $('#ml_channel, #ml_status, #ml_source, #ml_search, #ml_batch').val('');
    $('#ml_sent_by, #ml_template').val(0);
    $('#ml_per_page').val(50);
    $('#ml_quick button').removeClass('is-on');
    $('#ml_quick button[data-range="30"]').addClass('is-on');
    mlApplyRange(30);
    cdpMlGo(1);
}

function cdpMlExport() {
    window.location.href = ML_CSV + '?' + $.param(mlFilters());
}

function cdpMlFilterBatch(batch) {
    $('#mlDetailModal').modal('hide');
    $('#ml_batch').val(batch);
    $('#ml_quick button').removeClass('is-on');
    $('#ml_quick button[data-range="all"]').addClass('is-on');
    mlApplyRange('all');
    cdpMlGo(1);
}

function cdpMlFilterSender(id) {
    $('#ml_sent_by').val(String(id === 0 ? -1 : id));
    cdpMlGo(1);
    $('html, body').animate({ scrollTop: $('#ml_rows').offset().top - 90 }, 250);
}

function cdpMlFilterSource(key) {
    $('#ml_source').val(key);
    cdpMlGo(1);
}

// ── Load ────────────────────────────────────────────────────────────────────
function cdpMlGo(page) {
    mlPage = page || 1;
    mlLoadRows();
    mlLoadStats();
}

function mlLoadRows() {
    var params = mlFilters();
    params.page = mlPage;
    params.per_page = $('#ml_per_page').val() || 50;

    $('#ml_loader').show();
    $.ajax({
        url: ML_ROWS, data: params, cache: false,
        success: function (html) { $('#ml_rows').html(html); $('#ml_loader').hide(); },
        error: function () {
            $('#ml_rows').html('<div class="alert alert-danger mb-0">Could not load the message log. Please refresh and try again.</div>');
            $('#ml_loader').hide();
        }
    });
}

function mlLoadStats() {
    $.getJSON(ML_STATS, mlFilters(), function (r) {
        if (!r || !r.ok) {
            $('#ml_kpis').html('');
            return;
        }
        mlRenderKpis(r.headline);
        mlRenderTimeline(r.timeline);
        mlRenderChannels(r.channels);
        mlRenderSources(r.sources);
        mlRenderFailures(r.failures);
        mlRenderSenders(r.senders);
    }).fail(function () {
        $('#ml_kpis').html('<div class="alert alert-warning mb-0" style="grid-column:1/-1">Statistics are unavailable right now.</div>');
    });
}

function mlNum(n) { return Number(n || 0).toLocaleString(); }
function mlEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

// ── Tiles ───────────────────────────────────────────────────────────────────
function mlRenderKpis(h) {
    var tile = function (color, label, value, sub, filter) {
        return '<div class="ml-kpi" style="--c:' + color + '"' + (filter !== undefined ? ' data-filter="' + filter + '"' : '') + '>' +
                   '<div class="ml-kpi__k">' + label + '</div>' +
                   '<div class="ml-kpi__v">' + value + '</div>' +
                   '<div class="ml-kpi__s">' + sub + '</div>' +
               '</div>';
    };
    var rate = h.total ? Math.round((h.sent / h.total) * 100) : 0;

    $('#ml_kpis').html(
        tile('#336aea', 'Messages', mlNum(h.total), 'In the selected range', '') +
        tile('#0aa699', 'Sent', mlNum(h.sent), rate + '% of attempts', 'sent') +
        tile('#f62d51', 'Failed', mlNum(h.failed), 'Rejected by the provider or transport', 'failed') +
        tile('#b4770d', 'Skipped', mlNum(h.skipped), 'No number, not on WhatsApp, channel off', 'skipped') +
        tile('#9b6ef3', 'Recipients', mlNum(h.recipients), 'Distinct people reached') +
        tile('#8a94a6', 'Last Message', h.last_at && h.last_at !== '—' ? h.last_at.split(' ')[1] : '—',
             h.last_at && h.last_at !== '—' ? h.last_at.split(' ')[0] : 'Nothing recorded')
    );
    $('#ml_range_note').text(h.first_at && h.first_at !== '—' ? '(' + h.first_at + ' → ' + h.last_at + ')' : '');
}

// ── Timeline ────────────────────────────────────────────────────────────────
function mlRenderTimeline(t) {
    var el = document.querySelector('#ml_chart_time');
    if (!el || typeof ApexCharts === 'undefined') return;

    var series = (t && t.series) || [];
    var options = {
        chart: { type: 'bar', height: 260, stacked: true, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        series: series.map(function (s) { return { name: s.name, data: s.data }; }),
        colors: series.map(function (s) { return s.color; }),
        xaxis: {
            categories: (t && t.dates) || [],
            labels: { style: { colors: '#99a2b1', fontSize: '11px' }, rotate: -45, hideOverlappingLabels: true },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: { labels: { style: { colors: '#99a2b1', fontSize: '11px' } } },
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
        noData: { text: 'No messages in this range' }
    };

    if (mlChart) {
        mlChart.updateOptions(options, true, true);
    } else {
        mlChart = new ApexCharts(el, options);
        mlChart.render();
    }
}

// ── Channel bars (stacked sent/failed/skipped) ──────────────────────────────
function mlRenderChannels(items) {
    items = items || [];
    if (!items.length) { $('#ml_channels').html('<div class="ml-empty">Nothing to show for these filters.</div>'); return; }
    var max = Math.max.apply(null, items.map(function (i) { return i.total; })) || 1;

    $('#ml_channels').html(items.map(function (i) {
        var w = function (n) { return Math.round((n / max) * 100); };
        return '<div class="ml-bar" onclick="$(\'#ml_channel\').val(\'' + mlEsc(i.key) + '\');cdpMlGo(1)">' +
                   '<div class="ml-bar__top"><span class="ml-bar__name">' + mlEsc(i.label) + '</span>' +
                   '<span class="ml-bar__n">' + mlNum(i.sent) + ' · ' + mlNum(i.failed) + ' · ' + mlNum(i.skipped) + '</span></div>' +
                   '<div class="ml-bar__track">' +
                       '<div class="ml-bar__fill" style="width:' + w(i.sent) + '%;--c:#0aa699"></div>' +
                       '<div class="ml-bar__fill" style="width:' + w(i.failed) + '%;--c:#f62d51"></div>' +
                       '<div class="ml-bar__fill" style="width:' + w(i.skipped) + '%;--c:#b4770d"></div>' +
                   '</div>' +
               '</div>';
    }).join(''));
}

function mlRenderSources(items) {
    items = items || [];
    if (!items.length) { $('#ml_sources').html('<div class="ml-empty">Nothing to show for these filters.</div>'); return; }
    var max = Math.max.apply(null, items.map(function (i) { return i.count; })) || 1;
    $('#ml_sources').html(items.map(function (i) {
        var pct = Math.max(2, Math.round((i.count / max) * 100));
        return '<div class="ml-bar" onclick="cdpMlFilterSource(\'' + mlEsc(i.key) + '\')">' +
                   '<div class="ml-bar__top"><span class="ml-bar__name">' + mlEsc(i.label) + '</span>' +
                   '<span class="ml-bar__n">' + mlNum(i.count) + (i.failed ? ' <span style="color:#f62d51">(' + mlNum(i.failed) + ' failed)</span>' : '') + '</span></div>' +
                   '<div class="ml-bar__track"><div class="ml-bar__fill" style="width:' + pct + '%;--c:#336aea"></div></div>' +
               '</div>';
    }).join(''));
}

function mlRenderFailures(items) {
    items = items || [];
    if (!items.length) { $('#ml_failures').html('<div class="ml-empty">Every message in this range went out.</div>'); return; }
    var max = Math.max.apply(null, items.map(function (i) { return i.count; })) || 1;
    $('#ml_failures').html(items.map(function (i) {
        var pct = Math.max(2, Math.round((i.count / max) * 100));
        return '<div class="ml-bar" style="cursor:default">' +
                   '<div class="ml-bar__top"><span class="ml-bar__name">' + mlEsc(i.label) + ' <small class="text-muted">' + mlEsc(i.channel) + '</small></span>' +
                   '<span class="ml-bar__n">' + mlNum(i.count) + '</span></div>' +
                   '<div class="ml-bar__track"><div class="ml-bar__fill" style="width:' + pct + '%;--c:#b4770d"></div></div>' +
               '</div>';
    }).join(''));
}

function mlRenderSenders(rows) {
    rows = rows || [];
    if (!rows.length) { $('#ml_senders').html('<tr><td colspan="10" class="ml-empty">No messages for these filters.</td></tr>'); return; }
    $('#ml_senders').html(rows.map(function (a) {
        return '<tr class="ml-row" onclick="cdpMlFilterSender(' + a.id + ')">' +
                   '<td class="ml-name">' + mlEsc(a.name) + '</td>' +
                   '<td class="text-muted">' + mlEsc(a.role) + '</td>' +
                   '<td class="text-right">' + mlNum(a.wa) + '</td>' +
                   '<td class="text-right">' + mlNum(a.em) + '</td>' +
                   '<td class="text-right">' + mlNum(a.sms) + '</td>' +
                   '<td class="text-right" style="color:#0aa699">' + mlNum(a.sent) + '</td>' +
                   '<td class="text-right" style="color:#f62d51">' + mlNum(a.failed) + '</td>' +
                   '<td class="text-right" style="color:#b4770d">' + mlNum(a.skipped) + '</td>' +
                   '<td class="text-right"><b>' + mlNum(a.count) + '</b></td>' +
                   '<td class="text-muted"><small>' + mlEsc(a.last_at) + '</small></td>' +
               '</tr>';
    }).join(''));
}

// ── Detail ──────────────────────────────────────────────────────────────────
function cdpMlDetail(id) {
    $('#ml_detail_body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#mlDetailModal').modal('show');
    $.get(ML_ROWS, { action: 'detail', id: id }, function (html) {
        $('#ml_detail_body').html(html);
    }).fail(function () {
        $('#ml_detail_body').html('<div class="alert alert-danger mb-0">Could not load that message.</div>');
    });
}
