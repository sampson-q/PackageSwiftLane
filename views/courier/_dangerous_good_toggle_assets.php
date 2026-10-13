<?php
// Shared presentation + behaviour for the "Dangerous Goods (Hazmat)" toggle used
// on the courier add and edit forms. Kept in one partial so both stay in sync.
?>
<style>
    .dg-toggle-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        border: 1px dashed var(--amber-500);
        border-radius: 8px;
        padding: 12px 16px;
        background: var(--page-050);
        transition: background .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .dg-toggle-bar.is-dangerous {
        background: var(--page-050);
        border-style: solid;
        border-color: var(--amber-500);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--amber-500) 12%, transparent);
    }
    .dg-toggle-bar .dg-icon { color: var(--amber-500); margin-right: 6px; }
    .dg-toggle-bar .dg-title { font-weight: 600; color: var(--amber-500); }
    .dg-toggle-bar .dg-state { font-weight: 600; color: var(--slate-400); }
    .dg-toggle-bar.is-dangerous .dg-state { color: var(--amber-500); }
    .dg-switch { cursor: pointer; white-space: nowrap; }
</style>
<script>
    // Highlight the bar + update the state label when the dangerous-goods flag flips.
    function cdp_toggleDangerousBar(input) {
        var bar = document.getElementById('dg_toggle_bar');
        var state = bar ? bar.querySelector('.dg-state') : null;
        if (!bar) return;
        if (input.checked) {
            bar.classList.add('is-dangerous');
            if (state) state.textContent = 'Dangerous goods';
        } else {
            bar.classList.remove('is-dangerous');
            if (state) state.textContent = 'Not dangerous';
        }
    }
    // Reflect the initial state (edit form may load it pre-checked).
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('is_dangerous_good');
        if (input) cdp_toggleDangerousBar(input);
    });
</script>
