<?php
/**
 * Shared thermal-label stylesheet.
 *
 * Emits the <style> block used by every shipping/consolidation/package box
 * label so they all render exactly like print_label_ship.php. Two physical
 * sizes are supported and selected by $label_size (default 'normal'):
 *
 *   normal  -> 102 x 152 mm  (4x6")  full box label
 *   small   ->  50.8 x 25.4 mm (2x1") gadget label (phones etc.) — carries just
 *              the locker ID, system tracking and one scannable barcode so the
 *              sticker is small enough not to cover / damage a retail box.
 *
 * @var string $label_size  'normal' | 'small'  (set by the including view)
 */
$label_size = (isset($label_size) && $label_size === 'small') ? 'small' : 'normal';
?>
<style>
    <?php if ($label_size === 'small') : ?>
    @page { size: 50.8mm 25.4mm; margin: 0; }
    <?php else : ?>
    @page { size: 102mm 152mm; margin: 0; }
    <?php endif; ?>

    * { box-sizing: border-box; }
    html, body {
        margin: 0; padding: 0;
        background: #fff; color: #000;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }

    /* One label = one physical page. */
    .label { page-break-after: always; break-after: page; }
    .label:last-of-type { page-break-after: auto; break-after: auto; }

    /* ─────────────────────────── NORMAL 4x6" label ─────────────────────── */
    body.size-normal { width: 102mm; }
    .size-normal .label { width: 102mm; height: 152mm; padding: 2.5mm; display: flex; flex-direction: column; }

    /* Everything sits inside this border */
    .size-normal .panel {
        border: 2.5px solid #000; flex: 1 1 auto;
        display: flex; flex-direction: column; overflow: hidden;
    }

    /* Top: logo + sender contact block */
    .size-normal .top { display: flex; align-items: center; justify-content: space-between; max-height: 80px; min-height: 80px; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .top .logo { width: 40%; text-align: center; }
    .size-normal .top .logo img { max-width: 100%; height: auto; max-height: 18mm; }
    .size-normal .top .logo .fallback { font-size: 13pt; font-weight: 800; letter-spacing: .5px; }
    .size-normal .top .qr { text-align: center; }
    .size-normal .top .qr img { width: 15mm; height: 15mm; }
    .size-normal .top .qr small { display: block; font-size: 6pt; font-weight: 800; color: #000; margin-top: .3mm; letter-spacing: .3px; }
    .size-normal .top .contact { text-align: center; font-size: 7.5pt; font-weight: 700; color: #000; line-height: 1.3; }
    .size-normal .top .contact strong { font-size: 9pt; font-weight: 800; }

    /* Title band: system / package tracking */
    .size-normal .title { background: #000; color: #fff; text-align: center; padding: 1.6mm 2mm; }
    .size-normal .title .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
    .size-normal .title .v { font-size: 16pt; font-weight: 800; letter-spacing: 1px; line-height: 1.05; }

    /* Shipment facts row (no monetary values) */
    .size-normal .facts { display: flex; gap: 1.5mm; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .facts .f { flex: 1; border: 1.5px solid #000; padding: 1.4mm 1mm; text-align: center; }
    .size-normal .facts .f .k { font-size: 6pt; font-weight: 800; text-transform: uppercase; color: #000; letter-spacing: .3px; }
    .size-normal .facts .f .v { font-size: 9pt; font-weight: 800; line-height: 1.15; }

    /* Sender / Recipient block */
    .size-normal .addr-block { padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .addr { margin-bottom: 1.5mm; }
    .size-normal .addr:last-child { margin-bottom: 0; }
    .size-normal .addr .role { font-size: 7pt; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #000; border-bottom: 1.5px solid #000; padding-bottom: .6mm; margin-bottom: 1mm; }
    .size-normal .addr.from .name { font-size: 13pt; font-weight: 800; }
    .size-normal .addr.from .line { font-size: 10pt; font-weight: 700; line-height: 1.3; }
    .size-normal .addr.to .name { font-size: 13pt; font-weight: 800; line-height: 1.15; }
    .size-normal .addr.to .line { font-size: 10pt; font-weight: 700; line-height: 1.3; }

    /* Bottom: both barcodes, stacked in rows, filling the space */
    .size-normal .bar { border-top: 1.5px solid #000; padding: 1.8mm 2.5mm 2mm; text-align: center; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    .size-normal .bar .track-row { display: flex; flex-direction: column; gap: 1.5mm; flex: 1 1 auto; min-height: 0; overflow: hidden; }
    .size-normal .bar .track-row.single { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    .size-normal .bar .track { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
    .size-normal .bar .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; flex: 0 0 auto; }
    .size-normal .bar img { display: block; margin: 0 auto; flex: 1 1 auto; min-height: 0; height: 100%; width: auto; max-width: 100%; object-fit: contain; }
    .size-normal .bar .num { font-size: 8pt; font-weight: 800; letter-spacing: 1px; }
    .size-normal .bar .divider { height: 1.5px; width: 100%; background: #000; flex: 0 0 auto; }

    /* ─────────────────────────── SMALL 2x1" label ──────────────────────── */
    body.size-small { width: 50.8mm; }
    .size-small .label { width: 50.8mm; height: 25.4mm; padding: 0.6mm; display: flex; flex-direction: column; }
    .size-small .panel {
        border: 1.5px solid #000; flex: 1 1 auto;
        display: flex; flex-direction: column; overflow: hidden;
        padding: 0.6mm 1mm;
    }
    /* Row 1: locker (big, left) + brand (right) */
    .size-small .s-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1mm; line-height: 1; }
    .size-small .s-head .locker { font-size: 8pt; font-weight: 800; letter-spacing: .2px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .size-small .s-head .locker .lk { font-size: 5pt; font-weight: 800; }
    .size-small .s-head .brand { font-size: 5pt; font-weight: 800; text-transform: uppercase; text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 22mm; }
    /* Barcode: the one scannable code, spanning the width */
    .size-small .s-bar { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; justify-content: center; text-align: center; margin-top: .4mm; }
    .size-small .s-bar img { display: block; margin: 0 auto; height: 100%; max-height: 8mm; width: 100%; max-width: 100%; object-fit: contain; }
    .size-small .s-bar .num { font-size: 6pt; font-weight: 800; letter-spacing: .5px; line-height: 1.05; }
    .size-small .s-bar .num .bk { font-size: 4.5pt; font-weight: 800; }
    /* Row 3: the secondary identifier line */
    .size-small .s-foot { display: flex; align-items: baseline; justify-content: space-between; gap: 1mm; font-size: 5.5pt; font-weight: 800; line-height: 1.05; }
    .size-small .s-foot .lbl { font-size: 4.5pt; font-weight: 800; text-transform: uppercase; }

    /* Screen-only chrome (size switch + print button) */
    .print-toolbar { text-align: center; margin-top: 4mm; }
    .print-toolbar .size-switch { display: inline-flex; border: 1px solid #333; border-radius: 6px; overflow: hidden; margin-right: 8px; vertical-align: middle; }
    .print-toolbar .size-switch a { padding: 8px 16px; font-size: 14px; text-decoration: none; color: #333; background: #fff; }
    .print-toolbar .size-switch a.active { background: #333; color: #fff; }
    .print-toolbar button { padding: 9px 22px; font-size: 15px; cursor: pointer; }

    @media print {
        .print-toolbar { display: none !important; }
    }
</style>
