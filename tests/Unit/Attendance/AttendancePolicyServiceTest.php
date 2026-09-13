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
