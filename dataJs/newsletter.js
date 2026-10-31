"use strict";

/* ===========================================================================
   E-mail newsletter builder (views/tools/newsletter.php).

   A design is plain data:
       { settings: { bodyBg, contentBg, font, textColor, headingColor, linkColor, accent },
         blocks:   [ { id, type, props }, ... ] }

   renderBlock() turns one block into e-mail-safe HTML (tables, inline styles,
   600px wide, stacking on phones). The same function draws the canvas (with
   editing handles around each block) and produces the HTML that is sent, so
   what the sender sees is what the reader gets.

   The canvas is a same-origin iframe, so the app's own CSS never leaks into
   the e-mail. Blocks are dragged in from the palette (or clicked to add),
   reordered by their handle, edited inline (text) or in the inspector
   (everything else). Each change is kept for undo and autosaved to this
   browser; "Save Design" stores it on the server.
   =========================================================================== */

(function ($) {
    var CFG = window.NLB_CONFIG || {};
    var AJAX = CFG.ajax || 'ajax/tools/newsletter_ajax.php';
    var DRAFT_KEY = 'nlb_draft_v1';
    var WIDTH = 600;

    // ── State ───────────────────────────────────────────────────────────────
    var S = {
        design: null,          // { settings, blocks }
        selected: null,        // block id
        designId: 0,           // saved design id (0 = not saved)
        name: '',
        undo: [],
        redo: [],
        drag: null,            // { kind: 'new', type } | { kind: 'move', id }
        device: 'desktop',
        reach: 0,
        dirty: false
    };

    // ── Small helpers ───────────────────────────────────────────────────────
    function uid() { return 'b' + Math.random().toString(36).slice(2, 10); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function esc(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }
    /** Dark or light text for a background colour. */
    function onColor(bg) {
        var h = String(bg || '').replace('#', '');
        if (h.length === 3) h = h.replace(/(.)/g, '$1$1');
        if (h.length < 6) return '#192A3E';
        var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#192A3E' : '#FFFFFF';
    }
    function isColor(c) { return /^#[0-9a-f]{3,8}$/i.test(String(c || '')); }
    function col(c, d) { return isColor(c) ? c : d; }

    /** Absolute URL for an e-mail (a mail client has no page to resolve against). */
    function absUrl(u) {
        u = String(u || '').trim();
        if (!u || /^([a-z][a-z0-9+.-]*:|\/\/|#|\[)/i.test(u)) return u;
        return (CFG.siteUrl || '') + '/' + u.replace(/^\.?\/+/, '');
    }

    /** Remove anything active from HTML typed or pasted into a design. */
    function clean(html) {
        var doc = new DOMParser().parseFromString('<body>' + String(html || '') + '</body>', 'text/html');
        $(doc.body).find('script,iframe,object,embed,form,input,button,textarea,select,link,meta,base,style').remove();
        $(doc.body).find('*').each(function () {
            var el = this;
            $.each($.makeArray(el.attributes), function (_, a) {
                var n = a.name.toLowerCase(), v = String(a.value || '').replace(/[\x00-\x20]+/g, '').toLowerCase();
                if (n.indexOf('on') === 0 || n === 'contenteditable' || n.indexOf('data-nlb') === 0 ||
                    ((n === 'href' || n === 'src' || n === 'background') && /^(javascript|vbscript|data):/.test(v) && !/^data:image\//.test(v))) {
                    el.removeAttribute(a.name);
                }
            });
        });
        return doc.body.innerHTML;
    }

    function ajax(data, opts) {
        var fd = new FormData();
        $.each(data, function (k, v) { fd.append(k, v); });
        return $.ajax($.extend({ type: 'POST', url: AJAX, data: fd, contentType: false, processData: false, dataType: 'json', cache: false }, opts || {}));
    }

    function toast(msg, icon) {
        if (window.Swal) {
            Swal.fire({ toast: true, position: 'top-end', timer: 2600, showConfirmButton: false, icon: icon || 'success', title: msg });
        }
    }
    function alertErr(errors, title) {
        var list = Array.isArray(errors) ? errors : [errors || 'Something went wrong.'];
        Swal.fire({ title: title || 'Error', html: '<ul class="text-left">' + list.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>', icon: 'error' });
    }
    function ajaxErr(xhr) {
        var r = xhr && xhr.responseJSON;
        alertErr(r && r.errors && r.errors.length ? r.errors : ['The server did not answer (' + (xhr ? xhr.status : '?') + ').']);
    }

    // =======================================================================
    // Block types
    // =======================================================================

    var SOCIAL = {
        facebook: 'Facebook', instagram: 'Instagram', x: 'X', linkedin: 'LinkedIn',
        youtube: 'YouTube', tiktok: 'TikTok', whatsapp: 'WhatsApp', website: 'Website'
    };

    var TYPES = {
        logo:      { label: 'Logo',          icon: 'solar:crown-minimalistic-linear', d: { src: '', alt: '', width: 160, align: 'center', href: '[URL]', bg: '', pt: 24, pb: 24, px: 32 } },
        heading:   { label: 'Heading',       icon: 'solar:text-bold-linear',          d: { text: 'Your headline here', size: 28, color: '', align: 'left', weight: '700', bg: '', pt: 16, pb: 8, px: 32 } },
        text:      { label: 'Text',          icon: 'solar:text-field-linear',         d: { html: '<p>Write your message here. Click to edit, select words to make them <b>bold</b> or add a link.</p>', size: 16, color: '', align: 'left', lh: 1.6, bg: '', pt: 8, pb: 8, px: 32 } },
        image:     { label: 'Image',         icon: 'solar:gallery-linear',            d: { src: 'assets/images/newsletter/hero-shipping.jpg', alt: '', width: 100, align: 'center', href: '', radius: 0, bg: '', pt: 0, pb: 0, px: 0 } },
        button:    { label: 'Button',        icon: 'solar:cursor-linear',             d: { label: 'Click here', href: '[URL]', color: '', textColor: '', align: 'center', radius: 6, size: 16, full: false, bg: '', pt: 16, pb: 16, px: 32 } },
        imagetext: { label: 'Image + Text',  icon: 'solar:posts-carousel-horizontal-linear', d: { src: 'assets/images/newsletter/card-air.jpg', side: 'left', title: 'A short title', html: '<p>A few lines about this picture.</p>', btn: 'Read more', href: '[URL]', bg: '', pt: 16, pb: 16, px: 32 } },
        columns:   { label: 'Columns',       icon: 'solar:widget-linear',             d: { cols: null, align: 'left', bg: '', pt: 16, pb: 16, px: 32 } },
        video:     { label: 'Video',         icon: 'solar:videocamera-record-linear', d: { url: 'https://www.youtube.com/', thumb: 'assets/images/newsletter/video-thumb.jpg', caption: 'Watch the video', bg: '', pt: 16, pb: 16, px: 32 } },
        divider:   { label: 'Divider',       icon: 'solar:minus-square-linear',       d: { color: '#E1E5EA', thickness: 1, bg: '', pt: 16, pb: 16, px: 32 } },
        spacer:    { label: 'Spacer',        icon: 'solar:square-transfer-vertical-linear', d: { height: 32, bg: '' } },
        social:    { label: 'Social Links',  icon: 'solar:share-linear',              d: { links: [{ net: 'facebook', url: '' }, { net: 'instagram', url: '' }, { net: 'x', url: '' }], align: 'center', color: '#192A3E', bg: '', pt: 16, pb: 16, px: 32 } },
        footer:    { label: 'Footer',        icon: 'solar:document-text-linear',      d: { html: '<p>[SITE_NAME]</p><p><a href="[UNSUBSCRIBE_URL]">Unsubscribe</a></p>', size: 12, color: '#8392A5', align: 'center', bg: '', pt: 24, pb: 24, px: 32 } },
        html:      { label: 'Custom HTML',   icon: 'solar:code-linear',               d: { code: '<p style="margin:0">Your own HTML</p>', bg: '', pt: 16, pb: 16, px: 32 } }
    };

    // Palette entries (two column layouts share the "columns" type).
    var PALETTE = [
        ['heading'], ['text'], ['image'], ['button'], ['imagetext'],
        ['columns', 2, '2 Columns', 'solar:sidebar-minimalistic-linear'],
        ['columns', 3, '3 Columns', 'solar:widget-linear'],
        ['video'], ['logo'], ['social'], ['divider'], ['spacer'], ['footer'], ['html']
    ];

    function defaultCols(n) {
        var c = [];
        for (var i = 0; i < n; i++) {
            c.push({ img: true, src: 'assets/images/newsletter/' + ['card-air.jpg', 'card-sea.jpg', 'card-warehouse.jpg'][i % 3], title: 'Column title', html: '<p>Some text for this column.</p>', btn: 'Read more', href: '[URL]' });
        }
        return c;
    }

    function mk(type, props, n) {
        var t = TYPES[type];
        var p = $.extend({}, clone(t.d), clone(props || {}));
        if (type === 'columns' && !p.cols) p.cols = defaultCols(n || 2);
        if (type === 'logo' && !p.src) p.src = CFG.logo || '';
        if (type === 'logo' && !p.alt) p.alt = CFG.siteName || '';
        return { id: uid(), type: type, props: p };
    }

    // =======================================================================
    // Rendering (e-mail HTML)
    // =======================================================================

    var EMAIL_CSS =
        'body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}' +
        'table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}' +
        'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}' +
        'p{margin:0 0 12px 0;}' +
        'h1,h2,h3{margin:0;}' +
        '@media only screen and (max-width:620px){' +
        '.nl-container{width:100%!important;max-width:100%!important;}' +
        '.nl-col{display:block!important;width:100%!important;max-width:100%!important;padding-left:0!important;padding-right:0!important;padding-bottom:18px!important;}' +
        '.nl-img{width:100%!important;max-width:100%!important;height:auto!important;}' +
        '.nl-px{padding-left:20px!important;padding-right:20px!important;}' +
        '.nl-btn{display:block!important;}' +
        '}';

    function settings() { return S.design.settings; }

    /** Inner content width of a block in px (for image sizes). */
    function innerW(p) { return Math.max(100, WIDTH - 2 * num(p.px, 0)); }

    function wrap(p, inner, extraTd) {
        var bg = isColor(p.bg) ? p.bg : '';
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"' + (bg ? ' bgcolor="' + bg + '" style="background-color:' + bg + ';"' : '') + '>' +
            '<tr><td class="nl-px" ' + (extraTd || '') + ' style="padding:' + num(p.pt, 0) + 'px ' + num(p.px, 0) + 'px ' + num(p.pb, 0) + 'px ' + num(p.px, 0) + 'px;">' +
            inner + '</td></tr></table>';
    }

    function link(href, inner, style) {
        href = String(href || '').trim();
        if (!href) return inner;
        return '<a href="' + esc(href) + '" target="_blank" style="' + (style || 'text-decoration:none;') + '">' + inner + '</a>';
    }

    function textStyle(p, fallbackColor, size) {
        var st = settings();
        return 'font-family:' + st.font + ';font-size:' + num(size || p.size, 16) + 'px;line-height:' + num(p.lh, 1.6) + ';color:' + col(p.color, fallbackColor || st.textColor) + ';text-align:' + (p.align || 'left') + ';';
    }

    /** ed: attribute that makes an element inline-editable in the canvas. */
    function ed(mode, field, rich) {
        return mode === 'edit' ? ' contenteditable="true" data-nlb-edit="' + field + '"' + (rich ? ' data-nlb-rich="1"' : '') : '';
    }

    function imgTag(mode, src, alt, w, extra) {
        var s = mode === 'export' ? absUrl(src) : src;
        if (!s) {
            return mode === 'edit' ? '<div class="nlb-ph">Choose an image in the block settings</div>' : '';
        }
        return '<img src="' + esc(s) + '" alt="' + esc(alt || '') + '" width="' + Math.round(w) + '" class="nl-img" style="display:block;width:100%;max-width:' + Math.round(w) + 'px;height:auto;border:0;' + (extra || '') + '">';
    }

    function buttonHtml(mode, label, href, opts) {
        var st = settings();
        var bg = col(opts.color, st.accent);
        var fg = col(opts.textColor, onColor(bg));
        var r = num(opts.radius, 6);
        var size = num(opts.size, 16);
        var align = opts.align || 'center';
        var a = '<a href="' + esc(href || '#') + '" target="_blank" class="nl-btn" style="display:' + (opts.full ? 'block' : 'inline-block') + ';padding:' + Math.round(size * 0.8) + 'px ' + Math.round(size * 1.7) + 'px;font-family:' + st.font + ';font-size:' + size + 'px;font-weight:700;line-height:1.2;color:' + fg + ';text-decoration:none;border-radius:' + r + 'px;text-align:center;"' + ed(mode, opts.field || 'label') + '>' + label + '</a>';
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' + align + '"' + (opts.full ? ' width="100%"' : '') + ' style="' + (align === 'center' ? 'margin:0 auto;' : '') + '">' +
            '<tr><td align="center" bgcolor="' + bg + '" style="background-color:' + bg + ';border-radius:' + r + 'px;">' + a + '</td></tr></table>';
    }

    function youtubeThumb(url) {
        var m = String(url || '').match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_-]{11})/);
        return m ? 'https://img.youtube.com/vi/' + m[1] + '/hqdefault.jpg' : '';
    }

    function renderBlock(b, mode) {
        var p = b.props, st = settings(), w;
        switch (b.type) {
            case 'logo':
                w = Math.min(num(p.width, 160), innerW(p));
                var logoImg = p.src
                    ? '<img src="' + esc(mode === 'export' ? absUrl(p.src) : p.src) + '" alt="' + esc(p.alt) + '" width="' + w + '" style="display:inline-block;width:' + w + 'px;max-width:100%;height:auto;border:0;">'
                    : (mode === 'edit' ? '<div class="nlb-ph">Add your logo in the block settings</div>' : '<span style="font-family:' + st.font + ';font-size:22px;font-weight:700;color:' + st.headingColor + ';">' + esc(CFG.siteName) + '</span>');
                return wrap(p, link(p.href, logoImg), 'align="' + p.align + '"');

            case 'heading':
                return wrap(p, '<h2 style="margin:0;font-family:' + st.font + ';font-size:' + num(p.size, 28) + 'px;line-height:1.25;font-weight:' + (p.weight || '700') + ';color:' + col(p.color, st.headingColor) + ';text-align:' + p.align + ';"' + ed(mode, 'text') + '>' + clean(p.text) + '</h2>');

            case 'text':
            case 'footer':
                return wrap(p, '<div style="' + textStyle(p, b.type === 'footer' ? '#8392A5' : '') + '"' + ed(mode, 'html', true) + '>' + clean(p.html) + '</div>');

            case 'image':
                w = innerW(p) * Math.min(100, Math.max(10, num(p.width, 100))) / 100;
                return wrap(p, link(p.href, imgTag(mode, p.src, p.alt, w, p.radius ? 'border-radius:' + num(p.radius, 0) + 'px;' : '') ), 'align="' + p.align + '"');

            case 'button':
                return wrap(p, buttonHtml(mode, clean(p.label), p.href, p), 'align="' + p.align + '"');

            case 'divider':
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:' + num(p.thickness, 1) + 'px solid ' + col(p.color, '#E1E5EA') + ';font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table>');

            case 'spacer':
                var h = num(p.height, 32);
                var sbg = isColor(p.bg) ? p.bg : '';
                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"' + (sbg ? ' bgcolor="' + sbg + '" style="background-color:' + sbg + ';"' : '') + '><tr><td style="height:' + h + 'px;line-height:' + h + 'px;font-size:1px;">&nbsp;</td></tr></table>';

            case 'imagetext':
                var iw = Math.round(innerW(p) * 0.44);
                var imgTd = '<td class="nl-col" width="44%" valign="top" style="width:44%;padding-' + (p.side === 'left' ? 'right' : 'left') + ':12px;">' + link(p.href, imgTag(mode, p.src, p.title, iw, 'border-radius:6px;')) + '</td>';
                var txtTd = '<td class="nl-col" width="56%" valign="top" style="width:56%;padding-' + (p.side === 'left' ? 'left' : 'right') + ':12px;">' + columnText(mode, p, '') + '</td>';
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' + (p.side === 'left' ? imgTd + txtTd : txtTd + imgTd) + '</tr></table>');

            case 'columns':
                var cols = p.cols || [];
                var n = Math.max(1, cols.length), gap = 16;
                var cw = Math.round((innerW(p) - gap * (n - 1)) / n);
                var tds = cols.map(function (c, i) {
                    var pad = 'padding-left:' + (i === 0 ? 0 : gap / 2) + 'px;padding-right:' + (i === n - 1 ? 0 : gap / 2) + 'px;';
                    var inner = (c.img ? link(c.href, imgTag(mode, c.src, c.title, cw, 'border-radius:6px;')) + '<div style="height:12px;line-height:12px;font-size:1px;">&nbsp;</div>' : '') +
                        columnText(mode, $.extend({}, c, { align: p.align }), 'cols.' + i + '.');
                    return '<td class="nl-col" width="' + Math.floor(100 / n) + '%" valign="top" style="width:' + Math.floor(100 / n) + '%;' + pad + '">' + inner + '</td>';
                });
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' + tds.join('') + '</tr></table>');

            case 'video':
                var thumb = p.thumb || youtubeThumb(p.url);
                var vimg = imgTag(mode, thumb, p.caption || 'Video', innerW(p), 'border-radius:6px;');
                var cap = p.caption ? '<div style="padding-top:10px;font-family:' + st.font + ';font-size:15px;font-weight:700;text-align:center;">' + link(p.url, '&#9654;&nbsp; ' + esc(p.caption), 'color:' + st.linkColor + ';text-decoration:none;') + '</div>' : '';
                return wrap(p, link(p.url, vimg) + cap, 'align="center"');

            case 'social':
                var pills = (p.links || []).filter(function (l) { return l.url; }).map(function (l) {
                    return '<a href="' + esc(l.url) + '" target="_blank" style="display:inline-block;margin:0 4px 8px 4px;padding:8px 16px;border-radius:999px;background-color:' + col(p.color, '#192A3E') + ';color:#FFFFFF;font-family:' + st.font + ';font-size:13px;font-weight:700;text-decoration:none;">' + esc(SOCIAL[l.net] || l.net) + '</a>';
                }).join('');
                if (!pills && mode === 'edit') pills = '<div class="nlb-ph">Add your social pages in the block settings</div>';
                return wrap(p, pills, 'align="' + p.align + '"');

            case 'html':
                return wrap(p, clean(p.code));
        }
        return '';
    }

    /** Title, text and a small link button: used by columns and image+text. */
    function columnText(mode, c, prefix) {
        var st = settings();
        var out = '';
        if (c.title !== undefined) {
            out += '<h3 style="margin:0 0 8px 0;font-family:' + st.font + ';font-size:18px;line-height:1.3;font-weight:700;color:' + st.headingColor + ';text-align:' + (c.align || 'left') + ';"' + ed(mode, prefix + 'title') + '>' + clean(c.title) + '</h3>';
        }
        out += '<div style="font-family:' + st.font + ';font-size:15px;line-height:1.55;color:' + st.textColor + ';text-align:' + (c.align || 'left') + ';"' + ed(mode, prefix + 'html', true) + '>' + clean(c.html) + '</div>';
        if (c.btn) {
            out += '<div style="padding-top:6px;text-align:' + (c.align || 'left') + ';">' + link(c.href || '#', '<span style="font-family:' + st.font + ';font-size:15px;font-weight:700;color:' + st.linkColor + ';"' + ed(mode, prefix + 'btn') + '>' + clean(c.btn) + ' &rarr;</span>') + '</div>';
        }
        return out;
    }

    function emailHead(title) {
        var st = S.design ? settings() : blankSettings();
        return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="x-apple-disable-message-reformatting">' +
            '<title>' + esc(title || '') + '</title><style>' + EMAIL_CSS + 'a{color:' + st.linkColor + ';}</style>';
    }

    /** The complete e-mail, ready to send. */
    function exportHtml(subject, preheader, mode) {
        var st = settings();
        var body = S.design.blocks.map(function (b) { return renderBlock(b, mode || 'export'); }).join('\n');
        var pre = preheader ? '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' + esc(preheader) + '&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>' : '';
        return '<!DOCTYPE html>\n<html lang="en">' + emailHead(subject) + '</head>' +
            '<body style="margin:0;padding:0;background-color:' + st.bodyBg + ';">' + pre +
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.bodyBg + '" style="background-color:' + st.bodyBg + ';">' +
            '<tr><td align="center" style="padding:24px 10px;">' +
            '<table role="presentation" class="nl-container" width="' + WIDTH + '" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.contentBg + '" style="width:' + WIDTH + 'px;max-width:' + WIDTH + 'px;background-color:' + st.contentBg + ';">' +
            '<tr><td style="padding:0;">\n' + body + '\n</td></tr></table>' +
            '</td></tr></table></body></html>';
    }

    /** Same e-mail with relative image paths (for in-page previews). */
    function previewHtml(subject, preheader) {
        return exportHtml(subject, preheader, 'preview').replace('<head>', '<head><base href="' + esc(location.href.replace(/[^/]*$/, '')) + '" target="_blank">');
    }

    // =======================================================================
    // Canvas
    // =======================================================================

    var canvas, cdoc;

    var CANVAS_CSS =
        'html,body{margin:0;}' +
        'body{min-height:100vh;}' +
        '#nlb-root{min-height:400px;}' +
        '.nlb-block{position:relative;outline:1px dashed transparent;outline-offset:-1px;cursor:pointer;}' +
        '.nlb-block:hover{outline-color:#9BA9BB;}' +
        '.nlb-block.is-selected{outline:2px solid #5548D1;outline-offset:-2px;}' +
        '.nlb-tools{position:absolute;top:4px;right:4px;z-index:5;display:none;gap:2px;background:#192A3E;border-radius:6px;padding:3px;box-shadow:0 4px 14px rgba(0,0,0,.2);}' +
        '.nlb-block:hover .nlb-tools,.nlb-block.is-selected .nlb-tools{display:flex;}' +
        '.nlb-tools button,.nlb-tools .nlb-grip{all:unset;cursor:pointer;color:#fff;font:600 12px/1 -apple-system,Segoe UI,Arial,sans-serif;padding:6px 7px;border-radius:4px;}' +
        '.nlb-tools button:hover,.nlb-tools .nlb-grip:hover{background:#253E5B;}' +
        '.nlb-tools .nlb-grip{cursor:grab;-webkit-user-drag:element;user-select:none;}' +
        '.nlb-tag{position:absolute;top:4px;left:4px;z-index:5;display:none;background:#5548D1;color:#fff;font:600 11px/1 -apple-system,Segoe UI,Arial,sans-serif;padding:4px 7px;border-radius:4px;pointer-events:none;}' +
        '.nlb-block.is-selected .nlb-tag{display:block;}' +
        '[contenteditable]:focus{outline:2px dashed #FFCB01;outline-offset:2px;}' +
        '[contenteditable]:empty:before{content:"Type here";color:#9BA9BB;}' +
        '.nlb-drop{height:4px;margin:-2px 0;background:#5548D1;border-radius:2px;position:relative;z-index:6;box-shadow:0 0 0 3px rgba(85,72,209,.2);}' +
        '.nlb-empty{margin:24px;padding:60px 20px;border:2px dashed #9BA9BB;border-radius:12px;text-align:center;color:#6B7A8C;font:500 15px/1.5 -apple-system,Segoe UI,Arial,sans-serif;}' +
        '.nlb-ph{padding:28px 12px;border:2px dashed #C9D2DD;border-radius:8px;text-align:center;color:#6B7A8C;font:500 13px/1.4 -apple-system,Segoe UI,Arial,sans-serif;background:#F6F7F9;}' +
        '.nlb-dragging{opacity:.35;}';

    function initCanvas() {
        canvas = document.getElementById('nlb_canvas');
        var html = '<!DOCTYPE html><html>' + emailHead('') + '<style id="nlb-css">' + CANVAS_CSS + '</style></head><body><div id="nlb-outer"></div></body></html>';
        cdoc = canvas.contentDocument;
        cdoc.open(); cdoc.write(html); cdoc.close();
        bindCanvas();
    }

    /** Redraw the whole canvas from the design. */
    function draw() {
        if (!cdoc || !S.design) return;
        var st = settings();
        // Link colour and the body background follow the e-mail style.
        $(cdoc).find('head style').first().text(EMAIL_CSS + 'a{color:' + st.linkColor + ';}');
        cdoc.body.style.backgroundColor = st.bodyBg;

        var blocks = S.design.blocks.map(function (b) {
            return '<div class="nlb-block' + (b.id === S.selected ? ' is-selected' : '') + '" data-id="' + b.id + '">' +
                '<span class="nlb-tag">' + esc(TYPES[b.type].label) + '</span>' +
                '<div class="nlb-tools">' +
                '<span class="nlb-grip" draggable="true" title="Drag to move" aria-label="Drag to move">&#8942;&#8942;</span>' +
                '<button type="button" data-act="up" title="Move up">&#8593;</button>' +
                '<button type="button" data-act="down" title="Move down">&#8595;</button>' +
                '<button type="button" data-act="dup" title="Duplicate">&#10697;</button>' +
                '<button type="button" data-act="del" title="Delete">&#10005;</button>' +
                '</div>' + renderBlock(b, 'edit') + '</div>';
        }).join('');
        if (!blocks) blocks = '<div class="nlb-empty">Drag blocks here from the left, or click a block to add it.</div>';

        cdoc.getElementById('nlb-outer').innerHTML =
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' + st.bodyBg + ';">' +
            '<tr><td align="center" style="padding:24px 10px;">' +
            '<table role="presentation" class="nl-container" width="' + WIDTH + '" cellpadding="0" cellspacing="0" border="0" style="width:' + WIDTH + 'px;max-width:' + WIDTH + 'px;background-color:' + st.contentBg + ';">' +
            '<tr><td id="nlb-root" style="padding:0;">' + blocks + '</td></tr></table></td></tr></table>';
        fitCanvas();
    }

    function fitCanvas() {
        if (!cdoc || !cdoc.body) return;
        // the iframe grows with the e-mail; the page scrolls, not the frame
        canvas.style.height = Math.max(560, cdoc.documentElement.scrollHeight) + 'px';
    }

    function find(id) {
        for (var i = 0; i < S.design.blocks.length; i++) if (S.design.blocks[i].id === id) return i;
        return -1;
    }

    /** Where in the list a drop at clientY lands (index to insert before). */
    function dropIndex(y) {
        var els = cdoc.querySelectorAll('.nlb-block');
        for (var i = 0; i < els.length; i++) {
            var r = els[i].getBoundingClientRect();
            if (y < r.top + r.height / 2) return i;
        }
        return els.length;
    }

    function showDrop(idx) {
        $(cdoc).find('.nlb-drop').remove();
        var root = cdoc.getElementById('nlb-root');
        if (!root) return;
        var bar = cdoc.createElement('div');
        bar.className = 'nlb-drop';
        var els = root.querySelectorAll('.nlb-block');
        if (idx < els.length) root.insertBefore(bar, els[idx]); else root.appendChild(bar);
    }

    function bindCanvas() {
        var $d = $(cdoc);

        // Select a block
        $d.on('mousedown', '.nlb-block', function (e) {
            var id = $(this).attr('data-id');
            if (id !== S.selected) {
                S.selected = id;
                $d.find('.nlb-block').removeClass('is-selected');
                $(this).addClass('is-selected');
                inspector();
            }
            e.stopPropagation();
        });
        $d.on('click', 'a', function (e) { e.preventDefault(); });

        // Toolbar actions
        $d.on('click', '.nlb-tools button[data-act]', function (e) {
            e.preventDefault(); e.stopPropagation();
            var id = $(this).closest('.nlb-block').attr('data-id');
            act($(this).attr('data-act'), id);
        });

        // Inline editing: keep the data in step, without redrawing (keeps the caret)
        $d.on('input', '[data-nlb-edit]', function () {
            var id = $(this).closest('.nlb-block').attr('data-id');
            var i = find(id);
            if (i < 0) return;
            var path = $(this).attr('data-nlb-edit').split('.');
            var target = S.design.blocks[i].props;
            for (var k = 0; k < path.length - 1; k++) target = target[path[k]];
            target[path[path.length - 1]] = this.innerHTML;
            changedSoft();
            fitCanvas();
        });
        // Paste as plain text: pasted HTML from Word or a website brings fonts and junk
        $d.on('paste', '[data-nlb-edit]', function (e) {
            e.preventDefault();
            var t = (e.originalEvent.clipboardData || window.clipboardData).getData('text/plain');
            cdoc.execCommand('insertText', false, t);
        });
        // Enter in a one-line field (heading, button) must not create paragraphs
        $d.on('keydown', '[data-nlb-edit]:not([data-nlb-rich])', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); }
        });
        $d.on('focusout', '[data-nlb-edit]', function () { commit(); });

        // Keyboard shortcuts inside the canvas
        $d.on('keydown', function (e) { shortcuts(e); });

        // Drag: reorder by the grip
        $d.on('dragstart', '.nlb-grip', function (e) {
            var id = $(this).closest('.nlb-block').attr('data-id');
            S.drag = { kind: 'move', id: id };
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', 'nlb:' + id);
            var blockEl = $(this).closest('.nlb-block')[0];
            try { e.originalEvent.dataTransfer.setDragImage(blockEl, 20, 20); } catch (x) { /* older browsers */ }
            setTimeout(function () { $(blockEl).addClass('nlb-dragging'); }, 0);
        });
        $d.on('dragend', '.nlb-grip', function () {
            S.drag = null;
            $d.find('.nlb-dragging').removeClass('nlb-dragging');
            $d.find('.nlb-drop').remove();
        });

        // Drop zone (palette tiles and the grip both land here)
        cdoc.addEventListener('dragover', function (e) {
            if (!S.drag) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = S.drag.kind === 'move' ? 'move' : 'copy';
            showDrop(dropIndex(e.clientY));
        });
        cdoc.addEventListener('dragleave', function (e) {
            if (!e.relatedTarget) $(cdoc).find('.nlb-drop').remove();
        });
        cdoc.addEventListener('drop', function (e) {
            if (!S.drag) return;
            e.preventDefault();
            var idx = dropIndex(e.clientY);
            $(cdoc).find('.nlb-drop').remove();
            if (S.drag.kind === 'new') {
                insertAt(idx, mk(S.drag.type, {}, S.drag.n));
            } else {
                var from = find(S.drag.id);
                if (from >= 0) {
                    var b = S.design.blocks.splice(from, 1)[0];
                    if (from < idx) idx--;
                    S.design.blocks.splice(idx, 0, b);
                    commit(true);
                }
            }
            S.drag = null;
        });
    }

    // =======================================================================
    // Editing operations
    // =======================================================================

    function insertAt(idx, block) {
        S.design.blocks.splice(idx, 0, block);
        S.selected = block.id;
        commit(true);
        inspector();
        // bring the new block into view
        var el = cdoc.querySelector('.nlb-block[data-id="' + block.id + '"]');
        if (el) {
            var top = $(canvas).offset().top + el.offsetTop - 120;
            $('html, body').animate({ scrollTop: Math.max(0, top) }, 250);
        }
    }

    function addBlock(type, n) {
        var i = S.selected ? find(S.selected) : -1;
        var idx;
        if (i >= 0) {
            idx = i + 1;
        } else {
            // Nothing selected: above the footer, so new content does not land under it
            idx = S.design.blocks.length;
            while (idx > 0 && S.design.blocks[idx - 1].type === 'footer') idx--;
        }
        insertAt(idx, mk(type, {}, n));
    }

    function act(what, id) {
        var i = find(id);
        if (i < 0) return;
        var B = S.design.blocks;
        if (what === 'up' && i > 0) { B.splice(i - 1, 0, B.splice(i, 1)[0]); }
        else if (what === 'down' && i < B.length - 1) { B.splice(i + 1, 0, B.splice(i, 1)[0]); }
        else if (what === 'dup') { var c = clone(B[i]); c.id = uid(); B.splice(i + 1, 0, c); S.selected = c.id; }
        else if (what === 'del') { B.splice(i, 1); if (S.selected === id) S.selected = null; }
        else return;
        commit(true);
        inspector();
    }

    // ── Undo / redo, autosave ───────────────────────────────────────────────
    var lastSnap = '';
    var softTimer = null;

    function snapshot() { return JSON.stringify(S.design); }

    /** A finished change: keep it for undo, redraw if asked, autosave. */
    function commit(redraw) {
        clearTimeout(softTimer);
        var now = snapshot();
        if (now !== lastSnap) {
            if (lastSnap) { S.undo.push(lastSnap); if (S.undo.length > 60) S.undo.shift(); }
            S.redo = [];
            lastSnap = now;
            S.dirty = true;
            saveDraft();
        }
        if (redraw) draw();
        histButtons();
        refreshSend();
    }

    /** Typing: commit once the typing pauses. */
    function changedSoft() {
        clearTimeout(softTimer);
        softTimer = setTimeout(function () { commit(false); }, 700);
    }

    function undo() {
        if (!S.undo.length) return;
        S.redo.push(snapshot());
        lastSnap = S.undo.pop();
        S.design = JSON.parse(lastSnap);
        if (S.selected && find(S.selected) < 0) S.selected = null;
        draw(); inspector(); styleForm(); histButtons(); saveDraft();
    }
    function redo() {
        if (!S.redo.length) return;
        S.undo.push(snapshot());
        lastSnap = S.redo.pop();
        S.design = JSON.parse(lastSnap);
        if (S.selected && find(S.selected) < 0) S.selected = null;
        draw(); inspector(); styleForm(); histButtons(); saveDraft();
    }
    function histButtons() {
        $('#nlb_undo').prop('disabled', !S.undo.length);
        $('#nlb_redo').prop('disabled', !S.redo.length);
    }

    function shortcuts(e) {
        var t = e.target;
        var typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
        if ((e.ctrlKey || e.metaKey) && !e.altKey) {
            var k = e.key.toLowerCase();
            if (k === 'z' && !e.shiftKey && !typing) { e.preventDefault(); undo(); }
            else if ((k === 'y' || (k === 'z' && e.shiftKey)) && !typing) { e.preventDefault(); redo(); }
            else if (k === 's') { e.preventDefault(); saveDesign(); }
        } else if (e.key === 'Delete' && !typing && S.selected && $('#nlb_step_design').hasClass('active')) {
            e.preventDefault(); act('del', S.selected);
        }
    }

    function saveDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({
                design: S.design, id: S.designId, name: S.name,
                subject: $('#nlb_subject').val(), preheader: $('#nlb_preheader').val(), at: Date.now()
            }));
        } catch (x) { /* storage full or disabled */ }
    }

    // =======================================================================
    // Inspector (block settings) and e-mail style
    // =======================================================================

    var FONTS = [
        ['Arial, Helvetica, sans-serif', 'Arial'],
        ['Helvetica, Arial, sans-serif', 'Helvetica'],
        ['Verdana, Geneva, sans-serif', 'Verdana'],
        ['Trebuchet MS, Helvetica, sans-serif', 'Trebuchet MS'],
        ['Tahoma, Geneva, sans-serif', 'Tahoma'],
        ['Georgia, Times New Roman, serif', 'Georgia'],
        ['Times New Roman, Times, serif', 'Times New Roman'],
        ['Courier New, Courier, monospace', 'Courier New']
    ];
    var STOCK = ['hero-shipping.jpg', 'hero-promo.jpg', 'hero-welcome.jpg', 'hero-holiday.jpg', 'card-air.jpg', 'card-sea.jpg', 'card-warehouse.jpg', 'card-delivery.jpg', 'video-thumb.jpg'];

    // Field kinds: text, url, textarea, range, color, align, select, check, image, social, cols, format
    var COMMON = [
        { k: 'bg', t: 'color', l: 'Block background', clear: true },
        { k: 'pt', t: 'range', l: 'Space above', min: 0, max: 80 },
        { k: 'pb', t: 'range', l: 'Space below', min: 0, max: 80 },
        { k: 'px', t: 'range', l: 'Side margins', min: 0, max: 80 }
    ];
    var FIELDS = {
        logo: [{ k: 'src', t: 'image', l: 'Logo image' }, { k: 'alt', t: 'text', l: 'Alt text' }, { k: 'width', t: 'range', l: 'Width (px)', min: 40, max: 600 }, { k: 'href', t: 'url', l: 'Link' }, { k: 'align', t: 'align' }],
        heading: [{ t: 'note', l: 'Click the heading in the e-mail to change its words.' }, { t: 'format' }, { k: 'size', t: 'range', l: 'Size', min: 14, max: 56 }, { k: 'weight', t: 'select', l: 'Weight', o: [['400', 'Regular'], ['700', 'Bold'], ['900', 'Heavy']] }, { k: 'color', t: 'color', l: 'Colour', clear: true }, { k: 'align', t: 'align' }],
        text: [{ t: 'note', l: 'Click the text in the e-mail to type. Select words to format them.' }, { t: 'format', rich: true }, { k: 'size', t: 'range', l: 'Text size', min: 10, max: 28 }, { k: 'lh', t: 'range', l: 'Line height', min: 1, max: 2.4, step: 0.1 }, { k: 'color', t: 'color', l: 'Text colour', clear: true }, { k: 'align', t: 'align', justify: true }],
        footer: [{ t: 'note', l: 'Keep the [UNSUBSCRIBE_URL] link: bulk e-mail must let people opt out.' }, { t: 'format', rich: true }, { k: 'size', t: 'range', l: 'Text size', min: 10, max: 18 }, { k: 'color', t: 'color', l: 'Text colour', clear: true }, { k: 'align', t: 'align' }],
        image: [{ k: 'src', t: 'image', l: 'Image' }, { k: 'alt', t: 'text', l: 'Alt text (shown when images are off)' }, { k: 'href', t: 'url', l: 'Link when clicked' }, { k: 'width', t: 'range', l: 'Width (%)', min: 10, max: 100 }, { k: 'radius', t: 'range', l: 'Rounded corners', min: 0, max: 40 }, { k: 'align', t: 'align' }],
        button: [{ k: 'label', t: 'text', l: 'Button text', plain: true }, { k: 'href', t: 'url', l: 'Link' }, { k: 'color', t: 'color', l: 'Button colour', clear: true }, { k: 'textColor', t: 'color', l: 'Text colour', clear: true }, { k: 'size', t: 'range', l: 'Text size', min: 12, max: 26 }, { k: 'radius', t: 'range', l: 'Rounded corners', min: 0, max: 40 }, { k: 'full', t: 'check', l: 'Full width' }, { k: 'align', t: 'align' }],
        imagetext: [{ k: 'src', t: 'image', l: 'Image' }, { k: 'side', t: 'select', l: 'Image side', o: [['left', 'Left'], ['right', 'Right']] }, { t: 'note', l: 'Click the title and text in the e-mail to edit them.' }, { t: 'format', rich: true }, { k: 'btn', t: 'text', l: 'Link text (empty for none)', plain: true }, { k: 'href', t: 'url', l: 'Link' }],
        columns: [{ t: 'cols' }, { t: 'format', rich: true }, { k: 'align', t: 'align' }],
        video: [{ k: 'url', t: 'url', l: 'Video link (YouTube, Vimeo, Facebook...)' }, { k: 'thumb', t: 'image', l: 'Thumbnail (empty = YouTube picture)' }, { k: 'caption', t: 'text', l: 'Caption' }, { t: 'note', l: 'E-mail cannot play video: readers see the picture and open the video when they click it.' }],
        divider: [{ k: 'color', t: 'color', l: 'Line colour' }, { k: 'thickness', t: 'range', l: 'Thickness', min: 1, max: 10 }],
        spacer: [{ k: 'height', t: 'range', l: 'Height', min: 4, max: 120 }, { k: 'bg', t: 'color', l: 'Background', clear: true }],
        social: [{ t: 'social' }, { k: 'color', t: 'color', l: 'Button colour' }, { k: 'align', t: 'align' }],
        html: [{ k: 'code', t: 'textarea', l: 'HTML', rows: 10 }, { t: 'note', l: 'Scripts, forms and frames are removed. Use inline styles: many mail clients ignore others.' }]
    };

    function selectedBlock() { var i = find(S.selected); return i >= 0 ? S.design.blocks[i] : null; }

    function field(f, val, path) {
        var id = 'nlbf_' + (path || f.k || f.t).replace(/\W/g, '_');
        var label = f.l ? '<label for="' + id + '">' + esc(f.l) + '</label>' : '';
        var data = ' data-path="' + esc(path || f.k) + '"';
        switch (f.t) {
            case 'note':
                return '<p class="nlb-note">' + esc(f.l) + '</p>';
            case 'text':
            case 'url':
                var v = f.plain ? $('<div>').html(val || '').text() : (val || '');
                return '<div class="form-group">' + label + '<input type="text" class="form-control nlb-in" id="' + id + '"' + data + (f.plain ? ' data-plain="1"' : '') + ' value="' + esc(v) + '"' + (f.t === 'url' ? ' placeholder="https://... or a tag like [URL]"' : '') + '>' +
                    (f.t === 'url' ? tagMenu(id) : '') + '</div>';
            case 'textarea':
                return '<div class="form-group">' + label + '<textarea class="form-control nlb-in nlb-code" id="' + id + '"' + data + ' rows="' + (f.rows || 4) + '">' + esc(val || '') + '</textarea></div>';
            case 'range':
                return '<div class="form-group nlb-range">' + label + '<div class="nlb-inline"><input type="range" class="custom-range nlb-in" id="' + id + '"' + data + ' min="' + f.min + '" max="' + f.max + '" step="' + (f.step || 1) + '" value="' + esc(val) + '"><output>' + esc(val) + '</output></div></div>';
            case 'color':
                return '<div class="form-group">' + label + '<div class="nlb-inline nlb-color"><input type="color" class="nlb-in" id="' + id + '"' + data + ' value="' + (isColor(val) && val.length === 7 ? val : '#ffffff') + '"' + (isColor(val) ? '' : ' data-empty="1"') + '>' +
                    '<input type="text" class="form-control nlb-in nlb-hex"' + data + ' value="' + esc(isColor(val) ? val : '') + '" placeholder="' + (f.clear ? 'Default' : '#000000') + '" maxlength="7">' +
                    (f.clear ? '<button type="button" class="btn btn-secondary btn-sm nlb-clear"' + data + ' title="Use the default">&times;</button>' : '') + '</div></div>';
            case 'align':
                var opts = [['left', 'solar:align-left-linear'], ['center', 'solar:align-horizontal-center-linear'], ['right', 'solar:align-right-linear']];
                if (f.justify) opts.push(['justify', 'solar:hamburger-menu-linear']);
                return '<div class="form-group"><label>Alignment</label><div class="btn-group nlb-seg d-flex" role="group">' +
                    opts.map(function (o) { return '<button type="button" class="btn btn-secondary nlb-align' + (val === o[0] ? ' active' : '') + '"' + data + ' data-val="' + o[0] + '" title="' + o[0] + '"><iconify-icon icon="' + o[1] + '"></iconify-icon></button>'; }).join('') +
                    '</div></div>';
            case 'select':
                return '<div class="form-group">' + label + '<select class="form-control custom-select nlb-in" id="' + id + '"' + data + '>' +
                    f.o.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(val) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select></div>';
            case 'check':
                return '<div class="form-group"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input nlb-in" id="' + id + '"' + data + (val ? ' checked' : '') + '><label class="custom-control-label" for="' + id + '">' + esc(f.l) + '</label></div></div>';
            case 'image':
                return '<div class="form-group">' + label +
                    '<div class="nlb-imgpick">' + (val ? '<img src="' + esc(val) + '" alt="">' : '<span>No image</span>') + '</div>' +
                    '<input type="text" class="form-control nlb-in" id="' + id + '"' + data + ' value="' + esc(val || '') + '" placeholder="Image address (https://...)">' +
                    '<div class="nlb-inline nlb-imgbtns"><button type="button" class="btn btn-secondary btn-sm nlb-upload"' + data + '><iconify-icon icon="solar:upload-linear"></iconify-icon> Upload</button>' +
                    '<button type="button" class="btn btn-secondary btn-sm nlb-stock-toggle"><iconify-icon icon="solar:gallery-wide-linear"></iconify-icon> Stock</button></div>' +
                    '<div class="nlb-stock" hidden>' + STOCK.map(function (s) { return '<button type="button" class="nlb-stock-item"' + data + ' data-val="assets/images/newsletter/' + s + '"><img src="assets/images/newsletter/' + s + '" alt=""></button>'; }).join('') +
                    (CFG.logo ? '<button type="button" class="nlb-stock-item"' + data + ' data-val="' + esc(CFG.logo) + '"><img src="' + esc(CFG.logo) + '" alt=""></button>' : '') + '</div>' +
                    '</div>';
            case 'format':
                return '<div class="form-group"><label>Format selected text</label><div class="nlb-format">' +
                    '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>' +
                    '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>' +
                    '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>' +
                    '<button type="button" data-cmd="link" title="Add link"><iconify-icon icon="solar:link-linear"></iconify-icon></button>' +
                    '<button type="button" data-cmd="unlink" title="Remove link"><iconify-icon icon="solar:link-broken-linear"></iconify-icon></button>' +
                    (f.rich ? '<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><iconify-icon icon="solar:list-linear"></iconify-icon></button>' +
                        '<button type="button" data-cmd="insertOrderedList" title="Numbered list"><iconify-icon icon="solar:list-arrow-down-linear"></iconify-icon></button>' : '') +
                    '<button type="button" data-cmd="foreColor" title="Text colour"><iconify-icon icon="solar:pallete-2-linear"></iconify-icon></button>' +
                    '<button type="button" data-cmd="removeFormat" title="Clear formatting"><iconify-icon icon="solar:eraser-linear"></iconify-icon></button>' +
                    '</div><input type="color" class="nlb-forecolor" value="#D80613" hidden>' +
                    '<div class="nlb-inline nlb-tagrow"><select class="form-control custom-select nlb-tag-insert"><option value="">Insert a personal tag&hellip;</option>' +
                    $.map(CFG.tags || {}, function (lbl, tag) { return '<option value="' + esc(tag) + '">' + esc(tag + '  ' + lbl) + '</option>'; }).join('') + '</select></div></div>';
            case 'social':
                var rows = (val || []).map(function (l, i) {
                    return '<div class="nlb-social-row nlb-inline">' +
                        '<select class="form-control custom-select nlb-in" data-path="links.' + i + '.net">' + $.map(SOCIAL, function (lbl, k) { return '<option value="' + k + '"' + (l.net === k ? ' selected' : '') + '>' + lbl + '</option>'; }).join('') + '</select>' +
                        '<input type="text" class="form-control nlb-in" data-path="links.' + i + '.url" value="' + esc(l.url) + '" placeholder="https://...">' +
                        '<button type="button" class="btn btn-secondary btn-sm nlb-social-del" data-i="' + i + '" title="Remove">&times;</button></div>';
                }).join('');
                return '<div class="form-group"><label>Social pages</label>' + rows + '<button type="button" class="btn btn-secondary btn-sm nlb-social-add"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> Add a link</button></div>';
            case 'cols':
                return '';
        }
        return '';
    }

    function tagMenu(forId) {
        return '<div class="nlb-chips">' + ['[URL]', '[TRACK_URL]', '[UNSUBSCRIBE_URL]'].map(function (t) {
            return '<button type="button" class="nlb-chip" data-for="' + forId + '" data-tag="' + t + '">' + t + '</button>';
        }).join('') + '</div>';
    }

    function colsFields(b) {
        var p = b.props, out = '<div class="form-group"><label>Columns</label><div class="btn-group nlb-seg d-flex" role="group">';
        [1, 2, 3].forEach(function (n) {
            out += '<button type="button" class="btn btn-secondary nlb-colcount' + (p.cols.length === n ? ' active' : '') + '" data-n="' + n + '">' + n + '</button>';
        });
        out += '</div></div><p class="nlb-note">Click a column\'s title or text in the e-mail to edit it.</p>';
        p.cols.forEach(function (c, i) {
            out += '<details class="nlb-colbox"' + (i === 0 ? ' open' : '') + '><summary>Column ' + (i + 1) + '</summary>' +
                field({ k: 'img', t: 'check', l: 'Show an image' }, c.img, 'cols.' + i + '.img') +
                (c.img ? field({ t: 'image', l: 'Image' }, c.src, 'cols.' + i + '.src') : '') +
                field({ t: 'text', l: 'Link text (empty for none)', plain: true }, c.btn, 'cols.' + i + '.btn') +
                field({ t: 'url', l: 'Link' }, c.href, 'cols.' + i + '.href') +
                '</details>';
        });
        return out;
    }

    function inspector() {
        var $box = $('#nlb_inspector');
        var b = selectedBlock();
        if (!b) {
            $box.html('<div class="nlb-empty-insp"><iconify-icon icon="solar:cursor-square-linear"></iconify-icon><p>Click a block in the e-mail to change it.</p>' +
                '<p class="nlb-note">Drag blocks in from the left. Hover a block to move, copy or delete it.</p></div>');
            return;
        }
        var html = '<div class="nlb-insp-head"><b>' + esc(TYPES[b.type].label) + '</b>' +
            '<span><button type="button" class="btn btn-secondary btn-sm" data-insp-act="dup" title="Duplicate"><iconify-icon icon="solar:copy-linear"></iconify-icon></button> ' +
            '<button type="button" class="btn btn-secondary btn-sm nlb-del" data-insp-act="del" title="Delete"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></span></div>';
        (FIELDS[b.type] || []).forEach(function (f) {
            html += f.t === 'cols' ? colsFields(b) : field(f, f.k ? getPath(b.props, f.k) : (f.t === 'social' ? b.props.links : null));
        });
        if (b.type !== 'spacer') {
            html += '<details class="nlb-colbox"><summary>Spacing &amp; background</summary>' + COMMON.map(function (f) { return field(f, b.props[f.k]); }).join('') + '</details>';
        }
        $box.html(html);
        // Bring the inspector tab forward
        $('.nlb-insp-tabs a[href="#nlb_insp_block"]').tab('show');
    }

    function getPath(obj, path) {
        var parts = String(path).split('.');
        for (var i = 0; i < parts.length; i++) { if (obj == null) return undefined; obj = obj[parts[i]]; }
        return obj;
    }
    function setPath(obj, path, val) {
        var parts = String(path).split('.');
        for (var i = 0; i < parts.length - 1; i++) obj = obj[parts[i]];
        obj[parts[parts.length - 1]] = val;
    }

    /** Apply one inspector value to the selected block (or the e-mail style). */
    function setVal(path, val, scope, live) {
        var target = scope === 'style' ? S.design.settings : (selectedBlock() || {}).props;
        if (!target) return;
        setPath(target, path, val);
        draw();
        if (live) changedSoft(); else commit(false);
    }

    function bindInspector() {
        var $doc = $(document);
        var scopeOf = function (el) { return $(el).closest('#nlb_style').length ? 'style' : 'block'; };

        $doc.on('input change', '#nlb_inspector .nlb-in, #nlb_style .nlb-in', function (e) {
            var $el = $(this), path = $el.data('path'), v;
            if (!path) return;
            if (this.type === 'checkbox') v = this.checked;
            else if (this.type === 'range') { v = num(this.value, 0); $el.siblings('output').text(this.value); }
            else if (this.type === 'color') { v = this.value; $el.siblings('.nlb-hex').val(v); }
            else if ($el.hasClass('nlb-hex')) { v = this.value.trim(); if (v && !isColor(v)) return; if (v.length === 7) $el.siblings('input[type=color]').val(v); }
            else if ($el.data('plain')) v = esc(this.value);
            else v = this.value;
            setVal(path, v, scopeOf(this), e.type === 'input');
            if (e.type === 'change' && (this.type === 'checkbox' || $el.is('select')) && scopeOf(this) === 'block') inspector();
            if ($el.closest('.form-group').find('.nlb-imgpick').length && e.type === 'change') {
                $el.closest('.form-group').find('.nlb-imgpick').html(v ? '<img src="' + esc(v) + '" alt="">' : '<span>No image</span>');
            }
        });
        $doc.on('change', '#nlb_inspector .nlb-in, #nlb_style .nlb-in', function () { commit(false); });

        $doc.on('click', '.nlb-clear', function () { setVal($(this).data('path'), '', scopeOf(this)); scopeOf(this) === 'style' ? styleForm() : inspector(); });
        $doc.on('click', '.nlb-align', function () {
            $(this).addClass('active').siblings().removeClass('active');
            setVal($(this).data('path'), $(this).data('val'), scopeOf(this));
        });
        $doc.on('click', '.nlb-chip', function () {
            var $in = $('#' + $(this).data('for'));
            $in.val($(this).data('tag')).trigger('input').trigger('change');
        });
        $doc.on('click', '[data-insp-act]', function () { act($(this).data('insp-act'), S.selected); });

        // Images
        var uploadPath = null;
        $doc.on('click', '.nlb-upload', function () { uploadPath = $(this).data('path'); $('#nlb_file').val('').trigger('click'); });
        $('#nlb_file').on('change', function () {
            var f = this.files && this.files[0];
            if (!f || !uploadPath) return;
            if (f.size > 5 * 1024 * 1024) { alertErr(['Images must be 5 MB or smaller.']); return; }
            var path = uploadPath;
            Swal.fire({ title: 'Uploading…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            ajax({ action: 'upload', image: f }).done(function (r) {
                Swal.close();
                if (!r.success) { alertErr(r.errors); return; }
                setVal(path, r.path, 'block');
                inspector();
            }).fail(function (xhr) { Swal.close(); ajaxErr(xhr); });
        });
        $doc.on('click', '.nlb-stock-toggle', function () { var $s = $(this).closest('.form-group').find('.nlb-stock'); $s.prop('hidden', !$s.prop('hidden')); });
        $doc.on('click', '.nlb-stock-item', function () { setVal($(this).data('path'), $(this).data('val'), 'block'); inspector(); });

        // Columns and social rows
        $doc.on('click', '.nlb-colcount', function () {
            var b = selectedBlock(), n = $(this).data('n');
            if (!b) return;
            while (b.props.cols.length < n) b.props.cols.push(defaultCols(b.props.cols.length + 1).pop());
            b.props.cols = b.props.cols.slice(0, n);
            commit(true); inspector();
        });
        $doc.on('click', '.nlb-social-add', function () {
            var b = selectedBlock(); if (!b) return;
            b.props.links.push({ net: 'website', url: '' });
            commit(true); inspector();
        });
        $doc.on('click', '.nlb-social-del', function () {
            var b = selectedBlock(); if (!b) return;
            b.props.links.splice($(this).data('i'), 1);
            commit(true); inspector();
        });

        // Text formatting on the canvas selection. mousedown keeps the selection.
        $doc.on('mousedown', '.nlb-format button', function (e) { e.preventDefault(); });
        $doc.on('click', '.nlb-format button', function () {
            var cmd = $(this).data('cmd');
            if (!cdoc.activeElement || !cdoc.activeElement.isContentEditable) {
                toast('Click into the text in the e-mail first, then select words.', 'info');
                return;
            }
            if (cmd === 'link') {
                var sel = cdoc.getSelection();
                var range = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
                var active = cdoc.activeElement;
                Swal.fire({ title: 'Add a link', input: 'text', inputValue: 'https://', inputPlaceholder: 'https://... or [URL]', showCancelButton: true, confirmButtonText: 'Add link' }).then(function (res) {
                    if (!res.isConfirmed || !res.value) return;
                    active.focus();
                    if (range) { sel.removeAllRanges(); sel.addRange(range); }
                    if (range && range.collapsed) cdoc.execCommand('insertHTML', false, '<a href="' + esc(res.value) + '">' + esc(res.value) + '</a>');
                    else cdoc.execCommand('createLink', false, res.value);
                    $(active).trigger('input');
                });
                return;
            }
            if (cmd === 'foreColor') {
                var $c = $(this).closest('.form-group').find('.nlb-forecolor');
                $c.off('change.nlb').on('change.nlb', function () {
                    cdoc.execCommand('foreColor', false, this.value);
                    $(cdoc.activeElement).trigger('input');
                });
                $c.trigger('click');
                return;
            }
            cdoc.execCommand(cmd, false, null);
            $(cdoc.activeElement).trigger('input');
        });
        $doc.on('change', '.nlb-tag-insert', function () {
            var tag = this.value;
            this.value = '';
            if (!tag) return;
            var active = lastEditable;
            if (!active) { toast('Click where the tag should go in the e-mail first.', 'info'); return; }
            active.focus();
            if (lastRange) { var sel = cdoc.getSelection(); sel.removeAllRanges(); sel.addRange(lastRange); }
            cdoc.execCommand('insertText', false, tag);
            $(active).trigger('input');
        });
    }

    // Remember where the caret was in the canvas (a <select> takes the focus away)
    var lastEditable = null, lastRange = null;
    function trackSelection() {
        cdoc.addEventListener('selectionchange', function () {
            var a = cdoc.activeElement;
            if (a && a.isContentEditable) {
                lastEditable = a;
                var sel = cdoc.getSelection();
                if (sel && sel.rangeCount) lastRange = sel.getRangeAt(0).cloneRange();
            }
        });
    }

    function styleForm() {
        var st = S.design.settings;
        $('#nlb_style').html(
            '<div class="form-group"><label for="nlbs_font">Font</label><select class="form-control custom-select nlb-in" id="nlbs_font" data-path="font">' +
            FONTS.map(function (f) { return '<option value="' + esc(f[0]) + '"' + (st.font === f[0] ? ' selected' : '') + '>' + esc(f[1]) + '</option>'; }).join('') + '</select></div>' +
            field({ k: 'bodyBg', t: 'color', l: 'Page background' }, st.bodyBg) +
            field({ k: 'contentBg', t: 'color', l: 'E-mail background' }, st.contentBg) +
            field({ k: 'headingColor', t: 'color', l: 'Headings' }, st.headingColor) +
            field({ k: 'textColor', t: 'color', l: 'Text' }, st.textColor) +
            field({ k: 'linkColor', t: 'color', l: 'Links' }, st.linkColor) +
            field({ k: 'accent', t: 'color', l: 'Buttons' }, st.accent) +
            '<p class="nlb-note">These apply to every block that has no colour of its own.</p>'
        );
    }

    // =======================================================================
    // Templates and saved designs
    // =======================================================================

    var templates = [];

    function blankSettings() {
        return { bodyBg: '#F6F7F9', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#0077B6', accent: '#FFCB01' };
    }

    function load(design, meta) {
        meta = meta || {};
        S.design = { settings: $.extend(blankSettings(), design.settings || {}), blocks: (design.blocks || []).filter(function (b) { return b && TYPES[b.type]; }) };
        S.design.blocks.forEach(function (b) { b.id = b.id || uid(); b.props = $.extend({}, clone(TYPES[b.type].d), b.props || {}); if (b.type === 'columns' && !b.props.cols) b.props.cols = defaultCols(2); });
        S.selected = null;
        S.designId = meta.id || 0;
        S.name = meta.name || '';
        S.undo = []; S.redo = [];
        lastSnap = snapshot();
        if (meta.subject !== undefined) $('#nlb_subject').val(meta.subject);
        if (meta.preheader !== undefined) $('#nlb_preheader').val(meta.preheader);
        $('#nlb_design_label').text(S.name || 'Untitled design');
        draw(); inspector(); styleForm(); histButtons(); refreshSend();
        saveDraft();
    }

    function gallery() {
        var $g = $('#nlb_gallery').empty();
        templates.forEach(function (t, i) {
            var $card = $('<div class="nlb-tpl" tabindex="0" role="button">' +
                '<div class="nlb-tpl-thumb"><iframe sandbox="" tabindex="-1" aria-hidden="true" title=""></iframe></div>' +
                '<div class="nlb-tpl-meta"><b>' + esc(t.name) + '</b><span>' + esc(t.description) + '</span></div></div>');
            $card.on('click keydown', function (e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                useTemplate(i);
            });
            $g.append($card);
            // thumbnail: render the template on its own
            var saved = S.design;
            S.design = clone(t.design);
            var html = previewHtml(t.subject, '');
            S.design = saved;
            $card.find('iframe').attr('srcdoc', html);
        });
    }

    function confirmReplace(done) {
        if (!S.dirty) { done(); return; }
        Swal.fire({ title: 'Replace the current design?', text: 'Unsaved changes to the design you are working on will be lost.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Replace it', cancelButtonText: 'Keep working' })
            .then(function (r) { if (r.isConfirmed) done(); });
    }

    function useTemplate(i) {
        confirmReplace(function () {
            var t = templates[i];
            load(clone(t.design), { id: 0, name: t.name === 'Blank' ? '' : t.name, subject: t.subject, preheader: t.preheader });
            S.dirty = false;
            goStep('design');
        });
    }

    function savedList(list) {
        var $rows = $('#nlb_saved_rows').empty();
        $('#nlb_saved_empty').toggle(!list.length);
        $('#nlb_saved_wrap').toggle(!!list.length);
        list.forEach(function (d) {
            $rows.append('<tr><td><b>' + esc(d.name) + '</b></td><td>' + esc(d.subject || '—') + '</td><td>' + esc(d.updated_at || '') + '</td><td>' + esc(d.last_sent_at || 'Never') + '</td>' +
                '<td class="text-right"><button type="button" class="btn btn-secondary btn-sm nlb-open" data-id="' + d.id + '">Open</button> ' +
                '<button type="button" class="btn btn-secondary btn-sm nlb-delete" data-id="' + d.id + '" title="Delete"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></td></tr>');
        });
    }

    function loadSaved() {
        ajax({ action: 'designs' }).done(function (r) {
            if (r.success) savedList(r.designs || []);
            else $('#nlb_saved_empty').text((r.errors || []).join(' '));
        });
    }

    function saveDesign() {
        if (!S.design) return;
        Swal.fire({
            title: S.designId ? 'Save design' : 'Save as a new design',
            input: 'text', inputValue: S.name || $('#nlb_subject').val() || '', inputPlaceholder: 'Design name',
            showCancelButton: true, confirmButtonText: 'Save',
            showDenyButton: !!S.designId, denyButtonText: 'Save as new',
            inputValidator: function (v) { return v && v.trim() ? null : 'Give the design a name.'; }
        }).then(function (res) {
            if (!res.isConfirmed && !res.isDenied) return;
            var name = (res.value || S.name || '').trim();
            if (res.isDenied) name = $('.swal2-input').val() || name;
            ajax({
                action: 'save', id: res.isDenied ? 0 : S.designId, name: name,
                subject: $('#nlb_subject').val(), preheader: $('#nlb_preheader').val(),
                design_json: JSON.stringify(S.design), html: exportHtml($('#nlb_subject').val(), $('#nlb_preheader').val())
            }).done(function (r) {
                if (!r.success) { alertErr(r.errors); return; }
                S.designId = r.id; S.name = name; S.dirty = false;
                $('#nlb_design_label').text(name);
                savedList(r.designs || []);
                saveDraft();
                toast('Design saved');
            }).fail(ajaxErr);
        });
    }

    // =======================================================================
    // Preview
    // =======================================================================

    function openPreview() {
        if (!S.design) { goStep('template'); return; }
        var subj = $('#nlb_subject').val(), pre = $('#nlb_preheader').val();
        $('#nlb_pv_subject').text(subj || '(no subject)');
        $('#nlb_pv_pre').text(pre || '');
        $('#nlb_pv_frame').attr('srcdoc', previewHtml(subj, pre));
        $('#nlb_preview_modal').modal('show');
    }

    function miniPreview() {
        if (!S.design) return;
        var subj = $('#nlb_subject').val(), pre = $('#nlb_preheader').val();
        $('#nlb_inbox_subject').text(subj || '(no subject)');
        $('#nlb_inbox_pre').text(pre || $('<div>').html(S.design.blocks.filter(function (b) { return b.type === 'text'; }).map(function (b) { return b.props.html; }).join(' ')).text().slice(0, 120));
        $('#nlb_mini').attr('srcdoc', previewHtml(subj, pre));
        fitMini();
    }

    /** Scale the small preview so the 640px-wide e-mail fills its box. */
    function fitMini() {
        var $w = $('.nlb-mini'), w = $w.width();
        if (!w) return;
        var k = Math.min(1, w / 640);
        $('#nlb_mini').css({ width: 640, height: Math.ceil($w.height() / k), transform: 'scale(' + k + ')' });
    }

    // =======================================================================
    // Recipients and sending
    // =======================================================================

    var countTimer = null, countReq = null;

    function countRecipients() {
        clearTimeout(countTimer);
        var aud = $('#nlb_audience').val();
        $('#nlb_emails_group').toggle(aud === 'emails');
        countTimer = setTimeout(function () {
            if (countReq) countReq.abort();
            $('#nlb_reach').removeClass('is-warn').html('Counting recipients&hellip;');
            countReq = ajax({ action: 'count', audience: aud, emails: $('#nlb_emails').val() }).done(function (r) {
                if (!r.success) { S.reach = 0; $('#nlb_reach').addClass('is-warn').text((r.errors || []).join(' ')); refreshSend(); return; }
                S.reach = r.total;
                var html = '<b>' + r.total + '</b> ' + (r.total === 1 ? 'person' : 'people') + ' will receive this newsletter.';
                if (r.sample && r.sample.length) html += '<div class="nlb-sample">' + r.sample.map(esc).join(', ') + (r.total > r.sample.length ? ' and ' + (r.total - r.sample.length) + ' more' : '') + '</div>';
                if (r.invalid && r.invalid.length) html += '<div class="nlb-warn-line">Not valid, will be left out: ' + r.invalid.map(esc).join(', ') + '</div>';
                $('#nlb_reach').toggleClass('is-warn', !r.total).html(html);
                refreshSend();
            }).fail(function (xhr, st) { if (st !== 'abort') { S.reach = 0; $('#nlb_reach').addClass('is-warn').text('Could not count the recipients.'); refreshSend(); } });
        }, 350);
    }

    function checks() {
        var subj = $.trim($('#nlb_subject').val());
        var html = S.design ? exportHtml(subj, '') : '';
        var aud = $('#nlb_audience').val();
        var list = [];
        list.push({ ok: !!S.design && S.design.blocks.length > 0, text: S.design && S.design.blocks.length ? 'The design has ' + S.design.blocks.length + ' blocks' : 'The design is empty', hard: true });
        list.push({ ok: !!subj, text: subj ? 'Subject line is set' : 'Add a subject line', hard: true });
        list.push({ ok: S.reach > 0, text: S.reach > 0 ? S.reach + ' recipient' + (S.reach === 1 ? '' : 's') : 'No recipients yet', hard: true });
        var hasUnsub = html.indexOf('[UNSUBSCRIBE_URL]') !== -1;
        list.push({ ok: hasUnsub || aud === 'emails', text: hasUnsub ? 'Unsubscribe link included' : 'No unsubscribe link: add a Footer block (needed for bulk e-mail)' });
        var stock = (html.match(/assets\/images\/newsletter\//g) || []).length;
        list.push({ ok: !stock, text: stock ? stock + ' placeholder picture' + (stock === 1 ? ' is' : 's are') + ' still in the design' : 'Your own pictures are used' });
        var noAlt = S.design ? S.design.blocks.filter(function (b) { return b.type === 'image' && b.props.src && !b.props.alt; }).length : 0;
        list.push({ ok: !noAlt, text: noAlt ? noAlt + ' image' + (noAlt === 1 ? ' has' : 's have') + ' no alt text' : 'Images have alt text' });
        return list;
    }

    function refreshSend() {
        if (!$('#nlb_step_send').hasClass('active')) return;
        var list = checks();
        $('#nlb_checks').html(list.map(function (c) {
            return '<li class="' + (c.ok ? 'is-ok' : (c.hard ? 'is-bad' : 'is-warn')) + '"><iconify-icon icon="' + (c.ok ? 'solar:check-circle-bold' : (c.hard ? 'solar:close-circle-bold' : 'solar:danger-triangle-bold')) + '"></iconify-icon> ' + esc(c.text) + '</li>';
        }).join(''));
        $('#nlb_send_btn').prop('disabled', list.some(function (c) { return c.hard && !c.ok; }));
        miniPreview();
    }

    function sendTest() {
        var subj = $.trim($('#nlb_subject').val());
        if (!S.design || !S.design.blocks.length) { alertErr(['The design is empty.']); return; }
        if (!subj) { alertErr(['Add a subject line first.']); return; }
        var $b = $('#nlb_test_btn').prop('disabled', true).text('Sending…');
        ajax({ action: 'test', emails: $('#nlb_test_emails').val(), subject: subj, html: exportHtml(subj, $('#nlb_preheader').val()) })
            .done(function (r) {
                if (r.success) Swal.fire({ title: 'Test sent', html: '<ul class="text-left">' + (r.lines || []).map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>', icon: 'success' });
                else alertErr((r.errors && r.errors.length ? r.errors : []).concat(r.lines || []), 'Test not sent');
            })
            .fail(ajaxErr)
            .always(function () { $b.prop('disabled', false).text('Send Test'); });
    }

    function sendCampaign() {
        var subj = $.trim($('#nlb_subject').val());
        var pre = $('#nlb_preheader').val();
        var aud = $('#nlb_audience').val();
        var audLabel = $('#nlb_audience option:selected').text();
        Swal.fire({
            title: 'Send this newsletter?',
            html: '<p><b>' + esc(subj) + '</b></p><p>to <b>' + S.reach + '</b> ' + (S.reach === 1 ? 'person' : 'people') + ' (' + esc(audLabel) + ').</p><p class="text-muted">This cannot be undone.</p>',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Send now', cancelButtonText: 'Not yet'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            var html = exportHtml(subj, pre);
            var emails = $('#nlb_emails').val();
            var tot = { total: S.reach, done: 0, sent: 0, failed: 0, skipped: 0, lines: [], batch: '' };
            var retries = 0;

            function progress() {
                var pct = tot.total ? Math.round(tot.done * 100 / tot.total) : 0;
                var h = '<div class="progress mb-2"><div class="progress-bar" style="width:' + pct + '%"></div></div><div>' + tot.done + ' of ' + tot.total + ' sent</div><div class="text-muted mt-1">Keep this page open until it finishes.</div>';
                if (Swal.isVisible() && $('#nlb_prog').length) $('#nlb_prog').html(h);
                else Swal.fire({ title: 'Sending newsletter', html: '<div id="nlb_prog">' + h + '</div>', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false });
            }
            function finish(problem) {
                var h = '<div class="text-left">';
                if (problem) h += '<p class="text-danger">' + esc(problem) + '</p>';
                h += '<div><b>Delivered:</b> ' + tot.sent + ' · <b>Failed:</b> ' + tot.failed + ' · <b>Skipped:</b> ' + tot.skipped + '</div>';
                if (tot.lines.length) h += '<details class="mt-2"><summary>Per-recipient results</summary><ul class="pl-3 mt-2 nlb-lines">' + tot.lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul></details>';
                if (tot.batch) h += '<div class="mt-2 text-muted">Batch ' + esc(tot.batch) + ': every attempt is in Settings → Message Logs.</div>';
                h += '</div>';
                Swal.fire({ title: problem ? 'Sending stopped' : (tot.sent ? 'Newsletter sent' : 'Nothing was delivered'), html: h, icon: problem ? 'warning' : (tot.sent ? 'success' : 'error') });
                loadHistory();
            }
            function step(offset) {
                ajax({ action: 'send', audience: aud, emails: emails, subject: subj, html: html, design_id: S.designId, offset: offset, batch_id: tot.batch })
                    .done(function (r) {
                        if (!r.success || !r.summary) {
                            if (tot.done) finish('Stopped early: ' + (r.errors || []).join(' '));
                            else alertErr(r.errors);
                            return;
                        }
                        retries = 0;
                        var s = r.summary;
                        tot.batch = s.batch_id; tot.total = s.total;
                        tot.done += s.recipients; tot.sent += s.sent; tot.failed += s.failed; tot.skipped += s.skipped;
                        tot.lines = tot.lines.concat(s.lines || []);
                        if (s.done) { finish(''); return; }
                        progress();
                        step(s.next_offset);
                    })
                    .fail(function (xhr, st) {
                        // The same chunk is asked for again; whoever the lost request reached is skipped by the server.
                        if (retries < 2 && tot.batch) { retries++; setTimeout(function () { step(offset); }, 3000); return; }
                        finish('The connection failed (' + st + ') after ' + tot.done + ' recipient(s). Check Message Logs for batch ' + (tot.batch || '—') + ' before sending again.');
                    });
            }
            progress();
            step(0);
        });
    }

    function loadHistory() {
        ajax({ action: 'history' }).done(function (r) {
            var list = (r && r.history) || [];
            $('#nlb_hist_empty').toggle(!list.length);
            $('#nlb_hist_wrap').toggle(!!list.length);
            $('#nlb_hist_rows').html(list.map(function (h) {
                return '<tr><td>' + esc(h.started_at) + '</td><td>' + esc(h.subject) + '</td><td>' + esc(h.audience) + '</td><td>' + esc(h.sent_by) + '</td>' +
                    '<td class="text-right">' + (+h.sent || 0) + '</td><td class="text-right">' + (+h.failed || 0) + '</td><td><code>' + esc(h.batch_id) + '</code></td></tr>';
            }).join(''));
        });
    }

    // =======================================================================
    // Steps, palette, start-up
    // =======================================================================

    function goStep(step) {
        $('.nlb-steps a[data-step="' + step + '"]').tab('show');
    }

    function palette() {
        var $t = $('#nlb_tiles');
        PALETTE.forEach(function (e) {
            var type = e[0], t = TYPES[type];
            var $tile = $('<button type="button" class="nlb-tile" draggable="true"><iconify-icon icon="' + (e[3] || t.icon) + '"></iconify-icon><span>' + esc(e[2] || t.label) + '</span></button>');
            $tile.on('dragstart', function (ev) {
                S.drag = { kind: 'new', type: type, n: e[1] || 0 };
                ev.originalEvent.dataTransfer.effectAllowed = 'copy';
                ev.originalEvent.dataTransfer.setData('text/plain', 'nlb-new:' + type);
                $(this).addClass('is-dragging');
            });
            $tile.on('dragend', function () { S.drag = null; $(this).removeClass('is-dragging'); $(cdoc).find('.nlb-drop').remove(); });
            $tile.on('click', function () {
                if (!S.design) return;
                addBlock(type, e[1] || 0);
            });
            $t.append($tile);
        });
    }

    $(function () {
        if (!document.getElementById('nlb_canvas')) return;

        templates = (window.NLB_TEMPLATES ? window.NLB_TEMPLATES(CFG, mk) : []);
        initCanvas();
        trackSelection();
        palette();
        bindInspector();

        // Start: the autosaved draft, or the first template
        var draft = null;
        try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (x) { draft = null; }
        if (draft && draft.design && draft.design.blocks) {
            load(draft.design, { id: draft.id, name: draft.name, subject: draft.subject || '', preheader: draft.preheader || '' });
            S.dirty = true;
        } else {
            var t0 = templates[0];
            load(clone(t0.design), { id: 0, name: t0.name, subject: t0.subject, preheader: t0.preheader });
            S.dirty = false;
        }
        if ((CFG.preset || []).length) {
            $('#nlb_emails').val(CFG.preset.join(', '));
            $('#nlb_audience').val('emails');
        }
        gallery();
        loadSaved();

        // A draft from an earlier visit opens on the designer; say so.
        if (draft && draft.design && draft.design.blocks) {
            goStep('design');
            $('#resultados_ajax').html('<div class="alert alert-info nlb-draft-note">Your last design was restored' + (draft.at ? ' (from ' + esc(new Date(draft.at).toLocaleString()) + ')' : '') + '. <a href="#" id="nlb_fresh">Start from a template instead</a></div>');
        }
        $(document).on('click', '#nlb_fresh', function (e) { e.preventDefault(); $('#resultados_ajax').empty(); goStep('template'); });

        // Steps
        $('.nlb-steps a').on('shown.bs.tab', function () {
            var step = $(this).data('step');
            $('body').toggleClass('nlb-designing', step === 'design');
            if (step === 'design') { draw(); }
            if (step === 'send') { countRecipients(); loadHistory(); refreshSend(); }
        });
        $('#nlb_next_btn').on('click', function () {
            var active = $('.nlb-steps a.active').data('step');
            goStep(active === 'template' ? 'design' : 'send');
        });
        $('#nlb_pv_send').on('click', function () { $('#nlb_preview_modal').modal('hide'); goStep('send'); });

        // Toolbar
        $('#nlb_undo').on('click', undo);
        $('#nlb_redo').on('click', redo);
        $('#nlb_save_btn').on('click', saveDesign);
        $('#nlb_preview_btn, #nlb_preview_btn2').on('click', openPreview);
        $('[data-nlb-device]').on('click', function () {
            S.device = $(this).data('nlb-device');
            $(this).addClass('active').siblings().removeClass('active');
            $('#nlb_canvas_wrap').toggleClass('is-mobile', S.device === 'mobile');
            setTimeout(fitCanvas, 50);
        });
        $('[data-nlb-preview]').on('click', function () {
            $(this).addClass('active').siblings().removeClass('active');
            $('#nlb_pv_frame_wrap').toggleClass('is-mobile', $(this).data('nlb-preview') === 'mobile');
        });
        $(document).on('keydown', shortcuts);
        $(window).on('resize', function () { fitCanvas(); fitMini(); });

        // Send step
        $('#nlb_audience').on('change', countRecipients);
        $('#nlb_emails').on('input', countRecipients);
        $('#nlb_subject, #nlb_preheader').on('input', function () { saveDraft(); refreshSend(); });
        $('#nlb_test_btn').on('click', sendTest);
        $('#nlb_send_btn').on('click', sendCampaign);
        $('#nlb_emails_group').toggle($('#nlb_audience').val() === 'emails');

        // Expose for debugging and tests
        window.NLB = { state: S, exportHtml: exportHtml, renderBlock: renderBlock, mk: mk, load: load, addBlock: addBlock, templates: function () { return templates; } };
    });
})(jQuery);
