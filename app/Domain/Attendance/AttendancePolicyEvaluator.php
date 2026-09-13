<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance;

use DateTimeImmutable;

/**
 * AttendancePolicyEvaluator — pure attendance-policy threshold calculator.
 *
 * Determines which HR-review flags (REQ006 AC16–AC17) have been reached for a
 * single employee over one date range:
 *
 *   ConsecutiveLate   — three calendar-consecutive late arrivals
 *   TardinessMemoCap  — the third tardiness memorandum (ConsecutiveLate flag)
 *   TwoWeekAbsence    — fourteen consecutive calendar days without reporting,
 *                       none excused by an approved leave request
 *   ConsecutiveAWOL   — three consecutive unexcused days without reporting,
 *                       inside the employee's attendance span
 *
 * The evaluator is pure: it receives attendance rows, existing flags, approved
 * leave excused dates, and prior memorandum history, and returns only NEW
 * flag proposals. It never suspends or disciplines an employee — HR performs
 * the review (REQ006 AC17: the system SHALL NOT terminate automatically).
 *
 * Input conventions:
 *   - $attendance must be sorted ascending by attendance_date.
 *   - A day is "attended" when it has a row whose status is not 'Cancelled'
 *     and whose time_in is not NULL.
 *   - Absence gaps are evaluated only INSIDE the employee's attendance span
 *     (first attended day through last attended day) so that the edges of the
 *     selected window do not produce false positives.
 *   - A day covered by an approved leave request is "excused" and resets an
 *     unexcused-absence run.
 */
final class AttendancePolicyEvaluator
{
    public const CONSECUTIVE_LATE_THRESHOLD = 3;
    public const TARDINESS_MEMO_CAP = 3;
    public const TWO_WEEK_ABSENCE_DAYS = 14;
    public const CONSECUTIVE_AWOL_DAYS = 3;

    /**
     * @param list<array{attendance_date:string,time_in:?string,late_minutes:int,status:string}> $attendance Sorted ascending by date.
     * @param list<array{flag_type:string,triggering_date:string}> $existingFlags Flags already on file for this employee in the window.
     * @param list<string> $excusedDates          'YYYY-MM-DD' covered by an approved leave request.
     * @param int          $existingMemoCount     Prior ConsecutiveLate flags for this employee (any status).
     * @return list<array{flag_type:string,triggering_date:string,notes:string}>
     */
    public function evaluate(
        array $attendance = [],
        array $existingFlags = [],
        array $excusedDates = [],
        int $existingMemoCount = 0,
    ): array {
        if ($attendance === []) {
            return [];
        }

        $already = [];
        foreach ($existingFlags as $flag) {
            $already[(string) $flag['flag_type'] . '|' . (string) $flag['triggering_date']] = true;
        }
        $excused = [];
        foreach ($excusedDates as $date) {
            $excused[(string) $date] = true;
        }

        $span = $this->buildSpan($attendance, $excused);
        if ($span === []) {
            return [];
        }

        $proposals = [];

        // --- Consecutive late arrivals ------------------------------------
        // A late arrival extends the run; an on-time day, an absence, or an
        // excused day resets it, so the run counts only consecutive late days.
        $lateRun = 0;
        foreach ($span as $date => $info) {
            if ($info['attended'] && $info['late']) {
                $lateRun++;
                if ($lateRun === self::CONSECUTIVE_LATE_THRESHOLD) {
                    $key = 'ConsecutiveLate|' . $date;
                    if (!isset($already[$key])) {
                        $proposals[] = [
                            'flag_type' => 'ConsecutiveLate',
                            'triggering_date' => $date,
                            'notes' => "Three consecutive late arrivals through {$date}; HR memorandum required.",
                        ];
                        $already[$key] = true;
                    }
                }
            } else {
                $lateRun = 0;
            }
        }

        // --- Third tardiness memorandum -----------------------------------
        // Every ConsecutiveLate flag represents one tardiness memorandum.
        // When the third memorandum is reached, flag the employee for HR
        // review of a one-week suspension (REQ006 AC16).
        $memoIndex = 0;
        foreach ($proposals as $proposal) {
            if ($proposal['flag_type'] !== 'ConsecutiveLate') {
                continue;
            }
            $memoIndex++;
            if ($existingMemoCount + $memoIndex === self::TARDINESS_MEMO_CAP) {
                $date = (string) $proposal['triggering_date'];
                $key = 'TardinessMemoCap|' . $date;
                if (!isset($already[$key])) {
                    $proposals[] = [
                        'flag_type' => 'TardinessMemoCap',
                        'triggering_date' => $date,
                        'notes' => "Third tardiness memorandum reached on {$date}; schedule HR review of a one-week suspension.",
                    ];
                    $already[$key] = true;
                }
                break;
            }
        }

        // --- Unexcused absence runs (ConsecutiveAWOL, TwoWeekAbsence) -----
        $absenceRun = 0;
        foreach ($span as $date => $info) {
            if ($info['attended'] || $info['excused']) {
                $absenceRun = 0;
                continue;
            }
            $absenceRun++;
            if ($absenceRun === self::CONSECUTIVE_AWOL_DAYS) {
                $key = 'ConsecutiveAWOL|' . $date;
                if (!isset($already[$key])) {
                    $proposals[] = [
                        'flag_type' => 'ConsecutiveAWOL',
                        'triggering_date' => $date,
                        'notes' => "Three consecutive unexcused absences through {$date}; flag for AWOL/termination policy review.",
                    ];
                    $already[$key] = true;
                }
            }
            if ($absenceRun === self::TWO_WEEK_ABSENCE_DAYS) {
                $key = 'TwoWeekAbsence|' . $date;
                if (!isset($already[$key])) {
                    $proposals[] = [
                        'flag_type' => 'TwoWeekAbsence',
                        'triggering_date' => $date,
                        'notes' => "No reporting for 14 consecutive days ending {$date}; flag for AWOL/termination policy review.",
                    ];
                    $already[$key] = true;
                }
            }
        }

        return $proposals;
    }

    /**
     * Materialise every calendar day from the first attended date to the last
     * attended date so absence runs are measured in calendar days.
     *
     * Days without an attendance row default to unattended; 'Cancelled' rows
     * are treated as not attended (the day was voided).
     *
     * @param list<array{attendance_date:string,time_in:?string,late_minutes:int,status:string}> $attendance
     * @param array<string,bool> $excused
     * @return array<string,array{attended:bool,late:bool,excused:bool}>
     */
    private function buildSpan(array $attendance, array $excused): array
    {
        $present = [];
        $first = null;
        $last = null;
        foreach ($attendance as $row) {
            if ((string) $row['status'] === 'Cancelled') {
                continue;
            }
            $date = (string) $row['attendance_date'];
            $timeIn = $row['time_in'] ?? null;
            $present[$date] = [
                'attended' => $timeIn !== null && $timeIn !== '',
                'late' => (int) ($row['late_minutes'] ?? 0) > 0,
            ];
            if ($first === null || $date < $first) {
                $first = $date;
            }
            if ($last === null || $date > $last) {
                $last = $date;
            }
        }
        if ($first === null || $last === null) {
            return [];
        }

        $span = [];
        $day = DateTimeImmutable::createFromFormat('Y-m-d', $first);
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $last);
        while ($day !== false && $day <= $end) {
            $date = $day->format('Y-m-d');
            $info = $present[$date] ?? ['attended' => false, 'late' => false];
            $span[$date] = [
                'attended' => $info['attended'],
                'late' => $info['late'],
                'excused' => isset($excused[$date]),
            ];
            $day = $day->modify('+1 day');
        }
        return $span;
    }
}