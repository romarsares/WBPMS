<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;

/**
 * RequestService — application service for leave/overtime/cash-advance requests.
 *
 * Implements REQ032–REQ046, REQ076–REQ080:
 *   REQ032  HR lists all requests with filters
 *   REQ033  HR views a single request
 *   REQ034  HR pre-approves a request (Pending → HRApproved)
 *   REQ034b Owner finally approves (HRApproved → Approved) — side-effects applied here
 *   REQ035  HR or Owner rejects a request with a note
 *   REQ036  HR archives a request
 *   REQ076  Employee submits a leave request
 *   REQ077  Employee submits an overtime request
 *   REQ078  Sick-leave balance check via leave_entitlement + leave_ledger
 *   REQ079  Employee submits a cash-advance request
 *   REQ080  Employee cancels a Pending request
 *
 * Two-stage approval workflow:
 *   Pending → HR pre-approves → HRApproved → Owner approves → Approved
 *                                           → Owner returns  → Returned
 *   Returned → HR re-submits (pre-approve again) → HRApproved
 *
 * Business rules:
 *   - Only Pending or Returned requests may be pre-approved by HR.
 *   - Only HRApproved requests may be finally approved or returned by Owner.
 *   - Leave ledger debit and CashAdvance obligation are created on Owner final approval.
 *   - Leave balance is checked against the leave_entitlement before submission;
 *     if no entitlement row exists for the year, a 4-day default is auto-provisioned.
 *   - Leave days are calculated as business days (inclusive) from start_date to end_date.
 */
final class RequestService
{
    public function __construct(private Connection $connection) {}

    // -----------------------------------------------------------------------
    // Employee — REQ076–REQ080
    // -----------------------------------------------------------------------

    /**
     * Submit a new request.
     *
     * Leave requests require start_date, end_date (YYYY-MM-DD).
     * Overtime requests require overtime_date, start_time, end_time (HH:MM).
     * Cash advance requests require amount_requested (numeric string > 0).
     *
     * @param array{
     *   request_type_id:   int,
     *   reason:            string,
     *   start_date?:       string,
     *   end_date?:         string,
     *   overtime_date?:    string,
     *   start_time?:       string,
     *   end_time?:         string,
     *   amount_requested?: string,
     * } $data
     * @throws RuntimeException on validation failure or insufficient leave balance
     */
    public function submit(int $employeeId, array $data): int
    {
        $typeId = (int) ($data['request_type_id'] ?? 0);
        $reason = trim($data['reason'] ?? '');

        if ($typeId === 0) {
            throw new RuntimeException('Request type is required.');
        }
        if ($reason === '') {
            throw new RuntimeException('Reason is required.');
        }

        $typeName = $this->resolveTypeName($typeId);

        // --- Type-specific validation -----------------------------------------

        if ($typeName === 'Leave') {
            $startDate = trim($data['start_date'] ?? '');
            $endDate   = trim($data['end_date']   ?? '');

            if ($startDate === '' || !$this->isValidDate($startDate)) {
                throw new RuntimeException('Leave start date is required (YYYY-MM-DD).');
            }
            if ($endDate === '' || !$this->isValidDate($endDate)) {
                throw new RuntimeException('Leave end date is required (YYYY-MM-DD).');
            }
            if ($endDate < $startDate) {
                throw new RuntimeException('Leave end date must be on or after start date.');
            }

            $daysRequested = $this->calcLeaveDays($startDate, $endDate);
            if ($daysRequested <= 0.0) {
                throw new RuntimeException('Leave request must cover at least one day.');
            }

            // REQ078: check sick-leave balance
            $this->assertLeaveBalance($employeeId, $daysRequested);
        }

        if ($typeName === 'Overtime') {
            $overtimeDate = trim($data['overtime_date'] ?? '');
            $startTime    = trim($data['start_time']    ?? '');
            $endTime      = trim($data['end_time']      ?? '');

            if ($overtimeDate === '' || !$this->isValidDate($overtimeDate)) {
                throw new RuntimeException('Overtime date is required (YYYY-MM-DD).');
            }
            if ($startTime === '' || !$this->isValidTime($startTime)) {
                throw new RuntimeException('Overtime start time is required (HH:MM).');
            }
            if ($endTime === '' || !$this->isValidTime($endTime)) {
                throw new RuntimeException('Overtime end time is required (HH:MM).');
            }
            if ($endTime <= $startTime) {
                throw new RuntimeException('Overtime end time must be after start time.');
            }
        }

        if ($typeName === 'CashAdvance') {
            $amount = trim($data['amount_requested'] ?? '');
            if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
                throw new RuntimeException('Cash advance amount must be a positive number.');
            }
        }

        // --- Persist (header + detail in one transaction) --------------------

        return $this->connection->transaction(function (PDO $pdo) use (
            $employeeId, $typeId, $typeName, $reason, $data
        ): int {
            $stmt = $pdo->prepare(
                "INSERT INTO request
                    (employee_id, request_type_id, reason, status, submitted_at, created_at, updated_at)
                 VALUES
                    (:emp, :type, :reason, 'Pending', NOW(), NOW(), NOW())"
            );
            $stmt->execute([
                ':emp'    => $employeeId,
                ':type'   => $typeId,
                ':reason' => $reason,
            ]);
            $requestId = (int) $pdo->lastInsertId();

            // Insert type-specific detail row
            if ($typeName === 'Leave') {
                $startDate     = $data['start_date'];
                $endDate       = $data['end_date'];
                $daysRequested = $this->calcLeaveDays($startDate, $endDate);

                $pdo->prepare(
                    "INSERT INTO leave_request_detail
                        (request_id, leave_type, start_date, end_date, days_requested)
                     VALUES
                        (:rid, 'Sick', :start, :end, :days)"
                )->execute([
                    ':rid'   => $requestId,
                    ':start' => $startDate,
                    ':end'   => $endDate,
                    ':days'  => number_format($daysRequested, 2, '.', ''),
                ]);
            }

            if ($typeName === 'Overtime') {
                $startTime    = $data['start_time'];
                $endTime      = $data['end_time'];
                $minutes      = $this->calcMinutes($startTime, $endTime);

                $pdo->prepare(
                    "INSERT INTO overtime_request_detail
                        (request_id, overtime_date, start_time, end_time, requested_minutes)
                     VALUES
                        (:rid, :date, :start, :end, :mins)"
                )->execute([
                    ':rid'   => $requestId,
                    ':date'  => $data['overtime_date'],
                    ':start' => $startTime . ':00',
                    ':end'   => $endTime . ':00',
                    ':mins'  => $minutes,
                ]);
            }

            if ($typeName === 'CashAdvance') {
                $pdo->prepare(
                    "INSERT INTO cash_advance_request_detail
                        (request_id, amount)
                     VALUES
                        (:rid, :amt)"
                )->execute([
                    ':rid' => $requestId,
                    ':amt' => $data['amount_requested'],
                ]);
            }

            return $requestId;
        });
    }

    /**
     * Cancel a Pending or Returned request (employee action — REQ080).
     *
     * @throws RuntimeException when request not found, not owned by employee, or not cancellable
     */
    public function cancel(int $requestId, int $employeeId): void
    {
        $row = $this->findOrFail($requestId);

        if ((int) $row['employee_id'] !== $employeeId) {
            throw new RuntimeException('You may only cancel your own requests.');
        }
        if (!in_array($row['status'], ['Pending', 'Returned'], true)) {
            throw new RuntimeException('Only Pending or Returned requests can be cancelled.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId): void {
            $pdo->prepare(
                "UPDATE request SET status = 'Cancelled', updated_at = NOW() WHERE request_id = :id"
            )->execute([':id' => $requestId]);
        });
    }

    // -----------------------------------------------------------------------
    // HR — REQ032–REQ036
    // -----------------------------------------------------------------------

    /**
     * List all requests with optional filters.
     *
     * @param array{
     *   status?:  string,
     *   type_id?: int,
     *   search?:  string,
     * } $filters
     * @return list<array<string,mixed>>
     */
    public function list(array $filters = []): array
    {
        $where  = ['r.archived_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['type_id'])) {
            $where[]            = 'r.request_type_id = :type_id';
            $params[':type_id'] = (int) $filters['type_id'];
        }
        if (!empty($filters['search'])) {
            $where[]              = "(CONCAT(e.last_name,' ',e.first_name) LIKE :search_name
                                   OR e.employee_number LIKE :search_num)";
            $params[':search_name'] = '%' . $filters['search'] . '%';
            $params[':search_num']  = '%' . $filters['search'] . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $stmt = $this->connection->pdo()->prepare(
            "SELECT r.request_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    rt.type_name,
                    r.status,
                    r.submitted_at,
                    r.reason,
                    r.review_notes
               FROM request r
               JOIN employee e      ON e.employee_id      = r.employee_id
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
              {$whereSql}
              ORDER BY r.submitted_at DESC
              LIMIT 300"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Return a single request row (with detail payload) or throw.
     *
     * @return array<string,mixed>
     * @throws RuntimeException
     */
    public function findOrFail(int $requestId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT r.request_id,
                    r.employee_id,
                    r.request_type_id,
                    r.reason,
                    r.status,
                    r.submitted_at,
                    r.reviewed_by,
                    r.reviewed_at,
                    r.review_notes,
                    r.owner_reviewed_by,
                    r.owner_reviewed_at,
                    r.owner_notes,
                    r.archived_at,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    rt.type_name,
                    -- Leave detail
                    lrd.start_date,
                    lrd.end_date,
                    lrd.days_requested,
                    -- Overtime detail
                    ord2.overtime_date,
                    ord2.start_time   AS overtime_start_time,
                    ord2.end_time     AS overtime_end_time,
                    ord2.requested_minutes,
                    -- Cash advance detail
                    card.amount       AS amount_requested
               FROM request r
               JOIN employee e      ON e.employee_id      = r.employee_id
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
               LEFT JOIN leave_request_detail    lrd   ON lrd.request_id  = r.request_id
               LEFT JOIN overtime_request_detail ord2  ON ord2.request_id = r.request_id
               LEFT JOIN cash_advance_request_detail card ON card.request_id = r.request_id
              WHERE r.request_id = :id"
        );
        $stmt->execute([':id' => $requestId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException("Request #{$requestId} not found.");
        }
        return $row;
    }

    /**
     * HR pre-approves a Pending or Returned request (REQ034).
     *
     * Moves status to HRApproved. Side-effects (leave debit, cash-advance
     * obligation) are NOT applied here — they happen on Owner final approval.
     *
     * @throws RuntimeException when not found or not in Pending/Returned status
     */
    public function approve(int $requestId, int $actingUserId): void
    {
        $row = $this->findOrFail($requestId);

        if (!in_array($row['status'], ['Pending', 'Returned'], true)) {
            throw new RuntimeException('Only Pending or Returned requests can be pre-approved by HR.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId): void {
            $pdo->prepare(
                "UPDATE request
                    SET status      = 'HRApproved',
                        reviewed_by = :reviewer,
                        reviewed_at = NOW(),
                        updated_at  = NOW()
                  WHERE request_id  = :id"
            )->execute([':reviewer' => $actingUserId, ':id' => $requestId]);
        });
    }

    /**
     * Owner finally approves an HRApproved request (REQ034b).
     *
     * Side effects applied here:
     *   - Leave: appends a Usage ledger entry debiting the balance.
     *   - CashAdvance: creates a cash_advance_history obligation row.
     *
     * @throws RuntimeException when not found or not in HRApproved status
     */
    public function ownerApprove(int $requestId, int $actingUserId): void
    {
        $row = $this->findOrFail($requestId);

        if ($row['status'] !== 'HRApproved') {
            throw new RuntimeException('Only HR-approved requests can be finally approved by the Owner.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId, $row): void {
            $pdo->prepare(
                "UPDATE request
                    SET status             = 'Approved',
                        owner_reviewed_by  = :owner,
                        owner_reviewed_at  = NOW(),
                        updated_at         = NOW()
                  WHERE request_id = :id"
            )->execute([':owner' => $actingUserId, ':id' => $requestId]);

            // REQ078: approved Leave → debit leave_ledger
            if ($row['type_name'] === 'Leave') {
                $this->debitLeaveLedger(
                    $pdo,
                    (int) $row['employee_id'],
                    (float) $row['days_requested'],
                    $requestId,
                    $actingUserId
                );
            }

            // REQ034: approved CashAdvance → create obligation in cash_advance_history
            if ($row['type_name'] === 'CashAdvance') {
                $amount = (string) ($row['amount_requested'] ?? '0.00');
                $pdo->prepare(
                    "INSERT INTO cash_advance_history
                        (request_id, employee_id, original_amount, remaining_balance,
                         status, approved_at, created_at, updated_at)
                     VALUES
                        (:req, :emp, :amount, :amount,
                         'Active', NOW(), NOW(), NOW())"
                )->execute([
                    ':req'    => $requestId,
                    ':emp'    => $row['employee_id'],
                    ':amount' => $amount,
                ]);
            }
        });
    }

    /**
     * Owner returns an HRApproved request to HR for revision.
     *
     * Status becomes Returned so HR can address the feedback and re-submit
     * for pre-approval.
     *
     * @throws RuntimeException when not found, not HRApproved, or note is empty
     */
    public function ownerReturn(int $requestId, int $actingUserId, string $note): void
    {
        $row = $this->findOrFail($requestId);

        if ($row['status'] !== 'HRApproved') {
            throw new RuntimeException('Only HR-approved requests can be returned by the Owner.');
        }
        if (trim($note) === '') {
            throw new RuntimeException('A return note is required.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId, $note): void {
            $pdo->prepare(
                "UPDATE request
                    SET status            = 'Returned',
                        owner_reviewed_by = :owner,
                        owner_reviewed_at = NOW(),
                        owner_notes       = :note,
                        updated_at        = NOW()
                  WHERE request_id = :id"
            )->execute([':owner' => $actingUserId, ':note' => trim($note), ':id' => $requestId]);
        });
    }

    /**
     * Reject a Pending or HRApproved request with a note (HR or Owner action — REQ035).
     *
     * @throws RuntimeException when not found, not in a rejectable status, or note is empty
     */
    public function reject(int $requestId, int $actingUserId, string $note): void
    {
        $row = $this->findOrFail($requestId);

        if (!in_array($row['status'], ['Pending', 'HRApproved', 'Returned'], true)) {
            throw new RuntimeException('Only Pending, HR-approved, or Returned requests can be rejected.');
        }
        if (trim($note) === '') {
            throw new RuntimeException('A rejection note is required.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId, $note): void {
            $pdo->prepare(
                "UPDATE request
                    SET status       = 'Rejected',
                        reviewed_by  = :reviewer,
                        reviewed_at  = NOW(),
                        review_notes = :note,
                        updated_at   = NOW()
                  WHERE request_id   = :id"
            )->execute([':reviewer' => $actingUserId, ':note' => $note, ':id' => $requestId]);
        });
    }

    /**
     * Soft-archive a request (HR action — REQ036).
     *
     * @throws RuntimeException when not found or already archived
     */
    public function archive(int $requestId, int $actingUserId): void
    {
        $row = $this->findOrFail($requestId);

        if (($row['archived_at'] ?? null) !== null) {
            throw new RuntimeException('Request is already archived.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId): void {
            $pdo->prepare(
                "UPDATE request
                    SET archived_by = :by,
                        archived_at = NOW(),
                        updated_at  = NOW()
                  WHERE request_id  = :id"
            )->execute([':by' => $actingUserId, ':id' => $requestId]);
        });
    }

    /**
     * List requests awaiting Owner final approval (status = HRApproved).
     *
     * @return list<array<string,mixed>>
     */
    public function ownerPendingList(): array
    {
        $stmt = $this->connection->pdo()->query(
            "SELECT r.request_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    rt.type_name,
                    r.status,
                    r.submitted_at,
                    r.reviewed_at   AS hr_reviewed_at,
                    r.reason
               FROM request r
               JOIN employee e      ON e.employee_id      = r.employee_id
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
              WHERE r.status = 'HRApproved'
                AND r.archived_at IS NULL
              ORDER BY r.reviewed_at ASC"
        );
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Return all active request types for dropdowns.
     *
     * @return list<array{request_type_id: int, type_name: string}>
     */
    public function requestTypes(): array
    {
        return $this->connection->pdo()
            ->query("SELECT request_type_id, type_name FROM request_type WHERE status = 'Active' ORDER BY type_name")
            ->fetchAll();
    }

    /**
     * Return the current sick-leave balance for an employee in a given year.
     * Auto-provisions a 4-day entitlement row if none exists.
     */
    public function leaveBalance(int $employeeId, int $year = 0): float
    {
        if ($year === 0) {
            $year = (int) date('Y');
        }

        $pdo = $this->connection->pdo();
        $entitlementId = $this->ensureLeaveEntitlement($pdo, $employeeId, $year);

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(days_delta), 0)
               FROM leave_ledger
              WHERE entitlement_id = :eid"
        );
        $stmt->execute([':eid' => $entitlementId]);
        return (float) $stmt->fetchColumn();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function resolveTypeName(int $typeId): string
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT type_name FROM request_type WHERE request_type_id = :id"
        );
        $stmt->execute([':id' => $typeId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException("Request type #{$typeId} does not exist.");
        }
        return (string) $row['type_name'];
    }

    /**
     * REQ078: Block leave submission when sick-leave balance is insufficient.
     *
     * Uses the leave_entitlement + leave_ledger system.
     * Auto-provisions a 4-day entitlement row for the current year if none exists.
     */
    private function assertLeaveBalance(int $employeeId, float $daysRequested): void
    {
        $year    = (int) date('Y');
        $balance = $this->leaveBalance($employeeId, $year);

        if ($balance < $daysRequested) {
            $balFmt = number_format($balance, 2);
            $reqFmt = number_format($daysRequested, 2);
            throw new RuntimeException(
                "Insufficient sick-leave balance. You have {$balFmt} day(s) remaining "
                . "but requested {$reqFmt} day(s)."
            );
        }
    }

    /**
     * Ensure a leave_entitlement row exists for the employee/year.
     * If none exists, auto-provisions 4 days (ADR-0001) and writes a Grant ledger entry.
     * Returns the entitlement_id.
     */
    private function ensureLeaveEntitlement(PDO $pdo, int $employeeId, int $year): int
    {
        $stmt = $pdo->prepare(
            "SELECT entitlement_id, entitled_days
               FROM leave_entitlement
              WHERE employee_id = :emp AND leave_type = 'Sick' AND leave_year = :yr"
        );
        $stmt->execute([':emp' => $employeeId, ':yr' => $year]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return (int) $row['entitlement_id'];
        }

        // Auto-provision 4-day entitlement (ADR-0001)
        $pdo->prepare(
            "INSERT INTO leave_entitlement
                (employee_id, leave_type, leave_year, entitled_days)
             VALUES
                (:emp, 'Sick', :yr, 4.00)"
        )->execute([':emp' => $employeeId, ':yr' => $year]);
        $entitlementId = (int) $pdo->lastInsertId();

        // Grant ledger entry
        $pdo->prepare(
            "INSERT INTO leave_ledger
                (entitlement_id, request_id, entry_type, days_delta, notes)
             VALUES
                (:eid, NULL, 'Grant', 4.00, 'Annual sick leave auto-grant')"
        )->execute([':eid' => $entitlementId]);

        return $entitlementId;
    }

    /**
     * Append a Usage ledger entry debiting the sick-leave balance.
     * Called inside an existing transaction when a Leave request is approved.
     */
    private function debitLeaveLedger(
        PDO $pdo,
        int $employeeId,
        float $days,
        int $requestId,
        int $actingUserId
    ): void {
        $year          = (int) date('Y');
        $entitlementId = $this->ensureLeaveEntitlement($pdo, $employeeId, $year);

        $pdo->prepare(
            "INSERT INTO leave_ledger
                (entitlement_id, request_id, entry_type, days_delta, recorded_by, notes)
             VALUES
                (:eid, :rid, 'Usage', :delta, :by, 'Deducted on approval')"
        )->execute([
            ':eid'   => $entitlementId,
            ':rid'   => $requestId,
            ':delta' => number_format(-$days, 2, '.', ''),
            ':by'    => $actingUserId,
        ]);
    }

    /**
     * Calculate leave days inclusive of start and end (1 day per calendar day).
     * Fractional days are not supported in the current business rules.
     */
    private function calcLeaveDays(string $startDate, string $endDate): float
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
        $end   = \DateTimeImmutable::createFromFormat('Y-m-d', $endDate);

        if ($start === false || $end === false) {
            return 0.0;
        }

        $diff = $start->diff($end);
        return (float) ($diff->days + 1); // inclusive
    }

    /**
     * Calculate overtime minutes between two HH:MM time strings.
     */
    private function calcMinutes(string $startTime, string $endTime): int
    {
        [$h1, $m1] = array_map('intval', explode(':', $startTime));
        [$h2, $m2] = array_map('intval', explode(':', $endTime));
        return max(0, ($h2 * 60 + $m2) - ($h1 * 60 + $m1));
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}$/', $time);
    }
}
