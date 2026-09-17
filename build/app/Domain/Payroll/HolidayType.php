<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

/**
 * Classification of a Philippine public holiday.
 *
 * Regular holidays are mandated by law (Republic Act No. 9492 and later
 * proclamations); special days are declared annually by presidential
 * proclamation and may be working or non-working.
 *
 * Pay rules (REQ047, Final Defense Reviewer pp. 4–5):
 *   Regular          — 200 % daily rate when worked; 100 % when not worked (covered employees).
 *   Special          — 100 % daily rate when worked (Special Working Holiday); no pay when not worked.
 *   SpecialNonWorking— 130 % daily rate when worked (Special Non-Working Holiday); no pay when not worked.
 *
 * These values are the multipliers recognised by the holiday_calendar table
 * constraint: Regular = 2.00, Special = 1.00, SpecialNonWorking = 1.30.
 */
enum HolidayType: string
{
    case Regular           = 'Regular';
    case Special           = 'Special';
    case SpecialNonWorking = 'SpecialNonWorking';

    /** Multiplier applied to daily_rate when the employee WORKS on this holiday. */
    public function workedMultiplier(): float
    {
        return match ($this) {
            self::Regular           => 2.00,
            self::Special           => 1.00,   // Special Working Holiday — regular day rate
            self::SpecialNonWorking => 1.30,   // Special Non-Working Holiday — 30 % premium
        };
    }

    /**
     * Multiplier applied to daily_rate when the employee does NOT work.
     * Regular-holiday covered employees receive 100 % of their daily rate.
     * Special and Special Non-Working days carry no pay obligation for absences.
     */
    public function absentMultiplier(): float
    {
        return match ($this) {
            self::Regular           => 1.00,
            self::Special           => 0.00,
            self::SpecialNonWorking => 0.00,
        };
    }

    /**
     * Multiplier when the holiday also falls on the employee's scheduled rest
     * day and the employee WORKS.
     *
     * Regular holiday + rest day:             daily_rate × 260 %  (200 % × 130 %).
     * Special holiday + rest day:             daily_rate × 130 %  (100 % + 30 %).
     * Special Non-Working holiday + rest day: daily_rate × 150 %  (130 % + 30 %).
     *
     * NOTE: The treatment of overtime on a holiday is a separate policy
     * decision (ADR-0001) and is NOT compounded here.
     */
    public function restDayWorkedMultiplier(): float
    {
        return match ($this) {
            self::Regular           => 2.60,   // 200 % × 130 %
            self::Special           => 1.30,   // 100 % + 30 % of daily_rate
            self::SpecialNonWorking => 1.50,   // 130 % + 30 % of daily_rate
        };
    }
}
