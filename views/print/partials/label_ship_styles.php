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
 *              the locker ID, one scannable barcode and the tracking number(s)
 *              so the sticker is small enough not to cover / damage a retail box.
 *
 * On screen the label is presented as a centred sheet on a neutral backdrop
 * with a fixed toolbar; @media print strips all of that and prints the bare
 * label at its exact @page size.
 *
 * @var string $label_size  'normal' | 'small'
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
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }

    /* The physical sheet — identical on screen and paper. */
    .label { background: #fff; }

    /* ── Screen presentation only (hidden/neutralised for print) ─────────── */
    @media screen {
        body {
            background: #eceef1;
            min-height: 100vh;
            padding: 88px 20px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .label {
            box-shadow: 0 6px 22px rgba(0, 0, 0, .22);
            border-radius: 1px;
        }
    }

    /* ─────────────────────────── NORMAL 4x6" label ─────────────────────── */
    .size-normal .label { width: 102mm; height: 152mm; padding: 2.5mm; display: flex; flex-direction: column; }

    .size-normal .panel {
        border: 2.5px solid #000; flex: 1 1 auto;
        display: flex; flex-direction: column; overflow: hidden;
    }

    /* Top: logo + sender contact block */
    .size-normal .top { display: flex; align-items: center; justify-content: space-between; max-height: 80px; min-height: 80px; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .top .logo { width: 40%; text-align: center; }
    .size-normal .top .logo img { max-width: 100%; height: auto; max-height: 18mm; }
    .size-normal .top .logo .fallback { font-size: 13pt; font-weight: 800; letter-spacing: .5px; }
    .size-normal .top .contact { text-align: center; font-size: 7.5pt; font-weight: 700; color: #000; line-height: 1.3; }
    .size-normal .top .contact strong { font-size: 9pt; font-weight: 800; }

    /* Title band: system / package tracking. Wraps inside the band so a long
       number can never bleed past the border. */
    .size-normal .title { background: #000; color: #fff; text-align: center; padding: 1.5mm 2.5mm; overflow: hidden; }
    .size-normal .title .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
    .size-normal .title .v { font-size: 15pt; font-weight: 800; letter-spacing: .4px; line-height: 1.1; word-break: break-all; overflow-wrap: anywhere; }

    /* Shipment facts row (no monetary values) */
    .size-normal .facts { display: flex; gap: 1.5mm; padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .facts .f { flex: 1; border: 1.5px solid #000; padding: 1.4mm 1mm; text-align: center; min-width: 0; }
    .size-normal .facts .f .k { font-size: 6pt; font-weight: 800; text-transform: uppercase; color: #000; letter-spacing: .3px; }
    .size-normal .facts .f .v { font-size: 9pt; font-weight: 800; line-height: 1.15; word-break: break-word; }

    /* Sender / Recipient block */
    .size-normal .addr-block { padding: 2mm 2.5mm; border-bottom: 2.5px solid #000; }
    .size-normal .addr { margin-bottom: 1.5mm; }
    .size-normal .addr:last-child { margin-bottom: 0; }
    .size-normal .addr .role { font-size: 7pt; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #000; border-bottom: 1.5px solid #000; padding-bottom: .6mm; margin-bottom: 1mm; }
    .size-normal .addr.from .name { font-size: 13pt; font-weight: 800; word-break: break-word; }
    .size-normal .addr.from .line { font-size: 10pt; font-weight: 700; line-height: 1.3; word-break: break-word; }
    .size-normal .addr.to .name { font-size: 13pt; font-weight: 800; line-height: 1.15; word-break: break-word; }
    .size-normal .addr.to .line { font-size: 10pt; font-weight: 700; line-height: 1.3; word-break: break-word; }

    /* Bottom: system + courier barcodes, stacked, filling the space */
    .size-normal .bar { border-top: 1.5px solid #000; padding: 1.8mm 2.5mm 2mm; text-align: center; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    .size-normal .bar .track-row { display: flex; flex-direction: column; gap: 1.5mm; flex: 1 1 auto; min-height: 0; overflow: hidden; }
    .size-normal .bar .track { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
    .size-normal .bar .k { font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; flex: 0 0 auto; }
    .size-normal .bar img { display: block; margin: 0 auto; flex: 1 1 auto; min-height: 0; height: 100%; width: auto; max-width: 100%; object-fit: contain; }
    .size-normal .bar .divider { height: 1.5px; width: 100%; background: #000; flex: 0 0 auto; }

    /* ─────────────────────────── SMALL 2x1" label ──────────────────────── */
    .size-small .label { width: 50.8mm; height: 25.4mm; padding: 0.5mm; display: flex; flex-direction: column; }
    .size-small .panel {
        border: 1.4px solid #000; flex: 1 1 auto;
        display: flex; flex-direction: column; gap: 0.6mm;
        padding: 0.9mm 1.3mm; overflow: hidden;
    }
    .size-small .s-cap { display: block; font-size: 4pt; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; line-height: 1; }

    /* Row 1: locker (left, prominent) + brand (right) */
    .size-small .s-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5mm; }
    .size-small .s-locker { min-width: 0; }
    .size-small .s-locker .s-val { display: block; font-size: 9.5pt; font-weight: 800; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .size-small .s-brand { font-size: 4.5pt; font-weight: 800; text-transform: uppercase; text-align: right; line-height: 1.15; max-width: 20mm; overflow: hidden; }

    /* Barcode: the one scannable code, filling the middle, with its number */
    .size-small .s-barwrap { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .size-small .s-barcode { display: block; height: 100%; max-height: 9mm; width: 100%; object-fit: contain; }
    .size-small .s-code { font-size: 7pt; font-weight: 800; letter-spacing: .4px; line-height: 1.05; margin-top: .3mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

    /* Row 3: system tracking number (only when a postal barcode is shown) */
    .size-small .s-sys { display: flex; align-items: baseline; gap: 1mm; line-height: 1; }
    .size-small .s-sys .s-cap { flex: 0 0 auto; }
    .size-small .s-sys .s-sval { font-size: 6.5pt; font-weight: 800; letter-spacing: .3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Screen toolbar (size switch + print). Fixed top, hidden on print. ── */
    .print-toolbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 50;
        display: flex; align-items: center; justify-content: center; gap: 14px;
        padding: 12px 16px;
        background: #ffffff; border-bottom: 1px solid #d7dbe0;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .08);
        font-family: Arial, Helvetica, sans-serif;
    }
    .print-toolbar .size-switch { display: inline-flex; border: 1px solid #c4c9d0; border-radius: 8px; overflow: hidden; }
    .print-toolbar .size-switch a { padding: 8px 18px; font-size: 13px; font-weight: 700; text-decoration: none; color: #3a4149; background: #fff; }
    .print-toolbar .size-switch a + a { border-left: 1px solid #c4c9d0; }
    .print-toolbar .size-switch a.active { background: #1f6feb; color: #fff; }
    .print-toolbar button { padding: 9px 20px; font-size: 14px; font-weight: 700; color: #fff; background: #111418; border: none; border-radius: 8px; cursor: pointer; }
    .print-toolbar button:hover { background: #000; }

    @media print {
        html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; display: block !important; }
        .print-toolbar { display: none !important; }
        .label { box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; }
        /* one label = one physical page */
        .label { page-break-after: always; break-after: page; }
        .label:last-of-type { page-break-after: auto; break-after: auto; }
    }
</style>
