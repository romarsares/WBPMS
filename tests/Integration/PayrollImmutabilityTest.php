<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Application\PayrollAdjustmentService;
use Wbpms\Application\PayrollService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * PayrollImmutabilityTest — Tasks 10.11 and 10.12 (partial)
 *
 * Verifies that:
 *   1. An Approved payroll run cannot be re-submitted.
 *   2. An Approved payroll run cannot be re-approved (double-approval guard).
 *   3. An Approved payroll run cannot be returned for revision.
 *   4. An Approved payroll run cannot be cancelled.
 *   5. Manual adjustments cannot be applied to an Approved run.
 *   6. An Approved run cannot be recomputed.
 *
 * These tests validate the state-machine guards across all mutation paths.
 */
final class PayrollImmutabilityTest extends IntegrationTestCase
{
    private PayrollService $payrollService;
    private PayrollAdjustmentService $adjustmentService;
    private int $hrUserId;
    private int $ownerUserId;
    private int $approvedRunId;
    private int $payrollId;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(['pdo' => $this->pdo]);
        $this->payrollService    = new PayrollService($connection);
        $this->adjustmentService = new PayrollAdjustmentService($connection);

        // Actor users
        $hrRoleId          = $this->insertRole('HRHead');
        $ownerRoleId       = $this->insertRole('BusinessOwner');
        $this->hrUserId    = $this->insertUser($hrRoleId);
        $this->ownerUserId = $this->insertUser($ownerRoleId);

        // Approved payroll run setup
        [$this->approvedRunId, $this->payrollId] = $this->buildApprovedRun();
    }

    // -----------------------------------------------------------------------
    // Guard: resubmit an already-Approved run
    // -----------------------------------------------------------------------

    public function testApprovedRunCannotBeResubmitted(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/i');
        $this->payrollService->submitForApproval($this->approvedRunId, $this->hrUserId);
    }

    // -----------------------------------------------------------------------
    // Guard: double-approve an already-Approved run
    // -----------------------------------------------------------------------

    public function testApprovedRunCannotBeDoubleApproved(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/pending approval/i');
        $this->payrollService->approve($this->approvedRunId, $this->ownerUserId);
    }

    // -----------------------------------------------------------------------
    // Guard: return an already-Approved run
    // -----------------------------------------------------------------------

    public function testApprovedRunCannotBeReturned(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/i');
        $this->payrollService->returnForRevision($this->approvedRunId, $this->ownerUserId, 'Test return');
    }

    // -----------------------------------------------------------------------
    // Guard: cancel an already-Approved run
    // -----------------------------------------------------------------------

    public function testApprovedRunCannotBeCancelled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/i');
        $this->payrollService->cancelRun($this->approvedRunId, $this->hrUserId, 'Test cancellation');
    }

    // -----------------------------------------------------------------------
    // Guard: manual adjustment on an Approved run
    // -----------------------------------------------------------------------

    public function testManualAdjustmentRejectedForApprovedRun(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/computed or returned/i');

        $this->adjustmentService->adjust(
            $this->approvedRunId,
            $this->payrollId,
            [
                'adjustment_kind' => 'earning',
                'amount'          => '100.00',
                'reason'          => 'Should fail',
            ],
            $this->hrUserId
        );
    }

    // -----------------------------------------------------------------------
    // Guard: recompute an already-Approved run
    // -----------------------------------------------------------------------

    public function testApprovedRunCannotBeRecomputed(): void
    {
        $this->expectException(RuntimeException::class);
        // computeRun only accepts Draft, Computed, or Returned
        $this->payrollService->computeRun($this->approvedRunId, $this->hrUserId);
    }

    // -----------------------------------------------------------------------
    // Positive case: Approved run data is intact
    // -----------------------------------------------------------------------

    public function testApprovedRunStatusIsApproved(): void
    {
        $run = $this->payrollService->findRunOrFail($this->approvedRunId);
        self::assertSame('Approved', $run['status']);
    }

    public function testComputeOrSubmitActorCannotApproveTheSameRun(): void
    {
        $branchId = $this->insertBranch();
        $periodId = $this->insertPayrollPeriod('2026-10-04', '2026-10-09', '2026-10-09');
        $policyId = $this->insertPayrollPolicy();
        $this->pdo->prepare(
            "INSERT INTO payroll_run
                (payroll_period_id, branch_id, payroll_policy_id, status, computed_by,
                 gross_pay, total_deductions, net_pay, lock_version, created_at, updated_at)
             VALUES (:period, :branch, :policy, 'PendingOwnerApproval', :computed_by,
                     0.00, 0.00, 0.00, 0, NOW(), NOW())"
        )->execute([
            ':period' => $periodId,
            ':branch' => $branchId,
            ':policy' => $policyId,
            ':computed_by' => $this->ownerUserId,
        ]);
        $runId = (int) $this->pdo->lastInsertId();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot approve/i');
        $this->payrollService->approve($runId, $this->ownerUserId);
    }

    public function testFutureSalaryRateDoesNotRemoveEmployeeFromCurrentPayroll(): void
    {
        $branchId = $this->insertBranch();
        $periodId = $this->insertPayrollPeriod('2026-10-04', '2026-10-09', '2026-10-09');
        $policyId = $this->insertPayrollPolicy();
        $runId = $this->insertPayrollRun($periodId, $branchId, $policyId);
        $employeeId = $this->insertEmployee();
        $this->pdo->prepare("UPDATE employee SET hire_date = '2026-01-01' WHERE employee_id = :id")
            ->execute([':id' => $employeeId]);
        $this->insertBranchAssignment($employeeId, $branchId, '2026-01-01');
        $oldSalaryId = $this->insertSalary($employeeId, '2026-01-01');
        $this->pdo->prepare(
            "UPDATE salary
                SET status = 'Superseded', effective_to = '2026-10-15'
              WHERE salary_id = :id"
        )->execute([':id' => $oldSalaryId]);
        $this->pdo->prepare(
            "INSERT INTO salary (employee_id, daily_rate, effective_from, status)
             VALUES (:employee_id, 500.00, '2026-10-16', 'Active')"
        )->execute([':employee_id' => $employeeId]);

        $midPeriodHireId = $this->insertEmployee();
        $this->pdo->prepare("UPDATE employee SET hire_date = '2026-10-06' WHERE employee_id = :id")
            ->execute([':id' => $midPeriodHireId]);
        $this->insertBranchAssignment($midPeriodHireId, $branchId, '2026-10-06');
        $this->insertSalary($midPeriodHireId, '2026-10-06');

        $this->payrollService->computeRun($runId, $this->hrUserId);

        $stmt = $this->pdo->prepare(
            'SELECT daily_rate_snapshot FROM payroll WHERE payroll_run_id = :run_id AND employee_id = :employee_id'
        );
        $stmt->execute([':run_id' => $runId, ':employee_id' => $employeeId]);
        self::assertSame('460.00', number_format((float) $stmt->fetchColumn(), 2, '.', ''));

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM payroll WHERE payroll_run_id = :run_id');
        $count->execute([':run_id' => $runId]);
        self::assertSame(2, (int) $count->fetchColumn(), 'A mid-period hire must be included in the current run.');
    }

    // -----------------------------------------------------------------------
    // Build fixture: Approved run with one employee payroll row
    // -----------------------------------------------------------------------

    /**
     * @return array{0: int, 1: int} [approvedRunId, payrollId]
     */
    private function buildApprovedRun(): array
    {
        $branchId  = $this->insertBranch();
        $periodId  = $this->insertPayrollPeriod();
        $policyId  = $this->insertPayrollPolicy();

        // Insert the run directly in PendingOwnerApproval status (bypassing
        // computation so we can use a minimal fixture without full attendance data)
        $this->pdo->prepare(
            "INSERT INTO payroll_run
                (payroll_period_id, branch_id, payroll_policy_id, status,
                 gross_pay, total_deductions, net_pay, lock_version, created_at, updated_at)
             VALUES
                (:per, :br, :pol, 'PendingOwnerApproval',
                 2300.00, 0.00, 2300.00, 0, NOW(), NOW())"
        )->execute([':per' => $periodId, ':br' => $branchId, ':pol' => $policyId]);
        $runId = (int) $this->pdo->lastInsertId();

        $employeeId    = $this->insertEmployee();
        $baId          = $this->insertBranchAssignment($employeeId, $branchId, '2026-01-01');
        $salaryId      = $this->insertSalary($employeeId, '2026-01-01');
        $payrollId     = $this->insertPayroll($runId, $periodId, $employeeId, $baId, $salaryId);

        // Owner approves — this transitions to Approved and generates a payslip
        $this->payrollService->approve($runId, $this->ownerUserId);

        return [$runId, $payrollId];
    }
}
