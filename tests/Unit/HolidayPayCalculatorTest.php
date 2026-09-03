<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Domain\Payroll\HolidayPayCalculator;
use Wbpms\Domain\Payroll\HolidayType;

/**
 * Unit tests for HolidayPayCalculator.
 *
 * All tests are pure calculations — no database or HTTP dependency.
 *
 * Coverage:
 *   - computeAbsencePay: Regular and Special holiday, zero values
 *   - computeDayPay: Regular/Special, ordinary day vs rest day
 *   - computeOvertimeOnHoliday: Regular/Special, rest day compounding, edge cases
 *   - computeBundle: worked/absent branches, full output keys
 *   - Rounding (REQ047, REQN013)
 *
 * REQ047 | ADR-0001 §Holiday Pay | REQN013 (rounding_mode = HalfUp)
 */
final class HolidayPayCalculatorTest extends TestCase
{
    private HolidayPayCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new HolidayPayCalculator();
    }

    // ===================================================================
    // computeAbsencePay
    // ===================================================================

    public function testAbsencePayRegularHolidayIsDailyRate(): void
    {
        // A covered employee who does not work on a Regular Holiday receives 100 % daily rate.
        $result = $this->calc->computeAbsencePay(460.00, HolidayType::Regular);
        $this->assertSame(460.00, $result, 'Regular holiday absence pay must equal daily rate (1.00×).');
    }

    public function testAbsencePaySpecialHolidayIsZero(): void
    {
        // Special Non-Working Days carry no pay obligation for absences.
        $result = $this->calc->computeAbsencePay(460.00, HolidayType::Special);
        $this->assertSame(0.00, $result, 'Special holiday absence pay must be ₱0.00.');
    }

    public function testAbsencePayZeroDailyRateReturnsZero(): void
    {
        $this->assertSame(0.00, $this->calc->computeAbsencePay(0.00, HolidayType::Regular));
        $this->assertSame(0.00, $this->calc->computeAbsencePay(0.00, HolidayType::Special));
    }

    public function testAbsencePayRoundingHalfUp(): void
    {
        // ₱461.005 × 1.00 = ₱461.005 → rounds to ₱461.01 (half-up)
        $result = $this->calc->computeAbsencePay(461.005, HolidayType::Regular);
        $this->assertSame(461.01, $result, 'Absence pay must use half-up rounding (REQN013).');
    }

    // ===================================================================
    // computeDayPay — Regular Holiday
    // ===================================================================

    public function testDayPayRegularOrdinaryDay(): void
    {
        // Regular holiday, ordinary work day: 460.00 × 2.00 = 920.00
        $result = $this->calc->computeDayPay(460.00, HolidayType::Regular, false);
        $this->assertSame(920.00, $result, 'Regular holiday day pay should be 200 % of daily rate.');
    }

    public function testDayPayRegularRestDay(): void
    {
        // Regular holiday + rest day: 460.00 × 2.60 = 1,196.00
        $result = $this->calc->computeDayPay(460.00, HolidayType::Regular, true);
        $this->assertSame(1196.00, $result, 'Regular holiday + rest day pay should be 260 % of daily rate.');
    }

    // ===================================================================
    // computeDayPay — Special Holiday
    // ===================================================================

    public function testDayPaySpecialOrdinaryDay(): void
    {
        // Special holiday, ordinary day: 460.00 × 1.30 = 598.00
        $result = $this->calc->computeDayPay(460.00, HolidayType::Special, false);
        $this->assertSame(598.00, $result, 'Special holiday day pay should be 130 % of daily rate.');
    }

    public function testDayPaySpecialRestDay(): void
    {
        // Special holiday + rest day: 460.00 × 1.50 = 690.00
        $result = $this->calc->computeDayPay(460.00, HolidayType::Special, true);
        $this->assertSame(690.00, $result, 'Special holiday + rest day pay should be 150 % of daily rate.');
    }

    public function testDayPayDefaultIsOrdinaryDay(): void
    {
        // isRestDay defaults to false
        $ordinary  = $this->calc->computeDayPay(460.00, HolidayType::Regular);
        $explicit  = $this->calc->computeDayPay(460.00, HolidayType::Regular, false);
        $this->assertSame($explicit, $ordinary);
    }

    public function testDayPayRoundingHalfUp(): void
    {
        // ₱1.005 × 2.00 = ₱2.01 (half-up)
        $result = $this->calc->computeDayPay(1.005, HolidayType::Regular, false);
        $this->assertSame(2.01, $result, 'Day pay must use half-up rounding (REQN013).');
    }

    // ===================================================================
    // computeOvertimeOnHoliday
    // ===================================================================

    public function testOvertimeOnRegularHoliday(): void
    {
        // Regular holiday, 60 min OT, 480 std min, no rest-day flag.
        // dayPay          = 460.00 × 2.00 = 920.00
        // holidayHourly   = 920.00 / 8    = 115.00
        // OT pay          = 115.00 × 1.30 × 1.0h = 149.50
        $result = $this->calc->computeOvertimeOnHoliday(
            dailyRate: 460.00,
            type: HolidayType::Regular,
            isRestDay: false,
            overtimeMinutes: 60,
            standardMinutes: 480,
        );
        $this->assertSame(149.50, $result, 'Regular holiday OT (1h) should be ₱149.50.');
    }

    public function testOvertimeOnSpecialHoliday(): void
    {
        // Special holiday, 60 min OT, 480 std min.
        // dayPay        = 460.00 × 1.30 = 598.00
        // holidayHourly = 598.00 / 8    = 74.75
        // OT pay        = 74.75 × 1.30 × 1.0h = 97.175 → 97.18 (half-up)
        $result = $this->calc->computeOvertimeOnHoliday(
            dailyRate: 460.00,
            type: HolidayType::Special,
            isRestDay: false,
            overtimeMinutes: 60,
            standardMinutes: 480,
        );
        $this->assertSame(97.18, $result, 'Special holiday OT (1h) should be ₱97.18.');
    }

    public function testOvertimeOnRegularHolidayRestDay(): void
    {
        // Regular + rest day, 30 min OT.
        // dayPay        = 460.00 × 2.60 = 1196.00
        // holidayHourly = 1196.00 / 8   = 149.50
        // OT pay        = 149.50 × 1.30 × 0.5h = 97.175 → 97.18 (half-up)
        $result = $this->calc->computeOvertimeOnHoliday(
            dailyRate: 460.00,
            type: HolidayType::Regular,
            isRestDay: true,
            overtimeMinutes: 30,
            standardMinutes: 480,
        );
        $this->assertSame(97.18, $result, 'Regular holiday + rest day OT (30 min) should be ₱97.18.');
    }

    public function testOvertimeOnSpecialHolidayRestDay(): void
    {
        // Special + rest day, 60 min OT.
        // dayPay        = 460.00 × 1.50 = 690.00
        // holidayHourly = 690.00 / 8    = 86.25
        // OT pay        = 86.25 × 1.30 × 1.0h = 112.125 → 112.13 (half-up)
        $result = $this->calc->computeOvertimeOnHoliday(
            dailyRate: 460.00,
            type: HolidayType::Special,
            isRestDay: true,
            overtimeMinutes: 60,
            standardMinutes: 480,
        );
        $this->assertSame(112.13, $result, 'Special holiday + rest day OT (1h) should be ₱112.13.');
    }

    public function testZeroOvertimeMinutesReturnsZero(): void
    {
        $result = $this->calc->computeOvertimeOnHoliday(460.00, HolidayType::Regular, false, 0);
        $this->assertSame(0.00, $result, 'Zero OT minutes must return ₱0.00.');
    }

    public function testNegativeOvertimeMinutesReturnsZero(): void
    {
        $result = $this->calc->computeOvertimeOnHoliday(460.00, HolidayType::Regular, false, -30);
        $this->assertSame(0.00, $result, 'Negative OT minutes must return ₱0.00.');
    }

    public function testZeroStandardMinutesReturnsZero(): void
    {
        // Guard against divide-by-zero: if standardMinutes=0, return 0.
        $result = $this->calc->computeOvertimeOnHoliday(460.00, HolidayType::Regular, false, 60, 0);
        $this->assertSame(0.00, $result, 'Zero standard minutes must return ₱0.00 (avoid div-by-zero).');
    }

    // ===================================================================
    // computeBundle — absent branch
    // ===================================================================

    public function testBundleAbsentRegularHoliday(): void
    {
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Regular,
            worked: false,
        );

        $this->assertSame(460.00, $bundle['day_pay'],         'Absent Regular: day_pay = daily_rate.');
        $this->assertSame(0.00,   $bundle['overtime_pay'],    'Absent Regular: overtime_pay = 0.');
        $this->assertSame(460.00, $bundle['total'],           'Absent Regular: total = daily_rate.');
        $this->assertSame(1.00,   $bundle['multiplier_used'], 'Absent Regular: multiplier = 1.00.');
        $this->assertSame('RegularHoliday', $bundle['earning_type']);
    }

    public function testBundleAbsentSpecialHoliday(): void
    {
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Special,
            worked: false,
        );

        $this->assertSame(0.00, $bundle['day_pay'],         'Absent Special: day_pay = 0.');
        $this->assertSame(0.00, $bundle['overtime_pay'],    'Absent Special: overtime_pay = 0.');
        $this->assertSame(0.00, $bundle['total'],           'Absent Special: total = 0.');
        $this->assertSame(0.00, $bundle['multiplier_used'], 'Absent Special: multiplier = 0.');
        $this->assertSame('SpecialHoliday', $bundle['earning_type']);
    }

    // ===================================================================
    // computeBundle — worked branch
    // ===================================================================

    public function testBundleWorkedRegularHolidayNoOT(): void
    {
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Regular,
            worked: true,
            isRestDay: false,
            overtimeMinutes: 0,
        );

        $this->assertSame(920.00, $bundle['day_pay']);
        $this->assertSame(0.00,   $bundle['overtime_pay']);
        $this->assertSame(920.00, $bundle['total']);
        $this->assertSame(2.00,   $bundle['multiplier_used']);
        $this->assertSame('RegularHoliday', $bundle['earning_type']);
    }

    public function testBundleWorkedRegularHolidayWithOT(): void
    {
        // dayPay = 920.00; OT 60min → 149.50; total = 1,069.50
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Regular,
            worked: true,
            isRestDay: false,
            overtimeMinutes: 60,
            standardMinutes: 480,
        );

        $this->assertSame(920.00,  $bundle['day_pay']);
        $this->assertSame(149.50,  $bundle['overtime_pay']);
        $this->assertSame(1069.50, $bundle['total']);
        $this->assertSame(2.00,    $bundle['multiplier_used']);
    }

    public function testBundleWorkedSpecialHolidayNoOT(): void
    {
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Special,
            worked: true,
            isRestDay: false,
            overtimeMinutes: 0,
        );

        $this->assertSame(598.00, $bundle['day_pay']);
        $this->assertSame(0.00,   $bundle['overtime_pay']);
        $this->assertSame(598.00, $bundle['total']);
        $this->assertSame(1.30,   $bundle['multiplier_used']);
        $this->assertSame('SpecialHoliday', $bundle['earning_type']);
    }

    public function testBundleWorkedSpecialHolidayRestDayWithOT(): void
    {
        // Special + rest day: dayPay = 690.00; OT 60min → 112.13; total = 802.13
        $bundle = $this->calc->computeBundle(
            dailyRate: 460.00,
            type: HolidayType::Special,
            worked: true,
            isRestDay: true,
            overtimeMinutes: 60,
            standardMinutes: 480,
        );

        $this->assertSame(690.00, $bundle['day_pay']);
        $this->assertSame(112.13, $bundle['overtime_pay']);
        $this->assertSame(802.13, $bundle['total']);
        $this->assertSame(1.50,   $bundle['multiplier_used']);
    }

    public function testBundleContainsAllRequiredKeys(): void
    {
        $bundle = $this->calc->computeBundle(460.00, HolidayType::Regular, true);
        $this->assertArrayHasKey('day_pay',         $bundle);
        $this->assertArrayHasKey('overtime_pay',    $bundle);
        $this->assertArrayHasKey('total',           $bundle);
        $this->assertArrayHasKey('multiplier_used', $bundle);
        $this->assertArrayHasKey('earning_type',    $bundle);
    }

    public function testBundleDefaultsAreOrdinaryDayNoOT(): void
    {
        // computeBundle($rate, $type, $worked) with all defaults
        $default  = $this->calc->computeBundle(460.00, HolidayType::Regular, true);
        $explicit = $this->calc->computeBundle(460.00, HolidayType::Regular, true, false, 0, 480);
        $this->assertSame($explicit, $default, 'Default arguments must match explicit ordinary-day/no-OT values.');
    }

    // ===================================================================
    // Rounding edge cases (REQN013)
    // ===================================================================

    public function testOvertimeFractionalMinutesRoundsHalfUp(): void
    {
        // 90 min OT on Regular holiday at ₱460 / 480 std min.
        // dayPay        = 920.00
        // holidayHourly = 115.00
        // OT pay        = 115.00 × 1.30 × 1.5 = 224.25
        $result = $this->calc->computeOvertimeOnHoliday(460.00, HolidayType::Regular, false, 90, 480);
        $this->assertSame(224.25, $result);
    }

    public function testBundleTotalMatchesSumOfParts(): void
    {
        $bundle = $this->calc->computeBundle(
            dailyRate: 461.005,
            type: HolidayType::Special,
            worked: true,
            isRestDay: false,
            overtimeMinutes: 30,
            standardMinutes: 480,
        );

        // total should equal day_pay + overtime_pay (both already rounded)
        $expected = round($bundle['day_pay'] + $bundle['overtime_pay'], 2);
        $this->assertSame($expected, $bundle['total'],
            'Bundle total must equal rounded sum of day_pay + overtime_pay.');
    }
}
