<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance;

use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use DateTimeImmutable;

/** Applies only the documented one/two/many-punch attendance policy. */
final class TimesheetGenerator
{
    /** @param list<ParsedPunch> $punches */
    public function generate(int $employeeId, string $attendanceDate, array $punches, WorkSchedule $schedule): GeneratedAttendance
    {
        usort($punches, static fn (ParsedPunch $left, ParsedPunch $right): int => $left->localTimestamp <=> $right->localTimestamp);
        $first = $punches[0] ?? null;
        $last  = count($punches) > 1 ? $punches[array_key_last($punches)] : null;
        $flags = [];

        if (count($punches) === 1 && $first !== null) {
            // Determine whether the sole punch is a time-in or a time-out by
            // comparing it against the schedule midpoint.  Punches that fall
            // on or after the midpoint are treated as time-outs (the employee
            // forgot to punch in); punches before the midpoint are time-ins
            // (the employee forgot to punch out).  Both cases are incomplete.
            $timezone  = $first->localTimestamp->getTimezone();
            $schedStart = $schedule->startOn($first->localTimestamp, $timezone);
            $schedEnd   = $schedule->endOn($first->localTimestamp, $timezone);
            if ($schedEnd->getTimestamp() <= $schedStart->getTimestamp()) {
                $schedEnd = $schedEnd->modify('+1 day');
            }
            $midpoint = (int) (($schedStart->getTimestamp() + $schedEnd->getTimestamp()) / 2);

            if ($first->localTimestamp->getTimestamp() >= $midpoint) {
                // Sole punch is past the schedule midpoint → timeout with no time-in
                $flags[] = 'INCOMPLETE';
                $flags[] = 'TIMEOUT_ONLY';
                return new GeneratedAttendance(
                    $employeeId,
                    $attendanceDate,
                    null,
                    $first->localTimestamp,
                    0, 0, 0, 0,
                    $punches,
                    $flags,
                );
            }

            // Sole punch is before the midpoint → time-in with no time-out
            $flags[] = 'INCOMPLETE';
        }

        if (count($punches) > 2) {
            $flags[] = 'MULTI_PUNCH_REVIEW';
        }

        [$worked, $late, $undertime, $overtime] = $this->calculate($first?->localTimestamp, $last?->localTimestamp, $schedule);
        return new GeneratedAttendance(
            $employeeId,
            $attendanceDate,
            $first?->localTimestamp,
            $last?->localTimestamp,
            $worked,
            $late,
            $undertime,
            $overtime,
            $punches,
            $flags,
        );
    }

    /** @return array{int, int, int, int} worked, late, undertime, overtime */
    private function calculate(?DateTimeImmutable $timeIn, ?DateTimeImmutable $timeOut, WorkSchedule $schedule): array
    {
        if ($timeIn === null || $timeOut === null) {
            return [0, 0, 0, 0];
        }
        $timezone = $timeIn->getTimezone();
        $scheduledStart = $schedule->startOn($timeIn, $timezone);
        $scheduledEnd = $schedule->endOn($timeIn, $timezone);
        if ($scheduledEnd <= $scheduledStart) {
            $scheduledEnd = $scheduledEnd->modify('+1 day');
        }
        $elapsed = max(0, intdiv($timeOut->getTimestamp() - $timeIn->getTimestamp(), 60));
        $worked = max(0, $elapsed - $schedule->unpaidBreakMinutes);
        // Match the HR adjustment rule: the schedule grace period reduces
        // chargeable lateness, never the recorded time-in.
        $late = max(0, intdiv($timeIn->getTimestamp() - $scheduledStart->getTimestamp(), 60) - $schedule->graceMinutes);
        $undertime = max(0, intdiv($scheduledEnd->getTimestamp() - $timeOut->getTimestamp(), 60));
        $overtime = max(0, $worked - $schedule->standardMinutes);
        return [$worked, $late, $undertime, $overtime];
    }
}
