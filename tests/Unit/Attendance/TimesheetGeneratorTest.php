<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance;

use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use Wbpms\Domain\Attendance\TimesheetGenerator;
use Wbpms\Domain\Attendance\WorkSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class TimesheetGeneratorTest extends TestCase
{
    public function testItCalculatesAndFlagsAMultiPunchDayWithoutDiscardingEvidence(): void
    {
        $tz = new DateTimeZone('Asia/Manila');
        $punches = array_map(static fn (string $time): ParsedPunch => new ParsedPunch(1, '00123', new DateTimeImmutable("2026-08-03 {$time}", $tz), 2, 'E', $time, null, null, null), ['08:10', '12:00', '13:00', '18:15']);
        $attendance = (new TimesheetGenerator())->generate(9, '2026-08-03', $punches, new WorkSchedule(1, '08:00', '17:00'));
        self::assertSame('08:10', $attendance->timeIn?->format('H:i'));
        self::assertSame('18:15', $attendance->timeOut?->format('H:i'));
        self::assertSame(4, count($attendance->punches));
        self::assertSame(['MULTI_PUNCH_REVIEW'], $attendance->flags);
        self::assertSame(10, $attendance->lateMinutes);
        self::assertSame(65, $attendance->overtimeMinutes);
    }

    public function testItFlagsASinglePunchAsIncomplete(): void
    {
        $tz = new DateTimeZone('Asia/Manila');
        $punch = new ParsedPunch(1, '00123', new DateTimeImmutable('2026-08-03 08:00', $tz), 2, 'E', '08:00', null, null, null);
        $attendance = (new TimesheetGenerator())->generate(9, '2026-08-03', [$punch], new WorkSchedule(1, '08:00', '17:00'));
        self::assertTrue($attendance->isIncomplete());
        self::assertSame(0, $attendance->workedMinutes);
    }
}
