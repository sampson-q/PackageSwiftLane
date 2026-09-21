"use strict";

/* ---------------------------------------------------------------------------
   Shared by push_notifications.js and push_notifications_consolidation.js.

   A push is delivered a few recipients per request: the endpoint answers with
   { summary: { done, next_after_id, batch_id, total, ... } } and this runner
   calls it again until `done`, adding the chunk summaries up and showing the
   progress. One request for the whole audience cannot work — a broadcast is
   thousands of WhatsApp + SMTP round trips and the web server ends the request
   long before the loop does.
   --------------------------------------------------------------------------- */

function cdp_pushEsc(t) {
    return $('<div>').text(t == null ? '' : t).html();
}

function cdp_pushChannelLine(label, c) {
    return '<b>' + label + ':</b> ' + c.sent + ' sent · ' + c.failed + ' failed · ' + c.skipped + ' skipped';
}

function cdp_pushSummaryHtml(s) {
    if (!s) return '';
    var html = '<div class="text-left" style="font-size:.9rem">';
    if (s.channels_label) html += '<div><b>Sent By:</b> ' + cdp_pushEsc(s.channels_label) + '</div>';
    html += '<div><b>Recipients:</b> ' + s.recipients + (s.total && s.total !== s.recipients ? ' of ' + s.total : '') + '</div>';
    // A channel the admin did not choose is not reported at all.
    if (s.channels !== 'email') html += '<div>' + cdp_pushChannelLine('WhatsApp', s.whatsapp) + '</div>';
    if (s.channels !== 'whatsapp') html += '<div>' + cdp_pushChannelLine('E-mail', s.email) + '</div>';
    if (s.lines && s.lines.length) {
        html += '<details class="mt-2"><summary style="cursor:pointer">Per-recipient results</summary><ul class="pl-3 mt-2" style="max-height:220px;overflow:auto">';
        s.lines.forEach(function (l) { html += '<li>' + cdp_pushEsc(l) + '</li>'; });
        html += '</ul></details>';
    }
    if (s.batch_id) html += '<div class="mt-2 text-muted">Batch ' + cdp_pushEsc(s.batch_id) + ' — every attempt is recorded in Settings → Message Logs.</div>';
    html += '</div>';
    return html;
}

function cdp_pushErrorsHtml(errors) {
    var list = Array.isArray(errors) ? errors : Object.keys(errors || {}).map(function (k) { return errors[k]; });
    var html = '<ul class="error">';
    list.forEach(function (e) { html += '<li class="text-left">' + cdp_pushEsc(e) + '</li>'; });
    return html + '</ul>';
}

/**
 * Run a push to completion.
 *
 * @param {string}   url        endpoint
 * @param {function} buildData  returns a fresh FormData with the form's fields
 * @param {function} onFinish   called once, after the result dialog is shown
 */
function cdp_pushRun(url, buildData, onFinish) {
    var total = {
        recipients: 0, total: 0, lines: [], batch_id: '', channels: 'both', channels_label: '',
        whatsapp: { sent: 0, failed: 0, skipped: 0 },
        email: { sent: 0, failed: 0, skipped: 0 }
    };
    var retries = 0;

    function add(s) {
        total.recipients += s.recipients;
        total.total = s.total;
        total.batch_id = s.batch_id;
        total.channels = s.channels;
        total.channels_label = s.channels_label;
        ['whatsapp', 'email'].forEach(function (ch) {
            ['sent', 'failed', 'skipped'].forEach(function (k) { total[ch][k] += (s[ch][k] || 0); });
        });
        total.lines = total.lines.concat(s.lines || []);
    }

    function progress() {
        var pct = total.total ? Math.round(total.recipients * 100 / total.total) : 0;
        var html = '<div class="progress mb-2" style="height:10px"><div class="progress-bar" style="width:' + pct + '%"></div></div>' +
            '<div>' + total.recipients + ' of ' + (total.total || '…') + ' recipients</div>' +
            '<div class="text-muted mt-1" style="font-size:.85rem">Keep this page open until the push finishes.</div>';
        if (Swal.isVisible() && $('#cdp-push-progress').length) {
            $('#cdp-push-progress').html(html);
        } else {
            Swal.fire({ title: 'Sending Notifications', html: '<div id="cdp-push-progress">' + html + '</div>',
                allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false });
        }
    }

    function finish(problem) {
        var delivered = total.whatsapp.sent + total.email.sent;
        var html = '';
        if (problem) html += cdp_pushErrorsHtml([problem]);
        else if (!delivered) html += cdp_pushErrorsHtml(['Nothing was delivered. See the per-recipient results and the Message Logs page.']);
        if (total.recipients) html += cdp_pushSummaryHtml(total);
        Swal.fire({
            title: problem ? 'Push Notifications Interrupted' : (delivered ? 'Push Notifications Sent' : (typeof message_error !== 'undefined' ? message_error : 'Error')),
            html: html,
            icon: problem ? 'warning' : (delivered ? 'success' : 'error'),
            allowOutsideClick: false,
            confirmButtonText: 'OK'
        });
        if (typeof onFinish === 'function') onFinish(!problem && delivered > 0);
    }

    function step(afterId) {
        var data = buildData();
        data.append('after_id', afterId);
        if (total.batch_id) data.append('batch_id', total.batch_id);

        $.ajax({ type: 'POST', url: url, data: data, contentType: false, processData: false, dataType: 'json', cache: false })
            .done(function (response) {
                if (!response || response.success !== true || !response.summary) {
                    // Validation / lookup error: nothing was attempted in this call.
                    if (total.recipients) { finish('The push stopped early: ' + cdp_pushEsc(JSON.stringify((response && response.errors) || 'unknown error'))); return; }
                    Swal.fire({ title: typeof message_error !== 'undefined' ? message_error : 'Error',
                        html: cdp_pushErrorsHtml((response && response.errors) || ['Unknown error']), icon: 'error', confirmButtonText: 'OK' });
                    if (typeof onFinish === 'function') onFinish(false);
                    return;
                }
                retries = 0;
                add(response.summary);
                if (response.summary.done) { finish(''); return; }
                progress();
                step(response.summary.next_after_id);
            })
            .fail(function (xhr, status) {
                // The same chunk is asked for again. Whoever the lost request already
                // reached is in the message log under this batch id, and the server
                // leaves them out, so nobody receives the message twice.
                if (retries < 2 && total.batch_id) {
                    retries++;
                    setTimeout(function () { step(afterId); }, 3000);
                    return;
                }
                finish('The connection to the server failed (' + status + ') after ' + total.recipients + ' recipient(s). Check Message Logs for batch ' + (total.batch_id || '—') + ' before sending again.');
            });
    }

    progress();
    step(0);
}
