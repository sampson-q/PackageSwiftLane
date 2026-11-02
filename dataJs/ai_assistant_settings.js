"use strict";

// ============================================================================
// AI Assistant settings — connection (super admins), what the assistant
// knows, what people ask, and suggested pages (administrators).
// Everything comes from one GET to settings_ajax.php and every change returns
// the same payload, so the page always shows what is stored.
// ============================================================================

(function ($) {
    var CFG = window.CDP_AI_SETTINGS || {};
    var data = null;
    var cleared = {};

    $(function () {
        $('#ai_settings_form').on('submit', function (ev) { ev.preventDefault(); save(); });
        $('#ai_settings_form').on('change', 'input[name="provider"]', function () { onProvider(true); });
        $('#ai_test_btn').on('click', test);
        $('#ai_models_btn').on('click', loadModels);
        $('.ai-key-clear').on('click', function () {
            var p = $(this).data('provider');
            cleared[p] = true;
            $('#ai_key_' + p).val('').attr('placeholder', 'Will be removed when you save');
        });
        $('#ai_note_form').on('submit', function (ev) {
            ev.preventDefault();
            var note = $.trim($('#ai_note').val());
            if (!note) { return; }
            post({ action: 'knowledge_add', note: note }, function () { $('#ai_note').val(''); });
        });
        $('#ai_notes').on('click', '[data-del]', function () {
            var id = $(this).data('del');
            confirmBox('Remove this fact?', 'The assistant will no longer know it.', function () { post({ action: 'knowledge_delete', id: id }); });
        });
        $('#ai_ideas').on('change', '[data-status]', function () {
            post({ action: 'idea_status', id: $(this).data('status'), status: $(this).val() });
        });
        $('#ai_ideas').on('click', '[data-idea-del]', function () {
            var id = $(this).data('idea-del');
            confirmBox('Delete this idea?', '', function () { post({ action: 'idea_delete', id: id }); });
        });
        $('#ai_analyse_btn').on('click', analyse);

        $.getJSON(CFG.url).done(render).fail(function () { notice('danger', 'Could not load the assistant settings.'); });
    });

    function render(r) {
        if (!r || !r.ok) { notice('danger', (r && r.error) || 'Could not load the assistant settings.'); return; }
        data = r;
        if (r.settings) { renderSettings(r.settings, r.configured); }
        if (r.can_insights) {
            renderNotes(r.knowledge || []);
            renderStats(r.stats || {});
            renderThemes(r.last_analysis);
            renderIdeas(r.ideas || []);
        }
    }

    // ── Connection ──────────────────────────────────────────────────────────
    function renderSettings(s, configured) {
        $('#ai_enabled').prop('checked', !!s.enabled);
        $('input[name="provider"][value="' + s.provider + '"]').prop('checked', true);
        $('#ai_model').val(s.model);
        $('#ai_effort').val(s.effort);
        $('#ai_audience').val(s.audience);
        $('#ai_rounds').val(s.max_rounds);
        $('#ai_suggest').prop('checked', !!s.suggest_pages);
        $('#ai_instructions').val(s.instructions);
        cleared = {};
        $.each(s.keys || {}, function (p, masked) {
            $('#ai_key_' + p).val('').attr('placeholder', masked ? masked + '  (saved; type to replace)' : 'Paste the API key');
            $('[data-key-state="' + p + '"]').text(masked ? 'Key saved' : 'No key').toggleClass('is-set', !!masked);
        });
        onProvider(false);
        var label = (CFG.providers[s.provider] || {}).label || s.provider;
        $('#ai_status').html(configured
            ? '<span class="label label-success">On</span> Answering with <b>' + esc(label) + '</b> · <code>' + esc(s.model) + '</code>'
            : (s.enabled ? '<span class="label label-warning">Incomplete</span> Add the API key for ' + esc(label) + '.'
                         : '<span class="label label-default">Off</span> The assistant is switched off.'));
    }

    function provider() { return $('input[name="provider"]:checked').val() || 'claude'; }

    function onProvider(changed) {
        var p = provider();
        var info = CFG.providers[p] || { models: {} };
        $('.swl-ai-keyrow').hide().filter('[data-provider="' + p + '"]').show();
        $('.swl-ai-claude-only').toggle(p === 'claude');
        fillModels($.map(info.models, function (label, id) { return { id: id, name: label }; }));
        var current = $.trim($('#ai_model').val());
        if (changed && (!current || !belongs(current, p))) { $('#ai_model').val(info['default'] || ''); }
        $('#ai_model').attr('placeholder', info['default'] || '');
    }

    function belongs(model, p) {
        if (p === 'claude') { return /^claude-/.test(model); }
        if (p === 'openrouter') { return model.indexOf('/') > 0; }
        return !/^claude-/.test(model) && model.indexOf('/') < 0;
    }

    function fillModels(list) {
        $('#ai_model_list').html(list.map(function (m) {
            return '<option value="' + esc(m.id) + '">' + esc(m.name && m.name !== m.id ? m.name : '') + '</option>';
        }).join(''));
    }

    function formData() {
        var f = {
            action: 'save',
            enabled: $('#ai_enabled').is(':checked') ? 1 : 0,
            provider: provider(),
            model: $.trim($('#ai_model').val()),
            effort: $('#ai_effort').val(),
            audience: $('#ai_audience').val(),
            max_rounds: $('#ai_rounds').val(),
            suggest_pages: $('#ai_suggest').is(':checked') ? 1 : 0,
            instructions: $('#ai_instructions').val()
        };
        $.each(CFG.providers, function (p) {
            var v = $.trim($('#ai_key_' + p).val());
            if (v) { f['key_' + p] = v; }
            if (cleared[p]) { f['clear_key_' + p] = 1; }
        });
        return f;
    }

    function save() {
        var $b = $('#ai_save_btn').prop('disabled', true);
        $.post(CFG.url, formData(), null, 'json').done(function (r) {
            if (!r || !r.ok) { notice('danger', (r && r.error) || 'Could not save.'); return; }
            render(r);
            notice('success', 'Settings saved.');
        }).fail(function (x) { notice('danger', (x.responseJSON && x.responseJSON.error) || 'Could not save.'); })
          .always(function () { $b.prop('disabled', false); });
    }

    function test() {
        var f = formData();
        f.action = 'test';
        var $b = $('#ai_test_btn').prop('disabled', true).addClass('is-busy');
        notice('info', 'Testing the connection…');
        $.post(CFG.url, f, null, 'json').done(function (r) {
            if (r && r.ok) { notice('success', r.message); } else { notice('danger', (r && r.error) || 'The test failed.'); }
        }).fail(function (x) { notice('danger', (x.responseJSON && x.responseJSON.error) || 'The test failed.'); })
          .always(function () { $b.prop('disabled', false).removeClass('is-busy'); });
    }

    function loadModels() {
        var f = formData();
        f.action = 'models';
        var $b = $('#ai_models_btn').prop('disabled', true);
        $.post(CFG.url, f, null, 'json').done(function (r) {
            if (!r || !r.ok) { notice('danger', (r && r.error) || 'Could not load the models.'); return; }
            fillModels(r.models);
            $('#ai_model_hint').text(r.models.length + ' models available from ' + ((CFG.providers[f.provider] || {}).label || f.provider) + '. Click the field to pick one.');
            $('#ai_model').trigger('focus');
        }).always(function () { $b.prop('disabled', false); });
    }

    // ── Knowledge ───────────────────────────────────────────────────────────
    function renderNotes(notes) {
        if (!notes.length) {
            $('#ai_notes').html('<li class="swl-ai-empty">Nothing yet. Add the things a new colleague would need to be told.</li>');
            return;
        }
        $('#ai_notes').html(notes.map(function (n) {
            return '<li><div class="swl-ai-note-text">' + esc(n.note) + '</div>'
                + '<div class="swl-ai-note-meta">' + esc(n.author || 'Someone') + ' · ' + esc((n.created_at || '').slice(0, 10))
                + (n.source === 'chat' ? ' · taught in a chat' : '') + '</div>'
                + '<button type="button" class="swl-ai-icon-btn btn-delete" data-del="' + n.id + '" title="Remove"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></li>';
        }).join(''));
    }

    // ── Questions ───────────────────────────────────────────────────────────
    function renderStats(s) {
        $('#ai_stats').html(
            tile('Questions', s.total || 0) + tile('People Asking', s.users || 0)
            + tile('Not Answered', (s.unanswered || []).length));
        $('#ai_by_area').html(bars(s.by_area || []));
        $('#ai_by_role').html(bars(s.by_role || []));
        $('#ai_recent').html(qlist(s.recent || [], 'No questions yet.'));
        $('#ai_unanswered').html(qlist(s.unanswered || [], 'Every question got an answer.'));
    }

    function tile(label, n) {
        return '<div class="swl-ai-stat"><div class="swl-ai-stat-n">' + esc(n) + '</div><div class="swl-ai-stat-l">' + esc(label) + '</div></div>';
    }

    function bars(list) {
        if (!list.length) { return '<div class="swl-ai-empty">No data yet.</div>'; }
        var max = Math.max.apply(null, list.map(function (x) { return x.count; })) || 1;
        return '<ul class="swl-ai-bars">' + list.map(function (x) {
            return '<li><span class="swl-ai-bar-l">' + esc(x.label) + '</span><span class="swl-ai-bar-t"><span style="width:' + Math.round(x.count / max * 100) + '%"></span></span><span class="swl-ai-bar-n">' + x.count + '</span></li>';
        }).join('') + '</ul>';
    }

    function qlist(list, empty) {
        if (!list.length) { return '<li class="swl-ai-empty">' + esc(empty) + '</li>'; }
        return list.map(function (q) {
            return '<li><div>' + esc(q.question) + '</div><span>' + esc(q.role || '') + ' · ' + esc((q.at || '').slice(0, 16))
                + (q.outcome && q.outcome !== 'answered' ? ' · ' + esc(q.outcome.replace('_', ' ')) : '') + '</span></li>';
        }).join('');
    }

    // ── Ideas ───────────────────────────────────────────────────────────────
    function renderThemes(a) {
        if (!a || !a.themes || !a.themes.length) { $('#ai_themes').empty(); return; }
        $('#ai_themes').html('<div class="swl-ai-themes"><div class="swl-ai-themes-head">Themes in the last ' + esc(a.questions) + ' questions · analysed ' + esc((a.at || '').slice(0, 16)) + '</div>'
            + a.themes.map(function (t) {
                return '<span class="swl-ai-theme" title="' + esc(t.example || '') + '">' + esc(t.theme) + (t.count ? ' <b>' + esc(t.count) + '</b>' : '') + '</span>';
            }).join('') + '</div>');
    }

    function renderIdeas(ideas) {
        if (!ideas.length) {
            $('#ai_ideas').html('<div class="swl-ai-empty">No ideas yet. They appear as people use the assistant, or press Analyse Questions.</div>');
            return;
        }
        var statuses = { 'new': 'New', planned: 'Planned', built: 'Built', dismissed: 'Dismissed' };
        $('#ai_ideas').html('<div class="swl-ai-ideas">' + ideas.map(function (i) {
            var opts = $.map(statuses, function (l, v) { return '<option value="' + v + '"' + (v === i.status ? ' selected' : '') + '>' + l + '</option>'; }).join('');
            var ex = $.trim(i.example_questions || '').split('\n').filter(Boolean).slice(0, 3);
            return '<div class="swl-ai-idea is-' + esc(i.status) + '">'
                + '<div class="swl-ai-idea-top"><div class="swl-ai-idea-title">' + esc(i.title) + '</div>'
                + '<span class="swl-ai-idea-votes" title="Times this came up">' + i.votes + '×</span></div>'
                + (i.purpose ? '<p>' + esc(i.purpose) + '</p>' : '')
                + kv('For', i.audience) + kv('Data', i.data_needed) + kv('Why', i.rationale)
                + (ex.length ? '<div class="swl-ai-idea-ex">' + ex.map(function (q) { return '<span>“' + esc(q) + '”</span>'; }).join('') + '</div>' : '')
                + '<div class="swl-ai-idea-foot"><span>' + (i.source === 'analysis' ? 'From the analysis' : 'Suggested in a chat') + ' · ' + esc((i.created_at || '').slice(0, 10)) + '</span>'
                + '<select class="custom-select custom-select-sm" data-status="' + i.id + '">' + opts + '</select>'
                + '<button type="button" class="swl-ai-icon-btn btn-delete" data-idea-del="' + i.id + '" title="Delete"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></div>'
                + '</div>';
        }).join('') + '</div>');
    }

    function kv(k, v) { return v ? '<div class="swl-ai-idea-kv"><b>' + esc(k) + '</b> ' + esc(v) + '</div>' : ''; }

    function analyse() {
        var $b = $('#ai_analyse_btn').prop('disabled', true);
        notice('info', 'Reading the recent questions… this can take a minute.');
        $.ajax({ url: CFG.url, type: 'POST', dataType: 'json', timeout: 330000, data: { action: 'analyse' } }).done(function (r) {
            if (!r || !r.ok) { notice('danger', (r && r.error) || 'The analysis failed.'); return; }
            render(r);
            notice('success', r.added ? r.added + ' page idea' + (r.added === 1 ? '' : 's') + ' added or reinforced.' : 'No new ideas this time.');
        }).fail(function (x) { notice('danger', (x.responseJSON && x.responseJSON.error) || 'The analysis failed.'); })
          .always(function () { $b.prop('disabled', false); });
    }

    // ── Shared ──────────────────────────────────────────────────────────────
    function post(body, after) {
        $.post(CFG.url, body, null, 'json').done(function (r) {
            if (!r || !r.ok) { notice('danger', (r && r.error) || 'Could not save.'); return; }
            render(r);
            if (after) { after(r); }
        }).fail(function (x) { notice('danger', (x.responseJSON && x.responseJSON.error) || 'Could not save.'); });
    }

    function notice(kind, msg) {
        $('#resultados_ajax').html('<div class="alert alert-' + kind + '">' + esc(msg) + '</div>');
        if (kind === 'success') { setTimeout(function () { $('#resultados_ajax .alert-success').fadeOut(); }, 4000); }
    }

    function confirmBox(title, text, fn) {
        if (window.Swal) {
            Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes' })
                .then(function (r) { if (r.isConfirmed) { fn(); } });
        } else if (window.confirm(title)) { fn(); }
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
})(window.jQuery);
