<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbpms\Domain\Payroll\ApprovedOvertimeCalculator;

final class ApprovedOvertimeCalculatorTest extends TestCase
{
    public function testNoApprovedRequestMeansNoPayableOvertime(): void
    {
        self::assertSame(0, (new ApprovedOvertimeCalculator())->payableMinutes(90, '18:30:00', []));
    }

    public function testApprovedRequestIsCappedByActualAttendanceOvertime(): void
    {
        self::assertSame(45, (new ApprovedOvertimeCalculator())->payableMinutes(45, '18:00:00', [
            ['start_time' => '16:00:00', 'end_time' => '19:00:00'],
        ]));
    }

    public function testOnlyTheActualAndApprovedTimeWindowIsPayable(): void
    {
        self::assertSame(60, (new ApprovedOvertimeCalculator())->payableMinutes(120, '19:00:00', [
            ['start_time' => '18:00:00', 'end_time' => '20:00:00'],
        ]));
    }

    public function testNonOverlappingRequestDoesNotProducePayableOvertime(): void
    {
        self::assertSame(0, (new ApprovedOvertimeCalculator())->payableMinutes(69, '17:11:00', [
            ['start_time' => '18:00:00', 'end_time' => '19:00:00'],
        ]));
    }

    public function testOverlappingRequestsDoNotDoublePayTheSameMinutes(): void
    {
        self::assertSame(60, (new ApprovedOvertimeCalculator())->payableMinutes(60, '18:00:00', [
            ['start_time' => '17:00:00', 'end_time' => '17:45:00'],
            ['start_time' => '17:30:00', 'end_time' => '18:30:00'],
        ]));
    }
}
