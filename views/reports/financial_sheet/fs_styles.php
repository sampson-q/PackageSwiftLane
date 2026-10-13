<style type="text/css">
    /* ============ Financial Sheet — 4-level hierarchy ============
       Shared by financial_sheet.php (list) and financial_sheet_consolidation.php.
       Depth is expressed three ways at once so levels can't be confused:
       colour (purple spine > teal cards > slate rows), size (headers
       shrink as you go deeper) and indentation (each level steps right). */

    .fs-toolbar .input-group-text {
        background: var(--page-050);
        color: var(--ink-700);
    }

    .fs-hint {
        font-size: 12px;
        color: var(--slate-400);
    }

    /* Flex headers keep every accordion row tidy and aligned. */
    .fs-consol-header,
    .fs-cust-header,
    .fs-pkg-header {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        cursor: pointer;
    }

    .fs-spacer {
        flex: 1 1 auto;
    }

    .fs-dim {
        opacity: .8;
        font-size: 12.5px;
        white-space: nowrap;
    }

    .fs-caret {
        transition: transform .2s;
    }

    /* Level chip (packages only; customers use the avatar) */
    .fs-level-chip {
        display: inline-block;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .6px;
        padding: 1px 6px;
        border-radius: 10px;
        vertical-align: middle;
    }

    .fs-chip-pkg {
        background: var(--fill-100);
        color: var(--ink-700);
        border: 1px solid var(--line-200);
    }

    /* ---- Level 1 — consolidation: native card, primary-purple accent ----
       !important + child selector: the template's .card .card-header rules
       otherwise override these and wash the text out. */
    /* overflow must stay visible: the customer tier's Actions dropdown lives
       inside this card and gets clipped at the card boundary otherwise. */
    .fs-consol-card {
        border: 1px solid var(--fill-100);
        border-left: 5px solid var(--sky-500);
        border-radius: 6px;
        overflow: visible;
        box-shadow: 0 1px 3px color-mix(in srgb, var(--ink-700) 8%, transparent);
    }

    .fs-consol-card>.fs-consol-header {
        background: var(--white) !important;
        color: var(--ink-800) !important;
        font-size: 14.5px;
        padding: 11px 14px;
    }

    /* Untouched (collapsed) consolidation: the number reads like the rest
       of the meta line (same colour as the date and the total weight). */
    .fs-consol-card>.fs-consol-header b {
        color: var(--ink-700) !important;
        font-size: 14.5px;
        font-weight: 600;
        letter-spacing: .4px;
    }

    .fs-consol-card>.fs-consol-header .fs-dim {
        color: var(--ink-700) !important;
        opacity: 1;
    }

    .fs-consol-card>.fs-consol-header .fs-caret,
    .fs-consol-card>.fs-consol-header>i {
        color: var(--ink-700);
    }

    .fs-consol-card>.fs-consol-header .fs-money {
        background: var(--fill-100);
        color: var(--green-500) !important;
        padding: 2px 10px;
        border-radius: 12px;
    }

    /* Active/opened consolidation: the left accent bar turns charcoal
       (var(--ink-800)) over a light header, with the number darkened for emphasis. */
    .fs-consol-card.fs-active {
        border-color: var(--ink-800);
        border-left-color: var(--ink-800);
        box-shadow: 0 2px 8px color-mix(in srgb, var(--ink-800) 25%, transparent);
    }

    .fs-consol-card.fs-active>.fs-consol-header {
        background: var(--page-050) !important;
    }

    .fs-consol-card.fs-active>.fs-consol-header b {
        color: var(--ink-800) !important;
        font-size: 16px;
    }

    .fs-consol-card.fs-active>.fs-consol-header .fs-caret,
    .fs-consol-card.fs-active>.fs-consol-header>i {
        color: var(--ink-800);
    }

    /* The consolidation body is a tinted canvas its customers sit on. */
    .fs-consol-body {
        background: var(--page-050);
        border-top: 1px solid var(--line-200);
    }

    /* Paid / discount chips (stage-2 billing) */
    .fs-chip-paid {
        background: var(--fill-100);
        color: var(--sky-500);
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .fs-chip-discount {
        background: var(--fill-100);
        color: var(--amber-500);
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .fs-chip-balance {
        background: var(--page-050);
        color: var(--red-500);
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .fs-chip-settled {
        background: var(--fill-100);
        color: var(--green-500);
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .fs-chip-owed {
        background: var(--red-500);
        color: var(--white);
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    /* Exchange-rate anchor shown on each billing-log entry (audit). */
    .fs-rate-chip {
        display: inline-block;
        background: var(--page-050);
        color: var(--sky-500);
        font-size: 11px;
        font-weight: 700;
        padding: 0 7px;
        border-radius: 9px;
        margin: 0 2px;
        white-space: nowrap;
    }

    /* ---- Level 2 — customer: white card, teal spine, avatar ----
       overflow must stay visible or the Actions dropdown gets clipped
       inside the collapsed card. */
    .fs-cust-card {
        border: 1px solid var(--line-200);
        border-left: 5px solid var(--green-500);
        border-radius: 6px;
        margin-left: 16px;
        background: var(--white);
        overflow: visible;
        box-shadow: 0 1px 2px color-mix(in srgb, var(--green-500) 6%, transparent);
    }

    .fs-cust-header {
        background: var(--white);
        color: var(--ink-900);
        font-size: 13.5px;
    }

    .fs-cust-header b {
        font-size: 14px;
    }

    .fs-avatar {
        position: relative;
        width: 30px;
        height: 30px;
        min-width: 30px;
        border-radius: 50%;
        background: var(--green-500);
        color: var(--white);
        font-weight: 700;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Billed marker: small money badge at the avatar's 5 o'clock. */
    .fs-avatar-billed {
        position: absolute;
        right: -4px;
        bottom: -4px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--green-500);
        color: var(--white);
        font-size: 10px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--white);
    }

    .fs-cust-card.fs-active {
        border-color: var(--green-500);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--green-500) 30%, transparent);
    }

    .fs-cust-card.fs-active>.fs-cust-header {
        background: var(--fill-100);
    }

    /* Customer body: its own tint, one shade lighter than the teal spine. */
    .fs-cust-body {
        background: var(--page-050);
        border-top: 1px dashed var(--slate-400);
    }

    /* ---- Level 3 — package: compact slate row, deeper indent ---- */
    .fs-pkg-card {
        border: 1px solid var(--line-200);
        border-left: 4px solid var(--slate-400);
        border-radius: 5px;
        margin-left: 26px;
        background: var(--white);
        /* visible so the Package Actions dropdown isn't clipped by the card */
        overflow: visible;
    }

    .fs-pkg-header {
        background: var(--white);
        color: var(--ink-800);
        font-size: 12.5px;
        padding: 6px 10px !important;
    }

    .fs-pkg-header b {
        font-family: SFMono-Regular, Consolas, "Liberation Mono", monospace;
        font-size: 13px;
    }

    .fs-pkg-card.fs-active {
        background: var(--page-050);
        box-shadow: inset 0 0 0 2px var(--amber-500);
    }

    .fs-pkg-card.fs-active>.fs-pkg-header {
        background: var(--amber-surface);
    }

    /* ---- Level 4 — items ---- */
    .fs-items-table {
        background: var(--white);
    }

    .fs-items-table td {
        vertical-align: middle;
    }

    /* Pricing control: mode toggle + currency toggle + ONE value input */
    .fs-price-ctl {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .fs-price-ctl .fs-valgrp {
        width: 130px;
    }

    .fs-price-ctl .fs-equiv {
        flex-basis: 100%;
        font-size: 11px;
        line-height: 1.2;
    }

    .fs-cur-btn {
        font-weight: 700;
    }

    /* Money */
    .fs-money {
        font-weight: 700;
        color: var(--green-500);
        white-space: nowrap;
    }

    /* Customer-level billing log (mirrors the package change log).
       Double class outranks the base .fs-history rules defined below. */
    .fs-history.fs-billing-log {
        margin: 8px 4px 4px 26px;
        padding: 6px 10px;
        background: var(--page-050);
        border: 1px solid var(--line-200);
        border-radius: 5px;
    }

    .fs-history.fs-billing-log .fs-history-title {
        color: var(--green-500);
    }

    /* Grouped-items card */
    .fs-group-card {
        border: 1px dashed var(--green-500);
        border-radius: 5px;
        background: var(--page-050);
        padding: 8px 10px;
    }

    .fs-group-head {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 4px;
    }

    .fs-group-members {
        margin: 0 0 4px 0;
        padding-left: 22px;
        font-size: 12.5px;
        color: var(--ink-700);
    }

    .fs-group-ctl {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .fs-group-bar {
        margin-bottom: 8px;
    }

    /* Dangerous-goods marker */
    .fs-dg-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        color: var(--white);
        font-size: 11px;
        vertical-align: middle;
    }

    /* Package change log */
    .fs-history {
        margin-top: 10px;
        padding-top: 6px;
        border-top: 1px solid var(--fill-100);
        font-size: 12px;
    }

    .fs-history-title {
        font-weight: 700;
        color: var(--ink-700);
        margin-bottom: 3px;
    }

    .fs-hist-item {
        padding: 2px 0;
    }
</style>
