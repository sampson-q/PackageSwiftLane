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
 * Pull everything in one query:
 * - map each detail row to exactly one order
 * - bring sender data with the row
 * - sort by sender name
 */
$db->cdp_query("
    SELECT
        a.order_id AS oid,
        d.order_prefix,
        d.order_no,
        d.weight AS detail_weight,
        a.total_order,
        COALESCE(NULLIF(pt.tracking_number,''), a.tracking_num) AS carrier_tracking,
        a.sender_id,
        u.fname,
        u.lname,
        u.locker
    FROM cdb_consolidate_detail d
    INNER JOIN cdb_add_order a ON a.order_id = (
        SELECT a2.order_id
        FROM cdb_add_order a2
        WHERE a2.order_prefix = d.order_prefix
          AND a2.order_no = d.order_no
        ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
        LIMIT 1
    )
    LEFT JOIN cdb_package_tracking_number pt ON pt.order_id = a.order_id
    LEFT JOIN cdb_users u ON u.id = a.sender_id
    WHERE d.consolidate_id = :cid
    ORDER BY
        COALESCE(u.lname, '') ASC,
        COALESCE(u.fname, '') ASC,
        COALESCE(u.locker, '') ASC,
        d.detail_id ASC
");
$db->bind(':cid', $cid);
$db->cdp_execute();
$packages = $db->cdp_registros();

/*
 * Compact A4 layout:
 * - landscape
 * - smaller margins
 * - fixed table layout
 * - tighter font and padding
 */
$h  = '<style>
    @page {
        margin: 6mm 5mm 6mm 5mm;
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
        margin: 0 0 2mm 0;
        padding: 0;
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

$h .= '<div class="sub">'
    . 'Date: ' . htmlspecialchars((string) $consol->c_date)
    . ' &nbsp;|&nbsp; Total weight: ' . round($calcWeight, 2)
    . ' &nbsp;|&nbsp; Consolidation total: ' . $sym . ' ' . cdb_money_format_bar($calcTotal)
    . ' &nbsp;|&nbsp; Generated ' . date('Y-m-d H:i')
    . '</div>';

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
    $sn = 1;

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
            . '<td class="sn">' . $sn . '</td>'
            . '<td class="sender">' . $senderName . '</td>'
            . '<td class="swift">' . $shipTrack . '</td>'
            . '<td class="tracking">' . $carrier . '</td>'
            . '<td class="qty">' . $qtyCol . '</td>'
            . '<td class="desc">' . $descCol . '</td>'
            . '</tr>';

        $sn++;
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