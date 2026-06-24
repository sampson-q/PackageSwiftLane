<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet (consolidation -> packages -> items)    *
// *************************************************************************

if ((!$user->cdp_is_Admin() && (int) ($user->userlevel ?? 0) !== 3)) {
    cdp_redirect_to("login.php");
}

$userData = $user->cdp_getUserData();

/**
 * Return existing columns for a table in the current database.
 */
function cdp_fs_get_table_columns($db, $table)
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $cache[$table] = [];

    try {
        $db->cdp_query("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $db->bind(':table_name', $table);
        $db->cdp_execute();
        $rows = $db->cdp_registros();

        if ($rows) {
            foreach ($rows as $row) {
                $name = strtolower((string) ($row->COLUMN_NAME ?? $row->column_name ?? ''));
                if ($name !== '') {
                    $cache[$table][$name] = true;
                }
            }
        }
    } catch (Throwable $e) {
        // Fallback to empty list; the search code still works with the base columns.
    }

    return $cache[$table];
}

function cdp_fs_has_column($columns, $name)
{
    return isset($columns[strtolower($name)]);
}

function cdp_fs_escape_like_column($column)
{
    // The column names are only chosen from a trusted whitelist / schema check.
    return $column;
}
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title>Financial Sheet | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <style type="text/css">
        /* ----- Three-level visual hierarchy: consolidation > package > items ----- */
        .fs-level-chip {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .5px;
            padding: 2px 7px;
            border-radius: 10px;
            margin-right: 8px;
            vertical-align: middle;
        }

        .fs-chip-consol {
            background: #1d2c9e;
            color: #fff;
        }

        .fs-chip-pkg {
            background: #cfd8e3;
            color: #2b3a4a;
        }

        /* Level 1 — consolidation card */
        .fs-consol-card {
            border: 1px solid #c7cfdb;
            border-left: 5px solid #1d2c9e;
            border-radius: 6px;
            overflow: hidden;
        }

        .fs-consol-header {
            background: #f4f6fb;
            color: #1f2d3d;
            cursor: pointer;
            font-size: 15px;
        }

        .fs-consol-header b {
            color: #16245f;
        }

        .fs-consol-header .btn-light,
        .fs-consol-header .btn-light i {
            color: #212529 !important;
        }

        .fs-consol-header .fs-dim {
            opacity: .85;
            font-size: 13px;
        }

        /* Active consolidation: tint the WHOLE card so its packages sit on this bg. */
        .fs-consol-card.fs-active {
            background: #e7ecfb;
            box-shadow: inset 0 0 0 2px #1d2c9e;
        }

        .fs-consol-card.fs-active>.fs-consol-header {
            background: #dbe3fb;
        }

        /* Level 2 — package card (sits on the consolidation's background) */
        .fs-pkg-card {
            border: 1px solid #dfe4ea;
            border-left: 4px solid #8aa0bd;
            border-radius: 5px;
            margin-left: 12px;
            background: #fff;
            overflow: hidden;
        }

        .fs-pkg-header {
            background: #f7f9fb;
            color: #26333f;
            cursor: pointer;
        }

        .fs-pkg-header b,
        .fs-pkg-header i {
            color: #26333f;
        }

        /* Active package: a DIFFERENT bg so its items stand out from the consolidation. */
        .fs-pkg-card.fs-active {
            background: #fff7e6;
            box-shadow: inset 0 0 0 2px #e0a800;
        }

        .fs-pkg-card.fs-active>.fs-pkg-header {
            background: #ffeec6;
        }

        /* Level 3 — items */
        .fs-items-table {
            margin-left: 6px;
            background: #fff;
        }

        .fs-items-table input:disabled {
            background: #f3f4f6;
        }

        /* Money */
        .fs-money {
            font-weight: 700;
            color: #1b8a4b;
            white-space: nowrap;
        }

        /* Dangerous-goods marker: warning icon only, in a coloured circle. */
        .fs-dg-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #fff;
            font-size: 11px;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* Per-input currency toggle (tiny, above the custom-price box) */
        .fs-cur-mini .fs-cur-btn {
            padding: 0 7px;
            font-size: 12px;
            line-height: 18px;
            height: 20px;
            font-weight: 700;
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

        .fs-pkg-caret,
        .fs-consol-caret {
            transition: transform .2s;
        }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card card-outline" style="border-top: 3px solid #bbb">
                            <h4 class="card-title ml-4 mt-3"><i class="fas fa-file-invoice-dollar"></i> Financial Sheet</h4>

                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" id="fs_search" class="form-control" placeholder="Search by consolidation number...">
                                            <div class="input-group-append">
                                                <button id="fs_search_btn" class="btn btn-secondary" type="button">Search</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" id="fs_search_package" class="form-control" placeholder="Search by package / tracking...">
                                            <div class="input-group-append">
                                                <button id="fs_search_package_btn" class="btn btn-secondary" type="button">Search</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <small class="text-muted">Rate: $1 = &#8373;<span id="fs_rate_label"></span> &middot; custom prices can be entered in $ or &#8373; &middot; the <b>PDF</b> button exports a consolidation.</small>
                                    </div>
                                </div>

                                <div id="loader" style="display:none;" class="text-center my-3">
                                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                </div>

                                <div class="outer_div mt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>
    <script>
        // Storage stays canonical USD; the per-input toggle only controls how an
        // operator-typed custom price is interpreted before conversion to USD.
        window.FS_RATE = <?php echo (float) ($core->exchange_rate ?: 1); ?>;
    </script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="<?= cdp_asset('dataJs/financial_sheet.js') ?>"></script>
</body>

</html>