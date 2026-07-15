<?php
// *************************************************************************
// * Accounts Receivable - printable invoice for ONE billing record        *
// * (a consolidation + customer row of cdb_consolidate_customer_billing). *
// * Reuses print_inv_ship.php's stylesheet/design verbatim.               *
// *************************************************************************
require_once('helpers/querys.php');

$cid = (int) ($_GET['consolidate_id'] ?? 0);
$sid = (int) ($_GET['sender_id'] ?? 0);
if ($cid <= 0 || $sid <= 0) {
    cdp_redirect_to("accounts_receivable.php");
}

// Same source of truth (and join) the Accounts Receivable list uses.
$db->cdp_query("SELECT b.*, u.fname, u.lname, u.locker, u.email, u.phone,
                       CONCAT(COALESCE(c.c_prefix,''), COALESCE(c.c_no,'')) AS cno
                FROM cdb_consolidate_customer_billing b
                LEFT JOIN cdb_users u ON u.id = b.sender_id
                LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id
                WHERE b.consolidate_id = :cid AND b.sender_id = :sid LIMIT 1");
$db->bind(':cid', $cid);
$db->bind(':sid', $sid);
$db->cdp_execute();
$bill = $db->cdp_registro();
if (!$bill) {
    cdp_redirect_to("accounts_receivable.php");
}

$cno      = ($bill->cno !== null && $bill->cno !== '') ? $bill->cno : ('#' . $cid);
$billed   = (float) $bill->amount_ghs;
$disc     = (float) $bill->discount_ghs;
$paid     = (float) $bill->paid_ghs;
$handling = (float) ($bill->handling_ghs ?? 0);
$rate     = (float) ($bill->exchange_rate ?? 0);
// Identical to the list's formula: GREATEST(0, billed - discount - paid).
$outstanding = max(0, $billed - $disc - $paid);

// Packages in this consolidation for this customer. cdb_consolidate_detail is
// the link and joins to cdb_add_order on prefix+no, as the Financial Sheet does.
$db->cdp_query("SELECT a.order_id, a.order_prefix, a.order_no, a.total_order,
                       COALESCE(NULLIF(t.tracking_number,''), a.tracking_num) AS tracking_number
                FROM cdb_consolidate_detail d
                INNER JOIN cdb_add_order a ON a.order_id = (
                    SELECT a2.order_id FROM cdb_add_order a2
                    WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
                    ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
                    LIMIT 1)
                LEFT JOIN cdb_package_tracking_number t ON t.order_id = a.order_id
                WHERE d.consolidate_id = :cid AND a.sender_id = :sid
                ORDER BY a.order_id");
$db->bind(':cid', $cid);
$db->bind(':sid', $sid);
$db->cdp_execute();
$packages = $db->cdp_registros() ?: [];

// Payments recorded against this billing record.
$db->cdp_query("SELECT p.amount_ghs, p.mode, p.reference, p.recorded_at
                FROM cdb_fs_payments p
                WHERE p.consolidate_id = :cid AND p.sender_id = :sid
                ORDER BY p.id");
$db->bind(':cid', $cid);
$db->bind(':sid', $sid);
$db->cdp_execute();
$payments = $db->cdp_registros() ?: [];

$cust_name = trim((string) ($bill->fname ?? '') . ' ' . (string) ($bill->lname ?? ''));
if ($cust_name === '') { $cust_name = 'Customer #' . $sid; }
$g = function ($n) { return '&#8373;' . number_format((float) $n, 2); };
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title>Invoice - <?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type="text/css" href="assets/custom_dependencies/print.css" rel="stylesheet" />

    
    <style>
        @page {
            size: 110mm auto;
            margin: 0;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            overflow: visible;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.35;
            color: #000;
        }

        .label-page {
            width: 100%;
            max-width: 110mm;
            padding: 4mm 3.5mm 5mm;
            overflow: visible;
            display: block;
        }

        .topbar {
            border-bottom: 0.35mm solid #000;
            padding-bottom: 2.2mm;
            margin-bottom: 2.2mm;
        }

        .brand-wrap {
            display: block;
        }

        .logo {
            text-align: center;
            margin-bottom: -15.5mm;
            margin-top: -15mm;
        }

        .logo img {
            display: inline-block;
            max-width: 120mm;
            max-height: 54mm;
            object-fit: contain;
        }

        .brand-text {
            min-width: 0;
            text-align: center;
        }

        .brand-name {
            margin: 0 0 1mm 0;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-lines {
            margin: 0;
            font-size: 16px;
            line-height: 1.35;
            word-break: break-word;
        }

        .barcode {
            text-align: center;
            margin-top: 1.5mm;
        }

        .barcode img {
            display: inline-block;
            width: 100%;
            max-width: 80mm;
            height: 20mm;
            object-fit: contain;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2mm;
        }

        .panel {
            border: 0.35mm solid #000;
            padding: 1.5mm 1.8mm;
            overflow: visible;
        }

        .panel-title {
            margin: 0 0 1mm 0;
            padding-bottom: 0.8mm;
            border-bottom: 0.25mm solid #000;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .kv {
            display: grid;
            grid-template-columns: 19mm 1fr;
            gap: 1.2mm;
            margin: 0 0 0.8mm 0;
            font-size: 15px;
            line-height: 1.35;
        }

        .kv .k {
            font-weight: 700;
            white-space: nowrap;
        }

        .kv .v {
            min-width: 0;
            word-break: break-word;
        }

        .sn-row {
            align-items: baseline;
        }

        .sn-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
        }

        .sn-total {
            font-size: 15px;
            font-weight: 700;
        }

        .items-panel {
            border: 0.35mm solid #000;
            padding: 1.2mm 1.4mm;
            overflow: visible;
            min-height: 0;
        }

        .items-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            margin-bottom: 1mm;
        }

        .items-header h3 {
            margin: 0;
            font-size: 14.5px;
            font-weight: 700;
            line-height: 1.1;
        }

        .meta-chip {
            font-size: 14px;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            background: #f0f0f0;
            font-size: 16px;
            font-weight: 700;
        }

        th, td {
            border: 0.2mm solid #000;
            padding: 0.75mm 1mm;
            vertical-align: top;
            word-break: break-word;
        }

        td.qty {
            width: 16mm;
            text-align: center;
            font-weight: 700;
        }

        td.desc {
            width: auto;
        }

        tfoot td {
            font-size: 16px;
            padding: 0.8mm 1mm;
            background: #fafafa;
        }

        .footer {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2mm;
            align-items: start;
        }

        .total-box {
            border: 0.35mm solid #000;
            padding: 1.5mm;
            text-align: center;
        }

        .total-box label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }

        .total-box .value {
            font-size: 23px;
            font-weight: 700;
            line-height: 1;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            align-items: end;
        }

        .signature {
            text-align: center;
            font-size: 14px;
            line-height: 1.2;
        }

        .signature .line {
            margin-top: 43mm;
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
        }

        .credits {
            text-align: center;
            font-size: 12px;
            margin-top: 5mm;
        }

        .print-button {
            text-align: center;
            margin: 2mm 0 0 0;
        }

        .print-button button {
            padding: 10px 24px;
            font-size: 20px;
            cursor: pointer;
        }

        .print-info {
            margin-top: 1mm;
            font-size: 14.5px;
            color: #000;
        }


        /* Stronger contrast for thermal printing */
        body, .label-page, .panel, .items-panel, .total-box, .signature, .credits,
        .topbar, .brand-text, .brand-lines, .brand-name, .panel-title, .items-header h3,
        .meta-chip, .kv, .kv .k, .kv .v, table, thead th, th, td, tfoot td {
            color: #000 !important;
        }

        .panel, .items-panel, .total-box {
            border-color: #000 !important;
        }

        .topbar, .panel-title, th, td, tfoot td, .total-box {
            border-color: #000 !important;
        }

        .brand-name,
        .panel-title,
        .items-header h3,
        .total-box label,
        .kv .k,
        .signature .line,
        .print-info {
            font-weight: 700 !important;
        }

        .brand-lines,
        .kv .v,
        td,
        th,
        tfoot td,
        .credits,
        .meta-chip {
            font-weight: 600 !important;
        }

        .logo img,
        .barcode img {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .financial-sn{
            display:inline-flex;
            align-items:center;
            justify-content:center;

            min-width:28px;
            height:28px;

            padding:0 8px;

            border:2px solid #000;
            border-radius:999px;

            background:#fff;
            color:#000 !important;

            font-weight:800;
            font-size:15px;
            line-height:1;
            white-space:nowrap;

            -webkit-print-color-adjust:exact;
            print-color-adjust:exact;
        }

        @media print {
            html, body {
                width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                -webkit-text-size-adjust: 100% !important;
                text-size-adjust: 100% !important;
            }

            * {
                color: #000 !important;
                text-shadow: none !important;
                box-shadow: none !important;
                filter: none !important;
            }

            .label-page {
                width: 100% !important;
                max-width: 110mm !important;
                height: auto !important;
                min-height: 0 !important;
                padding: 4mm 3.5mm 5mm !important;
                overflow: visible !important;
            }

            .topbar,
            .panel,
            .items-panel,
            .total-box,
            .signature .line,
            th,
            td,
            tfoot td {
                border-color: #000 !important;
            }

            .print-button,
            .print-info {
                display: none !important;
            }

            a[href]:after {
                content: "";
            }
        }
    </style>

</head>
<body>
    <div class="label-page">
        <div class="topbar">
            <div class="brand-wrap">
                <div class="logo">
                    <?php echo ($core->logo) ? '<img src="assets/uploads/SWIFT LOGO PNG-04.png" alt="' . htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') . '"/>' : '<h3>' . htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8') . '</h3>'; ?>
                </div>
                <div class="brand-text" style="color:#000 !important;">
                    <p class="brand-name"><?php echo htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="brand-lines">
                        <strong><?php echo $lang['inv-shipping2']; ?>:</strong> <?php echo htmlspecialchars("+233(0)243438799 || +233(0)342292798", ENT_QUOTES, 'UTF-8'); ?><br>
                        <strong><?php echo $lang['inv-shipping3']; ?>:</strong> <?php echo htmlspecialchars($core->site_email, ENT_QUOTES, 'UTF-8'); ?><br>
                        <strong>Address:</strong> <?php echo htmlspecialchars("#01, Adaman Crescent, Behind The Allied Filling Station, Tesano Abeka Junction", ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>

            <div class="barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($cno); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
            </div>
        </div>

        <div class="info-grid">
            <div class="panel">
                <div class="panel-title">Bill To</div>

                <div class="kv">
                    <div class="k">Name:</div>
                    <div class="v"><strong><?php echo htmlspecialchars($cust_name, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                </div>

                <div class="kv">
                    <div class="k">Locker:</div>
                    <div class="v"><?php echo htmlspecialchars((string) ($bill->locker ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="kv">
                    <div class="k">Email:</div>
                    <div class="v"><?php echo htmlspecialchars((string) ($bill->email ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="kv">
                    <div class="k">Phone:</div>
                    <div class="v"><?php echo htmlspecialchars((string) ($bill->phone ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Invoice Details</div>

            <div class="kv">
                <div class="k">Consolidation:</div>
                <div class="v">&nbsp;&nbsp;&nbsp;&nbsp;<strong><?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?></strong></div>
            </div>

            <div class="kv">
                <div class="k">Billed On:</div>
                <div class="v"><?php echo $bill->billed_at ? htmlspecialchars(date('Y-m-d H:i', strtotime((string) $bill->billed_at)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
            </div>

            <div class="kv">
                <div class="k">Packages:</div>
                <div class="v"><?php echo count($packages); ?></div>
            </div>

            <?php if ($rate > 0) : ?>
            <div class="kv">
                <div class="k">Rate:</div>
                <div class="v">$1 = &#8373;<?php echo number_format($rate, 2); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="items-panel">
            <div class="items-header">
                <h3><?php echo htmlspecialchars("Packages", ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 14mm;">#</th>
                        <th>Tracking</th>
                        <th style="text-align:right;">Amount (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$packages) : ?>
                        <tr><td colspan="3">No packages found for this record.</td></tr>
                    <?php else : $i = 0; foreach ($packages as $p) : $i++; ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p->order_prefix . $p->order_no, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($p->tracking_number)) : ?>
                                    <br><small><?php echo htmlspecialchars((string) $p->tracking_number, ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">$<?php echo number_format((float) $p->total_order, 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="items-panel">
            <div class="items-header">
                <h3><?php echo htmlspecialchars("Summary", ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>

            <table>
                <tbody>
                    <tr>
                        <td>Billed</td>
                        <td style="text-align:right;"><?php echo $g($billed); ?></td>
                    </tr>
                    <?php if ($handling > 0) : ?>
                    <tr>
                        <td>Handling Fee</td>
                        <td style="text-align:right;"><?php echo $g($handling); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($disc > 0) : ?>
                    <tr>
                        <td>Discount</td>
                        <td style="text-align:right;">- <?php echo $g($disc); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Paid</td>
                        <td style="text-align:right;"><?php echo $g($paid); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Outstanding</strong></td>
                        <td style="text-align:right;"><strong><?php echo $g($outstanding); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($payments) : ?>
        <div class="items-panel">
            <div class="items-header">
                <h3><?php echo htmlspecialchars("Payments Received", ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p) : ?>
                        <tr>
                            <td><?php echo $p->recorded_at ? htmlspecialchars(date('Y-m-d', strtotime((string) $p->recorded_at)), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string) $p->mode), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p->reference ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:right;"><?php echo $g($p->amount_ghs); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="credits text-center">
            Designed by <b>iSolveAfrica</b><br>
            +233 (0) 591 447 845 / +233 (0) 50 550 5009<br>
            Email: hello@isolveafrica.com<br>
            https://www.isolveafrica.com
        </div>
    </div>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print</div>
    </div>
</body>

</html>
