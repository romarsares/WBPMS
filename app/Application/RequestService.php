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
 *   REQ034  HR approves a request
 *   REQ035  HR rejects a request with a note
 *   REQ036  HR archives a request
 *   REQ076  Employee submits a leave request
 *   REQ077  Employee submits an overtime request
 *   REQ078  Sick-leave balance check (4 days entitlement)
 *   REQ079  Employee submits a cash-advance request
 *   REQ080  Employee cancels a Pending request
 *
 * Business rules:
 *   - Only Pending requests may be updated/cancelled by the employee.
 *   - Only Pending requests may be approved/rejected by HR.
 *   - Approved cash-advance creates an obligation record (cash_advance_obligation).
 *   - Leave balance is checked against sick-leave entitlement before submission.
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
     * @param array{
     *   request_type_id: int,
     *   reason:          string,
     *   start_date?:     string,
     *   end_date?:       string,
     *   hours_requested?: float,
     *   amount_requested?: string,
     * } $data
     * @throws RuntimeException on validation failure or insufficient leave balance
     */
    public function submit(int $employeeId, array $data): int
    {
        $typeId  = (int) ($data['request_type_id'] ?? 0);
        $reason  = trim($data['reason'] ?? '');

        if ($typeId === 0) {
            throw new RuntimeException('Request type is required.');
        }
        if ($reason === '') {
            throw new RuntimeException('Reason is required.');
        }

        $typeName = $this->resolveTypeName($typeId);

        // REQ078: check sick-leave balance for Leave requests
        if ($typeName === 'Leave') {
            $this->assertSickLeaveBalance($employeeId, $data);
        }

        $requestId = $this->connection->transaction(function (PDO $pdo) use (
            $employeeId, $typeId, $reason, $data
        ): int {
            $stmt = $pdo->prepare(
                "INSERT INTO request
                    (employee_id, request_type_id, reason, status, submitted_at)
                 VALUES
                    (:emp, :type, :reason, 'Pending', NOW())"
            );
            $stmt->execute([
                ':emp'    => $employeeId,
                ':type'   => $typeId,
                ':reason' => $reason,
            ]);
            return (int) $pdo->lastInsertId();
        });

        return $requestId;
    }

    /**
     * Cancel a Pending request (employee action — REQ080).
     *
     * @throws RuntimeException when request not found, not owned by employee, or not Pending
     */
    public function cancel(int $requestId, int $employeeId): void
    {
        $row = $this->findOrFail($requestId);

        if ((int) $row['employee_id'] !== $employeeId) {
            throw new RuntimeException('You may only cancel your own requests.');
        }
        if ($row['status'] !== 'Pending') {
            throw new RuntimeException('Only Pending requests can be cancelled.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId): void {
            $pdo->prepare("UPDATE request SET status = 'Cancelled', updated_at = NOW() WHERE request_id = :id")
                ->execute([':id' => $requestId]);
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
            $where[]         = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['type_id'])) {
            $where[]          = 'r.request_type_id = :type_id';
            $params[':type_id'] = (int) $filters['type_id'];
        }
        if (!empty($filters['search'])) {
            $where[]           = "(CONCAT(e.last_name,' ',e.first_name) LIKE :search
                                   OR e.employee_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
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
     * Return a single request row or throw.
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
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    rt.type_name
               FROM request r
               JOIN employee e      ON e.employee_id      = r.employee_id
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
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
     * Approve a Pending request (HR action — REQ034).
     *
     * @throws RuntimeException when not found or not in Pending status
     */
    public function approve(int $requestId, int $actingUserId): void
    {
        $row = $this->findOrFail($requestId);

        if ($row['status'] !== 'Pending') {
            throw new RuntimeException('Only Pending requests can be approved.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($requestId, $actingUserId, $row): void {
            $pdo->prepare(
                "UPDATE request
                    SET status      = 'Approved',
                        reviewed_by = :reviewer,
                        reviewed_at = NOW(),
                        updated_at  = NOW()
                  WHERE request_id  = :id"
            )->execute([':reviewer' => $actingUserId, ':id' => $requestId]);

            // REQ034: approved cash-advance creates an obligation record
            if ($row['type_name'] === 'CashAdvance') {
                $pdo->prepare(
                    "INSERT INTO cash_advance_obligation
                        (request_id, employee_id, remaining_balance, created_at)
                     VALUES
                        (:req, :emp, 0.00, NOW())"
                )->execute([
                    ':req' => $requestId,
                    ':emp' => $row['employee_id'],
                ]);
            }
        });
    }

    /**
     * Reject a Pending request with a note (HR action — REQ035).
     *
     * @throws RuntimeException when not found or not in Pending status
     */
    public function reject(int $requestId, int $actingUserId, string $note): void
    {
        $row = $this->findOrFail($requestId);

        if ($row['status'] !== 'Pending') {
            throw new RuntimeException('Only Pending requests can be rejected.');
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

        if ($row['archived_at'] ?? null) {
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
     * REQ078: block Leave submission when sick-leave balance is insufficient.
     *
     * ADR-0001: 4 paid sick days per year. Balance = 4 − approved leave days taken.
     * @param  array<string,mixed> $data
     */
    private function assertSickLeaveBalance(int $employeeId, array $data): void
    {
        // Count days already used this calendar year (approved leave requests)
        $year  = date('Y');
        $stmt  = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM request r
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
              WHERE r.employee_id = :emp
                AND rt.type_name  = 'Leave'
                AND r.status      = 'Approved'
                AND YEAR(r.submitted_at) = :year"
        );
        $stmt->execute([':emp' => $employeeId, ':year' => $year]);
        $used = (int) $stmt->fetchColumn();

        $balance = max(0, 4 - $used); // ADR-0001: 4 sick days

        if ($balance <= 0) {
            throw new RuntimeException(
                "Insufficient sick-leave balance. You have used all {$used} of your 4 annual paid sick days."
            );
        }
    }
}

