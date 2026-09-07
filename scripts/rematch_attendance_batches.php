<?php

declare(strict_types=1);

use Wbpms\Application\Attendance\AttendancePunchMatch;
use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use Wbpms\Domain\Attendance\TimesheetGenerator;
use Wbpms\Infrastructure\Persistence\PdoAttendanceImportGateway;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    Dotenv\Dotenv::createUnsafeMutable(dirname(__DIR__))->load();
}

$batchIds = array_values(array_unique(array_filter(
    array_map('intval', array_slice($argv, 1)),
    static fn (int $id): bool => $id > 0,
)));
if ($batchIds === []) {
    throw new RuntimeException('Usage: php scripts/rematch_attendance_batches.php <batch-id> [<batch-id> ...]');
}

$config = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    '%s:host=%s;port=%s;dbname=%s;charset=%s',
    $config['driver'], $config['host'], $config['port'], $config['database'], $config['charset'],
);
$pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
$gateway = new PdoAttendanceImportGateway($pdo);
$generator = new TimesheetGenerator();
$timezone = new DateTimeZone('Asia/Manila');
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$results = [];

$pdo->beginTransaction();
try {
    $batchStatement = $pdo->prepare(
        'SELECT import_batch_id, uploaded_by FROM attendance_import_batch WHERE import_batch_id = :id FOR UPDATE'
    );
    $punchStatement = $pdo->prepare(
        'SELECT punch_id, device_id, device_employee_code, source_local_at, source_workbook_row,
                source_date_column, raw_record, source_department, source_user_id, source_employee_name
           FROM biometric_punch
          WHERE import_batch_id = :batch_id
          ORDER BY source_local_at, punch_id'
    );
    $approvedPayrollStatement = $pdo->prepare(
        "SELECT COUNT(*)
           FROM biometric_punch bp
           JOIN employee_biometric_enrollment ebe
             ON ebe.device_id = bp.device_id
            AND ebe.device_employee_code = bp.device_employee_code
            AND ebe.status = 'Active'
            AND ebe.effective_from <= DATE(bp.source_local_at)
            AND (ebe.effective_to IS NULL OR ebe.effective_to > DATE(bp.source_local_at))
           JOIN payroll p ON p.employee_id = ebe.employee_id
           JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
           JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
          WHERE bp.import_batch_id = :batch_id
            AND pr.status = 'Approved'
            AND DATE(bp.source_local_at) BETWEEN pp.period_start AND pp.period_end"
    );
    $assignmentStatement = $pdo->prepare(
        'SELECT branch_assignment_id FROM employee_branch_assignment
          WHERE employee_id = :employee_id
            AND effective_from <= :from_date
            AND (effective_to IS NULL OR effective_to > :to_date)
          ORDER BY effective_from DESC
          LIMIT 1'
    );
    $updatePunchStatement = $pdo->prepare(
        'UPDATE biometric_punch
            SET employee_id = :employee_id,
                branch_assignment_id = :branch_assignment_id,
                match_status = :match_status
          WHERE punch_id = :punch_id'
    );
    $deleteAttendanceStatement = $pdo->prepare(
        "DELETE FROM attendance WHERE import_batch_id = :batch_id AND source = 'xls_import'"
    );
    $updateBatchStatement = $pdo->prepare(
        "UPDATE attendance_import_batch
            SET records_matched = :matched,
                records_unmatched = :unmatched,
                incomplete_days = :incomplete,
                multi_punch_days = :multi,
                completed_at = :completed_at
          WHERE import_batch_id = :batch_id"
    );
    $auditStatement = $pdo->prepare(
        "INSERT INTO audit_logs
            (user_id, event_type, action_performed, table_affected, record_id,
             description, action_at, created_at)
         VALUES
            (:user_id, 'attendance_batch_rematched', 'rematch_attendance_batch', 'attendance_import_batch', :batch_id,
             :description, :action_at, :created_at)"
    );

    foreach ($batchIds as $batchId) {
        $batchStatement->execute([':id' => $batchId]);
        $batch = $batchStatement->fetch(PDO::FETCH_ASSOC);
        if ($batch === false) {
            throw new RuntimeException("Attendance import batch {$batchId} was not found.");
        }
        $approvedPayrollStatement->execute([':batch_id' => $batchId]);
        if ((int) $approvedPayrollStatement->fetchColumn() > 0) {
            throw new RuntimeException(
                "Batch {$batchId} contains punches in an approved payroll period and cannot be rematched."
            );
        }

        $punchStatement->execute([':batch_id' => $batchId]);
        $groups = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($punchStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $localTimestamp = new DateTimeImmutable((string) $row['source_local_at'], $timezone);
            $punch = new ParsedPunch(
                (int) $row['device_id'],
                (string) $row['device_employee_code'],
                $localTimestamp,
                (int) $row['source_workbook_row'],
                (string) $row['source_date_column'],
                (string) $row['raw_record'],
                $row['source_department'] !== null ? (string) $row['source_department'] : null,
                $row['source_user_id'] !== null ? (string) $row['source_user_id'] : null,
                $row['source_employee_name'] !== null ? (string) $row['source_employee_name'] : null,
            );
            $match = $gateway->match($punch);

            if ($match->status !== AttendancePunchMatch::MATCHED) {
                $unmatched++;
                $updatePunchStatement->execute([
                    ':employee_id' => null,
                    ':branch_assignment_id' => null,
                    ':match_status' => $match->status,
                    ':punch_id' => $row['punch_id'],
                ]);
                continue;
            }

            $matched++;
            $date = $localTimestamp->format('Y-m-d');
            $assignmentStatement->execute([
                ':employee_id' => $match->employeeId,
                ':from_date' => $date,
                ':to_date' => $date,
            ]);
            $assignmentId = $assignmentStatement->fetchColumn();
            $updatePunchStatement->execute([
                ':employee_id' => $match->employeeId,
                ':branch_assignment_id' => $assignmentId !== false ? (int) $assignmentId : null,
                ':match_status' => AttendancePunchMatch::MATCHED,
                ':punch_id' => $row['punch_id'],
            ]);
            $groupKey = $match->employeeId . ':' . $date;
            $groups[$groupKey] ??= [
                'employeeId' => $match->employeeId,
                'scheduleId' => $match->scheduleId,
                'date' => $date,
                'punches' => [],
            ];
            $groups[$groupKey]['punches'][] = $punch;
        }

        $deleteAttendanceStatement->execute([':batch_id' => $batchId]);
        $incomplete = 0;
        $multiPunch = 0;
        foreach ($groups as $group) {
            $attendance = $generator->generate(
                $group['employeeId'],
                $group['date'],
                $group['punches'],
                $gateway->effectiveSchedule($group['scheduleId'], $group['date']),
            );
            $gateway->saveGeneratedAttendance($batchId, $attendance);
            $incomplete += (int) $attendance->isIncomplete();
            $multiPunch += (int) in_array('MULTI_PUNCH_REVIEW', $attendance->flags, true);
        }

        $updateBatchStatement->execute([
            ':matched' => $matched,
            ':unmatched' => $unmatched,
            ':incomplete' => $incomplete,
            ':multi' => $multiPunch,
            ':completed_at' => $now,
            ':batch_id' => $batchId,
        ]);
        $auditStatement->execute([
            ':user_id' => (int) $batch['uploaded_by'],
            ':batch_id' => $batchId,
            ':description' => json_encode([
                'matched' => $matched,
                'unmatched' => $unmatched,
                'incomplete_days' => $incomplete,
                'multi_punch_days' => $multiPunch,
            ], JSON_UNESCAPED_SLASHES),
            ':action_at' => $now,
            ':created_at' => $now,
        ]);
        $results[] = [
            'batch_id' => $batchId,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'incomplete_days' => $incomplete,
            'multi_punch_days' => $multiPunch,
        ];
    }

    $pdo->commit();
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
