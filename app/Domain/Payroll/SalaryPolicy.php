<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

use RuntimeException;

/** Authoritative validation rules for employee daily rates. */
final class SalaryPolicy
{
    public const MAX_DAILY_RATE = 800.00;

    public static function assertWithinDailyRateLimit(float $dailyRate): void
    {
        if ($dailyRate > self::MAX_DAILY_RATE) {
            throw new RuntimeException(
                'Daily rate cannot exceed ₱' . number_format(self::MAX_DAILY_RATE, 2) . '.'
            );
        }
    }
}
