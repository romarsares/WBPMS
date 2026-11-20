<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Wbpms\Domain\Attendance\AttendancePolicyEvaluator;
use Wbpms\Infrastructure\Database\Connection;

/**
 * AttendancePolicyService — application service for HR-review policy flags.
 *
 * Implements REQ006 AC16–AC17:
 *   - AC16   Three consecutive lates → flag for an HR memorandum; the third
 *            memorandum → flag for HR review of a one-week suspension.
 *   - AC17   Two weeks without reporting, or three consecutive unexcused
 *            absences → flag for HR review under the AWOL/termination policy.
 *
 * The system SHALL NOT impose discipline or terminate automatically — this
 * service only raises flags for HR to review, and HR records the reviewed
 * action and supporting reason (in the flag row and the audit log).
 *
 * Evaluation rules (see AttendancePolicyEvaluator):
 *   - Late days come from attendance.late_minutes after the schedule grace
 *     period has already been applied at import/adjustment time.
 *   - Three-day AWOL runs are scheduled workdays inside the employee's
 *     attendance span. Rest days and active holidays neither count nor break
 *     that run; approved leave resets it. The separate two-week policy uses
 *     calendar days without reporting.
 *   - Flags are unique per (employee_id, flag_type, triggering_date), so
 *     re-running an evaluation never duplicates flags.
 */
final class AttendancePolicyService
{
    /** Default look-back window (days) used by the HR UI. */
    public const DEFAULT_LOOKBACK_DAYS = 30;

    public function __construct(private Connection $connection) {}

    // -----------------------------------------------------------------------
    // Evaluation
    // -----------------------------------------------------------------------

    /**
     * Evaluate every active employee and persist any new flags, one employee
     * at a time (each in its own transaction so a partial failure is isolated).
     *
     * @return int Number of flags created.
     */
    public function evaluateAll(string $from, string $to): int
    {
        $pdo = $this->connection->pdo();
        $employees = $pdo->query(
            "SELECT employee_id
               FROM employee
              WHERE status = 'Active'
              ORDER BY employee_id"
        )->fetchAll();

        $created = 0;
        foreach ($employees as $employee) {
            $created += count($this->evaluateEmployee((int) $employee['employee_id'], $from, $to));
        }
        return $created;
    }

    /**
     * Evaluate one employee inside [from, to] and insert any new flags.
     *
     * @return list<array{flag_id:int,employee_id:int,flag_type:string,triggering_date:string,status:string,notes:string}>
     */
    public function evaluateEmployee(int $employeeId, string $from, string $to): array
    {
        $pdo = $this->connection->pdo();

        $stmt = $pdo->prepare(
            "SELECT attendance_date, time_in, late_minutes, status
               FROM attendance
              WHERE employee_id = :id
                AND attendance_date BETWEEN :from AND :to
              ORDER BY attendance_date ASC"
        );
        $stmt->execute([':id' => $employeeId, ':from' => $from, ':to' => $to]);
        $attendance = $stmt->fetchAll();

        $existingStmt = $pdo->prepare(
            "SELECT flag_type, triggering_date
               FROM attendance_policy_flag
              WHERE employee_id = :id
                AND triggering_date BETWEEN :from AND :to"
        );
        $existingStmt->execute([':id' => $employeeId, ':from' => $from, ':to' => $to]);
        $existing = $existingStmt->fetchAll();

        $memoStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM attendance_policy_flag
              WHERE employee_id = :id AND flag_type = 'ConsecutiveLate'"
        );
        $memoStmt->execute([':id' => $employeeId]);
        $existingMemoCount = (int) $memoStmt->fetchColumn();

        $excusedDates = $this->approvedLeaveDates($pdo, $employeeId, $from, $to);
        $scheduledWorkDates = $this->scheduledWorkDates($pdo, $employeeId, $from, $to);
        $proposals = (new AttendancePolicyEvaluator())->evaluate(
            $attendance,
            $existing,
            $excusedDates,
            $existingMemoCount,
            $scheduledWorkDates,
        );

        if ($proposals === []) {
            return [];
        }

        return $this->connection->transaction(function (PDO $pdo) use ($employeeId, $proposals): array {
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $insert = $pdo->prepare(
                "INSERT INTO attendance_policy_flag
                    (employee_id, flag_type, triggering_date, status, notes, created_at, updated_at)
                 VALUES
                    (:emp, :type, :date, 'Pending', :notes, :created_at, :updated_at)"
            );
            $created = [];
            foreach ($proposals as $proposal) {
                // Unique key (employee_id, flag_type, triggering_date): skip any
                // flag a concurrent evaluation already created.
                $dup = $pdo->prepare(
                    "SELECT 1 FROM attendance_policy_flag
                      WHERE employee_id = :emp AND flag_type = :type AND triggering_date = :date
                      LIMIT 1"
                );
                $dup->execute([
                    ':emp' => $employeeId,
                    ':type' => $proposal['flag_type'],
                    ':date' => $proposal['triggering_date'],
                ]);
                if ((bool) $dup->fetchColumn()) {
                    continue;
                }
                $insert->execute([
                    ':emp' => $employeeId,
                    ':type' => $proposal['flag_type'],
                    ':date' => $proposal['triggering_date'],
                    ':notes' => $proposal['notes'],
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $created[] = [
                    'flag_id' => (int) $pdo->lastInsertId(),
                    'employee_id' => $employeeId,
                    'flag_type' => $proposal['flag_type'],
                    'triggering_date' => $proposal['triggering_date'],
                    'status' => 'Pending',
                    'notes' => $proposal['notes'],
                ];
            }
            return $created;
        });
    }

    // -----------------------------------------------------------------------
    // HR review queue
    // -----------------------------------------------------------------------

    /**
     * List flags for the HR review queue with optional filters.
     *
     * @param array{status?:string,type?:string,employee_number?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function listFlags(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $employeeNumber = trim((string) ($filters['employee_number'] ?? ''));
        if ($status !== '') {
            $conditions[] = "f.status = :status";
            $params[':status'] = $status;
        }
        if ($type !== '') {
            $conditions[] = "f.flag_type = :type";
            $params[':type'] = $type;
        }
        if ($employeeNumber !== '') {
            $conditions[] = "e.employee_number = :emp";
            $params[':emp'] = $employeeNumber;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT f.flag_id, f.employee_id, f.flag_type, f.triggering_date,
                       f.status, f.reviewed_by, f.reviewed_at, f.action_taken,
                       f.notes, f.created_at,
                       n.notice_id, n.title AS notice_title, n.issued_at AS notice_issued_at,
                       n.acknowledged_at AS notice_acknowledged_at,
                       CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                       e.employee_number, e.position
                  FROM attendance_policy_flag f
                  JOIN employee e ON e.employee_id = f.employee_id"
              . " LEFT JOIN employee_hr_notice n ON n.policy_flag_id = f.flag_id"
              . $where
              . " ORDER BY (f.status = 'Pending') DESC, f.triggering_date DESC, f.flag_id DESC
                 LIMIT 200";

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);
        $flags = $this->withAbsenceEvidenceDates($stmt->fetchAll());
        $flags = $this->withLateEvidenceDates($flags);
        return $this->withReviewHistory($flags);
    }
    public function counts(): array
    {
        $rows = $this->connection->pdo()->query(
            "SELECT status, COUNT(*) AS total
               FROM attendance_policy_flag
              GROUP BY status"
        )->fetchAll();

        $counts = ['Pending' => 0, 'Reviewed' => 0, 'Closed' => 0, 'Total' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['total'];
            $counts['Total'] += (int) $row['total'];
        }
        return $counts;
    }

    /**
     * Load a single flag with its employee for the review page.
     *
     * @return array<string,mixed>
     */
    public function findFlag(int $flagId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT f.flag_id, f.employee_id, f.flag_type, f.triggering_date,
                    f.status, f.reviewed_by, f.reviewed_at, f.action_taken,
                    f.notes, f.created_at,
                    n.notice_id, n.title AS notice_title, n.issued_at AS notice_issued_at,
                    n.acknowledged_at AS notice_acknowledged_at,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number, e.position
               FROM attendance_policy_flag f
               JOIN employee e ON e.employee_id = f.employee_id
               LEFT JOIN employee_hr_notice n ON n.policy_flag_id = f.flag_id
              WHERE f.flag_id = :id"
        );
        $stmt->execute([':id' => $flagId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !$row) {
            throw new RuntimeException('Policy flag not found.');
        }
        return $this->withLateEvidenceDates($this->withAbsenceEvidenceDates([$row]))[0];
    }

    /**
     * Record HR's decision on a flag. The system never auto-suspends or
     * terminates — HR records the reviewed action and notes here, and an
     * audit event is appended for traceability.
     *
     * @param array{status:string,action_taken:string,notes:string,notice_title?:string,notice_body?:string} $input
     */
    public function review(int $flagId, int $reviewedByUserId, array $input): void
    {
        if ($reviewedByUserId < 1) {
            throw new RuntimeException('A signed-in HR user is required to review a policy flag.');
        }

        $status = trim((string) ($input['status'] ?? ''));
        if (!in_array($status, ['Reviewed', 'Closed'], true)) {
            throw new RuntimeException('Review status must be Reviewed or Closed.');
        }
        $action = trim((string) ($input['action_taken'] ?? ''));
        if (mb_strlen($action) > 255) {
            throw new RuntimeException('Action taken must be 255 characters or fewer.');
        }
        $notes = trim((string) ($input['notes'] ?? ''));
        if (mb_strlen($notes) > 4000) {
            throw new RuntimeException('Notes must be 4,000 characters or fewer.');
        }
        $noticeTitle = trim((string) ($input['notice_title'] ?? ''));
        $noticeBody = trim((string) ($input['notice_body'] ?? ''));
        if (($noticeTitle === '') !== ($noticeBody === '')) {
            throw new RuntimeException('An employee notice requires both a title and message.');
        }
        if (mb_strlen($noticeTitle) > 160) {
            throw new RuntimeException('Employee notice title must be 160 characters or fewer.');
        }
        if (mb_strlen($noticeBody) > 4000) {
            throw new RuntimeException('Employee notice message must be 4,000 characters or fewer.');
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->connection->transaction(function (PDO $pdo) use (
            $flagId, $reviewedByUserId, $status, $action, $notes, $noticeTitle, $noticeBody, $now
        ): void {
            $flag = $pdo->prepare(
                'SELECT employee_id FROM attendance_policy_flag WHERE flag_id = :id'
            );
            $flag->execute([':id' => $flagId]);
            $employeeId = $flag->fetchColumn();
            if ($employeeId === false) {
                throw new RuntimeException('Policy flag not found.');
            }
            if ($noticeTitle !== '') {
                $existingNotice = $pdo->prepare(
                    'SELECT 1 FROM employee_hr_notice WHERE policy_flag_id = :id LIMIT 1'
                );
                $existingNotice->execute([':id' => $flagId]);
                if ((bool) $existingNotice->fetchColumn()) {
                    throw new RuntimeException('An employee notice has already been issued for this policy flag.');
                }
            }

            $stmt = $pdo->prepare(
                "UPDATE attendance_policy_flag
                    SET status = :status,
                        action_taken = :action,
                        notes = :notes,
                        reviewed_by = :by,
                        reviewed_at = :reviewed_at,
                        updated_at = :updated_at
                  WHERE flag_id = :id"
            );
            $stmt->execute([
                ':status' => $status,
                ':action' => $action === '' ? null : $action,
                ':notes' => $notes === '' ? null : $notes,
                ':by' => $reviewedByUserId,
                ':reviewed_at' => $now,
                ':updated_at' => $now,
                ':id' => $flagId,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Policy flag not found.');
            }

            $pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, event_type, action_performed, table_affected, record_id,
                     description, action_at, created_at)
                 VALUES
                    (:user_id, 'attendance_policy_flag_reviewed', 'review_attendance_policy_flag',
                     'attendance_policy_flag', :record_id, :description, :action_at, :created_at)"
            )->execute([
                ':user_id' => $reviewedByUserId,
                ':record_id' => $flagId,
                ':description' => "Flag {$flagId} reviewed as {$status}."
                    . ($action === '' ? '' : " Action taken: {$action}."),
                ':action_at' => $now,
                ':created_at' => $now,
            ]);

            if ($noticeTitle !== '') {
                $notice = $pdo->prepare(
                    "INSERT INTO employee_hr_notice
                        (employee_id, policy_flag_id, issued_by, title, body, issued_at, created_at, updated_at)
                     VALUES
                        (:employee_id, :policy_flag_id, :issued_by, :title, :body, :issued_at, :created_at, :updated_at)"
                );
                $notice->execute([
                    ':employee_id' => (int) $employeeId,
                    ':policy_flag_id' => $flagId,
                    ':issued_by' => $reviewedByUserId,
                    ':title' => $noticeTitle,
                    ':body' => $noticeBody,
                    ':issued_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $noticeId = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    "INSERT INTO audit_logs
                        (user_id, event_type, action_performed, table_affected, record_id,
                         description, action_at, created_at)
                     VALUES
                        (:user_id, 'employee_hr_notice_issued', 'issue_employee_hr_notice',
                         'employee_hr_notice', :record_id, :description, :action_at, :created_at)"
                )->execute([
                    ':user_id' => $reviewedByUserId,
                    ':record_id' => $noticeId,
                    ':description' => "Employee notice {$noticeId} issued from policy flag {$flagId}.",
                    ':action_at' => $now,
                    ':created_at' => $now,
                ]);
            }
        });
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Expand approved leave intervals for one employee into individual dates,
     * clamped to the evaluation window so an absence run can be excused.
     *
     * @return list<string> 'YYYY-MM-DD' dates covered by an Approved leave request.
     */
    private function approvedLeaveDates(PDO $pdo, int $employeeId, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT lrd.start_date, lrd.end_date
               FROM leave_request_detail lrd
               JOIN request r ON r.request_id = lrd.request_id
              WHERE r.employee_id = :id
                AND r.status = 'Approved'
                AND lrd.start_date <= :to
                AND lrd.end_date >= :from"
        );
        $stmt->execute([':id' => $employeeId, ':from' => $from, ':to' => $to]);
        $intervals = $stmt->fetchAll();

        $dates = [];
        foreach ($intervals as $interval) {
            // ISO dates compare chronologically as strings.
            $startKey = (string) $interval['start_date'] < $from ? $from : (string) $interval['start_date'];
            $endKey = (string) $interval['end_date'] > $to ? $to : (string) $interval['end_date'];
            if ($startKey > $endKey) {
                continue;
            }
            $day = DateTimeImmutable::createFromFormat('Y-m-d', $startKey);
            $end = DateTimeImmutable::createFromFormat('Y-m-d', $endKey);
            while ($day !== false && $day <= $end) {
                $dates[] = $day->format('Y-m-d');
                $day = $day->modify('+1 day');
            }
        }
        return $dates;
    }

    /**
     * Return dates on which an employee was scheduled to work, excluding
     * active holidays. Assignment periods are effective-dated, so a schedule
     * change within the evaluation window is respected.
     *
     * @return array<string,bool> keyed by Y-m-d
     */
    private function scheduledWorkDates(PDO $pdo, int $employeeId, string $from, string $to): array
    {
        $assignments = $pdo->prepare(
            "SELECT esa.effective_from, esa.effective_to, ws.working_days
               FROM employee_schedule_assignment esa
               JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
              WHERE esa.employee_id = :id
                AND esa.status = 'Active'
                AND esa.effective_from <= :to
                AND (esa.effective_to IS NULL OR esa.effective_to >= :from)
              ORDER BY esa.effective_from DESC"
        );
        $assignments->execute([':id' => $employeeId, ':from' => $from, ':to' => $to]);

        $byDate = [];
        foreach ($assignments->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
            $workingDays = json_decode((string) $assignment['working_days'], true);
            if (!is_array($workingDays)) {
                continue;
            }
            $start = max($from, (string) $assignment['effective_from']);
            $end = min($to, (string) ($assignment['effective_to'] ?? $to));
            for ($day = new DateTimeImmutable($start), $last = new DateTimeImmutable($end); $day <= $last; $day = $day->modify('+1 day')) {
                $date = $day->format('Y-m-d');
                if (!isset($byDate[$date])) {
                    $byDate[$date] = in_array($day->format('l'), $workingDays, true);
                }
            }
        }

        $holidays = $pdo->prepare(
            "SELECT holiday_date
               FROM holiday_calendar
              WHERE status = 'Active' AND holiday_date BETWEEN :from AND :to"
        );
        $holidays->execute([':from' => $from, ':to' => $to]);
        foreach ($holidays->fetchAll(PDO::FETCH_COLUMN) as $holiday) {
            unset($byDate[(string) $holiday]);
        }

        return array_filter($byDate);
    }

    /**
     * Attach late-arrival evidence to ConsecutiveLate and TardinessMemoCap flags.
     *
     * Reconstructs the exact consecutive late run that triggered the flag by
     * walking backward through attended rows (time_in IS NOT NULL) from the
     * triggering date and stopping at the first attended row that was on time.
     * Rows where time_in IS NULL (absence rows that may carry a non-zero
     * late_minutes from an import artefact) are excluded from both the run
     * walk and the evidence string; they never affect flag creation either
     * because AttendancePolicyEvaluator::buildSpan marks them as not attended.
     *
     * @param list<array<string,mixed>> $flags
     * @return list<array<string,mixed>>
     */
    private function withLateEvidenceDates(array $flags): array
    {
        $lateTypes = ['ConsecutiveLate', 'TardinessMemoCap'];
        foreach ($flags as &$flag) {
            if (!in_array((string) ($flag['flag_type'] ?? ''), $lateTypes, true)) {
                continue;
            }
            if (!isset($flag['employee_id'], $flag['triggering_date'])) {
                continue;
            }
            // Fetch attended rows in reverse order. We walk back through them
            // and stop at the first on-time row to isolate only the consecutive
            // late run that triggered the flag. Fetching more rows than the
            // threshold (×3) ensures we don't miss the run boundary.
            $lookback = AttendancePolicyEvaluator::CONSECUTIVE_LATE_THRESHOLD * 3;
            $stmt = $this->connection->pdo()->prepare(
                "SELECT attendance_date, time_in, late_minutes
                   FROM attendance
                  WHERE employee_id = :emp
                    AND attendance_date <= :date
                    AND time_in IS NOT NULL
                    AND status <> 'Cancelled'
                  ORDER BY attendance_date DESC
                  LIMIT {$lookback}"
            );
            $stmt->execute([
                ':emp' => (int) $flag['employee_id'],
                ':date' => (string) $flag['triggering_date'],
            ]);
            // Walk from most-recent backward; collect late rows until we hit
            // an on-time row, which ends the consecutive run.
            $runRows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((int) $row['late_minutes'] <= 0) {
                    break;
                }
                $runRows[] = $row;
                if (count($runRows) === AttendancePolicyEvaluator::CONSECUTIVE_LATE_THRESHOLD) {
                    break;
                }
            }
            $runRows = array_reverse($runRows);
            $parts = [];
            foreach ($runRows as $row) {
                $time = substr((string) $row['time_in'], 0, 5);
                $parts[] = $row['attendance_date'] . ' ' . $time . ' (+' . $row['late_minutes'] . ' min)';
            }
            $flag['late_dates'] = $parts !== [] ? implode(', ', $parts) : null;
        }
        unset($flag);

        return $flags;
    }

    /**
     * Attach absence evidence. AWOL evidence uses the employee's scheduled
     * workdays, while the two-week policy remains calendar-day based.
     *
     * @param list<array<string,mixed>> $flags
     * @return list<array<string,mixed>>
     */
    private function withAbsenceEvidenceDates(array $flags): array
    {
        foreach ($flags as &$flag) {
            $days = match ((string) ($flag['flag_type'] ?? '')) {
                'ConsecutiveAWOL' => AttendancePolicyEvaluator::CONSECUTIVE_AWOL_DAYS,
                'TwoWeekAbsence' => AttendancePolicyEvaluator::TWO_WEEK_ABSENCE_DAYS,
                default => 0,
            };
            if ($days === 0 || !isset($flag['triggering_date'])) {
                continue;
            }

            $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $flag['triggering_date']);
            if ($end === false) {
                continue;
            }
            if ((string) $flag['flag_type'] === 'ConsecutiveAWOL'
                && isset($flag['employee_id'])
                && (int) $flag['employee_id'] > 0) {
                $dates = $this->awolEvidenceDates((int) $flag['employee_id'], $end, $days);
            } else {
                $dates = [];
                for ($offset = $days - 1; $offset >= 0; $offset--) {
                    $dates[] = $end->modify("-{$offset} days")->format('Y-m-d');
                }
            }
            $flag['absence_dates'] = implode(', ', $dates);
        }
        unset($flag);

        return $flags;
    }

    /** @return list<string> */
    private function awolEvidenceDates(int $employeeId, DateTimeImmutable $triggeringDate, int $days): array
    {
        $from = $triggeringDate->modify('-90 days')->format('Y-m-d');
        $to = $triggeringDate->format('Y-m-d');
        $scheduled = $this->scheduledWorkDates($this->connection->pdo(), $employeeId, $from, $to);
        $dates = array_keys($scheduled);
        rsort($dates);

        $evidence = [];
        foreach ($dates as $date) {
            $evidence[] = $date;
            if (count($evidence) === $days) {
                break;
            }
        }
        sort($evidence);
        return $evidence;
    }

    /**
     * Attach review history entries from audit_logs to each flag.
     *
     * Each flag gains a 'review_history' key: a list of rows ordered oldest
     * first, each with keys: reviewed_by_name, action_at, description.
     *
     * @param list<array<string,mixed>> $flags
     * @return list<array<string,mixed>>
     */
    private function withReviewHistory(array $flags): array
    {
        if ($flags === []) {
            return $flags;
        }

        $flagIds = array_map(static fn(array $f): int => (int) $f['flag_id'], $flags);
        $placeholders = implode(',', array_fill(0, count($flagIds), '?'));

        $stmt = $this->connection->pdo()->prepare(
            "SELECT al.record_id AS flag_id,
                    COALESCE(CONCAT(e.last_name, ', ', e.first_name), u.username, 'System') AS reviewed_by_name,
                    al.action_at,
                    al.description
               FROM audit_logs al
               LEFT JOIN users u ON u.user_id = al.user_id
               LEFT JOIN employee e ON e.employee_id = u.employee_id
              WHERE al.event_type = 'attendance_policy_flag_reviewed'
                AND al.table_affected = 'attendance_policy_flag'
                AND al.record_id IN ({$placeholders})
              ORDER BY al.action_at ASC"
        );
        $stmt->execute($flagIds);
        $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byFlag = [];
        foreach ($historyRows as $row) {
            $byFlag[(int) $row['flag_id']][] = $row;
        }

        foreach ($flags as &$flag) {
            $flag['review_history'] = $byFlag[(int) $flag['flag_id']] ?? [];
        }
        unset($flag);

        return $flags;
    }
}
