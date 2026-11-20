<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance;

use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Application\AttendancePolicyService;
use Wbpms\Infrastructure\Database\Connection;

final class AttendancePolicyServiceTest extends TestCase
{
    public function testItPersistsFlagDetailsAndDoesNotDuplicateARepeatedEvaluation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance (employee_id INTEGER, attendance_date TEXT, time_in TEXT, late_minutes INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE attendance_policy_flag (
            flag_id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            flag_type TEXT NOT NULL,
            triggering_date TEXT NOT NULL,
            status TEXT NOT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (employee_id, flag_type, triggering_date)
        )');
        $pdo->exec('CREATE TABLE request (request_id INTEGER PRIMARY KEY, employee_id INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE leave_request_detail (request_id INTEGER PRIMARY KEY, start_date TEXT, end_date TEXT)');
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER, schedule_id INTEGER, effective_from TEXT, effective_to TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER, working_days TEXT)');
        $pdo->exec('CREATE TABLE holiday_calendar (holiday_date TEXT, status TEXT)');
        $pdo->exec("INSERT INTO attendance VALUES
            (42, '2026-08-03', '08:10:00', 10, 'Complete'),
            (42, '2026-08-04', '08:05:00', 5, 'Complete'),
            (42, '2026-08-05', '08:15:00', 15, 'Complete')");

        $service = new AttendancePolicyService(new Connection([], $pdo));
        $created = $service->evaluateEmployee(42, '2026-08-01', '2026-08-10');

        self::assertCount(1, $created);
        self::assertSame('ConsecutiveLate', $created[0]['flag_type']);
        self::assertStringContainsString('HR memorandum required', $created[0]['notes']);
        self::assertSame(
            $created[0]['notes'],
            $pdo->query('SELECT notes FROM attendance_policy_flag')->fetchColumn(),
        );
        self::assertSame([], $service->evaluateEmployee(42, '2026-08-01', '2026-08-10'));
    }

    public function testItReconstructsEveryAbsenceDateForAwolEvidence(): void
    {
        $method = new ReflectionMethod(AttendancePolicyService::class, 'withAbsenceEvidenceDates');
        $flags = $method->invoke(new AttendancePolicyService(new Connection([])), [[
            'flag_type' => 'ConsecutiveAWOL',
            'triggering_date' => '2026-08-06',
        ], [
            'flag_type' => 'TwoWeekAbsence',
            'triggering_date' => '2026-08-15',
        ]]);

        self::assertSame('2026-08-04, 2026-08-05, 2026-08-06', $flags[0]['absence_dates']);
        self::assertSame(
            '2026-08-02, 2026-08-03, 2026-08-04, 2026-08-05, 2026-08-06, 2026-08-07, 2026-08-08, 2026-08-09, 2026-08-10, 2026-08-11, 2026-08-12, 2026-08-13, 2026-08-14, 2026-08-15',
            $flags[1]['absence_dates'],
        );
    }

    public function testAwolEvidenceSkipsAnEmployeeRestDay(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER, schedule_id INTEGER, effective_from TEXT, effective_to TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER, working_days TEXT)');
        $pdo->exec('CREATE TABLE holiday_calendar (holiday_date TEXT, status TEXT)');
        $pdo->exec("INSERT INTO work_schedule VALUES (1, '[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\"]')");
        $pdo->exec("INSERT INTO employee_schedule_assignment VALUES (42, 1, '2026-01-01', NULL, 'Active')");

        $method = new ReflectionMethod(AttendancePolicyService::class, 'withAbsenceEvidenceDates');
        $flags = $method->invoke(new AttendancePolicyService(new Connection([], $pdo)), [[
            'employee_id' => 42,
            'flag_type' => 'ConsecutiveAWOL',
            'triggering_date' => '2026-07-21',
        ]]);

        self::assertSame('2026-07-17, 2026-07-20, 2026-07-21', $flags[0]['absence_dates']);
    }

    /**
     * TC: 3rd Tardiness Memo evidence must show the actual three consecutive
     * late days, not a fixed 2-day calendar window that can include an absent
     * day when late days span a weekend or other gap.
     *
     * Scenario: late on 07/13, 07/14, 07/15 (Mon–Wed); 07/12 (Sun) is absent
     * with late_minutes > 0 from a bad import row (time_in IS NULL).
     * Evidence must show 07/13, 07/14, 07/15 — not 07/12.
     */
    public function testLateEvidenceExcludesAbsentRowsAndPicksLastThreeAttendedLateRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance (
            employee_id INTEGER,
            attendance_date TEXT,
            time_in TEXT,
            late_minutes INTEGER,
            status TEXT
        )');
        $pdo->exec('CREATE TABLE attendance_policy_flag (
            flag_id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            flag_type TEXT NOT NULL,
            triggering_date TEXT NOT NULL,
            status TEXT NOT NULL,
            notes TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            action_taken TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (employee_id, flag_type, triggering_date)
        )');
        $pdo->exec('CREATE TABLE employee (
            employee_id INTEGER PRIMARY KEY,
            last_name TEXT,
            first_name TEXT,
            employee_number TEXT,
            position TEXT,
            status TEXT
        )');
        $pdo->exec('CREATE TABLE employee_hr_notice (
            notice_id INTEGER PRIMARY KEY,
            policy_flag_id INTEGER UNIQUE,
            title TEXT,
            issued_at TEXT,
            acknowledged_at TEXT
        )');
        $pdo->exec('CREATE TABLE audit_logs (
            user_id INTEGER, event_type TEXT, action_performed TEXT,
            table_affected TEXT, record_id INTEGER, description TEXT,
            action_at TEXT, created_at TEXT
        )');

        // 07/12: absent row with late_minutes set (bad import) — time_in IS NULL
        // 07/13–07/15: attended and late (the actual consecutive run)
        $pdo->exec("INSERT INTO attendance VALUES
            (42, '2026-07-12', NULL,         2,  'Incomplete'),
            (42, '2026-07-13', '07:32:00',   2,  'Complete'),
            (42, '2026-07-14', '12:03:00',   273,'Complete'),
            (42, '2026-07-15', '07:41:00',   11, 'Complete')");

        // withLateEvidenceDates for a ConsecutiveLate flag with triggering_date 07/15
        $method = new ReflectionMethod(AttendancePolicyService::class, 'withLateEvidenceDates');
        $flags = $method->invoke(new AttendancePolicyService(new Connection([], $pdo)), [[
            'employee_id' => 42,
            'flag_type' => 'ConsecutiveLate',
            'triggering_date' => '2026-07-15',
        ]]);

        // Exact evidence string: three consecutive late days in ascending order,
        // absent row on 07/12 (time_in IS NULL) must not appear.
        self::assertSame(
            '2026-07-13 07:32 (+2 min), 2026-07-14 12:03 (+273 min), 2026-07-15 07:41 (+11 min)',
            (string) ($flags[0]['late_dates'] ?? ''),
        );
    }

    /**
     * A TardinessMemoCap raised on the first day of a new late run must show
     * only that run's rows in evidence — not rows from an earlier distinct run.
     */
    public function testLateEvidenceDoesNotPullRowsFromAPriorDisconnectedRun(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance (
            employee_id INTEGER,
            attendance_date TEXT,
            time_in TEXT,
            late_minutes INTEGER,
            status TEXT
        )');

        // Prior run: 07/06, 07/07, 07/08 triggered a ConsecutiveLate flag.
        // 07/09: on time — resets the run.
        // New run: 07/13 alone triggers TardinessMemoCap (3rd memo overall).
        // Evidence must show only 07/13, not rows from 07/06–07/08.
        $pdo->exec("INSERT INTO attendance VALUES
            (42, '2026-07-06', '08:05:00', 5,  'Complete'),
            (42, '2026-07-07', '08:08:00', 8,  'Complete'),
            (42, '2026-07-08', '08:12:00', 12, 'Complete'),
            (42, '2026-07-09', '08:00:00', 0,  'Complete'),
            (42, '2026-07-13', '08:03:00', 3,  'Complete')");

        $method = new ReflectionMethod(AttendancePolicyService::class, 'withLateEvidenceDates');
        $flags = $method->invoke(new AttendancePolicyService(new Connection([], $pdo)), [[
            'employee_id' => 42,
            'flag_type' => 'TardinessMemoCap',
            'triggering_date' => '2026-07-13',
        ]]);

        $lateDates = (string) ($flags[0]['late_dates'] ?? '');
        // Only the current run (single day) is evidence; prior run excluded.
        self::assertSame('2026-07-13 08:03 (+3 min)', $lateDates);
        self::assertStringNotContainsString('2026-07-06', $lateDates);
        self::assertStringNotContainsString('2026-07-07', $lateDates);
        self::assertStringNotContainsString('2026-07-08', $lateDates);
    }

    /** An unknown schedule must never create a disciplinary AWOL flag. */
    public function testNoAwolFlagIsCreatedWhenEmployeeHasNoScheduleAssignment(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance (employee_id INTEGER, attendance_date TEXT, time_in TEXT, late_minutes INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE attendance_policy_flag (
            flag_id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            flag_type TEXT NOT NULL,
            triggering_date TEXT NOT NULL,
            status TEXT NOT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (employee_id, flag_type, triggering_date)
        )');
        $pdo->exec('CREATE TABLE request (request_id INTEGER PRIMARY KEY, employee_id INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE leave_request_detail (request_id INTEGER PRIMARY KEY, start_date TEXT, end_date TEXT)');
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER, schedule_id INTEGER, effective_from TEXT, effective_to TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER, working_days TEXT)');
        $pdo->exec('CREATE TABLE holiday_calendar (holiday_date TEXT, status TEXT)');

        // Three calendar-day absences exist, but no schedule assignment exists
        // to establish whether they are scheduled workdays.
        $pdo->exec("INSERT INTO attendance VALUES
            (99, '2026-08-03', '08:00:00', 0, 'Complete'),
            (99, '2026-08-07', '08:00:00', 0, 'Complete')");

        $service = new AttendancePolicyService(new Connection([], $pdo));
        $created = $service->evaluateEmployee(99, '2026-08-01', '2026-08-10');

        $awolFlags = array_values(array_filter($created, fn(array $f): bool => $f['flag_type'] === 'ConsecutiveAWOL'));
        self::assertSame([], $awolFlags, 'AWOL requires an active schedule assignment.');
    }

    public function testHrCanIssueOneEmployeeNoticeWhenReviewingAFlag(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance_policy_flag (
            flag_id INTEGER PRIMARY KEY,
            employee_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            action_taken TEXT NULL,
            notes TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            updated_at TEXT NULL
        )');
        $pdo->exec('CREATE TABLE employee_hr_notice (
            notice_id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            policy_flag_id INTEGER NOT NULL UNIQUE,
            issued_by INTEGER NOT NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            issued_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE audit_logs (
            user_id INTEGER,
            event_type TEXT,
            action_performed TEXT,
            table_affected TEXT,
            record_id INTEGER,
            description TEXT,
            action_at TEXT,
            created_at TEXT
        )');
        $pdo->exec("INSERT INTO attendance_policy_flag (flag_id, employee_id, status) VALUES (7, 42, 'Pending')");

        $service = new AttendancePolicyService(new Connection([], $pdo));
        $service->review(7, 9, [
            'status' => 'Reviewed',
            'action_taken' => 'Memorandum issued',
            'notes' => 'Reviewed attendance evidence.',
            'notice_title' => 'Attendance memorandum',
            'notice_body' => 'Please review your attendance record and coordinate with HR.',
        ]);

        self::assertSame('Reviewed', $pdo->query('SELECT status FROM attendance_policy_flag WHERE flag_id = 7')->fetchColumn());
        self::assertSame('Attendance memorandum', $pdo->query('SELECT title FROM employee_hr_notice')->fetchColumn());
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been issued');
        $service->review(7, 9, [
            'status' => 'Closed',
            'action_taken' => 'Memorandum issued',
            'notes' => '',
            'notice_title' => 'Attendance memorandum',
            'notice_body' => 'Duplicate notice.',
        ]);
    }
}
