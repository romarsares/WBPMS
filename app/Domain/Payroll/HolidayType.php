<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

/**
 * Classification of a Philippine public holiday.
 *
 * Regular holidays are mandated by law (Republic Act No. 9492 and later
 * proclamations); special non-working days are declared annually by
 * presidential proclamation.
 *
 * Pay rules (REQ047, Final Defense Reviewer pp. 4–5):
 *   Regular  — 200 % daily rate when worked; 100 % when not worked (covered employees).
 *   Special  — 130 % daily rate when worked; no pay when not worked.
 *
 * These values are the multipliers recognised by the holiday_calendar table
 * constraint: Regular = 2.00, Special = 1.30.
 */
enum HolidayType: string
{
    case Regular = 'Regular';
    case Special = 'Special';

    /** Multiplier applied to daily_rate when the employee WORKS on this holiday. */
    public function workedMultiplier(): float
    {
        return match ($this) {
            self::Regular => 2.00,
            self::Special => 1.30,
        };
    }

    /**
     * Multiplier applied to daily_rate when the employee does NOT work.
     * Regular-holiday covered employees receive 100 % of their daily rate.
     * Special non-working days carry no pay obligation for absences.
     */
    public function absentMultiplier(): float
    {
        return match ($this) {
            self::Regular => 1.00,
            self::Special => 0.00,
        };
    }

    /**
     * Additional multiplier when the holiday also falls on the employee's
     * scheduled rest day and the employee WORKS.
     *
     * Regular holiday + rest day: daily_rate × 260%  (200% × 130%).
     * Special holiday + rest day: daily_rate × 150%  (130% × 130 / 100;
     *   DOLE guidance: an additional 30% on top of the 130%).
     *
     * NOTE: The treatment of overtime on a holiday is a separate policy
     * decision (ADR-0001) and is NOT compounded here.
     */
    public function restDayWorkedMultiplier(): float
    {
        return match ($this) {
            self::Regular => 2.60,   // 200% × 130%
            self::Special => 1.50,   // 130% + 30% of daily_rate
        };
    }
}
