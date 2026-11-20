<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeRepository;

final class EmployeeScheduleUpdateTest extends IntegrationTestCase
{
    public function testUpdatingCurrentSchedulePersistsItsShiftAndBreakValues(): void
    {
        $employeeId = $this->insertEmployee();
        $this->insertWorkSchedule($employeeId);
        $replacementScheduleId = $this->insertScheduleTemplate('13:00:00', '22:00:00', 45);
        $repo = new EmployeeRepository(new Connection([], static::$sharedPdo));

        $repo->updateCurrentSchedule($employeeId, $replacementScheduleId, '2026-01-01');

        $stmt = $this->pdo->prepare(
            "SELECT esa.schedule_id, ws.work_start_time, ws.work_end_time, ws.break_minutes
               FROM employee_schedule_assignment esa
               JOIN work_schedule ws ON ws.schedule_id = esa.schedule_id
              WHERE esa.employee_id = :employee_id
                AND esa.effective_to IS NULL
                AND esa.status = 'Active'"
        );
        $stmt->execute([':employee_id' => $employeeId]);
        $assignment = $stmt->fetch();

        self::assertSame($replacementScheduleId, (int) $assignment['schedule_id']);
        self::assertSame('13:00:00', $assignment['work_start_time']);
        self::assertSame('22:00:00', $assignment['work_end_time']);
        self::assertSame(45, (int) $assignment['break_minutes']);
    }

    private function insertScheduleTemplate(string $timeIn, string $timeOut, int $breakMinutes): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO work_schedule
                (schedule_name, working_days, rest_days, break_minutes,
                 work_start_time, work_end_time, standard_minutes,
                 effective_from, status)
             VALUES (:name, :working_days, :rest_days, :break_minutes,
                     :time_in, :time_out, :standard_minutes,
                     '2026-01-01', 'Active')"
        );
        $stmt->execute([
            ':name' => 'Replacement Shift ' . uniqid(),
            ':working_days' => '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
            ':rest_days' => '["Saturday","Sunday"]',
            ':break_minutes' => $breakMinutes,
            ':time_in' => $timeIn,
            ':time_out' => $timeOut,
            ':standard_minutes' => 480,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
