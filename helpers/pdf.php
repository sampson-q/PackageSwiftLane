<?php
/**
 * Wrapper PDF - Compatible PHP 8.4
 * Usa vendor/spipu/html2pdf + tecnickcom/tcpdf (Composer).
 * Sustituye el uso de pdf/html2pdf.class.php + pdf/_tcpdf_5.0.002 (each/create_function).
 */

if (!defined('DEPRIXAPRO_PDF_LOADED')) {
    if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    }
    define('DEPRIXAPRO_PDF_LOADED', true);
}

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

/**
 * Genera PDF desde HTML usando Spipu Html2Pdf (TCPDF 6.x). Compatible PHP 8.4.
 *
 * @param string $html      Contenido HTML
 * @param string $filename  Nombre sugerido para el archivo (solo informativo en options)
 * @param array  $options   orientation (P/L), format (LETTER/A4), lang, margins
 * @return string PDF binario (para Output('', 'S'))
 * @throws RuntimeException si falla la generación
 */
function deprixapro_render_html_to_pdf($html, $filename = '', array $options = [])
{
    $orientation = $options['orientation'] ?? 'P';
    $format      = $options['format'] ?? 'LETTER';
    $lang        = $options['lang'] ?? 'es';
    $unicode     = $options['unicode'] ?? true;
    $encoding    = $options['encoding'] ?? 'UTF-8';
    $margins     = $options['margins'] ?? [0, 0, 0, 0];

    try {
        $html2pdf = new Html2Pdf($orientation, $format, $lang, $unicode, $encoding, $margins, false);
        $html2pdf->writeHTML($html);
        return $html2pdf->output('', 'S');
    } catch (Html2PdfException $e) {
        $formatter = new ExceptionFormatter($e);
        throw new \RuntimeException('PDF: ' . $formatter->getHtmlMessage(), (int) $e->getCode(), $e);
    }
}
