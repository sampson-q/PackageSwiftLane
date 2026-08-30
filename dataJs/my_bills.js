"use strict";

// ============================================================================
// My Bills — customer self-service payments.
// The server owns every figure here: the checkout amount is computed from
// stored prices, never sent up from this page. What we post is only WHICH
// packages the customer ticked.
// ============================================================================

var MB_URL = 'ajax/customer/my_bills_ajax.php';
var mbCanPay = false;
var mbBills = [];          // last payload from the server
var mbFilter = 'all';      // all | owing | settled
var mbSearch = '';
var mbOpen = {};           // cid -> true while a bill row is expanded

$(function () {
    mbLoad();

    $('#mb_filters').on('click', 'button', function () {
        mbFilter = $(this).data('filter');
        $('#mb_filters button').removeClass('is-on');
        $(this).addClass('is-on');
        mbRender();
    });

    $('#mb_search').on('keyup', function () {
        mbSearch = ($(this).val() || '').toString().trim().toLowerCase();
        mbRender();
    });
});

function mbMoney(v) {
    return '₵' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mbEsc(s) {
    return $('<div>').text(s == null ? '' : s).html();
}

function mbDate(s) {
    if (!s) return '';
    var d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

// ── Load ────────────────────────────────────────────────────────────────────
function mbLoad() {
    $('#mb_bills').html('<div class="mb-empty">Loading your bills&hellip;</div>');

    $.getJSON(MB_URL, { action: 'list' }, function (r) {
        if (!r || !r.ok) {
            $('#mb_bills').html('<div class="alert alert-warning">We could not load your bills right now.</div>');
            return;
        }
        mbCanPay = !!r.can_pay;
        mbBills = r.bills || [];

        mbRenderKpis(r);

        if (!r.gateway_up) {
            $('#mb_gateway_warn').html(
                '<div class="alert alert-warning">' +
                '<b>Online payment is unavailable at the moment.</b> Please pay at the office &mdash; ' +
                'the bills below are still accurate.</div>'
            );
        } else {
            $('#mb_gateway_warn').empty();
        }

        mbRender();
    }).fail(function () {
        $('#mb_bills').html('<div class="alert alert-warning">We could not load your bills right now.</div>');
    });
}

// ── Summary strip ───────────────────────────────────────────────────────────
function mbRenderKpis(r) {
    var billed = 0, paid = 0, owingCount = 0;

    mbBills.forEach(function (b) {
        billed += Number(b.billed) || 0;
        paid += Number(b.paid) || 0;
        if (!b.settled) owingCount++;
    });

    var kpi = function (mod, label, value, sub) {
        return '' +
            '<div class="mb-kpi mb-kpi--' + mod + '">' +
                '<div class="mb-kpi__k">' + label + '</div>' +
                '<div class="mb-kpi__v">' + value + '</div>' +
                '<div class="mb-kpi__s">' + sub + '</div>' +
            '</div>';
    };

    $('#mb_kpis').html(
        kpi('owed', 'Total Outstanding', mbMoney(r.total_owed),
            owingCount + (owingCount === 1 ? ' bill still owing' : ' bills still owing')) +
        kpi('billed', 'Total Billed', mbMoney(billed), 'Across all your consolidations') +
        kpi('paid', 'Total Paid', mbMoney(paid), 'Received and confirmed') +
        kpi('count', 'Bills', String(mbBills.length), 'Mobile Money payments only')
    );
}

// ── Bill list ───────────────────────────────────────────────────────────────
function mbRender() {
    var rows = mbBills.filter(function (b) {
        if (mbFilter === 'owing' && b.settled) return false;
        if (mbFilter === 'settled' && !b.settled) return false;
        if (mbSearch) {
            var hay = ((b.consol_no || '') + ' #' + b.cid).toLowerCase();
            if (hay.indexOf(mbSearch) === -1) return false;
        }
        return true;
    });

    if (!rows.length) {
        $('#mb_bills').html(
            '<div class="mb-empty">' +
                '<iconify-icon icon="solar:bill-list-linear"></iconify-icon>' +
                '<h5>' + (mbBills.length ? 'Nothing matches that filter' : 'You have no bills yet') + '</h5>' +
                '<div>' + (mbBills.length
                    ? 'Try a different filter or clear the search box.'
                    : 'Bills appear here once your packages have been consolidated and billed.') + '</div>' +
            '</div>'
        );
        return;
    }

    $('#mb_bills').html(rows.map(mbBillCard).join(''));

    // Re-open whatever was open before the re-render.
    Object.keys(mbOpen).forEach(function (cid) {
        if (mbOpen[cid] && $('#mb_bill_' + cid).length) mbOpenBill(Number(cid));
    });
}

function mbBillCard(b) {
    var badge = b.settled
        ? '<span class="mb-chip mb-chip-paid">Settled</span>'
        : '<span class="mb-chip mb-chip-due">Owing</span>';

    var fig = function (mod, label, value) {
        return '<div class="mb-fig ' + mod + '"><div class="mb-fig__k">' + label + '</div>' +
               '<div class="mb-fig__v">' + value + '</div></div>';
    };

    return '' +
        '<div class="mb-bill" id="mb_bill_' + b.cid + '">' +
            '<div class="mb-bill__head" onclick="mbToggle(' + b.cid + ')">' +
                '<div>' +
                    '<div class="mb-bill__no">Consolidation ' + mbEsc(b.consol_no || ('#' + b.cid)) + ' ' + badge + '</div>' +
                    '<div class="mb-bill__meta">' +
                        (b.billed_at ? 'Billed ' + mbEsc(mbDate(b.billed_at)) : 'Billed') +
                        (b.discount > 0 ? ' &middot; Discount ' + mbMoney(b.discount) : '') +
                    '</div>' +
                '</div>' +
                '<div class="mb-bill__figs">' +
                    fig('', 'Billed', mbMoney(b.billed)) +
                    fig('', 'Paid', mbMoney(b.paid)) +
                    fig('mb-fig--balance', b.settled ? 'Nothing Due' : 'Balance',
                        '<span class="' + (b.settled ? 'mb-settled' : 'mb-owed') + '">' + mbMoney(b.balance) + '</span>') +
                '</div>' +
                '<iconify-icon class="mb-caret" icon="solar:alt-arrow-down-linear"></iconify-icon>' +
            '</div>' +
            '<div class="mb-bill__body" style="display:none"></div>' +
        '</div>';
}

// ── Expand / collapse ───────────────────────────────────────────────────────
function mbToggle(cid) {
    if (mbOpen[cid]) {
        mbOpen[cid] = false;
        $('#mb_bill_' + cid).removeClass('is-open').find('.mb-bill__body').slideUp(150);
        return;
    }
    mbOpen[cid] = true;
    mbOpenBill(cid);
}

function mbOpenBill(cid) {
    var $card = $('#mb_bill_' + cid).addClass('is-open');
    var $body = $card.find('.mb-bill__body');

    if ($body.data('loaded')) {
        $body.slideDown(150);
        return;
    }

    $body.html('<div class="p-3 text-muted">Loading&hellip;</div>').slideDown(150);

    $.getJSON(MB_URL, { action: 'bill', cid: cid }, function (r) {
        if (!r || !r.ok) {
            $body.html('<div class="p-3 text-danger">' + mbEsc((r && r.message) || 'Could not load this bill.') + '</div>');
            return;
        }
        $body.data('loaded', true).html(mbBillBody(r.bill, r.packages));
        mbRecalc(cid);
    });
}

function mbBillBody(b, packages) {
    var payable = packages.filter(function (p) { return !p.cleared; }).length;

    var rows = packages.map(function (p) {
        var dis = p.cleared ? ' disabled checked' : '';
        return '' +
            '<tr class="' + (p.cleared ? 'is-cleared' : '') + '">' +
                '<td style="width:38px;">' +
                    '<input type="checkbox" class="mb-pkg" data-cid="' + b.cid + '" ' +
                           'data-oid="' + p.oid + '" data-share="' + p.share + '"' + dis + '>' +
                '</td>' +
                '<td><b>' + mbEsc(p.tracking) + '</b></td>' +
                '<td class="text-right">' + mbMoney(p.share) + '</td>' +
                '<td class="text-right" style="width:90px;">' +
                    (p.cleared ? '<span class="mb-chip mb-chip-paid">Paid</span>'
                               : '<span class="mb-chip mb-chip-due">Due</span>') +
                '</td>' +
            '</tr>';
    }).join('');

    var head = '' +
        '<table class="table mb-pkg-table">' +
            '<thead><tr>' +
                '<th style="width:38px;">' +
                    (payable && mbCanPay && !b.settled
                        ? '<input type="checkbox" class="mb-all" data-cid="' + b.cid + '" title="Select all">'
                        : '') +
                '</th>' +
                '<th>Package</th>' +
                '<th class="text-right">Amount</th>' +
                '<th class="text-right">Status</th>' +
            '</tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
        '</table>';

    var footer = '';
    if (!b.settled && mbCanPay) {
        footer = '' +
            '<div class="mb-paybar">' +
                '<div>' +
                    '<div class="mb-fig__k">Amount To Pay</div>' +
                    '<div class="mb-paybar__amt" id="mb_amt_' + b.cid + '">' + mbMoney(0) + '</div>' +
                    '<div class="mb-momo-note">' +
                        '<iconify-icon icon="solar:smartphone-2-linear"></iconify-icon> ' +
                        'You will complete this on your phone with Mobile Money.' +
                    '</div>' +
                '</div>' +
                '<button class="btn btn-primary btn-lg" id="mb_pay_' + b.cid + '" onclick="mbCheckout(' + b.cid + ')">' +
                    '<iconify-icon icon="solar:smartphone-2-linear"></iconify-icon> Pay Now' +
                '</button>' +
            '</div>';
    } else if (b.settled) {
        footer = '<div class="mb-paybar text-muted">This bill is fully settled. Nothing further is owed.</div>';
    } else {
        footer = '<div class="mb-paybar text-muted">Please settle this bill at the office.</div>';
    }

    return '' +
        '<div class="px-3 pt-3 text-muted" style="font-size:.8rem">' +
            'Tick the packages you want to pay for and release. Already-paid packages are locked.' +
        '</div>' +
        head +
        footer;
}

// ── Selection maths (preview only — the server recomputes at checkout) ──────
$(document).on('change', '.mb-pkg', function () {
    mbRecalc($(this).data('cid'));
});

$(document).on('change', '.mb-all', function () {
    var cid = $(this).data('cid');
    $('#mb_bill_' + cid + ' .mb-pkg:not(:disabled)').prop('checked', this.checked);
    mbRecalc(cid);
});

function mbRecalc(cid) {
    var total = 0;
    $('#mb_bill_' + cid + ' .mb-pkg:checked:not(:disabled)').each(function () {
        total += Number($(this).data('share')) || 0;
    });
    $('#mb_amt_' + cid).text(mbMoney(total));
    $('#mb_pay_' + cid).prop('disabled', total <= 0);
}

// ── Checkout ────────────────────────────────────────────────────────────────
function mbCheckout(cid) {
    var orders = [];
    $('#mb_bill_' + cid + ' .mb-pkg:checked:not(:disabled)').each(function () {
        orders.push(Number($(this).data('oid')));
    });
    if (!orders.length) {
        Swal.fire('Select Packages', 'Tick at least one package to pay for.', 'info');
        return;
    }

    var $btn = $('#mb_pay_' + cid).prop('disabled', true).text('Starting…');

    $.post(MB_URL, { action: 'checkout', cid: cid, orders: JSON.stringify(orders) }, function (r) {
        try { r = (typeof r === 'string') ? JSON.parse(r) : r; } catch (e) { r = null; }

        if (!r || !r.ok || !r.url) {
            $btn.prop('disabled', false).html('<iconify-icon icon="solar:smartphone-2-linear"></iconify-icon> Pay Now');
            Swal.fire('Payment Not Started', (r && r.message) || 'Please try again.', 'error');
            return;
        }
        // Hand off to the Paystack hosted checkout. From here the outcome is
        // established server-side (payment_return.php, or the webhook if this
        // browser never comes back).
        window.location.href = r.url;
    });
}
