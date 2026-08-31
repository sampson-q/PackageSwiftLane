"use strict";

// ============================================================================
// Staff Productivity — active hours and output per staff member.
//
// One filter set, one server call. The tiles, both charts and the table are all
// rendered from the same payload, so they cannot disagree with each other.
// ============================================================================

var SP_URL = 'ajax/reports/staff_productivity_ajax.php';
var SP_CSV = 'ajax/reports/staff_productivity_export_ajax.php';

var spCharts = { day: null, hour: null };

$(function () {
    $('#sp_quick').on('click', 'button', function () {
        $('#sp_quick button').removeClass('is-on');
        $(this).addClass('is-on');
        spApplyRange($(this).data('range'));
        cdpSpLoad();
    });

    $('#sp_from, #sp_to, #sp_user, #sp_gap').on('change', function () { cdpSpLoad(); });

    cdpSpLoad();
});

// ── Filters ─────────────────────────────────────────────────────────────────
function spFilters() {
    return {
        from:    $('#sp_from').val() || '',
        to:      $('#sp_to').val() || '',
        user_id: $('#sp_user').val() || '',
        gap:     $('#sp_gap').val() || 15
    };
}

function spFmt(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth() + 1).padStart(2, '0') + '-' +
           String(d.getDate()).padStart(2, '0');
}

function spApplyRange(range) {
    var today = new Date();

    if (range === 'all') {
        $('#sp_from').val('');
        $('#sp_to').val('');
        return;
    }
    if (range === 'today') {
        $('#sp_from').val(spFmt(today));
        $('#sp_to').val(spFmt(today));
        return;
    }
    if (range === 'month') {
        $('#sp_from').val(spFmt(new Date(today.getFullYear(), today.getMonth(), 1)));
        $('#sp_to').val(spFmt(today));
        return;
    }
    if (range === 'lastmonth') {
        $('#sp_from').val(spFmt(new Date(today.getFullYear(), today.getMonth() - 1, 1)));
        $('#sp_to').val(spFmt(new Date(today.getFullYear(), today.getMonth(), 0)));
        return;
    }
    var days = parseInt(range, 10) || 30;
    var from = new Date(today.getTime());
    from.setDate(from.getDate() - (days - 1));
    $('#sp_from').val(spFmt(from));
    $('#sp_to').val(spFmt(today));
}

function cdpSpExport() {
    window.location.href = SP_CSV + '?' + $.param(spFilters());
}

// ── Load ────────────────────────────────────────────────────────────────────
function cdpSpLoad() {
    $('#sp_loader').show();

    $.getJSON(SP_URL, spFilters(), function (r) {
        $('#sp_loader').hide();
        if (!r || !r.ok) {
            $('#sp_rows').html('<tr><td colspan="13" class="sp-empty">Could not load the report.</td></tr>');
            return;
        }
        spRenderKpis(r.totals, r.rows);
        spRenderDayChart(r.by_day);
        spRenderHourChart(r.by_hour);
        spRenderRows(r.rows);
    }).fail(function () {
        $('#sp_loader').hide();
        $('#sp_rows').html('<tr><td colspan="13" class="sp-empty">Could not load the report.</td></tr>');
    });
}

function spNum(n) {
    return Number(n || 0).toLocaleString();
}

function spEsc(s) {
    return $('<div>').text(s == null ? '' : s).html();
}

// Hours as "6h 12m" — the same shape the server uses in the table and the CSV.
function spHours(h) {
    var total = Math.round(Number(h || 0) * 60);
    var hh = Math.floor(total / 60);
    var mm = total % 60;
    if (!hh && !mm) return '—';
    return (hh ? hh + 'h ' : '') + mm + 'm';
}

// ── Headline ────────────────────────────────────────────────────────────────
function spRenderKpis(t, rows) {
    var busiest = (rows && rows.length) ? rows[0] : null;

    var tile = function (color, label, value, sub) {
        return '<div class="sp-kpi" style="--c:' + color + '">' +
                   '<div class="sp-k">' + label + '</div>' +
                   '<div class="sp-kpi__v">' + value + '</div>' +
                   '<div class="sp-kpi__s">' + sub + '</div>' +
               '</div>';
    };

    $('#sp_kpis').html(
        tile('#336aea', 'Total Active Hours', spHours(t.active_hours), 'Across all staff shown') +
        tile('#b4770d', 'Total Idle Hours',
             t.idle_rows ? spHours(t.idle_hours) : '—',
             t.idle_rows ? 'Breaks inside the working window · ' + t.idle_rows + ' of ' + t.staff + ' staff'
                         : 'Not enough detail recorded yet') +
        tile('#7d8fa9', 'Utilisation',
             t.idle_rows ? t.utilisation + '%' : '—',
             t.idle_rows ? 'Active as a share of the window' : 'Needs the activity trail') +
        tile('#0aa699', 'Packages Added', spNum(t.packages_added), 'Registered in this period') +
        tile('#9b6ef3', 'Packages Per Hour', t.per_hour, 'Across all staff shown') +
        tile('#e8a33d', 'Staff Active', spNum(t.staff), 'Of the staff accounts on file') +
        tile('#8a94a6', 'Days With Activity', spNum(t.days_worked), 'Days anyone was working') +
        tile('#f62d51', 'Most Hours', busiest ? spEsc(busiest.name) : '—',
             busiest ? spHours(busiest.active_hours) + ' · ' + spNum(busiest.packages_added) + ' packages' : 'No activity')
    );
}

// ── Charts ──────────────────────────────────────────────────────────────────
function spRenderDayChart(days) {
    var el = document.querySelector('#sp_chart_day');
    if (!el || typeof ApexCharts === 'undefined') return;

    days = days || [];
    var options = {
        chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        series: [
            { name: 'Active Hours', type: 'column', data: days.map(function (d) { return d.hours; }) },
            { name: 'Idle Hours',   type: 'column', data: days.map(function (d) { return d.idle; }) },
            { name: 'Packages Added', type: 'line', data: days.map(function (d) { return d.packages; }) }
        ],
        xaxis: {
            categories: days.map(function (d) { return d.date; }),
            labels: { style: { colors: '#99a2b1', fontSize: '11px' }, rotate: -45, hideOverlappingLabels: true },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: [
            { seriesName: 'Active Hours',
              title: { text: 'Hours', style: { color: '#99a2b1', fontWeight: 600 } },
              labels: { style: { colors: '#99a2b1', fontSize: '11px' } } },
            { seriesName: 'Active Hours', show: false },
            { opposite: true, seriesName: 'Packages Added',
              title: { text: 'Packages', style: { color: '#99a2b1', fontWeight: 600 } },
              labels: { style: { colors: '#99a2b1', fontSize: '11px' } } }
        ],
        colors: ['#336aea', '#e3b765', '#0aa699'],
        stroke: { width: [0, 0, 2.5], curve: 'smooth' },
        plotOptions: { bar: { columnWidth: '70%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', markers: { width: 9, height: 9 } },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
        tooltip: { shared: true, y: { formatter: function (v, o) {
            return o.seriesIndex === 2 ? spNum(v) : spHours(v);
        } } },
        noData: { text: 'No activity in this period' }
    };

    if (spCharts.day) {
        spCharts.day.updateOptions(options, true, true);
    } else {
        spCharts.day = new ApexCharts(el, options);
        spCharts.day.render();
    }
}

function spRenderHourChart(hours) {
    var el = document.querySelector('#sp_chart_hour');
    if (!el || typeof ApexCharts === 'undefined') return;

    hours = hours || [];
    var options = {
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        series: [{ name: 'Actions', data: hours.map(function (h) { return h.events; }) }],
        xaxis: {
            categories: hours.map(function (h) { return h.hour; }),
            labels: { style: { colors: '#99a2b1', fontSize: '10px' }, rotate: -45, hideOverlappingLabels: true },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: { labels: { style: { colors: '#99a2b1', fontSize: '11px' } } },
        colors: ['#9b6ef3'],
        plotOptions: { bar: { columnWidth: '65%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
        noData: { text: 'No activity in this period' }
    };

    if (spCharts.hour) {
        spCharts.hour.updateOptions(options, true, true);
    } else {
        spCharts.hour = new ApexCharts(el, options);
        spCharts.hour.render();
    }
}

// ── Per-staff table ─────────────────────────────────────────────────────────
function spRenderRows(rows) {
    rows = rows || [];
    if (!rows.length) {
        $('#sp_rows').html('<tr><td colspan="13" class="sp-empty">No staff activity in this period.</td></tr>');
        return;
    }

    var max = Math.max.apply(null, rows.map(function (r) { return r.active_hours; })) || 1;

    $('#sp_rows').html(rows.map(function (r) {
        var pct = Math.max(2, Math.round((r.active_hours / max) * 100));

        // Hours drawn wholly or partly from the period before the activity log
        // existed are understated — only package actions were recorded then, so
        // a quiet day collapses to a minute. Say so on the row.
        var flag = '';
        if (r.coverage === 'partial') {
            flag = '<span class="sp-pill sp-pill--thin" title="All of this person\'s activity in ' +
                   'this period predates the activity trail, so only package actions are known ' +
                   'and the hours are understated.">low detail</span>';
        } else if (r.coverage === 'mixed') {
            flag = '<span class="sp-pill sp-pill--thin" title="Part of this period predates the ' +
                   'activity trail (' + spNum(r.events_history) + ' of ' + spNum(r.events) +
                   ' actions), so the hours are understated for those days.">part detail</span>';
        }

        return '<tr class="sp-row" onclick="cdpSpDetail(' + r.user_id + ')">' +
                   '<td><div class="sp-name">' + spEsc(r.name) +
                       (r.is_active ? '' : '<span class="sp-pill sp-pill--off">Inactive</span>') + '</div>' +
                       '<small class="text-muted">' + spEsc(r.username) + '</small></td>' +
                   '<td class="text-muted">' + spEsc(r.role) + '</td>' +
                   '<td class="text-right"><span class="sp-hours">' + spEsc(r.active_label) + '</span>' + flag +
                       '<div class="sp-bar"><span style="width:' + pct + '%"></span></div></td>' +
                   '<td class="text-right text-muted">' +
                       (r.idle_reliable ? spEsc(r.idle_label) : '<span title="Not enough detail was ' +
                        'recorded in this period to tell working time from breaks.">&mdash;</span>') + '</td>' +
                   '<td class="text-right">' +
                       (r.idle_reliable && r.span_seconds > 0 ? r.utilisation + '%' : '&mdash;') + '</td>' +
                   '<td class="text-right">' + spNum(r.days_worked) + '</td>' +
                   '<td class="text-right">' + spHours(r.avg_hours_day) + '</td>' +
                   '<td class="text-right"><b>' + spNum(r.packages_added) + '</b></td>' +
                   '<td class="text-right text-muted">' + r.per_hour + '</td>' +
                   '<td class="text-right text-muted">' + spNum(r.packages_edited) + '</td>' +
                   '<td class="text-right text-muted">' + spNum(r.logins) + '</td>' +
                   '<td class="text-muted"><small>' + spEsc(r.first_at || '—') + '</small></td>' +
                   '<td class="text-muted"><small>' + spEsc(r.last_at || '—') + '</small></td>' +
               '</tr>';
    }).join(''));
}

// ── Drill-down ──────────────────────────────────────────────────────────────
function cdpSpDetail(userId) {
    $('#sp_detail_body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#spDetailModal').modal('show');

    var params = spFilters();
    params.action = 'detail';
    params.detail_user = userId;

    $.get(SP_URL, params, function (html) {
        $('#sp_detail_body').html(html);
    }).fail(function () {
        $('#sp_detail_body').html('<div class="alert alert-danger mb-0">Could not load that breakdown.</div>');
    });
}
