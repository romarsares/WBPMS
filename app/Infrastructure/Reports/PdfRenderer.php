<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Reports;

use Dompdf\Dompdf;
use RuntimeException;

/**
 * PdfRenderer — thin adapter around Dompdf for server-side PDF generation.
 *
 * Used by ReportsController (report exports) and EmployeePortalController
 * (payslip downloads) to produce real downloadable PDF documents instead of
 * relying on the browser's "Save as PDF" print dialog.
 *
 * Dates in generated PDFs follow the project standard: MM/DD/YY.
 */
final class PdfRenderer
{
    /**
     * Render the given HTML into a PDF and stream it to the browser as a
     * downloadable attachment.
     *
     * @param string $html      Full HTML document (with inline styles).
     * @param string $filename  Download file name (without extension if desired;
     *                          .pdf is appended automatically when missing).
     */
    public static function download(string $html, string $filename): void
    {
        $pdf  = self::createDompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $safe = self::safeFilename($filename);
        $pdf->stream($safe, ['Attachment' => true]);
    }

    /**
     * Render the given HTML into a PDF and stream it inline (for in-browser
     * preview).  The browser shows the PDF in its native viewer.
     *
     * @param string $html      Full HTML document (with inline styles).
     * @param string $filename  Suggested file name for the download icon.
     */
    public static function inline(string $html, string $filename): void
    {
        $pdf  = self::createDompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $safe = self::safeFilename($filename);
        $pdf->stream($safe, ['Attachment' => false]);
    }

    /**
     * Render the given HTML into a PDF and return the raw PDF bytes.
     *
     * Useful for tests or when the caller needs to post-process the output
     * before sending it to the client.
     */
    public static function renderToString(string $html): string
    {
        $pdf = self::createDompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    /**
     * Format a date string (or DateTimeInterface) as MM/DD/YY for PDF output.
     *
     * Centralised here so all generated PDFs use the project-standard date
     * format regardless of the underlying data source format.
     */
    public static function formatDate(\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = is_string($value) ? new \DateTimeImmutable($value) : $value;

        return $date->format('m/d/Y');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Create a configured Dompdf instance.
     *
     * The font cache and temporary directories live under storage/private/
     * so they stay outside the web root (ADR-0003 upload/storage rules).
     */
    private static function createDompdf(): Dompdf
    {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        // Use storage/private for Dompdf temp files (never public).
        $storageDir = defined('APP_ROOT')
            ? APP_ROOT . '/storage/private/dompdf'
            : sys_get_temp_dir() . '/wbpms-dompdf';

        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        $options->set('tempDir', $storageDir);
        $options->set('fontCache', $storageDir);

        return new Dompdf($options);
    }

    /**
     * Sanitise a filename and ensure it ends with .pdf.
     */
    private static function safeFilename(string $filename): string
    {
        $name = trim($filename);

        // Strip any path components.
        $name = basename($name);

        // Replace whitespace and special characters with underscores.
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'document';

        // Collapse repeated underscores.
        $name = preg_replace('/_+/', '_', $name) ?? $name;

        // Ensure .pdf extension.
        if (!str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return $name;
    }
}
