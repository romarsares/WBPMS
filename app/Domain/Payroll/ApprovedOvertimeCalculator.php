<?php

declare(strict_types=1);

namespace Wbpms\Domain\Payroll;

/**
 * Determines the overtime minutes that may be paid for one attendance date.
 *
 * Attendance proves time worked; an approved overtime request authorises payment.
 * Payroll pays only the overlap between the recorded overtime interval and
 * approved request window(s), never more than actual overtime.
 */
final class ApprovedOvertimeCalculator
{
    /**
     * Return approved overtime for an attendance interval.
     *
     * Early arrivals and late departures are represented as separate overtime
     * windows. This prevents an early punch from shifting the post-shift
     * overtime window backwards when matching an approved request.
     *
     * @param list<array{start_time:string,end_time:string}> $approvedRequests
     */
    public function payableMinutesForAttendance(
        ?string $timeIn,
        ?string $timeOut,
        string $scheduledStart,
        string $scheduledEnd,
        int $unpaidBreakMinutes,
        int $standardMinutes,
        array $approvedRequests,
    ): int {
        if ($timeIn === null || $timeOut === null || $approvedRequests === []) {
            return 0;
        }

        $actualStart = $this->minutesFromMidnight($timeIn);
        $actualEnd = $this->minutesFromMidnight($timeOut);
        $scheduleStart = $this->minutesFromMidnight($scheduledStart);
        $scheduleEnd = $this->minutesFromMidnight($scheduledEnd);
        if ($actualStart === null || $actualEnd === null || $scheduleStart === null || $scheduleEnd === null) {
            return 0;
        }

        $overnight = $scheduleEnd <= $scheduleStart;
        if ($overnight) {
            $scheduleEnd += 1440;
            if ($actualEnd <= $actualStart) {
                $actualEnd += 1440;
            }
        }
        if ($actualEnd <= $actualStart) {
            return 0;
        }

        $workedMinutes = max(0, ($actualEnd - $actualStart) - max(0, $unpaidBreakMinutes));
        $recordedOvertime = max(0, $workedMinutes - max(1, $standardMinutes));
        if ($recordedOvertime === 0) {
            return 0;
        }

        $actualWindows = [];
        if ($actualStart < $scheduleStart) {
            $actualWindows[] = [$actualStart, min($actualEnd, $scheduleStart)];
        }
        if ($actualEnd > $scheduleEnd) {
            $actualWindows[] = [max($actualStart, $scheduleEnd), $actualEnd];
        }
        $actualWindows = array_values(array_filter(
            $actualWindows,
            static fn (array $window): bool => $window[1] > $window[0],
        ));
        if ($actualWindows === []) {
            return 0;
        }

        $overlaps = [];
        foreach ($approvedRequests as $request) {
            $requestStart = $this->minutesFromMidnight($request['start_time']);
            $requestEnd = $this->minutesFromMidnight($request['end_time']);
            if ($requestStart === null || $requestEnd === null) {
                continue;
            }
            if ($requestEnd <= $requestStart) {
                $requestEnd += 1440;
            }
            if ($overnight && $requestStart < $scheduleStart) {
                $requestStart += 1440;
                $requestEnd += 1440;
            }
            foreach ($actualWindows as [$actualWindowStart, $actualWindowEnd]) {
                $overlapStart = max($actualWindowStart, $requestStart);
                $overlapEnd = min($actualWindowEnd, $requestEnd);
                if ($overlapEnd > $overlapStart) {
                    $overlaps[] = [$overlapStart, $overlapEnd];
                }
            }
        }

        return min($recordedOvertime, $this->mergedMinutes($overlaps));
    }

    /**
     * @param list<array{start_time:string,end_time:string}> $approvedRequests
     */
    public function payableMinutes(
        int $attendanceOvertimeMinutes,
        ?string $attendanceTimeOut,
        array $approvedRequests,
    ): int
    {
        if ($attendanceOvertimeMinutes <= 0 || $attendanceTimeOut === null || $approvedRequests === []) {
            return 0;
        }

        $actualEnd = $this->minutesFromMidnight($attendanceTimeOut);
        if ($actualEnd === null) {
            return 0;
        }
        $actualStart = max(0, $actualEnd - $attendanceOvertimeMinutes);

        $intervals = [];
        foreach ($approvedRequests as $request) {
            $start = $this->minutesFromMidnight($request['start_time']);
            $end = $this->minutesFromMidnight($request['end_time']);
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }
            $overlapStart = max($actualStart, $start);
            $overlapEnd = min($actualEnd, $end);
            if ($overlapEnd > $overlapStart) {
                $intervals[] = [$overlapStart, $overlapEnd];
            }
        }

        return min($attendanceOvertimeMinutes, $this->mergedMinutes($intervals));
    }

    /** @param list<array{int,int}> $intervals */
    private function mergedMinutes(array $intervals): int
    {
        if ($intervals === []) {
            return 0;
        }
        usort($intervals, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        $paid = 0;
        [$start, $end] = array_shift($intervals);
        foreach ($intervals as [$nextStart, $nextEnd]) {
            if ($nextStart <= $end) {
                $end = max($end, $nextEnd);
                continue;
            }
            $paid += $end - $start;
            [$start, $end] = [$nextStart, $nextEnd];
        }
        $paid += $end - $start;

        return $paid;
    }

    private function minutesFromMidnight(string $time): ?int
    {
        if (!preg_match('/^(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d)(?::[0-5]\d)?$/', $time, $matches)) {
            return null;
        }
        return ((int) $matches['hour'] * 60) + (int) $matches['minute'];
    }
}
