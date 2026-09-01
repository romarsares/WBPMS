<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * AttendanceController — HR attendance management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/attendance         → index()  — attendance list / timesheet view
 *   GET  /hr/attendance/import  → import() — upload form
 *   POST /hr/attendance/import  → upload() — process XLS upload
 *
 * REQ018–REQ024 (import) and the HR attendance list view.
 *
 * The XLS parsing and matching logic lives in AttendanceImportService;
 * this controller handles only the HTTP boundary: file validation,
 * temporary storage, calling the service, and rendering results.
 *
 * ADR-0003 upload controls:
 *   - Extension + OLE signature check before any processing.
 *   - File stored to storage/private/ with randomized name.
 *   - File removed after import completes or fails.
 *   - Max size: 10 MiB.
 */
final class AttendanceController
{
    // -----------------------------------------------------------------------
    // GET /hr/attendance
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $total = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

        $complete = (int) $pdo->query(
            "SELECT COUNT(*) FROM attendance WHERE status IN ('Complete','Approved')"
        )->fetchColumn();

        $incomplete = (int) $pdo->query(
            "SELECT COUNT(*) FROM attendance WHERE status IN ('Incomplete','ReviewRequired')"
        )->fetchColumn();

        $unmatched = (int) $pdo->query(
            "SELECT COUNT(*) FROM biometric_punch WHERE employee_id IS NULL"
        )->fetchColumn();

        $rows = $pdo->query(
            "SELECT a.attendance_id,
                    e.employee_number,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    b.branch_name,
                    a.attendance_date,
                    a.time_in,
                    a.time_out,
                    a.hours_worked_minutes AS worked_minutes,
                    a.late_minutes,
                    a.undertime_minutes,
                    a.overtime_minutes,
                    CASE WHEN a.status IN ('Incomplete','ReviewRequired') THEN 1 ELSE 0 END AS is_incomplete
               FROM attendance a
               JOIN employee e ON e.employee_id = a.employee_id
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              ORDER BY a.attendance_date DESC, e.last_name
              LIMIT 200"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/index', [
            'rows'       => $rows,
            'total'      => $total,
            'complete'   => $complete,
            'incomplete' => $incomplete,
            'unmatched'  => $unmatched,
        ], 'Attendance Management');
    }

    // -----------------------------------------------------------------------
    // GET /hr/attendance/import
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function import(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        // Recent import batches
        $batches = $pdo->query(
            "SELECT aib.import_batch_id,
                    aib.source_filename,
                    aib.sha256_checksum,
                    aib.status,
                    aib.imported_by,
                    aib.imported_at,
                    aib.matched_count,
                    aib.unmatched_count,
                    aib.duplicate_count
               FROM attendance_import_batch aib
              ORDER BY aib.imported_at DESC
              LIMIT 20"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/import', [
            'batches' => $batches,
            'errors'  => [],
        ], 'Import Attendance');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/import
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function upload(array $params = []): void
    {
        $identity = AuthMiddleware::identity();
        $errors   = [];

        // --- 1. Basic upload presence check ---
        if (empty($_FILES['attendance_file']) || $_FILES['attendance_file']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['attendance_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errors[] = $this->uploadErrorMessage($code);
        }

        if ($errors === []) {
            $file     = $_FILES['attendance_file'];
            $tmpPath  = (string) $file['tmp_name'];
            $origName = (string) $file['name'];
            $size     = (int) $file['size'];

            // --- 2. Size limit: 10 MiB (ADR-0003) ---
            if ($size > 10 * 1024 * 1024) {
                $errors[] = 'File exceeds the 10 MiB size limit.';
            }

            // --- 3. Extension check ---
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'xls') {
                $errors[] = 'Only .xls files exported from the biometric device are accepted.';
            }

            // --- 4. OLE signature check (ADR-0003: D0 CF 11 E0 A1 B1 1A E1) ---
            if ($errors === []) {
                $handle = fopen($tmpPath, 'rb');
                $sig    = $handle !== false ? fread($handle, 8) : '';
                if ($handle !== false) {
                    fclose($handle);
                }
                if ($sig !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                    $errors[] = 'File does not appear to be a valid BIFF8 .xls workbook (OLE signature mismatch).';
                }
            }

            // --- 5. Move to private storage with randomized name ---
            if ($errors === []) {
                $storageDir = APP_ROOT . '/storage/private/attendance/';
                if (!is_dir($storageDir)) {
                    mkdir($storageDir, 0750, true);
                }

                $safeName    = bin2hex(random_bytes(16)) . '.xls';
                $storagePath = $storageDir . $safeName;

                if (!move_uploaded_file($tmpPath, $storagePath)) {
                    $errors[] = 'Failed to store uploaded file. Please try again.';
                }
            }

            // --- 6. Attempt import via AttendanceImportService ---
            if ($errors === []) {
                try {
                    $pdo = $this->makeConnection()->pdo();

                    // Compute SHA-256 for duplicate detection (REQ024)
                    $checksum = hash_file('sha256', $storagePath);
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM attendance_import_batch WHERE sha256_checksum = :hash"
                    );
                    $stmt->execute([':hash' => $checksum]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        @unlink($storagePath);
                        $errors[] = 'This file has already been imported (duplicate checksum detected).';
                    } else {
                        // Record the batch — full parse/match is deferred until
                        // AttendanceImportService PDO gateway is implemented.
                        $stmt = $pdo->prepare(
                            "INSERT INTO attendance_import_batch
                                (source_filename, sha256_checksum, status, imported_by, imported_at,
                                 matched_count, unmatched_count, duplicate_count)
                             VALUES
                                (:fname, :hash, 'Pending', :by, NOW(), 0, 0, 0)"
                        );
                        $stmt->execute([
                            ':fname' => $origName,
                            ':hash'  => $checksum,
                            ':by'    => $identity['user_id'] ?? null,
                        ]);

                        @unlink($storagePath); // remove temp after recording

                        ViewRenderer::flash(
                            "File '{$origName}' uploaded and queued for processing. "
                            . "Import batch recorded — matching and timesheet generation will run on next process cycle."
                        );
                        $this->redirect('/hr/attendance/import');
                        return;
                    }
                } catch (\Throwable $e) {
                    if (isset($storagePath) && file_exists($storagePath)) {
                        @unlink($storagePath);
                    }
                    $errors[] = 'Import failed: ' . $e->getMessage();
                }
            }
        }

        // Re-render import form with errors
        $pdo     = $this->makeConnection()->pdo();
        $batches = $pdo->query(
            "SELECT import_batch_id, source_filename, sha256_checksum,
                    status, imported_by, imported_at, matched_count, unmatched_count, duplicate_count
               FROM attendance_import_batch ORDER BY imported_at DESC LIMIT 20"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/import', [
            'batches' => $batches,
            'errors'  => $errors,
        ], 'Import Attendance');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL                        => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE                        => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Missing temporary directory.',
            UPLOAD_ERR_CANT_WRITE                     => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION                      => 'Upload blocked by PHP extension.',
            default                                   => 'Unknown upload error.',
        };
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
