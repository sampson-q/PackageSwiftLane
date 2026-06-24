<?php
// *************************************************************************
// * DEPRIXA PRO - Financial Sheet PDF (one consolidation)                *
// * Mirrors the consolidate_view package breakdown: s/n, sender, both     *
// * tracking numbers, qty vs description, weight, total.                  *
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

// Packages in this consolidation. (order_prefix, order_no) is not unique in
// cdb_add_order, so map each detail row to exactly ONE order (prefer the exact
// numeric order_id match, else the lowest matching id) — otherwise the list and
// totals multiply. The per-row weight comes from the detail row itself.
$db->cdp_query("SELECT a.order_id AS oid, d.order_prefix, d.order_no, a.sender_id,
                       d.weight AS detail_weight, a.total_order,
                       COALESCE(NULLIF(pt.tracking_number,''), a.tracking_num) AS carrier_tracking
                FROM cdb_consolidate_detail d
                INNER JOIN cdb_add_order a ON a.order_id = (
                    SELECT a2.order_id FROM cdb_add_order a2
                    WHERE a2.order_prefix = d.order_prefix AND a2.order_no = d.order_no
                    ORDER BY (a2.order_id = CAST(d.order_id AS UNSIGNED)) DESC, a2.order_id ASC
                    LIMIT 1)
                LEFT JOIN cdb_package_tracking_number pt ON pt.order_id = a.order_id
                WHERE d.consolidate_id = :cid ORDER BY d.detail_id ASC");
$db->bind(':cid', $cid);
$db->cdp_execute();
$packages = $db->cdp_registros();

$h  = '<style>
        body { font-family: sans-serif; font-size: 10px; color:#222; }
        h2 { font-size: 16px; margin: 0 0 2px 0; }
        .sub { font-size: 10px; color:#666; margin-bottom: 8px; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #bbb; padding:4px 5px; vertical-align:top; }
        th { background:#3e5569; color:#fff; text-align:left; }
        .r { text-align:right; }
      </style>';

// Consolidation weight AND total are the sum of its member packages' own values
// (the stored cdb_consolidate.total_weight / total_order are always 0), matching
// the on-screen sheet.
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
    . ($isDg ? ' <span style="background:#ff6d00;color:#fff;font-size:9px;padding:2px 6px;border-radius:8px;">&#9888; DANGEROUS GOODS</span>' : '')
    . '</h2>';
$h .= '<div class="sub">Date: ' . htmlspecialchars((string) $consol->c_date)
    . ' &nbsp;|&nbsp; Total weight: ' . round($calcWeight, 2)
    . ' &nbsp;|&nbsp; Consolidation total: ' . $sym . ' ' . cdb_money_format_bar($calcTotal)
    . ' &nbsp;|&nbsp; Generated ' . date('Y-m-d H:i') . '</div>';

$h .= '<table><thead><tr>'
    . '<th style="width:28px;">S/N</th>'
    . '<th>Sender</th>'
    . '<th>Shipment Tracking</th>'
    . '<th>Carrier Tracking</th>'
    . '<th class="r" style="width:34px;">Qty</th>'
    . '<th>Description</th>'
    . '<th class="r" style="width:50px;">Weight</th>'
    . '<th class="r" style="width:70px;">Total</th>'
    . '</tr></thead><tbody>';

if (!$packages) {
    $h .= '<tr><td colspan="8">No packages in this consolidation.</td></tr>';
} else {
    $sn = 1;
    foreach ($packages as $p) {
        // Sender name
        $db->cdp_query("SELECT fname, lname FROM cdb_users WHERE id = :uid LIMIT 1");
        $db->bind(':uid', (int) $p->sender_id);
        $db->cdp_execute();
        $s = $db->cdp_registro();
        $sender = $s ? htmlspecialchars(trim(($s->fname ?? '') . ' ' . ($s->lname ?? ''))) : 'N/A';

        // Items (qty + description)
        $db->cdp_query("SELECT order_item_quantity, order_item_description FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
        $db->bind(':oid', (int) $p->oid);
        $db->cdp_execute();
        $items = $db->cdp_registros();

        $qtyCol = ''; $descCol = '';
        if ($items) {
            foreach ($items as $it) {
                $qtyCol  .= (int) $it->order_item_quantity . '<br>';
                $descCol .= htmlspecialchars($it->order_item_description ?? '') . '<br>';
            }
        } else {
            $qtyCol = '&mdash;'; $descCol = '&mdash;';
        }

        $shipTrack = htmlspecialchars(($p->order_prefix ?? '') . ($p->order_no ?? ''));
        $carrier   = htmlspecialchars($p->carrier_tracking ?: 'N/A');

        $h .= '<tr>'
            . '<td>' . $sn . '</td>'
            . '<td>' . $sender . '</td>'
            . '<td>' . $shipTrack . '</td>'
            . '<td>' . $carrier . '</td>'
            . '<td class="r">' . $qtyCol . '</td>'
            . '<td>' . $descCol . '</td>'
            . '<td class="r">' . number_format((float) $p->detail_weight, 2) . '</td>'
            . '<td class="r">' . $sym . ' ' . cdb_money_format_bar($p->total_order) . '</td>'
            . '</tr>';
        $sn++;
    }
}
$h .= '</tbody></table>';

try {
    $pdf = deprixapro_render_html_to_pdf($h, 'financial_sheet_' . $cNo . '.pdf', ['orientation' => 'L', 'format' => 'A4']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="financial_sheet_' . $cNo . '.pdf"');
    echo $pdf;
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Could not generate PDF: ' . htmlspecialchars($e->getMessage());
}
