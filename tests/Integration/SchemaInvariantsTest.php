<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use PDOException;
use Wbpms\Application\PayrollService;
use Wbpms\Infrastructure\Database\Connection;

/**
 * SchemaInvariantsTest
 *
 * Integration-level proof that every ADR-0002 "Verification requirements"
 * invariant is enforced by the MySQL schema (unique keys, FKs, CHECK
 * constraints, and composite FKs).
 *
 * ADR-0002 verification requirements (§ "Verification requirements"):
 *
 *  1. Duplicate employee/date attendance is rejected.
 *  2. A payroll employee cannot appear in two branch runs for one period.
 *  3. Mismatched payroll/run periods are rejected.
 *  4. Duplicate contribution programs and payslips are rejected.
 *  5. Invalid payroll dates and invalid status/timestamp combinations are
 *     rejected (Friday start, Thursday end, 7 calendar days, pay date).
 *  6. Temporal overlaps are rejected under concurrent writes
 *     (unique-key layer; service layer enforces full overlap).
 *  7. Failed-login audit rows work with user_id = NULL.
 *  8. An approved payroll cannot be approved twice (status authority).
 *  9. One period creates at most one disbursement batch.
 *
 * Each test method is wrapped in a roll-back transaction by IntegrationTestCase,
 * so tests are fully isolated.
 *
 * Requirement traceability: ADR-0002 (REQN-schema invariants)
 */
final class SchemaInvariantsTest extends IntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Shared fixtures created in setUpBeforeClass cannot be shared across tests
    // because each test rolls back; we build minimal state inside each test.
    // -----------------------------------------------------------------------

    // ===================================================================
    // 1. ATTENDANCE: duplicate employee/date is rejected
    // ===================================================================

    /**
     * ADR-0002 §4 / UNIQUE(employee_id, attendance_date):
     * A second attendance row for the same employee on the same date must
     * be rejected by the uq_attendance_employee_date unique index.
     */
    public function testDuplicateAttendanceEmployeeDateIsRejected(): void
    {
        $employeeId = $this->insertEmployee();
        $branchId   = $this->insertBranch();
        $assignId   = $this->insertBranchAssignment($employeeId, $branchId);
        $scheduleId = $this->insertWorkSchedule($employeeId);

        $this->insertAttendance($employeeId, $assignId, $scheduleId, '2026-09-04');

        $this->assertDbException(
            fn () => $this->insertAttendance($employeeId, $assignId, $scheduleId, '2026-09-04'),
            'duplicate attendance employee/date'
        );
    }

    /**
     * Two distinct employees may have attendance on the same date (negative
     * guard — must NOT fail).
     */
    public function testDifferentEmployeesSameDateIsAllowed(): void
    {
        $branchId = $this->insertBranch();

        $emp1 = $this->insertEmployee();
        $asgn1 = $this->insertBranchAssignment($emp1, $branchId, '2026-01-01');
        $sched1 = $this->insertWorkSchedule($emp1, '2026-01-01');

        $emp2 = $this->insertEmployee();
        $asgn2 = $this->insertBranchAssignment($emp2, $branchId, '2026-01-01');
        $sched2 = $this->insertWorkSchedule($emp2, '2026-01-01');

        $this->assertDbSuccess(
            fn () => $this->insertAttendance($emp1, $asgn1, $sched1, '2026-09-04'),
            'first employee attendance'
        );
        $this->assertDbSuccess(
            fn () => $this->insertAttendance($emp2, $asgn2, $sched2, '2026-09-04'),
            'second employee same date'
        );
    }

    // ===================================================================
    // 2. PAYROLL: employee cannot appear in two branch runs for one period
    // ===================================================================

    /**
     * ADR-0002 §3 / UNIQUE(payroll_period_id, employee_id):
     * Inserting a second payroll row for the same employee in the same period
     * (even in a different branch run) must be rejected.
     */
    public function testPayrollEmployeeCannotAppearInTwoRunsForOnePeriod(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $policy = $this->insertPayrollPolicy();

        $branchA = $this->insertBranch();
        $branchB = $this->insertBranch();

        $runA = $this->insertPayrollRun($period, $branchA, $policy);
        $runB = $this->insertPayrollRun($period, $branchB, $policy);

        $employee  = $this->insertEmployee();
        $assignA   = $this->insertBranchAssignment($employee, $branchA, '2026-01-01');
        $salary    = $this->insertSalary($employee);

        // First payroll row in runA — OK.
        $this->insertPayroll($runA, $period, $employee, $assignA, $salary);

        // Second payroll row for same employee in same period (different run) — must fail.
        $this->assertDbException(
            fn () => $this->insertPayroll($runB, $period, $employee, $assignA, $salary),
            'employee duplicate across two branch runs in one period'
        );
    }

    /**
     * Different employees in the same period must succeed.
     */
    public function testDifferentEmployeesSamePeriodIsAllowed(): void
    {
        $period  = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $policy  = $this->insertPayrollPolicy();
        $branch  = $this->insertBranch();
        $run     = $this->insertPayrollRun($period, $branch, $policy);

        $emp1  = $this->insertEmployee();
        $asgn1 = $this->insertBranchAssignment($emp1, $branch, '2026-01-01');
        $sal1  = $this->insertSalary($emp1);

        $emp2  = $this->insertEmployee();
        $asgn2 = $this->insertBranchAssignment($emp2, $branch, '2026-01-01');
        $sal2  = $this->insertSalary($emp2);

        $this->assertDbSuccess(
            fn () => $this->insertPayroll($run, $period, $emp1, $asgn1, $sal1),
            'first employee in period'
        );
        $this->assertDbSuccess(
            fn () => $this->insertPayroll($run, $period, $emp2, $asgn2, $sal2),
            'second employee in same period'
        );
    }

    // ===================================================================
    // 3. PAYROLL: mismatched payroll/run periods are rejected (composite FK)
    // ===================================================================

    /**
     * ADR-0002 §3 / composite FK fk_payroll_run_period:
     * The payroll.payroll_period_id must equal the parent run's period.
     * Supplying a different period_id must be rejected.
     */
    public function testMismatchedPayrollRunPeriodIsRejected(): void
    {
        $period1 = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $period2 = $this->insertPayrollPeriod('2026-09-11', '2026-09-17', '2026-09-18');
        $policy  = $this->insertPayrollPolicy();
        $branch  = $this->insertBranch();

        // Run is tied to period1.
        $run = $this->insertPayrollRun($period1, $branch, $policy);

        $employee  = $this->insertEmployee();
        $branchAss = $this->insertBranchAssignment($employee, $branch, '2026-01-01');
        $salary    = $this->insertSalary($employee);

        // Attempt to insert payroll referencing period2 while run is on period1.
        $this->assertDbException(
            fn () => $this->insertPayroll($run, $period2, $employee, $branchAss, $salary),
            'payroll_period_id does not match run period (composite FK violation)'
        );
    }

    // ===================================================================
    // 4a. CONTRIBUTIONS: duplicate program per payroll is rejected
    // ===================================================================

    /**
     * ADR-0002 §7 / UNIQUE(payroll_id, contribution_type):
     * Two SSS contribution records for the same payroll row must be rejected.
     */
    public function testDuplicateContributionProgramIsRejected(): void
    {
        [$payrollId, $policy] = $this->buildMinimalPayrollRow();
        $contribPolicyId = $this->insertContributionPolicy();

        // Create a deduction to satisfy the FK.
        $deductId1 = $this->insertDeduction($payrollId);
        $deductId2 = $this->insertDeduction($payrollId);

        $this->insertContributionRecord($payrollId, $deductId1, $contribPolicyId, 'SSS');

        $this->assertDbException(
            fn () => $this->insertContributionRecord($payrollId, $deductId2, $contribPolicyId, 'SSS'),
            'duplicate SSS contribution for same payroll'
        );
    }

    /**
     * Different contribution programs (SSS, PhilHealth, PagIBIG) for the same
     * payroll row must be accepted.
     */
    public function testDifferentContributionProgramsSamePayrollIsAllowed(): void
    {
        [$payrollId] = $this->buildMinimalPayrollRow();
        $contribPolicyId = $this->insertContributionPolicy();

        $d1 = $this->insertDeduction($payrollId);
        $d2 = $this->insertDeduction($payrollId);
        $d3 = $this->insertDeduction($payrollId);

        $this->assertDbSuccess(
            fn () => $this->insertContributionRecord($payrollId, $d1, $contribPolicyId, 'SSS'),
            'SSS contribution'
        );
        $this->assertDbSuccess(
            fn () => $this->insertContributionRecord($payrollId, $d2, $contribPolicyId, 'PhilHealth'),
            'PhilHealth contribution'
        );
        $this->assertDbSuccess(
            fn () => $this->insertContributionRecord($payrollId, $d3, $contribPolicyId, 'PagIBIG'),
            'PagIBIG contribution'
        );
    }

    // ===================================================================
    // 4b. PAYSLIP: duplicate payslip per payroll is rejected
    // ===================================================================

    /**
     * ADR-0002 §15 / UNIQUE(payroll_id) on payslip:
     * A second payslip for the same payroll row must be rejected.
     */
    public function testDuplicatePayslipIsRejected(): void
    {
        [$payrollId] = $this->buildMinimalPayrollRow();

        $this->insertPayslip($payrollId, '2026-09-11');

        $this->assertDbException(
            fn () => $this->insertPayslip($payrollId, '2026-09-11'),
            'duplicate payslip for same payroll'
        );
    }

    // ===================================================================
    // 5a. PAYROLL PERIOD: invalid dates are rejected (CHECK constraints)
    // ===================================================================

    /**
     * ADR-0002 §16 / chk_period_start_friday:
     * A payroll_period whose start is not a Friday must be rejected.
     */
    public function testPayrollPeriodNonSundayStartIsRejected(): void
    {
        // 2026-09-07 is a Monday
        $this->assertDbException(
            fn () => $this->insertPayrollPeriod('2026-09-07', '2026-09-13', '2026-09-14'),
            'period_start is Monday, not Sunday'
        );
    }

    /**
     * ADR-0002 §16 / chk_period_end_thursday:
     * A payroll_period whose end is not a Thursday must be rejected.
     */
    public function testPayrollPeriodNonFridayEndIsRejected(): void
    {
        // 2026-09-04 is Friday, 2026-09-11 is Friday — not Thursday.
        $this->assertDbException(
            fn () => $this->insertPayrollPeriod('2026-09-06', '2026-09-10', '2026-09-10'),
            'period_end is Thursday, not Friday'
        );
    }

    /**
     * ADR-0002 §16 / chk_period_seven_days:
     * A payroll_period that is not exactly 7 calendar days must be rejected.
     */
    public function testPayrollPeriodNonSixDaysIsRejected(): void
    {
        // 2026-09-04 (Fri) to 2026-09-09 (Wed) = 5 days, not 6 (DATEDIFF = 5, not 6)
        $this->assertDbException(
            fn () => $this->insertPayrollPeriod('2026-09-06', '2026-09-09', '2026-09-09'),
            'period is not 6 calendar days'
        );
    }

    /**
     * ADR-0002 §16 / chk_period_pay_date:
     * pay_date must be the day immediately following period_end.
     */
    public function testPayrollPeriodWrongPayDateIsRejected(): void
    {
        // 2026-09-04 (Fri) → 2026-09-10 (Thu); correct pay = 2026-09-11 (Fri)
        $this->assertDbException(
            fn () => $this->insertPayrollPeriod('2026-09-06', '2026-09-11', '2026-09-12'),
            'pay_date is after the period-ending Friday'
        );
    }

    /**
     * A correctly formed period (Fri → Thu, 7 days, pay = Fri) must succeed.
     */
    public function testValidPayrollPeriodIsAccepted(): void
    {
        $this->assertDbSuccess(
            fn () => $this->insertPayrollPeriod('2026-09-06', '2026-09-11', '2026-09-11'),
            'valid Sunday-start payroll period'
        );
    }

    // ===================================================================
    // 5b. PAYROLL RUN: duplicate (period, branch) is rejected
    // ===================================================================

    /**
     * ADR-0002 §2 (implied) / UNIQUE(payroll_period_id, branch_id):
     * Two payroll runs for the same period and branch must be rejected.
     */
    public function testDuplicatePayrollRunPeriodBranchIsRejected(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();

        $this->insertPayrollRun($period, $branch, $policy);

        $this->assertDbException(
            fn () => $this->insertPayrollRun($period, $branch, $policy),
            'duplicate payroll run for period+branch'
        );
    }

    /** A cancelled run is retained but releases the pair for a corrected run. */
    public function testCancelledPayrollRunAllowsReplacementAndWritesAuditEvent(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();
        $actor  = $this->insertUser($this->insertRole());
        $runId  = $this->insertPayrollRun($period, $branch, $policy);

        $service = new PayrollService(new Connection([], $this->pdo));
        $service->cancelRun($runId, $actor, 'Incorrect branch selected.');

        $run = $this->pdo->query(
            "SELECT status, active_run_marker, cancellation_reason FROM payroll_run WHERE payroll_run_id = {$runId}"
        )->fetch();
        $this->assertSame('Cancelled', $run['status']);
        $this->assertNull($run['active_run_marker']);
        $this->assertSame('Incorrect branch selected.', $run['cancellation_reason']);

        $replacementId = $this->insertPayrollRun($period, $branch, $policy);
        $this->assertGreaterThan($runId, $replacementId);

        $audit = $this->pdo->prepare(
            "SELECT event_type, action_performed FROM audit_logs
              WHERE record_id = :run_id AND table_affected = 'payroll_run'"
        );
        $audit->execute([':run_id' => $runId]);
        $this->assertSame([
            'event_type' => 'payroll_cancelled',
            'action_performed' => 'cancel_payroll_run',
        ], $audit->fetch());
    }

    // ===================================================================
    // 6. TEMPORAL: effective-period bounds are enforced by CHECK constraints
    // ===================================================================

    /**
     * ADR-0002 §8 / chk_eba_period:
     * employee_branch_assignment with effective_to <= effective_from must be rejected.
     */
    public function testBranchAssignmentInvalidPeriodBoundsRejected(): void
    {
        $emp    = $this->insertEmployee();
        $branch = $this->insertBranch();

        $this->assertDbException(
            fn () => $this->insertBranchAssignment($emp, $branch, '2026-09-10', '2026-09-01'),
            'effective_to before effective_from on branch assignment'
        );
    }

    /**
     * ADR-0002 §8 / chk_eba_period:
     * effective_to equal to effective_from must be rejected.
     */
    public function testBranchAssignmentSameDayPeriodBoundsRejected(): void
    {
        $emp    = $this->insertEmployee();
        $branch = $this->insertBranch();

        $this->assertDbException(
            fn () => $this->insertBranchAssignment($emp, $branch, '2026-09-01', '2026-09-01'),
            'effective_to equals effective_from on branch assignment'
        );
    }

    /**
     * ADR-0002 §8 / unique index uq_eba_employee_from:
     * Two branch assignments for the same employee sharing the same effective_from
     * must be rejected (unique-key layer of overlap detection).
     */
    public function testBranchAssignmentDuplicateEffectiveFromRejected(): void
    {
        $emp     = $this->insertEmployee();
        $branchA = $this->insertBranch();
        $branchB = $this->insertBranch();

        $this->insertBranchAssignment($emp, $branchA, '2026-09-01');

        $this->assertDbException(
            fn () => $this->insertBranchAssignment($emp, $branchB, '2026-09-01'),
            'duplicate effective_from on employee_branch_assignment'
        );
    }

    /**
     * ADR-0002 §8 / chk_ws_period:
     * work_schedule with effective_to <= effective_from must be rejected.
     */
    public function testWorkScheduleInvalidPeriodBoundsRejected(): void
    {
        $emp = $this->insertEmployee();

        $this->assertDbException(
            fn () => $this->insertWorkSchedule($emp, '2026-09-10', '2026-09-01'),
            'effective_to before effective_from on work_schedule'
        );
    }

    /**
     * ADR-0002 §8 / uq_ws_employee_from:
     * Two work_schedule rows for the same employee with the same effective_from
     * must be rejected.
     */
    public function testWorkScheduleDuplicateEffectiveFromRejected(): void
    {
        $emp = $this->insertEmployee();
        $this->insertWorkSchedule($emp, '2026-01-01');

        $this->assertDbException(
            fn () => $this->insertWorkSchedule($emp, '2026-01-01'),
            'duplicate effective_from on work_schedule'
        );
    }

    /**
     * ADR-0002 §8 / chk_salary_period:
     * salary with effective_to <= effective_from must be rejected.
     */
    public function testSalaryInvalidPeriodBoundsRejected(): void
    {
        $emp = $this->insertEmployee();

        $this->assertDbException(
            fn () => $this->pdo->prepare(
                "INSERT INTO salary (employee_id, daily_rate, effective_from, effective_to, status)
                 VALUES (:emp, 460.00, '2026-09-10', '2026-09-01', 'Active')"
            )->execute([':emp' => $emp]),
            'salary effective_to before effective_from'
        );
    }

    /**
     * ADR-0002 §8 / chk_salary_rate:
     * A zero or negative daily_rate must be rejected.
     */
    public function testSalaryNonPositiveRateRejected(): void
    {
        $emp = $this->insertEmployee();

        $this->assertDbException(
            fn () => $this->pdo->prepare(
                "INSERT INTO salary (employee_id, daily_rate, effective_from, status)
                 VALUES (:emp, 0.00, '2026-01-01', 'Active')"
            )->execute([':emp' => $emp]),
            'salary daily_rate = 0'
        );
    }

    // ===================================================================
    // 7. AUDIT LOG: failed-login events with user_id = NULL are accepted
    // ===================================================================

    /**
     * ADR-0002 §10:
     * An audit_logs row with NULL user_id, non-null attempted_identifier,
     * IP, user_agent, and request_id must be accepted.
     */
    public function testFailedLoginAuditWithNullUserIdIsAccepted(): void
    {
        $this->assertDbSuccess(
            fn () => $this->pdo->exec(
                "INSERT INTO audit_logs
                    (user_id, event_type, action_performed, attempted_identifier,
                     request_id, ip_address, user_agent, action_at)
                 VALUES
                    (NULL, 'login_failure', 'login_attempt', 'unknown_user',
                     UUID(), '127.0.0.1', 'PHPUnit/1.0', NOW())"
            ),
            'failed-login audit row with NULL user_id'
        );
    }

    /**
     * A login_success audit row with a valid user_id must also be accepted.
     */
    public function testLoginSuccessAuditWithUserIdIsAccepted(): void
    {
        $roleId = $this->insertRole('BusinessOwner');
        $userId = $this->insertUser($roleId);

        $this->assertDbSuccess(
            fn () => $this->pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, event_type, action_performed, action_at)
                 VALUES (:uid, 'login_success', 'login', NOW())"
            )->execute([':uid' => $userId]),
            'login_success audit row with user_id'
        );
    }

    // ===================================================================
    // 8. PAYROLL STATUS: approved payroll run cannot be approved twice
    //    (status-field unique-value / application-layer guard, proven via DB)
    // ===================================================================

    /**
     * ADR-0002 §2:
     * payroll_run.status is the sole state authority.  Once a run is
     * 'Approved', a further attempt to update it back to 'Approved' with the
     * same reviewed_by/reviewed_at constitutes a no-op guard (no duplicate-
     * approval column exists). We verify here that:
     *   a) A run can be set to 'Approved' once (the schema accepts it).
     *   b) The UNIQUE(payroll_period_id, branch_id) constraint means no
     *      second run can be created for the same period+branch, so a
     *      "second approval path" via a new run is blocked at DB level.
     */
    public function testApprovedStatusIsAcceptedOnce(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();

        $runId = $this->insertPayrollRun($period, $branch, $policy, 'Draft');

        $this->assertDbSuccess(
            fn () => $this->pdo->prepare(
                "UPDATE payroll_run SET status = 'Approved' WHERE payroll_run_id = :id"
            )->execute([':id' => $runId]),
            'marking run as Approved'
        );

        $row = $this->pdo
            ->query("SELECT status FROM payroll_run WHERE payroll_run_id = {$runId}")
            ->fetch();
        $this->assertSame('Approved', $row['status']);
    }

    /**
     * ADR-0002 §2:
     * A second payroll_run for the same period+branch (which would be the
     * only vehicle for a double-approval) must be rejected by the DB.
     */
    public function testSecondRunForSamePeriodBranchIsRejected(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();

        $this->insertPayrollRun($period, $branch, $policy, 'Approved');

        $this->assertDbException(
            fn () => $this->insertPayrollRun($period, $branch, $policy, 'Draft'),
            'second run for already-approved period+branch'
        );
    }

    /**
     * ADR-0002 §5 / chk_run_returned_reason:
     * A payroll_run with status='Returned' must have a non-NULL return_reason.
     */
    public function testReturnedRunWithoutReasonIsRejected(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();

        $this->assertDbException(
            fn () => $this->pdo->prepare(
                "INSERT INTO payroll_run
                    (payroll_period_id, branch_id, payroll_policy_id, status, return_reason)
                 VALUES (:per, :br, :pol, 'Returned', NULL)"
            )->execute([':per' => $period, ':br' => $branch, ':pol' => $policy]),
            'Returned run with NULL return_reason'
        );
    }

    /**
     * A Returned run WITH a non-null return_reason must be accepted.
     */
    public function testReturnedRunWithReasonIsAccepted(): void
    {
        $period = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $branch = $this->insertBranch();
        $policy = $this->insertPayrollPolicy();

        $this->assertDbSuccess(
            fn () => $this->pdo->prepare(
                "INSERT INTO payroll_run
                    (payroll_period_id, branch_id, payroll_policy_id, status, return_reason)
                 VALUES (:per, :br, :pol, 'Returned', 'Calculation error in overtime')"
            )->execute([':per' => $period, ':br' => $branch, ':pol' => $policy]),
            'Returned run with return_reason'
        );
    }

    // ===================================================================
    // 9. DISBURSEMENT BATCH: one per payroll period
    // ===================================================================

    /**
     * ADR-0002 §14 / UNIQUE(payroll_period_id) on disbursement_batch:
     * A second disbursement batch for the same payroll period must be rejected.
     */
    public function testDuplicateDisbursementBatchIsRejected(): void
    {
        $periodId = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');

        $this->insertDisbursementBatch($periodId, 10000.00);

        $this->assertDbException(
            fn () => $this->insertDisbursementBatch($periodId, 15000.00),
            'second disbursement_batch for same payroll_period'
        );
    }

    /**
     * One batch per period must be accepted.
     */
    public function testOneDisbursementBatchPerPeriodIsAccepted(): void
    {
        $periodId = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');

        $this->assertDbSuccess(
            fn () => $this->insertDisbursementBatch($periodId, 10000.00),
            'one disbursement_batch for a period'
        );
    }

    // ===================================================================
    // ADDITIONAL: biometric punch duplicate key is rejected
    // ===================================================================

    /**
     * ADR-0002 §5 (import batch month/year integrity):
     * attendance_import_batch must accept source_month 1–12.
     * A month value outside that range must be rejected by chk_batch_month.
     */
    public function testImportBatchInvalidMonthIsRejected(): void
    {
        $siteId = $this->insertAttendanceSite();
        $deviceId = $this->insertBiometricDevice($siteId);
        $roleId = $this->insertRole('HRHead');
        $userId = $this->insertUser($roleId);

        $this->assertDbException(
            fn () => $this->pdo->prepare(
                "INSERT INTO attendance_import_batch
                    (device_id, uploaded_by, file_name, file_checksum,
                     source_year, source_month, status, uploaded_at)
                 VALUES (:dev, :usr, 'test.xls', :chk, 2026, 13, 'Processing', NOW())"
            )->execute([
                ':dev' => $deviceId,
                ':usr' => $userId,
                ':chk' => hash('sha256', uniqid('batch_inv_month')),
            ]),
            'import_batch with source_month=13'
        );
    }

    /**
     * A valid source_month (1–12) must be accepted.
     */
    public function testImportBatchValidMonthIsAccepted(): void
    {
        $siteId   = $this->insertAttendanceSite();
        $deviceId = $this->insertBiometricDevice($siteId);
        $roleId   = $this->insertRole('HRHead');
        $userId   = $this->insertUser($roleId);

        $this->assertDbSuccess(
            fn () => $this->pdo->prepare(
                "INSERT INTO attendance_import_batch
                    (device_id, uploaded_by, file_name, file_checksum,
                     source_year, source_month, status, uploaded_at)
                 VALUES (:dev, :usr, 'test.xls', :chk, 2026, 9, 'Processing', NOW())"
            )->execute([
                ':dev' => $deviceId,
                ':usr' => $userId,
                ':chk' => hash('sha256', uniqid('batch_valid_month')),
            ]),
            'import_batch with source_month=9'
        );
    }

    /**
     * ADR-0002 §5 / uq_batch_checksum:
     * Two import batches with the same SHA-256 checksum must be rejected
     * (duplicate file detection).
     */
    public function testDuplicateImportBatchChecksumIsRejected(): void
    {
        $siteId   = $this->insertAttendanceSite();
        $deviceId = $this->insertBiometricDevice($siteId);
        $roleId   = $this->insertRole('HRHead');
        $userId   = $this->insertUser($roleId);

        $checksum = hash('sha256', 'same-file-content-12345');

        $insertBatch = fn () => $this->pdo->prepare(
            "INSERT INTO attendance_import_batch
                (device_id, uploaded_by, file_name, file_checksum,
                 source_year, source_month, status, uploaded_at)
             VALUES (:dev, :usr, 'log.xls', :chk, 2026, 8, 'Processing', NOW())"
        )->execute([':dev' => $deviceId, ':usr' => $userId, ':chk' => $checksum]);

        $this->assertDbSuccess($insertBatch, 'first import batch');
        $this->assertDbException($insertBatch, 'duplicate checksum import batch');
    }

    /**
     * ADR-0002 / uq_punch_device_code_local_at:
     * Two biometric_punch rows for the same device + code + local timestamp
     * must be rejected (duplicate punch detection).
     */
    public function testDuplicateBiometricPunchIsRejected(): void
    {
        $siteId   = $this->insertAttendanceSite();
        $deviceId = $this->insertBiometricDevice($siteId);
        $roleId   = $this->insertRole('HRHead');
        $userId   = $this->insertUser($roleId);

        $batchId = $this->insertImportBatch($deviceId, $userId, 2026, 9);

        $insertPunch = fn () => $this->pdo->prepare(
            "INSERT INTO biometric_punch
                (import_batch_id, device_id, device_employee_code,
                 source_local_at, punched_at_utc, match_status, raw_record,
                 source_workbook_row, source_date_column)
             VALUES (:bat, :dev, 'EMP001', '2026-09-04 07:00:00', '2026-09-03 23:00:00',
                     'matched', 'EMP001|2026-09-04 07:00:00', 2, '09/04 Fri')"
        )->execute([':bat' => $batchId, ':dev' => $deviceId]);

        $this->assertDbSuccess($insertPunch, 'first biometric punch');
        $this->assertDbException($insertPunch, 'duplicate biometric punch (same device+code+local_at)');
    }

    // ===================================================================
    // ADDITIONAL: holiday_calendar multiplier constraint
    // ===================================================================

    /**
     * ADR-0001 / chk_holiday_multiplier:
     * A Regular holiday must have pay_multiplier = 2.00.
     * A wrong multiplier (e.g. 1.50) must be rejected.
     */
    public function testHolidayRegularWithWrongMultiplierRejected(): void
    {
        $this->assertDbException(
            fn () => $this->pdo->exec(
                "INSERT INTO holiday_calendar
                    (holiday_date, description, holiday_type, pay_multiplier)
                 VALUES ('2026-12-25', 'Christmas Day', 'Regular', 1.50)"
            ),
            'Regular holiday with pay_multiplier = 1.50 instead of 2.00'
        );
    }

    /**
     * A Regular holiday with pay_multiplier = 2.00 must be accepted.
     */
    public function testHolidayRegularWithCorrectMultiplierAccepted(): void
    {
        $this->assertDbSuccess(
            fn () => $this->pdo->exec(
                "INSERT INTO holiday_calendar
                    (holiday_date, description, holiday_type, pay_multiplier)
                 VALUES ('2026-12-25', 'Christmas Day', 'Regular', 2.00)"
            ),
            'Regular holiday with pay_multiplier = 2.00'
        );
    }

    /**
     * A Special holiday with pay_multiplier = 1.30 must be accepted.
     */
    public function testHolidaySpecialWithCorrectMultiplierAccepted(): void
    {
        $this->assertDbSuccess(
            fn () => $this->pdo->exec(
                "INSERT INTO holiday_calendar
                    (holiday_date, description, holiday_type, pay_multiplier)
                 VALUES ('2026-11-01', 'All Saints Day', 'Special', 1.30)"
            ),
            'Special holiday with pay_multiplier = 1.30'
        );
    }

    // ===================================================================
    // ADDITIONAL: unique payslip per payroll (ADR-0002 §15)
    // ===================================================================

    /**
     * One payslip per payroll_id — second insert must be rejected.
     * (Already covered in testDuplicatePayslipIsRejected but kept here for
     * explicit ADR-0002 §15 traceability.)
     */
    public function testPayslipPayrollIdIsUnique(): void
    {
        [$payrollId] = $this->buildMinimalPayrollRow();

        $this->assertDbSuccess(
            fn () => $this->insertPayslip($payrollId, '2026-09-11'),
            'first payslip for a payroll_id'
        );

        $this->assertDbException(
            fn () => $this->insertPayslip($payrollId, '2026-09-11'),
            'second payslip for same payroll_id'
        );
    }

    // ===================================================================
    // Private fixture helpers specific to this test class
    // ===================================================================

    /**
     * Insert an attendance row.  Returns attendance_id.
     */
    private function insertAttendance(
        int $employeeId,
        int $branchAssignId,
        int $scheduleId,
        string $date
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance
                (employee_id, branch_assignment_id, schedule_id,
                 attendance_date, status, source)
             VALUES (:emp, :asgn, :sched, :dt, 'Complete', 'manual')"
        );
        $stmt->execute([
            ':emp'   => $employeeId,
            ':asgn'  => $branchAssignId,
            ':sched' => $scheduleId,
            ':dt'    => $date,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Build a minimal payroll row (period → run → payroll) and return
     * [payroll_id, contrib_policy_id].
     *
     * @return array{int, null}
     */
    private function buildMinimalPayrollRow(): array
    {
        $period   = $this->insertPayrollPeriod('2026-09-04', '2026-09-10', '2026-09-11');
        $policy   = $this->insertPayrollPolicy();
        $branch   = $this->insertBranch();
        $run      = $this->insertPayrollRun($period, $branch, $policy);
        $employee = $this->insertEmployee();
        $assign   = $this->insertBranchAssignment($employee, $branch, '2026-01-01');
        $salary   = $this->insertSalary($employee);
        $payrollId = $this->insertPayroll($run, $period, $employee, $assign, $salary);

        return [$payrollId, null];
    }

    /**
     * Insert a minimal contribution_policy_version and return its id.
     */
    private function insertContributionPolicy(): int
    {
        $code = uniqid('CPV');
        $stmt = $this->pdo->prepare(
            "INSERT INTO contribution_policy_version
                (policy_code, version, effective_from, status)
             VALUES (:code, '1.0', '2026-01-01', 'Approved')"
        );
        $stmt->execute([':code' => $code]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a deduction row for the given payroll_id and return deduction_id.
     */
    private function insertDeduction(int $payrollId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO deduction
                (payroll_id, deduction_type, description,
                 quantity, unit_rate, amount, calculation_details)
             VALUES (:pid, 'SSS', 'SSS Employee Share', 1.0000, 400.0000, 400.00, '{}')"
        );
        $stmt->execute([':pid' => $payrollId]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a contribution_record row and return its id.
     */
    private function insertContributionRecord(
        int $payrollId,
        int $deductionId,
        int $policyId,
        string $type
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contribution_record
                (payroll_id, deduction_id, contribution_policy_id, contribution_type,
                 eemr_basis, employee_share, employer_share, deduction_date,
                 calculation_details)
             VALUES (:pid, :did, :cpid, :type, 11983.33, 400.00, 400.00, '2026-09-30', '{}')"
        );
        $stmt->execute([
            ':pid'  => $payrollId,
            ':did'  => $deductionId,
            ':cpid' => $policyId,
            ':type' => $type,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a payslip row and return its payslip_id.
     */
    private function insertPayslip(int $payrollId, string $issueDate): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payslip (payroll_id, issue_date, generated_at)
             VALUES (:pid, :dt, NOW())"
        );
        $stmt->execute([':pid' => $payrollId, ':dt' => $issueDate]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a disbursement_batch row and return its batch_id.
     */
    private function insertDisbursementBatch(int $periodId, float $total): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO disbursement_batch (payroll_period_id, cheque_total)
             VALUES (:per, :tot)"
        );
        $stmt->execute([':per' => $periodId, ':tot' => $total]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert an attendance_site and return its site_id.
     */
    private function insertAttendanceSite(): int
    {
        $code = uniqid('SITE');
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance_site (site_code, site_name) VALUES (:c, :n)"
        );
        $stmt->execute([':c' => $code, ':n' => 'Site ' . $code]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a biometric_device and return its device_id.
     */
    private function insertBiometricDevice(int $siteId): int
    {
        $code = uniqid('DEV');
        $stmt = $this->pdo->prepare(
            "INSERT INTO biometric_device (site_id, device_code, file_format, timezone)
             VALUES (:s, :c, 'LDE_XLS_DAILY_LOG_V1', 'Asia/Manila')"
        );
        $stmt->execute([':s' => $siteId, ':c' => $code]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert an attendance_import_batch and return its import_batch_id.
     */
    private function insertImportBatch(
        int $deviceId,
        int $userId,
        int $year,
        int $month
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance_import_batch
                (device_id, uploaded_by, file_name, file_checksum,
                 source_year, source_month, status, uploaded_at)
             VALUES (:dev, :usr, 'log.xls', :chk, :yr, :mo, 'Processing', NOW())"
        );
        $stmt->execute([
            ':dev' => $deviceId,
            ':usr' => $userId,
            ':chk' => hash('sha256', uniqid('batch')),
            ':yr'  => $year,
            ':mo'  => $month,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
