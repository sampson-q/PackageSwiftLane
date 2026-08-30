<?php
// ============================================================================
// My Bills — the customer's own outstanding bills, and paying them by mobile
// money. Data + checkout: ajax/customer/my_bills_ajax.php.
//
// The page is a shell only: the KPI strip, the filter toolbar and every bill
// row are rendered by dataJs/my_bills.js from that endpoint's JSON. No figure
// on this page is computed in the browser from anything the browser supplied.
// ============================================================================
$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>My Bills | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        /* ── Summary strip ───────────────────────────────────────────────── */
        .mb-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .mb-kpi {
            background: #fff;
            border: 1px solid #e9edf3;
            border-radius: 10px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
        }
        .mb-kpi::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--mb-accent, #d7dce5);
        }
        .mb-kpi--owed   { --mb-accent: #f62d51; }
        .mb-kpi--billed { --mb-accent: #336aea; }
        .mb-kpi--paid   { --mb-accent: #0aa699; }
        .mb-kpi--count  { --mb-accent: #9b6ef3; }
        .mb-kpi__k {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
            color: #8a94a6;
        }
        .mb-kpi__v {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -.02em;
            color: #1f2a37;
            margin-top: 6px;
            line-height: 1.15;
        }
        .mb-kpi__s { font-size: .76rem; color: #8a94a6; margin-top: 2px; }

        /* ── Toolbar ─────────────────────────────────────────────────────── */
        .mb-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .mb-seg { display: inline-flex; border: 1px solid #e0e5ee; border-radius: 8px; overflow: hidden; }
        .mb-seg button {
            border: 0;
            background: #fff;
            padding: 8px 16px;
            font-size: .82rem;
            font-weight: 600;
            color: #6b7788;
            cursor: pointer;
        }
        .mb-seg button + button { border-left: 1px solid #e0e5ee; }
        .mb-seg button.is-on { background: #1f2a37; color: #fff; }

        /* ── Bill rows ───────────────────────────────────────────────────── */
        .mb-bill {
            background: #fff;
            border: 1px solid #e9edf3;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: box-shadow .15s ease;
        }
        .mb-bill.is-open { box-shadow: 0 6px 22px -12px rgba(31, 42, 55, .35); }
        .mb-bill__head {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 18px;
            align-items: center;
            padding: 16px 20px;
            cursor: pointer;
        }
        .mb-bill__head:hover { background: #fafbfd; }
        .mb-bill__no { font-weight: 700; font-size: 1rem; color: #1f2a37; }
        .mb-bill__meta { font-size: .78rem; color: #8a94a6; margin-top: 3px; }
        .mb-bill__figs { display: flex; gap: 26px; text-align: right; }
        .mb-fig__k {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #99a2b1;
            font-weight: 700;
        }
        .mb-fig__v { font-weight: 700; font-size: .95rem; color: #3b4655; }
        .mb-fig--balance .mb-fig__v { font-size: 1.2rem; }
        .mb-owed { color: #f62d51; }
        .mb-settled { color: #0aa699; }
        .mb-caret { color: #b3bbc7; transition: transform .18s ease; font-size: 1.3rem; }
        .mb-bill.is-open .mb-caret { transform: rotate(180deg); }

        .mb-chip {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 1rem;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .mb-chip-paid { background: #e8f8f5; color: #0aa699; }
        .mb-chip-due { background: #fdeaec; color: #f62d51; }

        /* ── Expanded body ───────────────────────────────────────────────── */
        .mb-bill__body { border-top: 1px solid #eef1f6; background: #fbfcfe; }
        .mb-pkg-table { width: 100%; font-size: .87rem; margin-bottom: 0; }
        .mb-pkg-table th {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #99a2b1;
            font-weight: 700;
            border-top: 0;
            padding: 10px 20px;
        }
        .mb-pkg-table td { padding: 11px 20px; vertical-align: middle; border-top: 1px solid #eef1f6; }
        .mb-pkg-table tr.is-cleared td { color: #a2abb8; }
        .mb-paybar {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-top: 1px solid #eef1f6;
            background: #fff;
        }
        .mb-paybar__amt { font-size: 1.5rem; font-weight: 700; color: #1f2a37; letter-spacing: -.02em; }
        .mb-momo-note { font-size: .78rem; color: #8a94a6; }

        /* ── Empty state ─────────────────────────────────────────────────── */
        .mb-empty {
            background: #fff;
            border: 1px dashed #dde3ec;
            border-radius: 10px;
            padding: 54px 24px;
            text-align: center;
            color: #8a94a6;
        }
        .mb-empty iconify-icon { font-size: 2.6rem; color: #c6cede; }
        .mb-empty h5 { color: #4a5566; font-weight: 700; margin: 12px 0 4px; }

        @media (max-width: 767px) {
            .mb-bill__head { grid-template-columns: 1fr auto; }
            .mb-bill__figs { grid-column: 1 / -1; justify-content: space-between; gap: 12px; text-align: left; }
        }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><iconify-icon icon="solar:wallet-money-linear"></iconify-icon> My Bills</h4>
                        <span class="text-muted">Everything you owe, what you have already settled, and how to pay.</span>
                        <br>
                    </div>
                </div>
            </div>

            <div class="container-fluid pb-4">

                <!-- Summary -->
                <div class="mb-kpis" id="mb_kpis"></div>

                <!-- Gateway / permission notices -->
                <div id="mb_gateway_warn"></div>

                <!-- Toolbar -->
                <div class="mb-toolbar">
                    <div class="mb-seg" id="mb_filters">
                        <button type="button" class="is-on" data-filter="all">All</button>
                        <button type="button" data-filter="owing">Owing</button>
                        <button type="button" data-filter="settled">Settled</button>
                    </div>
                    <div class="input-group" style="max-width:280px;">
                        <input type="text" id="mb_search" class="form-control" placeholder="Search consolidation…">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                        </div>
                    </div>
                </div>

                <div id="mb_bills"></div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script src="<?= cdp_asset('dataJs/my_bills.js') ?>"></script>
</body>

</html>
