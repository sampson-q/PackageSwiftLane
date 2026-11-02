"use strict";

/* ---------------------------------------------------------------------------
   Push Notifications composer (views/tools/push_notifications.php).

   - Live previews of what each recipient receives: the WhatsApp message
     (template 12 with the title and message filled in, WhatsApp formatting,
     the attached image / video / document) and the SMS (title + message,
     with its length in parts).
   - The WhatsApp attachment: uploaded on pick (ajax/tools/push_media_upload_ajax.php),
     its stored name posted with the push (see push_notifications.js).
   - The estimated cost: who the push reaches (action=estimate on the push
     endpoint, nothing is sent) times the price per message set on the
     notification cost page, per channel and in total.
   --------------------------------------------------------------------------- */

(function ($) {
    var CFG = window.CDP_PUSH_COMPOSE || {};
    var MAX_BYTES = 16 * 1024 * 1024;
    var CAPTION_MAX = CFG.captionMax || 1024;
    var ACCEPT = {
        image: '.jpg,.jpeg,.png,.webp,.gif',
        document: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt',
        video: '.mp4,.3gp,.mov'
    };
    var ICON = { image: 'far fa-image', document: 'far fa-file-alt', video: 'fas fa-video' };

    var media = null;          // { file, kind, name, size, localUrl }
    var pickKind = 'image';
    var estimate = null;       // { audience: {...}, rates: {...} }
    var uploading = false;     // an attachment upload is in flight
    var estimateSeq = 0;
    var estimateTimer = null;

    // ── helpers ────────────────────────────────────────────────────────────
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

    function chosen(ch) { return $('#channels_' + ch).is(':checked'); }

    function notifType() { return $('input[name="notification_type"]:checked').val() || 'broadcast'; }

    function fmtBytes(n) {
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        if (n >= 1024) return Math.round(n / 1024) + ' KB';
        return n + ' B';
    }

    function strLen(s) { return Array.from(s || '').length; }

    /** WhatsApp markup, the way the app shows it: *bold* _italic_ ~strike~ ```mono```. */
    function waFormat(text) {
        var h = esc(text);
        h = h.replace(/```([\s\S]+?)```/g, '<code>$1</code>');
        h = h.replace(/(^|[\s>(])\*([^*\n]+)\*(?=$|[\s<.,!?:;)])/g, '$1<b>$2</b>');
        h = h.replace(/(^|[\s>(])_([^_\n]+)_(?=$|[\s<.,!?:;)])/g, '$1<i>$2</i>');
        h = h.replace(/(^|[\s>(])~([^~\n]+)~(?=$|[\s<.,!?:;)])/g, '$1<s>$2</s>');
        return h.replace(/\n/g, '<br>');
    }

    function recipientName() {
        if (notifType() === 'single_user') {
            var d = $('#user_id').select2 ? $('#user_id').select2('data') : null;
            if (d && d.length && d[0].text) return d[0].text;
        }
        return 'Customer Name';
    }

    /** The WhatsApp text one recipient receives (helpers/push_notify.php does the same). */
    function waBody() {
        var tpl = CFG.waTemplate || '[SUBJECT]\n\n[MESSAGE]';
        var name = recipientName();
        var map = {
            '[USERNAME]': name.charAt(0).toUpperCase() + name.slice(1),
            '[SUBJECT]': $.trim($('#subject').val()),
            '[SITE_NAME]': CFG.siteName || '',
            '[MESSAGE]': $.trim($('#message').val()),
            '[URL]': CFG.siteUrl || ''
        };
        return tpl.replace(/\[(USERNAME|SUBJECT|SITE_NAME|MESSAGE|URL)\]/g, function (m) { return map[m]; });
    }

    /** The SMS text (helpers/push_notify.php: title, new line, message), cleaned as it is sent. */
    function smsBody() {
        var s = $.trim($('#subject').val()), m = $.trim($('#message').val());
        return smsPlain(s !== '' ? s + '\n' + m : m);
    }

    /**
     * What the SMS provider helper sends (helpers/hubtel_sms.php cdp_smsPlainText):
     * WhatsApp markup reduced to the words, emoji removed, typographic quotes and
     * dashes turned into plain ones, so the preview shows the real text and parts.
     */
    function smsPlain(t) {
        t = String(t || '');
        t = t.replace(/\*([^*\n]+)\*/g, '$1')
             .replace(/(^|[^A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/g, '$1$2')
             .replace(/~([^~\n]+)~/g, '$1')
             .replace(/```/g, '');
        var map = { '\u2018': "'", '\u2019': "'", '\u201A': "'", '\u2032': "'", '\u201C': '"', '\u201D': '"', '\u201E': '"', '\u2033': '"',
            '\u2013': '-', '\u2014': '-', '\u2212': '-', '\u2026': '...', '\u2022': '-', '\u25CF': '-', '\u25AA': '-', '\u25B8': '-', '\u25BA': '-',
            '\u2192': '->', '\u2190': '<-', '\u00A0': ' ', '\u2009': ' ', '\u202F': ' ' };
        t = t.replace(/[\u2018\u2019\u201A\u2032\u201C\u201D\u201E\u2033\u2013\u2014\u2212\u2026\u2022\u25CF\u25AA\u25B8\u25BA\u2192\u2190\u00A0\u2009\u202F]/g, function (c) { return map[c]; });
        t = t.replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}\u{2B00}-\u{2BFF}\u{2190}-\u{21FF}\u{25A0}-\u{25FF}\u{2900}-\u{297F}\u{3030}\u{303D}\u{3297}\u{3299}\u{2122}\u{2139}\u{24C2}\u{200D}\u{20E3}\u{FE00}-\u{FE0F}\u{E0020}-\u{E007F}]/gu, '');
        t = t.replace(/[ \t]+/g, ' ').replace(/ +([,.!?;:])/g, '$1').replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n');
        return $.trim(t);
    }

    var GSM = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    var GSM_EXT = '^{}\\[~]|€';

    /** Length and parts of an SMS: GSM-7 160 / 153 per part, Unicode 70 / 67. */
    function smsParts(text) {
        var units = 0, unicode = false;
        Array.from(text).forEach(function (c) {
            if (GSM.indexOf(c) !== -1) units += 1;
            else if (GSM_EXT.indexOf(c) !== -1) units += 2;
            else unicode = true;
        });
        if (unicode) {
            var n = strLen(text);
            return { chars: n, unicode: true, parts: n === 0 ? 0 : (n <= 70 ? 1 : Math.ceil(n / 67)) };
        }
        return { chars: units, unicode: false, parts: units === 0 ? 0 : (units <= 160 ? 1 : Math.ceil(units / 153)) };
    }

    /** WhatsApp messages per recipient: a caption over the limit follows as its own message. */
    function waUnits() {
        return (media && strLen(waBody()) > CAPTION_MAX) ? 2 : 1;
    }

    // ── previews ───────────────────────────────────────────────────────────
    function renderWa() {
        var body = waBody();
        var long = media && strLen(body) > CAPTION_MAX;
        var empty = $.trim($('#subject').val()) === '' && $.trim($('#message').val()) === '';

        var $m = $('#wa_preview_media');
        if (media) {
            var h;
            if (media.kind === 'image') {
                h = '<img src="' + esc(media.localUrl) + '" alt="">';
            } else if (media.kind === 'video') {
                h = '<video src="' + esc(media.localUrl) + '" controls muted playsinline preload="metadata"></video>';
            } else {
                var ext = (media.name.split('.').pop() || '').toUpperCase();
                h = '<div class="swl-bubble-doc"><i class="far fa-file-alt"></i><div><div class="swl-bubble-doc-name">' + esc(media.name) +
                    '</div><div class="swl-bubble-doc-meta">' + esc(ext) + ' · ' + fmtBytes(media.size) + '</div></div></div>';
            }
            $m.html(h).prop('hidden', false);
        } else {
            $m.empty().prop('hidden', true);
        }

        if (long) {
            $('#wa_preview_text').html('').prop('hidden', true);
            $('#wa_preview_tail_text').html(waFormat(body));
            $('#wa_preview_tail').prop('hidden', false);
        } else {
            $('#wa_preview_text').prop('hidden', false)
                .html(empty ? '<span class="text-muted">Type a title and message to see the preview.</span>' : waFormat(body));
            $('#wa_preview_tail').prop('hidden', true);
        }

        var d = new Date();
        $('#wa_preview_time').text(('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2));

        var foot = strLen(body) + ' characters';
        if (media) foot += ' · ' + media.kind + ' attached';
        if (long) foot += ' · longer than the ' + CAPTION_MAX + '-character caption limit, so the text follows the ' + media.kind + ' as a second message';
        $('#wa_preview_foot').text(foot);
    }

    function renderSms() {
        var text = smsBody();
        var p = smsParts(text);
        $('#sms_preview_text').html(text === '' ? '<span class="text-muted">Type a title and message to see the preview.</span>' : esc(text).replace(/\n/g, '<br>'));
        $('#sms_preview_foot').text(p.chars + ' characters · ' + p.parts + ' part' + (p.parts === 1 ? '' : 's') +
            (p.unicode ? ' · Unicode (emoji or special characters: 70 per part)' : ''));
    }

    // ── cost ───────────────────────────────────────────────────────────────
    function money(v, cur, dp) {
        var n = Number(v || 0);
        return (cur ? cur + ' ' : '') + n.toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: Math.max(dp, 4) });
    }

    function renderCost() {
        var $b = $('#cost_body');
        $b.data('total', '');
        if (notifType() === 'invoice') return;
        if (!estimate) {
            $b.html('<span class="text-muted">Choose who receives it to see the cost.</span>');
            return;
        }
        var a = estimate.audience || {}, r = estimate.rates || {};
        if (!a.total) {
            $b.html('<span class="text-muted">' + (notifType() === 'broadcast' ? 'No active customers to send to.' : 'Choose who receives it to see the cost.') + '</span>');
            return;
        }
        var cur = r.currency || '';
        var rows = [], total = 0, anyPrice = false;
        var sms = smsParts(smsBody());

        if (chosen('whatsapp')) {
            var u = waUnits();
            var n = a.with_phone * u;
            var c = n * (r.whatsapp || 0);
            total += c; anyPrice = anyPrice || r.whatsapp > 0;
            rows.push(['WhatsApp', a.with_phone + ' recipient' + (a.with_phone === 1 ? '' : 's') + (u > 1 ? ' × 2 messages' : ''), r.whatsapp, c]);
        }
        if (chosen('sms')) {
            var parts = Math.max(1, sms.parts);
            var per = r.sms_per_part ? parts : 1;
            var cs = a.with_phone * per * (r.sms || 0);
            total += cs; anyPrice = anyPrice || r.sms > 0;
            rows.push(['SMS', a.with_phone + ' recipient' + (a.with_phone === 1 ? '' : 's') + (per > 1 ? ' × ' + per + ' parts' : ''), r.sms, cs]);
        }
        if (chosen('email')) {
            var ce = a.with_email * (r.email || 0);
            total += ce; anyPrice = anyPrice || r.email > 0;
            rows.push(['E-mail', a.with_email + ' recipient' + (a.with_email === 1 ? '' : 's'), r.email, ce]);
        }

        var h = '<div class="swl-cost-audience">' + a.total + ' recipient' + (a.total === 1 ? '' : 's') + ' · ' +
            a.with_phone + ' with a phone number · ' + a.with_email + ' with an e-mail address</div>';

        if (!rows.length) {
            $b.html(h + '<span class="text-muted">Tick a channel to send by.</span>');
            return;
        }

        h += '<div class="table-responsive"><table class="table table-sm swl-cost-table"><thead><tr><th>Channel</th><th>Messages</th><th class="text-right">Cost</th></tr></thead><tbody>';
        rows.forEach(function (row) {
            var priced = r.available && row[2] > 0;
            h += '<tr><td>' + row[0] + '</td><td>' + esc(row[1]) + '<div class="swl-cost-rate">' +
                (priced ? esc(money(row[2], cur, 2)) + ' each' : 'price not set') + '</div></td><td class="text-right">' +
                (priced ? esc(money(row[3], cur, 2)) : '—') + '</td></tr>';
        });
        h += '</tbody><tfoot><tr><th colspan="2">Estimated total</th><th class="text-right">' + (anyPrice ? esc(money(total, cur, 2)) : '—') + '</th></tr></tfoot></table></div>';

        if (!r.available || !anyPrice) {
            h += '<small class="form-text text-muted">Set the price of each message on the notification cost page to see what this push costs.</small>';
        } else {
            h += '<small class="form-text text-muted">An estimate: recipients without a phone number or e-mail address are skipped, and WhatsApp skips numbers that are not on WhatsApp.</small>';
        }
        $b.html(h);
        $b.data('total', anyPrice ? money(total, cur, 2) : '');
    }

    function loadEstimate() {
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(function () {
            var type = notifType();
            if (type === 'invoice') return;
            var data = new FormData();
            data.append('action', 'estimate');
            data.append('notification_type', type);
            data.append('channels', $('input[name="channels[]"]:checked').map(function () { return this.value; }).get().join(','));
            var uid = $('#user_id').val() || $('#uid').val();
            var cid = $('#consolidation_id').val() || $('#cid').val();
            if (type === 'single_user' && uid) data.append('user_id', uid);
            if (type === 'consolidation' && cid) data.append('consolidation_id', cid);

            var seq = ++estimateSeq;
            $('#cost_body').addClass('is-loading');
            $.ajax({ type: 'POST', url: 'ajax/tools/push_notifications_ajax.php', data: data, contentType: false, processData: false, dataType: 'json', cache: false })
                .done(function (res) {
                    if (seq !== estimateSeq) return;
                    estimate = (res && res.success) ? res : null;
                    renderCost();
                })
                .fail(function () {
                    if (seq !== estimateSeq) return;
                    estimate = null;
                    $('#cost_body').html('<span class="text-muted">The cost estimate could not be loaded.</span>');
                })
                .always(function () { if (seq === estimateSeq) $('#cost_body').removeClass('is-loading'); });
        }, 250);
    }

    // ── layout per type / channel ──────────────────────────────────────────
    function applyVisibility() {
        var invoice = notifType() === 'invoice';
        $('#composer-preview').toggle(!invoice);
        $('#media-row').toggle(!invoice && chosen('whatsapp'));
        $('#wa_preview_box').toggle(chosen('whatsapp'));
        $('#sms_preview_box').toggle(chosen('sms'));
    }

    function renderAll() {
        applyVisibility();
        renderWa();
        renderSms();
        renderCost();
        var n = strLen($('#message').val());
        $('#message_count').text(n + ' character' + (n === 1 ? '' : 's'));
    }

    // ── attachment ─────────────────────────────────────────────────────────
    function clearMedia() {
        if (media && media.localUrl) URL.revokeObjectURL(media.localUrl);
        media = null;
        $('#media_file').val('');
        $('#media_chip').prop('hidden', true);
        $('#media_picker').val('');
        renderAll();
    }

    function showMedia() {
        $('#media_chip_icon').attr('class', ICON[media.kind] || 'far fa-file');
        $('#media_chip_name').text(media.name);
        $('#media_chip_size').text(fmtBytes(media.size));
        $('#media_chip').prop('hidden', false);
    }

    function upload(file) {
        if (file.size > MAX_BYTES) {
            Swal.fire({ title: 'File too large', html: 'WhatsApp accepts files up to 16 MB.', icon: 'error', confirmButtonText: 'OK' });
            return;
        }
        var data = new FormData();
        data.append('media', file);
        uploading = true;
        $('.swl-attach-pick').prop('disabled', true);
        $('#media_chip').prop('hidden', false);
        $('#media_chip_icon').attr('class', 'fas fa-spinner fa-spin');
        $('#media_chip_name').text(file.name);
        $('#media_chip_size').text('Uploading…');

        $.ajax({ type: 'POST', url: 'ajax/tools/push_media_upload_ajax.php', data: data, contentType: false, processData: false, dataType: 'json', cache: false })
            .done(function (res) {
                if (!res || !res.success) {
                    $('#media_chip').prop('hidden', !media);
                    if (media) showMedia();
                    Swal.fire({ title: 'Attachment not added', html: esc((res && res.message) || 'The upload failed.'), icon: 'error', confirmButtonText: 'OK' });
                    return;
                }
                if (media && media.localUrl) URL.revokeObjectURL(media.localUrl);
                media = { file: res.file, kind: res.kind, name: res.name, size: res.size, localUrl: URL.createObjectURL(file) };
                $('#media_file').val(res.file);
                showMedia();
                renderAll();
            })
            .fail(function () {
                $('#media_chip').prop('hidden', !media);
                if (media) showMedia();
                Swal.fire({ title: 'Attachment not added', html: 'The upload failed. Check the file size and try again.', icon: 'error', confirmButtonText: 'OK' });
            })
            .always(function () {
                uploading = false;
                $('.swl-attach-pick').prop('disabled', false);
                $('#media_picker').val('');
            });
    }

    /**
     * Called by push_notifications.js before a push starts: shows who it
     * reaches and what it costs, and goes ahead only when confirmed.
     */
    window.cdpPushConfirm = function (proceed) {
        // The send would snapshot the attachment before the upload finishes.
        if (uploading) {
            Swal.fire({ title: 'Attachment still uploading', html: 'Wait for the attachment to finish uploading, then send.', icon: 'info', confirmButtonText: 'OK' });
            return;
        }
        if (notifType() === 'invoice') { proceed(); return; } // no estimate for invoices
        var total = $('#cost_body').data('total');
        var a = estimate && estimate.audience;
        if (a && !a.total) { proceed(); return; }
        var html;
        if (!a) {
            // Still loading or failed: never send without a confirmation.
            html = 'The audience and estimated cost could not be loaded yet, so this push has not been reviewed.<br>Send it anyway?';
            total = null;
        } else {
            html = 'This sends to <b>' + a.total + '</b> recipient' + (a.total === 1 ? '' : 's') + '.';
        }
        if (total) html += '<br>Estimated cost: <b>' + esc(total) + '</b>';
        if (media) html += '<br>WhatsApp attachment: ' + esc(media.name);
        Swal.fire({ title: 'Send this push?', html: html, icon: 'question', showCancelButton: true,
            confirmButtonText: 'Send', cancelButtonText: 'Cancel' })
            .then(function (r) { if (r.isConfirmed) proceed(); });
    };

    // ── wiring ─────────────────────────────────────────────────────────────
    $(function () {
        $(document).on('click', '.swl-attach-pick', function () {
            pickKind = $(this).data('kind');
            $('#media_picker').attr('accept', ACCEPT[pickKind] || '').trigger('click');
        });
        $('#media_picker').on('change', function () {
            if (this.files && this.files[0]) upload(this.files[0]);
        });
        $('#media_remove').on('click', clearMedia);

        $('#subject, #message').on('input', function () { renderWa(); renderSms(); renderCost();
            var n = strLen($('#message').val());
            $('#message_count').text(n + ' character' + (n === 1 ? '' : 's'));
        });
        $(document).on('change', 'input[name="channels[]"]', function () { renderAll(); loadEstimate(); });
        $(document).on('change', 'input[name="notification_type"]', function () { estimate = null; renderAll(); loadEstimate(); });
        $('#user_id, #consolidation_id').on('change', function () { estimate = null; renderAll(); loadEstimate(); });

        // The page resets the form after a push: drop the attachment with it.
        $('#push_notification_form').on('reset', function () {
            setTimeout(function () { clearMedia(); estimate = null; loadEstimate(); }, 0);
        });

        renderAll();
        loadEstimate();
    });
})(jQuery);
