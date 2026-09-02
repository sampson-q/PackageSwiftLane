"use strict";

// ============================================================================
// Staff Productivity — check-ins, active / idle time and output per staff
// member.
//
// One filter set, one server call. The tiles, the charts, the heatmap and the
// table are all rendered from the same payload, so they cannot disagree.
// Drill-down: staff row → that person's days → one day's timeline.
// ============================================================================

var SP_URL      = 'ajax/reports/staff_productivity_ajax.php';
var SP_CSV      = 'ajax/reports/staff_productivity_export_ajax.php';
var SP_SETTINGS = 'ajax/reports/staff_productivity_settings_ajax.php';

var spCharts = { day: null, hour: null, heat: null, detailHeat: null };
var spSettings = null;

$(function () {
    $('#sp_quick').on('click', 'button', function () {
        $('#sp_quick button').removeClass('is-on');
        $(this).addClass('is-on');
        spApplyRange($(this).data('range'));
        cdpSpLoad();
    });

    $('#sp_from, #sp_to, #sp_user').on('change', function () { cdpSpLoad(); });

    // The detail heatmap lives inside the modal; drop it when the modal closes
    // so the next open renders fresh.
    $('#spDetailModal').on('hidden.bs.modal', function () {
        if (spCharts.detailHeat) { spCharts.detailHeat.destroy(); spCharts.detailHeat = null; }
    });

    cdpSpLoad();
});

// ── Filters ─────────────────────────────────────────────────────────────────
function spFilters() {
    return {
        from:    $('#sp_from').val() || '',
        to:      $('#sp_to').val() || '',
        user_id: $('#sp_user').val() || ''
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
        spSettings = r.settings || spSettings;
        spRenderSettingsLine(r.settings, r.presence);
        spRenderKpis(r.totals, r.rows);
        spRenderDayChart(r.by_day);
        spRenderHourChart(r.by_hour);
        spRenderHeat('#sp_chart_heat', r.heat, 'heat');
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

function spMinutes(m) {
    m = Math.round(Number(m || 0));
    if (m <= 0) return '—';
    var hh = Math.floor(m / 60), mm = m % 60;
    return (hh ? hh + 'h ' : '') + mm + 'm';
}

// ── Settings line under the filters ─────────────────────────────────────────
function spRenderSettingsLine(s, presenceTable) {
    if (!s) return;
    var scope = s.checkin_scope === 'create_only' ? 'first package created' : 'first package created or edited';
    var beacon = presenceTable === false
        ? '<span class="sp-pill sp-pill--thin">presence table not deployed</span>'
        : (Number(s.beacon_enabled) === 1
            ? '<span class="sp-pill sp-pill--ok">presence tracking on</span>'
            : '<span class="sp-pill sp-pill--off">presence tracking off</span>');
    $('#sp_settings_line').html(
        'Check-in: <b>' + scope + '</b> · Idle after <b>' + spNum(s.idle_minutes) + ' min</b> without input · ' +
        'Legacy gap <b>' + spNum(s.gap_minutes) + ' min</b> · Reports every <b>' + spNum(s.ping_seconds) + 's</b> ' + beacon
    );
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
        tile('#336aea', 'Total Active', spHours(t.active_hours), 'Across all staff shown') +
        tile('#b4770d', 'Total Idle',
             t.idle_rows ? spHours(t.idle_hours) : '—',
             t.idle_rows ? 'Pauses inside the working window · ' + t.idle_rows + ' of ' + t.staff + ' staff measurable'
                         : 'Not enough detail recorded yet') +
        tile('#7d8fa9', 'Utilisation',
             t.idle_rows ? t.utilisation + '%' : '—',
             t.idle_rows ? 'Active share of the working window' : 'Needs recorded activity') +
        tile('#0aa699', 'Packages Created', spNum(t.packages_added), spNum(t.packages_edited) + ' edited') +
        tile('#4258c9', 'Check-Ins', spNum(t.checkins),
             'Days that started with a package · ' + spNum(t.staff_days) + ' staff-days in total') +
        tile('#9b6ef3', 'Packages Per Hour', t.per_hour, 'Per active hour, all staff') +
        tile('#e8a33d', 'Staff Active', spNum(t.staff), 'Of the staff accounts on file') +
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
            { name: 'Packages Created', type: 'line', data: days.map(function (d) { return d.packages; }) }
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
            { opposite: true, seriesName: 'Packages Created',
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
        chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        series: [
            { name: 'Active Hours', type: 'column', data: hours.map(function (h) { return h.hours; }) },
            { name: 'Packages Created', type: 'line', data: hours.map(function (h) { return h.packages; }) }
        ],
        xaxis: {
            categories: hours.map(function (h) { return h.hour; }),
            labels: { style: { colors: '#99a2b1', fontSize: '10px' }, rotate: -45, hideOverlappingLabels: true },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: [
            { labels: { style: { colors: '#99a2b1', fontSize: '11px' } } },
            { opposite: true, labels: { style: { colors: '#99a2b1', fontSize: '11px' } } }
        ],
        colors: ['#336aea', '#0aa699'],
        stroke: { width: [0, 2.5], curve: 'smooth' },
        plotOptions: { bar: { columnWidth: '65%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', markers: { width: 9, height: 9 } },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
        tooltip: { shared: true, y: { formatter: function (v, o) {
            return o.seriesIndex === 1 ? spNum(v) : spHours(v);
        } } },
        noData: { text: 'No activity in this period' }
    };

    if (spCharts.hour) {
        spCharts.hour.updateOptions(options, true, true);
    } else {
        spCharts.hour = new ApexCharts(el, options);
        spCharts.hour.render();
    }
}

// Date × hour heatmap of active minutes. One row per date, oldest at the top.
// Very long ranges are trimmed to the most recent 90 days so the rows stay
// readable; the card says so.
function spRenderHeat(selector, heat, key) {
    var el = document.querySelector(selector);
    if (!el || typeof ApexCharts === 'undefined') return;

    heat = heat || [];
    var trimmed = false;
    if (heat.length > 90) {
        heat = heat.slice(heat.length - 90);
        trimmed = true;
    }
    var note = $(selector).closest('.sp-card').find('.sp-heat-note');
    if (note.length) {
        note.text(trimmed ? 'Showing the most recent 90 days of the selection.' : '');
    }

    var hours = [];
    for (var h = 0; h < 24; h++) hours.push(String(h).padStart(2, '0'));

    var series = heat.map(function (row) {
        return { name: row.date, data: row.minutes.map(function (m, i) { return { x: hours[i], y: m }; }) };
    });
    // ApexCharts draws the first series at the bottom; reverse so the oldest
    // date sits at the top and the list reads downwards.
    series.reverse();

    var options = {
        chart: { type: 'heatmap', height: Math.max(160, 22 * series.length + 70), toolbar: { show: false },
                 fontFamily: 'Public Sans, sans-serif', animations: { enabled: false } },
        series: series,
        dataLabels: { enabled: false },
        stroke: { width: 1, colors: ['#fff'] },
        plotOptions: {
            heatmap: {
                radius: 2,
                enableShades: false,
                colorScale: {
                    ranges: [
                        { from: 0,  to: 0,  color: '#eef1f6', name: 'none' },
                        { from: 1,  to: 10, color: '#d5e1fb', name: '1–10 min' },
                        { from: 11, to: 25, color: '#a9c2f6', name: '11–25 min' },
                        { from: 26, to: 45, color: '#6f97ee', name: '26–45 min' },
                        { from: 46, to: 100000, color: '#2d5fdb', name: '46–60 min' }
                    ]
                }
            }
        },
        xaxis: { labels: { style: { colors: '#99a2b1', fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false },
                 title: { text: 'Hour of day', style: { color: '#99a2b1', fontWeight: 600, fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6b7788', fontSize: '10px' } } },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', markers: { width: 9, height: 9 } },
        tooltip: { y: { formatter: function (v) { return spMinutes(v) + ' active'; } } },
        noData: { text: 'No activity in this period' }
    };

    if (spCharts[key]) {
        spCharts[key].destroy();
        spCharts[key] = null;
    }
    spCharts[key] = new ApexCharts(el, options);
    spCharts[key].render();
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

        var flag = '';
        if (r.coverage === 'presence') {
            flag = '<span class="sp-pill sp-pill--ok" title="Presence data on every day shown: idle is measured from keyboard, mouse and screen input.">presence</span>';
        } else if (r.coverage === 'partial') {
            flag = '<span class="sp-pill sp-pill--thin" title="All of this person\'s activity in this period predates the activity trail, so only package actions are known and the hours are understated.">low detail</span>';
        } else if (r.coverage === 'mixed') {
            flag = '<span class="sp-pill sp-pill--thin" title="' + spNum(r.history_days) + ' of ' + spNum(r.days_worked) +
                   ' days predate the activity trail; the idle figure covers the other ' + spNum(r.idle_days) + '.">part detail</span>';
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
                   '<td class="text-right">' + spNum(r.checkins) + '</td>' +
                   '<td class="text-right">' + spHours(r.avg_hours_day) + '</td>' +
                   '<td class="text-right"><b>' + spNum(r.packages_added) + '</b></td>' +
                   '<td class="text-right text-muted">' + r.per_hour + '</td>' +
                   '<td class="text-right text-muted">' + spNum(r.packages_edited) + '</td>' +
                   '<td class="text-muted"><small>' + spEsc(r.first_at || '—') + '</small></td>' +
                   '<td class="text-muted"><small>' + spEsc(r.last_at || '—') + '</small></td>' +
               '</tr>';
    }).join(''));
}

// ── Drill-down: one person's days ───────────────────────────────────────────
function cdpSpDetail(userId) {
    $('#sp_detail_body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#spDetailModal .modal-title').text('Day-By-Day Activity');
    $('#spDetailModal').modal('show');

    if (spCharts.detailHeat) { spCharts.detailHeat.destroy(); spCharts.detailHeat = null; }

    var params = spFilters();
    params.action = 'detail';
    params.detail_user = userId;

    $.get(SP_URL, params, function (html) {
        $('#sp_detail_body').html(html);
        var heatEl = document.querySelector('#sp_detail_heat');
        if (heatEl) {
            var heat = [];
            try { heat = JSON.parse(heatEl.getAttribute('data-heat') || '[]'); } catch (e) { heat = []; }
            spRenderHeat('#sp_detail_heat', heat, 'detailHeat');
        }
    }).fail(function () {
        $('#sp_detail_body').html('<div class="alert alert-danger mb-0">Could not load that breakdown.</div>');
    });
}

// ── Drill-down: one person's day ────────────────────────────────────────────
function cdpSpDay(userId, day) {
    if (spCharts.detailHeat) { spCharts.detailHeat.destroy(); spCharts.detailHeat = null; }
    $('#sp_detail_body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#spDetailModal .modal-title').text('Timeline · ' + day);
    $('#spDetailModal').modal('show');

    $.get(SP_URL, { action: 'day', detail_user: userId, day: day }, function (html) {
        $('#sp_detail_body').html(html);
    }).fail(function () {
        $('#sp_detail_body').html('<div class="alert alert-danger mb-0">Could not load that day.</div>');
    });
}

// ── Settings ────────────────────────────────────────────────────────────────
function cdpSpOpenSettings() {
    var fill = function (s) {
        $('#sps_idle').val(s.idle_minutes);
        $('#sps_gap').val(s.gap_minutes);
        $('#sps_scope').val(s.checkin_scope);
        $('#sps_ping').val(s.ping_seconds);
        $('#sps_beacon').prop('checked', Number(s.beacon_enabled) === 1);
        $('#sps_error').hide().text('');
        $('#spSettingsModal').modal('show');
    };
    if (spSettings) { fill(spSettings); return; }
    $.getJSON(SP_SETTINGS, function (r) { if (r && r.ok) { spSettings = r.settings; fill(r.settings); } });
}

function cdpSpSaveSettings() {
    var data = {
        idle_minutes:   $('#sps_idle').val(),
        gap_minutes:    $('#sps_gap').val(),
        checkin_scope:  $('#sps_scope').val(),
        ping_seconds:   $('#sps_ping').val(),
        beacon_enabled: $('#sps_beacon').is(':checked') ? 1 : 0
    };
    $('#sps_save').prop('disabled', true);
    $.post(SP_SETTINGS, data, function (r) {
        $('#sps_save').prop('disabled', false);
        if (!r || !r.ok) {
            $('#sps_error').text((r && r.error) || 'Could not save the settings.').show();
            return;
        }
        spSettings = r.settings;
        $('#spSettingsModal').modal('hide');
        cdpSpLoad();
    }, 'json').fail(function () {
        $('#sps_save').prop('disabled', false);
        $('#sps_error').text('Could not save the settings.').show();
    });
}
