<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet PDF (one consolidation)                *
// * Sorted by sender name and fitted for A4                              *
// *************************************************************************

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/pdf.php");

$user = new User;
$core = new Core;

if ($user->cdp_loginCheck() != true) {
    header("location: ../../login.php");
    exit;
}
if (!$user->cdp_hasPermission('view_shipment_by_agencies')) {
    header("location: ../../error403.php");
    exit;
}

$db  = new Conexion;
$cid = (int) ($_REQUEST['consolidate_id'] ?? 0);

$db->cdp_query("SELECT * FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $cid);
$db->cdp_execute();
$consol = $db->cdp_registro();

if (!$consol) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Consolidation not found.';
    exit;
}

$cNo = htmlspecialchars(($consol->c_prefix ?? '') . ($consol->c_no ?? ''));
$sym = htmlspecialchars($core->currency ?? 'USD');

/*
 * Pull every package in this consolidation, already sorted by sender name and
 * numbered 1..N. This is the same helper the individual shipment labels use to
 * stamp their S/N, so the sheet and the labels can never disagree.
 * (See cdp_getConsolidationFinancialRows in helpers/querys.php.)
 */
$packages = cdp_getConsolidationFinancialRows($cid);

/*
 * Compact A4 layout:
 * - landscape
 * - smaller margins
 * - fixed table layout
 * - tighter font and padding
 */
$h  = '<style>
    @page {
        margin: 12mm 11mm 12mm 11mm;
    }
    body {
        font-family: sans-serif;
        font-size: 8.5px;
        color: #222;
        margin: 0;
        padding: 0;
    }
    h2 {
        font-size: 13px;
        margin: 0 0 3mm 0;
        padding: 0;
        text-align: center;
    }
    .sub {
        font-size: 8px;
        color: #666;
        margin-bottom: 3mm;
        line-height: 1.25;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    th, td {
        border: 1px solid #bbb;
        padding: 2px 3px;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
        line-height: 1.15;
    }
    th {
        background: #3e5569;
        color: #fff;
        text-align: left;
        font-size: 8px;
    }
    .r {
        text-align: right;
    }
    .sn {
        width: 5%;
        white-space: nowrap;
    }
    .sender {
        width: 24%;
    }
    .swift {
        width: 16%;
        white-space: nowrap;
    }
    .tracking {
        width: 16%;
        white-space: nowrap;
    }
    .qty {
        width: 6%;
        text-align: right;
        white-space: nowrap;
    }
    .desc {
        width: 33%;
    }
    tr {
        page-break-inside: avoid;
    }
</style>';

// Sum totals
$calcWeight = 0.0;
$calcTotal  = 0.0;
if ($packages) {
    foreach ($packages as $p) {
        $calcWeight += (float) $p->detail_weight;
        $calcTotal  += (float) $p->total_order;
    }
}

$isDg = ((int) ($consol->is_dangerous_good ?? 0) === 1);

$h .= '<h2>Financial Sheet &mdash; Consolidation ' . $cNo
    . ($isDg ? ' <span style="background:#ff6d00;color:#fff;font-size:8px;padding:2px 6px;border-radius:8px;">&#9888; DANGEROUS GOODS</span>' : '')
    . '</h2>';

$h .= '<table>
    <thead>
        <tr>
            <th class="sn">S/N</th>
            <th class="sender">Sender</th>
            <th class="swift">Swift Tracking</th>
            <th class="tracking">Tracking</th>
            <th class="qty">Qty</th>
            <th class="desc">Description</th>
        </tr>
    </thead>
    <tbody>';

if (!$packages) {
    $h .= '<tr><td colspan="6">No packages in this consolidation.</td></tr>';
} else {
    foreach ($packages as $p) {
        $senderName = 'N/A';
        if (!empty($p->fname) || !empty($p->lname) || !empty($p->locker)) {
            $fullName = trim(($p->fname ?? '') . ' ' . ($p->lname ?? ''));
            $locker   = trim((string) ($p->locker ?? ''));
            $senderName = htmlspecialchars(trim($fullName . ($locker !== '' ? ' (' . $locker . ')' : '')));
        }

        $db->cdp_query("
            SELECT order_item_quantity, order_item_description
            FROM cdb_add_order_item
            WHERE order_id = :oid
            ORDER BY order_item_id ASC
        ");
        $db->bind(':oid', (int) $p->oid);
        $db->cdp_execute();
        $items = $db->cdp_registros();

        $qtyCol  = '';
        $descCol = '';

        if ($items) {
            foreach ($items as $it) {
                $qtyCol  .= (int) $it->order_item_quantity . '<br>';
                $descCol .= htmlspecialchars((string) ($it->order_item_description ?? '')) . '<br>';
            }
        } else {
            $qtyCol  = '&mdash;';
            $descCol = '&mdash;';
        }

        $shipTrack = htmlspecialchars(($p->order_prefix ?? '') . ($p->order_no ?? ''));
        $carrier   = htmlspecialchars($p->carrier_tracking ?: 'N/A');

        $h .= '<tr>'
            . '<td class="sn">' . (int) $p->sn . '</td>'
            . '<td class="sender">' . $senderName . '</td>'
            . '<td class="swift">' . $shipTrack . '</td>'
            . '<td class="tracking">' . $carrier . '</td>'
            . '<td class="qty">' . $qtyCol . '</td>'
            . '<td class="desc">' . $descCol . '</td>'
            . '</tr>';
    }
}

$h .= '</tbody></table>';

try {
    $pdf = deprixapro_render_html_to_pdf(
        $h,
        'financial_sheet_' . $cNo . '.pdf',
        [
            'orientation' => 'L',
            'format' => 'A4',
        ]
    );

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="financial_sheet_' . $cNo . '.pdf"');
    echo $pdf;
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Could not generate PDF: ' . htmlspecialchars($e->getMessage());
}