<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Consolidation Parking List (PDF / Excel)                  *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
//
// Every package in one consolidation, sorted by sender and numbered S/N
// 1..N. No prices or totals.
//
// Layout is grouped, not one flat row per package:
//   Sender           — once per sender, centred down the sender's block
//   S/N, Swift Tracking, Tracking — once per package, centred down its items
//   Qty, Description — one line per item, so each quantity sits beside its
//                      own description
// Lines get heavier going up the hierarchy: dashed between items, solid
// between packages, thick between senders.
//
// The PDF is drawn with TCPDF directly, not Html2Pdf: Html2Pdf cannot break a
// long unbroken tracking number (it ran into the next column) and cannot
// split a merged cell across pages (one sender can have 200+ packages). When
// a sender's block continues on the next page, the name is repeated there,
// marked "continued". A package is only split across pages when it alone is
// taller than a page; its S/N and tracking numbers are then repeated too.
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
// Some descriptions are stored entity-encoded (&#34;); decode to plain text.
$plain    = function ($v) { return trim(html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8')); };
$ref      = cdp_consolidationRef($consol);
$fileBase = 'parking_list_' . preg_replace('/[^A-Za-z0-9_\-]/', '', ($consol->c_prefix ?? '') . ($consol->c_no ?? ''));
$isDg     = ((int) ($consol->is_dangerous_good ?? 0) === 1);
$packages = cdp_getConsolidationFinancialRows($cid);

// All items of all packages in one query, keyed by order id.
$itemsByOrder = [];
$oids = array_values(array_unique(array_map(function ($p) { return (int) $p->oid; }, $packages)));
if ($oids) {
    $db->cdp_query("SELECT order_id, order_item_quantity, order_item_description
                    FROM cdb_add_order_item
                    WHERE order_id IN (" . implode(',', $oids) . ")
                    ORDER BY order_id ASC, order_item_id ASC");
    $db->cdp_execute();
    foreach (($db->cdp_registros() ?: []) as $it) {
        $itemsByOrder[(int) $it->order_id][] = [
            'qty'  => (string) (int) $it->order_item_quantity,
            'desc' => $plain($it->order_item_description ?? ''),
        ];
    }
}

// Group packages by sender, blocks in order of first appearance. The key is
// the sender id (the name only when there is no sender), so two people with
// the same name never merge. The rows helper sorts by sender name, so a named
// sender's packages are already adjacent; senders whose account no longer
// exists all sort as a blank name and interleave, so their packages are
// collected here and their S/N values are not consecutive. The rows are never
// re-sorted: the S/N must match the one on the shipment labels.
$groups     = [];
$groupIndex = [];
foreach ($packages as $p) {
    $name   = trim(($p->fname ?? '') . ' ' . ($p->lname ?? ''));
    $locker = trim((string) ($p->locker ?? ''));
    $sid    = (int) ($p->sender_id ?? 0);
    $key    = $sid > 0 ? 'id:' . $sid : 'name:' . $name . '|' . $locker;

    $items = $itemsByOrder[(int) $p->oid] ?? [];
    if (!$items) {
        $items = [['qty' => '-', 'desc' => '-']];
    }
    foreach ($items as &$it) {
        if ($it['desc'] === '') {
            $it['desc'] = '-';
        }
    }
    unset($it);

    $pkg = [
        'sn'      => (string) (int) $p->sn,
        'swift'   => $plain(($p->order_prefix ?? '') . ($p->order_no ?? '')),
        'carrier' => $plain($p->carrier_tracking ?? '') !== '' ? $plain($p->carrier_tracking) : 'N/A',
        'items'   => $items,
    ];

    if (isset($groupIndex[$key])) {
        $groups[$groupIndex[$key]]['packages'][] = $pkg;
    } else {
        // A sender whose account no longer exists is shown by id, which is
        // also why two such blocks are not merged.
        $groupIndex[$key] = count($groups);
        $groups[] = [
            'name'     => $name !== '' ? $name : ($sid > 0 ? 'Unknown Sender' : 'N/A'),
            'locker'   => ($name === '' && $locker === '' && $sid > 0) ? 'Sender ID ' . $sid : $locker,
            'packages' => [$pkg],
        ];
    }
}

$pkgCount   = count($packages);
$groupCount = count($groups);
$countLabel = function ($n) { return $n . ' package' . ($n === 1 ? '' : 's'); };

// ── Excel (.xls = HTML table, downloaded) ────────────────────────────────────
if ($cdpParkingFormat === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileBase . '.xls');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);

    // mso-number-format:'\@' keeps long tracking numbers as text (no 1.23E+14).
    $txt = "mso-number-format:'\\@';";
    $mid = 'vertical-align:middle;';
    $br  = '<br style="mso-data-placement:same-cell;">';
    $title = 'Parking List &mdash; Consolidation ' . $esc($ref);

    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<table border="1" cellspacing="0"><thead>';
    echo '<tr><th colspan="6" style="font-size:14px;font-weight:bold;text-align:center;">' . $title . ($isDg ? ' (DANGEROUS GOODS)' : '') . '</th></tr>';
    echo '<tr><th colspan="6" style="font-weight:normal;text-align:center;">' . $countLabel($pkgCount) . ' &middot; ' . $groupCount . ' sender' . ($groupCount === 1 ? '' : 's') . '</th></tr>';
    echo '<tr style="background:#3e5569;color:#ffffff;font-weight:bold;"><th>Sender</th><th>S/N</th><th>Swift Tracking</th><th>Tracking</th><th>Qty</th><th>Description</th></tr>';
    echo '</thead><tbody>';
    if (!$groups) {
        echo '<tr><td colspan="6">No packages in this consolidation.</td></tr>';
    }
    foreach ($groups as $g) {
        $gRows = 0;
        foreach ($g['packages'] as $pkg) {
            $gRows += count($pkg['items']);
        }
        $firstOfGroup = true;
        foreach ($g['packages'] as $pkg) {
            $pRows = count($pkg['items']);
            foreach ($pkg['items'] as $i => $it) {
                echo '<tr>';
                if ($firstOfGroup) {
                    echo '<td rowspan="' . $gRows . '" style="' . $mid . 'background:#eef2f6;">'
                        . '<b>' . $esc($g['name']) . '</b>'
                        . ($g['locker'] !== '' ? $br . $esc($g['locker']) : '')
                        . (count($g['packages']) > 1 ? $br . $countLabel(count($g['packages'])) : '') . '</td>';
                    $firstOfGroup = false;
                }
                if ($i === 0) {
                    echo '<td rowspan="' . $pRows . '" style="' . $mid . 'text-align:center;">' . $esc($pkg['sn']) . '</td>'
                        . '<td rowspan="' . $pRows . '" style="' . $mid . $txt . '">' . $esc($pkg['swift']) . '</td>'
                        . '<td rowspan="' . $pRows . '" style="' . $mid . $txt . '">' . $esc($pkg['carrier']) . '</td>';
                }
                echo '<td style="text-align:center;">' . $esc($it['qty']) . '</td><td>' . $esc($it['desc']) . '</td></tr>';
            }
        }
    }
    echo '</tbody></table>';
    exit;
}

// ── PDF (A4 landscape, opens in the browser) ────────────────────────────────
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

$titleText = 'Parking List — Consolidation ' . $plain($ref);

try {
    $pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
        public $cdpFooterText = '';
        public function Footer()
        {
            $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;
            $this->SetY(-9);
            $this->SetFont('dejavusanscondensed', '', 6.5);
            $this->SetTextColor(120, 130, 140);
            $this->Cell($w / 2, 4, $this->cdpFooterText, 0, 0, 'L');
            $this->Cell($w / 2, 4, $this->getAliasRightShift() . 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
        }
    };
    $pdf->cdpFooterText = $titleText;
    $pdf->SetCreator('Swiftlane');
    $pdf->SetTitle($titleText);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 10, 10);
    $pdf->setFooterMargin(9);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->setFontSubsetting(true);

    $FONT   = 'dejavusanscondensed';
    $FS     = 7.5;
    $PAD    = [1.4, 1.0, 1.4, 1.0];
    $bottom = $pdf->getPageHeight() - 13;

    // Column widths (mm) — 277 = A4 landscape minus 2 x 10 mm margins.
    $cols = [
        'sender'   => ['w' => 50,  'label' => 'Sender',         'align' => 'L'],
        'sn'       => ['w' => 11,  'label' => 'S/N',            'align' => 'C'],
        'swift'    => ['w' => 31,  'label' => 'Swift Tracking', 'align' => 'L'],
        'carrier'  => ['w' => 56,  'label' => 'Tracking',       'align' => 'L'],
        'qty'      => ['w' => 11,  'label' => 'Qty',            'align' => 'C'],
        'desc'     => ['w' => 118, 'label' => 'Description',    'align' => 'L'],
    ];
    $x = 10;
    foreach ($cols as $k => $c) {
        $cols[$k]['x'] = $x;
        $x += $c['w'];
    }
    $xEnd = $x;

    $ink    = [34, 34, 34];
    $navy   = [62, 85, 105];
    $mid    = [138, 150, 163];
    $light  = [184, 194, 204];
    $tint   = [238, 242, 246];

    $setLine = function ($width, $rgb, $dash = 0) use ($pdf) {
        $pdf->SetLineStyle(['width' => $width, 'color' => $rgb, 'dash' => $dash, 'cap' => 'butt']);
    };
    $measure = function ($w, $text, $style = '', $size = null) use ($pdf, $FONT, $FS, $PAD) {
        $pdf->SetFont($FONT, $style, $size ?? $FS);
        $pdf->setCellPaddings($PAD[0], $PAD[1], $PAD[2], $PAD[3]);
        return $pdf->getStringHeight($w, $text);
    };
    $cell = function ($k, $y, $h, $text, $style = '', $valign = 'T') use ($pdf, $cols, $FONT, $FS, $PAD, $ink) {
        $pdf->SetFont($FONT, $style, $FS);
        $pdf->setCellPaddings($PAD[0], $PAD[1], $PAD[2], $PAD[3]);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->MultiCell($cols[$k]['w'], $h, $text, 0, $cols[$k]['align'], false, 0, $cols[$k]['x'], $y, true, 0, false, true, $h, $valign);
    };

    // Sender label lines: [text, style, size]. The package count shows for
    // senders with more than one; "continued" marks a page the block carries
    // over to.
    $senderLines = function ($g, $continued) use ($countLabel) {
        $lines = [[$g['name'], 'B', 8]];
        if ($g['locker'] !== '') {
            $lines[] = [$g['locker'], '', 7.5];
        }
        if ($continued) {
            $lines[] = ['(continued)', 'I', 6.5];
        } elseif (count($g['packages']) > 1) {
            $lines[] = [$countLabel(count($g['packages'])), 'I', 6.5];
        }
        return $lines;
    };
    $senderHeight = function ($lines) use ($pdf, $FONT, $cols) {
        $h = 0;
        foreach ($lines as $l) {
            $pdf->SetFont($FONT, $l[1], $l[2]);
            $pdf->setCellPaddings(1.6, 0.3, 1.6, 0.3);
            $h += $pdf->getStringHeight($cols['sender']['w'], $l[0]);
        }
        return $h + 2.4;
    };

    // Measure every item row. A package's rows are at least as tall as its
    // S/N / Swift / Tracking cells need (the last row takes the slack).
    foreach ($groups as &$g) {
        foreach ($g['packages'] as &$pkg) {
            $total = 0;
            foreach ($pkg['items'] as &$it) {
                $it['h'] = max($measure($cols['qty']['w'], $it['qty']), $measure($cols['desc']['w'], $it['desc']));
                $total  += $it['h'];
            }
            unset($it);
            $need = max(
                $measure($cols['sn']['w'], $pkg['sn'], 'B'),
                $measure($cols['swift']['w'], $pkg['swift']),
                $measure($cols['carrier']['w'], $pkg['carrier'])
            );
            if ($total < $need) {
                $pkg['items'][count($pkg['items']) - 1]['h'] += $need - $total;
            }
        }
        unset($pkg);
        $g['need'] = $senderHeight($senderLines($g, false));
    }
    unset($g);

    $y        = 0;
    $tableTop = 0;

    $drawHeaderRow = function () use ($pdf, &$y, &$tableTop, $cols, $FONT, $navy) {
        $h = 6.5;
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($FONT, 'B', 7.5);
        $pdf->setCellPaddings(1.4, 0, 1.4, 0);
        foreach ($cols as $c) {
            $pdf->MultiCell($c['w'], $h, $c['label'], 0, $c['align'], true, 0, $c['x'], $y, true, 0, false, true, $h, 'M');
        }
        $y += $h;
        $tableTop = $y;
    };

    $newPage = function ($first = false) use ($pdf, &$y, $drawHeaderRow, $titleText, $isDg, $FONT, $pkgCount, $groupCount, $countLabel) {
        $pdf->AddPage();
        $y = 10;
        if ($first) {
            $pdf->SetTextColor(34, 34, 34);
            $pdf->SetFont($FONT, 'B', 13);
            $pdf->setCellPaddings(0, 0, 0, 0);
            $pdf->MultiCell(0, 6, $titleText, 0, 'C', false, 1, 10, $y);
            $y = $pdf->GetY() + 1;
            if ($isDg) {
                $label = '⚠ DANGEROUS GOODS';
                $pdf->SetFont($FONT, 'B', 7.5);
                $bw = $pdf->GetStringWidth($label) + 6;
                $bx = ($pdf->getPageWidth() - $bw) / 2;
                $pdf->SetFillColor(255, 109, 0);
                $pdf->RoundedRect($bx, $y, $bw, 4.6, 2.3, '1111', 'F');
                $pdf->SetTextColor(255, 255, 255);
                $pdf->MultiCell($bw, 4.6, $label, 0, 'C', false, 1, $bx, $y, true, 0, false, true, 4.6, 'M');
                $y += 5.6;
            }
            $pdf->SetTextColor(110, 120, 130);
            $pdf->SetFont($FONT, '', 8);
            $pdf->MultiCell(0, 4, $countLabel($pkgCount) . '  ·  ' . $groupCount . ' sender' . ($groupCount === 1 ? '' : 's'), 0, 'C', false, 1, 10, $y);
            $y = $pdf->GetY() + 2.5;
        }
        $drawHeaderRow();
    };

    // A segment is one sender's block on one page. It is buffered and drawn
    // when it closes, so the sender name can be centred on its final height.
    $seg = null;
    $openSegment = function ($g, $continued) use (&$seg, &$y, $senderHeight, $senderLines) {
        $lines = $senderLines($g, $continued);
        $seg = [
            'g'         => $g,
            'continued' => $continued,
            'lines'     => $lines,
            'need'      => $senderHeight($lines),
            'top'       => $y,
            'chunks'    => [],
        ];
    };
    $flushSegment = function ($groupEnds) use (&$seg, &$y, $pdf, $cols, $xEnd, $cell, $setLine, $FONT, $navy, $mid, $light, $tint, $ink) {
        if (!$seg || !$seg['chunks']) {
            return;
        }
        $top = $seg['top'];
        if ($y - $top < $seg['need']) {
            // Sender label is taller than its packages: the last item row takes the slack.
            $pad = $seg['need'] - ($y - $top);
            $lc  = count($seg['chunks']) - 1;
            $lr  = count($seg['chunks'][$lc]['rows']) - 1;
            $seg['chunks'][$lc]['rows'][$lr]['h'] += $pad;
            $y += $pad;
        }
        $segH = $y - $top;

        $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
        $pdf->Rect($cols['sender']['x'], $top, $cols['sender']['w'], $segH, 'F');

        $cy = $top;
        $nChunks = count($seg['chunks']);
        foreach ($seg['chunks'] as $ci => $ch) {
            $chH = 0;
            foreach ($ch['rows'] as $r) {
                $chH += $r['h'];
            }
            $ry = $cy;
            $nRows = count($ch['rows']);
            foreach ($ch['rows'] as $ri => $r) {
                $cell('qty', $ry, $r['h'], $r['qty'], '', 'M');
                $cell('desc', $ry, $r['h'], $r['desc'], '', 'M');
                $ry += $r['h'];
                if ($ri < $nRows - 1) {
                    $setLine(0.15, $light, '0.8,0.6');
                    $pdf->Line($cols['qty']['x'], $ry, $xEnd, $ry);
                }
            }
            $cell('sn', $cy, $chH, $ch['pkg']['sn'], 'B', 'M');
            $cell('swift', $cy, $chH, $ch['pkg']['swift'], '', 'M');
            $cell('carrier', $cy, $chH, $ch['pkg']['carrier'], '', 'M');
            $cy += $chH;
            if ($ci < $nChunks - 1) {
                $setLine(0.25, $mid);
                $pdf->Line($cols['sn']['x'], $cy, $xEnd, $cy);
            }
        }

        // Sender label, centred down the block.
        $textH = $seg['need'] - 2.4;
        $ty = $top + ($segH - $textH) / 2;
        foreach ($seg['lines'] as $li => $l) {
            $pdf->SetFont($FONT, $l[1], $l[2]);
            $pdf->setCellPaddings(1.6, 0.3, 1.6, 0.3);
            if ($l[1] === 'I') {
                $pdf->SetTextColor(110, 120, 130);
            } else {
                $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            }
            $lh = $pdf->getStringHeight($cols['sender']['w'], $l[0]);
            $pdf->MultiCell($cols['sender']['w'], $lh, $l[0], 0, 'L', false, 0, $cols['sender']['x'], $ty);
            $ty += $lh;
        }

        // Column rules, then the block's top and bottom edges.
        $setLine(0.25, $mid);
        foreach ($cols as $c) {
            $pdf->Line($c['x'], $top, $c['x'], $y);
        }
        $pdf->Line($xEnd, $top, $xEnd, $y);
        $setLine($seg['continued'] ? 0.25 : 0.6, $seg['continued'] ? $mid : $navy);
        $pdf->Line($cols['sender']['x'], $top, $xEnd, $top);
        $setLine($groupEnds ? 0.6 : 0.25, $groupEnds ? $navy : $mid);
        $pdf->Line($cols['sender']['x'], $y, $xEnd, $y);

        $seg = null;
    };

    $newPage(true);

    if (!$groups) {
        $pdf->SetFont($FONT, '', $FS);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->setCellPaddings($PAD[0], $PAD[1], $PAD[2], $PAD[3]);
        $setLine(0.25, $mid);
        $pdf->MultiCell($xEnd - 10, 7, 'No packages in this consolidation.', 1, 'L', false, 1, 10, $y, true, 0, false, true, 7, 'M');
    }

    foreach ($groups as $g) {
        $drawn = false;
        $openSegment($g, false);
        $nPkg = count($g['packages']);
        foreach ($g['packages'] as $pi => $pkg) {
            $isLast = ($pi === $nPkg - 1);
            $rows   = $pkg['items'];
            $start  = 0;
            $n      = count($rows);
            while ($start < $n) {
                $remH = 0;
                for ($i = $start; $i < $n; $i++) {
                    $remH += $rows[$i]['h'];
                }
                // A segment that may close after this package must leave room
                // for the sender label.
                $segH = $y - $seg['top'];
                $need = ($isLast || !$seg['chunks']) ? max($remH, $seg['need'] - $segH) : $remH;
                $avail = $bottom - $y;

                if ($need <= $avail) {
                    $take = $n - $start;
                } elseif ($y > $tableTop + 0.01) {
                    // Does not fit here: carry the rest of the block to a new page.
                    $flushSegment(false);
                    $newPage();
                    $openSegment($g, $drawn);
                    continue;
                } else {
                    // Taller than a whole page: split between items.
                    $take = 0;
                    $acc  = 0;
                    for ($i = $start; $i < $n; $i++) {
                        if ($acc + $rows[$i]['h'] > $avail && $take > 0) {
                            break;
                        }
                        $acc += $rows[$i]['h'];
                        $take++;
                    }
                }

                $chunkRows = array_slice($rows, $start, $take);
                $seg['chunks'][] = ['pkg' => $pkg, 'rows' => $chunkRows];
                foreach ($chunkRows as $r) {
                    $y += $r['h'];
                }
                $drawn  = true;
                $start += $take;
                if ($start < $n) {
                    $flushSegment(false);
                    $newPage();
                    $openSegment($g, true);
                }
            }
        }
        $flushSegment(true);
    }

    $out = $pdf->Output($fileBase . '.pdf', 'S');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileBase . '.pdf"');
    echo $out;
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Could not generate PDF: ' . $esc($e->getMessage());
}
