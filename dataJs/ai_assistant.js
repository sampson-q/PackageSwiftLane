"use strict";

// ============================================================================
// AI Assistant — the chat page.
//
// Conversations list, the open conversation, the composer. One POST per
// question to ajax/ai_assistant/chat_ajax.php; the server runs the lookups
// (inside the user's own permissions) and returns the finished answer, which
// is rendered from Markdown here with a small, escaping renderer: no HTML
// from the model ever reaches the page as HTML.
// ============================================================================

(function ($) {
    var CFG = window.CDP_AI || {};
    var state = { id: 0, title: 'New chat', busy: false, convs: [] };

    var $thread, $scroll, $welcome, $input, $send, $convs;

    $(function () {
        $thread = $('#ai_thread');
        $scroll = $('#ai_scroll');
        $welcome = $('#ai_welcome');
        $input = $('#ai_input');
        $send = $('#ai_send');
        $convs = $('#ai_convs');

        $('#ai_form').on('submit', function (ev) {
            ev.preventDefault();
            send($input.val());
        });
        $input.on('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
                ev.preventDefault();
                send($input.val());
            }
        }).on('input', grow);

        $('.swl-ai-starter').on('click', function () {
            send($(this).data('prompt'));
        });
        $('#ai_new').on('click', function () { openNew(); closeSide(); });
        $convs.on('click', '[data-id]', function () { load(+$(this).data('id')); closeSide(); });
        $('#ai_delete').on('click', removeCurrent);
        $('#ai_rename').on('click', renameCurrent);
        $('#ai_side_open').on('click', function () { $('#ai_app').addClass('is-side-open'); });
        $('#ai_side_close, #ai_backdrop').on('click', closeSide);
        $thread.on('click', '.swl-ai-copy', copyAnswer);
        $thread.on('click', '.swl-ai-tools-toggle', function () {
            $(this).closest('.swl-ai-tools').toggleClass('is-open');
        });

        refreshList();
        var m = /[?&]c=(\d+)/.exec(location.search);
        if (m) { load(+m[1]); }
        if (CFG.ready) { $input.trigger('focus'); }
    });

    function closeSide() { $('#ai_app').removeClass('is-side-open'); }

    function grow() {
        var el = $input[0];
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 220) + 'px';
    }

    // ── Conversations ───────────────────────────────────────────────────────
    function refreshList(list) {
        if (list) { renderList(list); return; }
        $.getJSON(CFG.url, { action: 'list' }).done(function (r) {
            if (r && r.ok) { renderList(r.conversations); }
        });
    }

    function renderList(list) {
        state.convs = list || [];
        if (!state.convs.length) {
            $convs.html('<li class="swl-ai-convs-empty">No conversations yet.</li>');
            return;
        }
        $convs.html(state.convs.map(function (c) {
            return '<li><button type="button" class="swl-ai-conv' + (c.id === state.id ? ' is-on' : '') + '" data-id="' + c.id + '">'
                + '<span class="swl-ai-conv-title">' + esc(c.title) + '</span>'
                + '<span class="swl-ai-conv-when">' + esc(when(c.updated_at)) + '</span></button></li>';
        }).join(''));
    }

    function openNew() {
        if (state.busy) { return; }
        state.id = 0;
        setTitle('New chat');
        $thread.empty();
        $welcome.show();
        renderList(state.convs);
        history.replaceState(null, '', location.pathname);
        $input.val('').trigger('focus');
        grow();
    }

    function load(id) {
        if (state.busy || !id) { return; }
        $.getJSON(CFG.url, { action: 'load', id: id }).done(function (r) {
            if (!r || !r.ok) { toast(r && r.error ? r.error : 'Could not open that conversation.'); return; }
            state.id = r.conversation.id;
            setTitle(r.conversation.title);
            $thread.empty();
            $welcome.toggle(!r.messages.length);
            r.messages.forEach(function (m) {
                if (m.role === 'user') { addUser(m.content); }
                else { addAssistant(m.content, m.tools); }
            });
            renderList(state.convs);
            history.replaceState(null, '', '?c=' + state.id);
            toBottom();
        }).fail(function () { toast('Could not open that conversation.'); });
    }

    function setTitle(t) {
        state.title = t;
        $('#ai_title').text(t);
        $('#ai_rename, #ai_delete').prop('hidden', !state.id);
    }

    function removeCurrent() {
        if (!state.id || state.busy) { return; }
        confirmBox('Delete this conversation?', 'It disappears from your list. This cannot be undone.', 'Delete', function () {
            $.post(CFG.url, { action: 'delete', id: state.id }, null, 'json').done(function (r) {
                if (r && r.ok) { state.convs = r.conversations; openNew(); }
            });
        });
    }

    function renameCurrent() {
        if (!state.id || state.busy) { return; }
        var done = function (title) {
            title = $.trim(title || '');
            if (!title) { return; }
            $.post(CFG.url, { action: 'rename', id: state.id, title: title }, null, 'json').done(function (r) {
                if (r && r.ok) { setTitle(title); renderList(r.conversations); }
            });
        };
        if (window.Swal) {
            Swal.fire({ title: 'Rename conversation', input: 'text', inputValue: state.title, showCancelButton: true, confirmButtonText: 'Save' })
                .then(function (res) { if (res.isConfirmed) { done(res.value); } });
        } else {
            done(window.prompt('Rename conversation', state.title));
        }
    }

    // ── Asking ──────────────────────────────────────────────────────────────
    function send(text) {
        text = $.trim(text || '');
        if (!text || state.busy || !CFG.ready) { return; }
        state.busy = true;
        $send.prop('disabled', true);
        $input.val('');
        grow();
        $welcome.hide();
        addUser(text);
        var $pending = addPending();
        toBottom();

        $.ajax({
            url: CFG.url,
            type: 'POST',
            dataType: 'json',
            timeout: 330000,
            data: { action: 'send', message: text, conversation_id: state.id }
        }).done(function (r) {
            $pending.remove();
            if (r && r.conversation_id && !state.id) {
                state.id = r.conversation_id;
                setTitle(titleFrom(text));
                history.replaceState(null, '', '?c=' + state.id);
            }
            if (!r || !r.ok) {
                addError(r && r.error ? r.error : 'The assistant could not answer.', text);
                refreshList();
                return;
            }
            addAssistant(r.answer, r.tools);
            renderList(r.conversations);
        }).fail(function (xhr, status) {
            $pending.remove();
            var msg = status === 'timeout' ? 'The answer took too long. Try a narrower question.'
                : (xhr.responseJSON && xhr.responseJSON.error) || 'Could not reach the server. Check your connection and try again.';
            addError(msg, text);
        }).always(function () {
            state.busy = false;
            $send.prop('disabled', false);
            stopPending();
            toBottom();
            $input.trigger('focus');
        });
    }

    function titleFrom(t) {
        t = t.replace(/\s+/g, ' ');
        return t.length > 70 ? t.slice(0, 67) + '…' : t;
    }

    // ── Rendering ───────────────────────────────────────────────────────────
    function addUser(text) {
        $thread.append('<div class="swl-ai-msg is-user"><div class="swl-ai-bubble">' + esc(text).replace(/\n/g, '<br>') + '</div></div>');
    }

    function addAssistant(md, tools) {
        var $m = $('<div class="swl-ai-msg is-assistant">'
            + '<div class="swl-ai-avatar"><iconify-icon icon="solar:magic-stick-3-bold"></iconify-icon></div>'
            + '<div class="swl-ai-body"><div class="swl-ai-md"></div>'
            + toolsHtml(tools)
            + '<div class="swl-ai-actions"><button type="button" class="swl-ai-copy" title="Copy"><iconify-icon icon="solar:copy-linear"></iconify-icon> Copy</button></div>'
            + '</div></div>');
        $m.find('.swl-ai-md').html(renderMarkdown(md || '')).data('md', md || '');
        $m.find('table').wrap('<div class="table-responsive"></div>');
        $thread.append($m);
    }

    function toolsHtml(tools) {
        if (!tools || !tools.length) { return ''; }
        var items = tools.map(function (t) {
            return '<li class="' + (t.ok ? '' : 'is-fail') + '"><iconify-icon icon="' + (t.ok ? 'solar:check-circle-linear' : 'solar:close-circle-linear') + '"></iconify-icon> '
                + esc(t.label) + (t.detail ? ' <span>' + esc(t.detail) + '</span>' : '') + '</li>';
        }).join('');
        return '<div class="swl-ai-tools"><button type="button" class="swl-ai-tools-toggle">'
            + '<iconify-icon icon="solar:database-linear"></iconify-icon> ' + tools.length + ' lookup' + (tools.length === 1 ? '' : 's')
            + ' <iconify-icon class="swl-ai-chev" icon="solar:alt-arrow-down-linear"></iconify-icon></button><ul>' + items + '</ul></div>';
    }

    var pendingTimer = null;
    function addPending() {
        var steps = ['Reading your question…', 'Looking through the records you can see…', 'Checking statuses and history…', 'Putting the answer together…'];
        var i = 0;
        var $p = $('<div class="swl-ai-msg is-assistant is-pending">'
            + '<div class="swl-ai-avatar"><iconify-icon icon="solar:magic-stick-3-bold"></iconify-icon></div>'
            + '<div class="swl-ai-body"><div class="swl-ai-dots"><span></span><span></span><span></span></div>'
            + '<div class="swl-ai-step">' + steps[0] + '</div></div></div>');
        $thread.append($p);
        pendingTimer = setInterval(function () {
            i = Math.min(i + 1, steps.length - 1);
            $p.find('.swl-ai-step').text(steps[i]);
        }, 4000);
        return $p;
    }
    function stopPending() { if (pendingTimer) { clearInterval(pendingTimer); pendingTimer = null; } }

    function addError(msg, text) {
        var $e = $('<div class="swl-ai-msg is-assistant is-error"><div class="swl-ai-avatar"><iconify-icon icon="solar:danger-triangle-linear"></iconify-icon></div>'
            + '<div class="swl-ai-body"><div class="swl-ai-md"><p>' + esc(msg) + '</p></div>'
            + '<div class="swl-ai-actions"><button type="button" class="swl-ai-copy swl-ai-retry"><iconify-icon icon="solar:restart-linear"></iconify-icon> Try again</button></div></div></div>');
        $e.find('.swl-ai-retry').on('click', function () {
            $e.remove();
            // the question is already on screen; drop it so send() shows it once
            $thread.find('.swl-ai-msg.is-user').last().remove();
            send(text);
        });
        $thread.append($e);
    }

    function copyAnswer() {
        var md = $(this).closest('.swl-ai-body').find('.swl-ai-md').data('md') || '';
        var $b = $(this);
        var ok = function () { $b.html('<iconify-icon icon="solar:check-read-linear"></iconify-icon> Copied'); setTimeout(function () { $b.html('<iconify-icon icon="solar:copy-linear"></iconify-icon> Copy'); }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(md).then(ok); }
    }

    function toBottom() { $scroll.stop().animate({ scrollTop: $scroll[0].scrollHeight }, 200); }

    function toast(msg) {
        if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 3500 }); }
    }

    function confirmBox(title, text, button, fn) {
        if (window.Swal) {
            Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: button })
                .then(function (r) { if (r.isConfirmed) { fn(); } });
        } else if (window.confirm(title)) { fn(); }
    }

    function when(dt) {
        if (!dt) { return ''; }
        var d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d)) { return dt; }
        var now = new Date();
        if (d.toDateString() === now.toDateString()) { return d.toTimeString().slice(0, 5); }
        var y = new Date(now); y.setDate(now.getDate() - 1);
        if (d.toDateString() === y.toDateString()) { return 'Yesterday'; }
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    }

    // ── Markdown (escaping first, then a small, safe subset) ──────────────────
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Links: pages of this app (foo.php?…) or http(s) addresses only.
    function safeUrl(u) {
        u = String(u || '').trim();
        if (/^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/i.test(u)) { return u; }
        if (/^https?:\/\/[^\s"'<>]+$/i.test(u)) { return u; }
        return null;
    }

    function inline(s) {
        // s is already escaped
        var codes = [];
        s = s.replace(/`([^`]+)`/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
        s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, label, url) {
            var raw = url.replace(/&amp;/g, '&');
            var safe = safeUrl(raw);
            if (!safe) { return label; }
            var ext = /^https?:/i.test(safe);
            return '<a href="' + esc(safe) + '"' + (ext ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + label + '</a>';
        });
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
             .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
             .replace(/(^|\W)_([^_\n]+)_(?=\W|$)/g, '$1<em>$2</em>')
             .replace(/~~([^~]+)~~/g, '<del>$1</del>');
        s = s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + codes[+i] + '</code>'; });
        return s;
    }

    function renderMarkdown(md) {
        var lines = esc(String(md).replace(/\r\n?/g, '\n')).split('\n');
        var out = [], i = 0, para = [];
        var flush = function () { if (para.length) { out.push('<p>' + inline(para.join(' ')) + '</p>'); para = []; } };
        var isRow = function (l) { return /^\s*\|.*\|\s*$/.test(l); };
        var cells = function (l) { return l.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return c.trim(); }); };

        while (i < lines.length) {
            var l = lines[i];
            if (/^\s*```/.test(l)) {
                flush();
                var code = [];
                i++;
                while (i < lines.length && !/^\s*```/.test(lines[i])) { code.push(lines[i]); i++; }
                i++;
                out.push('<pre><code>' + code.join('\n') + '</code></pre>');
                continue;
            }
            var h = /^(#{1,4})\s+(.*)$/.exec(l);
            if (h) { flush(); var lv = Math.min(h[1].length + 2, 6); out.push('<h' + lv + '>' + inline(h[2]) + '</h' + lv + '>'); i++; continue; }
            if (/^\s*(---|\*\*\*)\s*$/.test(l)) { flush(); out.push('<hr>'); i++; continue; }
            if (isRow(l) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}/.test(lines[i + 1])) {
                flush();
                var head = cells(l);
                i += 2;
                var body = [];
                while (i < lines.length && isRow(lines[i])) { body.push(cells(lines[i])); i++; }
                out.push('<table class="table table-sm"><thead><tr>' + head.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('')
                    + '</tr></thead><tbody>' + body.map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>'; }).join('')
                    + '</tbody></table>');
                continue;
            }
            if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) {
                flush();
                var ordered = /^\s*\d+[.)]/.test(l);
                var items = [];
                while (i < lines.length && /^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) {
                    var item = lines[i].replace(/^\s*([-*+]|\d+[.)])\s+/, '');
                    i++;
                    while (i < lines.length && /^\s{2,}\S/.test(lines[i]) && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) { item += ' ' + lines[i].trim(); i++; }
                    items.push('<li>' + inline(item) + '</li>');
                }
                out.push(ordered ? '<ol>' + items.join('') + '</ol>' : '<ul>' + items.join('') + '</ul>');
                continue;
            }
            if (/^&gt;\s?/.test(l)) {
                flush();
                var q = [];
                while (i < lines.length && /^&gt;\s?/.test(lines[i])) { q.push(lines[i].replace(/^&gt;\s?/, '')); i++; }
                out.push('<blockquote>' + inline(q.join(' ')) + '</blockquote>');
                continue;
            }
            if (/^\s*$/.test(l)) { flush(); i++; continue; }
            para.push(l.trim());
            i++;
        }
        flush();
        return out.join('');
    }

    window.cdpAiRenderMarkdown = renderMarkdown; // for tests
})(window.jQuery);
