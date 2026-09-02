<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\ScheduleRepository;

/**
 * Integration test: schedule assignment overlap rejection (task 5.5)
 *
 * REQ025-REQ031, ADR-0002 §7:
 *   - Assigning a schedule when a prior open assignment exists closes the
 *     prior assignment before inserting the new one (no overlap).
 *   - After assign(), only one active/open assignment exists per employee.
 *   - The close date of the superseded assignment is one day before the new
 *     effective_from.
 */
final class ScheduleAssignmentOverlapTest extends IntegrationTestCase
{
    private ScheduleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ScheduleRepository(
            new Connection([], static::$sharedPdo)
        );
    }

    // -----------------------------------------------------------------------
    // Helpers — insert reusable schedule templates (no employee_id column
    // since work_schedule was decoupled in migration 007)
    // -----------------------------------------------------------------------

    private function insertScheduleTemplate(string $name): int
    {
        $uid = uniqid('SCH_');
        $this->pdo->prepare(
            "INSERT INTO work_schedule
                (schedule_name, working_days, rest_days, break_minutes,
                 work_start_time, work_end_time, standard_minutes, effective_from, status)
             VALUES (:name, :wd, :rd, 60, '07:00:00', '16:00:00', 480, '2026-01-01', 'Active')"
        )->execute([
            ':name' => $name . '_' . $uid,
            ':wd'   => '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
            ':rd'   => '["Saturday","Sunday"]',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // -----------------------------------------------------------------------
    // Test: assigning a second schedule closes the first
    // -----------------------------------------------------------------------

    public function testAssignClosesExistingOpenAssignment(): void
    {
        $employeeId = $this->insertEmployee();
        $sched1     = $this->insertScheduleTemplate('Morning');
        $sched2     = $this->insertScheduleTemplate('Afternoon');

        // Assign first schedule
        $this->repo->assign([
            'employee_id'    => $employeeId,
            'schedule_id'    => $sched1,
            'effective_from' => '2026-01-01',
        ]);

        // Assign second schedule — should close sched1
        $this->repo->assign([
            'employee_id'    => $employeeId,
            'schedule_id'    => $sched2,
            'effective_from' => '2026-06-01',
        ]);

        // Only one open assignment should remain
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM employee_schedule_assignment
              WHERE employee_id = :emp AND effective_to IS NULL"
        );
        $stmt->execute([':emp' => $employeeId]);
        $this->assertSame(1, (int) $stmt->fetchColumn(),
            'Exactly one open assignment should exist after re-assign.');

        // First assignment should be closed at 2026-05-31
        $stmt = $this->pdo->prepare(
            "SELECT effective_to FROM employee_schedule_assignment
              WHERE employee_id = :emp AND schedule_id = :sch"
        );
        $stmt->execute([':emp' => $employeeId, ':sch' => $sched1]);
        $row = $stmt->fetch();
        $this->assertSame('2026-05-31', $row['effective_to'],
            'Previous assignment effective_to should be day before new effective_from.');
    }

    // -----------------------------------------------------------------------
    // Test: open assignment is the newly assigned one
    // -----------------------------------------------------------------------

    public function testNewAssignmentIsOpenAfterReassign(): void
    {
        $employeeId = $this->insertEmployee();
        $sched1     = $this->insertScheduleTemplate('Day');
        $sched2     = $this->insertScheduleTemplate('Night');

        $this->repo->assign([
            'employee_id'    => $employeeId,
            'schedule_id'    => $sched1,
            'effective_from' => '2026-01-01',
        ]);

        $this->repo->assign([
            'employee_id'    => $employeeId,
            'schedule_id'    => $sched2,
            'effective_from' => '2026-09-01',
        ]);

        $stmt = $this->pdo->prepare(
            "SELECT schedule_id FROM employee_schedule_assignment
              WHERE employee_id = :emp AND effective_to IS NULL"
        );
        $stmt->execute([':emp' => $employeeId]);
        $row = $stmt->fetch();
        $this->assertSame($sched2, (int) $row['schedule_id'],
            'The open assignment should be the most recently assigned schedule.');
    }

    // -----------------------------------------------------------------------
    // Test: first assignment with no prior — no row to close
    // -----------------------------------------------------------------------

    public function testFirstAssignmentHasNoEffectiveTo(): void
    {
        $employeeId = $this->insertEmployee();
        $sched      = $this->insertScheduleTemplate('Regular');

        $this->repo->assign([
            'employee_id'    => $employeeId,
            'schedule_id'    => $sched,
            'effective_from' => '2026-01-01',
        ]);

        $stmt = $this->pdo->prepare(
            "SELECT effective_to FROM employee_schedule_assignment
              WHERE employee_id = :emp"
        );
        $stmt->execute([':emp' => $employeeId]);
        $row = $stmt->fetch();
        $this->assertNull($row['effective_to'],
            'First assignment should have effective_to = NULL.');
    }
}
