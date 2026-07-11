<style>
    /* ==== Warehouse Delivery — green "delivery" theme (distinct from the blue
           Financial Sheet, same tier structure). Shared by the list page and
           the single-consolidation page. ==================================== */
    :root { --wd-green:#1b8a5a; --wd-green-d:#136343; --wd-green-l:#e8f6ef; --wd-amber:#b26a00; }

    .wd-banner {
        display:flex; align-items:center; gap:12px;
        background:linear-gradient(90deg,var(--wd-green),var(--wd-green-d));
        color:#fff; border-radius:8px; padding:14px 18px; margin-bottom:14px;
    }
    .wd-banner .wd-banner-ico { font-size:30px; line-height:1; }
    .wd-banner h4 { color:#fff; margin:0; font-weight:700; letter-spacing:.3px; }
    .wd-banner small { color:rgba(255,255,255,.85); }

    .wd-search { max-width:420px; }

    /* Level 1 — consolidation (green spine). On the list page it links out; on
       the single-consolidation page it's a static header. */
    .wd-consol-card { border:1px solid #d7e9e0; border-left:4px solid var(--wd-green); border-radius:6px; overflow:visible; }
    .wd-consol-header { display:flex; align-items:center; flex-wrap:wrap; gap:2px 4px; background:var(--wd-green-l); }
    .wd-consol-header.wd-link { cursor:pointer; }
    .wd-consol-ico { color:var(--wd-green); font-size:18px; }

    /* Level 2 — customer (white card, teal avatar) */
    .wd-cust-card { border:1px solid #e6efe9; border-radius:5px; margin-left:22px; background:#fff; overflow:visible; }
    .wd-cust-header { display:flex; align-items:center; flex-wrap:wrap; gap:2px 4px; cursor:pointer; }
    .wd-avatar {
        display:inline-flex; align-items:center; justify-content:center;
        width:26px; height:26px; border-radius:50%; background:var(--wd-green);
        color:#fff; font-size:12px; font-weight:700; margin-right:6px;
    }

    /* Level 3 — package */
    .wd-pkg-card { border:1px solid #eee; border-radius:5px; margin-left:26px; background:#fff; overflow:visible; }
    .wd-pkg-header { display:flex; align-items:center; flex-wrap:wrap; gap:2px 4px; cursor:pointer; }
    .wd-pkg-card.wd-state-delivered { border-left:3px solid var(--wd-green); }
    .wd-pkg-card.wd-state-ready     { border-left:3px solid #2e7d32; }
    .wd-pkg-card.wd-state-awaiting  { border-left:3px solid #bdbdbd; }

    /* Item table */
    .wd-item-table th { font-size:11px; text-transform:uppercase; color:#8a8a8a; border-top:0; }
    .wd-item-table td { vertical-align:middle; }

    /* Tier chips */
    .wd-level-chip { font-size:9px; font-weight:800; letter-spacing:.5px; padding:1px 6px; border-radius:8px; color:#fff; }
    .wd-chip-pkg  { background:#3a7563; }
    .wd-chip-item { background:#9aa7a1; }

    /* State chips */
    .wd-chip-done, .wd-chip-partial, .wd-chip-ready, .wd-chip-wait {
        font-size:12px; font-weight:700; padding:2px 10px; border-radius:12px; white-space:nowrap;
    }
    .wd-chip-done    { background:var(--wd-green); color:#fff; }
    .wd-chip-partial { background:#fff3e0; color:var(--wd-amber); }
    .wd-chip-ready   { background:#e8f5e9; color:#2e7d32; }
    .wd-chip-wait    { background:#f0f0f0; color:#777; }

    .wd-btn-deliver { background:var(--wd-green); border-color:var(--wd-green); color:#fff; }
    .wd-btn-deliver:hover { background:var(--wd-green-d); border-color:var(--wd-green-d); color:#fff; }

    .wd-mono { font-family:SFMono-Regular,Consolas,monospace; }
    .wd-dim { color:#7a8a83; font-size:12px; }
    .wd-spacer { flex:1 1 auto; }
    .wd-caret { color:#6b8078; }
    .wd-caret-toggle { transition:transform .15s ease; }
    .wd-open > .wd-caret-toggle, .card-header.wd-open .wd-caret-toggle { transform:rotate(180deg); }

    .wd-cust-body, .wd-pkg-body { border-top:1px solid #eef3f0; }
</style>
