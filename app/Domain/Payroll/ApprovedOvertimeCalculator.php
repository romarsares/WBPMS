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

        return min($attendanceOvertimeMinutes, $paid);
    }

    private function minutesFromMidnight(string $time): ?int
    {
        if (!preg_match('/^(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d)(?::[0-5]\d)?$/', $time, $matches)) {
            return null;
        }
        return ((int) $matches['hour'] * 60) + (int) $matches['minute'];
    }
}
