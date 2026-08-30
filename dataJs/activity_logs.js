"use strict";

// ============================================================================
// Activity Logs — filters, statistics and the entry trail.
//
// One filter set drives all three server endpoints. Whenever the filters change
// we reload the stats and the table together, so a tile can never describe a
// different set of rows than the table below it.
// ============================================================================

var AL_ROWS  = 'ajax/reports/activity_logs_ajax.php';
var AL_STATS = 'ajax/reports/activity_logs_stats_ajax.php';
var AL_CSV   = 'ajax/reports/activity_logs_export_ajax.php';

var alPage = 1;
var alSearchTimer = null;
var alCharts = { time: null, verb: null };

$(function () {
    // Quick date ranges
    $('#al_quick').on('click', 'button', function () {
        $('#al_quick button').removeClass('is-on');
        $(this).addClass('is-on');
        alApplyRange($(this).data('range'));
        cdpAlGo(1);
    });

    // Any dropdown change reloads immediately; free text is debounced.
    $('#al_user, #al_role, #al_module, #al_verb, #al_action, #al_status, #al_outcome, #al_per_page, #al_views, #al_from, #al_to')
        .on('change', function () { cdpAlGo(1); });

    $('#al_search').on('keyup', function () {
        clearTimeout(alSearchTimer);
        alSearchTimer = setTimeout(function () { cdpAlGo(1); }, 350);
    });

    cdpAlGo(1);
});

// ── Filter state ────────────────────────────────────────────────────────────
function alFilters() {
    return {
        from:       $('#al_from').val() || '',
        to:         $('#al_to').val() || '',
        user_id:    $('#al_user').val() || 0,
        role_id:    $('#al_role').val() || 0,
        module:     $('#al_module').val() || '',
        verb:       $('#al_verb').val() || '',
        action:     $('#al_action').val() || '',
        status_id:  $('#al_status').val() || 0,
        outcome:    $('#al_outcome').val() || '',
        search:     $('#al_search').val() || '',
        show_views: $('#al_views').is(':checked') ? 1 : 0
    };
}

function alApplyRange(range) {
    var today = new Date();
    var fmt = function (d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    };

    if (range === 'all') {
        $('#al_from').val('');
        $('#al_to').val('');
        return;
    }
    if (range === 'today') {
        $('#al_from').val(fmt(today));
        $('#al_to').val(fmt(today));
        return;
    }
    if (range === 'month') {
        $('#al_from').val(fmt(new Date(today.getFullYear(), today.getMonth(), 1)));
        $('#al_to').val(fmt(today));
        return;
    }
    var days = parseInt(range, 10) || 30;
    var from = new Date(today.getTime());
    from.setDate(from.getDate() - (days - 1));
    $('#al_from').val(fmt(from));
    $('#al_to').val(fmt(today));
}

function cdpAlReset() {
    $('#al_user, #al_role, #al_status').val(0);
    $('#al_module, #al_verb, #al_action, #al_outcome, #al_search').val('');
    $('#al_views').prop('checked', false);
    $('#al_per_page').val(50);
    $('#al_quick button').removeClass('is-on');
    $('#al_quick button[data-range="30"]').addClass('is-on');
    alApplyRange(30);
    cdpAlGo(1);
}

function cdpAlExport() {
    var q = $.param(alFilters());
    window.location.href = AL_CSV + '?' + q;
}

// ── Load ────────────────────────────────────────────────────────────────────
function cdpAlGo(page) {
    alPage = page || 1;
    alLoadRows();
    alLoadStats();
}

function alLoadRows() {
    var params = alFilters();
    params.page = alPage;
    params.per_page = $('#al_per_page').val() || 50;

    $('#al_loader').show();
    $.ajax({
        url: AL_ROWS,
        data: params,
        cache: false,
        success: function (html) {
            $('#al_rows').html(html);
            $('#al_loader').hide();
        },
        error: function () {
            $('#al_rows').html('<div class="alert alert-danger mb-0">Could not load the activity trail. Please refresh and try again.</div>');
            $('#al_loader').hide();
        }
    });
}

function alLoadStats() {
    $.getJSON(AL_STATS, alFilters(), function (r) {
        if (!r || !r.ok) return;
        alRenderKpis(r.headline);
        alRenderTimeline(r.timeline);
        alRenderVerbs(r.verbs);
        alRenderBars('#al_modules', r.modules, '#336aea');
        alRenderBars('#al_statuses', r.statuses, '#9b6ef3');
        alRenderBars('#al_roles', r.roles, '#0aa699');
        alRenderActors(r.actors_top);
    }).fail(function () {
        $('#al_kpis').html('<div class="alert alert-warning mb-0" style="grid-column:1/-1">Statistics are unavailable right now.</div>');
    });
}

function alNum(n) {
    return Number(n || 0).toLocaleString();
}

function alEsc(s) {
    return $('<div>').text(s == null ? '' : s).html();
}

// ── Headline tiles ──────────────────────────────────────────────────────────
function alRenderKpis(h) {
    var tile = function (color, label, value, sub) {
        return '<div class="al-kpi" style="--c:' + color + '">' +
                   '<div class="al-kpi__k">' + label + '</div>' +
                   '<div class="al-kpi__v">' + value + '</div>' +
                   '<div class="al-kpi__s">' + sub + '</div>' +
               '</div>';
    };

    $('#al_kpis').html(
        tile('#336aea', 'Total Actions', alNum(h.total), 'In the selected range') +
        tile('#0aa699', 'People Active', alNum(h.actors), 'Distinct users') +
        tile('#9b6ef3', 'Records Changed', alNum(h.writes), 'Creates, edits, status moves') +
        tile('#f62d51', 'Deletions', alNum(h.deletions), 'Records removed') +
        tile('#b4770d', 'Blocked Attempts', alNum(h.denied + h.failures), 'Denied or failed') +
        tile('#8a94a6', 'Last Activity', h.last_at && h.last_at !== '—' ? h.last_at.split(' ')[1] : '—',
             h.last_at && h.last_at !== '—' ? h.last_at.split(' ')[0] : 'Nothing recorded')
    );

    $('#al_range_note').text(
        h.first_at && h.first_at !== '—' ? '(' + h.first_at + ' → ' + h.last_at + ')' : ''
    );
}

// ── Charts ──────────────────────────────────────────────────────────────────
function alRenderTimeline(points) {
    var el = document.querySelector('#al_chart_time');
    if (!el || typeof ApexCharts === 'undefined') return;

    var options = {
        chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        series: [{ name: 'Actions', data: (points || []).map(function (p) { return p.count; }) }],
        xaxis: {
            categories: (points || []).map(function (p) { return p.date; }),
            labels: { style: { colors: '#99a2b1', fontSize: '11px' }, rotate: -45, hideOverlappingLabels: true },
            axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: { labels: { style: { colors: '#99a2b1', fontSize: '11px' } } },
        colors: ['#336aea'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.32, opacityTo: 0.02 } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
        noData: { text: 'No activity in this range' }
    };

    if (alCharts.time) {
        alCharts.time.updateOptions(options);
    } else {
        alCharts.time = new ApexCharts(el, options);
        alCharts.time.render();
    }
}

function alRenderVerbs(verbs) {
    var el = document.querySelector('#al_chart_verb');
    if (!el || typeof ApexCharts === 'undefined') return;

    verbs = verbs || [];
    var options = {
        chart: { type: 'donut', height: 260, fontFamily: 'Public Sans, sans-serif' },
        series: verbs.map(function (v) { return v.count; }),
        labels: verbs.map(function (v) { return v.label; }),
        colors: verbs.map(function (v) { return v.color; }),
        legend: { position: 'bottom', fontSize: '12px', markers: { width: 9, height: 9 } },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%', labels: {
            show: true,
            total: { show: true, label: 'Actions', fontSize: '12px', color: '#8a94a6' }
        } } } },
        noData: { text: 'No activity in this range' }
    };

    if (alCharts.verb) {
        alCharts.verb.updateOptions(options, true, true);
    } else {
        alCharts.verb = new ApexCharts(el, options);
        alCharts.verb.render();
    }
}

// ── Breakdown bars ──────────────────────────────────────────────────────────
function alRenderBars(sel, items, color) {
    items = items || [];
    if (!items.length) {
        $(sel).html('<div class="al-empty">Nothing to show for these filters.</div>');
        return;
    }
    var max = Math.max.apply(null, items.map(function (i) { return i.count; })) || 1;

    $(sel).html(items.map(function (i) {
        var pct = Math.max(2, Math.round((i.count / max) * 100));
        return '<div class="al-bar">' +
                   '<div class="al-bar__top">' +
                       '<span class="al-bar__name">' + alEsc(i.label) + '</span>' +
                       '<span class="al-bar__n">' + alNum(i.count) + '</span>' +
                   '</div>' +
                   '<div class="al-bar__track"><div class="al-bar__fill" style="width:' + pct + '%;--c:' + color + '"></div></div>' +
               '</div>';
    }).join(''));
}

// ── Most active users ───────────────────────────────────────────────────────
function alRenderActors(actors) {
    actors = actors || [];
    if (!actors.length) {
        $('#al_actors').html('<tr><td colspan="8" class="al-empty">No activity for these filters.</td></tr>');
        return;
    }

    $('#al_actors').html(actors.map(function (a) {
        return '<tr class="al-row" onclick="cdpAlFilterUser(' + a.id + ')">' +
                   '<td class="al-actor">' + alEsc(a.name) + '</td>' +
                   '<td class="text-muted">' + alEsc(a.role) + '</td>' +
                   '<td class="text-right">' + alNum(a.creates) + '</td>' +
                   '<td class="text-right">' + alNum(a.updates) + '</td>' +
                   '<td class="text-right">' + alNum(a.deletes) + '</td>' +
                   '<td class="text-right">' + alNum(a.statuses) + '</td>' +
                   '<td class="text-right"><b>' + alNum(a.count) + '</b></td>' +
                   '<td class="text-muted"><small>' + alEsc(a.last_at) + '</small></td>' +
               '</tr>';
    }).join(''));
}

function cdpAlFilterUser(userId) {
    $('#al_user').val(String(userId));
    cdpAlGo(1);
    $('html, body').animate({ scrollTop: $('#al_rows').offset().top - 90 }, 250);
}

// ── Entry detail ────────────────────────────────────────────────────────────
function cdpAlDetail(id) {
    $('#al_detail_body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#alDetailModal').modal('show');

    $.get(AL_ROWS, { action: 'detail', id: id }, function (html) {
        $('#al_detail_body').html(html);
    }).fail(function () {
        $('#al_detail_body').html('<div class="alert alert-danger mb-0">Could not load that entry.</div>');
    });
}
