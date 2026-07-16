"use strict";

// ============================================================================
// My Bills — customer self-service payments.
// The server owns every figure here: the checkout amount is computed from
// stored prices, never sent up from this page. What we post is only WHICH
// packages the customer ticked.
// ============================================================================

var MB_URL = 'ajax/customer/my_bills_ajax.php';
var mbCanPay = false;

$(function () {
    mbLoad();
});

function mbMoney(v) {
    return '₵' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mbLoad() {
    $.getJSON(MB_URL, { action: 'list' }, function (r) {
        if (!r || !r.ok) {
            $('#mb_bills').html('<div class="alert alert-warning">We could not load your bills right now.</div>');
            return;
        }
        mbCanPay = !!r.can_pay;
        $('#mb_total_owed').text(mbMoney(r.total_owed));

        if (!r.gateway_up) {
            $('#mb_gateway_warn').html(
                '<div class="alert alert-warning">' +
                'Online payment is unavailable at the moment. Please pay at the office &mdash; ' +
                'your bills below are still accurate.</div>'
            );
        } else {
            $('#mb_gateway_warn').empty();
        }

        if (!r.bills.length) {
            $('#mb_bills').html('<div class="alert alert-info">You have no bills yet.</div>');
            return;
        }
        $('#mb_bills').html(r.bills.map(mbBillCard).join(''));
    });
}

function mbBillCard(b) {
    var badge = b.settled
        ? '<span class="mb-chip mb-chip-paid">Settled</span>'
        : '<span class="mb-chip mb-chip-due">Owing</span>';

    return '' +
        '<div class="mb-bill-card" id="mb_bill_' + b.cid + '">' +
            '<div class="mb-bill-head d-flex justify-content-between align-items-center" ' +
                 'onclick="mbToggle(' + b.cid + ')">' +
                '<div>' +
                    '<div><b>Consolidation ' + (b.consol_no || ('#' + b.cid)) + '</b> ' + badge + '</div>' +
                    '<div class="text-muted" style="font-size:.8rem">' +
                        'Billed ' + mbMoney(b.billed) +
                        (b.discount > 0 ? ' &middot; Discount ' + mbMoney(b.discount) : '') +
                        (b.paid > 0 ? ' &middot; Paid ' + mbMoney(b.paid) : '') +
                    '</div>' +
                '</div>' +
                '<div class="text-right">' +
                    '<div class="mb-amount ' + (b.settled ? 'mb-settled' : 'mb-owed') + '">' +
                        mbMoney(b.balance) + '</div>' +
                    '<div class="text-muted" style="font-size:.75rem">' +
                        (b.settled ? 'Nothing due' : 'Balance') + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="mb-bill-body" style="display:none"></div>' +
        '</div>';
}

function mbToggle(cid) {
    var $body = $('#mb_bill_' + cid + ' .mb-bill-body');
    if ($body.is(':visible')) {
        $body.slideUp(150);
        return;
    }
    $body.html('<div class="p-3 text-muted">Loading&hellip;</div>').slideDown(150);

    $.getJSON(MB_URL, { action: 'bill', cid: cid }, function (r) {
        if (!r || !r.ok) {
            $body.html('<div class="p-3 text-danger">' + ((r && r.message) || 'Could not load this bill.') + '</div>');
            return;
        }
        $body.html(mbBillBody(r.bill, r.packages));
        mbRecalc(cid);
    });
}

function mbBillBody(b, packages) {
    var rows = packages.map(function (p) {
        var dis = p.cleared ? ' disabled checked' : '';
        return '' +
            '<div class="mb-pkg-row d-flex justify-content-between align-items-center' +
                 (p.cleared ? ' mb-pkg-cleared' : '') + '">' +
                '<label class="mb-0 d-flex align-items-center" style="cursor:' +
                    (p.cleared ? 'default' : 'pointer') + '">' +
                    '<input type="checkbox" class="mb-pkg" data-cid="' + b.cid + '" ' +
                           'data-oid="' + p.oid + '" data-share="' + p.share + '"' + dis + '> ' +
                    '<span class="ml-2">' + p.tracking + '</span>' +
                '</label>' +
                '<div>' +
                    '<span class="mr-2">' + mbMoney(p.share) + '</span>' +
                    (p.cleared ? '<span class="mb-chip mb-chip-paid">Paid</span>'
                               : '<span class="mb-chip mb-chip-due">Due</span>') +
                '</div>' +
            '</div>';
    }).join('');

    var footer = '';
    if (!b.settled && mbCanPay) {
        footer = '' +
            '<div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap">' +
                '<div>' +
                    '<div class="text-muted" style="font-size:.78rem">Amount To Pay</div>' +
                    '<div class="mb-amount" id="mb_amt_' + b.cid + '">' + mbMoney(0) + '</div>' +
                    '<div class="mb-momo-note">You will complete this on your phone with Mobile Money.</div>' +
                '</div>' +
                '<button class="btn btn-primary" id="mb_pay_' + b.cid + '" ' +
                        'onclick="mbCheckout(' + b.cid + ')">' +
                    '<iconify-icon icon="solar:smartphone-2-linear"></iconify-icon> Pay Now' +
                '</button>' +
            '</div>';
    } else if (b.settled) {
        footer = '<div class="p-3 border-top text-muted">This bill is fully settled.</div>';
    }

    return '' +
        '<div class="px-3 pt-2 text-muted" style="font-size:.78rem">' +
            'Tick the packages you want to pay for and release. Already-paid packages are locked.' +
        '</div>' +
        rows +
        footer;
}

// Tick a package -> update the running total. This figure is a PREVIEW only;
// the server recomputes it at checkout and that value is what gets charged.
$(document).on('change', '.mb-pkg', function () {
    mbRecalc($(this).data('cid'));
});

function mbRecalc(cid) {
    var total = 0;
    $('#mb_bill_' + cid + ' .mb-pkg:checked:not(:disabled)').each(function () {
        total += Number($(this).data('share')) || 0;
    });
    $('#mb_amt_' + cid).text(mbMoney(total));
    $('#mb_pay_' + cid).prop('disabled', total <= 0);
}

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
