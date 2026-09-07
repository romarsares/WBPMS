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
    private const PREVIEW_SESSION_KEY = '_attendance_import_preview';
    private const PREVIEW_TTL_SECONDS = 1800;

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
        $activeTab = 'cutoff'; // 'month' | 'cutoff'

        // Attendance review follows the payroll cadence by default. HR can
        // still switch to a full calendar month from the filter bar.
        if ($periodId === null && $tabHint !== 'month' && $periods !== []) {
            $periodId = (int) $periods[0]['payroll_period_id'];
        }

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
                    a.employee_id,
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
                    CASE
                        WHEN aib.status = 'Draft' THEN 'Draft'
                        WHEN aib.status = 'Cancelled' THEN 'Cancelled'
                        ELSE a.status
                    END AS status,
                    CASE WHEN a.status IN ('Incomplete','ReviewRequired') THEN 1 ELSE 0 END AS is_incomplete
               FROM attendance a
               JOIN employee e ON e.employee_id = a.employee_id
               JOIN employee_branch_assignment eba
                     ON eba.branch_assignment_id = a.branch_assignment_id
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
               LEFT JOIN attendance_import_batch aib ON aib.import_batch_id = a.import_batch_id
               {$whereClause}
              ORDER BY e.last_name ASC, e.first_name ASC, a.attendance_date ASC
              LIMIT 500"
        );
        $rows->execute($bind);
        $rows = $rows->fetchAll();

        // Matrix rows include active employees with no attendance yet, keeping
        // the employee list stable while HR scans a month or payroll cut-off.
        $employeeWhere = ["e.status = 'Active'"];
        $employeeBind = [];
        if ($search !== '') {
            $employeeWhere[] = "(e.last_name LIKE :search_last_name
                                 OR e.first_name LIKE :search_first_name
                                 OR e.employee_number LIKE :search_employee_number)";
            $employeeBind[':search_last_name'] = $searchPattern;
            $employeeBind[':search_first_name'] = $searchPattern;
            $employeeBind[':search_employee_number'] = $searchPattern;
        }
        if ($branchId !== null) {
            $employeeWhere[] = 'eba.branch_id = :branch_id';
            $employeeBind[':branch_id'] = $branchId;
        }
        $employeeClause = 'WHERE ' . implode(' AND ', $employeeWhere);
        $employees = $pdo->prepare(
            "SELECT e.employee_id, e.employee_number, e.hire_date,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    b.branch_name
               FROM employee e
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              {$employeeClause}
              ORDER BY e.last_name ASC, e.first_name ASC
              LIMIT 200"
        );
        $employees->execute($employeeBind);
        $employees = $employees->fetchAll();

        $dates = [];
        for ($day = new \DateTimeImmutable($dateFrom); $day <= new \DateTimeImmutable($dateTo); $day = $day->modify('+1 day')) {
            $dates[] = $day->format('Y-m-d');
        }

        // Approved leave is a display state in the attendance matrix. It takes
        // precedence over a derived absence and links back to the source request.
        $leaveStmt = $pdo->prepare(
            "SELECT r.request_id, r.employee_id, lrd.leave_type, lrd.start_date, lrd.end_date
               FROM request r
               JOIN request_type rt ON rt.request_type_id = r.request_type_id AND rt.type_name = 'Leave'
               JOIN leave_request_detail lrd ON lrd.request_id = r.request_id
              WHERE r.status = 'Approved'
                AND lrd.start_date <= :to_date
                AND lrd.end_date >= :from_date"
        );
        $leaveStmt->execute([':from_date' => $dateFrom, ':to_date' => $dateTo]);
        $approvedLeaves = [];
        foreach ($leaveStmt->fetchAll() as $leave) {
            $start = max((string) $leave['start_date'], $dateFrom);
            $end = min((string) $leave['end_date'], $dateTo);
            for ($day = new \DateTimeImmutable($start); $day <= new \DateTimeImmutable($end); $day = $day->modify('+1 day')) {
                $approvedLeaves[(int) $leave['employee_id']][$day->format('Y-m-d')] = [
                    'request_id' => (int) $leave['request_id'],
                    'leave_type' => (string) $leave['leave_type'],
                ];
            }
        }
        $holidayStmt = $pdo->prepare(
            "SELECT holiday_date, description FROM holiday_calendar
              WHERE status = 'Active' AND holiday_date BETWEEN :from_date AND :to_date"
        );
        $holidayStmt->execute([':from_date' => $dateFrom, ':to_date' => $dateTo]);
        $holidayDates = [];
        foreach ($holidayStmt->fetchAll() as $holiday) {
            $holidayDates[(string) $holiday['holiday_date']] = (string) $holiday['description'];
        }
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');

        // Branch list for filter dropdown
        $branches = $pdo->query(
            "SELECT branch_id, branch_name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/index', [
            'rows'        => $rows,
            'employees'   => $employees,
            'dates'       => $dates,
            'approvedLeaves' => $approvedLeaves,
            'holidayDates' => $holidayDates,
            'today'       => $today,
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
        // Visiting the upload form abandons any unconfirmed preview.
        $this->clearStagedImport();
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
            'preview' => null,
        ], 'Import Attendance');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/import
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function upload(array $params = []): void
    {
        $errors     = [];
        $storagePath = null;

        // Replacing a preview must never retain an older workbook in the session.
        $this->clearStagedImport();

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

            // --- 7. Parse and stage for review. This path has no database writes. ---
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

                    if ($gateway->hasCompletedChecksum($sha256)) {
                        throw new DuplicateAttendanceFileException();
                    }

                    $parsed = (new LdeXlsDailyLogParser())->parse($uploadedFile, $context);
                    $preview = $this->buildPreview($parsed->punches);
                    $preview['fileName'] = $origName;
                    $preview['sourceYear'] = $sourceYear;
                    $preview['sourceMonth'] = $sourceMonth;
                    $this->stageImport($storagePath, [
                        'original_filename' => $origName,
                        'size' => $size,
                        'sha256' => $sha256,
                        'device_id' => $deviceId,
                        'source_year' => $sourceYear,
                        'source_month' => $sourceMonth,
                    ]);
                    $storagePath = null;
                    $preview['token'] = (string) $this->stagedImport()['token'];
                    $this->renderImport([], $preview);
                    return;

                    /*
                    $summary = $service->import($uploadedFile, $context, 0);

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
                    */

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

    /**
     * POST /hr/attendance/import/confirm.
     *
     * The reviewed workbook is parsed again and saved only through the
     * import service's single database transaction.
     *
     * @param array<string, string> $params
     */
    public function confirmImport(array $params = []): void
    {
        $staged = $this->stagedImport();
        $submittedToken = (string) ($_POST['preview_token'] ?? '');
        if ($staged === null || !hash_equals((string) $staged['token'], $submittedToken)) {
            ViewRenderer::flashError('This import preview has expired. Upload the workbook again.');
            $this->redirect('/hr/attendance/import');
            return;
        }

        $storagePath = (string) $staged['path'];
        if (!is_file($storagePath) || hash_file('sha256', $storagePath) !== $staged['sha256']) {
            $this->clearStagedImport();
            ViewRenderer::flashError('The staged workbook is no longer available. Upload it again.');
            $this->redirect('/hr/attendance/import');
            return;
        }

        try {
            $identity = AuthMiddleware::identity();
            $file = new UploadedAttendanceFile(
                $storagePath,
                (string) $staged['original_filename'],
                (int) $staged['size'],
                (string) $staged['sha256'],
            );
            $context = new ParserContext(
                deviceId: (int) $staged['device_id'],
                sourceYear: (int) $staged['source_year'],
                sourceMonth: (int) $staged['source_month'],
                timezone: new DateTimeZone('Asia/Manila'),
            );
            $pdo = $this->makeConnection()->pdo();
            $service = new AttendanceImportService(
                new LdeXlsDailyLogParser(),
                new PdoAttendanceImportGateway($pdo),
                new TimesheetGenerator(),
            );
            $summary = $service->import($file, $context, (int) ($identity['user_id'] ?? 0));
            $this->patchFileName($pdo, (string) $staged['sha256'], (string) $staged['original_filename']);
            $this->clearStagedImport();
            ViewRenderer::flash(sprintf(
                'Draft import saved - %d punches parsed, %d matched, %d unmatched, %d duplicates skipped, %d incomplete days. Approve it before it is used by payroll.',
                $summary->parsedTokens,
                $summary->matchedPunches,
                $summary->unmatchedPunches,
                $summary->duplicatePunches,
                $summary->incompleteDays,
            ));
            $this->redirect('/hr/attendance/import');
            return;
        } catch (DuplicateAttendanceFileException) {
            $errors = ['This file has already been imported (duplicate checksum detected).'];
        } catch (AttendanceParseException $e) {
            $errors = $this->formatParseErrors($e);
        } catch (\Throwable $e) {
            $errors = ['Import failed. No attendance records were saved: ' . $e->getMessage()];
        }

        $this->clearStagedImport();
        $this->renderImport($errors);
    }

    /** @param array<string, string> $params */
    public function approveImport(array $params = []): void
    {
        $batchId = (int) ($params['id'] ?? 0);
        $actorId = (int) (AuthMiddleware::identity()['user_id'] ?? 0);

        try {
            $this->makeConnection()->transaction(function (\PDO $pdo) use ($batchId, $actorId): void {
                $batch = $pdo->prepare(
                    'SELECT status FROM attendance_import_batch WHERE import_batch_id = :id FOR UPDATE'
                );
                $batch->execute([':id' => $batchId]);
                if ($batch->fetchColumn() !== 'Draft') {
                    throw new RuntimeException('Only a draft attendance import can be approved.');
                }
                $pdo->prepare(
                    "UPDATE attendance_import_batch
                        SET status = 'Approved', approved_by = :user_id, approved_at = UTC_TIMESTAMP()
                      WHERE import_batch_id = :id"
                )->execute([':id' => $batchId, ':user_id' => $actorId]);
            });
            ViewRenderer::flash('Attendance import approved. Its timesheets are now available to payroll.');
        } catch (\Throwable $e) {
            ViewRenderer::flashError('The import could not be approved: ' . $e->getMessage());
        }

        $this->redirect('/hr/attendance/import');
    }

    /** @param array<string, string> $params */
    public function cancelImport(array $params = []): void
    {
        $batchId = (int) ($params['id'] ?? 0);
        $actorId = (int) (AuthMiddleware::identity()['user_id'] ?? 0);

        try {
            $this->makeConnection()->transaction(function (\PDO $pdo) use ($batchId, $actorId): void {
                $batch = $pdo->prepare(
                    'SELECT status FROM attendance_import_batch WHERE import_batch_id = :id FOR UPDATE'
                );
                $batch->execute([':id' => $batchId]);
                $status = $batch->fetchColumn();
                if (!in_array($status, ['Draft', 'Approved', 'Completed'], true)) {
                    throw new RuntimeException('Only a saved attendance import can be cancelled.');
                }

                // Payroll stores an employee and branch-assignment snapshot rather
                // than a direct batch reference. Any payroll period containing an
                // attendance day from this batch means it has been used and is
                // intentionally immutable from this workflow.
                $used = $pdo->prepare(
                    "SELECT COUNT(*)
                       FROM attendance a
                       JOIN payroll p ON p.employee_id = a.employee_id
                       JOIN payroll_period pp ON pp.payroll_period_id = p.payroll_period_id
                      WHERE a.import_batch_id = :id
                        AND a.attendance_date BETWEEN pp.period_start AND pp.period_end"
                );
                $used->execute([':id' => $batchId]);
                if ((int) $used->fetchColumn() > 0) {
                    throw new RuntimeException('This import has already been used by payroll and cannot be cancelled. Reverse or recompute the payroll first.');
                }

                $pdo->prepare(
                    "UPDATE attendance SET status = 'Cancelled' WHERE import_batch_id = :id"
                )->execute([':id' => $batchId]);

                // Delete biometric_punch rows so the same file can be cleanly
                // re-imported after cancellation — isDuplicatePunch checks
                // biometric_punch and would otherwise skip every punch as a
                // duplicate, producing an empty re-import.
                $pdo->prepare(
                    'DELETE FROM biometric_punch WHERE import_batch_id = :id'
                )->execute([':id' => $batchId]);

                $pdo->prepare(
                    "UPDATE attendance_import_batch
                        SET status = 'Cancelled', cancelled_by = :user_id, cancelled_at = UTC_TIMESTAMP(),
                            cancellation_reason = 'Cancelled by HR before payroll use.'
                      WHERE import_batch_id = :id"
                )->execute([':id' => $batchId, ':user_id' => $actorId]);
            });
            ViewRenderer::flash('Attendance import cancelled. Its timesheets are excluded from payroll.');
        } catch (\Throwable $e) {
            ViewRenderer::flashError('The import could not be cancelled: ' . $e->getMessage());
        }

        $this->redirect('/hr/attendance/import');
    }

    /** @param list<string> $errors */
    private function renderImport(array $errors, ?array $preview = null): void
    {
        $pdo = $this->makeConnection()->pdo();
        $batches = $pdo->query(
            "SELECT aib.import_batch_id, aib.file_name, aib.file_checksum, aib.status,
                    u.username AS uploaded_by, aib.uploaded_at, aib.records_parsed,
                    aib.records_matched, aib.records_unmatched, aib.duplicates_skipped,
                    aib.incomplete_days, aib.multi_punch_days, aib.completed_at
               FROM attendance_import_batch aib
               LEFT JOIN users u ON u.user_id = aib.uploaded_by
              ORDER BY aib.uploaded_at DESC LIMIT 20"
        )->fetchAll();

        ViewRenderer::render('hr/attendance/import', [
            'batches' => $batches,
            'errors' => $errors,
            'devices' => $this->loadDevices($pdo),
            'preview' => $preview,
        ], 'Import Attendance');
    }

    /** @param list<\Wbpms\Domain\Attendance\Parsing\ParsedPunch> $punches */
    private function buildPreview(array $punches): array
    {
        $sample = [];
        $dates = [];
        $enrollments = [];
        foreach ($punches as $punch) {
            $dateTime = $punch->localTimestamp->format('Y-m-d H:i');
            $dates[] = $punch->localTimestamp->format('Y-m-d');
            $enrollments[$punch->enrollmentCode] = true;
            if (count($sample) < 100) {
                $sample[] = [
                    'row' => $punch->sourceRow,
                    'dateTime' => $dateTime,
                    'enrollment' => $punch->enrollmentCode,
                    'name' => $punch->sourceName ?? '',
                    'department' => $punch->department ?? '',
                ];
            }
        }
        sort($dates);

        return [
            'total' => count($punches),
            'employeeCount' => count($enrollments),
            'dateFrom' => $dates[0] ?? null,
            'dateTo' => $dates[array_key_last($dates)] ?? null,
            'sample' => $sample,
        ];
    }

    /** @param array<string, int|string> $metadata */
    private function stageImport(string $path, array $metadata): void
    {
        $_SESSION[self::PREVIEW_SESSION_KEY] = $metadata + [
            'token' => bin2hex(random_bytes(24)),
            'path' => $path,
            'created_at' => time(),
        ];
    }

    /** @return array<string, int|string>|null */
    private function stagedImport(): ?array
    {
        $staged = $_SESSION[self::PREVIEW_SESSION_KEY] ?? null;
        if (!is_array($staged)
            || !isset($staged['token'], $staged['path'], $staged['sha256'], $staged['created_at'])
            || (int) $staged['created_at'] + self::PREVIEW_TTL_SECONDS < time()) {
            $this->clearStagedImport();
            return null;
        }
        return $staged;
    }

    private function clearStagedImport(): void
    {
        $staged = $_SESSION[self::PREVIEW_SESSION_KEY] ?? null;
        unset($_SESSION[self::PREVIEW_SESSION_KEY]);
        if (!is_array($staged) || !isset($staged['path'])) {
            return;
        }

        $directory = realpath(APP_ROOT . '/storage/private/attendance');
        $path = realpath((string) $staged['path']);
        if ($directory !== false && $path !== false && str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            @unlink($path);
        }
    }

    /** @return list<string> */
    private function formatParseErrors(AttendanceParseException $exception): array
    {
        $errors = [];
        foreach ($exception->errors as $err) {
            $location = '';
            if ($err->row !== null) { $location .= " row {$err->row}"; }
            if ($err->column !== null) { $location .= " col {$err->column}"; }
            $errors[] = "[{$err->code}]{$location}: {$err->message}";
        }
        return $errors;
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
