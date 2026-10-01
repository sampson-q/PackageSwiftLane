<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Consolidation Parking List (PDF / Excel)                  *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
//
// Every package in one consolidation, sorted by sender and numbered S/N
// 1..N: sender (name + locker), Swift tracking, carrier tracking, item
// quantities and descriptions. No prices or totals.
//
// This is the export that used to live on the Financial Sheet
// (views/print/print_financial_sheet*.php). It moved to the consolidation
// list and view so it does not depend on the financial module. It uses the
// same rows helper (cdp_getConsolidationFinancialRows), so the S/N matches
// the one stamped on the shipment labels.
//
// Included by consolidate_parking_list.php (PDF) and
// consolidate_parking_list_excel.php (Excel) with $cdpParkingFormat set.
// Access: logged in, print_consolidate, and not a customer role (the list
// shows every sender's name and locker).

require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/rbac.php';

$cdpParkingFormat = (isset($cdpParkingFormat) && $cdpParkingFormat === 'excel') ? 'excel' : 'pdf';

$user = new User;
$core = new Core;

if ($user->cdp_loginCheck() != true) {
    header('Location: login.php');
    exit;
}
$userData = $user->cdp_getUserData();
if (!$user->cdp_hasPermission('print_consolidate') || cdp_roleIsClient((int) $userData->userlevel)) {
    header('Location: error403.php');
    exit;
}

$db  = new Conexion;
$cid = (int) ($_GET['id'] ?? 0);

$db->cdp_query("SELECT * FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
$db->bind(':cid', $cid);
$db->cdp_execute();
$consol = $db->cdp_registro();

if (!$consol) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Consolidation not found.';
    exit;
}

$esc      = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$ref      = cdp_consolidationRef($consol);
$fileBase = 'parking_list_' . preg_replace('/[^A-Za-z0-9_\-]/', '', ($consol->c_prefix ?? '') . ($consol->c_no ?? ''));
$isDg     = ((int) ($consol->is_dangerous_good ?? 0) === 1);
$packages = cdp_getConsolidationFinancialRows($cid);

// One row per package: sender label + stacked item quantities / descriptions.
$rows = [];
foreach ($packages as $p) {
    $name   = trim(($p->fname ?? '') . ' ' . ($p->lname ?? ''));
    $locker = trim((string) ($p->locker ?? ''));
    $sender = trim($name . ($locker !== '' ? ' (' . $locker . ')' : ''));

    $db->cdp_query("SELECT order_item_quantity, order_item_description
                    FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
    $db->bind(':oid', (int) $p->oid);
    $db->cdp_execute();
    $items = $db->cdp_registros() ?: [];

    $qty = []; $desc = [];
    foreach ($items as $it) {
        $qty[]  = (int) $it->order_item_quantity;
        $desc[] = $esc($it->order_item_description ?? '');
    }

    $rows[] = [
        'sn'      => (int) $p->sn,
        'sender'  => $sender !== '' ? $sender : 'N/A',
        'swift'   => ($p->order_prefix ?? '') . ($p->order_no ?? ''),
        'carrier' => $p->carrier_tracking ?: 'N/A',
        'qty'     => $qty ? implode('<br>', $qty) : '-',
        'desc'    => $desc ? implode('<br>', $desc) : '-',
    ];
}

$title = 'Parking List &mdash; Consolidation ' . $esc($ref);

// ── Excel (.xls = HTML table, downloaded) ────────────────────────────────────
if ($cdpParkingFormat === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileBase . '.xls');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);

    // mso-number-format:'\@' keeps long tracking numbers as text (no 1.23E+14).
    $txt = "mso-number-format:'\\@';";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<table border="1" cellspacing="0"><thead>';
    echo '<tr><th colspan="6" style="font-size:14px;font-weight:bold;text-align:center;">' . $title . ($isDg ? ' (DANGEROUS GOODS)' : '') . '</th></tr>';
    echo '<tr style="background:#3e5569;color:#ffffff;font-weight:bold;"><th>S/N</th><th>Sender</th><th>Swift Tracking</th><th>Tracking</th><th>Qty</th><th>Description</th></tr>';
    echo '</thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="6">No packages in this consolidation.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr><td>' . $r['sn'] . '</td><td>' . $esc($r['sender']) . '</td>'
            . '<td style="' . $txt . '">' . $esc($r['swift']) . '</td>'
            . '<td style="' . $txt . '">' . $esc($r['carrier']) . '</td>'
            . '<td>' . $r['qty'] . '</td><td>' . $r['desc'] . '</td></tr>';
    }
    echo '</tbody></table>';
    exit;
}

// ── PDF (A4 landscape, opens in the browser) ────────────────────────────────
require_once __DIR__ . '/../../helpers/pdf.php';

$h = '<style>
    @page { margin: 12mm 11mm 12mm 11mm; }
    body { font-family: sans-serif; font-size: 8.5px; color: #222; margin: 0; padding: 0; }
    h2 { font-size: 13px; margin: 0 0 3mm 0; padding: 0; text-align: center; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #bbb; padding: 2px 3px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; line-height: 1.15; }
    th { background: #3e5569; color: #fff; text-align: left; font-size: 8px; }
    .sn { width: 5%; white-space: nowrap; }
    .sender { width: 24%; }
    .swift, .tracking { width: 16%; white-space: nowrap; }
    .qty { width: 6%; text-align: right; white-space: nowrap; }
    .desc { width: 33%; }
    tr { page-break-inside: avoid; }
</style>';

$h .= '<h2>' . $title
    . ($isDg ? ' <span style="background:#ff6d00;color:#fff;font-size:8px;padding:2px 6px;border-radius:8px;">&#9888; DANGEROUS GOODS</span>' : '')
    . '</h2>';
$h .= '<table><thead><tr>'
    . '<th class="sn">S/N</th><th class="sender">Sender</th><th class="swift">Swift Tracking</th>'
    . '<th class="tracking">Tracking</th><th class="qty">Qty</th><th class="desc">Description</th>'
    . '</tr></thead><tbody>';
if (!$rows) {
    $h .= '<tr><td colspan="6">No packages in this consolidation.</td></tr>';
}
foreach ($rows as $r) {
    $h .= '<tr><td class="sn">' . $r['sn'] . '</td><td class="sender">' . $esc($r['sender']) . '</td>'
        . '<td class="swift">' . $esc($r['swift']) . '</td><td class="tracking">' . $esc($r['carrier']) . '</td>'
        . '<td class="qty">' . $r['qty'] . '</td><td class="desc">' . $r['desc'] . '</td></tr>';
}
$h .= '</tbody></table>';

try {
    $pdf = swiftlane_render_html_to_pdf($h, $fileBase . '.pdf', ['orientation' => 'L', 'format' => 'A4']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileBase . '.pdf"');
    echo $pdf;
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Could not generate PDF: ' . $esc($e->getMessage());
}
