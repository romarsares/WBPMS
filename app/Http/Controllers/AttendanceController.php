<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use DateTimeZone;
use RuntimeException;
use Wbpms\Application\AttendanceAdjustmentService;
use Wbpms\Application\Attendance\AttendanceImportService;
use Wbpms\Application\Attendance\DuplicateAttendanceFileException;
use Wbpms\Domain\Attendance\Parsing\AttendanceParseException;
use Wbpms\Domain\Attendance\Parsing\LdeXlsDailyLogParser;
use Wbpms\Domain\Attendance\Parsing\ParserContext;
use Wbpms\Domain\Attendance\Parsing\UploadedAttendanceFile;
use Wbpms\Domain\Attendance\TimesheetGenerator;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\PdoAttendanceImportGateway;

/**
 * AttendanceController — HR attendance management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/attendance         → index()  — attendance list / timesheet view
 *   GET  /hr/attendance/import  → import() — upload form with recent batches
 *   POST /hr/attendance/import  → upload() — process XLS upload end-to-end
 *   GET  /hr/attendance/{id}/adjust  → adjustment form
 *   POST /hr/attendance/{id}/adjust → persist an audited correction
 *
 * REQ018–REQ024 (import) and the HR attendance list view.
 *
 * ADR-0003 upload controls applied in upload():
 *   - Extension + OLE signature check before any processing.
 *   - File stored to storage/private/attendance/ with randomized name.
 *   - File removed in finally block after import completes or fails.
 *   - Max size: 10 MiB.
 *   - SHA-256 duplicate detection via AttendanceImportService/gateway.
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

        // ---------------------------------------------------------------
        // Filter resolution
        //
        // Priority: period_id (cut-off) > month > (default: current month)
        // ---------------------------------------------------------------
        $periodId   = isset($_GET['period_id']) && $_GET['period_id'] !== ''
                      ? (int) $_GET['period_id'] : null;
        $monthInput = trim((string) ($_GET['month'] ?? ''));
        $tabHint    = (string) ($_GET['_tab'] ?? '');

        // Load all payroll periods for the cut-off dropdown
        $periods = $pdo->query(
            "SELECT payroll_period_id, period_start, period_end, pay_date, status
               FROM payroll_period
              ORDER BY period_start DESC"
        )->fetchAll();

        // Resolve date range
        $dateFrom  = null;
        $dateTo    = null;
        $activeTab = 'month'; // 'month' | 'cutoff'

        if ($periodId !== null) {
            // Cut-off filter
            $stmt = $pdo->prepare(
                "SELECT period_start, period_end FROM payroll_period WHERE payroll_period_id = :id"
            );
            $stmt->execute([':id' => $periodId]);
            $period = $stmt->fetch();
            if ($period) {
                $dateFrom  = $period['period_start'];
                $dateTo    = $period['period_end'];
                $activeTab = 'cutoff';
            } else {
                $periodId = null; // invalid id — fall through to month
            }
        }

        if ($dateFrom === null) {
            // Month filter — default to current Manila month
            $manilaNow = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
            if ($monthInput !== '' && preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $monthInput)) {
                [$yr, $mo] = array_map('intval', explode('-', $monthInput));
            } else {
                $yr = (int) $manilaNow->format('Y');
                $mo = (int) $manilaNow->format('m');
                $monthInput = $manilaNow->format('Y-m');
            }
            $dateFrom  = sprintf('%04d-%02d-01', $yr, $mo);
            $dateTo    = (new \DateTimeImmutable($dateFrom))->modify('last day of this month')->format('Y-m-d');
            // Stay on cutoff tab if that's what the user selected, even with no period chosen
            $activeTab = $tabHint === 'cutoff' ? 'cutoff' : 'month';
        }

        // ---------------------------------------------------------------
        // Summary counts — scoped to the same date range
        // ---------------------------------------------------------------
        $countStmt = $pdo->prepare(
            "SELECT
                COUNT(*)                                                           AS total,
                SUM(CASE WHEN status IN ('Complete','Approved')     THEN 1 ELSE 0 END) AS complete,
                SUM(CASE WHEN status IN ('Incomplete','ReviewRequired') THEN 1 ELSE 0 END) AS incomplete
               FROM attendance
              WHERE attendance_date BETWEEN :from AND :to"
        );
        $countStmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $counts     = $countStmt->fetch();
        $total      = (int) ($counts['total']      ?? 0);
        $complete   = (int) ($counts['complete']   ?? 0);
        $incomplete = (int) ($counts['incomplete'] ?? 0);

        // Unmatched punches within the same window (filter by source_local_at date part)
        $umStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM biometric_punch
              WHERE match_status IN ('unmatched','coverage_exception')
                AND DATE(source_local_at) BETWEEN :from AND :to"
        );
        $umStmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        $unmatched = (int) $umStmt->fetchColumn();

        // ---------------------------------------------------------------
        // Main row query — filtered + branch filter + employee search
        // ---------------------------------------------------------------
        $search   = trim((string) ($_GET['search']   ?? ''));
        $branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== ''
                    ? (int) $_GET['branch_id'] : null;

        $where  = ['a.attendance_date BETWEEN :from AND :to'];
        $bind   = [':from' => $dateFrom, ':to' => $dateTo];

        if ($search !== '') {
            $where[] = "(e.last_name LIKE :search_last_name
                          OR e.first_name LIKE :search_first_name
                          OR e.employee_number LIKE :search_employee_number)";
            $searchPattern = '%' . $search . '%';
            $bind[':search_last_name']       = $searchPattern;
            $bind[':search_first_name']      = $searchPattern;
            $bind[':search_employee_number'] = $searchPattern;
        }
        if ($branchId !== null) {
            $where[]           = 'eba.branch_id = :branch_id';
            $bind[':branch_id'] = $branchId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $rows = $pdo->prepare(
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
                    a.status,
                    CASE WHEN a.status IN ('Incomplete','ReviewRequired') THEN 1 ELSE 0 END AS is_incomplete
               FROM attendance a
               JOIN employee e ON e.employee_id = a.employee_id
               JOIN employee_branch_assignment eba
                     ON eba.branch_assignment_id = a.branch_assignment_id
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              {$whereClause}
              ORDER BY a.attendance_date DESC, e.last_name
              LIMIT 500"
        );
        $rows->execute($bind);
        $rows = $rows->fetchAll();

        // Branch list for filter dropdown
        $branches = $pdo->query(
            "SELECT branch_id, branch_name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/index', [
            'rows'        => $rows,
            'total'       => $total,
            'complete'    => $complete,
            'incomplete'  => $incomplete,
            'unmatched'   => $unmatched,
            // filter state
            'activeTab'   => $activeTab,
            'monthInput'  => $monthInput,
            'periodId'    => $periodId,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'periods'     => $periods,
            'branches'    => $branches,
            'branchId'    => $branchId,
            'search'      => $search,
        ], 'Attendance Management');
    }

    // -----------------------------------------------------------------------
    // GET /hr/attendance/import
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function import(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $batches = $pdo->query(
            "SELECT aib.import_batch_id,
                    aib.file_name,
                    aib.file_checksum,
                    aib.status,
                    u.username            AS uploaded_by,
                    aib.uploaded_at,
                    aib.records_parsed,
                    aib.records_matched,
                    aib.records_unmatched,
                    aib.duplicates_skipped,
                    aib.incomplete_days,
                    aib.multi_punch_days,
                    aib.completed_at
               FROM attendance_import_batch aib
               LEFT JOIN users u ON u.user_id = aib.uploaded_by
              ORDER BY aib.uploaded_at DESC
              LIMIT 20"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/import', [
            'batches' => $batches,
            'errors'  => [],
            'devices' => $this->loadDevices($pdo),
        ], 'Import Attendance');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/import
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function upload(array $params = []): void
    {
        $identity   = AuthMiddleware::identity();
        $errors     = [];
        $storagePath = null;

        // --- 1. Basic upload presence check ---
        $uploadError = $_FILES['attendance_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (empty($_FILES['attendance_file']) || $uploadError !== UPLOAD_ERR_OK) {
            $errors[] = $this->uploadErrorMessage((int) $uploadError);
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
            if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'xls') {
                $errors[] = 'Only .xls files exported from the biometric device are accepted.';
            }

            // --- 4. OLE signature check (ADR-0003) ---
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

            // --- 5. Read device_id, source_year, source_month from POST ---
            $deviceId    = (int) ($_POST['device_id']    ?? 0);
            $sourceYear  = (int) ($_POST['source_year']  ?? 0);
            $sourceMonth = (int) ($_POST['source_month'] ?? 0);

            if ($deviceId < 1) {
                $errors[] = 'Please select the biometric device this file was exported from.';
            }
            if ($sourceYear < 2000 || $sourceYear > 2100) {
                $errors[] = 'Invalid source year.';
            }
            if ($sourceMonth < 1 || $sourceMonth > 12) {
                $errors[] = 'Invalid source month.';
            }

            // --- 6. Move to private storage with randomized name (ADR-0003) ---
            if ($errors === []) {
                $storageDir = APP_ROOT . '/storage/private/attendance/';
                if (!is_dir($storageDir)) {
                    mkdir($storageDir, 0750, true);
                }
                $storagePath = $storageDir . bin2hex(random_bytes(16)) . '.xls';

                if (!move_uploaded_file($tmpPath, $storagePath)) {
                    $errors[] = 'Failed to store uploaded file. Please try again.';
                    $storagePath = null;
                }
            }

            // --- 7. Run AttendanceImportService ---
            if ($errors === [] && $storagePath !== null) {
                try {
                    $sha256 = (string) hash_file('sha256', $storagePath);

                    $uploadedFile = new UploadedAttendanceFile(
                        $storagePath,
                        $origName,
                        $size,
                        $sha256,
                    );

                    $context = new ParserContext(
                        deviceId:    $deviceId,
                        sourceYear:  $sourceYear,
                        sourceMonth: $sourceMonth,
                        timezone:    new DateTimeZone('Asia/Manila'),
                    );

                    $pdo     = $this->makeConnection()->pdo();
                    $gateway = new PdoAttendanceImportGateway($pdo);

                    // Stamp the original filename onto the batch after creation
                    // by wrapping the service call and updating after commit.
                    $service = new AttendanceImportService(
                        new LdeXlsDailyLogParser(),
                        $gateway,
                        new TimesheetGenerator(),
                    );

                    $summary = $service->import($uploadedFile, $context, (int) ($identity['user_id'] ?? 0));

                    // Update file_name now that we have the batch_id
                    // (the gateway createImportBatch stored an empty string;
                    //  fetch the row we just committed and patch it)
                    $this->patchFileName($pdo, $sha256, $origName);

                    ViewRenderer::flash(sprintf(
                        "Import complete — %d punches parsed, %d matched, %d unmatched, %d duplicates skipped, %d incomplete days.",
                        $summary->parsedTokens,
                        $summary->matchedPunches,
                        $summary->unmatchedPunches,
                        $summary->duplicatePunches,
                        $summary->incompleteDays,
                    ));
                    $this->redirect('/hr/attendance/import');
                    return;

                } catch (DuplicateAttendanceFileException) {
                    $errors[] = 'This file has already been imported (duplicate checksum detected).';

                } catch (AttendanceParseException $e) {
                    foreach ($e->errors as $err) {
                        $loc = '';
                        if ($err->row !== null)    { $loc .= " row {$err->row}"; }
                        if ($err->column !== null) { $loc .= " col {$err->column}"; }
                        $errors[] = "[{$err->code}]{$loc}: {$err->message}";
                    }

                } catch (\Throwable $e) {
                    $errors[] = 'Import failed: ' . $e->getMessage();
                } finally {
                    // Always remove the temporary file (ADR-0003)
                    if ($storagePath !== null && file_exists($storagePath)) {
                        @unlink($storagePath);
                    }
                }
            } elseif ($storagePath !== null && file_exists($storagePath)) {
                @unlink($storagePath);
            }
        }

        // Re-render import form with errors
        $pdo     = $this->makeConnection()->pdo();
        $batches = $pdo->query(
            "SELECT aib.import_batch_id,
                    aib.file_name,
                    aib.file_checksum,
                    aib.status,
                    u.username            AS uploaded_by,
                    aib.uploaded_at,
                    aib.records_parsed,
                    aib.records_matched,
                    aib.records_unmatched,
                    aib.duplicates_skipped,
                    aib.incomplete_days,
                    aib.multi_punch_days,
                    aib.completed_at
               FROM attendance_import_batch aib
               LEFT JOIN users u ON u.user_id = aib.uploaded_by
              ORDER BY aib.uploaded_at DESC
              LIMIT 20"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/import', [
            'batches' => $batches,
            'errors'  => $errors,
            'devices' => $this->loadDevices($pdo),
        ], 'Import Attendance');
    }

    // -----------------------------------------------------------------------
    // GET /hr/attendance/{id}/adjust
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function adjustForm(array $params = []): void
    {
        $attendanceId = (int) ($params['id'] ?? 0);
        try {
            $attendance = $this->adjustmentService()->findForAdjustment($attendanceId);
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
            $this->redirect('/hr/attendance');
            return;
        }

        ViewRenderer::render('hr/attendance/adjust', [
            'attendance' => $attendance,
            'errors' => [],
            'old' => [],
        ], 'Adjust Attendance');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/{id}/adjust
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function adjust(array $params = []): void
    {
        $attendanceId = (int) ($params['id'] ?? 0);
        $input = [
            'time_in' => trim((string) ($_POST['time_in'] ?? '')),
            'time_out' => trim((string) ($_POST['time_out'] ?? '')),
            'manual_overtime_minutes' => trim((string) ($_POST['manual_overtime_minutes'] ?? '')),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ];

        try {
            $identity = AuthMiddleware::identity();
            $this->adjustmentService()->adjust($attendanceId, $input, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Attendance adjustment saved. Recompute any unapproved payroll run for this period before submitting it.');
            $this->redirect('/hr/attendance');
            return;
        } catch (RuntimeException $e) {
            try {
                $attendance = $this->adjustmentService()->findForAdjustment($attendanceId);
            } catch (RuntimeException) {
                ViewRenderer::flashError('Attendance record not found.');
                $this->redirect('/hr/attendance');
                return;
            }
            ViewRenderer::render('hr/attendance/adjust', [
                'attendance' => $attendance,
                'errors' => [$e->getMessage()],
                'old' => $input,
            ], 'Adjust Attendance');
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** Load active biometric devices for the upload form device selector. */
    private function loadDevices(\PDO $pdo): array
    {
        return $pdo->query(
            "SELECT device_id, device_code, device_name
               FROM biometric_device
              WHERE status = 'Active'
              ORDER BY device_name"
        )->fetchAll();
    }

    /** Patch file_name on the batch row after a successful import. */
    private function patchFileName(\PDO $pdo, string $sha256, string $origName): void
    {
        $stmt = $pdo->prepare(
            "UPDATE attendance_import_batch
                SET file_name = :fname
              WHERE file_checksum = :checksum"
        );
        $stmt->execute([':fname' => $origName, ':checksum' => $sha256]);
    }

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

    private function adjustmentService(): AttendanceAdjustmentService
    {
        return new AttendanceAdjustmentService($this->makeConnection());
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
