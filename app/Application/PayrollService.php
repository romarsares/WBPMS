<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Wbpms\Domain\Payroll\HolidayPayCalculator;
use Wbpms\Domain\Payroll\HolidayRecord;
use Wbpms\Domain\Payroll\HolidayType;
use Wbpms\Domain\Payroll\ApprovedOvertimeCalculator;
use Wbpms\Infrastructure\Database\Connection;

/**
 * PayrollService — orchestrates the full payroll lifecycle.
 *
 * Responsibilities (ADR-0001):
 *   - Period management (create Sunday→Friday periods).
 *   - Draft payroll run creation.
 *   - Computation: gross from attendance, deductions (late/undertime/contributions/cash-advance), net.
 *   - Submission to Owner (Draft/Computed → PendingOwnerApproval).
 *   - Owner approve / return for revision.
 *   - 13th-month pay computation.
 *
 * All SQL lives here (or in helper methods on this class).
 * Money is always handled as integer centavos internally, stored as DECIMAL(12,2).
 *
 * ADR-0001 payroll cycle:
 *   period_start = Sunday, period_end = Friday, pay_date = the period-ending Friday.
 *   EEMR = (daily_rate × eemr_days_per_year) / 12.
 *   Late deduction = late_minutes × late_rate_per_minute (default ₱1.00/min).
 *   Undertime deduction = undertime_minutes × (daily_rate / standard_minutes).
 *   Overtime earning = approved, worked overtime_minutes × (daily_rate / standard_minutes) × multiplier.
 */
final class PayrollService
{
    private Connection $connection;
    private HolidayPayCalculator $holidayCalc;
    private ApprovedOvertimeCalculator $approvedOvertimeCalc;

    public function __construct(Connection $connection)
    {
        $this->connection  = $connection;
        $this->holidayCalc = new HolidayPayCalculator();
        $this->approvedOvertimeCalc = new ApprovedOvertimeCalculator();
    }

    // ===================================================================
    // Payroll Period management
    // ===================================================================

    /**
     * Create one Sunday→Friday payroll period.
     * Validates that the supplied date is a Sunday.
     */
    public function createPeriod(string $periodStart): int
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $periodStart, new DateTimeZone('Asia/Manila'));
        if ($start === false) {
            throw new RuntimeException('Invalid period start date. Use YYYY-MM-DD format.');
        }
        if ((int) $start->format('N') !== 7) { // 7 = Sunday
            throw new RuntimeException('Period start must be a Sunday.');
        }

        $end     = $start->modify('+5 days');  // Friday
        $payDate = $end;                       // Salary is released Friday

        $pdo = $this->connection->pdo();

        // Check for duplicate
        $chk = $pdo->prepare("SELECT COUNT(*) FROM payroll_period WHERE period_start = :s");
        $chk->execute([':s' => $start->format('Y-m-d')]);
        if ((int) $chk->fetchColumn() > 0) {
            throw new RuntimeException('A payroll period starting on that date already exists.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO payroll_period
                (period_start, period_end, pay_date, cutoff_pattern, status, created_at, updated_at)
             VALUES
                (:start, :end, :pay, 'SundayFriday', 'Open', :created_at, :updated_at)"
        );
        $now = $this->utcNow();
        $stmt->execute([
            ':start'      => $start->format('Y-m-d'),
            ':end'        => $end->format('Y-m-d'),
            ':pay'        => $payDate->format('Y-m-d'),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Generate every Sunday–Friday cutoff that overlaps a calendar month.
     * A cutoff may begin in the preceding month or end in the following one.
     *
     * @return array{created:int,existing:int}
     */
    public function createPeriodsForMonth(string $month): array
    {
        if (!preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $month)) {
            throw new RuntimeException('Select a valid payroll month.');
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $firstDay = new DateTimeImmutable($month . '-01', $timezone);
        $lastDay = $firstDay->modify('last day of this month');
        $firstSunday = $firstDay->modify('-' . (int) $firstDay->format('w') . ' days');
        $created = 0;
        $existing = 0;

        for ($start = $firstSunday; $start <= $lastDay; $start = $start->modify('+7 days')) {
            try {
                $this->createPeriod($start->format('Y-m-d'));
                $created++;
            } catch (RuntimeException $e) {
                if (!str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
                $existing++;
            }
        }

        return ['created' => $created, 'existing' => $existing];
    }

    /** @return list<array<string,mixed>> */
    public function listPeriods(): array
    {
        $rows = $this->connection->pdo()->query(
            "SELECT pp.payroll_period_id,
                    pp.period_start,
                    pp.period_end,
                    pp.pay_date,
                    pp.cutoff_pattern,
                    pp.status,
                    pp.created_at,
                    COUNT(pr.payroll_run_id) AS run_count
               FROM payroll_period pp
               LEFT JOIN payroll_run pr ON pr.payroll_period_id = pp.payroll_period_id
              GROUP BY pp.payroll_period_id, pp.period_start, pp.period_end, pp.pay_date,
                       pp.cutoff_pattern, pp.status, pp.created_at
              ORDER BY pp.period_start DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['is_month_end_contribution_cutoff'] = $this->isMonthlyContributionCutoff((string) $row['pay_date']);
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> Periods formatted for select dropdowns. */
    public function periods(): array
    {
        $rows = $this->listPeriods();
        foreach ($rows as &$r) {
            $r['label'] = $r['period_start'] . ' – ' . $r['period_end'];
        }
        return $rows;
    }

    // ===================================================================
    // Payroll Run lifecycle
    // ===================================================================

    /**
     * Create a Draft payroll run for a given period + branch.
     * Enforces the unique constraint (one run per period per branch).
     *
     * @param array<string,mixed> $data  Keys: payroll_period_id, branch_id
     */
    public function createRun(array $data, int $createdByUserId): int
    {
        $periodId = (int) ($data['payroll_period_id'] ?? 0);
        $branchId = (int) ($data['branch_id'] ?? 0);

        if ($periodId < 1 || $branchId < 1) {
            throw new RuntimeException('Period and branch are required.');
        }

        $pdo = $this->connection->pdo();

        // Validate period exists and is open
        $stmt = $pdo->prepare("SELECT status FROM payroll_period WHERE payroll_period_id = :id");
        $stmt->execute([':id' => $periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            throw new RuntimeException('Payroll period not found.');
        }

        // Resolve active payroll policy
        $policy = $this->resolveActivePolicy($pdo);
        $policyId = (int) $policy['policy_id'];

        $now  = $this->utcNow();
        $stmt = $pdo->prepare(
            "INSERT INTO payroll_run
                (payroll_period_id, branch_id, payroll_policy_id, status,
                 lock_version, created_at, updated_at)
             VALUES
                (:period_id, :branch_id, :policy_id, 'Draft',
                 0, :created_at, :updated_at)"
        );
        try {
            $stmt->execute([
                ':period_id'  => $periodId,
                ':branch_id'  => $branchId,
                ':policy_id'  => $policyId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                throw new RuntimeException('A payroll run already exists for this period and branch.');
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }

    /**
     * Find a run or throw RuntimeException with a safe 404-friendly message.
     *
     * @return array<string,mixed>
     */
    public function findRunOrFail(int $runId): array
    {
        $pdo  = $this->connection->pdo();
        $stmt = $pdo->prepare(
            "SELECT pr.payroll_run_id,
                    pr.status,
                    pr.return_reason,
                    pr.submitted_at,
                    pr.reviewed_at,
                    pr.created_at,
                    pr.gross_pay,
                    pr.net_pay,
                    pr.total_deductions,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    pp.period_start,
                    pp.period_end,
                    pp.pay_date
               FROM payroll_run pr
               JOIN branch b         ON b.branch_id          = pr.branch_id
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
              WHERE pr.payroll_run_id = :id"
        );
        $stmt->execute([':id' => $runId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            throw new RuntimeException('Payroll run not found.');
        }
        return $run;
    }

    /**
     * Employee payroll rows for a run.
     *
     * @return list<array<string,mixed>>
     */
    public function runDetails(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT p.payroll_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
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

    /**
     * Summarise the deductions posted to a payroll run for the HR review page.
     *
     * @return list<array{deduction_type:string,record_count:int,total_amount:float}>
     */
    public function runDeductionSummary(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT d.deduction_type,
                    COUNT(*) AS record_count,
                    SUM(d.amount) AS total_amount
               FROM deduction d
               JOIN payroll p ON p.payroll_id = d.payroll_id
              WHERE p.payroll_run_id = :id
              GROUP BY d.deduction_type
              ORDER BY total_amount DESC, d.deduction_type"
        );
        $stmt->execute([':id' => $runId]);

        return array_map(static fn (array $row): array => [
            'deduction_type' => (string) $row['deduction_type'],
            'record_count' => (int) $row['record_count'],
            'total_amount' => (float) $row['total_amount'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Summarise basic pay, overtime, holiday pay, and other posted earnings.
     *
     * @return list<array{earning_type:string,record_count:int,total_amount:float}>
     */
    public function runEarningSummary(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT pe.earning_type,
                    COUNT(*) AS record_count,
                    SUM(pe.amount) AS total_amount
               FROM payroll_earnings pe
               JOIN payroll p ON p.payroll_id = pe.payroll_id
              WHERE p.payroll_run_id = :id
              GROUP BY pe.earning_type
              ORDER BY total_amount DESC, pe.earning_type"
        );
        $stmt->execute([':id' => $runId]);

        return array_map(static fn (array $row): array => [
            'earning_type' => (string) $row['earning_type'],
            'record_count' => (int) $row['record_count'],
            'total_amount' => (float) $row['total_amount'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{payroll_id:int,earning_type:string,description:string,quantity:float,unit_rate:float,multiplier:float,amount:float}>
     */
    public function runEarnings(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT pe.payroll_id, pe.earning_type, pe.description,
                    pe.quantity, pe.unit_rate, pe.multiplier, pe.amount
               FROM payroll_earnings pe
               JOIN payroll p ON p.payroll_id = pe.payroll_id
              WHERE p.payroll_run_id = :id
              ORDER BY pe.payroll_id, pe.earning_type, pe.earning_id"
        );
        $stmt->execute([':id' => $runId]);

        return array_map(static fn (array $row): array => [
            'payroll_id' => (int) $row['payroll_id'],
            'earning_type' => (string) $row['earning_type'],
            'description' => (string) $row['description'],
            'quantity' => (float) $row['quantity'],
            'unit_rate' => (float) $row['unit_rate'],
            'multiplier' => (float) $row['multiplier'],
            'amount' => (float) $row['amount'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{payroll_id:int,deduction_type:string,description:string,quantity:float,unit_rate:float,amount:float}>
     */
    public function runDeductions(int $runId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT d.payroll_id, d.deduction_type, d.description,
                    d.quantity, d.unit_rate, d.amount
               FROM deduction d
               JOIN payroll p ON p.payroll_id = d.payroll_id
              WHERE p.payroll_run_id = :id
              ORDER BY d.payroll_id, d.deduction_type, d.deduction_id"
        );
        $stmt->execute([':id' => $runId]);

        return array_map(static fn (array $row): array => [
            'payroll_id' => (int) $row['payroll_id'],
            'deduction_type' => (string) $row['deduction_type'],
            'description' => (string) $row['description'],
            'quantity' => (float) $row['quantity'],
            'unit_rate' => (float) $row['unit_rate'],
            'amount' => (float) $row['amount'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ===================================================================
    // Payroll Computation (Wire #2 + Wire #3)
    // ===================================================================

    /**
     * Compute gross/deductions/net for every eligible employee in the run.
     * Clears any prior computed rows for this run first (re-compute is safe).
     *
     * Flow per employee:
     *   1. Fetch effective daily_rate for period_start.
     *   2. Sum attendance days worked / late / undertime; pay OT only where an approved request exists.
     *   3. Basic pay = days_worked_equivalent × daily_rate.
     *   4. Overtime earning (regular hours overtime, not holiday).
     *   5. Government contributions (SSS, PhilHealth, Pag-IBIG).
     *   6. Late deduction = late_minutes × policy.late_rate_per_minute.
     *   7. Undertime deduction = undertime_minutes × (daily_rate / standard_minutes).
     *   8. Outstanding cash advance deduction.
     *   9. Net = Gross − Total Deductions.
     */
    public function computeRun(int $runId, int $computedByUserId): void
    {
        $pdo = $this->connection->pdo();

        $run = $this->findRunOrFail($runId);
        if ($run['status'] === 'Approved') {
            throw new RuntimeException('Approved payroll runs are immutable.');
        }

        $adjustmentCheck = $pdo->prepare(
            "SELECT COUNT(*)
               FROM payroll_adjustment
              WHERE payroll_run_id = :run_id"
        );
        $adjustmentCheck->execute([':run_id' => $runId]);
        if ((int) $adjustmentCheck->fetchColumn() > 0) {
            throw new RuntimeException(
                'This payroll run contains manual adjustments and cannot be recomputed. '
                . 'Review the adjusted amounts before submitting it for approval.'
            );
        }

        $policy        = $this->resolveRunPolicy($pdo, $runId);
        $contribPolicy = $this->resolveContribPolicy($pdo);

        // SSS brackets, PhilHealth rate, Pag-IBIG rate
        $sssBrackets  = $this->loadSssBrackets($pdo, (int) $contribPolicy['contribution_policy_id']);
        $philRate     = $this->loadPhilhealthRate($pdo, (int) $contribPolicy['contribution_policy_id']);
        $pagibigRate  = $this->loadPagibigRate($pdo, (int) $contribPolicy['contribution_policy_id']);

        $lateRatePerMin   = (float) ($policy['late_rate_per_minute'] ?? 1.00);
        $eemrDaysPerYear  = (int)   ($policy['eemr_days_per_year']   ?? 313);
        $periodStart      = $run['period_start'];
        $periodEnd        = $run['period_end'];
        $payDate          = $run['pay_date'];
        $applyContributions = $this->isMonthlyContributionCutoff((string) $payDate);

        // Eligible employees: branch assignment effective on period_start
        // Note: PDO named params must be unique per statement — :ps1…:ps6 all bind $periodStart.
        $stmt = $pdo->prepare(
            "SELECT e.employee_id, e.employee_number,
                    eba.branch_assignment_id,
                    s.salary_id, s.daily_rate,
                    ws.standard_minutes
               FROM employee_branch_assignment eba
               JOIN employee e ON e.employee_id = eba.employee_id
               JOIN salary s
                 ON s.employee_id   = e.employee_id
                AND s.effective_from <= :ps1
                AND (s.effective_to IS NULL OR s.effective_to > :ps2)
                AND s.status = 'Active'
               LEFT JOIN employee_schedule_assignment esa
                 ON esa.employee_id = e.employee_id
                AND esa.effective_from <= :ps3
                AND (esa.effective_to IS NULL OR esa.effective_to > :ps4)
                AND esa.status = 'Active'
               LEFT JOIN work_schedule ws
                 ON ws.schedule_id = esa.schedule_id
              WHERE eba.branch_id     = (SELECT branch_id FROM payroll_run WHERE payroll_run_id = :run_id)
                AND eba.effective_from <= :ps5
                AND (eba.effective_to IS NULL OR eba.effective_to > :ps6)
                AND e.status = 'Active'"
        );
        $stmt->execute([
            ':ps1'    => $periodStart,
            ':ps2'    => $periodStart,
            ':ps3'    => $periodStart,
            ':ps4'    => $periodStart,
            ':ps5'    => $periodStart,
            ':ps6'    => $periodStart,
            ':run_id' => $runId,
        ]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->connection->transaction(function () use (
            $pdo, $runId, $employees, $periodStart, $periodEnd,
            $contribPolicy, $sssBrackets, $philRate, $pagibigRate,
            $lateRatePerMin, $eemrDaysPerYear, $computedByUserId,
            $payDate, $applyContributions
        ): void {
            // Clear prior rows for this run
            $pdo->prepare("DELETE FROM contribution_record WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = :id)")->execute([':id' => $runId]);
            $pdo->prepare("DELETE FROM deduction WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = :id)")->execute([':id' => $runId]);
            $pdo->prepare("DELETE FROM payroll_earnings WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = :id)")->execute([':id' => $runId]);
            $pdo->prepare("DELETE FROM payslip WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = :id)")->execute([':id' => $runId]);
            $pdo->prepare("DELETE FROM payroll WHERE payroll_run_id = :id")->execute([':id' => $runId]);

            $runGross = 0.0;
            $runDed   = 0.0;
            $runNet   = 0.0;

            foreach ($employees as $emp) {
                $employeeId      = (int) $emp['employee_id'];
                $baId            = (int) $emp['branch_assignment_id'];
                $salaryId        = (int) $emp['salary_id'];
                $dailyRate       = (float) $emp['daily_rate'];
                $standardMinutes = (int) ($emp['standard_minutes'] ?? 480); // 8h default

                // --- Attendance summary for period ---
                $stmt = $pdo->prepare(
                    "SELECT
                        COUNT(*) AS days_count,
                        SUM(hours_worked_minutes) AS total_worked,
                        SUM(late_minutes)         AS total_late,
                        SUM(undertime_minutes)    AS total_undertime
                       FROM attendance
                      WHERE employee_id = :emp_id
                        AND attendance_date BETWEEN :start AND :end
                        AND status IN ('Complete','Approved','ReviewRequired')"
                );
                $stmt->execute([':emp_id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
                $att = $stmt->fetch(PDO::FETCH_ASSOC);

                $daysCount       = (int)   ($att['days_count']    ?? 0);
                $totalLate       = (int)   ($att['total_late']     ?? 0);
                $totalUndertime  = (int)   ($att['total_undertime'] ?? 0);
                $approvedOtByDate = $this->approvedOvertimeRequestsByDate(
                    $pdo, $employeeId, $periodStart, $periodEnd
                );
                $paidOtByDate = $this->payableOvertimeByDate(
                    $pdo, $employeeId, $periodStart, $periodEnd, $approvedOtByDate
                );
                $totalOvertime = array_sum($paidOtByDate);

                // Basic pay = days present × daily_rate
                // (full days: worked_minutes >= standard_minutes - 30min tolerance)
                $basicPay = round($daysCount * $dailyRate, 2);

                // Overtime is paid only when attendance and an approved OT request agree.
                // Holiday OT is re-attributed below; this will be adjusted for holiday rows.
                $minuteRate  = $standardMinutes > 0 ? $dailyRate / $standardMinutes : 0;
                $overtimePay = round($totalOvertime * $minuteRate * 1.25, 2);

                // --- Holiday pay (REQ047, ADR-0001 §Holiday Pay) ---
                // Fetch attendance rows that overlap an active holiday in this period.
                // For each: compute holiday bundle, then apply an adjustment against the
                // regular-day amounts already counted in $basicPay / $overtimePay.
                $holidayDayAdj = 0.0;
                $holidayOtAdj  = 0.0;
                $holidayEarningRows = [];

                $hStmt = $pdo->prepare(
                    "SELECT a.attendance_id,
                            a.attendance_date,
                            a.hours_worked_minutes,
                            hc.holiday_type,
                            hc.description AS holiday_description
                       FROM attendance a
                       JOIN holiday_calendar hc
                         ON hc.holiday_date = a.attendance_date
                        AND hc.status = 'Active'
                      WHERE a.employee_id = :emp_id
                        AND a.attendance_date BETWEEN :start AND :end
                        AND a.status IN ('Complete','Approved')"
                );
                $hStmt->execute([
                    ':emp_id' => $employeeId,
                    ':start'  => $periodStart,
                    ':end'    => $periodEnd,
                ]);
                $holidayAttRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($holidayAttRows as $hRow) {
                    $hType      = HolidayType::from($hRow['holiday_type']);
                    $workedMins = (int) $hRow['hours_worked_minutes'];
                    $hOtMins    = $paidOtByDate[(string) $hRow['attendance_date']] ?? 0;
                    $worked     = $workedMins > 0;

                    $bundle = $this->holidayCalc->computeBundle(
                        dailyRate:       $dailyRate,
                        type:            $hType,
                        worked:          $worked,
                        isRestDay:       false, // rest-day detection requires schedule join; pending
                        overtimeMinutes: $hOtMins,
                        standardMinutes: $standardMinutes,
                    );

                    // The attendance aggregation already counted this date as a regular day.
                    // Swap out the regular-day contribution for the holiday amount.
                    $regularDayAmt = $worked ? $dailyRate : 0.0;
                    $holidayDayAdj += round($bundle['day_pay'] - $regularDayAmt, 2);

                    // Swap regular OT for holiday OT on this date
                    $regularOtAmt  = round($hOtMins * $minuteRate * 1.25, 2);
                    $holidayOtAdj += round($bundle['overtime_pay'] - $regularOtAmt, 2);

                    // Basic Pay already holds the normal daily amount for a worked
                    // holiday, and regular OT already holds its normal premium.
                    // Store only the extra holiday premium so itemized earnings sum
                    // exactly to gross pay and do not appear to double-pay the day.
                    $holidayDayPremium = round($bundle['day_pay'] - $regularDayAmt, 2);
                    $holidayOtPremium  = round($bundle['overtime_pay'] - $regularOtAmt, 2);
                    if ($holidayDayPremium > 0.0) {
                        $holidayEarningRows[] = [
                            'type'        => $bundle['earning_type'],
                            'description' => $hRow['holiday_description'] . ' premium above Basic Pay',
                            'qty'         => 1,
                            'unit_rate'   => $dailyRate,
                            'multiplier'  => $worked
                                ? max(0.0, $bundle['multiplier_used'] - 1.0)
                                : $bundle['multiplier_used'],
                            'amount'      => $holidayDayPremium,
                        ];
                    }
                    if ($holidayOtPremium > 0.0 && $hOtMins > 0 && $minuteRate > 0.0) {
                        $holidayEarningRows[] = [
                            'type'        => $bundle['earning_type'],
                            'description' => $hRow['holiday_description'] . ' OT premium above regular OT',
                            'qty'         => $hOtMins,
                            'unit_rate'   => $minuteRate,
                            'multiplier'  => round(($bundle['overtime_pay'] / ($hOtMins * $minuteRate)) - 1.25, 4),
                            'amount'      => $holidayOtPremium,
                        ];
                    }
                }

                $grossPay = round($basicPay + $overtimePay + $holidayDayAdj + $holidayOtAdj, 2);

                // EEMR = (daily_rate × eemr_days_per_year) / 12
                $eemr = round(($dailyRate * $eemrDaysPerYear) / 12, 2);

                // --- Contributions (Wire #3) ---
                $sssEmployee  = $applyContributions ? $this->computeSss($eemr, $sssBrackets) : 0.0;
                $sssEmployer  = $applyContributions ? $this->computeSssEmployer($eemr, $sssBrackets) : 0.0;
                $philEmployee = $applyContributions ? $this->computePhilhealth($eemr, $philRate, true) : 0.0;
                $philEmployer = $applyContributions ? $this->computePhilhealth($eemr, $philRate, false) : 0.0;
                $pagEmployee  = $applyContributions ? $this->computePagibig($eemr, $pagibigRate, true) : 0.0;
                $pagEmployer  = $applyContributions ? $this->computePagibig($eemr, $pagibigRate, false) : 0.0;

                // --- Late deduction ---
                $lateDed = round($totalLate * $lateRatePerMin, 2);

                // --- Undertime deduction ---
                $undertimeDed = round($totalUndertime * $minuteRate, 2);

                // --- Cash advance outstanding balance ---
                $caDed = $this->outstandingCashAdvance($pdo, $employeeId);

                // --- Totals ---
                $totalDed = $sssEmployee + $philEmployee + $pagEmployee + $lateDed + $undertimeDed + $caDed;
                $netPay   = round($grossPay - $totalDed, 2);
                if ($netPay < 0) {
                    $netPay = 0.0;
                }

                $now = $this->utcNow();

                // Insert payroll row
                $stmt = $pdo->prepare(
                    "INSERT INTO payroll
                        (payroll_run_id, payroll_period_id, employee_id,
                         branch_assignment_id, salary_id, daily_rate_snapshot,
                         gross_pay, total_deductions, net_pay,
                         created_at)
                     VALUES
                        (:run_id, (SELECT payroll_period_id FROM payroll_run WHERE payroll_run_id = :run_id2),
                         :emp_id, :ba_id, :sal_id, :rate,
                         :gross, :ded, :net,
                         :now)"
                );
                $stmt->execute([
                    ':run_id'  => $runId,
                    ':run_id2' => $runId,
                    ':emp_id'  => $employeeId,
                    ':ba_id'   => $baId,
                    ':sal_id'  => $salaryId,
                    ':rate'    => $dailyRate,
                    ':gross'   => $grossPay,
                    ':ded'     => $totalDed,
                    ':net'     => $netPay,
                    ':now'     => $now,
                ]);
                $payrollId = (int) $pdo->lastInsertId();

                // --- Earnings rows ---
                $this->insertEarning($pdo, $payrollId, 'Basic', 'Basic Pay', $daysCount, $dailyRate, 1.0, $basicPay, $now);
                if ($overtimePay > 0) {
                    $this->insertEarning($pdo, $payrollId, 'Overtime', 'Approved overtime pay', $totalOvertime, $minuteRate, 1.25, $overtimePay, $now);
                }
                // --- Holiday earnings rows (REQ047) ---
                foreach ($holidayEarningRows as $hr) {
                    $this->insertEarning(
                        $pdo, $payrollId,
                        $hr['type'], $hr['description'],
                        $hr['qty'], $hr['unit_rate'], $hr['multiplier'], $hr['amount'],
                        $now
                    );
                }

                // --- Deduction rows ---
                if ($lateDed > 0) {
                    $this->insertDeduction($pdo, $payrollId, 'Late', 'Late deduction', $totalLate, $lateRatePerMin, $lateDed, $now);
                }
                if ($undertimeDed > 0) {
                    $this->insertDeduction($pdo, $payrollId, 'Undertime', 'Undertime deduction', $totalUndertime, $minuteRate, $undertimeDed, $now);
                }
                if ($caDed > 0) {
                    $this->insertDeduction($pdo, $payrollId, 'CashAdvance', 'Cash advance repayment', 1, $caDed, $caDed, $now);

                    // Decrement the remaining balance on the active obligation row.
                    // If the repayment clears the balance entirely, mark it Settled
                    // so it is excluded from future runs.
                    $pdo->prepare(
                        "UPDATE cash_advance_history
                            SET remaining_balance = GREATEST(0, remaining_balance - :deducted),
                                status = CASE
                                    WHEN GREATEST(0, remaining_balance - :deducted2) = 0
                                    THEN 'Settled'
                                    ELSE status
                                END,
                                updated_at = NOW()
                          WHERE employee_id = :emp_id
                            AND status = 'Active'
                          ORDER BY approved_at DESC
                          LIMIT 1"
                    )->execute([
                        ':deducted'  => $caDed,
                        ':deducted2' => $caDed,
                        ':emp_id'    => $employeeId,
                    ]);
                }

                // --- Government contribution deduction rows + contribution_record ---
                $deductionDate = $payDate;
                foreach ([
                    ['SSS',       $sssEmployee,  $sssEmployer],
                    ['PhilHealth', $philEmployee, $philEmployer],
                    ['PagIBIG',   $pagEmployee,  $pagEmployer],
                ] as [$type, $empShare, $emplrShare]) {
                    if ($empShare <= 0) {
                        continue;
                    }
                    $dedId = $this->insertDeduction($pdo, $payrollId, $type, $type . ' contribution', 1, $empShare, $empShare, $now, true);

                    $pdo->prepare(
                        "INSERT INTO contribution_record
                            (payroll_id, deduction_id, contribution_policy_id,
                             contribution_type, eemr_basis,
                             employee_share, employer_share, deduction_date,
                             calculation_details, status, created_at)
                         VALUES
                            (:pid, :did, :cpid,
                             :type, :eemr,
                             :emp, :emplr, :ddate,
                             :details, 'Computed', :now)"
                    )->execute([
                        ':pid'     => $payrollId,
                        ':did'     => $dedId,
                        ':cpid'    => $contribPolicy['contribution_policy_id'],
                        ':type'    => $type,
                        ':eemr'    => $eemr,
                        ':emp'     => $empShare,
                        ':emplr'   => $emplrShare,
                        ':ddate'   => $deductionDate,
                        ':details' => json_encode(['eemr' => $eemr, 'type' => $type]),
                        ':now'     => $now,
                    ]);
                }

                $runGross += $grossPay;
                $runDed   += $totalDed;
                $runNet   += $netPay;
            }

            // Update run totals and status
            $computedNow = $this->utcNow();
            $pdo->prepare(
                "UPDATE payroll_run SET
                    status       = 'Computed',
                    gross_pay    = :gross,
                    total_deductions = :ded,
                    net_pay      = :net,
                    computed_by  = :by,
                    computed_at  = :computed_at,
                    updated_at   = :updated_at
                  WHERE payroll_run_id = :id"
            )->execute([
                ':gross'       => round($runGross, 2),
                ':ded'         => round($runDed, 2),
                ':net'         => round($runNet, 2),
                ':by'          => $computedByUserId,
                ':computed_at' => $computedNow,
                ':updated_at'  => $computedNow,
                ':id'          => $runId,
            ]);
        });
    }

    // ===================================================================
    // Submission and Approval
    // ===================================================================

    public function submitForApproval(int $runId, int $submittedByUserId): void
    {
        $pdo = $this->connection->pdo();
        $run = $this->findRunOrFail($runId);

        // REQ010.11: approved runs are immutable
        if ($run['status'] === 'Approved') {
            throw new RuntimeException('Approved payroll runs are immutable and cannot be resubmitted.');
        }

        if (!in_array($run['status'], ['Computed', 'Returned'], true)) {
            throw new RuntimeException('Only Computed or Returned runs can be submitted for approval.');
        }

        $submittedNow = $this->utcNow();
        $pdo->prepare(
            "UPDATE payroll_run SET
                status       = 'PendingOwnerApproval',
                submitted_by = :by,
                submitted_at = :submitted_at,
                updated_at   = :updated_at
              WHERE payroll_run_id = :id"
        )->execute([
            ':by'           => $submittedByUserId,
            ':submitted_at' => $submittedNow,
            ':updated_at'   => $submittedNow,
            ':id'           => $runId,
        ]);
    }

    public function approve(int $runId, int $reviewedByUserId): void
    {
        $pdo = $this->connection->pdo();
        $run = $this->findRunOrFail($runId);

        if ($run['status'] !== 'PendingOwnerApproval') {
            throw new RuntimeException('Only runs pending approval can be approved.');
        }

        $now = $this->utcNow();

        $this->connection->transaction(function () use ($pdo, $runId, $reviewedByUserId, $now): void {
            // 1. Mark run as Approved
            $pdo->prepare(
                "UPDATE payroll_run SET
                    status      = 'Approved',
                    reviewed_by = :by,
                    reviewed_at = :reviewed_at,
                    updated_at  = :updated_at
                  WHERE payroll_run_id = :id"
            )->execute([':by' => $reviewedByUserId, ':reviewed_at' => $now, ':updated_at' => $now, ':id' => $runId]);

            // 2. Generate one payslip row per employee payroll row (REQ048)
            // Skip employees that already have a payslip for this run
            $stmt = $pdo->prepare(
                "SELECT p.payroll_id
                   FROM payroll p
                   LEFT JOIN payslip ps ON ps.payroll_id = p.payroll_id
                  WHERE p.payroll_run_id = :run_id
                    AND ps.payslip_id IS NULL"
            );
            $stmt->execute([':run_id' => $runId]);
            $payrollIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $insert = $pdo->prepare(
                "INSERT INTO payslip
                    (payroll_id, issue_date, generated_by, generated_at)
                 VALUES
                    (:pid, :issue, :by, :generated_at)"
            );
            $issueDate = (new DateTimeImmutable($now))->format('Y-m-d');
            foreach ($payrollIds as $pid) {
                $insert->execute([
                    ':pid'          => $pid,
                    ':issue'        => $issueDate,
                    ':by'           => $reviewedByUserId,
                    ':generated_at' => $now,
                ]);
            }
        });
    }

    public function returnForRevision(int $runId, int $reviewedByUserId, string $reason): void
    {
        $pdo = $this->connection->pdo();
        $run = $this->findRunOrFail($runId);

        // REQ010.11: approved runs are immutable — cannot be returned after approval
        if ($run['status'] === 'Approved') {
            throw new RuntimeException('Approved payroll runs are immutable and cannot be returned.');
        }

        if ($run['status'] !== 'PendingOwnerApproval') {
            throw new RuntimeException('Only runs pending approval can be returned.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A return reason is required.');
        }

        $now = $this->utcNow();
        $pdo->prepare(
            "UPDATE payroll_run SET
                status        = 'Returned',
                return_reason = :reason,
                reviewed_by   = :by,
                reviewed_at   = :reviewed_at,
                updated_at    = :updated_at
              WHERE payroll_run_id = :id"
        )->execute([':reason' => $reason, ':by' => $reviewedByUserId, ':reviewed_at' => $now, ':updated_at' => $now, ':id' => $runId]);
    }

    // ===================================================================
    // Payslip detail data (Wire #4)
    // ===================================================================

    /**
     * Return full itemized payslip data for a given payslip_id.
     * Used by EmployeePortalController::payslipDetail() and the download renderer.
     *
     * @return array<string,mixed>
     */
    public function payslipData(int $payslipId): array
    {
        $pdo  = $this->connection->pdo();

        // Header
        $stmt = $pdo->prepare(
            "SELECT ps.payslip_id,
                    ps.issue_date,
                    p.employee_id,
                    p.payroll_id,
                    p.daily_rate_snapshot,
                    p.gross_pay,
                    p.total_deductions,
                    p.net_pay,
                    CONCAT(e.first_name, ' ', e.last_name)        AS employee_name,
                    e.employee_number,
                    e.position,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                    pp.period_start,
                    pp.period_end,
                    pp.pay_date
               FROM payslip ps
               JOIN payroll p         ON p.payroll_id          = ps.payroll_id
               JOIN payroll_run pr    ON pr.payroll_run_id      = p.payroll_run_id
               JOIN payroll_period pp ON pp.payroll_period_id   = pr.payroll_period_id
               JOIN employee e        ON e.employee_id          = p.employee_id
               JOIN branch b          ON b.branch_id            = pr.branch_id
              WHERE ps.payslip_id = :id"
        );
        $stmt->execute([':id' => $payslipId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            throw new RuntimeException('Payslip not found.');
        }

        $payrollId = (int) $header['payroll_id'];

        // Earnings
        $stmt = $pdo->prepare(
            "SELECT earning_type, description, quantity, unit_rate, multiplier, amount
               FROM payroll_earnings
              WHERE payroll_id = :id
              ORDER BY earning_type"
        );
        $stmt->execute([':id' => $payrollId]);
        $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deductions
        $stmt = $pdo->prepare(
            "SELECT deduction_type, description, quantity, unit_rate, amount
               FROM deduction
              WHERE payroll_id = :id
              ORDER BY deduction_type"
        );
        $stmt->execute([':id' => $payrollId]);
        $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'payslip_id'        => (int) $header['payslip_id'],
            'issue_date'        => $header['issue_date'],
            'employee_id'       => (int) $header['employee_id'],
            'employee_name'     => $header['employee_name'],
            'employee_number'   => $header['employee_number'],
            'position'          => $header['position'] ?? '',
            'branch_name'       => $header['branch_name'],
            'period'            => $header['period'],
            'period_start'      => $header['period_start'],
            'period_end'        => $header['period_end'],
            'pay_date'          => $header['pay_date'],
            'daily_rate'        => (float) $header['daily_rate_snapshot'],
            'gross_pay'         => (float) $header['gross_pay'],
            'total_deductions'  => (float) $header['total_deductions'],
            'net_pay'           => (float) $header['net_pay'],
            'earnings'          => $earnings,
            'deductions'        => $deductions,
        ];
    }

    // ===================================================================
    // 13th Month Pay (Wire #5)
    // ===================================================================

    /**
     * Compute 13th-month pay for employees with approved payroll in a given
     * calendar year, optionally limited by the historical branch assignment
     * captured on the payroll row or by employee.
     *
     * 13th month = total basic pay earned in the year / 12.
     * Total basic pay = SUM of payroll_earnings.amount WHERE earning_type = 'Basic'
     * across all Approved payroll runs in the year.
     *
     * @param array{branch_id?:int,employee_id?:int} $filters
     * @return list<array{employee_id:int,employee_name:string,employee_number:string,basic_total:float,thirteenth_month:float}>
     */
    public function compute13thMonth(int $year, array $filters = []): array
    {
        $where = [
            "pe.earning_type = 'Basic'",
            "pr.status = 'Approved'",
            'YEAR(pp.period_start) = :year',
        ];
        $parameters = [':year' => $year];

        if (($filters['branch_id'] ?? 0) > 0) {
            $where[] = 'eba.branch_id = :branch_id';
            $parameters[':branch_id'] = $filters['branch_id'];
        }

        if (($filters['employee_id'] ?? 0) > 0) {
            $where[] = 'p.employee_id = :employee_id';
            $parameters[':employee_id'] = $filters['employee_id'];
        }

        $sql = "SELECT e.employee_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    SUM(pe.amount) AS basic_total
               FROM payroll_earnings pe
               JOIN payroll p         ON p.payroll_id         = pe.payroll_id
               JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
               JOIN employee e        ON e.employee_id        = p.employee_id
                JOIN employee_branch_assignment eba
                  ON eba.branch_assignment_id = p.branch_assignment_id
               WHERE " . implode("\n                 AND ", $where) . "
              GROUP BY e.employee_id, e.last_name, e.first_name, e.employee_number
               ORDER BY e.last_name, e.first_name";

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($parameters);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $basicTotal      = (float) $row['basic_total'];
            $thirteenthMonth = round($basicTotal / 12, 2);
            $result[] = [
                'employee_id'      => (int) $row['employee_id'],
                'employee_name'    => $row['employee_name'],
                'employee_number'  => $row['employee_number'],
                'basic_total'      => $basicTotal,
                'thirteenth_month' => $thirteenthMonth,
            ];
        }
        return $result;
    }

    // ===================================================================
    // Private — Contribution calculations (Wire #3)
    // ===================================================================

    /** @param list<array<string,mixed>> $brackets */
    private function computeSss(float $eemr, array $brackets): float
    {
        foreach ($brackets as $b) {
            $from = (float) $b['salary_from'];
            $to   = $b['salary_to'] !== null ? (float) $b['salary_to'] : PHP_FLOAT_MAX;
            if ($eemr >= $from && $eemr <= $to) {
                return (float) $b['employee_share'];
            }
        }
        // Use last bracket if above ceiling
        if (!empty($brackets)) {
            return (float) end($brackets)['employee_share'];
        }
        return 0.0;
    }

    /** @param list<array<string,mixed>> $brackets */
    private function computeSssEmployer(float $eemr, array $brackets): float
    {
        foreach ($brackets as $b) {
            $from = (float) $b['salary_from'];
            $to   = $b['salary_to'] !== null ? (float) $b['salary_to'] : PHP_FLOAT_MAX;
            if ($eemr >= $from && $eemr <= $to) {
                return (float) $b['employer_share'];
            }
        }
        if (!empty($brackets)) {
            return (float) end($brackets)['employer_share'];
        }
        return 0.0;
    }

    /** @param array<string,mixed> $rate */
    private function computePhilhealth(float $eemr, array $rate, bool $employeeShare): float
    {
        $floor   = $rate['basis_floor']   !== null ? (float) $rate['basis_floor']   : 0.0;
        $ceiling = $rate['basis_ceiling'] !== null ? (float) $rate['basis_ceiling'] : PHP_FLOAT_MAX;
        $basis   = max($floor, min($ceiling, $eemr));

        $shareRate = $employeeShare
            ? (float) $rate['employee_share_decimal']
            : ((float) $rate['rate_decimal'] - (float) $rate['employee_share_decimal']);

        return round($basis * $shareRate, 2);
    }

    /** @param array<string,mixed> $rate */
    private function computePagibig(float $eemr, array $rate, bool $employeeShare): float
    {
        // Fixed amount model (demo fixture)
        if ($rate['employee_fixed_amount'] !== null) {
            return $employeeShare
                ? (float) $rate['employee_fixed_amount']
                : (float) ($rate['employer_fixed_amount'] ?? 0.0);
        }

        // Rate model
        $ceiling = $rate['basis_ceiling'] !== null ? (float) $rate['basis_ceiling'] : $eemr;
        $basis   = min($ceiling, $eemr);
        $r       = (float) ($rate['rate_decimal'] ?? 0.02);
        return round($basis * $r, 2);
    }

    private function outstandingCashAdvance(PDO $pdo, int $employeeId): float
    {
        // Deduct minimum weekly repayment (₱500) if there is an active cash advance
        $stmt = $pdo->prepare(
            "SELECT remaining_balance
               FROM cash_advance_history
              WHERE employee_id = :id AND status = 'Active'
              ORDER BY approved_at DESC
              LIMIT 1"
        );
        $stmt->execute([':id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }
        $balance    = (float) $row['remaining_balance'];
        $repayment  = 500.0; // ₱500/week (ADR-0001)
        return min($repayment, $balance);
    }

    /**
     * @return array<string,list<array{start_time:string,end_time:string}>> Approved OT request windows, keyed by date.
     */
    private function approvedOvertimeRequestsByDate(PDO $pdo, int $employeeId, string $periodStart, string $periodEnd): array
    {
        $stmt = $pdo->prepare(
            "SELECT ord.overtime_date, ord.start_time, ord.end_time
               FROM request r
               JOIN request_type rt ON rt.request_type_id = r.request_type_id
               JOIN overtime_request_detail ord ON ord.request_id = r.request_id
              WHERE r.employee_id = :emp_id
                AND rt.type_name = 'Overtime'
                AND r.status = 'Approved'
                AND ord.overtime_date BETWEEN :start AND :end
              ORDER BY ord.overtime_date, ord.start_time"
        );
        $stmt->execute([':emp_id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);

        $approved = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $approved[(string) $row['overtime_date']][] = [
                'start_time' => (string) $row['start_time'],
                'end_time' => (string) $row['end_time'],
            ];
        }
        return $approved;
    }

    /**
     * Match approved requests with actual attendance. ReviewRequired attendance is
     * deliberately excluded from OT pay until HR resolves the biometric exception.
     *
     * @param array<string,list<array{start_time:string,end_time:string}>> $approvedOtByDate
     * @return array<string,int> Payable OT minutes, keyed by attendance date.
     */
    private function payableOvertimeByDate(
        PDO $pdo,
        int $employeeId,
        string $periodStart,
        string $periodEnd,
        array $approvedOtByDate,
    ): array {
        if ($approvedOtByDate === []) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT attendance_date, time_out, overtime_minutes
               FROM attendance
              WHERE employee_id = :emp_id
                AND attendance_date BETWEEN :start AND :end
                AND status IN ('Complete', 'Approved')
                AND overtime_minutes > 0"
        );
        $stmt->execute([':emp_id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);

        $payable = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string) $row['attendance_date'];
            $minutes = $this->approvedOvertimeCalc->payableMinutes(
                (int) $row['overtime_minutes'],
                $row['time_out'] !== null ? (string) $row['time_out'] : null,
                $approvedOtByDate[$date] ?? [],
            );
            if ($minutes > 0) {
                $payable[$date] = $minutes;
            }
        }
        return $payable;
    }

    // ===================================================================
    // Private — DB helpers
    // ===================================================================

    /** @return array<string,mixed> */
    private function resolveActivePolicy(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT policy_id, policy_code, eemr_days_per_year,
                    late_rate_per_minute, rounding_mode
               FROM payroll_policy_version
              WHERE status = 'Approved'
              ORDER BY effective_from DESC
              LIMIT 1"
        );
        $policy = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$policy) {
            // Fallback defaults if no approved policy seeded
            return [
                'policy_id'            => 1,
                'eemr_days_per_year'   => 313,
                'late_rate_per_minute' => 1.00,
                'rounding_mode'        => 'HalfUp',
            ];
        }
        return $policy;
    }

    /** @return array<string,mixed> */
    private function resolveRunPolicy(PDO $pdo, int $runId): array
    {
        $stmt = $pdo->prepare(
            "SELECT ppv.policy_id, ppv.eemr_days_per_year,
                    ppv.late_rate_per_minute, ppv.rounding_mode
               FROM payroll_run pr
               JOIN payroll_policy_version ppv ON ppv.policy_id = pr.payroll_policy_id
              WHERE pr.payroll_run_id = :id"
        );
        $stmt->execute([':id' => $runId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$policy) {
            return $this->resolveActivePolicy($pdo);
        }
        return $policy;
    }

    /** @return array<string,mixed> */
    private function resolveContribPolicy(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT contribution_policy_id
               FROM contribution_policy_version
              WHERE status = 'Approved'
              ORDER BY effective_from DESC
              LIMIT 1"
        );
        $policy = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$policy) {
            throw new RuntimeException(
                'No approved contribution policy found. '
                . 'Please approve a contribution policy version before computing payroll.'
            );
        }
        return $policy;
    }

    /** @return list<array<string,mixed>> */
    private function loadSssBrackets(PDO $pdo, int $policyId): array
    {
        $stmt = $pdo->prepare(
            "SELECT salary_from, salary_to, employee_share, employer_share
               FROM sss_bracket
              WHERE contribution_policy_id = :id
              ORDER BY salary_from ASC"
        );
        $stmt->execute([':id' => $policyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    private function loadPhilhealthRate(PDO $pdo, int $policyId): array
    {
        $stmt = $pdo->prepare(
            "SELECT rate_decimal, basis_floor, basis_ceiling, employee_share_decimal
               FROM philhealth_rate
              WHERE contribution_policy_id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $policyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['rate_decimal' => 0.05, 'basis_floor' => 10000, 'basis_ceiling' => 100000, 'employee_share_decimal' => 0.025];
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function loadPagibigRate(PDO $pdo, int $policyId): array
    {
        $stmt = $pdo->prepare(
            "SELECT rate_decimal, basis_ceiling, employee_fixed_amount, employer_fixed_amount
               FROM pagibig_rate
              WHERE contribution_policy_id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $policyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['rate_decimal' => 0.02, 'basis_ceiling' => 10000, 'employee_fixed_amount' => null, 'employer_fixed_amount' => null];
        }
        return $row;
    }

    private function insertEarning(
        PDO $pdo, int $payrollId, string $type, string $desc,
        float $qty, float $unitRate, float $multiplier, float $amount, string $now
    ): int {
        $stmt = $pdo->prepare(
            "INSERT INTO payroll_earnings
                (payroll_id, earning_type, description,
                 quantity, unit_rate, multiplier, amount,
                 calculation_details, created_at)
             VALUES
                (:pid, :type, :desc,
                 :qty, :rate, :mult, :amount,
                 :details, :now)"
        );
        $stmt->execute([
            ':pid'     => $payrollId,
            ':type'    => $type,
            ':desc'    => $desc,
            ':qty'     => $qty,
            ':rate'    => $unitRate,
            ':mult'    => $multiplier,
            ':amount'  => $amount,
            ':details' => json_encode(['qty' => $qty, 'unit_rate' => $unitRate, 'multiplier' => $multiplier]),
            ':now'     => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Insert a deduction row and return its ID.
     */
    private function insertDeduction(
        PDO $pdo, int $payrollId, string $type, string $desc,
        float $qty, float $unitRate, float $amount, string $now,
        bool $returnId = false
    ): int {
        $stmt = $pdo->prepare(
            "INSERT INTO deduction
                (payroll_id, deduction_type, description,
                 quantity, unit_rate, amount,
                 calculation_details, created_at)
             VALUES
                (:pid, :type, :desc,
                 :qty, :rate, :amount,
                 :details, :now)"
        );
        $stmt->execute([
            ':pid'     => $payrollId,
            ':type'    => $type,
            ':desc'    => $desc,
            ':qty'     => $qty,
            ':rate'    => $unitRate,
            ':amount'  => $amount,
            ':details' => json_encode(['qty' => $qty, 'unit_rate' => $unitRate]),
            ':now'     => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** True only for the Friday payday that is last in its calendar month. */
    private function isMonthlyContributionCutoff(string $payDate): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $payDate, new DateTimeZone('Asia/Manila'));
        if ($date === false || (int) $date->format('N') !== 5) {
            return false;
        }
        return $date->format('Y-m-d') === $date->modify('last friday of this month')->format('Y-m-d');
    }

    private function utcNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
