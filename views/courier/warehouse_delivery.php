<?php
// ============================================================================
// Warehouse Delivery — tiered (FS-style) delivery workspace.
// Consolidation -> customer -> package -> item, with DELIVERY actions. Same
// accordion structure as the Financial Sheet but a deliberately different
// (green "delivery") skin so the two screens are never confused. All the
// tiers/actions are rendered by ajax/courier/warehouse_delivery_ajax.php, which
// is the authoritative permission gate.
// ============================================================================
$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Warehouse Delivery | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        /* ==== Warehouse Delivery — green "delivery" theme (distinct from the
               blue Financial Sheet, same tier structure). ==================== */
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

        /* Level 1 — consolidation (green spine) */
        .wd-consol-card { border:1px solid #d7e9e0; border-left:4px solid var(--wd-green); border-radius:6px; overflow:visible; }
        .wd-consol-header { display:flex; align-items:center; flex-wrap:wrap; gap:2px 4px; cursor:pointer; background:var(--wd-green-l); }
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
        .wd-caret { transition:transform .15s ease; color:#6b8078; }
        .wd-open > .wd-caret, .card-header.wd-open .wd-caret { transform:rotate(180deg); }

        .wd-consol-body, .wd-cust-body, .wd-pkg-body { border-top:1px solid #eef3f0; }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="wd-banner">
                    <i class="mdi mdi-truck-delivery wd-banner-ico"></i>
                    <div>
                        <h4>Warehouse Delivery</h4>
                        <small>Deliver packages Accounts has cleared &middot; grouped by consolidation &rarr; customer &rarr; package &rarr; item</small>
                    </div>
                </div>

                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <input type="text" id="search" class="form-control form-control-sm wd-search"
                               placeholder="Search consolidation, customer, locker or tracking #…">
                    </div>

                    <div id="loader" style="display:none;" class="text-center my-3">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color:#1b8a5a;"></i>
                    </div>

                    <div class="outer_div"></div>
                </div></div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/warehouse_delivery.js') ?>"></script>
</body>

</html>
