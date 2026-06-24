"use strict";
/* ===========================================================================
   Persistent multi-select for AJAX-reloaded list tables.

   Checkbox selections survive search / filter / pagination / per-page reloads:
   the chosen rows stay chosen even though the table HTML is replaced on every
   cdp_load(). Selections are keyed by each checkbox's VALUE (the row id the
   bulk actions already use), because the row element ids (cst_0, cst_1 …) are
   recycled on every render and are NOT stable.

   Shared markup contract (courier / consolidate / customer-package / pickup …):
     - table.custom-table-checkbox
     - per-row checkbox in the FIRST <td>, value = the row identifier
     - a select-all checkbox with class .sl-all in the header
     - #div-actions-checked (bulk-actions bar) and #countChecked (the badge)

   The set lives for the lifetime of the page. Each list is its own full page,
   so there is no cross-list contamination.

   Public API (used by the per-list scripts):
     cdpSelGet()     -> Array of selected values  (use this for bulk actions)
     cdpSelCount()   -> number selected
     cdpSelClear()   -> clear the whole selection + UI
     cdpSelRestore() -> re-apply selection to the currently rendered rows
   =========================================================================== */
(function () {
    // Loaded globally from the footer (so it covers every list) and may also be
    // included explicitly by a page — guard so the delegated handlers/observer
    // are only wired once.
    if (window.__cdpSelLoaded) return;
    window.__cdpSelLoaded = true;

    var SEL    = (window.cdpSel = window.cdpSel || new Set());
    var ROW_CB = ".custom-table-checkbox tr > td:first-child input[type=checkbox]";
    var raf    = null;

    function $rows() { return $(ROW_CB); }
    function style($cb) { $cb.closest("tr").css("background", $cb.is(":checked") ? "#fff8e1" : ""); }

    function updateCount() {
        var n = SEL.size;
        $("#div-actions-checked")[n > 0 ? "removeClass" : "addClass"]("hide");
        $("#countChecked")[n > 0 ? "removeClass" : "addClass"]("hide").html(n);
    }

    // Reflect "are all currently-visible rows selected?" on the select-all box.
    function syncAll() {
        var $r = $rows();
        $(".sl-all").prop("checked", $r.length > 0 && $r.filter(":not(:checked)").length === 0);
    }

    // Re-apply the persisted selection to the rows that are on screen right now.
    function restore() {
        $rows().each(function () {
            var $cb = $(this);
            $cb.prop("checked", SEL.has($cb.val()));
            style($cb);
        });
        syncAll();
        updateCount();
    }

    window.cdpSelRestore = restore;
    window.cdpSelGet     = function () { return Array.from(SEL); };
    window.cdpSelCount   = function () { return SEL.size; };
    window.cdpSelClear   = function () {
        SEL.clear();
        $rows().prop("checked", false).each(function () { style($(this)); });
        $(".sl-all").prop("checked", false);
        updateCount();
    };

    // A row checkbox toggled (delegated, so it keeps working after re-render).
    $(document).on("change", ROW_CB, function () {
        var $cb = $(this);
        if ($cb.is(":checked")) SEL.add($cb.val()); else SEL.delete($cb.val());
        style($cb);
        syncAll();
        updateCount();
    });

    // Select-all toggled — adds/removes only the currently-visible rows.
    $(document).on("change", ".sl-all", function () {
        var on = $(this).is(":checked");
        $rows().each(function () {
            var $cb = $(this).prop("checked", on);
            if (on) SEL.add($cb.val()); else SEL.delete($cb.val());
            style($cb);
        });
        updateCount();
    });

    // Re-apply the selection whenever a list container is re-rendered by AJAX.
    function schedule() {
        if (raf) return;
        raf = window.requestAnimationFrame(function () { raf = null; restore(); });
    }

    $(function () {
        var nodes = document.querySelectorAll('[class*="outer_div"], #resultados_ajax');
        if (window.MutationObserver && nodes.length) {
            var obs = new MutationObserver(schedule);
            Array.prototype.forEach.call(nodes, function (n) {
                obs.observe(n, { childList: true, subtree: true });
            });
        }
        restore();
    });
})();
