<style type="text/css">
    /* ============ Financial Sheet — 4-level hierarchy ============
       Shared by financial_sheet.php (list) and financial_sheet_consolidation.php.
       Depth is expressed three ways at once so levels can't be confused:
       colour (purple spine > teal cards > slate rows), size (headers
       shrink as you go deeper) and indentation (each level steps right). */

    .fs-toolbar .input-group-text {
        background: #f4f6fb;
        color: #5a6b7b;
    }

    .fs-hint {
        font-size: 12px;
        color: #8a97a5;
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
        background: #eceff3;
        color: #5b6b7c;
        border: 1px solid #d4dce5;
    }

    /* ---- Level 1 — consolidation: native card, primary-purple accent ----
       !important + child selector: the template's .card .card-header rules
       otherwise override these and wash the text out. */
    /* overflow must stay visible: the customer tier's Actions dropdown lives
       inside this card and gets clipped at the card boundary otherwise. */
    .fs-consol-card {
        border: 1px solid #e3e7ef;
        border-left: 5px solid #7460ee;
        border-radius: 6px;
        overflow: visible;
        box-shadow: 0 1px 3px rgba(60, 70, 100, .08);
    }

    .fs-consol-card>.fs-consol-header {
        background: #fff !important;
        color: #2c3652 !important;
        font-size: 14.5px;
        padding: 11px 14px;
    }

    /* Untouched (collapsed) consolidation: the number reads like the rest
       of the meta line (same colour as the date and the total weight). */
    .fs-consol-card>.fs-consol-header b {
        color: #6c7a89 !important;
        font-size: 14.5px;
        font-weight: 600;
        letter-spacing: .4px;
    }

    .fs-consol-card>.fs-consol-header .fs-dim {
        color: #6c7a89 !important;
        opacity: 1;
    }

    .fs-consol-card>.fs-consol-header .fs-caret,
    .fs-consol-card>.fs-consol-header>i {
        color: #6c7a89;
    }

    .fs-consol-card>.fs-consol-header .fs-money {
        background: #e5f6ec;
        color: #1b8a4b !important;
        padding: 2px 10px;
        border-radius: 12px;
    }

    /* Active/opened consolidation: the left accent bar turns charcoal
       (#343a40) over a light header, with the number darkened for emphasis. */
    .fs-consol-card.fs-active {
        border-color: #343a40;
        border-left-color: #343a40;
        box-shadow: 0 2px 8px rgba(52, 58, 64, .25);
    }

    .fs-consol-card.fs-active>.fs-consol-header {
        background: #f1f3f5 !important;
    }

    .fs-consol-card.fs-active>.fs-consol-header b {
        color: #343a40 !important;
        font-size: 16px;
    }

    .fs-consol-card.fs-active>.fs-consol-header .fs-caret,
    .fs-consol-card.fs-active>.fs-consol-header>i {
        color: #343a40;
    }

    /* The consolidation body is a tinted canvas its customers sit on. */
    .fs-consol-body {
        background: #f4f5f7;
        border-top: 1px solid #e0e3e7;
    }

    /* Paid / discount chips (stage-2 billing) */
    .fs-chip-paid {
        background: #e3f2fd;
        color: #1565c0;
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    .fs-chip-discount {
        background: #fff3e0;
        color: #b26a00;
        font-weight: 700;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }

    /* ---- Level 2 — customer: white card, teal spine, avatar ----
       overflow must stay visible or the Actions dropdown gets clipped
       inside the collapsed card. */
    .fs-cust-card {
        border: 1px solid #cfe0dd;
        border-left: 5px solid #00897b;
        border-radius: 6px;
        margin-left: 16px;
        background: #fff;
        overflow: visible;
        box-shadow: 0 1px 2px rgba(0, 60, 50, .06);
    }

    .fs-cust-header {
        background: #fff;
        color: #1d3833;
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
        background: #00897b;
        color: #fff;
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
        background: #28a745;
        color: #fff;
        font-size: 10px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
    }

    .fs-cust-card.fs-active {
        border-color: #00897b;
        box-shadow: 0 0 0 2px rgba(0, 137, 123, .3);
    }

    .fs-cust-card.fs-active>.fs-cust-header {
        background: #e4f3f1;
    }

    /* Customer body: its own tint, one shade lighter than the teal spine. */
    .fs-cust-body {
        background: #f0f7f6;
        border-top: 1px dashed #bcd8d4;
    }

    /* ---- Level 3 — package: compact slate row, deeper indent ---- */
    .fs-pkg-card {
        border: 1px solid #dde3ea;
        border-left: 4px solid #78909c;
        border-radius: 5px;
        margin-left: 26px;
        background: #fff;
        overflow: hidden;
    }

    .fs-pkg-header {
        background: #fbfcfe;
        color: #26333f;
        font-size: 12.5px;
        padding: 6px 10px !important;
    }

    .fs-pkg-header b {
        font-family: SFMono-Regular, Consolas, "Liberation Mono", monospace;
        font-size: 13px;
    }

    .fs-pkg-card.fs-active {
        background: #fff7e6;
        box-shadow: inset 0 0 0 2px #e0a800;
    }

    .fs-pkg-card.fs-active>.fs-pkg-header {
        background: #ffeec6;
    }

    /* ---- Level 4 — items ---- */
    .fs-items-table {
        background: #fff;
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
        color: #1b8a4b;
        white-space: nowrap;
    }

    /* Customer-level billing log (mirrors the package change log).
       Double class outranks the base .fs-history rules defined below. */
    .fs-history.fs-billing-log {
        margin: 8px 4px 4px 26px;
        padding: 6px 10px;
        background: #eef7f0;
        border: 1px solid #cbe5d2;
        border-radius: 5px;
    }

    .fs-history.fs-billing-log .fs-history-title {
        color: #2e7d32;
    }

    /* Grouped-items card */
    .fs-group-card {
        border: 1px dashed #17a2b8;
        border-radius: 5px;
        background: #f2fbfd;
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
        color: #44535f;
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
        color: #fff;
        font-size: 11px;
        vertical-align: middle;
    }

    /* Package change log */
    .fs-history {
        margin-top: 10px;
        padding-top: 6px;
        border-top: 1px solid #e3e6ea;
        font-size: 12px;
    }

    .fs-history-title {
        font-weight: 700;
        color: #5a6b7b;
        margin-bottom: 3px;
    }

    .fs-hist-item {
        padding: 2px 0;
    }
</style>
