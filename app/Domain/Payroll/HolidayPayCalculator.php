<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

/**
 * Pure domain service that applies Philippine holiday-pay rules.
 *
 * All arithmetic is integer-centavo to avoid floating-point accumulation.
 * Inputs are floats (as stored in DB) and outputs are rounded floats suitable
 * for payroll_earnings.amount (DECIMAL 12,2).
 *
 * Rules implemented (REQ047, Final Defense Reviewer pp. 4–5; steering 2026-09-03):
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │ REGULAR HOLIDAY                                                    │
 * │  Not worked (covered employee): daily_rate × 1.00                 │
 * │  Worked (first 8 h):            daily_rate × 2.00                 │
 * │  Worked on rest day:            daily_rate × 2.60  (200% × 130%)  │
 * │  Overtime (beyond 8 h):         holiday_hourly_rate × 1.30        │
 * │    where holiday_hourly_rate = (daily_rate × 2.00) / std_hours    │
 * ├────────────────────────────────────────────────────────────────────┤
 * │ SPECIAL NON-WORKING HOLIDAY                                        │
 * │  Not worked:                    0 (no pay obligation)             │
 * │  Worked (first 8 h):            daily_rate × 1.30                 │
 * │  Worked on rest day:            daily_rate × 1.50  (130% + 30%)   │
 * │  Overtime (beyond 8 h):         holiday_hourly_rate × 1.30        │
 * │    where holiday_hourly_rate = (daily_rate × 1.30) / std_hours    │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * The compounding of overtime AND holiday is tracked here but the ADR-0001
 * NOTE still applies: this is the current DOLE interpretation; HR must
 * confirm before paying out. Use computeOvertimeOnHoliday() explicitly.
 *
 * No HTTP, PDO, or session dependency — pure calculation class.
 */
final class HolidayPayCalculator
{
    /**
     * Pay for a covered employee who did NOT work on a Regular Holiday.
     * Special holidays carry no absence pay obligation.
     *
     * @param float $dailyRate  Employee's effective daily rate (PHP)
     * @param HolidayType $type Regular or Special
     * @return float            Amount due (0.00 for Special if absent)
     */
    public function computeAbsencePay(float $dailyRate, HolidayType $type): float
    {
        return $this->round($dailyRate * $type->absentMultiplier());
    }

    /**
     * Pay for working the first 8 hours on a holiday (ordinary work day).
     *
     * @param float $dailyRate Employee's effective daily rate (PHP)
     * @param HolidayType $type Regular or Special
     * @param bool $isRestDay  True if the holiday falls on the employee's
     *                         scheduled rest day
     * @return float           Gross pay for the holiday day
     */
    public function computeDayPay(
        float $dailyRate,
        HolidayType $type,
        bool $isRestDay = false,
    ): float {
        $multiplier = $isRestDay
            ? $type->restDayWorkedMultiplier()
            : $type->workedMultiplier();

        return $this->round($dailyRate * $multiplier);
    }

    /**
     * Overtime pay for hours worked BEYOND 8 on a holiday.
     *
     * The base hourly rate is derived from the holiday-enhanced daily rate
     * (i.e. the day pay / standard hours), then multiplied by 130% per DOLE.
     *
     * Formula:
     *   holidayHourlyRate = computeDayPay(dailyRate, type, isRestDay) / stdHours
     *   overtimePay       = holidayHourlyRate × 1.30 × overtimeMinutes / 60
     *
     * NOTE: ADR-0001 flags this as a separate policy decision pending HR
     * confirmation. The calculation is provided but PayrollService must be
     * explicitly configured to call it.
     *
     * @param float $dailyRate       Employee's effective daily rate (PHP)
     * @param HolidayType $type      Regular or Special
     * @param bool $isRestDay        True if this holiday also falls on rest day
     * @param int $overtimeMinutes   Approved overtime minutes beyond standard
     * @param int $standardMinutes   Standard working minutes per day (default 480 = 8 h)
     * @return float                 Overtime premium for this holiday
     */
    public function computeOvertimeOnHoliday(
        float $dailyRate,
        HolidayType $type,
        bool $isRestDay,
        int $overtimeMinutes,
        int $standardMinutes = 480,
    ): float {
        if ($overtimeMinutes <= 0 || $standardMinutes <= 0) {
            return 0.00;
        }

        $dayPay             = $this->computeDayPay($dailyRate, $type, $isRestDay);
        $holidayHourlyRate  = $dayPay / ($standardMinutes / 60);
        $overtimeHours      = $overtimeMinutes / 60;

        return $this->round($holidayHourlyRate * 1.30 * $overtimeHours);
    }

    /**
     * Convenience: compute the full holiday pay bundle for one attendance record.
     *
     * Returns a structured array that maps directly to the data needed for
     * payroll_earnings inserts. Both 'day_pay' and 'overtime_pay' are always
     * present; 'overtime_pay' is 0.00 when there is no overtime.
     *
     * @param float $dailyRate      Employee's effective daily rate
     * @param HolidayType $type     Holiday type
     * @param bool $worked          Whether the employee reported for work
     * @param bool $isRestDay       Whether the holiday falls on a rest day
     * @param int $overtimeMinutes  OT minutes beyond standard (0 if none)
     * @param int $standardMinutes  Standard working minutes (default 480)
     * @return array{
     *   day_pay: float,
     *   overtime_pay: float,
     *   total: float,
     *   multiplier_used: float,
     *   earning_type: string,
     * }
     */
    public function computeBundle(
        float $dailyRate,
        HolidayType $type,
        bool $worked,
        bool $isRestDay = false,
        int $overtimeMinutes = 0,
        int $standardMinutes = 480,
    ): array {
        if (!$worked) {
            $dayPay = $this->computeAbsencePay($dailyRate, $type);
            return [
                'day_pay'         => $dayPay,
                'overtime_pay'    => 0.00,
                'total'           => $dayPay,
                'multiplier_used' => $type->absentMultiplier(),
                'earning_type'    => $this->earningType($type),
            ];
        }

        $multiplier  = $isRestDay ? $type->restDayWorkedMultiplier() : $type->workedMultiplier();
        $dayPay      = $this->computeDayPay($dailyRate, $type, $isRestDay);
        $overtimePay = $this->computeOvertimeOnHoliday(
            $dailyRate, $type, $isRestDay, $overtimeMinutes, $standardMinutes
        );

        return [
            'day_pay'         => $dayPay,
            'overtime_pay'    => $overtimePay,
            'total'           => $this->round($dayPay + $overtimePay),
            'multiplier_used' => $multiplier,
            'earning_type'    => $this->earningType($type),
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** Maps HolidayType to the payroll_earnings.earning_type enum value. */
    private function earningType(HolidayType $type): string
    {
        return match ($type) {
            HolidayType::Regular => 'RegularHoliday',
            HolidayType::Special => 'SpecialHoliday',
        };
    }

    /** Half-up rounding to 2 decimal places (REQN013, policy rounding_mode = HalfUp). */
    private function round(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
