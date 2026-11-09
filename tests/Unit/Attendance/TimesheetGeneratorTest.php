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

    public function testEarlyTimeInWithoutTimeOutRemainsIncomplete(): void
    {
        $tz = new DateTimeZone('Asia/Manila');
        $punch = new ParsedPunch(1, '00123', new DateTimeImmutable('2026-08-03 07:45', $tz), 2, 'E', '07:45', null, null, null);

        $attendance = (new TimesheetGenerator())->generate(9, '2026-08-03', [$punch], new WorkSchedule(1, '08:00', '17:00'));

        self::assertTrue($attendance->isIncomplete());
        self::assertSame('07:45', $attendance->timeIn?->format('H:i'));
        self::assertNull($attendance->timeOut);
        self::assertSame(0, $attendance->workedMinutes);
        self::assertSame(0, $attendance->lateMinutes);
        self::assertSame(0, $attendance->overtimeMinutes);
    }

    public function testSinglePunchAfterMidpointIsStoredAsTimeoutOnly(): void
    {
        $tz    = new DateTimeZone('Asia/Manila');
        // Schedule 07:00–17:00 → midpoint 12:00. Punch at 17:01 is past midpoint.
        $punch = new ParsedPunch(1, '00129', new DateTimeImmutable('2026-08-02 17:01', $tz), 4, 'F', '17:01', null, null, null);

        $attendance = (new TimesheetGenerator())->generate(9, '2026-08-02', [$punch], new WorkSchedule(1, '07:00', '17:00'));

        self::assertTrue($attendance->isIncomplete());
        self::assertContains('TIMEOUT_ONLY', $attendance->flags);
        self::assertNull($attendance->timeIn);
        self::assertSame('17:01', $attendance->timeOut?->format('H:i'));
        self::assertSame(0, $attendance->workedMinutes);
    }

    public function testSinglePunchBeforeMidpointIsStoredAsTimeinOnly(): void
    {
        $tz    = new DateTimeZone('Asia/Manila');
        // Schedule 07:00–17:00 → midpoint 12:00. Punch at 06:40 is before midpoint.
        $punch = new ParsedPunch(1, '00129', new DateTimeImmutable('2026-08-06 06:40', $tz), 10, 'J', '06:40', null, null, null);

        $attendance = (new TimesheetGenerator())->generate(9, '2026-08-06', [$punch], new WorkSchedule(1, '07:00', '17:00'));

        self::assertTrue($attendance->isIncomplete());
        self::assertNotContains('TIMEOUT_ONLY', $attendance->flags);
        self::assertSame('06:40', $attendance->timeIn?->format('H:i'));
        self::assertNull($attendance->timeOut);
        self::assertSame(0, $attendance->workedMinutes);
    }

    public function testItAppliesTheScheduleGracePeriodToImportedLateness(): void
    {
        $tz = new DateTimeZone('Asia/Manila');
        $punches = [
            new ParsedPunch(1, '00123', new DateTimeImmutable('2026-08-03 08:10', $tz), 2, 'E', '08:10', null, null, null),
            new ParsedPunch(1, '00123', new DateTimeImmutable('2026-08-03 17:00', $tz), 2, 'E', '17:00', null, null, null),
        ];

        $attendance = (new TimesheetGenerator())->generate(
            9,
            '2026-08-03',
            $punches,
            new WorkSchedule(1, '08:00', '17:00', 60, 480, 5),
        );

        self::assertSame(5, $attendance->lateMinutes);
    }
}
