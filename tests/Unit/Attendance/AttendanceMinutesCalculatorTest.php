<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wbpms\Domain\Attendance\AttendanceMinutesCalculator;
use Wbpms\Domain\Attendance\WorkSchedule;

final class AttendanceMinutesCalculatorTest extends TestCase
{
    public function testPaidMinutesPreventFalseUndertimeBeforeWallClockEnd(): void
    {
        $schedule = new WorkSchedule(1, '07:00', '16:00', 60, 480, 30);

        [$worked, $late, $undertime] = AttendanceMinutesCalculator::calculate(
            new DateTimeImmutable('2026-08-25 07:12:00', new DateTimeZone('Asia/Manila')),
            new DateTimeImmutable('2026-08-25 16:30:00', new DateTimeZone('Asia/Manila')),
            $schedule,
        );

        self::assertSame(498, $worked);
        self::assertSame(0, $late);
        self::assertSame(0, $undertime);
    }

    public function testUndertimeIsTheMissingRequiredPaidMinutes(): void
    {
        $schedule = new WorkSchedule(1, '07:00', '16:00', 60, 480);

        [$worked, $late, $undertime] = AttendanceMinutesCalculator::calculate(
            new DateTimeImmutable('2026-08-25 07:00:00', new DateTimeZone('Asia/Manila')),
            new DateTimeImmutable('2026-08-25 15:00:00', new DateTimeZone('Asia/Manila')),
            $schedule,
        );

        self::assertSame(420, $worked);
        self::assertSame(0, $late);
        self::assertSame(60, $undertime);
    }

    public function testOvernightShiftUsesItsRequiredPaidMinutes(): void
    {
        $schedule = new WorkSchedule(2, '20:00', '05:00', 30, 510);

        [$worked, $late, $undertime] = AttendanceMinutesCalculator::calculate(
            new DateTimeImmutable('2026-08-25 20:00:00', new DateTimeZone('Asia/Manila')),
            new DateTimeImmutable('2026-08-26 05:00:00', new DateTimeZone('Asia/Manila')),
            $schedule,
        );

        self::assertSame(510, $worked);
        self::assertSame(0, $late);
        self::assertSame(0, $undertime);
    }
}
