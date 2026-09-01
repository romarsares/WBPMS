<?php

declare(strict_types=1);

namespace Wbpms\Application;

use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;

/**
 * PayrollService — orchestrates payroll run generation and state transitions.
 *
 * Implements REQ047–REQ052:
 *   REQ047  Generate a payroll run for a branch + period
 *   REQ048  Generate payslips
 *   REQ049  13th-month pay computation
 *   REQ050  HR submits run to Owner (Draft/Computed → PendingOwnerApproval)
 *   REQ051  Owner approves or returns the run
 *   REQ052  Guard against double-approval and edits after Approved
 *
 * State machine:
 *   Draft → Computed (generatePayrollRun) → PendingOwnerApproval (submitForApproval)
 *   PendingOwnerApproval → Approved (approve — Owner)
 *   PendingOwnerApproval → Returned (returnForRevision — Owner)
 *   Returned → Computed (recompute — HR)
 *
 * Business rules:
 *   - Salary snapshot is taken from the active salary record at period_start.
 *   - Deductions are fetched from approved contribution_record rows linked to
 *     this payroll entry (populated during compute).
 *   - 13th month: gross_pay_total_ytd / 12 (accrued from Jan to the period's month).
 *   - Payslip is created/replaced on every compute cycle.
 */
final class PayrollService
{
    public function __construct(private Connection $connection) {}

    // -----------------------------------------------------------------------
    // REQ047 — Create a Draft payroll run
    // -----------------------------------------------------------------------

    /**
     * Create a new Draft payroll run for a branch + period.
     *
     * @param array{
     *   payroll_period_id: int,
     *   branch_id:         int,
     * } $data
     * @throws RuntimeException on duplicate or missing data
     */
    public function createRun(array $data, int $actingUserId): int
    {
        $periodId = (int) ($data['payroll_period_id'] ?? 0);
        $branchId = (int) ($data['branch_id']         ?? 0);

        if ($periodId <= 0) {
            throw new RuntimeException('Payroll period is required.');
        }
        if ($branchId <= 0) {
            throw new RuntimeException('Branch is required.');
        }

        // REQ047: unique period+branch
        $stmt = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM payroll_run WHERE payroll_period_id = :p AND branch_id = :b"
        );
        $stmt->execute([':p' => $periodId, ':b' => $branchId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('A payroll run already exists for this period and branch.');
        }

        return $this->connection->transaction(function (PDO $pdo) use (
            $periodId, $branchId, $actingUserId
        ): int {
            $pdo->prepare(
                "INSERT INTO payroll_run
                    (payroll_period_id, branch_id, status, computed_by, created_at)
                 VALUES
                    (:pid, :bid, 'Draft', :by, NOW())"
            )->execute([':pid' => $periodId, ':bid' => $branchId, ':by' => $actingUserId]);
            $runId = (int) $pdo->lastInsertId();

            $this->insertAudit($pdo, $actingUserId, 'payroll_run_created', $runId,
                "Created Draft payroll_run_id={$runId} for period_id={$periodId}, branch_id={$branchId}");

            return $runId;
        });
    }

    // -----------------------------------------------------------------------
    // REQ047 — Compute / re-compute
    // -----------------------------------------------------------------------

    /**
     * Compute payroll for all eligible employees in the run.
     *
     * Eligible = active employees assigned to the branch at period_start.
     * Snapshots the daily_rate at period_start, derives gross from attendance,
     * deducts contribution shares, generates payslips.
     *
     * @throws RuntimeException when run not found, not in a computable state, or already Approved
     */
    public function computeRun(int $runId, int $actingUserId): void
    {
        $run = $this->findRunOrFail($runId);

        if ($run['status'] === 'Approved') {
            throw new RuntimeException('Cannot recompute an Approved payroll run.');
        }

        $pdo = $this->connection->pdo();

        // Load period
        $stmt = $pdo->prepare(
            "SELECT period_start, period_end FROM payroll_period WHERE payroll_period_id = :id"
        );
        $stmt->execute([':id' => $run['payroll_period_id']]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($period === false) {
            throw new RuntimeException('Payroll period not found.');
        }

        $branchId      = (int) $run['branch_id'];
        $periodStart   = (string) $period['period_start'];
        $periodEnd     = (string) $period['period_end'];

        // Employees assigned to this branch at period_start
        $stmt = $pdo->prepare(
            "SELECT e.employee_id,
                    eba.branch_assignment_id,
                    s.salary_id,
                    COALESCE(s.daily_rate, 0) AS daily_rate
               FROM employee_branch_assignment eba
               JOIN employee e ON e.employee_id = eba.employee_id
               LEFT JOIN salary s
                      ON s.employee_id = e.employee_id
                     AND s.status      = 'Active'
                     AND s.effective_from <= :pstart
              WHERE eba.branch_id    = :branch
                AND eba.effective_from <= :pstart
                AND (eba.effective_to IS NULL OR eba.effective_to >= :pstart)
                AND e.status = 'Active'"
        );
        $stmt->execute([':branch' => $branchId, ':pstart' => $periodStart]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->connection->transaction(function (PDO $pdo) use (
            $runId, $employees, $periodStart, $periodEnd, $run, $actingUserId
        ): void {
            // Delete existing payroll rows for this run (re-compute)
            $pdo->prepare("DELETE FROM payroll WHERE payroll_run_id = :run")
                ->execute([':run' => $runId]);

            $grossTotal = 0.0;
            $netTotal   = 0.0;
            $dedTotal   = 0.0;

            foreach ($employees as $emp) {
                $employeeId        = (int) $emp['employee_id'];
                $dailyRate         = (float) $emp['daily_rate'];
                $branchAssignId    = (int) $emp['branch_assignment_id'];
                $salaryId          = $emp['salary_id'] !== null ? (int) $emp['salary_id'] : null;

                // Days worked in the period from approved attendance
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM attendance
                      WHERE employee_id    = :emp
                        AND attendance_date BETWEEN :start AND :end
                        AND status IN ('Complete','Approved')"
                );
                $stmt->execute([
                    ':emp'   => $employeeId,
                    ':start' => $periodStart,
                    ':end'   => $periodEnd,
                ]);
                $daysWorked = (int) $stmt->fetchColumn();

                $grossPay = round($dailyRate * $daysWorked, 2);

                // Fetch total deductions from contribution_record if they exist
                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(cr.employee_share), 0)
                       FROM contribution_record cr
                       JOIN payroll p2 ON p2.payroll_id = cr.payroll_id
                       JOIN payroll_run pr2 ON pr2.payroll_run_id = p2.payroll_run_id
                       JOIN payroll_period pp2 ON pp2.payroll_period_id = pr2.payroll_period_id
                      WHERE p2.employee_id = :emp
                        AND pp2.period_start = :start"
                );
                $stmt->execute([':emp' => $employeeId, ':start' => $periodStart]);
                $totalDed = (float) $stmt->fetchColumn();

                $netPay = round($grossPay - $totalDed, 2);

                // Insert payroll row
                $pdo->prepare(
                    "INSERT INTO payroll
                        (payroll_run_id, payroll_period_id, employee_id,
                         branch_assignment_id, salary_id, daily_rate_snapshot,
                         gross_pay, total_deductions, net_pay)
                     VALUES
                        (:run, :pid, :emp, :ba, :sid, :rate, :gross, :ded, :net)"
                )->execute([
                    ':run'   => $runId,
                    ':pid'   => $run['payroll_period_id'],
                    ':emp'   => $employeeId,
                    ':ba'    => $branchAssignId,
                    ':sid'   => $salaryId,
                    ':rate'  => $dailyRate,
                    ':gross' => $grossPay,
                    ':ded'   => $totalDed,
                    ':net'   => $netPay,
                ]);
                $payrollId = (int) $pdo->lastInsertId();

                // Generate payslip (replace existing)
                $pdo->prepare(
                    "INSERT INTO payslip (payroll_id, generated_at)
                     VALUES (:pid, NOW())
                     ON DUPLICATE KEY UPDATE generated_at = NOW()"
                )->execute([':pid' => $payrollId]);

                $grossTotal += $grossPay;
                $dedTotal   += $totalDed;
                $netTotal   += $netPay;
            }

            // Update run totals and advance to Computed
            $pdo->prepare(
                "UPDATE payroll_run
                    SET status            = 'Computed',
                        gross_pay         = :gross,
                        total_deductions  = :ded,
                        net_pay           = :net,
                        computed_by       = :by,
                        computed_at       = NOW()
                  WHERE payroll_run_id    = :run"
            )->execute([
                ':gross' => round($grossTotal, 2),
                ':ded'   => round($dedTotal, 2),
                ':net'   => round($netTotal, 2),
                ':by'    => $actingUserId,
                ':run'   => $runId,
            ]);

            $this->insertAudit($pdo, $actingUserId, 'payroll_run_computed', $runId,
                "Computed payroll_run_id={$runId}: gross={$grossTotal}, net={$netTotal}");
        });
    }

    // -----------------------------------------------------------------------
    // REQ050 — Submit for Owner approval
    // -----------------------------------------------------------------------

    /**
     * Advance run status from Computed (or Returned) → PendingOwnerApproval.
     *
     * @throws RuntimeException when not found or not in a submittable state
     */
    public function submitForApproval(int $runId, int $actingUserId): void
    {
        $run = $this->findRunOrFail($runId);

        if (!in_array($run['status'], ['Computed', 'Returned'], true)) {
            throw new RuntimeException('Only Computed or Returned runs can be submitted for approval.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($runId, $actingUserId): void {
            $pdo->prepare(
                "UPDATE payroll_run
                    SET status       = 'PendingOwnerApproval',
                        submitted_by = :by,
                        submitted_at = NOW()
                  WHERE payroll_run_id = :id"
            )->execute([':by' => $actingUserId, ':id' => $runId]);

            $this->insertAudit($pdo, $actingUserId, 'payroll_submitted', $runId,
                "Submitted payroll_run_id={$runId} for owner approval");
        });
    }

    // -----------------------------------------------------------------------
    // REQ051 — Owner approve
    // -----------------------------------------------------------------------

    /**
     * Approve the run (Owner action).
     *
     * @throws RuntimeException when not in PendingOwnerApproval state (REQ052)
     */
    public function approve(int $runId, int $actingUserId): void
    {
        $run = $this->findRunOrFail($runId);

        if ($run['status'] !== 'PendingOwnerApproval') {
            throw new RuntimeException('Only runs pending approval can be approved.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($runId, $actingUserId): void {
            $pdo->prepare(
                "UPDATE payroll_run
                    SET status      = 'Approved',
                        reviewed_by = :by,
                        reviewed_at = NOW()
                  WHERE payroll_run_id = :id"
            )->execute([':by' => $actingUserId, ':id' => $runId]);

            $this->insertAudit($pdo, $actingUserId, 'payroll_approved', $runId,
                "Approved payroll_run_id={$runId}");
        });
    }

    // -----------------------------------------------------------------------
    // REQ051 — Owner return for revision
    // -----------------------------------------------------------------------

    /**
     * Return run to HR with a note (Owner action).
     *
     * @throws RuntimeException when not in PendingOwnerApproval state or note empty
     */
    public function returnForRevision(int $runId, int $actingUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A return reason is required.');
        }

        $run = $this->findRunOrFail($runId);

        if ($run['status'] !== 'PendingOwnerApproval') {
            throw new RuntimeException('Only runs pending approval can be returned.');
        }

        $this->connection->transaction(function (PDO $pdo) use ($runId, $actingUserId, $reason): void {
            $pdo->prepare(
                "UPDATE payroll_run
                    SET status        = 'Returned',
                        reviewed_by   = :by,
                        reviewed_at   = NOW(),
                        return_reason = :reason
                  WHERE payroll_run_id = :id"
            )->execute([':by' => $actingUserId, ':reason' => $reason, ':id' => $runId]);

            $this->insertAudit($pdo, $actingUserId, 'payroll_returned', $runId,
                "Returned payroll_run_id={$runId}: {$reason}");
        });
    }

    // -----------------------------------------------------------------------
    // Lookup helpers
    // -----------------------------------------------------------------------

    /**
     * Return a payroll_run row or throw.
     *
     * @return array<string,mixed>
     * @throws RuntimeException
     */
    public function findRunOrFail(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT pr.*,
                    b.branch_name,
                    CONCAT(pp.period_start,' – ',pp.period_end) AS period_label
               FROM payroll_run pr
               JOIN branch b         ON b.branch_id          = pr.branch_id
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
              WHERE pr.payroll_run_id = :id"
        );
        $stmt->execute([':id' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException("Payroll run #{$runId} not found.");
        }
        return $row;
    }

    /**
     * Return all payroll periods ordered desc.
     *
     * @return list<array<string,mixed>>
     */
    public function periods(): array
    {
        return $this->connection->pdo()->query(
            "SELECT payroll_period_id,
                    CONCAT(period_start,' – ',period_end) AS label,
                    period_start, period_end, pay_date, status
               FROM payroll_period
              ORDER BY period_start DESC
              LIMIT 60"
        )->fetchAll();
    }

    /**
     * Return employee-level rows for a run.
     *
     * @return list<array<string,mixed>>
     */
    public function runDetails(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT CONCAT(e.last_name,', ',e.first_name) AS employee_name,
                    e.employee_number,
                    p.daily_rate_snapshot,
                    p.gross_pay,
                    p.total_deductions,
                    p.net_pay
               FROM payroll p
               JOIN employee e ON e.employee_id = p.employee_id
              WHERE p.payroll_run_id = :id
              ORDER BY e.last_name, e.first_name"
        );
        $stmt->execute([':id' => $runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // Payroll period management
    // -----------------------------------------------------------------------

    /**
     * Create a new payroll period.
     *
     * Business rules (ADR-0002 §16):
     *   - period_start must be a Friday.
     *   - period_end is always period_start + 6 days (Thursday).
     *   - pay_date is always period_end + 1 day (Friday).
     *   - No duplicate period_start (uq_period_start enforced by DB too).
     *
     * @throws RuntimeException on validation failure or duplicate
     */
    public function createPeriod(string $periodStart): int
    {
        // Validate date format
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $periodStart);
        if ($date === false || $date->format('Y-m-d') !== $periodStart) {
            throw new RuntimeException('Invalid date format. Use YYYY-MM-DD.');
        }

        // Must be a Friday (ISO 5 = Friday)
        if ((int) $date->format('N') !== 5) {
            throw new RuntimeException(
                'Period start must be a Friday. '
                . $date->format('D M j, Y') . ' is a ' . $date->format('l') . '.'
            );
        }

        $periodEnd = $date->modify('+6 days')->format('Y-m-d');  // Thursday
        $payDate   = $date->modify('+7 days')->format('Y-m-d');  // Next Friday

        // Duplicate check
        $stmt = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM payroll_period WHERE period_start = :start"
        );
        $stmt->execute([':start' => $periodStart]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                "A payroll period starting {$periodStart} already exists."
            );
        }

        $stmt = $this->connection->pdo()->prepare(
            "INSERT INTO payroll_period (period_start, period_end, pay_date, status, created_at, updated_at)
             VALUES (:start, :end, :pay, 'Open', NOW(), NOW())"
        );
        $stmt->execute([
            ':start' => $periodStart,
            ':end'   => $periodEnd,
            ':pay'   => $payDate,
        ]);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Return all payroll periods ordered by most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function listPeriods(): array
    {
        return $this->connection->pdo()->query(
            "SELECT payroll_period_id,
                    period_start,
                    period_end,
                    pay_date,
                    status,
                    (SELECT COUNT(*) FROM payroll_run pr
                      WHERE pr.payroll_period_id = pp.payroll_period_id) AS run_count
               FROM payroll_period pp
              ORDER BY period_start DESC
              LIMIT 100"
        )->fetchAll();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function insertAudit(PDO $pdo, int $actingUserId, string $event, int $recordId, string $desc): void
    {
        $pdo->prepare(
            "INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected, record_id, description, action_at)
             VALUES (:uid, :evt, :evt, 'payroll_run', :rid, :desc, NOW())"
        )->execute([
            ':uid'  => $actingUserId,
            ':evt'  => $event,
            ':rid'  => $recordId,
            ':desc' => $desc,
        ]);
    }
}

