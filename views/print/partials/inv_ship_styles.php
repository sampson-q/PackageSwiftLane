<?php
/**
 * Shared invoice/receipt stylesheet — the exact print_inv_ship.php design so
 * every receipt (shipment, consolidation, customer package, charge) matches.
 * ~110mm thermal roll, content-driven height, monochrome-friendly.
 */
?>
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

    * { box-sizing: border-box; }

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

    .topbar { border-bottom: 0.35mm solid #000; padding-bottom: 2.2mm; margin-bottom: 2.2mm; }
    .brand-wrap { display: block; }
    .logo { text-align: center; margin-bottom: -15.5mm; margin-top: -15mm; }
    .logo img { display: inline-block; max-width: 120mm; max-height: 54mm; object-fit: contain; }
    .brand-text { min-width: 0; text-align: center; }
    .brand-name { margin: 0 0 1mm 0; font-size: 19px; font-weight: 700; line-height: 1.1; }
    .brand-lines { margin: 0; font-size: 16px; line-height: 1.35; word-break: break-word; }

    .barcode { text-align: center; margin-top: 1.5mm; }
    .barcode img { display: inline-block; width: 100%; max-width: 80mm; height: 20mm; object-fit: contain; }

    .info-grid { display: grid; grid-template-columns: 1fr; gap: 2mm; }
    .panel { border: 0.35mm solid #000; padding: 1.5mm 1.8mm; overflow: visible; }
    .panel-title { margin: 0 0 1mm 0; padding-bottom: 0.8mm; border-bottom: 0.25mm solid #000; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2px; }

    .kv { display: grid; grid-template-columns: 19mm 1fr; gap: 1.2mm; margin: 0 0 0.8mm 0; font-size: 15px; line-height: 1.35; }
    .kv .k { font-weight: 700; white-space: nowrap; }
    .kv .v { min-width: 0; word-break: break-word; }
    .sn-row { align-items: baseline; }
    .sn-value { font-size: 26px; font-weight: 800; line-height: 1; }
    .sn-total { font-size: 15px; font-weight: 700; }

    .items-panel { border: 0.35mm solid #000; padding: 1.2mm 1.4mm; overflow: visible; min-height: 0; }
    .items-header { display: flex; align-items: center; justify-content: space-between; gap: 2mm; margin-bottom: 1mm; }
    .items-header h3 { margin: 0; font-size: 14.5px; font-weight: 700; line-height: 1.1; }
    .meta-chip { font-size: 14px; white-space: nowrap; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead th { background: #f0f0f0; font-size: 16px; font-weight: 700; }
    th, td { border: 0.2mm solid #000; padding: 0.75mm 1mm; vertical-align: top; word-break: break-word; }
    td.qty { width: 16mm; text-align: center; font-weight: 700; }
    td.desc { width: auto; }
    tfoot td { font-size: 16px; padding: 0.8mm 1mm; background: #fafafa; }

    .footer { display: grid; grid-template-columns: 1fr; gap: 2mm; align-items: start; }
    .total-box { border: 0.35mm solid #000; padding: 1.5mm; text-align: center; }
    .total-box label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 1mm; text-transform: uppercase; }
    .total-box .value { font-size: 23px; font-weight: 700; line-height: 1; }

    /* PAID stamp. Black-only by design (monochrome thermal). */
    .paid-stamp { border: 0.6mm solid #000; padding: 1.5mm; text-align: center; margin-bottom: 2mm; }
    .paid-mark { font-size: 26px; font-weight: 700; letter-spacing: 2mm; line-height: 1; text-transform: uppercase; }
    .paid-detail { font-size: 12px; line-height: 1.35; margin-top: 1mm; }

    .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; align-items: end; }
    .signature { text-align: center; font-size: 14px; line-height: 1.2; }
    .signature .line { margin-top: 43mm; border-top: 0.3mm solid #000; padding-top: 1mm; }

    .credits { text-align: center; font-size: 12px; margin-top: 5mm; }

    .print-button { text-align: center; margin: 2mm 0 0 0; }
    .print-button button { padding: 10px 24px; font-size: 20px; cursor: pointer; }
    .print-info { margin-top: 1mm; font-size: 14.5px; color: #000; }

    /* Stronger contrast for thermal printing */
    body, .label-page, .panel, .items-panel, .total-box, .signature, .credits,
    .topbar, .brand-text, .brand-lines, .brand-name, .panel-title, .items-header h3,
    .meta-chip, .kv, .kv .k, .kv .v, table, thead th, th, td, tfoot td,
    .paid-stamp, .paid-mark, .paid-detail { color: #000 !important; }

    .panel, .items-panel, .total-box, .paid-stamp { border-color: #000 !important; }
    .topbar, .panel-title, th, td, tfoot td, .total-box { border-color: #000 !important; }

    .brand-name, .panel-title, .items-header h3, .total-box label, .kv .k,
    .signature .line, .print-info { font-weight: 700 !important; }
    .brand-lines, .kv .v, td, th, tfoot td, .credits, .meta-chip { font-weight: 600 !important; }

    .logo img, .barcode img { image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; }

    .financial-sn {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 28px; height: 28px; padding: 0 8px;
        border: 2px solid #000; border-radius: 999px;
        background: #fff; color: #000 !important;
        font-weight: 800; font-size: 15px; line-height: 1; white-space: nowrap;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }

    @media print {
        html, body {
            width: 100% !important; height: auto !important; min-height: 0 !important;
            margin: 0 !important; padding: 0 !important; overflow: visible !important;
            background: #fff !important; color: #000 !important;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            -webkit-text-size-adjust: 100% !important; text-size-adjust: 100% !important;
        }
        * { color: #000 !important; text-shadow: none !important; box-shadow: none !important; filter: none !important; }
        .label-page {
            width: 100% !important; max-width: 110mm !important; height: auto !important;
            min-height: 0 !important; padding: 4mm 3.5mm 5mm !important; overflow: visible !important;
        }
        .topbar, .panel, .items-panel, .total-box, .signature .line, th, td, tfoot td { border-color: #000 !important; }
        .print-button, .print-info { display: none !important; }
        a[href]:after { content: ""; }
        /* one receipt per page in bulk */
        .label-page { page-break-after: always; break-after: page; }
        .label-page:last-of-type { page-break-after: auto; break-after: auto; }
    }
</style>
