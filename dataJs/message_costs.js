"use strict";

// ============================================================================
// Message Costs — filters, cost tiles, chart, breakdown and the unit-cost
// editor. One request (message_costs_ajax.php) returns everything the page
// shows, so the tiles, chart and tables always describe the same messages.
// ============================================================================

var MC_DATA  = 'ajax/reports/message_costs_ajax.php';
var MC_RATES = 'ajax/reports/message_costs_rates_ajax.php';

var mcChart = null;
var mcChartMode = 'cost';
var mcLast = null;

$(function () {
    $('#mc_quick').on('click', 'button', function () {
        $('#mc_quick button').removeClass('is-on');
        $(this).addClass('is-on');
        mcSetMode('range');
        mcApplyRange($(this).data('range'));
        cdpMcLoad();
    });

    $('#mc_mode').on('change', function () {
        mcSetMode($(this).val());
        $('#mc_quick button').removeClass('is-on');
        cdpMcLoad();
    });

    $('#mc_from, #mc_to').on('change', function () {
        $('#mc_quick button').removeClass('is-on');
        cdpMcLoad();
    });
    $('#mc_month, #mc_day, #mc_channel, #mc_source, #mc_group').on('change', function () { cdpMcLoad(); });

    $('#mc_chart_mode').on('click', 'button', function () {
        $('#mc_chart_mode button').removeClass('is-on');
        $(this).addClass('is-on');
        mcChartMode = $(this).data('mode');
        if (mcLast) { mcRenderChart(mcLast); }
    });

    cdpMcLoad();
});

// ── Filter state ────────────────────────────────────────────────────────────
function mcFmt(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function mcToday() {
    var p = String(MC_TODAY || '').split('-');
    return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : new Date();
}

function mcSetMode(mode) {
    $('#mc_mode').val(mode);
    $('.mc-mode').removeClass('is-on');
    $('.mc-mode[data-mode="' + mode + '"]').addClass('is-on');
}

function mcApplyRange(range) {
    var t = mcToday();
    var from = null, to = t;
    if (range === 'all') { $('#mc_from').val(''); $('#mc_to').val(''); return; }
    if (range === 'today') { from = t; }
    else if (range === 'yesterday') { from = new Date(t.getFullYear(), t.getMonth(), t.getDate() - 1); to = from; }
    else if (range === 'month') { from = new Date(t.getFullYear(), t.getMonth(), 1); }
    else if (range === 'lastmonth') { from = new Date(t.getFullYear(), t.getMonth() - 1, 1); to = new Date(t.getFullYear(), t.getMonth(), 0); }
    else if (range === 'year') { from = new Date(t.getFullYear(), 0, 1); }
    else {
        var days = parseInt(range, 10) || 7;
        from = new Date(t.getFullYear(), t.getMonth(), t.getDate() - (days - 1));
    }
    $('#mc_from').val(mcFmt(from));
    $('#mc_to').val(mcFmt(to));
}

/** The date range the current mode stands for. */
function mcFilters() {
    var mode = $('#mc_mode').val();
    var from = '', to = '';
    if (mode === 'month') {
        var m = String($('#mc_month').val() || '').split('-');
        if (m.length === 2) {
            from = m[0] + '-' + m[1] + '-01';
            to = mcFmt(new Date(+m[0], +m[1], 0));
        }
    } else if (mode === 'day') {
        from = to = $('#mc_day').val() || '';
    } else {
        from = $('#mc_from').val() || '';
        to = $('#mc_to').val() || '';
    }
    return {
        from: from,
        to: to,
        channel: $('#mc_channel').val() || '',
        source: $('#mc_source').val() || '',
        group: $('#mc_group').val() || 'auto'
    };
}

function cdpMcReset() {
    $('#mc_channel, #mc_source').val('');
    $('#mc_group').val('auto');
    mcSetMode('range');
    $('#mc_quick button').removeClass('is-on');
    $('#mc_quick button[data-range="month"]').addClass('is-on');
    mcApplyRange('month');
    cdpMcLoad();
}

function cdpMcExport() {
    window.location.href = MC_DATA + '?' + $.param($.extend(mcFilters(), { format: 'csv' }));
}

function cdpMcFilterSource(key) {
    $('#mc_source').val(key);
    cdpMcLoad();
}

// ── Load ────────────────────────────────────────────────────────────────────
function cdpMcLoad() {
    $('#mc_loader').show();
    $.getJSON(MC_DATA, mcFilters(), function (r) {
        $('#mc_loader').hide();
        if (!r || !r.ok) {
            $('#mc_kpis').html('');
            $('#mc_breakdown_head, #mc_breakdown_foot').html('');
            $('#mc_breakdown').html('<tr><td class="mc-empty">' + (r && r.error === 'table_missing'
                ? 'The message log is not set up on this database yet.'
                : 'Could not load the figures.') + '</td></tr>');
            $('#mc_sources').html('');
            return;
        }
        mcLast = r;
        mcRenderUnpriced(r);
        mcRenderRatesLine(r);
        mcRenderKpis(r);
        mcRenderChart(r);
        mcRenderBreakdown(r);
        mcRenderSources(r);
    }).fail(function () {
        $('#mc_loader').hide();
        $('#mc_kpis').html('<div class="col-12"><div class="alert alert-warning">The figures are unavailable right now. Please refresh and try again.</div></div>');
    });
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function mcNum(n) { return Number(n || 0).toLocaleString(); }
function mcEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

/** Money: two decimals, up to four when the amount is that small. */
function mcMoney(v, cur) {
    var n = Number(v || 0);
    var dec = (n !== 0 && Math.abs(n) < 1 && Math.round(n * 100) !== n * 100) ? 4 : 2;
    var s = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: dec });
    return (cur ? mcEsc(cur) + ' ' : '') + s;
}

function mcRate(v, cur) {
    var n = Number(v || 0);
    var s = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });
    return (cur ? mcEsc(cur) + ' ' : '') + s;
}

function mcPeriodLabel(p, group) {
    var parts = String(p).split('-');
    if (group === 'month' && parts.length === 2) {
        return new Date(+parts[0], +parts[1] - 1, 1).toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
    }
    if (parts.length === 3) {
        return new Date(+parts[0], +parts[1] - 1, +parts[2]).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }
    return p;
}

// ── Notices ─────────────────────────────────────────────────────────────────
function mcRenderUnpriced(r) {
    var missing = MC_CHANNELS.filter(function (c) { return !r.priced[c.key]; }).map(function (c) { return c.label; });
    if (!missing.length) { $('#mc_unpriced').hide(); return; }
    $('#mc_unpriced').html('<b>No unit cost entered for ' + mcEsc(missing.join(', ')) + ' yet</b>, so ' +
        (missing.length > 1 ? 'they read' : 'it reads') + ' as zero. ' +
        (MC_CAN_EDIT ? '<a href="javascript:void(0)" onclick="cdpMcOpenRates()">Enter the unit costs</a>.' : 'Ask an Administrator to enter the unit costs.')
    ).show();
}

function mcRenderRatesLine(r) {
    var cur = r.settings.currency;
    var bits = MC_CHANNELS.map(function (c) {
        return c.label + ' <b>' + mcRate(r.current[c.key], cur) + '</b>';
    });
    $('#mc_rates_line').html('Current unit costs: ' + bits.join(' · ') +
        (Number(r.settings.sms_parts) === 1 ? ' · long SMS charged per part' : ''));
}

// ── Tiles ───────────────────────────────────────────────────────────────────
function mcRenderKpis(r) {
    var cur = r.settings.currency;
    var tile = function (opts) {
        return '<div class="col-md-6 col-xl-3 mb-3">' +
                   '<div class="swl-metric' + (opts.inverse ? ' swl-metric--inverse' : '') + '">' +
                       '<div class="swl-metric__head">' +
                           '<div class="swl-metric__text">' +
                               '<span class="swl-metric__label">' + mcEsc(opts.label) + '</span>' +
                               '<span class="swl-metric__value">' + opts.value + '</span>' +
                           '</div>' +
                           '<span class="swl-metric__icon"><iconify-icon icon="' + opts.icon + '"></iconify-icon></span>' +
                       '</div>' +
                       '<span class="swl-metric__foot"><span>' + opts.sub + '</span></span>' +
                   '</div>' +
               '</div>';
    };
    var icons = { sms: 'solar:chat-square-linear', whatsapp: 'ic:baseline-whatsapp', email: 'solar:letter-linear' };
    var g = r.grand;
    var html = tile({
        inverse: true, label: 'Total Cost', icon: 'solar:wallet-money-linear',
        value: mcMoney(g.cost, cur),
        sub: mcNum(g.sent) + ' messages sent' + (g.failed + g.skipped ? ' · ' + mcNum(g.failed + g.skipped) + ' not sent' : '')
    });
    MC_CHANNELS.forEach(function (c) {
        var t = r.totals[c.key];
        var parts = (c.key === 'sms' && Number(r.settings.sms_parts) === 1 && t.units !== t.sent) ? ' (' + mcNum(t.units) + ' parts)' : '';
        var notSent = t.failed + t.skipped;
        html += tile({
            label: c.label + ' Cost', icon: icons[c.key],
            value: mcMoney(t.cost, cur),
            sub: mcNum(t.sent) + ' sent' + parts + ' · ' + mcRate(r.current[c.key]) + ' each today' +
                 (notSent ? ' · ' + mcNum(notSent) + ' not sent' : '')
        });
    });
    $('#mc_kpis').html(html);

    var f = r.filters;
    $('#mc_range_note').text(f.from || f.to ? '(' + (f.from || 'start') + ' → ' + (f.to || 'today') + ')' : '(all time)');
}

// ── Chart ───────────────────────────────────────────────────────────────────
function mcRenderChart(r) {
    var el = document.querySelector('#mc_chart');
    if (!el || typeof ApexCharts === 'undefined') return;

    var cur = r.settings.currency;
    var isCost = mcChartMode === 'cost';
    var cats = r.periods.map(function (p) { return mcPeriodLabel(p.period, r.group); });
    var series = MC_CHANNELS.filter(function (c) { return !r.filters.channel || r.filters.channel === c.key; }).map(function (c) {
        return {
            name: c.label,
            data: r.periods.map(function (p) {
                var v = p.channels[c.key];
                return isCost ? Math.round(v.cost * 10000) / 10000 : v.sent;
            })
        };
    });
    var colors = MC_CHANNELS.filter(function (c) { return !r.filters.channel || r.filters.channel === c.key; }).map(function (c) { return c.color; });

    var options = {
        chart: { type: 'bar', height: 290, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
        series: series,
        colors: colors,
        xaxis: {
            categories: cats,
            labels: { rotate: -45, hideOverlappingLabels: true, style: { fontSize: '11px' } },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                style: { fontSize: '11px' },
                formatter: function (v) { return isCost ? mcMoney(v) : mcNum(Math.round(v)); }
            }
        },
        tooltip: {
            y: { formatter: function (v) { return isCost ? cur + ' ' + mcMoney(v) : mcNum(v) + ' sent'; } }
        },
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
        grid: { strokeDashArray: 4 },
        noData: { text: 'No messages in this period' }
    };

    if (mcChart) {
        mcChart.updateOptions(options, true, true);
    } else {
        mcChart = new ApexCharts(el, options);
        mcChart.render();
    }
}

// ── Breakdown table ─────────────────────────────────────────────────────────
function mcRenderBreakdown(r) {
    var cur = r.settings.currency;
    var parts = Number(r.settings.sms_parts) === 1;
    var chs = MC_CHANNELS.filter(function (c) { return !r.filters.channel || r.filters.channel === c.key; });

    $('#mc_breakdown_title').text(r.group === 'month' ? 'By Month' : 'By Day');

    var head = '<tr><th>' + (r.group === 'month' ? 'Month' : 'Day') + '</th>';
    chs.forEach(function (c) {
        head += '<th class="text-right"><span class="mc-dot" style="--c:' + c.color + '"></span>' + mcEsc(c.label) + ' Sent</th>' +
                '<th class="text-right">' + mcEsc(c.label) + ' Cost</th>';
    });
    head += '<th class="text-right">Total Sent</th><th class="text-right">Total Cost</th></tr>';
    $('#mc_breakdown_head').html(head);

    var cells = function (byCh) {
        var h = '', sent = 0, cost = 0;
        chs.forEach(function (c) {
            var v = byCh[c.key];
            var extra = [];
            if (c.key === 'sms' && parts && v.units !== v.sent) { extra.push(mcNum(v.units) + ' parts'); }
            if (v.failed + v.skipped) { extra.push(mcNum(v.failed + v.skipped) + ' not sent'); }
            h += '<td class="text-right">' + mcNum(v.sent) + (extra.length ? '<span class="mc-sub">' + extra.join(' · ') + '</span>' : '') + '</td>' +
                 '<td class="text-right">' + mcMoney(v.cost) + '</td>';
            sent += v.sent;
            cost += v.cost;
        });
        return h + '<td class="text-right">' + mcNum(sent) + '</td><td class="text-right mc-cost">' + mcMoney(cost, cur) + '</td>';
    };

    // Newest first in the table; the chart reads left to right.
    var rows = r.periods.slice().reverse().filter(function (p) {
        var any = false;
        chs.forEach(function (c) { var v = p.channels[c.key]; if (v.sent || v.failed || v.skipped) { any = true; } });
        return any;
    });

    if (!rows.length) {
        $('#mc_breakdown').html('<tr><td colspan="' + (chs.length * 2 + 3) + '" class="mc-empty">No messages in this period.</td></tr>');
        $('#mc_breakdown_foot').html('');
        return;
    }

    $('#mc_breakdown').html(rows.map(function (p) {
        var drill = r.group === 'month'
            ? ' class="mc-row" title="Show this month by day" onclick="cdpMcDrillMonth(\'' + mcEsc(p.period) + '\')"'
            : ' class="mc-row" title="Show this day" onclick="cdpMcDrillDay(\'' + mcEsc(p.period) + '\')"';
        return '<tr' + drill + '><td><b>' + mcEsc(mcPeriodLabel(p.period, r.group)) + '</b></td>' + cells(p.channels) + '</tr>';
    }).join(''));
    $('#mc_breakdown_foot').html('<tr><td>Total</td>' + cells(r.totals) + '</tr>');
}

function cdpMcDrillMonth(ym) {
    mcSetMode('month');
    $('#mc_month').val(ym);
    $('#mc_group').val('day');
    $('#mc_quick button').removeClass('is-on');
    cdpMcLoad();
}

function cdpMcDrillDay(d) {
    mcSetMode('day');
    $('#mc_day').val(d);
    $('#mc_quick button').removeClass('is-on');
    cdpMcLoad();
}

// ── By origin ───────────────────────────────────────────────────────────────
function mcRenderSources(r) {
    var cur = r.settings.currency;
    var rows = r.sources || [];
    if (!rows.length) {
        $('#mc_sources').html('<tr><td colspan="6" class="mc-empty">No messages sent in this period.</td></tr>');
        return;
    }
    $('#mc_sources').html(rows.map(function (s) {
        return '<tr class="mc-row" onclick="cdpMcFilterSource(\'' + mcEsc(s.key) + '\')">' +
                   '<td><b>' + mcEsc(s.label) + '</b></td>' +
                   '<td class="text-right">' + mcNum(s.channels.sms) + '</td>' +
                   '<td class="text-right">' + mcNum(s.channels.whatsapp) + '</td>' +
                   '<td class="text-right">' + mcNum(s.channels.email) + '</td>' +
                   '<td class="text-right">' + mcNum(s.sent) + '</td>' +
                   '<td class="text-right mc-cost">' + mcMoney(s.cost, cur) + '</td>' +
               '</tr>';
    }).join(''));
}

// ── Unit costs ──────────────────────────────────────────────────────────────
function cdpMcOpenRates() {
    $('#mcr_error').hide();
    $('#mcr_from').val(MC_TODAY);
    $('#mcr_history').html('<tr><td colspan="5" class="mc-empty"><i class="fa fa-spinner fa-spin"></i></td></tr>');
    $('#mcRatesModal').modal('show');
    $.getJSON(MC_RATES, function (r) { mcFillRates(r); });
}

function mcFillRates(r) {
    if (!r || !r.ok) { return; }
    var cur = r.settings.currency;
    $('#mcr_currency').val(cur);
    $('#mcr_parts').prop('checked', Number(r.settings.sms_parts) === 1);

    MC_CHANNELS.forEach(function (c) {
        var has = (r.rates[c.key] || []).length > 0;
        $('#mcr_' + c.key).val(has ? r.current[c.key] : '');
        $('#mcr_' + c.key + '_cur').text(has ? 'In use today: ' + cur + ' ' + mcRate(r.current[c.key]) : 'Not set yet');
    });

    var hist = [];
    MC_CHANNELS.forEach(function (c) {
        (r.rates[c.key] || []).forEach(function (x) { hist.push($.extend({ label: c.label, color: c.color }, x)); });
    });
    hist.sort(function (a, b) { return a.effective_from < b.effective_from ? 1 : (a.effective_from > b.effective_from ? -1 : a.label.localeCompare(b.label)); });

    if (!hist.length) {
        $('#mcr_history').html('<tr><td colspan="5" class="mc-empty">No prices entered yet.</td></tr>');
        return;
    }
    $('#mcr_history').html(hist.map(function (x) {
        return '<tr>' +
                   '<td><span class="mc-dot" style="--c:' + x.color + '"></span>' + mcEsc(x.label) + '</td>' +
                   '<td>' + mcEsc(x.effective_from) + '</td>' +
                   '<td class="text-right">' + mcEsc(cur) + ' ' + mcRate(x.unit_cost) + '</td>' +
                   '<td class="text-muted"><small>' + mcEsc(x.created_at || '') + '</small></td>' +
                   (MC_CAN_EDIT ? '<td class="text-right"><button type="button" class="btn btn-sm btn-outline-secondary btn-delete" title="Remove this price" onclick="cdpMcDeleteRate(' + Number(x.id) + ')"><i class="fa fa-trash"></i></button></td>' : '') +
               '</tr>';
    }).join(''));
}

function mcRatesError(msg) {
    $('#mcr_error').text(msg || 'Could not save the costs.').show();
}

function cdpMcSaveRates() {
    var data = {
        action: 'save',
        from: $('#mcr_from').val() || MC_TODAY,
        currency: $('#mcr_currency').val() || '',
        sms_parts: $('#mcr_parts').is(':checked') ? 1 : 0
    };
    MC_CHANNELS.forEach(function (c) { data[c.key] = $('#mcr_' + c.key).val(); });

    $('#mcr_save').prop('disabled', true);
    $.ajax({ url: MC_RATES, type: 'POST', data: data, dataType: 'json' })
        .done(function (r) {
            if (!r || !r.ok) { mcRatesError(r && r.error); return; }
            mcFillRates(r);
            $('#mcRatesModal').modal('hide');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Costs saved', timer: 1400, showConfirmButton: false });
            }
            cdpMcLoad();
        })
        .fail(function (x) { mcRatesError(x.responseJSON && x.responseJSON.error); })
        .always(function () { $('#mcr_save').prop('disabled', false); });
}

function cdpMcDeleteRate(id) {
    var go = function () {
        $.ajax({ url: MC_RATES, type: 'POST', data: { action: 'delete', id: id }, dataType: 'json' })
            .done(function (r) {
                if (!r || !r.ok) { mcRatesError(r && r.error); return; }
                mcFillRates(r);
                cdpMcLoad();
            })
            .fail(function (x) { mcRatesError(x.responseJSON && x.responseJSON.error); });
    };
    if (typeof Swal === 'undefined') { go(); return; }
    Swal.fire({
        title: 'Remove this price?',
        text: 'Messages it covered are priced again from the remaining history.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', cancelButtonText: 'Cancel'
    }).then(function (res) { if (res.isConfirmed) { go(); } });
}
