<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Payroll;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wbpms\Domain\Payroll\SalaryPolicy;

final class SalaryPolicyTest extends TestCase
{
    public function testMaximumDailyRateIsAccepted(): void
    {
        SalaryPolicy::assertWithinDailyRateLimit(SalaryPolicy::MAX_DAILY_RATE);

        self::assertSame(800.00, SalaryPolicy::MAX_DAILY_RATE);
    }

    public function testRateAboveMaximumIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Daily rate cannot exceed ₱800.00.');

        SalaryPolicy::assertWithinDailyRateLimit(800.01);
    }
}
