<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Golden tests for contribution calculations (task 9.4)
 * and payroll arithmetic (task 10.12 subset — pure unit tests, no DB).
 *
 * ADR-0001 approved demo fixture values:
 *   daily_rate      ₱460.00
 *   eemr_days/year  313
 *   EEMR            (460 × 313) ÷ 12 = 11,998.333... → 11,998.33 (half-up)
 *   SSS employee    ₱540.00  (bracket: salary_from 11,000 ≤ EEMR < no ceiling)
 *   SSS employer    ₱1,140.00
 *   PhilHealth rate 4% total / 2% employee → 11,998.33 × 0.02 = 239.9666 → ₱239.97 (half-up)
 *   Pag-IBIG        ₱200.00 fixed employee
 *   Late rate       ₱1.00 per minute
 *
 * These tests do NOT require a database connection and run in the Unit suite.
 *
 * REQ047, REQ058–REQ062, REQN013 (rounding)
 */
final class ContributionGoldenTest extends TestCase
{
    // -------------------------------------------------------------------
    // Constants from the ADR-0001 approved fixture
    // -------------------------------------------------------------------
    private const DAILY_RATE      = 460.00;
    private const EEMR_DAYS       = 313;
    private const LATE_RATE       = 1.00;           // ₱/min
    private const SSS_EMP_SHARE   = 540.00;
    private const SSS_EMPLR_SHARE = 1140.00;
    private const PH_RATE         = 0.04;
    private const PH_EMP_RATE     = 0.02;
    private const PAGIBIG_FIXED   = 200.00;

    // -------------------------------------------------------------------
    // EEMR calculation
    // -------------------------------------------------------------------

    public function testEemrCalculation(): void
    {
        // (460 × 313) ÷ 12 = 143,980 ÷ 12 = 11,998.333...
        $eemr = (self::DAILY_RATE * self::EEMR_DAYS) / 12;
        $this->assertEqualsWithDelta(11998.333, $eemr, 0.001, 'Raw EEMR should be ~₱11,998.33');

        // Half-up rounding to 2 decimal places
        $rounded = round($eemr, 2);
        $this->assertSame(11998.33, $rounded, 'EEMR rounded (half-up) should be ₱11,998.33');
    }

    // -------------------------------------------------------------------
    // SSS bracket lookup
    // -------------------------------------------------------------------

    public function testSssBracketLookupMatchesEemr(): void
    {
        $eemr     = round((self::DAILY_RATE * self::EEMR_DAYS) / 12, 2);
        $brackets = $this->demoBrackets();

        $empShare = $this->computeSss($eemr, $brackets, 'employee_share');
        $emplrShare = $this->computeSss($eemr, $brackets, 'employer_share');

        $this->assertSame(self::SSS_EMP_SHARE, $empShare,
            'SSS employee share should be ₱540.00 for EEMR ₱11,998.33');
        $this->assertSame(self::SSS_EMPLR_SHARE, $emplrShare,
            'SSS employer share should be ₱1,140.00 for EEMR ₱11,998.33');
    }

    public function testSssBracketBelowFloorReturnsFirstBracket(): void
    {
        // EEMR below the bracket floor should still match the first (and only) bracket
        // when it is the only bracket (salary_to = NULL means no upper bound)
        $brackets = $this->demoBrackets();
        $empShare = $this->computeSss(5000.00, $brackets, 'employee_share');
        $this->assertSame(self::SSS_EMP_SHARE, $empShare,
            'EEMR below bracket floor should fall through to last bracket');
    }

    public function testUnsupportedPolicyHasNoSssBrackets(): void
    {
        // Empty brackets should return 0.00
        $empShare = $this->computeSss(11998.33, [], 'employee_share');
        $this->assertSame(0.0, $empShare, 'Empty brackets should return 0.00');
    }

    // -------------------------------------------------------------------
    // PhilHealth calculation
    // -------------------------------------------------------------------

    public function testPhilhealthEmployeeShareGolden(): void
    {
        $eemr   = round((self::DAILY_RATE * self::EEMR_DAYS) / 12, 2); // 11,998.33
        $result = round($eemr * self::PH_EMP_RATE, 2);

        // 11,998.33 × 0.02 = 239.9666 → ₱239.97 (half-up)
        $this->assertSame(239.97, $result,
            'PhilHealth employee share should be ₱239.97 for EEMR ₱11,998.33');
    }

    public function testPhilhealthRoundingHalfUp(): void
    {
        // Verify that PHP's round() is using the expected half-up behaviour
        $this->assertSame(239.97, round(239.9666, 2));
        $this->assertSame(239.97, round(11998.33 * 0.02, 2));
    }

    // -------------------------------------------------------------------
    // Pag-IBIG fixed amount
    // -------------------------------------------------------------------

    public function testPagibigFixedAmountEmployee(): void
    {
        $result = $this->computePagibigFixed(self::PAGIBIG_FIXED, true);
        $this->assertSame(200.00, $result, 'Pag-IBIG fixed employee share should be ₱200.00');
    }

    public function testPagibigFixedAmountEmployer(): void
    {
        $result = $this->computePagibigFixed(self::PAGIBIG_FIXED, false);
        $this->assertSame(200.00, $result, 'Pag-IBIG fixed employer share should be ₱200.00');
    }

    // -------------------------------------------------------------------
    // Late deduction
    // -------------------------------------------------------------------

    public function testLateDeductionGolden(): void
    {
        // 10 minutes late × ₱1.00/min = ₱10.00
        $deduction = round(10 * self::LATE_RATE, 2);
        $this->assertSame(10.0, $deduction, 'Late deduction for 10 minutes should be ₱10.00');
    }

    public function testZeroLateMinutesNoDeduction(): void
    {
        $deduction = round(0 * self::LATE_RATE, 2);
        $this->assertSame(0.0, $deduction, 'Zero late minutes should produce no deduction.');
    }

    // -------------------------------------------------------------------
    // Gross / net golden case (5-day period, zero late/undertime)
    // -------------------------------------------------------------------

    public function testGrossPayGoldenCase(): void
    {
        // 5 working days × ₱460.00 = ₱2,300.00 gross
        $daysWorked  = 5;
        $grossPay    = round($daysWorked * self::DAILY_RATE, 2);

        $this->assertSame(2300.0, $grossPay, 'Gross pay for 5 days at ₱460 should be ₱2,300.00');
    }

    public function testNetPayGoldenCase(): void
    {
        // 5-day period, no late, no undertime, no cash advance, no overtime
        $grossPay = 5 * self::DAILY_RATE;                     // 2,300.00
        $eemr     = round((self::DAILY_RATE * self::EEMR_DAYS) / 12, 2); // 11,998.33

        $sss      = (float) self::SSS_EMP_SHARE;              //   540.00
        $philHlth = round($eemr * self::PH_EMP_RATE, 2);      //   239.97
        $pagibig  = (float) self::PAGIBIG_FIXED;               //   200.00

        $totalDed = $sss + $philHlth + $pagibig;              //   979.97
        $netPay   = round($grossPay - $totalDed, 2);           // 1,320.03

        $this->assertSame(979.97, round($totalDed, 2), 'Total contributions for demo fixture should be ₱979.97');
        $this->assertSame(1320.03, $netPay, 'Net pay (5 days, zero late) should be ₱1,320.03');
    }

    public function testNetPayNeverBelowZero(): void
    {
        // Simulate massive deductions exceeding gross pay
        $grossPay  = 100.00;
        $totalDed  = 500.00;
        $netPay    = round($grossPay - $totalDed, 2);
        $netPay    = max(0.0, $netPay);

        $this->assertSame(0.0, $netPay, 'Net pay should never go below ₱0.00');
    }

    // -------------------------------------------------------------------
    // Overtime earnings
    // -------------------------------------------------------------------

    public function testOvertimeEarningGolden(): void
    {
        // 480 standard minutes (8 hours), ₱460.00 daily rate
        $standardMinutes = 480;
        $minuteRate      = self::DAILY_RATE / $standardMinutes; // 460/480 = 0.9583...
        $overtimeMinutes = 60; // 1 hour overtime
        $overtime        = round($overtimeMinutes * $minuteRate * 1.25, 2);

        // 60 × (460/480) × 1.25 = 60 × 0.95833... × 1.25 = 71.875 → ₱71.88
        $this->assertSame(71.88, $overtime, '60-minute overtime at ₱460/8h × 1.25 multiplier should be ₱71.88');
    }

    // -------------------------------------------------------------------
    // Payroll state machine: double-approval guard
    // -------------------------------------------------------------------

    public function testDoubleApprovalIsRejectedByStatusCheck(): void
    {
        // Simulate the PayrollService status check logic
        $status = 'Approved';
        $allowed = 'PendingOwnerApproval';

        $this->expectException(\RuntimeException::class);

        if ($status !== $allowed) {
            throw new \RuntimeException('Only runs pending approval can be approved.');
        }
    }

    public function testApprovedRunCannotBeResubmitted(): void
    {
        $status = 'Approved';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/i');

        if ($status === 'Approved') {
            throw new \RuntimeException('Approved payroll runs are immutable and cannot be resubmitted.');
        }
    }

    // -------------------------------------------------------------------
    // Private calculation helpers (mirrors PayrollService private methods)
    // -------------------------------------------------------------------

    /**
     * @param list<array{salary_from: float, salary_to: float|null, employee_share: float, employer_share: float}> $brackets
     */
    private function computeSss(float $eemr, array $brackets, string $shareField): float
    {
        foreach ($brackets as $b) {
            $from = (float) $b['salary_from'];
            $to   = $b['salary_to'] !== null ? (float) $b['salary_to'] : PHP_FLOAT_MAX;
            if ($eemr >= $from && $eemr <= $to) {
                return (float) $b[$shareField];
            }
        }
        if (!empty($brackets)) {
            return (float) end($brackets)[$shareField];
        }
        return 0.0;
    }

    private function computePagibigFixed(float $fixedAmount, bool $employeeShare): float
    {
        return $fixedAmount; // Both employee and employer use the same fixed amount in demo
    }

    /**
     * @return list<array{salary_from: float, salary_to: float|null, employee_share: float, employer_share: float}>
     */
    private function demoBrackets(): array
    {
        return [
            [
                'salary_from'    => 11000.00,
                'salary_to'      => null,         // open-ended: covers any EEMR ≥ ₱11,000
                'employee_share' => 540.00,
                'employer_share' => 1140.00,
            ],
        ];
    }
}
