<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance;

use DateTimeImmutable;

/** Calculates payable attendance minutes from a work schedule. */
final class AttendanceMinutesCalculator
{
    /** @return array{int, int, int} worked, late, undertime */
    public static function calculate(
        DateTimeImmutable $timeIn,
        DateTimeImmutable $timeOut,
        WorkSchedule $schedule,
    ): array {
        $scheduledStart = $schedule->startOn($timeIn, $timeIn->getTimezone());
        $scheduledEnd = $schedule->endOn($timeIn, $timeIn->getTimezone());

        // An end time at or before the start time represents an overnight shift.
        if ($scheduledEnd <= $scheduledStart) {
            $scheduledEnd = $scheduledEnd->modify('+1 day');
            if ($timeOut <= $timeIn) {
                $timeOut = $timeOut->modify('+1 day');
            }
        }

        $elapsed = max(0, intdiv($timeOut->getTimestamp() - $timeIn->getTimestamp(), 60));
        $worked = max(0, $elapsed - $schedule->unpaidBreakMinutes);
        $late = max(0, intdiv($timeIn->getTimestamp() - $scheduledStart->getTimestamp(), 60) - $schedule->graceMinutes);

        // A schedule's end time includes the unpaid break. Undertime must be
        // measured against required paid minutes, otherwise a full shift that
        // ends before the wall-clock end is incorrectly penalised.
        $undertime = max(0, $schedule->standardMinutes - $worked);

        return [$worked, $late, $undertime];
    }
}
